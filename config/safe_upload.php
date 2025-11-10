<?php
/**
 * Emergency Fix for Render Deployment
 * This temporarily disables Cloudinary uploads and uses local storage
 * to prevent Bad Gateway errors while we debug the issue
 */

// Check if we're running on Render (live deployment)
function isRenderEnvironment() {
    return isset($_SERVER['RENDER']) || 
           strpos($_SERVER['HTTP_HOST'] ?? '', 'onrender.com') !== false ||
           strpos($_SERVER['SERVER_NAME'] ?? '', 'onrender.com') !== false;
}

// Disable Cloudinary on Render temporarily
if (isRenderEnvironment()) {
    // Override Cloudinary functions to return false (use local storage)
    function isCloudinaryConfigured() {
        return false; // Force local storage on Render
    }
    
    function getCloudinaryUploader() {
        return null; // Force local storage on Render
    }
    
    error_log("Cloudinary disabled on Render - using local storage fallback");
} else {
    // Load normal Cloudinary configuration for local development
    $path = $_SERVER['DOCUMENT_ROOT'];
    $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
    
    if (file_exists($basePath . '/config/cloudinary.php')) {
        require_once $basePath . '/config/cloudinary.php';
    }
}

/**
 * Safe upload function that works on both local and Render
 */
function safeUploadImage($tempFile, $originalName, $folder, $subfolder = '') {
    $result = [
        'success' => false,
        'filename' => null,
        'url' => null,
        'method' => 'local'
    ];
    
    try {
        // Always use local storage for now to prevent Bad Gateway
        $path = $_SERVER['DOCUMENT_ROOT'];
        $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
        
        $uploadDir = $basePath . '/' . trim($folder, '/') . '/';
        if ($subfolder) {
            $uploadDir .= trim($subfolder, '/') . '/';
        }
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = uniqid() . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $uniqueName;
        
        if (move_uploaded_file($tempFile, $targetPath)) {
            $result['success'] = true;
            $result['filename'] = $uniqueName;
            $result['url'] = trim($folder, '/') . '/' . ($subfolder ? trim($subfolder, '/') . '/' : '') . $uniqueName;
            error_log("File uploaded successfully: " . $result['url']);
        } else {
            $result['error'] = 'Failed to move uploaded file';
            error_log("Upload failed: " . $result['error']);
        }
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        error_log("Upload exception: " . $e->getMessage());
    }
    
    return $result;
}
?>