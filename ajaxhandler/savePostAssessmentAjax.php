<?php
session_start();
if (!isset($_SESSION["student_user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$student_id = $_SESSION["student_user"];

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . "/InternConnect/database/database.php";

try {
    $db = new Database();
    $conn = $db->conn;
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['questions']) || !is_array($data['questions'])) {
        throw new Exception("Invalid data");
    }
    $savedQuestions = [];
    // Debug: Display current database and post_assessment columns in response
    $debugInfo = [];
    try {
        $dbNameStmt = $conn->query('SELECT DATABASE()');
        $currentDb = $dbNameStmt->fetchColumn();
        $debugInfo['database'] = $currentDb;
        $columnsStmt = $conn->query('SHOW COLUMNS FROM post_assessment');
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        $debugInfo['post_assessment_columns'] = $columns;
    } catch (Exception $debugEx) {
        $debugInfo['debug_error'] = $debugEx->getMessage();
    }
    foreach ($data['questions'] as $q) {
        if ($q['category'] === 'Personal and Interpersonal Skills') {
            // Lookup or create in student_questions
            $stmtQ = $conn->prepare("SELECT id FROM student_questions WHERE student_id = ? AND category = ? AND question_number = ?");
            $stmtQ->execute([$student_id, $q['category'], $q['question_number']]);
            $rowQ = $stmtQ->fetch(PDO::FETCH_ASSOC);
            if ($rowQ) {
                $question_id = $rowQ['id'];
            } else {
                // Get question_id from evaluation_questions
                $stmtEval = $conn->prepare("SELECT question_id FROM evaluation_questions WHERE category = ? AND question_text = ?");
                $stmtEval->execute([$q['category'], $q['question_text']]);
                $rowEval = $stmtEval->fetch(PDO::FETCH_ASSOC);
                if ($rowEval) {
                    // Insert into student_questions
                    $stmtInsert = $conn->prepare("INSERT INTO student_questions (student_id, category, question_number, question_text) VALUES (?, ?, ?, ?)");
                    $stmtInsert->execute([$student_id, $q['category'], $q['question_number'], $q['question_text']]);
                    $question_id = $conn->lastInsertId();
                } else {
                    $savedQuestions[] = [
                        'question_text' => $q['question_text'],
                        'self_rating' => $q['self_rating'],
                        'category' => $q['category'],
                        'error' => 'No matching evaluation_questions.question_id found'
                    ];
                    continue;
                }
            }
            // Save rating in post_assessment with correct question_id
            $stmt2 = $conn->prepare("INSERT INTO post_assessment (student_id, question_id, self_rating, category) VALUES (?, ?, ?, ?)");
            $stmt2->execute([
                $student_id,
                $question_id,
                $q['self_rating'],
                $q['category']
            ]);
            $savedQuestions[] = [
                'question_text' => $q['question_text'],
                'self_rating' => $q['self_rating'],
                'category' => $q['category'],
                'question_id' => $question_id
            ];
        } else {
            // Find the correct student_questions.id for this question
            $stmtQ = $conn->prepare("SELECT id FROM student_questions WHERE student_id = ? AND category = ? AND question_number = ?");
            $stmtQ->execute([$student_id, $q['category'], $q['question_number']]);
            $rowQ = $stmtQ->fetch(PDO::FETCH_ASSOC);
            if ($rowQ) {
                $question_id = $rowQ['id'];
                // Save rating in post_assessment with correct question_id
                $stmt2 = $conn->prepare("INSERT INTO post_assessment (student_id, question_id, self_rating, category) VALUES (?, ?, ?, ?)");
                $stmt2->execute([
                    $student_id,
                    $question_id,
                    $q['self_rating'],
                    $q['category']
                ]);
                $savedQuestions[] = [
                    'question_text' => $q['question_text'],
                    'self_rating' => $q['self_rating'],
                    'category' => $q['category'],
                    'question_id' => $question_id
                ];
            } else {
                $savedQuestions[] = [
                    'question_text' => $q['question_text'],
                    'self_rating' => $q['self_rating'],
                    'category' => $q['category'],
                    'error' => 'No matching student_questions.id found'
                ];
            }
        }
    }
    echo json_encode(["success" => true, "saved" => $savedQuestions, "debug" => $debugInfo]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage(), "debug" => isset($debugInfo) ? $debugInfo : null]);
}

