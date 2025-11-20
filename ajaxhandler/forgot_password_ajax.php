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

$action = $_POST['action'] ?? '';

// Support both action names for compatibility
if ($action === 'send_reset_link' || $action === 'forgot_password') {
    $email = trim($_POST['email'] ?? '');
    $userType = $_POST['userType'] ?? $_POST['account_type'] ?? '';
    
    if (empty($email) || empty($userType)) {
        echo json_encode(['success' => false, 'message' => 'Email and user type are required']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }
    
    try {
        $dbo = new Database();
        
        // Check if email exists in the appropriate table
        $user = null;
        $userId = null;
        $userName = null;
        
        if ($userType === 'student') {
            // For students, get from interns_details table
            $stmt = $dbo->conn->prepare("SELECT INTERNS_ID, NAME, SURNAME, EMAIL FROM interns_details WHERE EMAIL = ? AND EMAIL IS NOT NULL AND EMAIL != ''");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $userId = $user['INTERNS_ID'];
                // Combine name and surname for full name
                $userName = trim($user['NAME'] . ' ' . ($user['SURNAME'] ?? ''));
            }
        } elseif ($userType === 'admin' || $userType === 'staff') {
            // For admin/coordinator/superadmin, get from coordinator table
            $stmt = $dbo->conn->prepare("SELECT COORDINATOR_ID, NAME, EMAIL, ROLE FROM coordinator WHERE EMAIL = ? AND EMAIL IS NOT NULL AND EMAIL != ''");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $userId = $user['COORDINATOR_ID'];
                $userName = $user['NAME'];
                // You can also use the ROLE field to customize the email template
                $userRole = $user['ROLE'] ?? 'Admin';
            }
        }
        
        if (!$user) {
            // For security, we don't reveal whether the email exists or not
            echo json_encode(['success' => true, 'message' => 'If the email exists in our system, you will receive a reset link.']);
            exit;
        }
        
        // Generate a secure reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
        
        // Store the reset token in database
        // First, check if password_reset_tokens table exists, if not create it
        createPasswordResetTable($dbo);
        
        // Standardize user type for database storage
        $dbUserType = ($userType === 'student') ? 'student' : 'admin';
        
        // Delete any existing tokens for this user
        $stmt = $dbo->conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND user_type = ?");
        $stmt->execute([$userId, $dbUserType]);
        
        // Insert new token
        $stmt = $dbo->conn->prepare("INSERT INTO password_reset_tokens (user_id, user_type, email, token, expires_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $dbUserType, $email, $token, $expiresAt]);
        
        // Send email with reset link
        $resetLink = getBaseUrl() . "/reset_password.php?token=" . $token;
        $userRole = isset($userRole) ? $userRole : ucfirst($userType);
        
        // Try enhanced email first, fallback to basic email
        require_once __DIR__ . '/../includes/phpmailer_email.php';
        $emailSent = sendResetEmailWithPHPMailer($email, $userName, $resetLink, $dbUserType, $userRole);
        
        if ($emailSent) {
            echo json_encode(['success' => true, 'message' => 'Password reset link has been sent to your email.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again later.']);
        }
        
    } catch (Exception $e) {
        error_log("Forgot Password Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
    }
    
} elseif ($action === 'verify_token') {
    $token = $_POST['token'] ?? '';
    
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }
    
    try {
        $dbo = new Database();
        
        // Check if token exists and is not expired
        $stmt = $dbo->conn->prepare("SELECT * FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenData) {
            echo json_encode(['success' => true, 'data' => $tokenData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        }
        
    } catch (Exception $e) {
        error_log("Token Verification Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while verifying token']);
    }
    
} elseif ($action === 'reset_password') {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        exit;
    }
    
    try {
        $dbo = new Database();
        
        // Verify token
        $stmt = $dbo->conn->prepare("SELECT * FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenData) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
            exit;
        }
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password in the appropriate table
        if ($tokenData['user_type'] === 'student') {
            // Update student password in interns_details table
            $stmt = $dbo->conn->prepare("UPDATE interns_details SET PASSWORD = ? WHERE INTERNS_ID = ?");
            $stmt->execute([$hashedPassword, $tokenData['user_id']]);
        } elseif ($tokenData['user_type'] === 'admin') {
            // Update admin/coordinator/superadmin password in coordinator table
            $stmt = $dbo->conn->prepare("UPDATE coordinator SET PASSWORD = ? WHERE COORDINATOR_ID = ?");
            $stmt->execute([$hashedPassword, $tokenData['user_id']]);
        }
        
        // Delete the used token
        $stmt = $dbo->conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        
        echo json_encode(['success' => true, 'message' => 'Password has been reset successfully']);
        
    } catch (Exception $e) {
        error_log("Password Reset Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while resetting password']);
    }
} else {
    // Invalid action
    echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}

function createPasswordResetTable($dbo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            user_type ENUM('student', 'admin') NOT NULL,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user (user_id, user_type),
            INDEX idx_expires (expires_at)
        )";
        
        $dbo->conn->exec($sql);
    } catch (Exception $e) {
        error_log("Error creating password_reset_tokens table: " . $e->getMessage());
        throw $e;
    }
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    
    // Remove 'ajaxhandler' from path if present
    $path = str_replace('/ajaxhandler', '', $path);
    
    return $protocol . '://' . $host . $path;
}

function sendResetEmail($email, $userName, $resetLink, $userType, $userRole = null) {
    $subject = "InternConnect - Password Reset Request";
    
    // Use specific role if provided, otherwise use user type
    $userTypeDisplay = $userRole ? $userRole : ucfirst($userType);
    
    // Customize display based on user type
    if ($userType === 'student') {
        $userTypeDisplay = 'Student';
    } elseif ($userType === 'admin') {
        $userTypeDisplay = $userRole ?: 'Admin';
    }
    
    $message = "
    <html>
    <head>
        <title>Password Reset - InternConnect</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; background: #1e40af; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            .footer { text-align: center; color: #6b7280; font-size: 14px; margin-top: 20px; }
            .warning { background: #fef3c7; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #f59e0b; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 InternConnect</h1>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <h2>Hello, " . htmlspecialchars($userName) . "!</h2>
                <p>We received a request to reset the password for your " . $userTypeDisplay . " account associated with this email address.</p>
                
                <p>If you made this request, click the button below to reset your password:</p>
                
                <div style='text-align: center;'>
                    <a href='" . $resetLink . "' class='button'>Reset My Password</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #e5e7eb; padding: 10px; border-radius: 4px;'>" . $resetLink . "</p>
                
                <div class='warning'>
                    <strong>⚠️ Important Security Information:</strong>
                    <ul>
                        <li>This link will expire in 1 hour for security reasons</li>
                        <li>If you didn't request this reset, please ignore this email</li>
                        <li>Never share this link with anyone else</li>
                    </ul>
                </div>
                
                <p>If you have any questions or concerns, please contact your system administrator.</p>
                
                <p>Best regards,<br>
                The InternConnect Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from InternConnect System.<br>
                Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: InternConnect System <noreply@internconnect.com>" . "\r\n";
    $headers .= "Reply-To: noreply@internconnect.com" . "\r\n";
    
    // PRODUCTION MODE: Send actual emails
    // Set to true for development/testing with log files
    $developmentMode = false;
    
    if ($developmentMode) {
        // Log the email to a file for testing
        $logMessage = "=== PASSWORD RESET EMAIL ===\n";
        $logMessage .= "To: $email\n";
        $logMessage .= "Subject: $subject\n";
        $logMessage .= "Reset Link: $resetLink\n";
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "========================\n\n";
        
        file_put_contents(__DIR__ . '/../reset_emails.log', $logMessage, FILE_APPEND | LOCK_EX);
        
        // Return true to simulate successful email sending
        return true;
    } else {
        // Try to send email using PHP's mail() function
        // Configure your SMTP settings in php.ini for Gmail:
        // SMTP = smtp.gmail.com
        // smtp_port = 587
        // auth_username = your-email@gmail.com
        // auth_password = your-app-password
        
        $success = mail($email, $subject, $message, $headers);
        
        if (!$success) {
            // Log failed email attempts
            error_log("Failed to send password reset email to: $email");
            
            // Also log to file for debugging
            $logMessage = "=== FAILED EMAIL ATTEMPT ===\n";
            $logMessage .= "To: $email\n";
            $logMessage .= "Subject: $subject\n";
            $logMessage .= "Reset Link: $resetLink\n";
            $logMessage .= "Error: Email sending failed\n";
            $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
            $logMessage .= "============================\n\n";
            
            file_put_contents(__DIR__ . '/../failed_emails.log', $logMessage, FILE_APPEND | LOCK_EX);
        }
        
        return $success;
    }
}

?>