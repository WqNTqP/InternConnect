<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/InternConnect/database/database.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deletePendingAttendance') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing ID for deletion']);
        exit();
    }

    try {
        $dbo = new Database();
        $stmt = $dbo->conn->prepare("DELETE FROM pending_attendance WHERE ID = ?");
        $stmt->execute([$id]);

        echo json_encode(['status' => 'success', 'message' => 'Record deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
