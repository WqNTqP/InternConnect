<?php
require_once 'database/database.php';

$db = new Database();

echo "=== TESTING PREDICTION FIX ===\n\n";

// Test student 67890 specifically
$student_id = '67890';

echo "Testing student ID: $student_id\n\n";

// Get pre-assessment data
$pre_sql = "SELECT * FROM pre_assessment WHERE STUDENT_ID = ?";
$pre_stmt = $db->conn->prepare($pre_sql);
$pre_stmt->execute([$student_id]);
$pre = $pre_stmt->fetch(PDO::FETCH_ASSOC);

if (!$pre) {
    echo "ERROR: No pre-assessment data found for student $student_id\n";
    exit;
}

echo "SKILL RATINGS:\n";
echo "- Soft Skill: " . ($pre['soft_skill'] ?? 'NULL') . "\n";
echo "- Communication Skill: " . ($pre['communication_skill'] ?? 'NULL') . "\n";
echo "- Technical Skill: " . ($pre['technical_skill'] ?? 'NULL') . "\n\n";

// Test the fixed validation logic
$required = [
    'CC_102','CC_103','PF_101','CC_104','IPT_101','IPT_102','CC_106','CC_105',
    'IM_101','IM_102','HCI_101','HCI_102','WS_101','NET_101','NET_102',
    'IAS_101','IAS_102','CAP_101','CAP_102','SP_101','soft_skill','communication_skill','technical_skill'
];

$missing = [];
$valid = true;

foreach ($required as $col) {
    if (!isset($pre[$col]) || $pre[$col] === null || $pre[$col] === "") {
        $missing[] = $col;
        $valid = false;
    }
}

echo "VALIDATION RESULTS:\n";
echo "- Valid for prediction: " . ($valid ? "YES" : "NO") . "\n";
echo "- Status should be: " . (($pre['soft_skill'] !== null && $pre['communication_skill'] !== null && $pre['technical_skill'] !== null) ? "Rated" : "Not Rated") . "\n";

if (!$valid) {
    echo "- Missing fields (" . count($missing) . "): " . implode(', ', $missing) . "\n";
}

echo "SAMPLE GRADES CHECK:\n";
$sample_grades = ['CC 102', 'PF 101', 'NET 101', 'SP 101'];
foreach ($sample_grades as $grade) {
    echo "- $grade: " . ($pre[$grade] ?? 'NULL') . "\n";
}

// Test what the prediction system should return now
echo "\n=== PREDICTION SYSTEM TEST ===\n";
if ($valid) {
    echo "✅ Student should show COMPLETE DATA and be ready for ML prediction\n";
    echo "✅ All required academic grades and skill ratings are present\n";
} else {
    echo "❌ Student still missing required data\n";
}
?>