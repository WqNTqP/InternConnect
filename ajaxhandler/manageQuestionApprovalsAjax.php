<?php
/**
 * AJAX handler for managing student question approvals in coordinator dashboard
 */

session_start();
if (!isset($_SESSION["admin_user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not authorized"]);
    exit;
}

$coordinator_id = $_SESSION["admin_user"];
header('Content-Type: application/json');

require_once __DIR__ . '/../config/path_config.php';
require_once PathConfig::getDatabasePath();

try {
    $db = new Database();
    $conn = $db->conn;
    
    // Make coordinator_id globally available for functions
    global $coordinator_id;
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'getPendingQuestions':
            getPendingQuestions($conn);
            break;
            
        case 'approveQuestion':
            approveQuestion($conn, $coordinator_id);
            break;
            
        case 'rejectQuestion':
            rejectQuestion($conn, $coordinator_id);
            break;
            
        case 'getApprovalStats':
            getApprovalStats($conn);
            break;
            
        case 'getStudentsWithQuestions':
            getStudentsWithQuestions($conn);
            break;
            
        case 'getStudentQuestions':
            getStudentQuestions($conn);
            break;
            
        case 'bulkApproveQuestions':
            bulkApproveQuestions($conn);
            break;
            
        case 'bulkRejectQuestions':
            bulkRejectQuestions($conn);
            break;
        
    default:
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getPendingQuestions($conn) {
    global $coordinator_id;
    
    // First get the admin's HTE_ID
    $hteStmt = $conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $hteStmt->execute([$coordinator_id]);
    $adminHTE = $hteStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminHTE || !$adminHTE['HTE_ID']) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin is not assigned to any HTE'
        ]);
        return;
    }
    
    $hteId = $adminHTE['HTE_ID'];
    
    $stmt = $conn->prepare("
        SELECT 
            sq.id,
            sq.student_id,
            sq.category,
            sq.question_text,
            sq.question_number,
            sq.created_at,
            sq.approval_status,
            sq.rejection_reason,
            CONCAT(id.NAME, ' (', id.STUDENT_ID, ')') as student_name
        FROM student_questions sq
        LEFT JOIN interns_details id ON sq.student_id = id.STUDENT_ID
        LEFT JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
        WHERE sq.approval_status IN ('pending', 'rejected')
        AND itd.HTE_ID = ?
        ORDER BY sq.created_at ASC
    ");
    
    $stmt->execute([$hteId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
}

function approveQuestion($conn, $coordinator_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $question_id = $data['question_id'] ?? null;
    
    if (!$question_id) {
        throw new Exception('Question ID required');
    }
    
    $stmt = $conn->prepare("
        UPDATE student_questions 
        SET approval_status = 'approved',
            approved_by = ?,
            approval_date = NOW(),
            rejection_reason = NULL
        WHERE id = ?
    ");
    
    $stmt->execute([$coordinator_id, $question_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Question approved successfully'
    ]);
}

function rejectQuestion($conn, $coordinator_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $question_id = $data['question_id'] ?? null;
    $rejection_reason = $data['rejection_reason'] ?? '';
    
    if (!$question_id) {
        throw new Exception('Question ID required');
    }
    
    if (empty($rejection_reason)) {
        throw new Exception('Rejection reason required');
    }
    
    $stmt = $conn->prepare("
        UPDATE student_questions 
        SET approval_status = 'rejected',
            approved_by = ?,
            approval_date = NOW(),
            rejection_reason = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$coordinator_id, $rejection_reason, $question_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Question rejected successfully'
    ]);
}

function getApprovalStats($conn) {
    global $coordinator_id;
    
    // Get admin's HTE_ID
    $hteStmt = $conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $hteStmt->execute([$coordinator_id]);
    $adminHTE = $hteStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminHTE || !$adminHTE['HTE_ID']) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin is not assigned to any HTE'
        ]);
        return;
    }
    
    $hteId = $adminHTE['HTE_ID'];
    
    $stmt = $conn->prepare("
        SELECT 
            sq.approval_status,
            COUNT(*) as count
        FROM student_questions sq
        LEFT JOIN interns_details id ON sq.student_id = id.STUDENT_ID
        LEFT JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
        WHERE itd.HTE_ID = ?
        GROUP BY sq.approval_status
    ");
    
    $stmt->execute([$hteId]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statsFormatted = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0
    ];
    
    foreach ($stats as $stat) {
        $statsFormatted[$stat['approval_status']] = $stat['count'];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $statsFormatted
    ]);
}

function getStudentsWithQuestions($conn) {
    global $coordinator_id;
    
    // Get admin's HTE_ID
    $hteStmt = $conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $hteStmt->execute([$coordinator_id]);
    $adminHTE = $hteStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminHTE || !$adminHTE['HTE_ID']) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin is not assigned to any HTE'
        ]);
        return;
    }
    
    $hteId = $adminHTE['HTE_ID'];
    
    // Get students under this HTE who have submitted questions
    $stmt = $conn->prepare("
        SELECT 
            id.INTERNS_ID as student_id,
            id.SURNAME,
            id.NAME,
            CONCAT(id.SURNAME, ', ', id.NAME) as student_name,
            COUNT(sq.id) as total_questions,
            SUM(CASE WHEN sq.approval_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN sq.approval_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN sq.approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        FROM interns_details id
        JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
        LEFT JOIN student_questions sq ON id.INTERNS_ID = sq.student_id
        WHERE itd.HTE_ID = ? AND sq.id IS NOT NULL
        GROUP BY id.INTERNS_ID, id.SURNAME, id.NAME
        ORDER BY id.SURNAME, id.NAME
    ");
    
    $stmt->execute([$hteId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
}

function getStudentQuestions($conn) {
    global $coordinator_id;
    
    // Handle both JSON and regular POST/GET parameters
    $data = json_decode(file_get_contents('php://input'), true);
    $student_id = $data['student_id'] ?? $_POST['student_id'] ?? $_GET['student_id'] ?? null;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'error' => 'Student ID required']);
        return;
    }
    
    // Verify student is under admin's HTE
    $hteStmt = $conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $hteStmt->execute([$coordinator_id]);
    $adminHTE = $hteStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminHTE || !$adminHTE['HTE_ID']) {
        echo json_encode(['success' => false, 'error' => 'Admin is not assigned to any HTE']);
        return;
    }
    
    // Verify student belongs to admin's HTE
    $verifyStmt = $conn->prepare("
        SELECT id.STUDENT_ID 
        FROM interns_details id
        JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
        WHERE id.INTERNS_ID = ? AND itd.HTE_ID = ?
    ");
    $verifyStmt->execute([$student_id, $adminHTE['HTE_ID']]);
    
    if (!$verifyStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Student not found or not assigned to your HTE']);
        return;
    }
    
    // Get student's questions
    $stmt = $conn->prepare("
        SELECT 
            sq.*,
            CONCAT(id.SURNAME, ', ', id.NAME) as student_name
        FROM student_questions sq
        JOIN interns_details id ON sq.student_id = id.INTERNS_ID
        WHERE sq.student_id = ?
        ORDER BY sq.category, sq.question_number
    ");
    
    $stmt->execute([$student_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
}

function bulkApproveQuestions($conn) {
    global $coordinator_id;
    
    $question_ids = $_POST['question_ids'] ?? [];
    
    if (empty($question_ids)) {
        echo json_encode(['success' => false, 'error' => 'No questions selected']);
        return;
    }
    
    // Verify admin's HTE access and question ownership
    $hteStmt = $conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $hteStmt->execute([$coordinator_id]);
    $adminHTE = $hteStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminHTE || !$adminHTE['HTE_ID']) {
        echo json_encode(['success' => false, 'error' => 'Admin is not assigned to any HTE']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        // Build placeholders for IN clause
        $placeholders = str_repeat('?,', count($question_ids) - 1) . '?';
        
        // Verify all questions belong to students under admin's HTE
        $verifyStmt = $conn->prepare("
            SELECT COUNT(*) as valid_count
            FROM student_questions sq
            JOIN interns_details id ON sq.student_id = id.INTERNS_ID
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE sq.id IN ($placeholders) 
            AND itd.HTE_ID = ? 
            AND sq.approval_status = 'pending'
        ");
        
        $params = array_merge($question_ids, [$adminHTE['HTE_ID']]);
        $verifyStmt->execute($params);
        $result = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['valid_count'] != count($question_ids)) {
            throw new Exception('Some questions are not valid for approval or not under your HTE');
        }
        
        // Update questions to approved
        $updateStmt = $conn->prepare("
            UPDATE student_questions 
            SET approval_status = 'approved', 
                approved_by = ?, 
                approval_date = NOW() 
            WHERE id IN ($placeholders)
        ");
        
        $updateParams = array_merge([$coordinator_id], $question_ids);
        $updateStmt->execute($updateParams);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => count($question_ids) . ' questions approved successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function bulkRejectQuestions($conn) {
    global $coordinator_id;
    
    $question_ids = $_POST['question_ids'] ?? [];
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    if (empty($question_ids)) {
        echo json_encode(['success' => false, 'error' => 'No questions selected']);
        return;
    }
    
    if (empty($rejection_reason)) {
        echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
        return;
    }
    
    // Verify admin's HTE access
    $hteStmt = $conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $hteStmt->execute([$coordinator_id]);
    $adminHTE = $hteStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminHTE || !$adminHTE['HTE_ID']) {
        echo json_encode(['success' => false, 'error' => 'Admin is not assigned to any HTE']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        // Build placeholders for IN clause
        $placeholders = str_repeat('?,', count($question_ids) - 1) . '?';
        
        // Verify all questions belong to students under admin's HTE
        $verifyStmt = $conn->prepare("
            SELECT COUNT(*) as valid_count
            FROM student_questions sq
            JOIN interns_details id ON sq.student_id = id.INTERNS_ID
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE sq.id IN ($placeholders) 
            AND itd.HTE_ID = ? 
            AND sq.approval_status = 'pending'
        ");
        
        $params = array_merge($question_ids, [$adminHTE['HTE_ID']]);
        $verifyStmt->execute($params);
        $result = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['valid_count'] != count($question_ids)) {
            throw new Exception('Some questions are not valid for rejection or not under your HTE');
        }
        
        // Update questions to rejected
        $updateStmt = $conn->prepare("
            UPDATE student_questions 
            SET approval_status = 'rejected', 
                approved_by = ?, 
                approval_date = NOW(),
                rejection_reason = ?
            WHERE id IN ($placeholders)
        ");
        
        $updateParams = array_merge([$coordinator_id, $rejection_reason], $question_ids);
        $updateStmt->execute($updateParams);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => count($question_ids) . ' questions rejected successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>