<?php
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

$db = new Database();
$stmt = $db->conn->query('SELECT category, COUNT(*) as count FROM evaluation_questions GROUP BY category ORDER BY category');

echo "📋 EVALUATION QUESTIONS VERIFICATION:\n";
echo "====================================\n";

$total = 0;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "   ✓ {$row['category']}: {$row['count']} questions\n";
    $total += $row['count'];
}

echo "\n📊 Total: $total questions restored successfully!\n";

// Show a sample from each category
echo "\n🔍 Sample questions per category:\n";
$sampleStmt = $db->conn->query("
    SELECT category, question_text 
    FROM evaluation_questions 
    WHERE question_id IN (1, 11, 21, 28) 
    ORDER BY question_id
");

while($row = $sampleStmt->fetch(PDO::FETCH_ASSOC)) {
    echo "   • {$row['category']}: " . substr($row['question_text'], 0, 60) . "...\n";
}
?>