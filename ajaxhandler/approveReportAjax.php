<?php
session_start();
require_once '../database/database.php';

header('Content-Type: application/json');

// Initialize database connection
$dbo = new Database();
$conn = $dbo->conn;

// Check if admin is logged in
if (!isset($_SESSION['admin_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? '';
$reportId = $_POST['reportId'] ?? null;

try {
    switch ($action) {
        case 'approveReport':
            approveReport($reportId);
            break;
        case 'returnReport':
            $returnReason = $_POST['returnReason'] ?? '';
            returnReport($reportId, $returnReason);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            exit;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

function approveReport($reportId) {
    global $dbo;
    
    if (!$reportId) {
        echo json_encode(['status' => 'error', 'message' => 'Report ID is required']);
        return;
    }

    try {
        $sql = "UPDATE weekly_reports 
                SET approval_status = 'approved',
                    approved_at = CURRENT_TIMESTAMP,
                    approved_by = :adminId
                WHERE report_id = :reportId";
        
        $stmt = $dbo->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }
        
        $adminId = $_SESSION['admin_user'];
        $stmt->bindParam(':adminId', $adminId, PDO::PARAM_INT);
        $stmt->bindParam(':reportId', $reportId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Report approved successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No report was updated']);
            }
        } else {
            throw new Exception("Failed to execute statement");
        }
    } catch (Exception $e) {
        error_log("Error in approveReport: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function returnReport($reportId, $returnReason) {
    global $dbo;
    
    if (!$reportId) {
        echo json_encode(['status' => 'error', 'message' => 'Report ID is required']);
        return;
    }

    if (empty($returnReason)) {
        echo json_encode(['status' => 'error', 'message' => 'Return reason is required']);
        return;
    }

    try {
        // First verify the report exists
        $checkSql = "SELECT report_id, status FROM weekly_reports WHERE report_id = :reportId";
        $checkStmt = $dbo->conn->prepare($checkSql);
        $checkStmt->bindParam(':reportId', $reportId, PDO::PARAM_INT);
        $checkStmt->execute();
        $report = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            echo json_encode(['status' => 'error', 'message' => 'Report not found']);
            return;
        }

        error_log("Returning report ID: " . $reportId);
        error_log("Current status: " . $report['status']);
        error_log("Return reason: " . $returnReason);

        // Update report status to allow editing and set return status
        $sql = "UPDATE weekly_reports 
                SET status = 'draft',
                    approval_status = 'returned',
                    return_reason = :reason,
                    updated_at = NOW()
                WHERE report_id = :reportId";
        
        $stmt = $dbo->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }
        
        $stmt->bindParam(':reason', $returnReason, PDO::PARAM_STR);
        $stmt->bindParam(':reportId', $reportId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $rowCount = $stmt->rowCount();
            error_log("Rows affected: " . $rowCount);
            
            if ($rowCount > 0) {
                // Create notification for the student
                $notifSql = "INSERT INTO notifications (
                    receiver_id, receiver_type, title, message, 
                    reference_id, reference_type, notification_type
                ) SELECT 
                    w.interns_id,
                    'student',
                    'Report Returned',
                    'Your report has been returned for revision. Check report tab for details.',
                    w.report_id,
                    'report',
                    'report_returned'
                FROM weekly_reports w 
                WHERE w.report_id = :reportId";
                
                $notifStmt = $dbo->conn->prepare($notifSql);
                $notifStmt->bindParam(':reportId', $reportId, PDO::PARAM_INT);
                $notifStmt->execute();
                
                echo json_encode(['status' => 'success', 'message' => 'Report returned to student for revision']);
            } else {
                // Double check if the report was actually updated
                $checkStmt->execute();
                $updatedReport = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if ($updatedReport && $updatedReport['status'] === 'draft') {
                    echo json_encode(['status' => 'success', 'message' => 'Report returned to student for revision']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update report status']);
                }
            }
        } else {
            throw new Exception("Failed to execute statement");
        }
    } catch (Exception $e) {
        error_log("Error in returnReport: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
