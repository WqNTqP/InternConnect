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


if ($action === 'getEvaluationReports') {
    $weekDate = $_POST['weekDate'] ?? '';
    $user = $_POST['user'] ?? '';
    $hte = $_POST['hte'] ?? '';
    $where = [];
    $params = [];
    if ($weekDate) {
        $date = new DateTime($weekDate);
        $day = (int)$date->format('w');
        $diffToMonday = ($day === 0 ? -6 : 1) - $day;
        $monday = clone $date;
        $monday->modify("$diffToMonday days");
        $sunday = clone $monday;
        $sunday->modify('+6 days');
        $where[] = '(e.timestamp BETWEEN :fromDate AND :toDate)';
        $params[':fromDate'] = $monday->format('Y-m-d 00:00:00');
        $params[':toDate'] = $sunday->format('Y-m-d 23:59:59');
    }
    if ($user) {
        $where[] = '(i.NAME LIKE :user OR e.STUDENT_ID = :userId)';
        $params[':user'] = "%$user%";
        $params[':userId'] = $user;
    }
    if ($hte) {
        $where[] = 'hte.NAME LIKE :hte OR hte.HTE_ID = :hteId';
        $params[':hte'] = "%$hte%";
        $params[':hteId'] = $hte;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $query = "
        SELECT 
            MIN(e.id) as id,
            e.STUDENT_ID,
            i.NAME as student_name,
            e.coordinator_id,
            c.NAME as coordinator_name,
            hte.NAME as hte_name,
            e.timestamp
        FROM coordinator_evaluation e
        LEFT JOIN interns_details i ON e.STUDENT_ID = i.STUDENT_ID
        LEFT JOIN coordinator c ON e.coordinator_id = c.COORDINATOR_ID
        LEFT JOIN intern_details ind ON i.INTERNS_ID = ind.INTERNS_ID
        LEFT JOIN host_training_establishment hte ON ind.HTE_ID = hte.HTE_ID
        $whereSql
        GROUP BY e.STUDENT_ID, i.NAME, e.coordinator_id, c.NAME, hte.NAME, e.timestamp
        ORDER BY e.timestamp DESC
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

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;

