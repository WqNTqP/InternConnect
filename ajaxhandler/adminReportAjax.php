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

try {
    switch ($action) {
        case 'getReports':
            getReports();
            break;
        case 'getReportDetails':
            $reportId = $_POST['reportId'] ?? null;
            getReportDetails($reportId);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            exit;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

function getReports() {
    global $conn, $dbo;
    
    try {
        // Get admin's HTE_ID
        $adminId = $_SESSION['admin_user'];
        $adminStmt = $conn->prepare("SELECT HTE_ID FROM admin WHERE admin_id = ?");
        $adminStmt->execute([$adminId]);
        $hteId = $adminStmt->fetch(PDO::FETCH_COLUMN);

        // Fetch reports for students under this admin
        $stmt = $conn->prepare("
            SELECT 
                wr.*, 
                id.NAME as student_name,
                CONCAT(id.NAME, ' ', id.SURNAME) as full_name,
                id.STUDENT_ID
            FROM weekly_reports wr
            JOIN interns_details id ON wr.interns_id = id.INTERNS_ID
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE itd.HTE_ID = ?
            ORDER BY wr.created_at DESC
        ");
        $stmt->execute([$hteId]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data
        foreach ($reports as &$report) {
            $report['week_number'] = date('W', strtotime($report['week_start']));
            
            // Get images for preview (limiting to first 3)
            $imageStmt = $conn->prepare("
                SELECT image_filename, day_of_week 
                FROM report_images 
                WHERE report_id = ?
                LIMIT 3
            ");
            $imageStmt->execute([$report['report_id']]);
            $report['preview_images'] = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['status' => 'success', 'reports' => $reports]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getReportDetails($reportId) {
    global $conn;
    
    if (!$reportId) {
        echo json_encode(['status' => 'error', 'message' => 'Report ID is required']);
        return;
    }

    try {
        // Get report details with student info
        $stmt = $conn->prepare("
            SELECT 
                wr.*,
                CONCAT(id.NAME, ' ', id.SURNAME) as student_name,
                id.STUDENT_ID
            FROM weekly_reports wr
            JOIN interns_details id ON wr.interns_id = id.INTERNS_ID
            WHERE wr.report_id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            echo json_encode(['status' => 'error', 'message' => 'Report not found']);
            return;
        }

        // Get all images for this report
        $imageStmt = $conn->prepare("
            SELECT image_filename, day_of_week
            FROM report_images
            WHERE report_id = ?
        ");
        $imageStmt->execute([$reportId]);
        $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

        // Organize images by day
        $report['imagesPerDay'] = [];
        foreach ($images as $image) {
            $day = $image['day_of_week'] ?: 'monday';
            if (!isset($report['imagesPerDay'][$day])) {
                $report['imagesPerDay'][$day] = [];
            }
            $report['imagesPerDay'][$day][] = [
                'filename' => $image['image_filename']
            ];
        }

        echo json_encode(['status' => 'success', 'report' => $report]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
