<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/database/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION["admin_user"])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['action']) || $_POST['action'] !== 'loadHistory' || !isset($_POST['date'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit();
}

$date = $_POST['date'];
$dbo = new Database();

// Get admin HTE_ID
$stmt = $dbo->conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = :cdrid AND ROLE = 'ADMIN'");
$stmt->execute([':cdrid' => $_SESSION["admin_user"]]);
$adminDetails = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminDetails) {
    echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
    exit();
}

$hteId = $adminDetails['HTE_ID'];

$stmt = $dbo->conn->prepare("
    SELECT
        COUNT(CASE WHEN TIMEIN IS NOT NULL AND TIMEOUT IS NOT NULL AND TIMEIN <= '08:00:59' THEN 1 END) AS on_time,
        COUNT(CASE WHEN TIMEIN IS NOT NULL AND TIMEOUT IS NOT NULL AND TIMEIN > '08:00:59' AND TIMEIN <= '16:00:00' THEN 1 END) AS late
    FROM interns_attendance
    WHERE HTE_ID = :hteId AND ON_DATE = :date
");
$stmt->execute([':hteId' => $hteId, ':date' => $date]);
$attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $dbo->conn->prepare("
    SELECT
        COALESCE(id.STUDENT_ID, ia.INTERNS_ID) as STUDENT_ID,
                CONCAT(id.SURNAME, ' ', LEFT(id.NAME, 1), '.') as SURNAME,
        ia.ON_DATE,
        ia.TIMEIN,
        ia.TIMEOUT,
        CASE
            WHEN ia.TIMEIN IS NOT NULL AND ia.TIMEOUT IS NOT NULL AND ia.TIMEIN <= '08:00:59' THEN 'On Time'
            WHEN ia.TIMEIN IS NOT NULL AND ia.TIMEOUT IS NOT NULL AND ia.TIMEIN > '08:00:59' AND ia.TIMEIN <= '16:00:00' THEN 'Late'
            ELSE 'Present'
        END as status
    FROM interns_attendance ia
    JOIN interns_details id ON ia.INTERNS_ID = id.INTERNS_ID
    WHERE ia.HTE_ID = :hteId AND ia.ON_DATE = :date
    ORDER BY status, id.SURNAME, id.NAME
");
$stmt->execute([':hteId' => $hteId, ':date' => $date]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$on_time = $attendanceStats['on_time'] ?? 0;
$late = $attendanceStats['late'] ?? 0;

// Calculate present as on_time + late
$present = $on_time + $late;
$total = $present;

// Group students by status
$presentList = [];
$onTimeList = [];
$lateList = [];

foreach ($records as $record) {
    if ($record['status'] === 'On Time') {
        $onTimeList[] = $record;
    } elseif ($record['status'] === 'Late') {
        $lateList[] = $record;
    } else {
        // Treat other statuses as present
        $presentList[] = $record;
    }
}

echo json_encode([
    'status' => 'success',
    'summary' => [
        'present' => $present,
        'late' => $late,
        'on_time' => $on_time,
        'total' => $total
    ],
    'records' => $records,
    'presentList' => $presentList,
    'onTimeList' => $onTimeList,
    'lateList' => $lateList
]);
?>

