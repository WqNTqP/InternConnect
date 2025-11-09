<?php
require_once '../database/database.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    // Try to get student_id from POST, session, or fallback
    $student_id = isset($data['student_id']) ? $data['student_id'] : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
    if (!$student_id) {
        throw new Exception('Student ID not provided.');
    }
    // Initialize $conn using Database class
    $db = new Database();
    $conn = $db->conn;
    if (!$conn) {
        throw new Exception('Database connection not initialized.');
    }
    $questions = $data['questions'];
    $savedQuestions = [];
    foreach ($questions as $q) {
            // Check if question already exists for this student, category, and question_number
            $checkStmt = $conn->prepare("SELECT id FROM student_questions WHERE student_id = ? AND category = ? AND question_number = ?");
            $checkStmt->execute([$student_id, $q['category'], $q['question_number']]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                // Update existing question
                $updateStmt = $conn->prepare("UPDATE student_questions SET question_text = ? WHERE id = ?");
                $updateStmt->execute([$q['question_text'], $existing['id']]);
                $savedQuestions[] = [
                    'id' => $existing['id'],
                    'question_text' => $q['question_text'],
                    'category' => $q['category'],
                    'question_number' => $q['question_number']
                ];
            } else {
                // Insert new question
                $stmt = $conn->prepare("INSERT INTO student_questions (student_id, category, question_text, question_number) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $student_id,
                    $q['category'],
                    $q['question_text'],
                    $q['question_number']
                ]);
                $savedQuestions[] = [
                    'id' => $conn->lastInsertId(),
                    'question_text' => $q['question_text'],
                    'category' => $q['category'],
                    'question_number' => $q['question_number']
                ];
            }
    }
    echo json_encode(['success' => true, 'savedQuestions' => $savedQuestions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

