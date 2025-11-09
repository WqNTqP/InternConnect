<?php
session_start();
if (!isset($_SESSION["coordinator_user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$coordinator_id = $_SESSION["coordinator_user"];

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . "/InternConnect/database/database.php";

try {
    $db = new Database();
    $conn = $db->conn;
    // Join to get students under this coordinator
    $stmt = $conn->prepare("SELECT i.INTERNS_ID, i.STUDENT_ID, i.SURNAME, i.NAME
        FROM interns_details i
        JOIN intern_details d ON i.INTERNS_ID = d.INTERNS_ID
        JOIN internship_needs n ON d.HTE_ID = n.HTE_ID
        WHERE n.COORDINATOR_ID = ?
        ORDER BY i.NAME, i.SURNAME");
    $stmt->execute([$coordinator_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "students" => $students]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

