<?php
require_once 'database/database.php';

$db = new Database();

echo "=== DEBUGGING STUDENT 67890 VALIDATION ===\n\n";

$student_id = '67890';
$pre_sql = "SELECT * FROM pre_assessment WHERE STUDENT_ID = ?";
$pre_stmt = $db->conn->prepare($pre_sql);
$pre_stmt->execute([$student_id]);
$pre = $pre_stmt->fetch(PDO::FETCH_ASSOC);

if (!$pre) {
    echo "❌ No pre-assessment data found!\n";
    exit;
}

echo "✅ Pre-assessment record found for student $student_id\n\n";

// Check EXACTLY what the prediction system checks
$required = [
    'CC 102','CC 103','PF 101','CC 104','IPT 101','IPT 102','CC 106','CC 105',
    'IM 101','IM 102','HCI 101','HCI 102','WS 101','NET 101','NET 102',
    'IAS 101','IAS 102','CAP 101','CAP 102','SP 101','soft_skill','communication_skill','technical_skill'
];

$missing = [];
$present = [];

foreach ($required as $col) {
    if (!isset($pre[$col]) || $pre[$col] === null || $pre[$col] === "") {
        $missing[] = $col;
    } else {
        $present[] = "$col: " . $pre[$col];
    }
}

echo "PRESENT FIELDS (" . count($present) . "):\n";
foreach ($present as $field) {
    echo "✅ $field\n";
}

echo "\nMISSING FIELDS (" . count($missing) . "):\n";
foreach ($missing as $field) {
    echo "❌ $field\n";
}

$valid = count($missing) == 0;
echo "\nFINAL VALIDATION:\n";
echo "- Complete: " . ($valid ? "YES" : "NO") . "\n";
echo "- Status: " . (($pre['soft_skill'] !== null && $pre['communication_skill'] !== null && $pre['technical_skill'] !== null) ? "Rated" : "Not Rated") . "\n";

if ($valid) {
    echo "\n🎉 STUDENT 67890 IS READY FOR PREDICTION!\n";
} else {
    echo "\n⚠️ Student 67890 still needs to complete missing fields\n";
}
?>