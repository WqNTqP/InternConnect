<?php
/**
 * Enhanced email sending with better HTML formatting
 * Uses basic PHP mail() function with improved templates
 */

function sendResetEmailWithPHPMailer($email, $userName, $resetLink, $userType, $userRole = '') {
    // Use Gmail SMTP only - no fallback for now to see Gmail errors
    require_once __DIR__ . '/gmail_smtp.php';
    
    return sendResetEmailViaGmail($email, $userName, $resetLink, $userType, $userRole);
}

function sendEnhancedResetEmail($email, $userName, $resetLink, $userType, $userRole = '') {
    $subject = 'InternConnect - Password Reset Request';
    $roleDisplay = $userRole ?: ucfirst($userType);
    
    // Create HTML email content
    $htmlMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .footer { margin-top: 20px; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔒 Password Reset Request</h1>
            </div>
            <div class='content'>
                <h2>Hello, {$userName}!</h2>
                <p>We received a request to reset the password for your {$roleDisplay} account in the InternConnect system.</p>
                
                <p>Click the button below to reset your password:</p>
                
                <a href='{$resetLink}' class='button'>🔑 Reset Password</a>
                
                <p><small>Or copy and paste this link in your browser:<br>
                <a href='{$resetLink}'>{$resetLink}</a></small></p>
                
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This link will expire in 1 hour for security reasons</li>
                    <li>If you didn't request this reset, please ignore this email</li>
                    <li>Never share this link with anyone</li>
                </ul>
                
                <div class='footer'>
                    <p>This email was sent from the InternConnect System.<br>
                    If you have questions, contact your system administrator.</p>
                    <p><em>Sent on " . date('F j, Y \a\t g:i A') . "</em></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email headers for HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: InternConnect System <kimcharles.emping@hcdc.edu.ph>" . "\r\n";
    $headers .= "Reply-To: kimcharles.emping@hcdc.edu.ph" . "\r\n";
    
    // Send the email
    $success = mail($email, $subject, $htmlMessage, $headers);
    
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
?>