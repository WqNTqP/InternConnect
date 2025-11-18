<?php
/**
 * Test script to verify HTE-based question filtering
 */

session_start();
require_once 'database/database.php';

if (!isset($_SESSION["admin_user"])) {
    echo "Please login as admin first\n";
    exit;
}

$coordinator_id = $_SESSION["admin_user"];
$db = new Database();
$conn = $db->conn;

echo "=== Question Approvals HTE Filtering Test ===\n\n";

// Get admin's HTE info
$stmt = $conn->prepare("SELECT c.COORDINATOR_ID, c.NAME, c.HTE_ID, h.NAME as HTE_NAME FROM coordinator c LEFT JOIN host_training_establishment h ON c.HTE_ID = h.HTE_ID WHERE c.COORDINATOR_ID = ?");
$stmt->execute([$coordinator_id]);
$adminInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($adminInfo) {
    echo "Admin Info:\n";
    echo "- ID: " . $adminInfo['COORDINATOR_ID'] . "\n";
    echo "- Name: " . $adminInfo['NAME'] . "\n";
    echo "- HTE ID: " . ($adminInfo['HTE_ID'] ?: 'Not assigned') . "\n";
    echo "- HTE Name: " . ($adminInfo['HTE_NAME'] ?: 'Not assigned') . "\n\n";
    
    if ($adminInfo['HTE_ID']) {
        // Get students under this HTE
        $stmt = $conn->prepare("
            SELECT id.STUDENT_ID, id.NAME, id.SURNAME, itd.HTE_ID 
            FROM interns_details id 
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID 
            WHERE itd.HTE_ID = ?
        ");
        $stmt->execute([$adminInfo['HTE_ID']]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Students under this HTE (" . count($students) . " total):\n";
        foreach ($students as $student) {
            echo "- " . $student['SURNAME'] . ", " . $student['NAME'] . " (ID: " . $student['STUDENT_ID'] . ")\n";
        }
        
        // Get questions from these students
        $stmt = $conn->prepare("
            SELECT 
                sq.id, sq.student_id, sq.category, sq.approval_status,
                id.NAME, id.SURNAME
            FROM student_questions sq
            JOIN interns_details id ON sq.student_id = id.STUDENT_ID
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE itd.HTE_ID = ?
            ORDER BY sq.created_at DESC
        ");
        $stmt->execute([$adminInfo['HTE_ID']]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n\nQuestions from these students (" . count($questions) . " total):\n";
        $statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($questions as $question) {
            echo "- " . $question['SURNAME'] . ", " . $question['NAME'] . 
                 " | " . $question['category'] . 
                 " | Status: " . $question['approval_status'] . "\n";
            $statusCounts[$question['approval_status']]++;
        }
        
        echo "\nStatus Summary:\n";
        echo "- Pending: " . $statusCounts['pending'] . "\n";
        echo "- Approved: " . $statusCounts['approved'] . "\n";
        echo "- Rejected: " . $statusCounts['rejected'] . "\n";
        
    } else {
        echo "❌ Admin is not assigned to any HTE - cannot manage questions\n";
    }
} else {
    echo "❌ Admin not found in database\n";
}

echo "\n=== Test Complete ===\n";
?>