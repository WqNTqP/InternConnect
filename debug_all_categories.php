<?php
require_once 'database/database.php';

$db = new Database();

echo "<h2>All Evaluation Questions by Category</h2>\n";

// Get all evaluation questions grouped by category
$sql = "SELECT eq.question_id, eq.category, eq.question_text, 
               se.id as student_eval_id, se.STUDENT_ID
        FROM evaluation_questions eq 
        LEFT JOIN student_evaluation se ON eq.question_id = se.question_id AND se.STUDENT_ID = 59828996
        ORDER BY eq.category, eq.question_id";

$stmt = $db->conn->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($results as $row) {
    $cat = $row['category'];
    if (!isset($categories[$cat])) {
        $categories[$cat] = [];
    }
    $categories[$cat][] = $row;
}

foreach ($categories as $category => $questions) {
    echo "<h3>$category (" . count($questions) . " questions)</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>\n";
    echo "<tr><th>Question ID</th><th>Student Eval ID</th><th>Question Text</th><th>Has Student Answer</th></tr>\n";
    
    foreach ($questions as $q) {
        echo "<tr>";
        echo "<td>{$q['question_id']}</td>";
        echo "<td>" . ($q['student_eval_id'] ?: 'N/A') . "</td>";
        echo "<td>" . substr($q['question_text'], 0, 60) . "...</td>";
        echo "<td>" . ($q['student_eval_id'] ? 'YES' : 'NO') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "<h2>Check Coordinator Ratings</h2>\n";
$sql2 = "SELECT ce.student_evaluation_id, ce.rating, eq.category 
         FROM coordinator_evaluation ce 
         JOIN student_evaluation se ON ce.student_evaluation_id = se.id 
         JOIN evaluation_questions eq ON se.question_id = eq.question_id 
         WHERE ce.STUDENT_ID = 59828996 
         ORDER BY eq.category, ce.student_evaluation_id";

$stmt2 = $db->conn->prepare($sql2);
$stmt2->execute();
$ratings = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>Student Eval ID</th><th>Category</th><th>Rating</th></tr>\n";

$ratingsByCategory = [];
foreach ($ratings as $rating) {
    echo "<tr>";
    echo "<td>{$rating['student_evaluation_id']}</td>";
    echo "<td><strong>{$rating['category']}</strong></td>";
    echo "<td>{$rating['rating']}</td>";
    echo "</tr>\n";
    
    $cat = $rating['category'];
    if (!isset($ratingsByCategory[$cat])) {
        $ratingsByCategory[$cat] = [];
    }
    $ratingsByCategory[$cat][] = $rating['rating'];
}
echo "</table>\n";

echo "<h3>Current Averages by Category</h3>\n";
foreach ($ratingsByCategory as $category => $ratings) {
    $avg = array_sum($ratings) / count($ratings);
    echo "<p><strong>$category:</strong> " . count($ratings) . " ratings, Average: " . round($avg, 2) . "</p>\n";
}
?>