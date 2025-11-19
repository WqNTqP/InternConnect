<?php
// Test script to debug the getPreassessmentEvaluation issue
require_once 'database/database.php';

$dbo = new Database();
if (!$dbo->conn) {
    die("Database connection failed");
}

echo "<h2>Debug: getPreassessmentEvaluation Issue</h2>";

$internsId = 12345; // The student ID from your request
echo "<p><strong>Testing with INTERNS_ID (studentId parameter): $internsId</strong></p>";

try {
    // Step 1: Map INTERNS_ID to STUDENT_ID
    echo "<h3>Step 1: Mapping INTERNS_ID to STUDENT_ID</h3>";
    $stmtMap = $dbo->conn->prepare("SELECT STUDENT_ID FROM interns_details WHERE INTERNS_ID = ? LIMIT 1");
    $stmtMap->execute([$internsId]);
    $row = $stmtMap->fetch(PDO::FETCH_ASSOC);
    
    if (!$row || !$row['STUDENT_ID']) {
        echo "<p style='color: red;'>❌ No STUDENT_ID found for INTERNS_ID: $internsId</p>";
        exit;
    }
    
    $studentId = $row['STUDENT_ID'];
    echo "<p style='color: green;'>✅ Found STUDENT_ID: $studentId for INTERNS_ID: $internsId</p>";

    // Step 2: Check if student_evaluation entries exist
    echo "<h3>Step 2: Check student_evaluation entries</h3>";
    $stmtCount = $dbo->conn->prepare("SELECT COUNT(*) as count FROM student_evaluation WHERE STUDENT_ID = ?");
    $stmtCount->execute([$studentId]);
    $countResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
    echo "<p>Student evaluation entries for STUDENT_ID $studentId: " . $countResult['count'] . "</p>";
    
    if ($countResult['count'] == 0) {
        echo "<p style='color: orange;'>⚠️ No evaluation entries found for this student. This explains why evaluations array is empty.</p>";
    }

    // Step 3: Check evaluation_questions table
    echo "<h3>Step 3: Check evaluation_questions table</h3>";
            $stmtEQ = $dbo->conn->prepare("SELECT COUNT(*) as count FROM evaluation_questions WHERE status = 1");
    $stmtEQ->execute();
    $eqCount = $stmtEQ->fetch(PDO::FETCH_ASSOC);
    echo "<p>Active evaluation questions: " . $eqCount['count'] . "</p>";

    // Step 4: Test the actual query from the AJAX handler
    echo "<h3>Step 4: Test the actual JOIN query</h3>";
    $stmt = $dbo->conn->prepare("SELECT se.id as id, se.id as student_evaluation_id, eq.question_text, se.answer FROM student_evaluation se JOIN evaluation_questions eq ON se.question_id = eq.question_id WHERE se.STUDENT_ID = ?");
    $stmt->execute([$studentId]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Evaluations found with JOIN query: " . count($evaluations) . "</p>";
    
    if (count($evaluations) > 0) {
        echo "<h4>Sample evaluation data:</h4>";
        echo "<pre>" . print_r(array_slice($evaluations, 0, 2), true) . "</pre>";
    }

    // Step 5: Check coordinator evaluation status
    echo "<h3>Step 5: Check coordinator evaluation status</h3>";
    if (count($evaluations) > 0) {
        $evalIds = array_column($evaluations, 'id');
        $placeholders = implode(',', array_fill(0, count($evalIds), '?'));
        $stmt2 = $dbo->conn->prepare("SELECT COUNT(*) as rated_count FROM coordinator_evaluation WHERE student_evaluation_id IN ($placeholders) AND STUDENT_ID = ?");
        $stmt2->execute(array_merge($evalIds, [$studentId]));
        $ratedCount = $stmt2->fetch(PDO::FETCH_ASSOC);
        $isRated = ($ratedCount && intval($ratedCount['rated_count']) === count($evalIds));
        
        echo "<p>Coordinator ratings found: " . $ratedCount['rated_count'] . " out of " . count($evalIds) . "</p>";
        echo "<p>Is fully rated: " . ($isRated ? 'Yes' : 'No') . "</p>";
    } else {
        echo "<p>Cannot check rating status - no evaluations exist.</p>";
    }

    // Final result
    echo "<h3>Final Result</h3>";
    $result = [
        'success' => true,
        'evaluations' => $evaluations,
        'isRated' => isset($isRated) ? $isRated : false
    ];
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>