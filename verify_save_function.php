<?php
// Verification script to check save function setup
require_once 'database/database.php';

echo "<h2>Save Function Verification</h2>";

// 1. Check coordinator_evaluation table structure
echo "<h3>1. coordinator_evaluation table structure:</h3>";
$db = new Database();
$stmt = $db->conn->prepare("DESCRIBE coordinator_evaluation");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
echo "</pre>";

// 2. Check pre_assessment table structure
echo "<h3>2. pre_assessment table structure:</h3>";
$stmt = $db->conn->prepare("DESCRIBE pre_assessment");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
echo "</pre>";

// 3. Check if technical_skill column exists
echo "<h3>3. Check technical_skill column:</h3>";
$stmt = $db->conn->prepare("SHOW COLUMNS FROM pre_assessment LIKE 'technical_skill'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
if($result) {
    echo "✅ technical_skill column EXISTS<br>";
} else {
    echo "❌ technical_skill column MISSING<br>";
    echo "Adding technical_skill column...<br>";
    try {
        $db->conn->exec("ALTER TABLE pre_assessment ADD COLUMN technical_skill DECIMAL(3,2) NULL");
        echo "✅ technical_skill column ADDED<br>";
    } catch (Exception $e) {
        echo "❌ Error adding column: " . $e->getMessage() . "<br>";
    }
}

// 4. Check evaluation_questions categories
echo "<h3>4. Available question categories:</h3>";
$stmt = $db->conn->prepare("SELECT DISTINCT category FROM evaluation_questions ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach($categories as $cat) {
    echo "'" . $cat['category'] . "'\n";
}
echo "</pre>";

// 5. Sample test data check
echo "<h3>5. Sample data verification:</h3>";
$stmt = $db->conn->prepare("SELECT COUNT(*) as count FROM evaluation_questions");
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total evaluation questions: " . $count['count'] . "<br>";

$stmt = $db->conn->prepare("SELECT COUNT(*) as count FROM student_evaluation");
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total student evaluations: " . $count['count'] . "<br>";

$stmt = $db->conn->prepare("SELECT COUNT(*) as count FROM coordinator_evaluation");
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total coordinator evaluations: " . $count['count'] . "<br>";

echo "<hr>";
echo "<h3>✅ Verification Complete!</h3>";
echo "<p>The save function should now:</p>";
echo "<ol>";
echo "<li>Save individual ratings to coordinator_evaluation table</li>";
echo "<li>Calculate category averages (Soft Skills, Communication Skills, Technical Skills)</li>";
echo "<li>Update pre_assessment table with calculated averages</li>";
echo "</ol>";
?>