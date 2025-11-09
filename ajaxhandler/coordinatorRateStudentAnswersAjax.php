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
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['action']) && $data['action'] === 'getReviewedStudents') {
        // Fetch all reviewed student answers and ratings
        $reviewedIds = [];
        $ratings = [];
        $sql = "SELECT ce.student_evaluation_id, ce.STUDENT_ID, ce.rating FROM coordinator_evaluation ce";
        $stmt = $db->conn->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $reviewedIds[] = $row['student_evaluation_id'];
            $ratings[] = [
                'student_evaluation_id' => $row['student_evaluation_id'],
                'STUDENT_ID' => $row['STUDENT_ID'],
                'rating' => $row['rating']
            ];
        }
        echo json_encode(['success' => true, 'reviewedIds' => $reviewedIds, 'ratings' => $ratings]);
        exit;
    }
    // --- NEW: Get reviewed evaluation for a student ---
    if (isset($data['action']) && $data['action'] === 'getReviewedEvaluation' && isset($data['studentId'])) {
        $studentId = $data['studentId'];
        $sql = "SELECT se.id as student_evaluation_id, eq.question_text, se.answer, ce.rating
                FROM coordinator_evaluation ce
                JOIN student_evaluation se ON ce.student_evaluation_id = se.id
                JOIN evaluation_questions eq ON se.question_id = eq.question_id
                WHERE ce.STUDENT_ID = :student_id";
        $stmt = $db->conn->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'evaluations' => $evaluations]);
        exit;
    }
    // Save coordinator ratings for multiple student answers
    $coordinator_id = $data['coordinator_id'];
    $ratings = $data['ratings'];
    $sql = "INSERT INTO coordinator_evaluation (student_evaluation_id, STUDENT_ID, coordinator_id, rating) VALUES (:student_evaluation_id, :student_id, :coordinator_id, :rating)";
    $stmt = $db->conn->prepare($sql);
    $allSuccess = true;
    foreach ($ratings as $r) {
        $student_evaluation_id = (int)$r['student_evaluation_id'];
        $student_id = isset($r['STUDENT_ID']) ? $r['STUDENT_ID'] : $r['student_id'];
        $rating = (int)$r['rating'];
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
        $sqlAvg = "SELECT eq.category, ce.rating FROM coordinator_evaluation ce 
            JOIN student_evaluation se ON ce.student_evaluation_id = se.id 
            JOIN evaluation_questions eq ON se.question_id = eq.question_id 
            WHERE ce.STUDENT_ID = :student_id";
        $stmtAvg = $db->conn->prepare($sqlAvg);
        $stmtAvg->execute([':student_id' => $studentIdForAvg]);
        $allRatings = $stmtAvg->fetchAll(PDO::FETCH_ASSOC);

        $softSkillRatings = [];
        $commSkillRatings = [];
        foreach ($allRatings as $row) {
            $cat = strtolower(trim($row['category']));
            error_log("[AVG] Student: $studentIdForAvg, Category: '$cat', Rating: {$row['rating']}");
            // Robust matching: partial, case-insensitive
            if (strpos($cat, 'soft') !== false) {
                error_log("[MATCH] Category contains 'soft', rating: {$row['rating']}");
                $softSkillRatings[] = $row['rating'];
            } elseif (strpos($cat, 'comm') !== false) {
                error_log("[MATCH] Category contains 'comm', rating: {$row['rating']}");
                $commSkillRatings[] = $row['rating'];
            } else {
                error_log("[NO MATCH] Category: '$cat' not matched for avg");
            }
        }
        $softAvg = count($softSkillRatings) ? array_sum($softSkillRatings) / count($softSkillRatings) : null;
        $commAvg = count($commSkillRatings) ? array_sum($commSkillRatings) / count($commSkillRatings) : null;

        // Update pre_assessment table
    error_log("[AVG] Calculated soft_skill avg for STUDENT_ID $studentIdForAvg: $softAvg");
    error_log("[AVG] Calculated communication_skill avg for STUDENT_ID $studentIdForAvg: $commAvg");
//    // Cons ole log for debugging in browser (if running via AJAX)
//     echo "<script>console.log('[AVG] soft_skill avg for STUDENT_ID $studentIdForAvg: $softAvg');console.log('[AVG] communication_skill avg for STUDENT_ID $studentIdForAvg: $commAvg');</script>";
        $sqlUpdate = "UPDATE pre_assessment SET soft_skill = :softAvg, communication_skill = :commAvg WHERE STUDENT_ID = :student_id";
        $stmtUpdate = $db->conn->prepare($sqlUpdate);
        $result = $stmtUpdate->execute([
            ':softAvg' => $softAvg,
            ':commAvg' => $commAvg,
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

