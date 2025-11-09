<?php
session_start();
require_once '../database/database.php';
header('Content-Type: application/json');

$dbo = new Database();
$conn = $dbo->conn;

// Only allow superadmin access
if (!isset($_SESSION['current_user_role']) || $_SESSION['current_user_role'] !== 'SUPERADMIN') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'getAttendanceReports') {
    // Support week filtering
    $fromDate = $_POST['fromDate'] ?? '';
    $toDate = $_POST['toDate'] ?? '';
    $user = $_POST['user'] ?? '';
    $hte = $_POST['hte'] ?? '';
    $where = [];
    $params = [];
    if ($fromDate && $toDate) {
        $where[] = 'a.ON_DATE BETWEEN :fromDate AND :toDate';
        $params[':fromDate'] = $fromDate;
        $params[':toDate'] = $toDate;
    }
    if ($user) {
        $where[] = '(i.NAME LIKE :user OR a.INTERNS_ID = :userId)';
        $params[':user'] = "%$user%";
        $params[':userId'] = $user;
    }
    if ($hte) {
        $where[] = '(hte.NAME LIKE :hte OR a.HTE_ID = :hteId)';
        $params[':hte'] = "%$hte%";
        $params[':hteId'] = $hte;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $query = "
        SELECT a.ON_DATE, i.NAME as student_name, c.NAME as coordinator_name, hte.NAME as hte_name, a.TIMEIN, a.TIMEOUT
        FROM interns_attendance a
        LEFT JOIN interns_details i ON a.INTERNS_ID = i.INTERNS_ID
        LEFT JOIN coordinator c ON a.COORDINATOR_ID = c.COORDINATOR_ID
        LEFT JOIN host_training_establishment hte ON a.HTE_ID = hte.HTE_ID
        $whereSql
        ORDER BY a.ON_DATE DESC, i.NAME
        LIMIT 200
    ";
    $stmt = $conn->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'reports' => $reports]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;

