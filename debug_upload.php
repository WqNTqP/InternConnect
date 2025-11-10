<?php
/**
 * Debug Upload Test - Check what's happening on live site
 */

// Load the safe upload configuration
require_once 'config/safe_upload.php';

// Check if this is a POST request with file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    
    // Debug information
    $debug = [
        'environment' => isRenderEnvironment() ? 'render' : 'local',
        'cloudinary_configured' => function_exists('isCloudinaryConfigured') ? isCloudinaryConfigured() : false,
        'uploader_available' => function_exists('getCloudinaryUploader') ? (getCloudinaryUploader() !== null) : false,
        'file_info' => [
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type'],
            'error' => $file['error']
        ]
    ];
    
    // Try the upload
    if ($file['error'] === UPLOAD_ERR_OK) {
        $result = safeUploadImage($file['tmp_name'], $file['name'], 'debug_test');
        $debug['upload_result'] = $result;
    } else {
        $debug['upload_error'] = 'File upload error: ' . $file['error'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($debug, JSON_PRETTY_PRINT);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Debug Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .debug { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #ffe6e6; border: 1px solid #ff9999; }
        .success { background: #e6ffe6; border: 1px solid #99ff99; }
    </style>
</head>
<body>
    <h1>🔍 Upload Debug Test</h1>
    
    <div class="debug">
        <h3>Environment Info:</h3>
        <p><strong>Environment:</strong> <?php echo isRenderEnvironment() ? 'Render (Live)' : 'Local'; ?></p>
        <p><strong>Cloudinary Configured:</strong> <?php echo (function_exists('isCloudinaryConfigured') && isCloudinaryConfigured()) ? '✅ Yes' : '❌ No'; ?></p>
        <p><strong>Uploader Available:</strong> <?php echo (function_exists('getCloudinaryUploader') && getCloudinaryUploader()) ? '✅ Yes' : '❌ No'; ?></p>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
        <p><strong>Upload Max:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
        <h3>Test File Upload:</h3>
        <input type="file" name="test_file" accept="image/*" required>
        <button type="submit">🧪 Test Upload</button>
    </form>
    
    <div class="debug">
        <h3>Instructions:</h3>
        <ol>
            <li>Select a small image file (under 1MB)</li>
            <li>Click "Test Upload"</li>
            <li>Check the JSON response for detailed debug info</li>
            <li>Look for errors in the upload_result section</li>
        </ol>
    </div>
</body>
</html>