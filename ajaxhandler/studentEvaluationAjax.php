<?php
require_once '../database/database.php';
header('Content-Type: application/json');

$db = new Database();


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch active evaluation questions
    $questions = [];
            $sql = "SELECT question_id, category, question_text FROM evaluation_questions WHERE status = 1";
    $stmt = $db->conn->prepare($sql);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'questions' => $questions]);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    // If requesting student answers for view mode
    if (isset($data['action']) && $data['action'] === 'getStudentAnswers' && isset($data['student_id'])) {
        $student_id = $data['student_id'];
        // Convert INTERNS_ID to STUDENT_ID if needed
        $student_id_converted = $student_id;
        if (is_numeric($student_id)) {
            $stmt_lookup = $db->conn->prepare("SELECT STUDENT_ID FROM interns_details WHERE INTERNS_ID = ?");
            $stmt_lookup->execute([$student_id]);
            $row = $stmt_lookup->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['STUDENT_ID'])) {
                $student_id_converted = $row['STUDENT_ID'];
            }
        }
        $sql = "SELECT se.id as student_evaluation_id, se.student_id, eq.category, eq.question_text, se.answer FROM student_evaluation se JOIN evaluation_questions eq ON se.question_id = eq.question_id WHERE se.student_id = ? ORDER BY eq.category, eq.question_id";
        $stmt = $db->conn->prepare($sql);
        $stmt->execute([$student_id_converted]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'answers' => $answers]);
        exit;
    }

    // Save student answers
    $student_id = $data['student_id'] ?? null;
    $answers = $data['answers'] ?? null; // array of ['question_id' => ..., 'answer' => ...]
    $success = true;
    $errorMsg = '';
    if (!$student_id || !$answers || !is_array($answers)) {
    $missing = [];
    if (!$student_id) $missing[] = 'student_id';
    if (!$answers) $missing[] = 'answers';
    if (!is_array($answers)) $missing[] = 'answers not array';
    $msg = 'Missing: ' . implode(', ', $missing) . '. Data: ' . json_encode($data);
    error_log("[Evaluation] " . $msg);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
    }
    // Convert INTERNS_ID to STUDENT_ID if needed
    $student_id_converted = $student_id;
    if (is_numeric($student_id)) {
        $stmt_lookup = $db->conn->prepare("SELECT STUDENT_ID FROM interns_details WHERE INTERNS_ID = ?");
        $stmt_lookup->execute([$student_id]);
        $row = $stmt_lookup->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['STUDENT_ID'])) {
            $student_id_converted = $row['STUDENT_ID'];
        }
    }
    $sql = "INSERT INTO student_evaluation (student_id, question_id, answer) VALUES (:student_id, :question_id, :answer)";
    $stmt = $db->conn->prepare($sql);
    foreach ($answers as $ans) {
        $question_id = isset($ans['question_id']) ? (int)$ans['question_id'] : null;
        $answer = $ans['answer'] ?? null;
        if (!$question_id || $answer === null) {
            error_log("[Evaluation] Invalid answer entry: " . json_encode($ans));
            $success = false;
            $errorMsg = 'Invalid answer entry';
            break;
        }
        if (!$stmt->execute([':student_id' => $student_id_converted, ':question_id' => $question_id, ':answer' => $answer])) {
            $success = false;
            $errorMsg = 'DB insert failed: ' . json_encode($stmt->errorInfo());
            error_log("[Evaluation] DB insert failed: " . json_encode($stmt->errorInfo()));
            break;
        }
    }
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);

