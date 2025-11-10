<?php
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$path = dirname(__FILE__) . '/../';
require_once $path . "database/database.php";

function sendResponse($status, $data, $message = '') {
    echo json_encode(array("status" => $status, "data" => $data, "message" => $message));
    exit;
}

function logError($message) {
    error_log(date('[Y-m-d H:i] ') . "ERROR: " . $message . "\n", 3, 'error.log');
}

try {
    $dbo = new Database();
} catch (Exception $e) {
    logError("Database connection failed: " . $e->getMessage());
    sendResponse('error', null, 'Database connection failed');
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if (empty($action)) {
    sendResponse('error', null, 'No action specified');
}

switch ($action) {
    case "getAllStudents":
        $coordinatorId = $_POST['coordinatorId'] ?? null;
        try {
            if ($coordinatorId) {
                $stmt = $dbo->conn->prepare("
                    SELECT
                        id.STUDENT_ID,
                        id.INTERNS_ID,
                        id.NAME,
                        id.SURNAME
                    FROM interns_details id
                    JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
                    JOIN internship_needs ins ON itd.HTE_ID = ins.HTE_ID AND ins.COORDINATOR_ID = ?
                    ORDER BY id.SURNAME ASC, id.NAME ASC
                ");
                $stmt->execute([$coordinatorId]);
            } else {
                $stmt = $dbo->conn->prepare("SELECT STUDENT_ID, INTERNS_ID, NAME, SURNAME FROM interns_details ORDER BY SURNAME, NAME");
                $stmt->execute();
            }
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse('success', $students, 'Student list retrieved successfully');
        } catch (Exception $e) {
            logError("Error retrieving student list: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving student list');
        }
        break;
    case "getAdminDetails":
        $adminId = $_POST['adminId'] ?? null;
        if (!$adminId) {
            sendResponse('error', null, 'Admin ID is required');
        }
        try {
            $stmt = $dbo->conn->prepare("
                SELECT c.*, h.NAME as HTE_NAME 
                FROM coordinator c 
                LEFT JOIN host_training_establishment h ON c.HTE_ID = h.HTE_ID 
                WHERE c.COORDINATOR_ID = ?
            ");
            $stmt->execute([$adminId]);
            $adminDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($adminDetails) {
                sendResponse('success', $adminDetails, 'Admin details retrieved successfully');
            } else {
                sendResponse('error', null, 'Admin not found');
            }
        } catch (Exception $e) {
            logError("Error retrieving admin details: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving admin details');
        }
        break;

    case "updateAdminProfileDetails":
        $adminId = $_POST['adminId'] ?? null;
        $name = $_POST['name'] ?? null;
        $email = $_POST['email'] ?? null;
        $contactNumber = $_POST['contactNumber'] ?? null;
        $department = $_POST['department'] ?? null;
        
        if (!$adminId || !$name || !$email || !$contactNumber || !$department) {
            sendResponse('error', null, 'All fields are required');
        }
        
        $profilePicturePath = null;
        if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] == UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profilePicture']['tmp_name'];
            $fileName = $_FILES['profilePicture']['name'];
            $fileSize = $_FILES['profilePicture']['size'];
            $fileType = $_FILES['profilePicture']['type'];
            $allowedFileTypes = ['image/jpeg', 'image/png', 'image/gif'];

            if (in_array($fileType, $allowedFileTypes)) {
                $uploadFileDir = '../uploads/';
                if (!file_exists($uploadFileDir)) {
                    mkdir($uploadFileDir, 0777, true);
                }
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueFileName = uniqid() . '_' . $adminId . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $uniqueFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $profilePicturePath = $uniqueFileName;
                } else {
                    sendResponse('error', null, 'Error moving the uploaded file');
                }
            } else {
                sendResponse('error', null, 'Invalid file type. Only JPEG, PNG, and GIF files are allowed.');
            }
        }

        try {
            if ($profilePicturePath) {
                // Update with profile picture
                $stmt = $dbo->conn->prepare("UPDATE coordinator SET NAME = ?, EMAIL = ?, CONTACT_NUMBER = ?, DEPARTMENT = ?, PROFILE = ? WHERE COORDINATOR_ID = ?");
                $stmt->execute([$name, $email, $contactNumber, $department, $profilePicturePath, $adminId]);
            } else {
                // Update without profile picture
                $stmt = $dbo->conn->prepare("UPDATE coordinator SET NAME = ?, EMAIL = ?, CONTACT_NUMBER = ?, DEPARTMENT = ? WHERE COORDINATOR_ID = ?");
                $stmt->execute([$name, $email, $contactNumber, $department, $adminId]);
            }
            sendResponse('success', null, 'Profile updated successfully');
        } catch (Exception $e) {
            logError("Error updating admin profile: " . $e->getMessage());
            sendResponse('error', null, 'Error updating profile');
        }
        break;

    case "updateAdminProfilePicture":
        $adminId = $_POST['adminId'] ?? null;
        
        if (!$adminId) {
            sendResponse('error', null, 'Admin ID is required');
        }
        
        if (!isset($_FILES['profilePicture']) || $_FILES['profilePicture']['error'] != UPLOAD_ERR_OK) {
            sendResponse('error', null, 'Please select a valid profile picture to upload');
        }

        // Validate file type
        $allowedFileTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['profilePicture']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedFileTypes)) {
            sendResponse('error', null, 'Invalid file type. Only JPEG, PNG, and GIF files are allowed.');
        }

        // Use safe upload with Cloudinary support - require cloud storage
        $path = $_SERVER['DOCUMENT_ROOT'];
        $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
        require_once $basePath . '/config/safe_upload.php';
        
        $uploadResult = safeUploadImage(
            $_FILES['profilePicture']['tmp_name'],
            $_FILES['profilePicture']['name'],
            'uploads',
            'admin_profiles',
            true // Require Cloudinary - fail if not available to prevent data loss
        );

        if ($uploadResult['success']) {
            try {
                // Store the full Cloudinary URL
                $stmt = $dbo->conn->prepare("UPDATE coordinator SET PROFILE = ? WHERE COORDINATOR_ID = ?");
                $stmt->execute([$uploadResult['url'], $adminId]);
                sendResponse('success', null, 'Profile picture updated successfully');
                error_log("Admin profile picture uploaded to Cloudinary: " . $uploadResult['url']);
            } catch (Exception $e) {
                logError("Error updating profile picture in database: " . $e->getMessage());
                sendResponse('error', null, 'Error updating profile picture');
            }
        } else {
            logError("Admin profile picture upload failed (Cloudinary required): " . ($uploadResult['error'] ?? 'Unknown error'));
            sendResponse('error', null, 'Profile picture upload failed: ' . ($uploadResult['error'] ?? 'Cloud storage unavailable'));
        }
        break;

    case "updatePassword":
        $adminId = $_POST['adminId'] ?? null;
        $currentPassword = $_POST['currentPassword'] ?? null;
        $newPassword = $_POST['newPassword'] ?? null;
        $confirmPassword = $_POST['confirmPassword'] ?? null;

        if (!$adminId || !$currentPassword || !$newPassword || !$confirmPassword) {
            sendResponse('error', null, 'All password fields are required');
        }

        if ($newPassword !== $confirmPassword) {
            sendResponse('error', null, 'New password and confirmation do not match');
        }

        try {
            // Fetch current password
            $stmt = $dbo->conn->prepare("SELECT PASSWORD FROM coordinator WHERE COORDINATOR_ID = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                sendResponse('error', null, 'Admin not found');
            }

            if ($admin['PASSWORD'] !== $currentPassword) {
                sendResponse('error', null, 'Current password is incorrect');
            }

            // Update password
            $stmt = $dbo->conn->prepare("UPDATE coordinator SET PASSWORD = ? WHERE COORDINATOR_ID = ?");
            $stmt->execute([$newPassword, $adminId]);

            sendResponse('success', null, 'Password updated successfully');
        } catch (Exception $e) {
            logError("Error updating password: " . $e->getMessage());
            sendResponse('error', null, 'Error updating password');
        }
        break;

    default:
        sendResponse('error', null, 'Invalid action');
}
?>

