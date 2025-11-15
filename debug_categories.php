<?php
require_once 'database/database.php';

$db = new Database();

echo "<h2>Debug: Categories for Student Evaluation IDs 584-593</h2>\n";

// Check the categories for student evaluation IDs 584-593
$sql = "SELECT se.id, se.question_id, eq.category, eq.question_text 
        FROM student_evaluation se 
        JOIN evaluation_questions eq ON se.question_id = eq.question_id 
        WHERE se.id BETWEEN 584 AND 593 
        ORDER BY se.id";

$stmt = $db->conn->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>Student Eval ID</th><th>Question ID</th><th>Category</th><th>Question Text</th></tr>\n";

foreach ($results as $row) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['question_id']}</td>";
    echo "<td><strong>{$row['category']}</strong></td>";
    echo "<td>" . substr($row['question_text'], 0, 50) . "...</td>";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h2>Category Distribution</h2>\n";
$categories = [];
foreach ($results as $row) {
    $cat = $row['category'];
    if (!isset($categories[$cat])) {
        $categories[$cat] = 0;
    }
    $categories[$cat]++;
}

foreach ($categories as $category => $count) {
    echo "<p><strong>$category:</strong> $count questions</p>\n";
}

echo "<h2>Category Matching Test</h2>\n";
foreach ($categories as $category => $count) {
    $cat = strtolower(trim($category));
    echo "<p>Category: '$category' (lowercase: '$cat')</p>\n";
    
    if (strpos($cat, 'soft') !== false) {
        echo "<p style='color: green;'>✓ Matches SOFT pattern</p>\n";
    } elseif (strpos($cat, 'communication') !== false || strpos($cat, 'comm') !== false) {
        echo "<p style='color: blue;'>✓ Matches COMMUNICATION pattern</p>\n";
    } elseif (strpos($cat, 'technical') !== false) {
        echo "<p style='color: orange;'>✓ Matches TECHNICAL pattern</p>\n";
    } else {
        echo "<p style='color: red;'>✗ NO MATCH for pre-assessment</p>\n";
    }
    echo "<br>\n";
}
?>