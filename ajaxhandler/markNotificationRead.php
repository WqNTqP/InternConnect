<?php
session_start();
require_once "../database/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION["student_user"]) || !isset($_POST['notificationId'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$studentId = $_SESSION["student_user"];
$notificationId = $_POST['notificationId'];

$db = new Database();

try {
    // Verify the notification belongs to the student before marking as read
    $stmt = $db->conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE notification_id = ? AND receiver_id = ? AND receiver_type = 'student'
    ");
    
    $result = $stmt->execute([$notificationId, $studentId]);
    
    if ($result) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update notification']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in markNotificationRead: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
}
?>
