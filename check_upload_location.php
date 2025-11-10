<?php
/**
 * Upload Location Checker
 * This will help you see exactly where files are being saved
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

echo "<html><head><title>Upload Location Checker</title></head><body>";
echo "<h1>📍 Upload Location Checker</h1>";
echo "<hr>";

// Get base path
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";

echo "<h2>🔍 Current Upload Configuration</h2>";
echo "<p><strong>Base Path:</strong> " . htmlspecialchars($basePath) . "</p>";

// Check if safe_upload.php exists and what it's configured to do
$safeUploadPath = $basePath . '/config/safe_upload.php';
if (file_exists($safeUploadPath)) {
    echo "<p><strong>Safe Upload Config:</strong> ✅ Found</p>";
    
    // Include and analyze the configuration
    require_once $safeUploadPath;
    
    // Check if we're in Render environment according to safe_upload.php
    if (function_exists('isRenderEnvironment')) {
        $isRender = isRenderEnvironment();
        echo "<p><strong>Environment Detection:</strong> " . ($isRender ? '🌐 Render (Live)' : '💻 Local') . "</p>";
        
        if ($isRender) {
            echo "<p><strong>Cloudinary Status:</strong> ❌ Disabled (forced local storage)</p>";
        } else {
            echo "<p><strong>Cloudinary Status:</strong> ✅ Enabled</p>";
        }
    }
} else {
    echo "<p><strong>Safe Upload Config:</strong> ❌ Not found</p>";
}

// Check current Cloudinary configuration
$cloudinaryPath = $basePath . '/config/cloudinary.php';
if (file_exists($cloudinaryPath)) {
    echo "<p><strong>Cloudinary Config:</strong> ✅ Found</p>";
    
    require_once $cloudinaryPath;
    
    if (function_exists('isCloudinaryConfigured')) {
        $configured = isCloudinaryConfigured();
        echo "<p><strong>Cloudinary Ready:</strong> " . ($configured ? '✅ Yes' : '❌ No') . "</p>";
    }
} else {
    echo "<p><strong>Cloudinary Config:</strong> ❌ Not found</p>";
}

echo "<h2>🎯 Test Upload to See Where Files Go</h2>";
echo "<form method='post' enctype='multipart/form-data' style='border: 1px solid #ccc; padding: 20px; margin: 20px 0;'>";
echo "<p><strong>Upload a test image to see where it's stored:</strong></p>";
echo "<input type='file' name='test_upload' accept='image/*' required>";
echo "<input type='submit' name='do_upload' value='Test Upload Location' style='margin-left: 10px; padding: 5px 15px; background: #007cba; color: white; border: none; cursor: pointer;'>";
echo "</form>";

if (isset($_POST['do_upload']) && isset($_FILES['test_upload'])) {
    echo "<h3>🧪 Upload Test Results</h3>";
    
    if ($_FILES['test_upload']['error'] === UPLOAD_ERR_OK) {
        echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0;'>";
        
        // Test the actual upload logic your app uses
        if (function_exists('safeUploadImage')) {
            echo "<h4>Using safeUploadImage() function:</h4>";
            
            $result = safeUploadImage(
                $_FILES['test_upload']['tmp_name'],
                $_FILES['test_upload']['name'],
                'uploads',
                'test_uploads'
            );
            
            echo "<p><strong>Upload Result:</strong> " . ($result['success'] ? '✅ Success' : '❌ Failed') . "</p>";
            
            if ($result['success']) {
                echo "<p><strong>Method Used:</strong> " . ($result['method'] ?? 'Unknown') . "</p>";
                echo "<p><strong>Filename:</strong> " . htmlspecialchars($result['filename'] ?? 'N/A') . "</p>";
                echo "<p><strong>URL:</strong> " . htmlspecialchars($result['url'] ?? 'N/A') . "</p>";
                
                // Check if file exists locally
                $localPath = $basePath . '/uploads/test_uploads/' . ($result['filename'] ?? '');
                if (file_exists($localPath)) {
                    echo "<p><strong>Local File:</strong> ✅ Found at " . htmlspecialchars($localPath) . "</p>";
                    echo "<p><strong>File Size:</strong> " . number_format(filesize($localPath)) . " bytes</p>";
                } else {
                    echo "<p><strong>Local File:</strong> ❌ Not found locally</p>";
                }
                
                // If it looks like a Cloudinary URL, test it
                if (isset($result['url']) && strpos($result['url'], 'cloudinary.com') !== false) {
                    echo "<p><strong>Cloudinary Upload:</strong> ✅ File uploaded to cloud</p>";
                    echo "<p><strong>Cloud URL:</strong> <a href='" . htmlspecialchars($result['url']) . "' target='_blank'>" . htmlspecialchars($result['url']) . "</a></p>";
                    echo "<div style='margin: 10px 0;'>";
                    echo "<img src='" . htmlspecialchars($result['url']) . "' style='max-width: 200px; border: 1px solid #ccc; padding: 5px;' alt='Cloudinary Upload' />";
                    echo "</div>";
                }
            } else {
                echo "<p><strong>Error:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</p>";
            }
            
        } else {
            echo "<p>❌ safeUploadImage function not available</p>";
        }
        
        echo "</div>";
        
    } else {
        echo "<p>❌ Upload failed with error code: " . $_FILES['test_upload']['error'] . "</p>";
    }
}

echo "<h2>📂 Current Local Files</h2>";

// Show current local uploads
$uploadDirs = ['uploads/hte_logos', 'uploads/test_uploads', 'uploads'];
foreach ($uploadDirs as $dir) {
    $fullPath = $basePath . '/' . $dir;
    if (is_dir($fullPath)) {
        $files = array_diff(scandir($fullPath), ['.', '..']);
        $fileCount = count($files);
        
        echo "<h4>{$dir}/ ({$fileCount} files)</h4>";
        if ($fileCount > 0) {
            echo "<ul style='margin-left: 20px;'>";
            foreach (array_slice($files, 0, 10) as $file) {
                $filePath = $fullPath . '/' . $file;
                if (is_file($filePath)) {
                    $size = number_format(filesize($filePath));
                    $modified = date('Y-m-d H:i:s', filemtime($filePath));
                    echo "<li><strong>{$file}</strong> ({$size} bytes, modified: {$modified})</li>";
                }
            }
            if ($fileCount > 10) {
                echo "<li><em>... and " . ($fileCount - 10) . " more files</em></li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='margin-left: 20px; color: #666;'>No files found</p>";
        }
    } else {
        echo "<h4>{$dir}/ (directory not found)</h4>";
    }
}

echo "<hr>";
echo "<h2>🎯 How to Check Your Cloudinary Dashboard</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
echo "<p><strong>To see if files are going to Cloudinary:</strong></p>";
echo "<ol>";
echo "<li><strong>Go to:</strong> <a href='https://console.cloudinary.com/console' target='_blank'>https://console.cloudinary.com/console</a></li>";
echo "<li><strong>Login</strong> with your Cloudinary account</li>";
echo "<li><strong>Click 'Media Library'</strong> in the left sidebar</li>";
echo "<li><strong>Look for folders:</strong> internconnect/logos, internconnect/profiles, internconnect/reports</li>";
echo "<li><strong>Recent uploads</strong> will appear at the top</li>";
echo "</ol>";
echo "</div>";

echo "<p><em>Diagnostic completed: " . date('Y-m-d H:i:s') . "</em></p>";
echo "</body></html>";
?>