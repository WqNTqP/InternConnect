<?php
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

// Initialize database connection
$dbo = new Database();

// Check if database connection is successful
if ($dbo->conn === null) {
    die("Database connection failed. Please check your connection settings.");
}

try {
    
    // First check what tables exist
    $stmt = $dbo->conn->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available tables: " . implode(", ", $tables) . "\n\n";
    
    // Check all students in interns_details
    $stmt = $dbo->conn->query('SELECT INTERNS_ID, NAME, SURNAME FROM interns_details ORDER BY SURNAME LIMIT 10');
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "All students found: " . count($students) . "\n";
    foreach($students as $student) {
        echo "ID: " . $student['INTERNS_ID'] . " Name: " . $student['SURNAME'] . ", " . $student['NAME'] . "\n";
    }
    
    // Check specifically for Doe
    $stmt = $dbo->conn->prepare('SELECT INTERNS_ID, NAME, SURNAME FROM interns_details WHERE SURNAME LIKE ? ORDER BY SURNAME');
    $stmt->execute(['%Doe%']);
    $does = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nStudents with 'Doe' in surname: " . count($does) . "\n";
    foreach($does as $doe) {
        echo "ID: " . $doe['INTERNS_ID'] . " Name: " . $doe['SURNAME'] . ", " . $doe['NAME'] . "\n";
    }
    
    // Now check the same query as in admindashboard.php - but we need an HTE_ID
    echo "\n=== Checking query from admindashboard.php ===\n";
    $stmt = $dbo->conn->query('SELECT DISTINCT itd.HTE_ID FROM intern_details itd LIMIT 5');
    $hteIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available HTE_IDs: " . implode(", ", $hteIds) . "\n";
    
    if (!empty($hteIds)) {
        $hteId = $hteIds[0];
        echo "Testing with HTE_ID: $hteId\n";
        
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
        
        echo "Students under HTE_ID $hteId: " . count($students) . "\n";
        foreach($students as $student) {
            echo "ID: " . $student['INTERNS_ID'] . " Name: " . $student['SURNAME'] . ", " . $student['NAME'] . "\n";
        }
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>