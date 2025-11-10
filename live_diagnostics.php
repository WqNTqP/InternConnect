<?php
/**
 * Live Server Diagnostic Tool
 * This will help us understand what's different between localhost and Render
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set content type
header('Content-Type: text/html; charset=UTF-8');

echo "<html><head><title>Live Server Diagnostics</title></head><body>";
echo "<h1>🔍 Live Server Diagnostics</h1>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Test 1: Basic PHP Information
echo "<h2>📋 PHP Environment</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Path:</strong> " . __FILE__ . "</p>";

// Test 2: Required Extensions
echo "<h2>🔧 Required Extensions</h2>";
$requiredExtensions = ['curl', 'json', 'fileinfo', 'gd'];
foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅' : '❌';
    echo "<p>{$status} <strong>{$ext}:</strong> " . ($loaded ? 'Loaded' : 'NOT LOADED') . "</p>";
}

// Test 3: File System Permissions
echo "<h2>📁 File System</h2>";
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";

echo "<p><strong>Base Path:</strong> " . $basePath . "</p>";
echo "<p><strong>Base Path Exists:</strong> " . (is_dir($basePath) ? '✅ Yes' : '❌ No') . "</p>";

// Check important directories
$dirs = [
    'config' => $basePath . '/config',
    'ajaxhandler' => $basePath . '/ajaxhandler',
    'uploads' => $basePath . '/uploads',
    'uploads/hte_logos' => $basePath . '/uploads/hte_logos'
];

foreach ($dirs as $name => $dir) {
    $exists = is_dir($dir);
    $writable = $exists ? is_writable($dir) : false;
    echo "<p><strong>{$name}/:</strong> ";
    echo $exists ? '✅ Exists' : '❌ Missing';
    if ($exists) {
        echo $writable ? ' | ✅ Writable' : ' | ❌ Not Writable';
    }
    echo "</p>";
}

// Test 4: Configuration Files
echo "<h2>⚙️ Configuration Files</h2>";
$configFiles = [
    'cloudinary.php' => $basePath . '/config/cloudinary.php',
    'database.php' => $basePath . '/database/database.php'
];

foreach ($configFiles as $name => $file) {
    $exists = file_exists($file);
    echo "<p><strong>{$name}:</strong> " . ($exists ? '✅ Exists' : '❌ Missing') . "</p>";
}

// Test 5: Try to include Cloudinary config
echo "<h2>☁️ Cloudinary Configuration Test</h2>";
try {
    if (file_exists($basePath . '/config/cloudinary.php')) {
        require_once $basePath . '/config/cloudinary.php';
        echo "<p>✅ Cloudinary config loaded successfully</p>";
        
        echo "<p><strong>Cloud Name:</strong> " . (defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : '❌ Not defined') . "</p>";
        echo "<p><strong>API Key:</strong> " . (defined('CLOUDINARY_API_KEY') ? CLOUDINARY_API_KEY : '❌ Not defined') . "</p>";
        echo "<p><strong>API Secret:</strong> " . (defined('CLOUDINARY_API_SECRET') ? '✅ Defined' : '❌ Not defined') . "</p>";
        
        if (function_exists('getCloudinaryUploader')) {
            echo "<p>✅ getCloudinaryUploader function available</p>";
            
            try {
                $uploader = getCloudinaryUploader();
                echo "<p>✅ CloudinaryUploader instance created</p>";
            } catch (Exception $e) {
                echo "<p>❌ Error creating uploader: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p>❌ getCloudinaryUploader function not found</p>";
        }
        
    } else {
        echo "<p>❌ Cloudinary config file not found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading Cloudinary config: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 6: cURL Test
echo "<h2>🌐 cURL Test</h2>";
if (function_loaded('curl')) {
    echo "<p>✅ cURL extension loaded</p>";
    
    // Test basic cURL functionality
    $ch = curl_init();
    if ($ch) {
        echo "<p>✅ cURL handle created</p>";
        
        // Test HTTPS request
        curl_setopt($ch, CURLOPT_URL, 'https://httpbin.org/get');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($result && $httpCode == 200) {
            echo "<p>✅ HTTPS requests working (HTTP {$httpCode})</p>";
        } else {
            echo "<p>❌ HTTPS request failed: HTTP {$httpCode}";
            if ($error) {
                echo " - " . htmlspecialchars($error);
            }
            echo "</p>";
        }
        
        curl_close($ch);
    } else {
        echo "<p>❌ Failed to create cURL handle</p>";
    }
} else {
    echo "<p>❌ cURL extension not loaded</p>";
}

// Test 7: Memory and Limits
echo "<h2>💾 System Limits</h2>";
echo "<p><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . " seconds</p>";
echo "<p><strong>Max File Upload:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>Max POST Size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>File Uploads:</strong> " . (ini_get('file_uploads') ? '✅ Enabled' : '❌ Disabled') . "</p>";

// Test 8: Error Log Location
echo "<h2>📝 Error Logging</h2>";
echo "<p><strong>Log Errors:</strong> " . (ini_get('log_errors') ? '✅ Enabled' : '❌ Disabled') . "</p>";
echo "<p><strong>Error Log:</strong> " . (ini_get('error_log') ?: 'Default location') . "</p>";
echo "<p><strong>Display Errors:</strong> " . (ini_get('display_errors') ? '✅ Enabled' : '❌ Disabled') . "</p>";

// Test 9: Test File Upload Simulation
echo "<h2>📤 Upload Test</h2>";
echo "<form method='post' enctype='multipart/form-data' style='border: 1px solid #ccc; padding: 15px; margin: 10px 0;'>";
echo "<p><strong>Test file upload functionality:</strong></p>";
echo "<input type='file' name='test_file' accept='image/*'>";
echo "<input type='submit' name='test_upload' value='Test Upload'>";
echo "</form>";

if (isset($_POST['test_upload']) && isset($_FILES['test_file'])) {
    echo "<h3>Upload Test Results:</h3>";
    echo "<p><strong>File Info:</strong></p>";
    echo "<pre>";
    print_r($_FILES['test_file']);
    echo "</pre>";
    
    if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        echo "<p>✅ File uploaded successfully to temp location</p>";
        
        // Try to read file info
        $fileInfo = [
            'Size' => $_FILES['test_file']['size'] . ' bytes',
            'Type' => $_FILES['test_file']['type'],
            'Temp Name' => $_FILES['test_file']['tmp_name'],
            'File Exists' => file_exists($_FILES['test_file']['tmp_name']) ? '✅ Yes' : '❌ No'
        ];
        
        foreach ($fileInfo as $key => $value) {
            echo "<p><strong>{$key}:</strong> {$value}</p>";
        }
        
        // Test Cloudinary upload if config is available
        if (defined('CLOUDINARY_CLOUD_NAME') && function_exists('getCloudinaryUploader')) {
            echo "<h4>Testing Cloudinary Upload:</h4>";
            try {
                $cloudinary = getCloudinaryUploader();
                $result = $cloudinary->uploadImage($_FILES['test_file']['tmp_name'], 'internconnect/diagnostics', 'diagnostic_test_' . time());
                
                if ($result['success']) {
                    echo "<p>✅ <strong>Cloudinary upload successful!</strong></p>";
                    echo "<p><strong>URL:</strong> <a href='" . htmlspecialchars($result['url']) . "' target='_blank'>" . htmlspecialchars($result['url']) . "</a></p>";
                    echo "<img src='" . htmlspecialchars($result['url']) . "' style='max-width: 200px; border: 1px solid #ccc; padding: 5px;' />";
                } else {
                    echo "<p>❌ <strong>Cloudinary upload failed:</strong> " . htmlspecialchars($result['error']) . "</p>";
                }
            } catch (Exception $e) {
                echo "<p>❌ <strong>Exception during Cloudinary upload:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        
    } else {
        echo "<p>❌ File upload failed with error code: " . $_FILES['test_file']['error'] . "</p>";
    }
}

echo "<hr>";
echo "<h2>🎯 Diagnostic Summary</h2>";
echo "<p>This diagnostic will help identify what's different between your local environment and Render.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Compare results with your local <code>test_cloudinary.php</code></li>";
echo "<li>Look for missing extensions or configuration differences</li>";
echo "<li>Check file permissions and directory structure</li>";
echo "<li>Test the file upload functionality above</li>";
echo "</ol>";

echo "<hr>";
echo "<p><em>Diagnostic completed on: " . date('Y-m-d H:i:s') . "</em></p>";
echo "</body></html>";
?>