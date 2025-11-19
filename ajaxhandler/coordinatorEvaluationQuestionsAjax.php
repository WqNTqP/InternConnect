<?php
require_once __DIR__ . '/../config/path_config.php';
require_once PathConfig::getDatabasePath();
header('Content-Type: application/json');

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if requesting categories only
    if (isset($_GET['action']) && $_GET['action'] === 'getCategories') {
        $sql = "SELECT DISTINCT category FROM evaluation_questions WHERE question_id IS NOT NULL ORDER BY category";
        $stmt = $db->conn->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'categories' => $categories]);
        exit;
    }
    
    // Fetch all evaluation questions by ID
    $questions = [];
    $sql = "SELECT question_id, category, question_text, status FROM evaluation_questions WHERE question_id IS NOT NULL ORDER BY question_id";
    $stmt = $db->conn->prepare($sql);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'questions' => $questions]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new evaluation question
    $data = json_decode(file_get_contents('php://input'), true);
    $category = $data['category'];
    $question_text = $data['question_text'];
            $sql = "INSERT INTO evaluation_questions (category, question_text, status) VALUES (:category, :question_text, 1)";
    $stmt = $db->conn->prepare($sql);
    $success = $stmt->execute([':category' => $category, ':question_text' => $question_text]);
    echo json_encode(['success' => $success]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Bulk update questions if 'questions' array is present
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['questions']) && is_array($data['questions'])) {
        $success = true;
        foreach ($data['questions'] as $q) {
            $question_id = (int)$q['question_id'];
            $question_text = $q['question_text'] ?? null;
            if ($question_id && $question_text !== null) {
                $sql = "UPDATE evaluation_questions SET question_text = :question_text WHERE question_id = :question_id";
                $stmt = $db->conn->prepare($sql);
                $ok = $stmt->execute([':question_text' => $question_text, ':question_id' => $question_id]);
                if (!$ok) $success = false;
            }
        }
        echo json_encode(['success' => $success]);
        exit;
    }
    // Fallback: single question update (legacy)
    $question_id = (int)($data['question_id'] ?? 0);
    $category = $data['category'] ?? null;
    $question_text = $data['question_text'] ?? null;
    $status = $data['status'] ?? null;
    $fields = [];
    $params = [':question_id' => $question_id];
    if ($category !== null) {
        $fields[] = 'category = :category';
        $params[':category'] = $category;
    }
    if ($question_text !== null) {
        $fields[] = 'question_text = :question_text';
        $params[':question_text'] = $question_text;
    }
    if ($status !== null) {
        $fields[] = 'status = :status';
        $params[':status'] = $status;
    }
    if (!empty($fields) && $question_id) {
        $sql = "UPDATE evaluation_questions SET " . implode(', ', $fields) . " WHERE question_id = :question_id";
        $stmt = $db->conn->prepare($sql);
        $success = $stmt->execute($params);
        echo json_encode(['success' => $success]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);

