<?php
/**
 * Background retry system for failed Cloudinary uploads
 * This script runs periodically to retry local files that failed to upload to Cloudinary
 */

require_once 'database/database.php';
require_once 'config/safe_upload.php';

class CloudinaryRetryService {
    private $dbo;
    
    public function __construct() {
        $this->dbo = new Database();
    }
    
    /**
     * Find files that are stored locally and retry uploading to Cloudinary
     */
    public function retryFailedUploads() {
        $retryResults = [];
        
        // Check all tables for local file references
        $this->retryHteLogos($retryResults);
        $this->retryProfilePictures($retryResults);
        $this->retryReportImages($retryResults);
        
        return $retryResults;
    }
    
    private function retryHteLogos(&$results) {
        // Find HTE logos that are local files (not Cloudinary URLs)
        $stmt = $this->dbo->conn->prepare("
            SELECT HTE_ID, LOGO_FILENAME 
            FROM host_training_establishment 
            WHERE LOGO_FILENAME IS NOT NULL 
            AND LOGO_FILENAME NOT LIKE 'https://res.cloudinary.com%'
            AND LOGO_FILENAME != ''
        ");
        $stmt->execute();
        $htes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($htes as $hte) {
            $localPath = $_SERVER['DOCUMENT_ROOT'] . '/InternConnect/uploads/hte_logos/' . $hte['LOGO_FILENAME'];
            
            if (file_exists($localPath)) {
                $uploadResult = $this->uploadToCloudinary($localPath, $hte['LOGO_FILENAME'], 'uploads', 'hte_logos');
                
                if ($uploadResult['success']) {
                    // Update database with Cloudinary URL
                    $updateStmt = $this->dbo->conn->prepare("
                        UPDATE host_training_establishment 
                        SET LOGO_FILENAME = ? 
                        WHERE HTE_ID = ?
                    ");
                    $updateStmt->execute([$uploadResult['url'], $hte['HTE_ID']]);
                    
                    // Delete local file to free space
                    unlink($localPath);
                    
                    $results[] = [
                        'type' => 'hte_logo',
                        'id' => $hte['HTE_ID'],
                        'status' => 'migrated',
                        'old_path' => $hte['LOGO_FILENAME'],
                        'new_url' => $uploadResult['url']
                    ];
                }
            }
        }
    }
    
    private function retryProfilePictures(&$results) {
        // Coordinator profiles
        $stmt = $this->dbo->conn->prepare("
            SELECT COORDINATOR_ID, profile_picture 
            FROM coordinators 
            WHERE profile_picture IS NOT NULL 
            AND profile_picture NOT LIKE 'https://res.cloudinary.com%'
            AND profile_picture != ''
        ");
        $stmt->execute();
        $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($coordinators as $coord) {
            $localPath = $_SERVER['DOCUMENT_ROOT'] . '/InternConnect/uploads/' . $coord['profile_picture'];
            
            if (file_exists($localPath)) {
                $uploadResult = $this->uploadToCloudinary($localPath, $coord['profile_picture'], 'uploads', 'coordinator_profiles');
                
                if ($uploadResult['success']) {
                    $updateStmt = $this->dbo->conn->prepare("
                        UPDATE coordinators 
                        SET profile_picture = ? 
                        WHERE COORDINATOR_ID = ?
                    ");
                    $updateStmt->execute([$uploadResult['url'], $coord['COORDINATOR_ID']]);
                    unlink($localPath);
                    
                    $results[] = [
                        'type' => 'coordinator_profile',
                        'id' => $coord['COORDINATOR_ID'],
                        'status' => 'migrated'
                    ];
                }
            }
        }
        
        // Student profiles
        $stmt = $this->dbo->conn->prepare("
            SELECT INTERNS_ID, profile_picture 
            FROM interns_details 
            WHERE profile_picture IS NOT NULL 
            AND profile_picture NOT LIKE 'https://res.cloudinary.com%'
            AND profile_picture != ''
        ");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($students as $student) {
            $localPath = $_SERVER['DOCUMENT_ROOT'] . '/InternConnect/uploads/' . $student['profile_picture'];
            
            if (file_exists($localPath)) {
                $uploadResult = $this->uploadToCloudinary($localPath, $student['profile_picture'], 'uploads', 'student_profiles');
                
                if ($uploadResult['success']) {
                    $updateStmt = $this->dbo->conn->prepare("
                        UPDATE interns_details 
                        SET profile_picture = ? 
                        WHERE INTERNS_ID = ?
                    ");
                    $updateStmt->execute([$uploadResult['url'], $student['INTERNS_ID']]);
                    unlink($localPath);
                    
                    $results[] = [
                        'type' => 'student_profile',
                        'id' => $student['INTERNS_ID'],
                        'status' => 'migrated'
                    ];
                }
            }
        }
    }
    
    private function retryReportImages(&$results) {
        $stmt = $this->dbo->conn->prepare("
            SELECT id, image_filename 
            FROM report_images 
            WHERE image_filename IS NOT NULL 
            AND image_filename NOT LIKE 'https://res.cloudinary.com%'
            AND image_filename != ''
        ");
        $stmt->execute();
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($images as $image) {
            $localPath = $_SERVER['DOCUMENT_ROOT'] . '/InternConnect/uploads/reports/' . $image['image_filename'];
            
            if (file_exists($localPath)) {
                $uploadResult = $this->uploadToCloudinary($localPath, $image['image_filename'], 'uploads', 'reports');
                
                if ($uploadResult['success']) {
                    $updateStmt = $this->dbo->conn->prepare("
                        UPDATE report_images 
                        SET image_filename = ? 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$uploadResult['url'], $image['id']]);
                    unlink($localPath);
                    
                    $results[] = [
                        'type' => 'report_image',
                        'id' => $image['id'],
                        'status' => 'migrated'
                    ];
                }
            }
        }
    }
    
    private function uploadToCloudinary($localPath, $originalName, $folder, $subfolder) {
        if (!function_exists('isCloudinaryConfigured') || !isCloudinaryConfigured()) {
            return ['success' => false, 'error' => 'Cloudinary not configured'];
        }
        
        $uploader = getCloudinaryUploader();
        if (!$uploader) {
            return ['success' => false, 'error' => 'Cloudinary uploader not available'];
        }
        
        $cloudinaryFolder = trim($folder, '/');
        if ($subfolder) {
            $cloudinaryFolder .= '/' . trim($subfolder, '/');
        }
        
        return $uploader->uploadImage($localPath, $cloudinaryFolder);
    }
}

// If called directly, run the retry service
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    
    $retryService = new CloudinaryRetryService();
    $results = $retryService->retryFailedUploads();
    
    echo json_encode([
        'success' => true,
        'message' => 'Retry process completed',
        'migrated_files' => count($results),
        'details' => $results
    ], JSON_PRETTY_PRINT);
}
?>