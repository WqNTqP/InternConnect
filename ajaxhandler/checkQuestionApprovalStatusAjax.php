<?php
/**
 * AJAX handler to check question approval status for students
 * Returns the approval status of all student questions
 */

session_start();
header('Content-Type: application/json');

require_once '../database/database.php';

// Handle coordinator readiness check
if (isset($_POST['action']) && $_POST['action'] === 'checkReadiness') {
    if (!isset($_SESSION["admin_user"])) {
        http_response_code(401);
        echo json_encode(["error" => "Not logged in as admin"]);
        exit;
    }
    
    $student_id = $_POST['student_id'] ?? null;
    if (!$student_id) {
        echo json_encode(["error" => "Student ID required"]);
        exit;
    }
} else {
    // Original student check
    if (!isset($_SESSION["student_user"])) {
        http_response_code(401);
        echo json_encode(["error" => "Not logged in"]);
        exit;
    }
    
    $student_id = $_SESSION["student_user"];
}

try {
    $db = new Database();
    $conn = $db->conn;
    
    // For coordinator readiness check, we need to check both questions and self-assessment
    if (isset($_POST['action']) && $_POST['action'] === 'checkReadiness') {
        // Convert STUDENT_ID to INTERNS_ID for questions lookup
        $stmt = $conn->prepare("SELECT INTERNS_ID FROM interns_details WHERE STUDENT_ID = ?");
        $stmt->execute([$student_id]);
        $interns_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $questions_student_id = $interns_data ? $interns_data['INTERNS_ID'] : $student_id;
        
        // Check custom questions (excluding Personal Skills which are auto-approved)
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_questions,
                SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_questions,
                SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_questions,
                SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_questions
            FROM student_questions
            WHERE student_id = ? AND category != 'Personal and Interpersonal Skills'
        ");
        $stmt->execute([$questions_student_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $statusCounts = [
            'pending' => (int)$stats['pending_questions'],
            'approved' => (int)$stats['approved_questions'], 
            'rejected' => (int)$stats['rejected_questions'],
            'total' => (int)$stats['total_questions']
        ];
        
        // For coordinator evaluation: all custom questions must be approved
        $canSubmitAssessment = $statusCounts['total'] > 0 && $statusCounts['pending'] == 0 && $statusCounts['rejected'] == 0;
    } else {
        // Original student logic - still check questions for student view
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
            WHERE student_id = ? AND category != 'Personal and Interpersonal Skills'
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
    }
    
    // Check if self-assessment is started (student has answered any evaluation questions)
    $eval_student_id = isset($_POST['action']) && $_POST['action'] === 'checkReadiness' ? $student_id : $student_id;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM student_evaluation WHERE STUDENT_ID = ?");
    $stmt->execute([$eval_student_id]);
    $selfAssessmentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $hasSelfAssessment = $selfAssessmentCount > 0;
    
    // For coordinator readiness check, provide detailed status
    if (isset($_POST['action']) && $_POST['action'] === 'checkReadiness') {
        echo json_encode([
            'success' => true,
            'questions_status' => $statusCounts,
            'has_self_assessment' => $hasSelfAssessment,
            'can_evaluate' => $canSubmitAssessment && $hasSelfAssessment
        ]);
    } else {
        // Original student response
        echo json_encode([
            'success' => true,
            'questions' => $questions,
            'status_counts' => $statusCounts,
            'can_submit_assessment' => $canSubmitAssessment,
            'message' => getStatusMessage($statusCounts)
        ]);
    }
    
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