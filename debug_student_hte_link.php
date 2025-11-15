<?php
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

$dbo = new Database();

echo "<h2>Debug: Student-HTE Relationship</h2>";
echo "<pre>";

// Check coordinator info
echo "=== COORDINATOR INFO ===\n";
$coordinatorId = 59828994; // Kim Charles from your screenshot
$stmt = $dbo->conn->prepare("SELECT COORDINATOR_ID, NAME, HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
$stmt->execute([$coordinatorId]);
$coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Coordinator: " . print_r($coordinator, true) . "\n";

if ($coordinator && $coordinator['HTE_ID']) {
    $hteId = $coordinator['HTE_ID'];
    
    // Check HTE info
    echo "=== HTE INFO ===\n";
    $stmt = $dbo->conn->prepare("SELECT HTE_ID, NAME FROM host_training_establishment WHERE HTE_ID = ?");
    $stmt->execute([$hteId]);
    $hte = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "HTE: " . print_r($hte, true) . "\n";
    
    // Check all students in interns_details
    echo "=== ALL STUDENTS ===\n";
    $stmt = $dbo->conn->prepare("SELECT INTERNS_ID, STUDENT_ID, NAME, SURNAME FROM interns_details ORDER BY STUDENT_ID");
    $stmt->execute();
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total students in interns_details: " . count($allStudents) . "\n";
    foreach ($allStudents as $student) {
        echo "  - {$student['STUDENT_ID']}: {$student['SURNAME']}, {$student['NAME']} (INTERN_ID: {$student['INTERNS_ID']})\n";
    }
    
    // Check intern_details table
    echo "\n=== INTERN_DETAILS TABLE ===\n";
    $stmt = $dbo->conn->prepare("SELECT INTERNS_ID, SESSION_ID, HTE_ID FROM intern_details ORDER BY INTERNS_ID");
    $stmt->execute();
    $internDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total records in intern_details: " . count($internDetails) . "\n";
    foreach ($internDetails as $detail) {
        echo "  - INTERN_ID: {$detail['INTERNS_ID']}, SESSION_ID: {$detail['SESSION_ID']}, HTE_ID: {$detail['HTE_ID']}\n";
    }
    
    // Check students assigned to this HTE
    echo "\n=== STUDENTS ASSIGNED TO HTE_ID $hteId ===\n";
    $stmt = $dbo->conn->prepare("
        SELECT 
            id.STUDENT_ID,
            id.INTERNS_ID,
            id.NAME,
            id.SURNAME,
            itd.HTE_ID,
            itd.SESSION_ID
        FROM interns_details id
        JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
        WHERE itd.HTE_ID = ?
        ORDER BY id.SURNAME ASC, id.NAME ASC
    ");
    $stmt->execute([$hteId]);
    $studentsInHTE = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Students assigned to HTE_ID $hteId: " . count($studentsInHTE) . "\n";
    foreach ($studentsInHTE as $student) {
        echo "  - {$student['STUDENT_ID']}: {$student['SURNAME']}, {$student['NAME']} (SESSION: {$student['SESSION_ID']})\n";
    }
    
    // Look for Kim Charles specifically
    echo "\n=== SEARCH FOR KIM CHARLES ===\n";
    $stmt = $dbo->conn->prepare("SELECT * FROM interns_details WHERE NAME LIKE '%Kim%' OR SURNAME LIKE '%Kim%' OR NAME LIKE '%Charles%' OR SURNAME LIKE '%Charles%'");
    $stmt->execute();
    $kimCharles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found Kim Charles matches: " . count($kimCharles) . "\n";
    foreach ($kimCharles as $student) {
        echo "  - {$student['STUDENT_ID']}: {$student['SURNAME']}, {$student['NAME']} (INTERN_ID: {$student['INTERNS_ID']})\n";
        
        // Check if this student is in intern_details
        $stmt2 = $dbo->conn->prepare("SELECT * FROM intern_details WHERE INTERNS_ID = ?");
        $stmt2->execute([$student['INTERNS_ID']]);
        $internDetail = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($internDetail) {
            echo "    -> Assigned to HTE_ID: {$internDetail['HTE_ID']}, SESSION_ID: {$internDetail['SESSION_ID']}\n";
        } else {
            echo "    -> NOT FOUND in intern_details table!\n";
        }
    }
}

echo "</pre>";
?>