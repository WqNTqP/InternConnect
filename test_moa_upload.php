<?php
/**
 * Test MOA Upload to debug Cloudinary signature issues
 */

// Include Cloudinary configuration
require_once 'config/cloudinary.php';

// Test signature generation
function testSignatureGeneration() {
    echo "<h2>Testing Cloudinary Signature Generation</h2>\n";
    
    $timestamp = time();
    $folder = 'moa';
    $publicId = 'test_hte_moa';
    
    // Test parameters like the failing request
    $params = [
        'folder' => $folder,
        'public_id' => $publicId,
        'timestamp' => $timestamp,
        'resource_type' => 'raw'
    ];
    
    echo "<p><strong>Parameters:</strong></p>\n";
    echo "<pre>" . json_encode($params, JSON_PRETTY_PRINT) . "</pre>\n";
    
    // Sort parameters
    ksort($params);
    
    echo "<p><strong>Sorted Parameters:</strong></p>\n";
    echo "<pre>" . json_encode($params, JSON_PRETTY_PRINT) . "</pre>\n";
    
    // Build string to sign
    $stringToSign = '';
    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') {
            $stringToSign .= $key . '=' . $value . '&';
        }
    }
    $stringToSign = rtrim($stringToSign, '&');
    
    echo "<p><strong>String to sign (without secret):</strong></p>\n";
    echo "<pre>" . $stringToSign . "</pre>\n";
    
    // Add API secret (don't display the actual secret)
    $stringWithSecret = $stringToSign . CLOUDINARY_API_SECRET;
    
    echo "<p><strong>String length with secret:</strong> " . strlen($stringWithSecret) . "</p>\n";
    
    // Generate signatures with different methods
    $sha1Signature = hash('sha1', $stringWithSecret);
    $sha256Signature = hash('sha256', $stringWithSecret);
    
    echo "<p><strong>SHA1 Signature:</strong> " . $sha1Signature . "</p>\n";
    echo "<p><strong>SHA256 Signature:</strong> " . $sha256Signature . "</p>\n";
    
    return [
        'timestamp' => $timestamp,
        'params' => $params,
        'stringToSign' => $stringToSign,
        'sha1' => $sha1Signature,
        'sha256' => $sha256Signature
    ];
}

// Test Cloudinary configuration
function testCloudinaryConfig() {
    echo "<h2>Cloudinary Configuration Test</h2>\n";
    
    echo "<p><strong>Cloud Name:</strong> " . CLOUDINARY_CLOUD_NAME . "</p>\n";
    echo "<p><strong>API Key:</strong> " . CLOUDINARY_API_KEY . "</p>\n";
    echo "<p><strong>API Secret Set:</strong> " . (defined('CLOUDINARY_API_SECRET') && !empty(CLOUDINARY_API_SECRET) ? 'Yes' : 'No') . "</p>\n";
    
    // Test if we can create uploader instance
    try {
        $uploader = new CloudinaryUploader();
        echo "<p><strong>Uploader Instance:</strong> ✅ Created successfully</p>\n";
    } catch (Exception $e) {
        echo "<p><strong>Uploader Instance:</strong> ❌ Failed - " . $e->getMessage() . "</p>\n";
    }
}

// Test actual upload with a small test file
function testActualUpload() {
    echo "<h2>Test Upload (Dry Run)</h2>\n";
    
    // Create a temporary test PDF content
    $testContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n>>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000074 00000 n \n0000000120 00000 n \ntrailer\n<<\n/Size 4\n/Root 1 0 R\n>>\nstartxref\n174\n%%EOF";
    
    $tempFile = sys_get_temp_dir() . '/test_moa.pdf';
    file_put_contents($tempFile, $testContent);
    
    echo "<p><strong>Test file created:</strong> " . $tempFile . "</p>\n";
    echo "<p><strong>File size:</strong> " . filesize($tempFile) . " bytes</p>\n";
    
    // Test MIME type detection
    if (function_exists('finfo_open')) {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $tempFile);
        finfo_close($fileInfo);
        echo "<p><strong>Detected MIME type:</strong> " . $mimeType . "</p>\n";
    } else {
        echo "<p><strong>finfo_open:</strong> ❌ Not available</p>\n";
    }
    
    // Clean up
    unlink($tempFile);
    
    echo "<p>📝 To test actual upload, use the main application form.</p>\n";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>MOA Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
        h2 { color: #333; border-bottom: 2px solid #007cba; }
        p { margin: 10px 0; }
    </style>
</head>
<body>
    <h1>MOA Upload Diagnostic Test</h1>
    
    <?php
    try {
        testCloudinaryConfig();
        echo "<hr>\n";
        
        testSignatureGeneration();
        echo "<hr>\n";
        
        testActualUpload();
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    }
    ?>
    
    <hr>
    <p><strong>Next Steps:</strong></p>
    <ol>
        <li>Check the error logs for detailed signature information</li>
        <li>Compare the generated signature with Cloudinary's expected format</li>
        <li>Test with the main application to see the actual API response</li>
    </ol>
</body>
</html>