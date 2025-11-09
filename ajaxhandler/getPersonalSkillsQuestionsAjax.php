<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . "/InternConnect/database/database.php";

try {
    $db = new Database();
    $conn = $db->conn;
    $stmt = $conn->prepare("SELECT question_id, question_text FROM evaluation_questions WHERE category = 'Personal and Interpersonal Skills' AND status = 'active'");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "questions" => $questions]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>

