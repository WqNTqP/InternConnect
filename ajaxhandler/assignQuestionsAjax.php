<?php
// Coordinator assigns questions to student
header('Content-Type: application/json');
require_once dirname(__FILE__) . '/../database/database.php';

function sendResponse($status, $message = '', $data = null) {
    echo json_encode(["status" => $status, "message" => $message, "data" => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Invalid request method');
}

$student_id = $_POST['student_id'] ?? null;
$question_ids = $_POST['question_ids'] ?? [];
$assigned_by = $_POST['assigned_by'] ?? null;

if (!$student_id || !$assigned_by || !is_array($question_ids) || count($question_ids) === 0) {
    sendResponse('error', 'Missing required parameters');
}

try {
    $db = new Database();
    $stmt = $db->conn->prepare("INSERT INTO assigned_questions (student_id, question_id, assigned_by) VALUES (?, ?, ?)");
    foreach ($question_ids as $qid) {
        $stmt->execute([$student_id, $qid, $assigned_by]);
    }
    sendResponse('success', 'Questions assigned successfully');
} catch (Exception $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage());
}

