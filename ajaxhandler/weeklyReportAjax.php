<?php
// Configure session to work across different hostnames/IPs
ini_set('session.cookie_domain', '');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_secure', false); // Set to true if using HTTPS
ini_set('session.cookie_httponly', true);
ini_set('session.use_only_cookies', true);

session_start();
require_once '../database/database.php';

header('Content-Type: application/json');

// Suppress warnings and errors to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Initialize database connection
$dbo = new Database();
$conn = $dbo->conn;

/**
 * Adjust session checks to support student session variable names
 * from student_dashboard.php where student ID is stored in $_SESSION['student_user']
 * and user_type is not set.
 */

// Normalize session variables for user_id and user_type
if (isset($_SESSION['student_user'])) {
    $_SESSION['user_id'] = $_SESSION['student_user'];
    $_SESSION['user_type'] = 'student';
}

// Handle admin session from admindashboarddb.php
if (isset($_SESSION['admin_user'])) {
    $_SESSION['user_id'] = $_SESSION['admin_user'];
    $_SESSION['user_type'] = 'admin';
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// For admin dashboard access to weekly reports, allow admin users
if ($_SESSION['user_type'] !== 'student' && $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? '';
$studentId = $_POST['studentId'] ?? $_SESSION['user_id'];

try {
    switch ($action) {
        case 'getWeeklyReport':
            getWeeklyReport($studentId);
            break;
        case 'getWeeklyReports':
            getWeeklyReports();
            break;
        case 'saveReportDraft':
            saveReportDraft($studentId);
            break;
        case 'submitFinalReport':
            submitFinalReport($studentId);
            break;
        case 'generatePDF':
            generateWeeklyReportPDF($studentId);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}

function getWeeklyReports() {
    global $conn;

    $studentId = $_POST['studentId'] ?? null;
    $weekStart = $_POST['weekStart'] ?? null;
    $weekEnd = $_POST['weekEnd'] ?? null;

    // Debug logging
    error_log("Searching for weekly reports with:");
    error_log("Student ID: " . $studentId);
    error_log("Week Start: " . $weekStart);
    error_log("Week End: " . $weekEnd);

    // Get base URL
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/';

    // Build the query based on filters
    $query = "
        SELECT r.*,
       i.SURNAME as student_name,
       ri.image_filename, 
       ri.day_of_week
FROM weekly_reports r
LEFT JOIN interns_details i ON r.interns_id = i.INTERNS_ID
LEFT JOIN report_images ri ON r.report_id = ri.report_id
WHERE r.status = 'submitted'
  AND r.approval_status = 'pending'
    ";

    $params = [];

    if ($studentId && $studentId !== 'all') {
        $query .= " AND r.interns_id = ?";
        $params[] = $studentId;
    }

    $weekStart = $_POST['weekStart'] ?? null;
    $weekEnd = $_POST['weekEnd'] ?? null;

    if ($weekStart && $weekEnd) {
        $query .= " AND r.week_start = ? AND r.week_end = ?";
        $params[] = $weekStart;
        $params[] = $weekEnd;
    }

    $query .= " ORDER BY r.created_at DESC, ri.day_of_week, ri.uploaded_at";

    // Debug logging
    error_log("SQL Query: " . $query);
    error_log("Parameters: " . print_r($params, true));

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . print_r($conn->errorInfo(), true));
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
        exit;
    }

    $result = $stmt->execute($params);
    if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
    }
    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Execute failed']);
        exit;
    }

    $reports = [];
    $currentReportId = null;
    $currentReport = null;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($currentReportId !== $row['report_id']) {
            // Save previous report if exists
            if ($currentReport) {
                $reports[] = $currentReport;
            }

            // Start new report
            $currentReportId = $row['report_id'];
            $currentReport = $row;

            // Process per-day content
            if (!empty($row['report_content'])) {
                $contentData = json_decode($row['report_content'], true);
                if (is_array($contentData)) {
                    $currentReport['contentPerDay'] = $contentData;
                } else {
                    // Fallback for old format
                    $currentReport['contentPerDay'] = [
                        'monday' => $row['report_content'],
                        'tuesday' => '',
                        'wednesday' => '',
                        'thursday' => '',
                        'friday' => ''
                    ];
                }
            } else {
                $currentReport['contentPerDay'] = [
                    'monday' => '',
                    'tuesday' => '',
                    'wednesday' => '',
                    'thursday' => '',
                    'friday' => ''
                ];
            }

            // Initialize images per day
            $currentReport['imagesPerDay'] = [
                'monday' => [],
                'tuesday' => [],
                'wednesday' => [],
                'thursday' => [],
                'friday' => []
            ];

            $currentReport['images'] = []; // Legacy format
        }

        // Add image to current report
        if (!empty($row['image_filename'])) {
            $day = $row['day_of_week'] ?: 'monday'; // Default to monday if no day specified
            $imageData = [
                'filename' => $row['image_filename'],
                'url' => $baseUrl . 'uploads/reports/' . $row['image_filename']
            ];

            if (isset($currentReport['imagesPerDay'][$day])) {
                $currentReport['imagesPerDay'][$day][] = $imageData;
            } else {
                $currentReport['imagesPerDay']['monday'][] = $imageData; // Fallback
            }

            $currentReport['images'][] = $imageData; // Legacy format
        }
    }

    // Add the last report
    if ($currentReport) {
        $reports[] = $currentReport;
    }

    echo json_encode([
        'status' => 'success',
        'reports' => $reports
    ]);
}

function getWeeklyReport($studentId) {
    global $conn;
    
    $weekInput = $_POST['week'] ?? null;
    $week = null;
    $weekStart = null;
    $weekEnd = null;

    if ($weekInput !== null) {
        // If week is numeric (week number), calculate start and end dates
        if (is_numeric($weekInput)) {
            $year = date('Y');
            $weekStart = date('Y-m-d', strtotime($year . 'W' . str_pad($weekInput, 2, '0', STR_PAD_LEFT)));
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        } else {
            // If week is a date range string or date, parse accordingly
            // For simplicity, assume weekInput is a date string representing the start of the week
            $weekStart = date('Y-m-d', strtotime($weekInput));
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        }
    } else {
        // Default to current week Monday-Sunday
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
    }
    
    // Get report by week start and end dates
    $report = getReportByWeekDates($studentId, $weekStart, $weekEnd);
    
    // Get all submitted reports for this student
    $submittedReports = getSubmittedReports($studentId);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'report' => $report,
            'submittedReports' => $submittedReports
        ]
    ]);
}

function getReportByWeekDates($studentId, $weekStart, $weekEnd) {
    global $conn;

    // Get base URL for image paths
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/';

    $stmt = $conn->prepare("
        SELECT r.*,
               ri.image_filename, ri.day_of_week
        FROM weekly_reports r
        LEFT JOIN report_images ri ON r.report_id = ri.report_id
        WHERE r.interns_id = ? AND r.week_start = ? AND r.week_end = ?
        ORDER BY ri.day_of_week, ri.uploaded_at
    ");

    $stmt->execute([$studentId, $weekStart, $weekEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        return null;
    }

    // Extract report data from the first row (all rows should have the same report data)
    $result = $rows[0];
    // Remove image-specific fields from the main result
    unset($result['image_filename'], $result['day_of_week']);

    // Process per-day content
    if (!empty($result['report_content'])) {
        $contentData = json_decode($result['report_content'], true);
        if (is_array($contentData)) {
            $result['contentPerDay'] = $contentData;
        } else {
            // Fallback for old format
            $result['contentPerDay'] = [
                'monday' => $result['report_content'],
                'tuesday' => '',
                'wednesday' => '',
                'thursday' => '',
                'friday' => ''
            ];
        }
    } else {
        $result['contentPerDay'] = [
            'monday' => '',
            'tuesday' => '',
            'wednesday' => '',
            'thursday' => '',
            'friday' => ''
        ];
    }

    // Process images per day
    $imagesPerDay = [
        'monday' => [],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => []
    ];

    foreach ($rows as $row) {
        if (!empty($row['image_filename'])) {
            $day = $row['day_of_week'] ?: 'monday'; // Default to monday if no day specified
            $imageUrl = $baseUrl . 'uploads/reports/' . $row['image_filename'];
            $imagesPerDay[$day][] = [
                'filename' => $row['image_filename'],
                'url' => $imageUrl
            ];
        }
    }

    $result['imagesPerDay'] = $imagesPerDay;

    // Keep legacy format for backward compatibility
    $allImages = [];
    foreach ($imagesPerDay as $dayImages) {
        $allImages = array_merge($allImages, $dayImages);
    }
    $result['images'] = $allImages;

    unset($result['report_content']);

    return $result;
}

function saveReportDraft($studentId) {
    global $conn;

    $week = $_POST['week'] ?? getCurrentWeek();

    // Debug: Log all POST and FILES data
    error_log("saveReportDraft called with POST: " . print_r($_POST, true));
    error_log("saveReportDraft called with FILES: " . print_r($_FILES, true));

    // Handle per-day content
    $contentPerDay = [];
    if (!empty($_POST['contentPerDay'])) {
        $contentPerDay = json_decode($_POST['contentPerDay'], true);
        if (!is_array($contentPerDay)) {
            $contentPerDay = [];
        }
    }

    // Collect daily descriptions
    $mondayDescription = $_POST['mondayDescription'] ?? '';
    $tuesdayDescription = $_POST['tuesdayDescription'] ?? '';
    $wednesdayDescription = $_POST['wednesdayDescription'] ?? '';
    $thursdayDescription = $_POST['thursdayDescription'] ?? '';
    $fridayDescription = $_POST['fridayDescription'] ?? '';

    // Check if report already exists
    $existingReport = getReportByWeek($studentId, $week);

    if ($existingReport && $existingReport['status'] === 'submitted') {
        echo json_encode(['status' => 'error', 'message' => 'Cannot edit submitted report']);
        return;
    }

    // Handle file uploads per day
    $uploadedImagesPerDay = [];
    if (!empty($_FILES)) {
        error_log("Files detected, processing uploads");
        $uploadedImagesPerDay = handleImageUploadsPerDay($_FILES);
    } else {
        error_log("No files detected in _FILES");
    }

    // Handle existing images sent from frontend per day
    $existingImagesPerDay = [];
    if (!empty($_POST['existingImages'])) {
        $existingImagesPerDay = json_decode($_POST['existingImages'], true);
        if (!is_array($existingImagesPerDay)) {
            $existingImagesPerDay = [];
        }
    }

    // Normalize existing images to consistent format
    foreach ($existingImagesPerDay as $day => &$images) {
        foreach ($images as &$image) {
            if (is_string($image)) {
                $image = ['filename' => $image];
            } elseif (is_array($image) && isset($image['filename'])) {
                // already correct format
            } else {
                $image = [];
            }
        }
    }
    unset($image);
    unset($images);

    // Normalize uploaded images to consistent format
    foreach ($uploadedImagesPerDay as $day => &$images) {
        foreach ($images as &$image) {
            if (isset($image['filename'])) {
                // already correct format
            } elseif (isset($image['original_name'])) {
                $image = ['filename' => $image['filename'] ?? '', 'original_name' => $image['original_name']];
            } else {
                $image = [];
            }
        }
    }
    unset($image);
    unset($images);

    // Merge existing images with newly uploaded images per day
    $allImagesPerDay = [];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    foreach ($days as $day) {
        $allImagesPerDay[$day] = array_merge(
            $existingImagesPerDay[$day] ?? [],
            $uploadedImagesPerDay[$day] ?? []
        );
    }

    if ($existingReport) {
        // Update existing draft
        updateReportPerDay($existingReport['report_id'], $contentPerDay, $allImagesPerDay, 'draft', 
            $mondayDescription, $tuesdayDescription, $wednesdayDescription, $thursdayDescription, $fridayDescription);
    } else {
        // Create new draft
        createReportPerDay($studentId, $week, $contentPerDay, $allImagesPerDay, 'draft',
            $mondayDescription, $tuesdayDescription, $wednesdayDescription, $thursdayDescription, $fridayDescription);
    }

    // Debug: Log images being saved
    error_log("Saving images per day: " . print_r($allImagesPerDay, true));

    echo json_encode(['status' => 'success', 'message' => 'Draft saved successfully']);
}

function submitFinalReport($studentId) {
    global $conn;

    $week = $_POST['week'] ?? getCurrentWeek();

    // Handle per-day content
    $contentPerDay = [];
    if (!empty($_POST['contentPerDay'])) {
        $contentPerDay = json_decode($_POST['contentPerDay'], true);
        if (!is_array($contentPerDay)) {
            $contentPerDay = [];
        }
    }

    // Collect daily descriptions
    $mondayDescription = trim($_POST['mondayDescription'] ?? '');
    $tuesdayDescription = trim($_POST['tuesdayDescription'] ?? '');
    $wednesdayDescription = trim($_POST['wednesdayDescription'] ?? '');
    $thursdayDescription = trim($_POST['thursdayDescription'] ?? '');
    $fridayDescription = trim($_POST['fridayDescription'] ?? '');

    // Check if there's any content in daily descriptions or per-day content
    $hasContent = false;

    $descriptions = [$mondayDescription, $tuesdayDescription, $wednesdayDescription, $thursdayDescription, $fridayDescription];
    foreach ($descriptions as $desc) {
        if (!empty(trim($desc))) {
            $hasContent = true;
            break;
        }
    }

    if (!$hasContent) {
        foreach ($contentPerDay as $dayContent) {
            if (!empty(trim($dayContent))) {
                $hasContent = true;
                break;
            }
        }
    }

    if (!$hasContent) {
        echo json_encode(['status' => 'error', 'message' => 'Report content cannot be empty']);
        return;
    }

    // Check if report already exists
    $existingReport = getReportByWeek($studentId, $week);

    if ($existingReport && $existingReport['status'] === 'submitted') {
        echo json_encode(['status' => 'error', 'message' => 'Report already submitted']);
        return;
    }

    // Handle file uploads per day
    $uploadedImagesPerDay = [];
    if (!empty($_FILES)) {
        error_log("submitFinalReport: Files detected, processing uploads");
        $uploadedImagesPerDay = handleImageUploadsPerDay($_FILES);
    } else {
        error_log("submitFinalReport: No files detected in _FILES");
    }

    // Handle existing images sent from frontend per day
    $existingImagesPerDay = [];
    if (!empty($_POST['existingImages'])) {
        $existingImagesPerDay = json_decode($_POST['existingImages'], true);
        if (!is_array($existingImagesPerDay)) {
            $existingImagesPerDay = [];
        }
    }

    // Normalize existing images to consistent format
    foreach ($existingImagesPerDay as $day => &$images) {
        foreach ($images as &$image) {
            if (is_string($image)) {
                $image = ['filename' => $image];
            } elseif (is_array($image) && isset($image['filename'])) {
                // already correct format
            } else {
                $image = [];
            }
        }
    }
    unset($image);
    unset($images);

    // Normalize uploaded images to consistent format
    foreach ($uploadedImagesPerDay as $day => &$images) {
        foreach ($images as &$image) {
            if (isset($image['filename'])) {
                // already correct format
            } elseif (isset($image['original_name'])) {
                $image = ['filename' => $image['filename'] ?? '', 'original_name' => $image['original_name']];
            } else {
                $image = [];
            }
        }
    }
    unset($image);
    unset($images);

    // Merge existing images with newly uploaded images per day
    $allImagesPerDay = [];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    foreach ($days as $day) {
        $allImagesPerDay[$day] = array_merge(
            $existingImagesPerDay[$day] ?? [],
            $uploadedImagesPerDay[$day] ?? []
        );
    }

    if ($existingReport) {
        // Update and submit existing report
        updateReportPerDay($existingReport['report_id'], $contentPerDay, $allImagesPerDay, 'submitted',
            $mondayDescription, $tuesdayDescription, $wednesdayDescription, $thursdayDescription, $fridayDescription);
    } else {
        // Create and submit new report
        createReportPerDay($studentId, $week, $contentPerDay, $allImagesPerDay, 'submitted',
            $mondayDescription, $tuesdayDescription, $wednesdayDescription, $thursdayDescription, $fridayDescription);
    }

    echo json_encode(['status' => 'success', 'message' => 'Report submitted successfully']);
}

function getReportByWeek($studentId, $week) {
    global $conn;

    // Calculate week start and end dates based on week number
    $year = date('Y');
    $weekStart = date('Y-m-d', strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT)));
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

    // Get base URL for image paths
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/';

    $stmt = $conn->prepare("
        SELECT r.*,
               ri.image_filename, ri.day_of_week
        FROM weekly_reports r
        LEFT JOIN report_images ri ON r.report_id = ri.report_id
        WHERE r.interns_id = ? AND r.week_start = ? AND r.week_end = ?
        ORDER BY ri.day_of_week, ri.uploaded_at
    ");

    $stmt->execute([$studentId, $weekStart, $weekEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        return null;
    }

    // Extract report data from the first row (all rows should have the same report data)
    $result = $rows[0];
    // Remove image-specific fields from the main result
    unset($result['image_filename'], $result['day_of_week']);

    // Process per-day content
    if (!empty($result['report_content'])) {
        $contentData = json_decode($result['report_content'], true);
        if (is_array($contentData)) {
            $result['contentPerDay'] = $contentData;
        } else {
            // Fallback for old format
            $result['contentPerDay'] = [
                'monday' => $result['report_content'],
                'tuesday' => '',
                'wednesday' => '',
                'thursday' => '',
                'friday' => ''
            ];
        }
    } else {
        $result['contentPerDay'] = [
            'monday' => '',
            'tuesday' => '',
            'wednesday' => '',
            'thursday' => '',
            'friday' => ''
        ];
    }

    // Process images per day
    $imagesPerDay = [
        'monday' => [],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => []
    ];

    foreach ($rows as $row) {
        if (!empty($row['image_filename'])) {
            $day = $row['day_of_week'] ?: 'monday'; // Default to monday if no day specified
            $imageUrl = $baseUrl . 'uploads/reports/' . $row['image_filename'];
            $imagesPerDay[$day][] = [
                'filename' => $row['image_filename'],
                'url' => $imageUrl
            ];
        }
    }

    $result['imagesPerDay'] = $imagesPerDay;

    // Keep legacy format for backward compatibility
    $allImages = [];
    foreach ($imagesPerDay as $dayImages) {
        $allImages = array_merge($allImages, $dayImages);
    }
    $result['images'] = $allImages;

    unset($result['report_content']);

    return $result;
}

function getSubmittedReports($studentId) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT r.*,
               ri.image_filename, ri.day_of_week
        FROM weekly_reports r
        LEFT JOIN report_images ri ON r.report_id = ri.report_id
        WHERE r.interns_id = ? AND r.status = 'submitted'
        ORDER BY r.created_at DESC, ri.day_of_week, ri.uploaded_at
    ");

    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get base URL
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/';

    $reports = [];
    $currentReportId = null;
    $currentReport = null;

    foreach ($rows as $row) {
        if ($currentReportId !== $row['report_id']) {
            // Save previous report if exists
            if ($currentReport) {
                $reports[] = $currentReport;
            }

            // Start new report
            $currentReportId = $row['report_id'];
            $currentReport = $row;

            // Process per-day content
            if (!empty($row['report_content'])) {
                $contentData = json_decode($row['report_content'], true);
                if (is_array($contentData)) {
                    $currentReport['contentPerDay'] = $contentData;
                } else {
                    // Fallback for old format
                    $currentReport['contentPerDay'] = [
                        'monday' => $row['report_content'],
                        'tuesday' => '',
                        'wednesday' => '',
                        'thursday' => '',
                        'friday' => ''
                    ];
                }
            } else {
                $currentReport['contentPerDay'] = [
                    'monday' => '',
                    'tuesday' => '',
                    'wednesday' => '',
                    'thursday' => '',
                    'friday' => ''
                ];
            }

            // Initialize images per day
            $currentReport['imagesPerDay'] = [
                'monday' => [],
                'tuesday' => [],
                'wednesday' => [],
                'thursday' => [],
                'friday' => []
            ];

            $currentReport['images'] = []; // Legacy format
        }

        // Add image to current report
        if (!empty($row['image_filename'])) {
            $day = $row['day_of_week'] ?: 'monday'; // Default to monday if no day specified
            $imageData = [
                'filename' => $row['image_filename'],
                'url' => $baseUrl . 'uploads/reports/' . $row['image_filename']
            ];

            if (isset($currentReport['imagesPerDay'][$day])) {
                $currentReport['imagesPerDay'][$day][] = $imageData;
            } else {
                $currentReport['imagesPerDay']['monday'][] = $imageData; // Fallback
            }

            $currentReport['images'][] = $imageData; // Legacy format
        }
    }

    // Add the last report
    if ($currentReport) {
        $reports[] = $currentReport;
    }

    return $reports;
}

function createReport($studentId, $week, $content, $images, $status) {
    global $conn;
    
    $conn->beginTransaction();
    
    try {
        // Calculate week start and end dates
        $year = date('Y');
        $weekStart = date('Y-m-d', strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT)));
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        
        // Insert report
        $stmt = $conn->prepare("
            INSERT INTO weekly_reports (interns_id, week_start, week_end, report_content, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([$studentId, $weekStart, $weekEnd, $content, $status]);

        $reportId = $conn->lastInsertId();
        
        // Insert images
        if (!empty($images)) {
            insertReportImages($reportId, $images);
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function updateReport($reportId, $content, $images, $status) {
    global $conn;
    
    $conn->beginTransaction();
    
    try {
        // Update report
        $stmt = $conn->prepare("
            UPDATE weekly_reports
            SET report_content = ?, status = ?,
                updated_at = NOW()
            WHERE report_id = ?
        ");

        $stmt->execute([$content, $status, $reportId]);

        // Delete existing images
        $deleteStmt = $conn->prepare("DELETE FROM report_images WHERE report_id = ?");
        $deleteStmt->execute([$reportId]);
        
        // Insert new images
        if (!empty($images)) {
            insertReportImages($reportId, $images);
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function insertReportImages($reportId, $images) {
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO report_images (report_id, image_filename, uploaded_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
    ");

    foreach ($images as $image) {
        $stmt->execute([$reportId, $image['filename']]);
    }
}

function handleImageUploads($files) {
    $uploadedImages = [];
    
    // Include Cloudinary configuration
    $path = $_SERVER['DOCUMENT_ROOT'];
    $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
    require_once $basePath . '/config/cloudinary.php';
    
    $cloudinary = getCloudinaryUploader();
    
    foreach ($files['name'] as $index => $name) {
        if ($files['error'][$index] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$index];
            $fileSize = $files['size'][$index];
            $fileType = $files['type'][$index];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($fileType, $allowedTypes)) {
                continue;
            }
            
            // Validate file size (5MB max)
            if ($fileSize > 5 * 1024 * 1024) {
                continue;
            }
            
            // Generate unique public ID
            $publicId = 'report_' . uniqid() . '_' . time();
            
            // Upload to Cloudinary
            $uploadResult = $cloudinary->uploadImage($tmpName, 'internconnect/reports', $publicId);
            
            if ($uploadResult['success']) {
                $uploadedImages[] = [
                    'filename' => $uploadResult['url'], // Store Cloudinary URL
                    'original_name' => $name
                ];
                error_log("Report image uploaded to Cloudinary: " . $uploadResult['url']);
            } else {
                error_log("Cloudinary upload failed: " . $uploadResult['error']);
            }
        }
    }
    
    return $uploadedImages;
}

function getCurrentWeek() {
    return date('W'); // Returns the current week number
}

function handleImageUploadsPerDay($files) {
    $uploadedImagesPerDay = [];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    // Debug: Log the entire $_FILES structure
    error_log("handleImageUploadsPerDay called with files: " . print_r($files, true));

    $uploadDir = '../uploads/reports/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        error_log("Created upload directory: $uploadDir");
    }

    foreach ($days as $day) {
        $uploadedImagesPerDay[$day] = [];

        // FormData sends files with keys like 'images[monday][]', 'images[tuesday][]', etc.
        // This creates $_FILES structure like: $_FILES['images']['name']['monday'][0], $_FILES['images']['name']['monday'][1], etc.
        $dayKey = $day;

        // Check if images array exists and has the day key
        if (isset($files['images']['name'][$dayKey]) && is_array($files['images']['name'][$dayKey])) {
            error_log("Processing images for day: $day");

            // Handle array of files for this day
            foreach ($files['images']['name'][$dayKey] as $index => $name) {
                error_log("Processing file: $name, Error: " . $files['images']['error'][$dayKey][$index]);

                if ($files['images']['error'][$dayKey][$index] === UPLOAD_ERR_OK) {
                    $tmpName = $files['images']['tmp_name'][$dayKey][$index];
                    $fileSize = $files['images']['size'][$dayKey][$index];
                    $fileType = $files['images']['type'][$dayKey][$index];

                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($fileType, $allowedTypes)) {
                        error_log("Invalid file type: $fileType for file $name");
                        continue;
                    }

                    // Validate file size (5MB max)
                    if ($fileSize > 5 * 1024 * 1024) {
                        error_log("File size too large: $fileSize for file $name");
                        continue;
                    }

                    // Generate unique filename
                    $extension = pathinfo($name, PATHINFO_EXTENSION);
                    if (empty($extension)) {
                        $extension = 'jpg'; // Default extension
                    }
                    $uniqueName = uniqid() . '_' . time() . '_' . $day . '.' . $extension;

                    // Move uploaded file to permanent location
                    $destinationPath = $uploadDir . $uniqueName;
                    if (move_uploaded_file($tmpName, $destinationPath)) {
                        $uploadedImagesPerDay[$day][] = [
                            'filename' => $uniqueName,
                            'original_name' => $name
                        ];
                        error_log("Successfully moved uploaded file to: $destinationPath for day: $day");
                    } else {
                        error_log("Failed to move uploaded file from $tmpName to $destinationPath");
                    }
                } else {
                    error_log("File upload error code: " . $files['images']['error'][$dayKey][$index] . " for file $name");
                }
            }
        } else {
            error_log("No files found for day: $day in images array");
        }
    }

    error_log("Validated and moved images per day: " . print_r($uploadedImagesPerDay, true));
    return $uploadedImagesPerDay;
}

function createReportPerDay($studentId, $week, $contentPerDay, $imagesPerDay, $status, $mondayDescription = '', $tuesdayDescription = '', $wednesdayDescription = '', $thursdayDescription = '', $fridayDescription = '') {
    global $conn;

    $conn->beginTransaction();

    try {
        // Calculate week start and end dates
        $year = date('Y');
        $weekStart = date('Y-m-d', strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT)));
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

        // Convert per-day content to JSON for storage
        $contentJson = json_encode($contentPerDay);

        // Insert report with daily descriptions
        $stmt = $conn->prepare("
            INSERT INTO weekly_reports (interns_id, week_start, week_end, report_content, monday_description, tuesday_description, wednesday_description, thursday_description, friday_description, status, approval_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // For new reports and resubmissions, set approval_status to 'pending'
        $stmt->execute([$studentId, $weekStart, $weekEnd, $contentJson, $mondayDescription, $tuesdayDescription, $wednesdayDescription, $thursdayDescription, $fridayDescription, $status, $status === 'submitted' ? 'pending' : NULL]);

        $reportId = $conn->lastInsertId();

        // Insert images per day
        insertReportImagesPerDay($reportId, $imagesPerDay);

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function updateReportPerDay($reportId, $contentPerDay, $imagesPerDay, $status, $mondayDescription = '', $tuesdayDescription = '', $wednesdayDescription = '', $thursdayDescription = '', $fridayDescription = '') {
    global $conn;

    $conn->beginTransaction();

    try {
        // Convert per-day content to JSON for storage
        $contentJson = json_encode($contentPerDay);

        // Update report with daily descriptions
        $stmt = $conn->prepare("
            UPDATE weekly_reports
            SET report_content = ?, monday_description = ?, tuesday_description = ?, wednesday_description = ?, 
                thursday_description = ?, friday_description = ?, status = ?, approval_status = 'pending',
                updated_at = NOW()
            WHERE report_id = ?
        ");

        $stmt->execute([$contentJson, $mondayDescription, $tuesdayDescription, $wednesdayDescription, $thursdayDescription, $fridayDescription, $status, $reportId]);

        // Delete existing images
        $deleteStmt = $conn->prepare("DELETE FROM report_images WHERE report_id = ?");
        $deleteStmt->execute([$reportId]);

        // Insert new images per day
        insertReportImagesPerDay($reportId, $imagesPerDay);

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function insertReportImagesPerDay($reportId, $imagesPerDay) {
    global $conn;

    // Prepare statement for filename-only storage
    $stmt = $conn->prepare("
        INSERT INTO report_images (report_id, image_filename, day_of_week, uploaded_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");

    // Create upload directory if it doesn't exist
    $uploadDir = '../uploads/reports/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        error_log("Created upload directory: $uploadDir");
    }

    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    foreach ($days as $day) {
        if (!empty($imagesPerDay[$day])) {
            foreach ($imagesPerDay[$day] as $image) {
                // Normalize image to array if object
                if (is_object($image)) {
                    $image = (array)$image;
                }

                $filename = null;

                // Handle new uploads (with tmp_name)
                if (isset($image['tmp_name']) && !empty($image['tmp_name'])) {
                    try {
                        // Generate unique filename
                        $originalName = $image['original_name'] ?? 'image.jpg';
                        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                        if (empty($extension)) {
                            $extension = 'jpg'; // Default extension
                        }
                        $uniqueName = uniqid() . '_' . $reportId . '_' . $day . '.' . $extension;

                        // Move uploaded file to permanent location
                        $destinationPath = $uploadDir . $uniqueName;
                        if (move_uploaded_file($image['tmp_name'], $destinationPath)) {
                            $filename = $uniqueName;
                            error_log("Successfully moved uploaded file to: $destinationPath for day: $day");
                        } else {
                            error_log("Failed to move uploaded file from {$image['tmp_name']} to $destinationPath");
                            continue;
                        }
                    } catch (Exception $e) {
                        error_log("Failed to process uploaded file: " . $e->getMessage());
                        continue;
                    }
                }
                // Handle existing images (with filename)
                elseif (isset($image['filename']) && !empty($image['filename'])) {
                    try {
                        $filename = $image['filename'];
                        error_log("Using existing filename for day: $day - $filename");
                    } catch (Exception $e) {
                        error_log("Failed to process existing filename: " . $e->getMessage());
                        continue;
                    }
                }
                else {
                    error_log("Skipping image without tmp_name or filename for day: $day");
                    continue;
                }

                if ($filename !== null) {
                    try {
                        $stmt->bindParam(1, $reportId, PDO::PARAM_INT);
                        $stmt->bindParam(2, $filename, PDO::PARAM_STR);
                        $stmt->bindParam(3, $day, PDO::PARAM_STR);
                        $stmt->execute();
                        error_log("Inserted image filename record for day: $day - $filename");
                    } catch (Exception $e) {
                        error_log("Failed to insert image filename: " . $e->getMessage());
                        throw new Exception("Failed to insert image filename: " . $e->getMessage());
                    }
                }
            }
        } else {
            error_log("No images to insert for day: $day");
        }
    }
}
require_once '../fpdf/fpdf.php';

function generateWeeklyReportPDF($studentId) {
    global $conn;

    // Get week from POST or current week
    $week = $_POST['week'] ?? date('W');
    $managerName = $_POST['managerName'] ?? 'Manager';

    // Calculate week start and end dates
    $year = date('Y');
    $weekStart = date('Y-m-d', strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT)));
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

    // Fetch student name
    $stmt = $conn->prepare("SELECT SURNAME, FIRSTNAME FROM interns_details WHERE INTERNS_ID = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    $studentName = $student ? $student['FIRSTNAME'] . ' ' . $student['SURNAME'] : 'Unknown Student';

    // Fetch report data for the week
    $stmt = $conn->prepare("
        SELECT report_content, challenges_faced, lessons_learned, goals_next_week
        FROM weekly_reports
        WHERE interns_id = ? AND week_start = ? AND week_end = ? AND status = 'submitted'
        LIMIT 1
    ");
    $stmt->execute([$studentId, $weekStart, $weekEnd]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        echo json_encode(['status' => 'error', 'message' => 'No submitted report found for the specified week']);
        exit;
    }

    // Decode report content JSON
    $contentPerDay = json_decode($report['report_content'], true);
    if (!is_array($contentPerDay)) {
        $contentPerDay = [];
    }

    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'WEEKLY REPORT', 0, 1, 'C');

    // Student name and week info
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "Student: $studentName", 0, 1);
    $pdf->Cell(0, 10, "Week: $weekStart to $weekEnd", 0, 1);

    $pdf->Ln(5);

    // Report content per day
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $pdf->SetFont('Arial', 'B', 12);
    foreach ($days as $day) {
        $pdf->Cell(0, 10, $day, 0, 1);
        $pdf->SetFont('Arial', '', 12);
        $dayKey = strtolower($day);
        $content = $contentPerDay[$dayKey] ?? 'No content';
        $pdf->MultiCell(0, 8, $content);
        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 12);
    }

    // Challenges faced, lessons learned, goals for next week
    $pdf->Cell(0, 10, 'Challenges Faced:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, $report['challenges_faced'] ?? 'None');
    $pdf->Ln(3);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Lessons Learned:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, $report['lessons_learned'] ?? 'None');
    $pdf->Ln(3);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Goals for Next Week:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, $report['goals_next_week'] ?? 'None');
    $pdf->Ln(10);

    // Manager name at bottom
    $pdf->SetY(-30);
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->Cell(0, 10, "Manager: $managerName", 0, 1, 'C');

    // Output PDF to browser
    $pdf->Output('I', "Weekly_Report_{$studentName}_Week_{$week}.pdf");
    exit;
}

