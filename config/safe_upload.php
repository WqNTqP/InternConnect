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

// Load Cloudinary configuration for all environments
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";

if (file_exists($basePath . '/config/cloudinary.php')) {
    require_once $basePath . '/config/cloudinary.php';
    
    if (isRenderEnvironment()) {
        error_log("Render environment detected - will try Cloudinary first, then local fallback");
    } else {
        error_log("Local environment detected - Cloudinary enabled");
    }
} else {
    error_log("Cloudinary config not found - using local storage only");
}

/**
 * Safe upload function that tries Cloudinary first, falls back to local
 * Set $requireCloudinary = true to prevent data loss by failing instead of using ephemeral storage
 */
function safeUploadImage($tempFile, $originalName, $folder, $subfolder = '', $requireCloudinary = false) {
    $result = [
        'success' => false,
        'filename' => null,
        'url' => null,
        'method' => 'local'
    ];
    
    try {
        // Try Cloudinary first if configured
        if (function_exists('isCloudinaryConfigured') && isCloudinaryConfigured()) {
            $uploader = getCloudinaryUploader();
            if ($uploader) {
                error_log("Attempting Cloudinary upload for: " . $originalName);
                
                $cloudinaryFolder = trim($folder, '/');
                if ($subfolder) {
                    $cloudinaryFolder .= '/' . trim($subfolder, '/');
                }
                
                $cloudinaryResult = $uploader->uploadImage($tempFile, $cloudinaryFolder);
                
                if ($cloudinaryResult['success']) {
                    $result['success'] = true;
                    $result['filename'] = $cloudinaryResult['public_id'] ?? 'cloudinary_upload';
                    $result['url'] = $cloudinaryResult['url'];
                    $result['method'] = 'cloudinary';
                    error_log("Cloudinary upload successful: " . $result['url']);
                    return $result;
                } else {
                    error_log("Cloudinary upload failed: " . $cloudinaryResult['error'] . " - " . ($requireCloudinary ? "failing upload" : "falling back to local"));
                    
                    if ($requireCloudinary) {
                        $result['error'] = 'Cloudinary upload required but failed: ' . $cloudinaryResult['error'];
                        return $result;
                    }
                }
            }
        }
        
        // Check if Cloudinary is required
        if ($requireCloudinary) {
            $result['error'] = 'Cloudinary upload required but not available';
            return $result;
        }
        
        // Fallback to local storage
        error_log("Using local storage fallback for: " . $originalName);
        
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
            $result['method'] = 'local';
            error_log("Local upload successful: " . $result['url']);
        } else {
            $result['error'] = 'Failed to move uploaded file';
            error_log("Local upload failed: " . $result['error']);
        }
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        error_log("Upload exception: " . $e->getMessage());
    }
    
    return $result;
}
?>