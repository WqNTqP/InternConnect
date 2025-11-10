<?php
/**
 * Simple Cloudinary Test Script
 * Test if your Cloudinary configuration is working properly
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// Include database and Cloudinary configuration
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
require_once $basePath . '/config/cloudinary.php';

echo "<html><head><title>Cloudinary Integration Test</title></head><body>";
echo "<h1>🚀 Cloudinary Integration Test</h1>";
echo "<hr>";

// Test 1: Check configuration
echo "<h2>✅ Step 1: Configuration Check</h2>";
echo "<p><strong>Cloud Name:</strong> " . CLOUDINARY_CLOUD_NAME . "</p>";
echo "<p><strong>API Key:</strong> " . CLOUDINARY_API_KEY . "</p>";
echo "<p><strong>API Secret:</strong> " . (strlen(CLOUDINARY_API_SECRET) > 10 ? '✅ Configured' : '❌ Missing') . "</p>";

if (isCloudinaryConfigured()) {
    echo "<p style='color: green;'>✅ <strong>Cloudinary is properly configured!</strong></p>";
} else {
    echo "<p style='color: red;'>❌ <strong>Cloudinary configuration is missing!</strong></p>";
}

// Test 2: Test Cloudinary uploader instance
echo "<h2>🔧 Step 2: Uploader Instance Test</h2>";
try {
    $cloudinary = getCloudinaryUploader();
    echo "<p style='color: green;'>✅ CloudinaryUploader instance created successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error creating CloudinaryUploader: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 3: Test image upload (if test image exists)
echo "<h2>📸 Step 3: Upload Test</h2>";
$testImagePath = $basePath . '/icon/nobglogo.ico';

if (file_exists($testImagePath)) {
    echo "<p>Test image found: " . htmlspecialchars($testImagePath) . "</p>";
    
    try {
        $cloudinary = getCloudinaryUploader();
        $uploadResult = $cloudinary->uploadImage($testImagePath, 'internconnect/test', 'test_upload_' . time());
        
        if ($uploadResult['success']) {
            echo "<p style='color: green;'>✅ <strong>Upload successful!</strong></p>";
            echo "<p><strong>Cloudinary URL:</strong> <a href='" . htmlspecialchars($uploadResult['url']) . "' target='_blank'>" . htmlspecialchars($uploadResult['url']) . "</a></p>";
            echo "<p><strong>Public ID:</strong> " . htmlspecialchars($uploadResult['public_id']) . "</p>";
            echo "<div style='margin: 20px 0;'>";
            echo "<img src='" . htmlspecialchars($uploadResult['url']) . "' alt='Test Upload' style='max-width: 200px; border: 1px solid #ccc; padding: 10px;' />";
            echo "</div>";
        } else {
            echo "<p style='color: red;'>❌ <strong>Upload failed:</strong> " . htmlspecialchars($uploadResult['error']) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Exception during upload: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Test image not found at: " . htmlspecialchars($testImagePath) . "</p>";
    echo "<p>Upload test skipped. Upload a test image to this location to test uploads.</p>";
}

// Test 4: Check if existing uploads directory has files to migrate
echo "<h2>📁 Step 4: Migration Check</h2>";
$uploadsDir = $basePath . '/uploads/';
$dirs = ['hte_logos', 'reports'];

foreach ($dirs as $dir) {
    $fullPath = $uploadsDir . $dir . '/';
    if (is_dir($fullPath)) {
        $files = array_diff(scandir($fullPath), ['.', '..']);
        $fileCount = count($files);
        echo "<p><strong>{$dir}/:</strong> {$fileCount} files found</p>";
        if ($fileCount > 0) {
            echo "<ul style='margin-left: 20px;'>";
            foreach (array_slice($files, 0, 5) as $file) {
                echo "<li>" . htmlspecialchars($file) . "</li>";
            }
            if ($fileCount > 5) {
                echo "<li><em>... and " . ($fileCount - 5) . " more files</em></li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p><strong>{$dir}/:</strong> Directory not found</p>";
    }
}

echo "<h2>🎯 Next Steps</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0;'>";
echo "<h3>If everything looks good:</h3>";
echo "<ol>";
echo "<li><strong>Test logo upload:</strong> Go to your dashboard → Companies → Update Logo</li>";
echo "<li><strong>Test profile picture:</strong> Go to student dashboard → Edit Profile → Upload Picture</li>";
echo "<li><strong>Test report images:</strong> Create a weekly report with images</li>";
echo "<li><strong>Run migration:</strong> Use the migration script to move existing files to Cloudinary</li>";
echo "</ol>";
echo "</div>";

if (!isCloudinaryConfigured()) {
    echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
    echo "<h3>⚠️ Configuration Required</h3>";
    echo "<p>Update your credentials in <code>config/cloudinary.php</code>:</p>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6;'>";
    echo "define('CLOUDINARY_CLOUD_NAME', 'dubq3bubx');\n";
    echo "define('CLOUDINARY_API_KEY', '899855394663579');\n";
    echo "define('CLOUDINARY_API_SECRET', 'GHs7_rp-1WGjFrafoy4cqsBg7jQ');\n";
    echo "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Test completed on: " . date('Y-m-d H:i:s') . "</em></p>";
echo "</body></html>";
?>