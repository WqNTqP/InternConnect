<?php
/**
 * Simple SMTP Email Sender for Gmail
 * This uses socket connections to send emails via Gmail SMTP
 */

function sendGmailEmail($to, $subject, $message, $fromEmail, $fromName, $gmailPassword) {
    $smtp_server = "smtp.gmail.com";
    $smtp_port = 587;
    
    // Create socket connection
    $sock = fsockopen($smtp_server, $smtp_port, $errno, $errstr, 30);
    
    if (!$sock) {
        error_log("SMTP Error: Cannot connect to $smtp_server:$smtp_port - $errstr ($errno)");
        return false;
    }
    
    // SMTP conversation
    $response = fgets($sock, 512);
    if (substr($response, 0, 3) != '220') {
        error_log("SMTP Error: $response");
        fclose($sock);
        return false;
    }
    
    // Send EHLO
    fputs($sock, "EHLO localhost\r\n");
    
    // Read all EHLO responses (Gmail sends multiple lines)
    do {
        $response = fgets($sock, 512);
    } while (substr($response, 3, 1) == '-'); // Continue while response has continuation marker
    
    // Start TLS
    fputs($sock, "STARTTLS\r\n");
    $response = fgets($sock, 512);
    error_log("STARTTLS Response: $response");
    
    if (substr($response, 0, 3) != '220') {
        error_log("SMTP Error: STARTTLS failed - $response");
        fclose($sock);
        return false;
    }
    
    // Enable crypto
    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log("SMTP Error: Cannot enable TLS");
        fclose($sock);
        return false;
    }
    
    // Send EHLO again after TLS
    fputs($sock, "EHLO localhost\r\n");
    
    // Read all EHLO responses after TLS
    do {
        $response = fgets($sock, 512);
    } while (substr($response, 3, 1) == '-');
    
    // Authentication
    fputs($sock, "AUTH LOGIN\r\n");
    $response = fgets($sock, 512);
    error_log("AUTH LOGIN Response: $response");
    
    if (substr($response, 0, 3) != '334') {
        error_log("Gmail SMTP: AUTH LOGIN failed - $response");
        fclose($sock);
        return false;
    }
    
    // Send username
    fputs($sock, base64_encode($fromEmail) . "\r\n");
    $response = fgets($sock, 512);
    error_log("Username Response: $response");
    
    if (substr($response, 0, 3) != '334') {
        error_log("Gmail SMTP: Username rejected - $response");
        fclose($sock);
        return false;
    }
    
    // Send password
    fputs($sock, base64_encode($gmailPassword) . "\r\n");
    $response = fgets($sock, 512);
    
    if (substr($response, 0, 3) != '235') {
        error_log("SMTP Error: Authentication failed - $response");
        fclose($sock);
        return false;
    }
    
    // Send email
    fputs($sock, "MAIL FROM: <$fromEmail>\r\n");
    $response = fgets($sock, 512);
    
    fputs($sock, "RCPT TO: <$to>\r\n");
    $response = fgets($sock, 512);
    
    fputs($sock, "DATA\r\n");
    $response = fgets($sock, 512);
    
    // Email headers and body
    $email_data = "From: $fromName <$fromEmail>\r\n";
    $email_data .= "To: $to\r\n";
    $email_data .= "Subject: $subject\r\n";
    $email_data .= "MIME-Version: 1.0\r\n";
    $email_data .= "Content-Type: text/html; charset=UTF-8\r\n";
    $email_data .= "\r\n";
    $email_data .= $message;
    $email_data .= "\r\n.\r\n";
    
    fputs($sock, $email_data);
    $response = fgets($sock, 512);
    
    // Quit
    fputs($sock, "QUIT\r\n");
    fclose($sock);
    
    return substr($response, 0, 3) == '250';
}

/**
 * Enhanced reset email function using Gmail SMTP
 */
function sendResetEmailViaGmail($email, $userName, $resetLink, $userType, $userRole = '') {
    $subject = 'InternConnect - Password Reset Request';
    $roleDisplay = $userRole ?: ucfirst($userType);
    
    // Get Gmail credentials from environment variables
    $gmailEmail = $_ENV['GMAIL_USERNAME'] ?? getenv('GMAIL_USERNAME') ?: 'shadowd6163@gmail.com';
    $gmailPassword = $_ENV['GMAIL_PASSWORD'] ?? getenv('GMAIL_PASSWORD') ?: 'qwjbrxhizcqlgfsz';
    
    // HTML message
    $htmlMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .footer { margin-top: 20px; font-size: 12px; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 15px; }
            .alert { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>🔒 Password Reset Request</h1>
        </div>
        <div class='content'>
            <h2>Hello, {$userName}!</h2>
            <p>We received a request to reset the password for your <strong>{$roleDisplay}</strong> account in the InternConnect system.</p>
            
            <p>Click the button below to reset your password:</p>
            
            <div style='text-align: center;'>
                <a href='{$resetLink}' class='button'>🔑 Reset Password</a>
            </div>
            
            <p><small>Or copy and paste this link in your browser:<br>
            <code style='background: #f8f9fa; padding: 5px; border-radius: 3px; display: inline-block; margin: 5px 0; word-break: break-all;'>{$resetLink}</code></small></p>
            
            <div class='alert'>
                <strong>⚠️ Important Security Information:</strong>
                <ul style='margin: 10px 0; padding-left: 20px;'>
                    <li>This link will expire in <strong>1 hour</strong> for security reasons</li>
                    <li>If you didn't request this reset, please ignore this email</li>
                    <li>Never share this link with anyone</li>
                    <li>InternConnect staff will never ask for your password via email</li>
                </ul>
            </div>
            
            <div class='footer'>
                <p>📧 This email was sent from the InternConnect System<br>
                🏫 Holy Child Colleges of Davao<br>
                📅 Sent on " . date('F j, Y \a\t g:i A') . "</p>
                <p><em>If you have questions, contact your system administrator.</em></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Check if Gmail password is configured
    if ($gmailPassword === 'YOUR_GMAIL_APP_PASSWORD_HERE' || empty($gmailPassword)) {
        // Log that Gmail is not configured
        error_log("Gmail SMTP not configured - App password needed");
        
        // Fallback to basic email logging
        $logMessage = "=== EMAIL NOT SENT (Gmail Not Configured) ===\n";
        $logMessage .= "To: $email\n";
        $logMessage .= "Subject: $subject\n";
        $logMessage .= "Reset Link: $resetLink\n";
        $logMessage .= "Note: Configure Gmail App Password in gmail_smtp.php\n";
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "============================================\n\n";
        
        file_put_contents(__DIR__ . '/../email_config_needed.log', $logMessage, FILE_APPEND | LOCK_EX);
        
        return false;
    }
    
    // Debug: Log the Gmail credentials being used

    
    // Try to send via Gmail SMTP
    $success = sendGmailEmail($email, $subject, $htmlMessage, $gmailEmail, 'InternConnect System', $gmailPassword);
    
    if ($success) {
        // Log successful email
        $logMessage = "=== EMAIL SENT SUCCESSFULLY ===\n";
        $logMessage .= "To: $email\n";
        $logMessage .= "Subject: $subject\n";
        $logMessage .= "Method: Gmail SMTP\n";
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "================================\n\n";
        
        file_put_contents(__DIR__ . '/../sent_emails.log', $logMessage, FILE_APPEND | LOCK_EX);

    } else {
        // Log failed attempt with more details
        $logMessage = "=== EMAIL FAILED ===\n";
        $logMessage .= "To: $email\n";
        $logMessage .= "Subject: $subject\n";
        $logMessage .= "Reset Link: $resetLink\n";
        $logMessage .= "Gmail Email: $gmailEmail\n";
        $logMessage .= "Password Set: " . (empty($gmailPassword) ? 'NO' : 'YES') . "\n";
        $logMessage .= "Method: Gmail SMTP\n";
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "=====================\n\n";
        
        file_put_contents(__DIR__ . '/../failed_emails.log', $logMessage, FILE_APPEND | LOCK_EX);
        error_log("Gmail SMTP: Email failed to send to $email");
    }
    
    return $success;
}
?>