<?php
session_start();
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

$dbo = new Database();

echo "<h2>Debug: Session and Query Check</h2>";
echo "<pre>";

echo "=== SESSION INFO ===\n";
echo "Session admin_user: " . ($_SESSION["admin_user"] ?? 'NOT SET') . "\n";
echo "All session vars: " . print_r($_SESSION, true) . "\n";

$coordinatorId = $_SESSION["admin_user"] ?? null;

if ($coordinatorId) {
    echo "\n=== COORDINATOR CHECK ===\n";
    $stmt = $dbo->conn->prepare("SELECT COORDINATOR_ID, NAME, HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $stmt->execute([$coordinatorId]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Coordinator found: " . print_r($coordinator, true) . "\n";

    if ($coordinator && $coordinator['HTE_ID']) {
        $hteId = $coordinator['HTE_ID'];
        
        echo "\n=== TESTING EXACT QUERY FROM ADMIN DASHBOARD ===\n";
        echo "Using HTE_ID: $hteId\n";
        
        $stmt = $dbo->conn->prepare("
            SELECT
                id.STUDENT_ID,
                id.INTERNS_ID,
                id.NAME,
                id.SURNAME
            FROM interns_details id
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE itd.HTE_ID = ?
            ORDER BY id.SURNAME ASC, id.NAME ASC
        ");
        $stmt->execute([$hteId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Query result: " . count($students) . " students found\n";
        foreach ($students as $student) {
            echo "  - {$student['STUDENT_ID']}: {$student['SURNAME']}, {$student['NAME']}\n";
        }
        
        if (count($students) == 0) {
            echo "\nDEBUG: Why no results?\n";
            echo "Let's check step by step:\n";
            
            // Check if any intern_details records exist for this HTE
            $stmt = $dbo->conn->prepare("SELECT COUNT(*) FROM intern_details WHERE HTE_ID = ?");
            $stmt->execute([$hteId]);
            $count = $stmt->fetchColumn();
            echo "Records in intern_details for HTE_ID $hteId: $count\n";
            
            if ($count > 0) {
                // Get the intern IDs for this HTE
                $stmt = $dbo->conn->prepare("SELECT INTERNS_ID FROM intern_details WHERE HTE_ID = ?");
                $stmt->execute([$hteId]);
                $internIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "Intern IDs for HTE_ID $hteId: " . implode(', ', $internIds) . "\n";
                
                // Check if these intern IDs exist in interns_details
                foreach ($internIds as $internId) {
                    $stmt = $dbo->conn->prepare("SELECT STUDENT_ID, NAME, SURNAME FROM interns_details WHERE INTERNS_ID = ?");
                    $stmt->execute([$internId]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($student) {
                        echo "  Found student for INTERN_ID $internId: {$student['STUDENT_ID']} - {$student['SURNAME']}, {$student['NAME']}\n";
                    } else {
                        echo "  NO student found in interns_details for INTERN_ID $internId\n";
                    }
                }
            }
        }
    } else {
        echo "Coordinator has no HTE_ID or coordinator not found\n";
    }
} else {
    echo "No coordinator ID in session\n";
}

echo "</pre>";
?>