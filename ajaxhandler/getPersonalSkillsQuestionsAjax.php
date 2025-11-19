<?php
header('Content-Type: application/json');
// Check if we're in a subdirectory (local development) or root (production)
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
require_once $basePath . "/database/database.php";

try {
    $db = new Database();
    $conn = $db->conn;
    $stmt = $conn->prepare("SELECT question_id, question_text FROM evaluation_questions WHERE category = 'Personal and Interpersonal Skills' AND status = 1");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "questions" => $questions]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>

