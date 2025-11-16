<?php
require_once 'database/database.php';

$db = new Database();

echo "<h2>Duplicate Questions Analysis</h2>";

// Check for duplicate questions in student_evaluation table for a specific student
echo "<h3>Checking Student ID 12345 for duplicate question entries:</h3>";
$stmt = $db->conn->prepare("
    SELECT 
        se.question_id,
        eq.question_text,
        eq.category,
        COUNT(*) as duplicate_count,
        GROUP_CONCAT(se.id) as evaluation_ids
    FROM student_evaluation se
    LEFT JOIN evaluation_questions eq ON se.question_id = eq.question_id 
    WHERE se.STUDENT_ID = '12345'
    GROUP BY se.question_id, eq.question_text, eq.category
    HAVING COUNT(*) > 1
    ORDER BY eq.category, se.question_id
");
$stmt->execute();
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "✅ No duplicate question entries found for this student.<br><br>";
} else {
    echo "❌ Found " . count($duplicates) . " duplicate question entries:<br>";
    foreach ($duplicates as $dup) {
        echo "- Question ID {$dup['question_id']} ({$dup['category']}): \"{$dup['question_text']}\" appears {$dup['duplicate_count']} times<br>";
        echo "&nbsp;&nbsp;Evaluation IDs: {$dup['evaluation_ids']}<br><br>";
    }
}

// Check all questions for this student
echo "<h3>All questions for Student ID 12345:</h3>";
$stmt = $db->conn->prepare("
    SELECT 
        se.id as student_evaluation_id,
        se.question_id,
        eq.question_text,
        eq.category,
        se.answer
    FROM student_evaluation se
    LEFT JOIN evaluation_questions eq ON se.question_id = eq.question_id 
    WHERE se.STUDENT_ID = '12345'
    ORDER BY eq.category, se.question_id, se.id
");
$stmt->execute();
$allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total question entries: " . count($allQuestions) . "<br><br>";

$currentCategory = '';
$questionCount = 1;
foreach ($allQuestions as $q) {
    if ($currentCategory != $q['category']) {
        if ($currentCategory != '') echo "<br>";
        echo "<strong>{$q['category']}:</strong><br>";
        $currentCategory = $q['category'];
        $questionCount = 1;
    }
    
    echo "{$questionCount}. ID:{$q['student_evaluation_id']} Q:{$q['question_id']} - {$q['question_text']}<br>";
    $questionCount++;
}

// Check if the issue is in the evaluation_questions table itself
echo "<br><h3>Checking evaluation_questions table for duplicates:</h3>";
$stmt = $db->conn->prepare("
    SELECT 
        question_text,
        category,
        COUNT(*) as duplicate_count,
        GROUP_CONCAT(question_id) as question_ids
    FROM evaluation_questions
    GROUP BY question_text, category
    HAVING COUNT(*) > 1
    ORDER BY category, question_text
");
$stmt->execute();
$questionDuplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questionDuplicates)) {
    echo "✅ No duplicate questions found in evaluation_questions table.<br>";
} else {
    echo "❌ Found " . count($questionDuplicates) . " duplicate questions in evaluation_questions table:<br>";
    foreach ($questionDuplicates as $dup) {
        echo "- \"{$dup['question_text']}\" ({$dup['category']}) appears {$dup['duplicate_count']} times<br>";
        echo "&nbsp;&nbsp;Question IDs: {$dup['question_ids']}<br><br>";
    }
}
?>