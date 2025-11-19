<?php
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$path = dirname(__FILE__) . '/../';
require_once $path . "database/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$user_type = $_POST['user_type'] ?? '';

if (empty($current_password) || empty($new_password) || empty($user_type)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if ($new_password === $current_password) {
    echo json_encode(['success' => false, 'message' => 'New password must be different from current password']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
    exit;
}

try {
    session_start();
    $dbo = new Database();
    
    if ($user_type === 'coordinator') {
        if (!isset($_SESSION['current_user'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            exit;
        }
        
        $coordinator_id = $_SESSION['current_user'];
        
        // Check current password
        $stmt = $dbo->conn->prepare("SELECT PASSWORD FROM coordinator WHERE COORDINATOR_ID = ?");
        $stmt->execute([$coordinator_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Verify current password - handle both hashed and plain text
        $storedPassword = $user['PASSWORD'];
        $passwordMatch = false;
        
        if (strpos($storedPassword, '$2y$') === 0) {
            // Password is hashed, use password_verify
            $passwordMatch = password_verify($current_password, $storedPassword);
        } else {
            // Password is plain text (legacy), do direct comparison
            $passwordMatch = ($storedPassword === $current_password);
        }
        
        if (!$passwordMatch) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        
        // Hash new password for security
        $hashedNewPassword = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $dbo->conn->prepare("UPDATE coordinator SET PASSWORD = ? WHERE COORDINATOR_ID = ?");
        $result = $stmt->execute([$hashedNewPassword, $coordinator_id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update password']);
        }
    } elseif ($user_type === 'admin') {
        if (!isset($_SESSION['admin_user'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            exit;
        }
        
        $admin_id = $_SESSION['admin_user'];
        
        // Check current password
        $stmt = $dbo->conn->prepare("SELECT PASSWORD FROM coordinator WHERE COORDINATOR_ID = ?");
        $stmt->execute([$admin_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Verify current password - handle both hashed and plain text
        $storedPassword = $user['PASSWORD'];
        $passwordMatch = false;
        
        if (strpos($storedPassword, '$2y$') === 0) {
            // Password is hashed, use password_verify
            $passwordMatch = password_verify($current_password, $storedPassword);
        } else {
            // Password is plain text (legacy), do direct comparison
            $passwordMatch = ($storedPassword === $current_password);
        }
        
        if (!$passwordMatch) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        
        // Hash new password for security
        $hashedNewPassword = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $dbo->conn->prepare("UPDATE coordinator SET PASSWORD = ? WHERE COORDINATOR_ID = ?");
        $result = $stmt->execute([$hashedNewPassword, $admin_id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid user type']);
    }
    
} catch (Exception $e) {
    error_log("Change password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while changing password']);
}
?>