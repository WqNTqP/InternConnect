<?php
session_start();
require_once "../database/database.php";

if(!isset($_SESSION["student_user"]) || !isset($_POST['action']) || $_POST['action'] !== 'getNotifications') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$studentId = $_SESSION["student_user"];
$db = new Database();

try {
    // Get notifications for the student
    $stmt = $db->conn->prepare("
        SELECT n.notification_id, n.title, n.message, n.created_at, n.is_read, n.notification_type,
               w.report_id, w.week_start, w.week_end
        FROM notifications n
        LEFT JOIN weekly_reports w ON n.reference_id = w.report_id AND n.reference_type = 'report'
        WHERE n.receiver_id = ? AND n.receiver_type = 'student'
        ORDER BY n.created_at DESC
    ");
    
    $stmt->execute([$studentId]);
    $notifications = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'id' => $row['notification_id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'time' => date('h:i A', strtotime($row['created_at'])),
            'date' => date('M d, Y', strtotime($row['created_at'])),
            'type' => $row['notification_type'],
            'isRead' => (bool)$row['is_read'],
            'reportId' => $row['report_id'],
            'weekRange' => $row['week_start'] ? 
                         date('M d', strtotime($row['week_start'])) . ' - ' . 
                         date('M d, Y', strtotime($row['week_end'])) : null
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $notifications
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in getNotifications: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
}
?>
