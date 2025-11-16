<?php
session_start();
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

// Initialize database connection
$dbo = new Database();

echo "=== Session Debug ===\n";
echo "Session admin_user: " . ($_SESSION["admin_user"] ?? 'NOT SET') . "\n";

if (isset($_SESSION["admin_user"])) {
    $coordinatorId = $_SESSION["admin_user"];
    
    // Get coordinator's HTE_ID
    $stmt = $dbo->conn->prepare("SELECT HTE_ID, COORDINATOR_NAME FROM coordinator WHERE COORDINATOR_ID = ?");
    $stmt->execute([$coordinatorId]);
    $coordinatorData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coordinatorData) {
        echo "Coordinator Name: " . $coordinatorData['COORDINATOR_NAME'] . "\n";
        echo "Coordinator HTE_ID: " . $coordinatorData['HTE_ID'] . "\n";
        
        $hteId = $coordinatorData['HTE_ID'];
        
        // Check what students are under this HTE_ID
        $studentsStmt = $dbo->conn->prepare("
            SELECT id.INTERNS_ID, id.NAME, id.SURNAME
            FROM interns_details id
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE itd.HTE_ID = :hteId
            ORDER BY id.SURNAME, id.NAME
        ");
        $studentsStmt->bindParam(':hteId', $hteId);
        $studentsStmt->execute();
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nStudents under this coordinator's management: " . count($students) . "\n";
        foreach($students as $student) {
            echo "ID: " . $student['INTERNS_ID'] . " Name: " . $student['SURNAME'] . ", " . $student['NAME'] . "\n";
        }
        
        // Check what HTE_ID Doe, John is under
        echo "\n=== Checking where Doe, John is assigned ===\n";
        $doeStmt = $dbo->conn->prepare("
            SELECT id.INTERNS_ID, id.NAME, id.SURNAME, itd.HTE_ID
            FROM interns_details id
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE id.SURNAME = 'Doe' AND id.NAME = 'John'
        ");
        $doeStmt->execute();
        $doeData = $doeStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doeData) {
            echo "Doe, John is under HTE_ID: " . $doeData['HTE_ID'] . "\n";
            echo "Current coordinator's HTE_ID: " . $hteId . "\n";
            echo "Match: " . ($doeData['HTE_ID'] == $hteId ? 'YES' : 'NO') . "\n";
        } else {
            echo "Doe, John not found in intern_details table\n";
        }
    }
}
?>