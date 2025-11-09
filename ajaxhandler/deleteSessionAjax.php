<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . "/database/database.php";

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $dbo = new Database();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sessionId = isset($_POST['sessionId']) ? intval($_POST['sessionId']) : 0;
        
        // Validate session ID
        if ($sessionId <= 0) {
            $response['message'] = 'Invalid session ID';
            echo json_encode($response);
            exit;
        }
        
        // Check if session exists
        $checkQuery = "SELECT COUNT(*) FROM session_details WHERE ID = ?";
        $stmt = $dbo->conn->prepare($checkQuery);
        $stmt->execute([$sessionId]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $response['message'] = 'Session not found';
            echo json_encode($response);
            exit;
        }
        
        // Start transaction for cascading deletion
        $dbo->conn->beginTransaction();
        
        try {
            // Get all interns in this session
            $getInternsQuery = "SELECT INTERNS_ID FROM intern_details WHERE SESSION_ID = ?";
            $stmt = $dbo->conn->prepare($getInternsQuery);
            $stmt->execute([$sessionId]);
            $internIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            $deletedCounts = [
                'attendance' => 0,
                'pending_attendance' => 0,
                'interns' => 0,
                'hte_assignments' => 0
            ];
            
            if (!empty($internIds)) {
                $placeholders = implode(',', array_fill(0, count($internIds), '?'));
                
                // 1. Delete attendance records for interns in this session
                $deleteAttendanceQuery = "DELETE FROM interns_attendance WHERE INTERNS_ID IN ($placeholders)";
                $stmt = $dbo->conn->prepare($deleteAttendanceQuery);
                $stmt->execute($internIds);
                $deletedCounts['attendance'] = $stmt->rowCount();
                
                // 2. Delete pending attendance records for interns in this session
                $deletePendingQuery = "DELETE FROM pending_attendance WHERE INTERNS_ID IN ($placeholders)";
                $stmt = $dbo->conn->prepare($deletePendingQuery);
                $stmt->execute($internIds);
                $deletedCounts['pending_attendance'] = $stmt->rowCount();
                
                // 3. Delete interns_details records (students) for interns in this session
                $deleteInternsDetailsQuery = "DELETE FROM interns_details WHERE INTERNS_ID IN ($placeholders)";
                $stmt = $dbo->conn->prepare($deleteInternsDetailsQuery);
                $stmt->execute($internIds);
                $deletedCounts['interns'] = $stmt->rowCount();
            }
            
            // 4. Delete intern_details links for this session
            $deleteInternLinksQuery = "DELETE FROM intern_details WHERE SESSION_ID = ?";
            $stmt = $dbo->conn->prepare($deleteInternLinksQuery);
            $stmt->execute([$sessionId]);
            
            // 5. Delete internship_needs (HTE assignments) for this session
            $deleteInternshipNeedsQuery = "DELETE FROM internship_needs WHERE SESSION_ID = ?";
            $stmt = $dbo->conn->prepare($deleteInternshipNeedsQuery);
            $stmt->execute([$sessionId]);
            $deletedCounts['hte_assignments'] = $stmt->rowCount();
            
            // 6. Finally, delete the session itself
            $deleteSessionQuery = "DELETE FROM session_details WHERE ID = ?";
            $stmt = $dbo->conn->prepare($deleteSessionQuery);
            $stmt->execute([$sessionId]);
            
            // Commit transaction
            $dbo->conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Session and all associated data deleted successfully';
            $response['deletedCounts'] = $deletedCounts;
            $response['sessionId'] = $sessionId;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $dbo->conn->rollBack();
            throw $e;
        }
        
    } else {
        $response['message'] = 'Invalid request method';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>

