<?php
session_start();
$path=$_SERVER['DOCUMENT_ROOT'];
require_once $path."/database/database.php";
require_once $path."/database/sessionDetails.php";
require_once $path."/database/coordinator.php";
require_once $path."/database/buildingRegistrationDetails.php";
require_once $path."/database/attendanceDetails.php";
require_once $path."/fpdf/fpdf.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');


$dbo = new Database();
// // $response = ['success' => true, 'message' => 'Student added successfully!'];

// // echo json_encode($response);
// // exit;

// Check if the request contains an 'action'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'] ?? null;

    // Perform actions based on the requested operation
    switch ($action) {
        case 'approveAttendance':
            if ($id) {
                approveAttendance($id);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing ID for approval']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action specified']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}


function approveAttendance($id) {
    global $dbo;

    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Fetch the record from pending_attendance
    $stmt = $dbo->conn->prepare("SELECT INTERNS_ID, HTE_ID, ON_DATE, TIMEIN, TIMEOUT FROM pending_attendance WHERE ID = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        // Extract necessary information
        $internId = $record['INTERNS_ID'];
        $hteId = $record['HTE_ID'];
        $onDate = $record['ON_DATE'];
        $timeIn = $record['TIMEIN'];
        $timeOut = $record['TIMEOUT'];

        // Get the COORDINATOR_ID from the internship_needs table
        $sessionId = getSessionIdByHteId($dbo, $hteId); // Assuming this function fetches the session ID based on HTE_ID
        if (!$sessionId) {
            echo json_encode(['status' => 'error', 'message' => 'Session ID not found']);
            return;
        }

        // Fetch the COORDINATOR_ID based on HTE_ID and SESSION_ID
        $coordinatorId = getCoordinatorId($dbo, $hteId, $sessionId);
        if (!$coordinatorId) {
            echo json_encode(['status' => 'error', 'message' => 'Coordinator ID not found']);
            return;
        }

        try {
            if (!empty($timeIn) && !empty($timeOut)) {
                // Insert into interns_attendance only if both timein and timeout are present
                $stmt = $dbo->conn->prepare("INSERT INTO interns_attendance (COORDINATOR_ID, HTE_ID, ID, INTERNS_ID, ON_DATE, TIMEIN, TIMEOUT) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$coordinatorId, $hteId, $sessionId, $internId, $onDate, $timeIn, $timeOut]);

                // Update status in pending_attendance to approved
                $stmt = $dbo->conn->prepare("UPDATE pending_attendance SET STATUS = 'approved', updated_at = NOW() WHERE ID = ?");
                $stmt->execute([$id]);

                echo json_encode(['status' => 'success', 'message' => 'Attendance approved successfully']);
            } else {
                // Keep status as pending if timeout is missing
                echo json_encode(['status' => 'pending', 'message' => 'Attendance pending: timeout missing']);
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Database error occurred: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Record not found']);
    }
}

// Function to fetch COORDINATOR_ID from internship_needs
function getCoordinatorId($dbo, $hteId, $sessionId) {
    $stmt = $dbo->conn->prepare("SELECT COORDINATOR_ID FROM internship_needs WHERE HTE_ID = :hteId AND SESSION_ID = :sessionId");
    $stmt->execute([':hteId' => $hteId, ':sessionId' => $sessionId]);
    return $stmt->fetchColumn(); // Returns the COORDINATOR_ID
}

function getSessionIdByHteId($dbo, $hteId) {
    // Prepare the SQL statement to fetch SESSION_ID for the given HTE_ID
    $stmt = $dbo->conn->prepare("
        SELECT DISTINCT SESSION_ID
        FROM intern_details
        WHERE HTE_ID = :hteId
    ");
    
    // Execute the query with the provided HTE_ID
    $stmt->execute([':hteId' => $hteId]);
    
    // Fetch the result
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return the SESSION_ID or null if not found
    return $session ? htmlspecialchars($session['SESSION_ID']) : null;
}

?>
