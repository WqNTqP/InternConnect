<?php
require_once 'config/path_config.php';
require_once PathConfig::getDatabasePath();

$db = new Database();

echo "=== DEBUGGING CATEGORY MISMATCH ===\n\n";

// 1. Check what categories are returned by getCategories endpoint
echo "1. Categories from getCategories endpoint:\n";
$sql = "SELECT DISTINCT category FROM evaluation_questions WHERE question_id IS NOT NULL ORDER BY category";
$stmt = $db->conn->prepare($sql);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($categories as $cat) {
    echo "   - '$cat'\n";
}
echo "\n";

// 2. Check what questions and their categories are returned
echo "2. Questions and their categories:\n";
$sql = "SELECT question_id, category, question_text FROM evaluation_questions WHERE question_id IS NOT NULL ORDER BY question_id";
$stmt = $db->conn->prepare($sql);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($questions as $q) {
    echo "   - ID: {$q['question_id']}, Category: '{$q['category']}', Text: " . substr($q['question_text'], 0, 50) . "...\n";
}
echo "\n";

// 3. Check for any whitespace or encoding issues
echo "3. Category analysis:\n";
foreach ($categories as $cat) {
    echo "   - Category: '$cat'\n";
    echo "     Length: " . strlen($cat) . "\n";
    echo "     Trimmed: '" . trim($cat) . "'\n";
    echo "     Hex: " . bin2hex($cat) . "\n\n";
}
?>