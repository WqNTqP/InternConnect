<?php
/**
 * Upload Helper with Cloudinary Fallback
 * This provides a robust upload system that tries Cloudinary first,
 * then falls back to local storage if Cloudinary fails
 */

class HybridUploader {
    private $cloudinary;
    
    public function __construct() {
        // Include Cloudinary configuration
        $path = $_SERVER['DOCUMENT_ROOT'];
        $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
        require_once $basePath . '/config/cloudinary.php';
        
        if (isCloudinaryConfigured()) {
            $this->cloudinary = getCloudinaryUploader();
        }
    }
    
    /**
     * Upload image with Cloudinary first, fallback to local
     */
    public function uploadImage($tempFilePath, $originalName, $folder = 'uploads', $subfolder = '') {
        $result = [
            'success' => false,
            'url' => null,
            'filename' => null,
            'method' => null,
            'error' => null
        ];
        
        // Try Cloudinary first if configured
        if ($this->cloudinary && isCloudinaryConfigured()) {
            $publicId = uniqid() . '_' . pathinfo($originalName, PATHINFO_FILENAME);
            $cloudinaryFolder = 'internconnect/' . trim($folder, '/');
            
            $cloudinaryResult = $this->cloudinary->uploadImage($tempFilePath, $cloudinaryFolder, $publicId);
            
            if ($cloudinaryResult['success']) {
                $result['success'] = true;
                $result['url'] = $cloudinaryResult['url'];
                $result['filename'] = $cloudinaryResult['url']; // Store full URL as filename
                $result['method'] = 'cloudinary';
                error_log("Successfully uploaded to Cloudinary: " . $cloudinaryResult['url']);
                return $result;
            } else {
                error_log("Cloudinary upload failed, falling back to local: " . $cloudinaryResult['error']);
            }
        }
        
        // Fallback to local upload
        return $this->uploadLocal($tempFilePath, $originalName, $folder, $subfolder);
    }
    
    /**
     * Local upload fallback
     */
    private function uploadLocal($tempFilePath, $originalName, $folder, $subfolder) {
        $result = [
            'success' => false,
            'url' => null,
            'filename' => null,
            'method' => 'local',
            'error' => null
        ];
        
        try {
            // Determine base path
            $path = $_SERVER['DOCUMENT_ROOT'];
            $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
            
            // Create upload directory
            $uploadDir = $basePath . '/' . trim($folder, '/') . '/';
            if ($subfolder) {
                $uploadDir .= trim($subfolder, '/') . '/';
            }
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $uniqueName = uniqid() . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $uniqueName;
            
            // Move uploaded file
            if (move_uploaded_file($tempFilePath, $targetPath)) {
                $result['success'] = true;
                $result['filename'] = $uniqueName;
                $result['url'] = trim($folder, '/') . '/' . ($subfolder ? trim($subfolder, '/') . '/' : '') . $uniqueName;
                error_log("Successfully uploaded locally: " . $result['url']);
            } else {
                $result['error'] = 'Failed to move uploaded file';
                error_log("Local upload failed: " . $result['error']);
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            error_log("Local upload exception: " . $e->getMessage());
        }
        
        return $result;
    }
}

/**
 * Helper function to get hybrid uploader instance
 */
function getHybridUploader() {
    return new HybridUploader();
}
?>