<?php
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

$dbo = new Database();

echo "=== DATA INCONSISTENCY ANALYSIS ===\n\n";

// Check Doe, John in different tables
echo "1. CHECKING INTERNS_DETAILS TABLE:\n";
$stmt = $dbo->conn->prepare("
    SELECT id.INTERNS_ID, id.NAME, id.SURNAME, itd.HTE_ID, hte.HTE_NAME
    FROM interns_details id
    JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
    LEFT JOIN host_training_establishment hte ON itd.HTE_ID = hte.HTE_ID
    WHERE id.SURNAME = 'Doe' AND id.NAME = 'John'
");
$stmt->execute();
$internDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($internDetails) {
    foreach ($internDetails as $intern) {
        echo "- Doe, John (ID: {$intern['INTERNS_ID']}) assigned to HTE_ID: {$intern['HTE_ID']} ({$intern['HTE_NAME']})\n";
    }
} else {
    echo "- Doe, John NOT found in interns_details\n";
}

echo "\n2. CHECKING STUDENT TABLE:\n";
$stmt = $dbo->conn->prepare("
    SELECT s.STUDENT_ID, s.NAME, s.SURNAME, s.COORDINATOR_ID, c.COORDINATOR_NAME, hte.HTE_NAME
    FROM student s
    LEFT JOIN coordinator c ON s.COORDINATOR_ID = c.COORDINATOR_ID
    LEFT JOIN host_training_establishment hte ON c.HTE_ID = hte.HTE_ID
    WHERE s.SURNAME = 'Doe' AND s.NAME = 'John'
");
$stmt->execute();
$studentDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($studentDetails) {
    foreach ($studentDetails as $student) {
        echo "- Doe, John (ID: {$student['STUDENT_ID']}) assigned to Coordinator: {$student['COORDINATOR_NAME']} at HTE: {$student['HTE_NAME']}\n";
    }
} else {
    echo "- Doe, John NOT found in student table\n";
}

echo "\n3. CHECKING PENDING_ATTENDANCE TABLE:\n";
$stmt = $dbo->conn->prepare("
    SELECT pa.INTERNS_ID, pa.HTE_ID, hte.HTE_NAME, id.NAME, id.SURNAME
    FROM pending_attendance pa
    LEFT JOIN host_training_establishment hte ON pa.HTE_ID = hte.HTE_ID
    LEFT JOIN interns_details id ON pa.INTERNS_ID = id.INTERNS_ID
    WHERE id.SURNAME = 'Doe' AND id.NAME = 'John'
");
$stmt->execute();
$attendanceDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($attendanceDetails) {
    foreach ($attendanceDetails as $attendance) {
        echo "- Doe, John has pending attendance under HTE_ID: {$attendance['HTE_ID']} ({$attendance['HTE_NAME']})\n";
    }
} else {
    echo "- Doe, John NOT found in pending_attendance\n";
}

echo "\n4. SUMMARY:\n";
echo "This shows if Doe, John appears in different systems with different HTE assignments.\n";
echo "The discrepancy you noticed suggests data inconsistency between tables.\n";
?>