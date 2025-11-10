<?php
/**
 * Emergency fallback - disable Cloudinary temporarily
 * Replace the upload logic with this if needed
 */

// In your upload handlers, replace Cloudinary calls with:
function emergencyLocalUpload($file, $uploadDir, $prefix = '') {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}

// Usage:
// $result = emergencyLocalUpload($_FILES['LOGO'], $basePath . '/uploads/hte_logos/', 'hte_logo_');
?>