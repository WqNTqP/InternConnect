<?php
session_start();
require_once '../database/database.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Debug: Log what we received
    error_log("saveStudentQuestionsAjax received: " . json_encode($data));
    
    // Try to get student_id from POST, session, or fallback
    $student_id = isset($data['student_id']) ? $data['student_id'] : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
    
    if (!$student_id) {
        // Return debug info about what was received
        echo json_encode([
            'success' => false, 
            'error' => 'Student ID not provided.',
            'debug' => [
                'data_received' => $data,
                'session_student_id' => isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null,
                'post_data' => $_POST
            ]
        ]);
        exit;
    }
    // Initialize $conn using Database class
    $db = new Database();
    $conn = $db->conn;
    if (!$conn) {
        throw new Exception('Database connection not initialized.');
    }
    $questions = $data['questions'];
    $savedQuestions = [];
    foreach ($questions as $q) {
            // Check if question already exists for this student, category, and question_number
            $checkStmt = $conn->prepare("SELECT id FROM student_questions WHERE student_id = ? AND category = ? AND question_number = ?");
            $checkStmt->execute([$student_id, $q['category'], $q['question_number']]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            // Determine approval status - Personal Skills questions are auto-approved
            $isPersonalSkills = ($q['category'] === 'Personal and Interpersonal Skills');
            $approvalStatus = $isPersonalSkills ? 'approved' : 'pending';
            
            if ($existing) {
                // Update existing question
                if ($isPersonalSkills) {
                    // Personal skills questions are always approved
                    $updateStmt = $conn->prepare("UPDATE student_questions SET question_text = ?, approval_status = 'approved', approved_by = 0, approval_date = NOW(), rejection_reason = NULL WHERE id = ?");
                    $updateStmt->execute([$q['question_text'], $existing['id']]);
                } else {
                    // Custom questions reset to pending for re-approval
                    $updateStmt = $conn->prepare("UPDATE student_questions SET question_text = ?, approval_status = 'pending', approved_by = NULL, approval_date = NULL, rejection_reason = NULL WHERE id = ?");
                    $updateStmt->execute([$q['question_text'], $existing['id']]);
                }
                
                $savedQuestions[] = [
                    'id' => $existing['id'],
                    'question_text' => $q['question_text'],
                    'category' => $q['category'],
                    'question_number' => $q['question_number'],
                    'approval_status' => $approvalStatus
                ];
            } else {
                // Insert new question
                if ($isPersonalSkills) {
                    // Personal skills questions are automatically approved
                    $stmt = $conn->prepare("INSERT INTO student_questions (student_id, category, question_text, question_number, approval_status, approved_by, approval_date) VALUES (?, ?, ?, ?, 'approved', 0, NOW())");
                    $stmt->execute([
                        $student_id,
                        $q['category'],
                        $q['question_text'],
                        $q['question_number']
                    ]);
                } else {
                    // Custom questions need approval
                    $stmt = $conn->prepare("INSERT INTO student_questions (student_id, category, question_text, question_number, approval_status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([
                        $student_id,
                        $q['category'],
                        $q['question_text'],
                        $q['question_number']
                    ]);
                }
                
                $savedQuestions[] = [
                    'id' => $conn->lastInsertId(),
                    'question_text' => $q['question_text'],
                    'category' => $q['category'],
                    'question_number' => $q['question_number'],
                    'approval_status' => $approvalStatus
                ];
            }
    }
    echo json_encode(['success' => true, 'savedQuestions' => $savedQuestions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

