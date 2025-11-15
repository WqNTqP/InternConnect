<?php
require_once '../database/database.php';
header('Content-Type: application/json');

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all student answers for evaluation questions
    $sql = "SELECT se.id as student_evaluation_id, se.STUDENT_ID, id.NAME, id.SURNAME, eq.category, eq.question_text, se.answer
            FROM student_evaluation se
            JOIN evaluation_questions eq ON se.question_id = eq.question_id
            JOIN interns_details id ON se.STUDENT_ID = id.STUDENT_ID
            ORDER BY se.STUDENT_ID, eq.category";
    $stmt = $db->conn->prepare($sql);
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'answers' => $answers]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle both JSON and form data intelligently
    $rawInput = file_get_contents('php://input');
    
    // Check content type to determine data format
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (!empty($rawInput) && (strpos($contentType, 'application/json') !== false || empty($_POST))) {
        // JSON data (for actions like getReviewedStudents)
        $data = json_decode($rawInput, true);
        error_log("[DEBUG] Using JSON data: " . var_export($data, true));
    } else {
        // Form data (for rating submissions)
        $data = $_POST;
        error_log("[DEBUG] Using form data from \$_POST: " . var_export($_POST, true));
    }
    
    if (isset($data['action']) && $data['action'] === 'getReviewedStudents') {
        error_log("[GET_REVIEWED] Action called - fetching reviewed students");
        // Fetch all reviewed student IDs and ratings
        $reviewedStudentIds = [];
        $ratings = [];
        $sql = "SELECT DISTINCT ce.STUDENT_ID FROM coordinator_evaluation ce";
        $stmt = $db->conn->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $reviewedStudentIds[] = $row['STUDENT_ID'];
        }
        error_log("[GET_REVIEWED] Found reviewed student IDs: " . var_export($reviewedStudentIds, true));
        
        // Also get all individual ratings for detailed review
        $sql = "SELECT ce.student_evaluation_id, ce.STUDENT_ID, ce.rating FROM coordinator_evaluation ce";
        $stmt = $db->conn->prepare($sql);
        $stmt->execute();
        $ratingsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ratingsResults as $row) {
            $ratings[] = [
                'student_evaluation_id' => $row['student_evaluation_id'],
                'STUDENT_ID' => $row['STUDENT_ID'],
                'rating' => $row['rating']
            ];
        }
        echo json_encode(['success' => true, 'reviewedIds' => $reviewedStudentIds, 'ratings' => $ratings]);
        exit;
    }
    // --- NEW: Get reviewed evaluation for a student ---
    if (isset($data['action']) && $data['action'] === 'getReviewedEvaluation' && isset($data['studentId'])) {
        $studentId = $data['studentId'];
        $sql = "SELECT se.id as student_evaluation_id, eq.question_text, eq.category, se.answer, ce.rating
                FROM coordinator_evaluation ce
                JOIN student_evaluation se ON ce.student_evaluation_id = se.id
                JOIN evaluation_questions eq ON se.question_id = eq.question_id
                WHERE ce.STUDENT_ID = :student_id
                ORDER BY eq.category, eq.question_id";
        $stmt = $db->conn->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'evaluations' => $evaluations]);
        exit;
    }
    // Save coordinator ratings for multiple student answers
    if (!isset($data['coordinator_id']) || !isset($data['ratings'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required data: coordinator_id or ratings']);
        exit;
    }
    
    $coordinator_id = $data['coordinator_id'];
    $ratings = $data['ratings'];
    
    // Debug logging
    error_log("[SAVE] Received coordinator_id: " . var_export($coordinator_id, true));
    error_log("[SAVE] Received ratings structure: " . var_export($ratings, true));
    
    if (empty($coordinator_id) || !is_array($ratings) || empty($ratings)) {
        echo json_encode(['success' => false, 'message' => 'Invalid coordinator_id or ratings data']);
        exit;
    }
    
    $sql = "INSERT INTO coordinator_evaluation (student_evaluation_id, STUDENT_ID, coordinator_id, rating) VALUES (:student_evaluation_id, :student_id, :coordinator_id, :rating)";
    $stmt = $db->conn->prepare($sql);
    $allSuccess = true;
    foreach ($ratings as $r) {
        // Validate individual rating data
        if (!is_array($r) || !isset($r['student_evaluation_id']) || !isset($r['rating'])) {
            error_log("[RATING ERROR] Invalid rating data structure: " . json_encode($r));
            $allSuccess = false;
            continue;
        }
        
        $student_evaluation_id = (int)$r['student_evaluation_id'];
        $student_id = isset($r['STUDENT_ID']) ? $r['STUDENT_ID'] : (isset($r['student_id']) ? $r['student_id'] : null);
        $rating = (int)$r['rating'];
        
        if (empty($student_evaluation_id) || empty($rating)) {
            error_log("[RATING ERROR] Missing required fields: student_evaluation_id or rating");
            $allSuccess = false;
            continue;
        }
        // If student_id looks like an INTERN_ID (e.g., 207), map it to STUDENT_ID
        if (is_numeric($student_id) && $student_id < 1000) {
            $stmt_map = $db->conn->prepare("SELECT STUDENT_ID FROM interns_details WHERE INTERNS_ID = ? LIMIT 1");
            $stmt_map->execute([$student_id]);
            $row_map = $stmt_map->fetch(PDO::FETCH_ASSOC);
            if ($row_map && !empty($row_map['STUDENT_ID'])) {
                $student_id = $row_map['STUDENT_ID'];
            }
        }
        // Fallback: Try to get student_id from student_evaluation_id if still not valid
        if ($student_id === 'undefined' || empty($student_id)) {
            $stmt_lookup = $db->conn->prepare("SELECT STUDENT_ID FROM student_evaluation WHERE id = ?");
            $stmt_lookup->execute([$student_evaluation_id]);
            $row = $stmt_lookup->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['STUDENT_ID'])) {
                $student_id = $row['STUDENT_ID'];
            }
        }
        try {
            // Check if a rating already exists for this student_evaluation_id and coordinator_id
            $stmt_check = $db->conn->prepare("SELECT id FROM coordinator_evaluation WHERE student_evaluation_id = ? AND coordinator_id = ?");
            $stmt_check->execute([$student_evaluation_id, $coordinator_id]);
            $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                // Update existing rating
                $stmt_update = $db->conn->prepare("UPDATE coordinator_evaluation SET rating = :rating, STUDENT_ID = :student_id WHERE id = :id");
                $success = $stmt_update->execute([
                    ':rating' => $rating,
                    ':student_id' => $student_id,
                    ':id' => $existing['id']
                ]);
            } else {
                // Insert new rating
                $success = $stmt->execute([
                    ':student_evaluation_id' => $student_evaluation_id,
                    ':student_id' => $student_id,
                    ':coordinator_id' => $coordinator_id,
                    ':rating' => $rating
                ]);
            }
            if (!$success) {
                $errorMsg = "Failed to save rating for student_evaluation_id: $student_evaluation_id, student_id: $student_id, coordinator_id: $coordinator_id, rating: $rating";
                error_log("[RATING ERROR] " . $errorMsg);
                $allSuccess = false;
            }
        } catch (PDOException $e) {
            $errorMsg = "PDOException: " . $e->getMessage() . " | student_evaluation_id: $student_evaluation_id, student_id: $student_id, coordinator_id: $coordinator_id, rating: $rating";
            error_log("[RATING ERROR] " . $errorMsg);
            $allSuccess = false;
        }
    }

    // --- Calculate and update averages in pre_assessment ---
    // Get all ratings for this student, joined with question category
    $studentIdForAvg = isset($student_id) ? $student_id : (isset($ratings[0]['STUDENT_ID']) ? $ratings[0]['STUDENT_ID'] : (isset($ratings[0]['student_id']) ? $ratings[0]['student_id'] : null));
    if ($studentIdForAvg) {
        // Correct join: coordinator_evaluation -> student_evaluation -> evaluation_questions
        $sqlAvg = "SELECT eq.category, eq.question_id, ce.rating, ce.student_evaluation_id FROM coordinator_evaluation ce 
            JOIN student_evaluation se ON ce.student_evaluation_id = se.id 
            JOIN evaluation_questions eq ON se.question_id = eq.question_id 
            WHERE ce.STUDENT_ID = :student_id";
        $stmtAvg = $db->conn->prepare($sqlAvg);
        $stmtAvg->execute([':student_id' => $studentIdForAvg]);
        $allRatings = $stmtAvg->fetchAll(PDO::FETCH_ASSOC);

        $softSkillRatings = [];
        $commSkillRatings = [];
        $techSkillRatings = [];
        foreach ($allRatings as $row) {
            $cat = strtolower(trim($row['category']));
            error_log("[AVG] Student: $studentIdForAvg, Question ID: {$row['question_id']}, Category: '$cat', Rating: {$row['rating']}");
            // Robust matching: partial, case-insensitive (pre-assessment only: Soft, Communication, Technical)
            if (strpos($cat, 'soft') !== false) {
                error_log("[MATCH] Category '$cat' contains 'soft', rating: {$row['rating']}");
                $softSkillRatings[] = $row['rating'];
            } elseif (strpos($cat, 'communication') !== false || strpos($cat, 'comm') !== false) {
                error_log("[MATCH] Category '$cat' contains 'communication/comm', rating: {$row['rating']}");
                $commSkillRatings[] = $row['rating'];
            } elseif (strpos($cat, 'technical') !== false) {
                error_log("[MATCH] Category '$cat' contains 'technical', rating: {$row['rating']}");
                $techSkillRatings[] = $row['rating'];
            } else {
                error_log("[NO MATCH] Category: '$cat' not matched for pre-assessment (Personal/Interpersonal skills belong to post-assessment)");
            }
        }
        // Calculate averages and log results
        $softAvg = count($softSkillRatings) ? array_sum($softSkillRatings) / count($softSkillRatings) : null;
        $commAvg = count($commSkillRatings) ? array_sum($commSkillRatings) / count($commSkillRatings) : null;
        $techAvg = count($techSkillRatings) ? array_sum($techSkillRatings) / count($techSkillRatings) : null;
        
        // Debug summary
        error_log("[AVG SUMMARY] Student $studentIdForAvg - Soft: " . count($softSkillRatings) . " ratings = $softAvg");
        error_log("[AVG SUMMARY] Student $studentIdForAvg - Comm: " . count($commSkillRatings) . " ratings = $commAvg");  
        error_log("[AVG SUMMARY] Student $studentIdForAvg - Tech: " . count($techSkillRatings) . " ratings = $techAvg");

        // Update pre_assessment table (only the three pre-assessment categories)
        error_log("[AVG] Calculated soft_skill avg for STUDENT_ID $studentIdForAvg: $softAvg");
        error_log("[AVG] Calculated communication_skill avg for STUDENT_ID $studentIdForAvg: $commAvg");
        error_log("[AVG] Calculated technical_skill avg for STUDENT_ID $studentIdForAvg: $techAvg");
        
        $sqlUpdate = "UPDATE pre_assessment SET soft_skill = :softAvg, communication_skill = :commAvg, technical_skill = :techAvg WHERE STUDENT_ID = :student_id";
        $stmtUpdate = $db->conn->prepare($sqlUpdate);
        $result = $stmtUpdate->execute([
            ':softAvg' => $softAvg,
            ':commAvg' => $commAvg,
            ':techAvg' => $techAvg,
            ':student_id' => $studentIdForAvg
        ]);
        error_log("[AVG] Update pre_assessment result for STUDENT_ID $studentIdForAvg: " . ($result ? 'SUCCESS' : 'FAIL'));
    }

    if (!$allSuccess && isset($errorMsg)) {
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    } else {
        echo json_encode(['success' => $allSuccess]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);

