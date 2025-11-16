<?php
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

// Initialize database connection
$dbo = new Database();

echo "=== Debugging Kim Charles (ID: 59828994) Access ===\n";

// Get Kim Charles's coordinator data
$stmt = $dbo->conn->prepare("SELECT * FROM coordinator WHERE COORDINATOR_ID = ?");
$stmt->execute(['59828994']);
$coordinatorData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($coordinatorData) {
    echo "Coordinator found:\n";
    echo "ID: " . $coordinatorData['COORDINATOR_ID'] . "\n";
    echo "Name: " . $coordinatorData['COORDINATOR_NAME'] . "\n";
    echo "HTE_ID: " . $coordinatorData['HTE_ID'] . "\n";
    
    $hteId = $coordinatorData['HTE_ID'];
    
    // Get HTE details
    echo "\n=== HTE Details ===\n";
    $hteStmt = $dbo->conn->prepare("SELECT * FROM host_training_establishment WHERE HTE_ID = ?");
    $hteStmt->execute([$hteId]);
    $hteData = $hteStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hteData) {
        echo "HTE Name: " . $hteData['HTE_NAME'] . "\n";
        echo "HTE ID: " . $hteData['HTE_ID'] . "\n";
    }
    
    // Check students under this HTE
    echo "\n=== Students under Kim Charles's management ===\n";
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
    
    echo "Total students: " . count($students) . "\n";
    foreach($students as $student) {
        echo "- " . $student['SURNAME'] . ", " . $student['NAME'] . " (ID: " . $student['INTERNS_ID'] . ")\n";
    }
    
    // Check where Doe, John is assigned
    echo "\n=== Where is Doe, John assigned? ===\n";
    $doeStmt = $dbo->conn->prepare("
        SELECT id.INTERNS_ID, id.NAME, id.SURNAME, itd.HTE_ID, hte.HTE_NAME
        FROM interns_details id
        JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
        LEFT JOIN host_training_establishment hte ON itd.HTE_ID = hte.HTE_ID
        WHERE id.SURNAME = 'Doe' AND id.NAME = 'John'
    ");
    $doeStmt->execute();
    $doeData = $doeStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doeData) {
        echo "Doe, John found:\n";
        echo "- Assigned to HTE_ID: " . $doeData['HTE_ID'] . "\n";
        echo "- HTE Name: " . $doeData['HTE_NAME'] . "\n";
        echo "- Kim Charles's HTE_ID: " . $hteId . "\n";
        echo "- Can Kim access Doe? " . ($doeData['HTE_ID'] == $hteId ? 'YES' : 'NO') . "\n";
    } else {
        echo "Doe, John not found in the system or not assigned to any HTE\n";
    }
} else {
    echo "Coordinator 59828994 not found\n";
}
?>