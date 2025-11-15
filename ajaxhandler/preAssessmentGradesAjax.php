<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/path_config.php';
require_once PathConfig::getDatabasePath();

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
if (!$student_id) {
    echo json_encode(["success" => false, "error" => "Missing student_id"]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->conn;
    $stmt = $conn->prepare("SELECT * FROM pre_assessment WHERE STUDENT_ID = ? LIMIT 1");
    $stmt->execute([$student_id]);
    $grades = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$grades) {
        echo json_encode(["success" => false, "error" => "No grades found for this student."]);
        exit;
    }
    echo json_encode(["success" => true, "grades" => $grades]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

