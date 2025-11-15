<?php
/**
 * Test script to verify student profile upload paths are working
 */

echo "<h2>Student Profile Upload Path Test</h2>\n";
echo "<pre>\n";

try {
    // Test the path resolution for studentDashboardAjax.php
    $ajaxPath = dirname(__FILE__) . '/ajaxhandler/../';
    echo "Ajax relative path: $ajaxPath\n";
    echo "Resolved to: " . realpath($ajaxPath) . "\n\n";
    
    // Test config file inclusion
    $configPath = $ajaxPath . 'config/safe_upload.php';
    echo "Config path: $configPath\n";
    echo "File exists: " . (file_exists($configPath) ? 'Yes' : 'No') . "\n\n";
    
    if (file_exists($configPath)) {
        echo "--- Testing safe_upload.php inclusion ---\n";
        require_once $configPath;
        
        echo "safe_upload.php loaded successfully\n";
        
        if (function_exists('safeUploadImage')) {
            echo "safeUploadImage function available\n";
        } else {
            echo "safeUploadImage function NOT available\n";
        }
        
        // Test Cloudinary configuration check
        if (function_exists('isCloudinaryConfigured')) {
            $isConfigured = isCloudinaryConfigured();
            echo "Cloudinary configured: " . ($isConfigured ? 'Yes' : 'No') . "\n";
        } else {
            echo "isCloudinaryConfigured function not available\n";
        }
        
    } else {
        echo "ERROR: safe_upload.php not found\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>