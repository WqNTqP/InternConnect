<?php
/**
 * AJAX handler to check question approval status for students
 * Returns the approval status of all student questions
 */

session_start();
if (!isset($_SESSION["student_user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$student_id = $_SESSION["student_user"];
header('Content-Type: application/json');

require_once '../database/database.php';

try {
    $db = new Database();
    $conn = $db->conn;
    
    // Get all questions for this student with their approval status
    $stmt = $conn->prepare("
        SELECT 
            id,
            category, 
            question_text, 
            question_number,
            approval_status,
            approved_by,
            approval_date,
            rejection_reason,
            created_at
        FROM student_questions 
        WHERE student_id = ? 
        ORDER BY category, question_number
    ");
    
    $stmt->execute([$student_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count questions by status
    $statusCounts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'total' => count($questions)
    ];
    
    foreach ($questions as $question) {
        $statusCounts[$question['approval_status']]++;
    }
    
    // Check if all questions are approved (allow assessment)
    $canSubmitAssessment = $statusCounts['total'] > 0 && $statusCounts['pending'] == 0 && $statusCounts['rejected'] == 0;
    
    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'status_counts' => $statusCounts,
        'can_submit_assessment' => $canSubmitAssessment,
        'message' => getStatusMessage($statusCounts)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error checking approval status: ' . $e->getMessage()
    ]);
}

function getStatusMessage($counts) {
    if ($counts['total'] == 0) {
        return 'No questions submitted yet. Please create and submit questions first.';
    }
    
    if ($counts['pending'] > 0) {
        return "You have {$counts['pending']} question(s) pending approval. Assessment submission is blocked until all questions are approved.";
    }
    
    if ($counts['rejected'] > 0) {
        return "You have {$counts['rejected']} rejected question(s). Please revise and resubmit them before proceeding with assessment.";
    }
    
    if ($counts['approved'] == $counts['total']) {
        return 'All questions approved! You can now submit your assessment.';
    }
    
    return 'Please submit questions for approval before proceeding with assessment.';
}
?>