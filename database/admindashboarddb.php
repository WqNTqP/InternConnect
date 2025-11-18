<?php
// Configure session to work across different hostnames/IPs
ini_set('session.cookie_domain', '');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_secure', false); // Set to true if using HTTPS
ini_set('session.cookie_httponly', true);
ini_set('session.use_only_cookies', true);

// Start the session
session_start();

// Check if the user is logged in
if(isset($_SESSION["admin_user"])) {
    $cdrid = $_SESSION["admin_user"];
    
    // Assuming you have a Database class to handle connections
    // Check if we're in a subdirectory (local development) or root (production)
    $path = $_SERVER['DOCUMENT_ROOT'];
    $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
    require_once $basePath . "/database/database.php";

    $dbo = new Database();
    
    // Fetch admin details along with HTE name
    $stmt = $dbo->conn->prepare("
        SELECT 
            c.COORDINATOR_ID,
            c.NAME,
            c.EMAIL,
            c.CONTACT_NUMBER,
            c.DEPARTMENT,
            c.HTE_ID,
            h.NAME AS HTE_NAME
        FROM coordinator c
        JOIN host_training_establishment h ON c.HTE_ID = h.HTE_ID
        WHERE c.COORDINATOR_ID = :cdrid AND c.ROLE = 'ADMIN'
    ");
    $stmt->execute([':cdrid' => $cdrid]);
    $adminDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminDetails) {
        // Admin is logged in, you can display their details
        $adminId = htmlspecialchars($adminDetails['COORDINATOR_ID']);
        $name = htmlspecialchars($adminDetails['NAME']);
        $email = htmlspecialchars($adminDetails['EMAIL']);
        $contactNumber = htmlspecialchars($adminDetails['CONTACT_NUMBER']);
        $department = htmlspecialchars($adminDetails['DEPARTMENT']);
        $hteId = htmlspecialchars($adminDetails['HTE_ID']);
        
        // Fetch session ID associated with the admin's HTE_ID from the intern_details table
        $stmt = $dbo->conn->prepare("
            SELECT DISTINCT SESSION_ID
            FROM intern_details
            WHERE HTE_ID = :hteId
        ");
        $stmt->execute([':hteId' => $hteId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        $sessionId = $session ? htmlspecialchars($session['SESSION_ID']) : 'Not Assigned';

        // Fetch total number of students under this manager
        $stmt = $dbo->conn->prepare("
            SELECT COUNT(*) AS total_students
            FROM intern_details
            WHERE HTE_ID = :hteId
        ");
        $stmt->execute([':hteId' => $hteId]);
        $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];

        // Fetch attendance records for these students (today only) - Statistics
        $stmt = $dbo->conn->prepare("
            SELECT
                COUNT(CASE WHEN TIMEIN IS NOT NULL THEN 1 END) as present,
                COUNT(CASE WHEN TIMEIN IS NOT NULL AND TIMEIN <= '08:00:59' THEN 1 END) AS on_time,
                COUNT(CASE WHEN TIMEIN IS NOT NULL AND TIMEIN > '08:00:59' AND TIMEIN <= '16:00:00' THEN 1 END) AS late
            FROM interns_attendance
            WHERE HTE_ID = :hteId AND ON_DATE = CURDATE()
        ");
        $stmt->execute([':hteId' => $hteId]);
        $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch detailed attendance lists for today
        $stmt = $dbo->conn->prepare("
            SELECT
                id.INTERNS_ID,
                id.STUDENT_ID,
                CONCAT(id.SURNAME, ' ', LEFT(id.NAME, 1), '.') as SURNAME,
                ia.TIMEIN,
                ia.TIMEOUT,
                CASE
                    WHEN ia.TIMEIN IS NOT NULL AND ia.TIMEOUT IS NOT NULL AND ia.TIMEIN <= '08:00:59' THEN 'On Time'
                    WHEN ia.TIMEIN IS NOT NULL AND ia.TIMEOUT IS NOT NULL AND ia.TIMEIN > '08:00:59' AND ia.TIMEIN <= '16:00:00' THEN 'Late'
                    ELSE 'Present'
                END as status
            FROM interns_attendance ia
            JOIN interns_details id ON ia.INTERNS_ID = id.INTERNS_ID
            WHERE ia.HTE_ID = :hteId AND ia.ON_DATE = CURDATE()
            ORDER BY status, id.SURNAME, id.NAME
        ");
        $stmt->execute([':hteId' => $hteId]);
        $attendanceDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Separate the details into on time, present, and late lists
        $onTimeList = array_filter($attendanceDetails, function($record) {
            return $record['status'] === 'On Time';
        });
        $lateList = array_filter($attendanceDetails, function($record) {
            return $record['status'] === 'Late';
        });

        // Present list is a combination of On Time and Late lists
        $presentList = array_merge($onTimeList, $lateList);

        // Fetch all students under this manager's HTE_ID by joining interns_details and intern_details
        $stmt = $dbo->conn->prepare("
            SELECT id.INTERNS_ID, id.STUDENT_ID, 
                CONCAT(id.SURNAME, ' ', LEFT(id.NAME, 1), '.') as SURNAME, 
                id.EMAIL, id.CONTACT_NUMBER
            FROM interns_details id
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE itd.HTE_ID = :hteId
            ORDER BY id.SURNAME, id.NAME
        ");
        $stmt->execute([':hteId' => $hteId]);
        $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch pending attendance records
        $stmt = $dbo->conn->prepare("SELECT * FROM pending_attendance WHERE status = 'pending'");
        $stmt->execute();
        $pendingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch historical attendance records (all records except today)
        $stmt = $dbo->conn->prepare("
            SELECT
                ia.INTERNS_ID,
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
            WHERE ia.HTE_ID = :hteId AND ia.ON_DATE != CURDATE()
            ORDER BY ia.ON_DATE DESC, ia.TIMEIN DESC
        ");
        $stmt->execute([':hteId' => $hteId]);
        $historicalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get unique dates for the date selector
        $stmt = $dbo->conn->prepare("
            SELECT DISTINCT ON_DATE 
            FROM interns_attendance 
            WHERE HTE_ID = :hteId 
            ORDER BY ON_DATE DESC
        ");
        $stmt->execute([':hteId' => $hteId]);
        $availableDates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Not an admin or doesn't exist
        echo "You are not logged in as an admin.";
        exit();
    }
} else {
    // Not logged in
    header("location: admin.php"); // Redirect to the login page
    die();
}
?>

