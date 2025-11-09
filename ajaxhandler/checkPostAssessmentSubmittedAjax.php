<?php
session_start();
if (!isset($_SESSION["student_user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$student_id = $_SESSION["student_user"];

header('Content-Type: application/json');
// Check if we're in a subdirectory (local development) or root (production)
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
require_once $basePath . "/database/database.php";

try {
    $db = new Database();
    $conn = $db->conn;
    // Check if student has already submitted post-assessment ratings
    $stmt = $conn->prepare("SELECT COUNT(*) FROM post_assessment WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $count = $stmt->fetchColumn();
    echo json_encode(["success" => true, "submitted" => ($count > 0)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

