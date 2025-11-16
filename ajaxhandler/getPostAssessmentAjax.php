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
    // Fetch all post-assessment ratings for this student
    $stmt = $conn->prepare("SELECT question_id, self_rating, category FROM post_assessment WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "ratings" => $ratings]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

