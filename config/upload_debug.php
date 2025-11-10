<?php
/**
 * Enhanced Error Logging for Upload Debugging
 * This will help us capture exactly what's happening during uploads
 */

function logUploadError($context, $message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] UPLOAD_ERROR [{$context}]: {$message}";
    
    if ($data !== null) {
        $logEntry .= " | DATA: " . json_encode($data);
    }
    
    $logEntry .= " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $logEntry .= " | USER_AGENT: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $logEntry .= "\n";
    
    // Try multiple log methods
    error_log($logEntry);
    
    // Also try to write to a specific file
    $logFile = __DIR__ . '/../uploads/upload_debug.log';
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function safeCloudinaryUpload($filePath, $folder, $publicId = null) {
    try {
        logUploadError('CLOUDINARY_START', 'Starting Cloudinary upload', [
            'file_path' => $filePath,
            'folder' => $folder,
            'public_id' => $publicId,
            'file_exists' => file_exists($filePath),
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0
        ]);
        
        // Check if file exists
        if (!file_exists($filePath)) {
            logUploadError('CLOUDINARY_ERROR', 'File does not exist', ['file_path' => $filePath]);
            return ['success' => false, 'error' => 'File does not exist'];
        }
        
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            logUploadError('CLOUDINARY_ERROR', 'cURL not available');
            return ['success' => false, 'error' => 'cURL extension not available'];
        }
        
        // Include Cloudinary configuration
        $path = $_SERVER['DOCUMENT_ROOT'];
        $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
        $configPath = $basePath . '/config/cloudinary.php';
        
        if (!file_exists($configPath)) {
            logUploadError('CLOUDINARY_ERROR', 'Config file not found', ['config_path' => $configPath]);
            return ['success' => false, 'error' => 'Cloudinary configuration not found'];
        }
        
        require_once $configPath;
        
        // Check if configuration is loaded
        if (!defined('CLOUDINARY_CLOUD_NAME') || !defined('CLOUDINARY_API_KEY') || !defined('CLOUDINARY_API_SECRET')) {
            logUploadError('CLOUDINARY_ERROR', 'Configuration constants not defined');
            return ['success' => false, 'error' => 'Cloudinary configuration incomplete'];
        }
        
        logUploadError('CLOUDINARY_CONFIG', 'Configuration loaded', [
            'cloud_name' => CLOUDINARY_CLOUD_NAME,
            'api_key' => CLOUDINARY_API_KEY,
            'has_secret' => !empty(CLOUDINARY_API_SECRET)
        ]);
        
        // Create uploader instance
        if (!function_exists('getCloudinaryUploader')) {
            logUploadError('CLOUDINARY_ERROR', 'getCloudinaryUploader function not found');
            return ['success' => false, 'error' => 'Cloudinary uploader function not available'];
        }
        
        $cloudinary = getCloudinaryUploader();
        
        // Attempt upload
        $result = $cloudinary->uploadImage($filePath, $folder, $publicId);
        
        logUploadError('CLOUDINARY_RESULT', 'Upload attempt completed', [
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'url' => $result['url'] ?? null
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        logUploadError('CLOUDINARY_EXCEPTION', 'Exception during upload', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

function fallbackLocalUpload($filePath, $uploadDir, $filename) {
    try {
        logUploadError('LOCAL_FALLBACK', 'Starting local upload fallback', [
            'file_path' => $filePath,
            'upload_dir' => $uploadDir,
            'filename' => $filename
        ]);
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                logUploadError('LOCAL_ERROR', 'Failed to create upload directory', ['upload_dir' => $uploadDir]);
                return ['success' => false, 'error' => 'Could not create upload directory'];
            }
        }
        
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($filePath, $targetPath)) {
            logUploadError('LOCAL_SUCCESS', 'Local upload successful', [
                'target_path' => $targetPath,
                'file_size' => filesize($targetPath)
            ]);
            return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
        } else {
            logUploadError('LOCAL_ERROR', 'move_uploaded_file failed', [
                'source' => $filePath,
                'target' => $targetPath,
                'source_exists' => file_exists($filePath),
                'target_dir_writable' => is_writable($uploadDir)
            ]);
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }
        
    } catch (Exception $e) {
        logUploadError('LOCAL_EXCEPTION', 'Exception during local upload', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

function hybridUpload($filePath, $cloudinaryFolder, $localUploadDir, $filename, $publicId = null) {
    // Try Cloudinary first
    $cloudinaryResult = safeCloudinaryUpload($filePath, $cloudinaryFolder, $publicId);
    
    if ($cloudinaryResult['success']) {
        logUploadError('HYBRID_SUCCESS', 'Cloudinary upload successful', ['url' => $cloudinaryResult['url']]);
        return [
            'success' => true,
            'method' => 'cloudinary',
            'url' => $cloudinaryResult['url'],
            'public_id' => $cloudinaryResult['public_id'] ?? null
        ];
    }
    
    // Fallback to local upload
    logUploadError('HYBRID_FALLBACK', 'Cloudinary failed, trying local', ['cloudinary_error' => $cloudinaryResult['error']]);
    
    $localResult = fallbackLocalUpload($filePath, $localUploadDir, $filename);
    
    if ($localResult['success']) {
        logUploadError('HYBRID_SUCCESS', 'Local fallback successful', ['filename' => $filename]);
        return [
            'success' => true,
            'method' => 'local',
            'filename' => $filename,
            'path' => $localResult['path']
        ];
    }
    
    // Both methods failed
    logUploadError('HYBRID_FAILURE', 'Both Cloudinary and local uploads failed', [
        'cloudinary_error' => $cloudinaryResult['error'],
        'local_error' => $localResult['error']
    ]);
    
    return [
        'success' => false,
        'error' => 'Both cloud and local uploads failed. Cloudinary: ' . $cloudinaryResult['error'] . '. Local: ' . $localResult['error']
    ];
}
?>