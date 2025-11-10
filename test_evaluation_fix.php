<?php
// Test the fixed getPreassessmentEvaluation
require_once 'database/database.php';

$dbo = new Database();
if (!$dbo->conn) {
    die("Database connection failed");
}

// Simulate the POST data with the correct INTERNS_ID
$_POST['action'] = 'getPreassessmentEvaluation';
$_POST['studentId'] = '291';  // This is the INTERNS_ID for the student with STUDENT_ID 12345

echo "<h2>Testing Fixed getPreassessmentEvaluation</h2>";
echo "<p><strong>Request:</strong> action=getPreassessmentEvaluation, studentId=291 (INTERNS_ID for STUDENT_ID 12345)</p>";

// Execute the same logic from studentDashboardAjax.php
$internsId = isset($_POST['studentId']) ? $_POST['studentId'] : null;
if (!$internsId) {
    echo json_encode(['success' => false, 'message' => 'No studentId provided']);
    exit;
}

try {
    // Map INTERNS_ID to STUDENT_ID
    echo "<p><strong>Looking for INTERNS_ID:</strong> $internsId</p>";
    $stmtMap = $dbo->conn->prepare("SELECT STUDENT_ID FROM interns_details WHERE INTERNS_ID = ? LIMIT 1");
    $stmtMap->execute([$internsId]);
    $row = $stmtMap->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Mapping result:</strong> " . print_r($row, true) . "</p>";
    
    if (!$row || !$row['STUDENT_ID']) {
        echo "<p style='color: red;'><strong>❌ ERROR:</strong> No STUDENT_ID found for INTERNS_ID $internsId</p>";
        echo "<p>This means the student doesn't exist in interns_details table with this INTERNS_ID.</p>";
        
        // Let's check what INTERNS_ID values exist
        $checkStmt = $dbo->conn->prepare("SELECT INTERNS_ID, STUDENT_ID, NAME FROM interns_details ORDER BY INTERNS_ID");
        $checkStmt->execute();
        $allInterns = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p><strong>Available INTERNS_ID values:</strong></p>";
        echo "<pre>" . print_r(array_slice($allInterns, 0, 10), true) . "</pre>";
        
        echo json_encode(['success' => true, 'evaluations' => [], 'isRated' => false]);
        exit;
    }
    $studentId = $row['STUDENT_ID'];
    
    echo "<p><strong>✅ Mapped STUDENT_ID:</strong> $studentId</p>";

    // First, let's check what data exists for this student
    echo "<h4>Debug: Student's evaluation data</h4>";
    $debugStmt = $dbo->conn->prepare("SELECT COUNT(*) as count, MIN(question_id) as min_q, MAX(question_id) as max_q FROM student_evaluation WHERE STUDENT_ID = ?");
    $debugStmt->execute([$studentId]);
    $debugInfo = $debugStmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Student has {$debugInfo['count']} evaluation entries, question_ids from {$debugInfo['min_q']} to {$debugInfo['max_q']}</p>";
    
    // Check what question_ids the student has
    $qidsStmt = $dbo->conn->prepare("SELECT DISTINCT question_id FROM student_evaluation WHERE STUDENT_ID = ? ORDER BY question_id");
    $qidsStmt->execute([$studentId]);
    $studentQuestionIds = $qidsStmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Student's question IDs: " . implode(', ', $studentQuestionIds) . "</p>";
    
    // Check what question_ids exist in evaluation_questions
    $allQidsStmt = $dbo->conn->prepare("SELECT DISTINCT question_id FROM evaluation_questions ORDER BY question_id");
    $allQidsStmt->execute();
    $allQuestionIds = $allQidsStmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>All available question IDs: " . implode(', ', $allQuestionIds) . "</p>";

    // Get all evaluation answers for this student with LEFT JOIN to handle missing questions
    $stmt = $dbo->conn->prepare("SELECT se.id as id, se.id as student_evaluation_id, COALESCE(eq.question_text, CONCAT('Question ', se.question_id)) as question_text, se.answer, se.question_id FROM student_evaluation se LEFT JOIN evaluation_questions eq ON se.question_id = eq.question_id WHERE se.STUDENT_ID = ? ORDER BY se.question_id");
    $stmt->execute([$studentId]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Evaluations found:</strong> " . count($evaluations) . "</p>";

    // Check if all answers have been rated by coordinator
    $isRated = false;
    if (count($evaluations) > 0) {
        $evalIds = array_column($evaluations, 'id');
        $placeholders = implode(',', array_fill(0, count($evalIds), '?'));
        $stmt2 = $dbo->conn->prepare("SELECT COUNT(*) as rated_count FROM coordinator_evaluation WHERE student_evaluation_id IN ($placeholders) AND STUDENT_ID = ?");
        $stmt2->execute(array_merge($evalIds, [$studentId]));
        $ratedCount = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($ratedCount && intval($ratedCount['rated_count']) === count($evalIds)) {
            $isRated = true;
        }
        echo "<p><strong>Coordinator ratings:</strong> " . $ratedCount['rated_count'] . " out of " . count($evalIds) . "</p>";
    }

    $result = [
        'success' => true,
        'evaluations' => $evaluations,
        'isRated' => $isRated
    ];
    
    // Also test a simple query without JOIN to isolate the issue
    echo "<h4>Debug: Simple query without JOIN</h4>";
    $simpleStmt = $dbo->conn->prepare("SELECT id, STUDENT_ID, question_id, answer FROM student_evaluation WHERE STUDENT_ID = ? ORDER BY question_id LIMIT 5");
    $simpleStmt->execute([$studentId]);
    $simpleResults = $simpleStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Simple query results: " . count($simpleResults) . " records</p>";
    if (count($simpleResults) > 0) {
        echo "<pre>" . print_r($simpleResults, true) . "</pre>";
    }

    echo "<h3>Response:</h3>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    if (count($evaluations) > 0) {
        echo "<h3>Sample Evaluation Data:</h3>";
        echo "<pre>" . print_r(array_slice($evaluations, 0, 3), true) . "</pre>";
    } else {
        echo "<h3 style='color: red;'>❌ No evaluations returned by the JOIN query!</h3>";
        echo "<p>This suggests there might be an issue with the JOIN or the data types.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>