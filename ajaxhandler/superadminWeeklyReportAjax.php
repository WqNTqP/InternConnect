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

if ($action === 'getWeeklyReports') {
    $fromDate = $_POST['fromDate'] ?? '';
    $toDate = $_POST['toDate'] ?? '';
    $user = $_POST['user'] ?? '';
    $hte = $_POST['hte'] ?? '';
    $where = ["r.approval_status = 'approved'"];
    $params = [];
    if ($fromDate && $toDate) {
        $where[] = '(r.week_start >= :fromDate AND r.week_end <= :toDate)';
        $params[':fromDate'] = $fromDate;
        $params[':toDate'] = $toDate;
    }
    if ($user) {
        $where[] = '(i.NAME LIKE :user OR r.interns_id = :userId)';
        $params[':user'] = "%$user%";
        $params[':userId'] = $user;
    }
    if ($hte) {
        $where[] = 'EXISTS (SELECT 1 FROM intern_details ind LEFT JOIN host_training_establishment hte ON ind.HTE_ID = hte.HTE_ID WHERE ind.INTERNS_ID = r.interns_id AND (hte.NAME LIKE :hte OR hte.HTE_ID = :hteId))';
        $params[':hte'] = "%$hte%";
        $params[':hteId'] = $hte;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $query = "
        SELECT r.report_id, r.interns_id, i.NAME as student_name, r.week_start, r.week_end, r.approval_status, r.created_at
        FROM weekly_reports r
        LEFT JOIN interns_details i ON r.interns_id = i.INTERNS_ID
        $whereSql
        ORDER BY r.created_at DESC
        LIMIT 100
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

// Default error
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;

