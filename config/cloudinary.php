<?php
/**
 * Cloudinary Configuration
 * Replace the placeholder values with your actual Cloudinary credentials
 * Get these from: https://console.cloudinary.com/console
 */

// Cloudinary Configuration
define('CLOUDINARY_CLOUD_NAME', 'dubq3bubx');
define('CLOUDINARY_API_KEY', '899855394663579'); 
define('CLOUDINARY_API_SECRET', 'GHs7_rp-1WGjFrafoy4cqsBg7jQ');

// Cloudinary Upload Presets (optional, for additional configuration)
define('CLOUDINARY_UPLOAD_PRESET', 'internconnect_uploads'); // You can create this in Cloudinary dashboard

/**
 * Simple Cloudinary Upload Function
 * Uses PHP cURL instead of SDK for lightweight implementation
 */
class CloudinaryUploader {
    
    private $cloud_name;
    private $api_key;
    private $api_secret;
    
    public function __construct() {
        $this->cloud_name = CLOUDINARY_CLOUD_NAME;
        $this->api_key = CLOUDINARY_API_KEY;
        $this->api_secret = CLOUDINARY_API_SECRET;
    }
    
    /**
     * Upload image to Cloudinary
     * @param string $filePath - Path to the uploaded file
     * @param string $folder - Cloudinary folder (e.g., 'logos', 'profiles', 'reports')
     * @param string $publicId - Optional custom filename
     * @return array - Response with URL or error
     */
    public function uploadImage($filePath, $folder = 'internconnect', $publicId = null) {
        try {
            // Check if cURL is available
            if (!function_exists('curl_init')) {
                return ['success' => false, 'error' => 'cURL extension is not available'];
            }
            
            // Check if file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                return ['success' => false, 'error' => 'File not found or not readable: ' . $filePath];
            }
            
            // Generate timestamp and signature
            $timestamp = time();
            $params = [
                'timestamp' => $timestamp,
                'folder' => $folder
            ];
            
            if ($publicId) {
                $params['public_id'] = $publicId;
            }
            
            // Create signature
            $signature = $this->generateSignature($params, $this->api_secret);
            
            // Prepare the upload data
            $postData = [
                'file' => new CURLFile($filePath),
                'timestamp' => $timestamp,
                'folder' => $folder,
                'api_key' => $this->api_key,
                'signature' => $signature
            ];
            
            if ($publicId) {
                $postData['public_id'] = $publicId;
            }
            
            // Upload URL
            $url = "https://api.cloudinary.com/v1_1/{$this->cloud_name}/image/upload";
            
            // cURL request with better error handling
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'InternConnect/1.0');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Handle cURL errors
            if ($response === false || !empty($curlError)) {
                error_log("Cloudinary cURL error: " . $curlError);
                return ['success' => false, 'error' => 'cURL error: ' . $curlError];
            }
            
            if ($httpCode !== 200) {
                error_log("Cloudinary HTTP error {$httpCode}: " . $response);
                return ['success' => false, 'error' => 'Upload failed with HTTP code: ' . $httpCode . '. Response: ' . substr($response, 0, 200)];
            }
            
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Cloudinary JSON decode error: " . json_last_error_msg());
                return ['success' => false, 'error' => 'Invalid JSON response from Cloudinary'];
            }
            
            if (isset($result['secure_url'])) {
                return [
                    'success' => true,
                    'url' => $result['secure_url'],
                    'public_id' => $result['public_id'],
                    'format' => $result['format'] ?? 'jpg'
                ];
            } else {
                error_log("Cloudinary upload failed: " . json_encode($result));
                $errorMsg = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error';
                return ['success' => false, 'error' => 'Cloudinary error: ' . $errorMsg];
            }
            
        } catch (Exception $e) {
            error_log("Cloudinary upload exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete image from Cloudinary
     * @param string $publicId - The public ID of the image to delete
     * @return array - Response
     */
    public function deleteImage($publicId) {
        try {
            $timestamp = time();
            $params = [
                'public_id' => $publicId,
                'timestamp' => $timestamp
            ];
            
            $signature = $this->generateSignature($params, $this->api_secret);
            
            $postData = [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
                'api_key' => $this->api_key,
                'signature' => $signature
            ];
            
            $url = "https://api.cloudinary.com/v1_1/{$this->cloud_name}/image/destroy";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            return ['success' => true, 'result' => $result];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Upload PDF/Document to Cloudinary
     * @param string $filePath - Path to the uploaded PDF file
     * @param string $folder - Cloudinary folder (e.g., 'moa', 'documents')
     * @param string $publicId - Optional custom filename
     * @return array - Response with URL or error
     */
    public function uploadDocument($filePath, $folder = 'moa', $publicId = null) {
        try {
            // Check if cURL is available
            if (!function_exists('curl_init')) {
                return ['success' => false, 'error' => 'cURL extension is not available'];
            }
            
            // Check if file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                return ['success' => false, 'error' => 'File not found or not readable: ' . $filePath];
            }
            
            // Validate file type (PDF only)
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $filePath);
            finfo_close($fileInfo);
            
            if ($mimeType !== 'application/pdf') {
                return ['success' => false, 'error' => 'Only PDF files are allowed'];
            }
            
            // Check file size (5MB limit)
            $fileSize = filesize($filePath);
            $maxSize = 5 * 1024 * 1024; // 5MB in bytes
            
            if ($fileSize > $maxSize) {
                return ['success' => false, 'error' => 'File size exceeds 5MB limit'];
            }
            
            // Generate timestamp and signature
            $timestamp = time();
            
            // Build signature parameters - order matters for Cloudinary
            $signatureParams = [
                'folder' => $folder,
                'timestamp' => $timestamp
            ];
            
            if ($publicId) {
                $signatureParams['public_id'] = $publicId;
            }
            
            // Note: resource_type should NOT be included in signature for raw uploads
            // Debug logging for signature generation
            error_log("PDF Upload Signature Params: " . json_encode($signatureParams));
            
            // Create signature
            $signature = $this->generateSignature($signatureParams, $this->api_secret);
            
            error_log("Generated PDF Upload Signature: " . $signature);
            
            // Ensure proper file extension for PDF (compatible with older PHP versions)
            if ($publicId && substr($publicId, -4) !== '.pdf') {
                $publicId .= '.pdf';
            }
            
            // Create CURLFile with proper filename and mime type
            $curlFile = new CURLFile($filePath, 'application/pdf', ($publicId ? basename($publicId) : 'document.pdf'));
            
            // Prepare the upload data
            $postData = [
                'file' => $curlFile,
                'timestamp' => $timestamp,
                'folder' => $folder,
                'resource_type' => 'raw',
                'api_key' => $this->api_key,
                'signature' => $signature
            ];
            
            if ($publicId) {
                $postData['public_id'] = $publicId;
            }
            
            // Upload URL for raw files (PDFs)
            $url = "https://api.cloudinary.com/v1_1/{$this->cloud_name}/raw/upload";
            
            // cURL request with better error handling
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second timeout for larger files
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'InternConnect/1.0');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Handle cURL errors
            if ($response === false || !empty($curlError)) {
                error_log("Cloudinary PDF upload cURL error: " . $curlError);
                return ['success' => false, 'error' => 'cURL error: ' . $curlError];
            }
            
            if ($httpCode !== 200) {
                error_log("Cloudinary PDF upload HTTP error {$httpCode}: " . $response);
                return ['success' => false, 'error' => 'Upload failed with HTTP code: ' . $httpCode . '. Response: ' . substr($response, 0, 200)];
            }
            
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Cloudinary PDF upload JSON decode error: " . json_last_error_msg());
                return ['success' => false, 'error' => 'Invalid JSON response from Cloudinary'];
            }
            
            if (isset($result['secure_url'])) {
                return [
                    'success' => true,
                    'url' => $result['secure_url'],
                    'public_id' => $result['public_id'],
                    'format' => $result['format'] ?? 'pdf',
                    'bytes' => $result['bytes'] ?? $fileSize
                ];
            } else {
                error_log("Cloudinary PDF upload failed: " . json_encode($result));
                $errorMsg = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error';
                return ['success' => false, 'error' => 'Cloudinary error: ' . $errorMsg];
            }
            
        } catch (Exception $e) {
            error_log("Cloudinary PDF upload exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete document from Cloudinary
     * @param string $publicId - The public ID of the document to delete
     * @return array - Response
     */
    public function deleteDocument($publicId) {
        try {
            $timestamp = time();
            $params = [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
                'resource_type' => 'raw' // Important for PDFs
            ];
            
            $signature = $this->generateSignature($params, $this->api_secret);
            
            $postData = [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
                'resource_type' => 'raw',
                'api_key' => $this->api_key,
                'signature' => $signature
            ];
            
            $url = "https://api.cloudinary.com/v1_1/{$this->cloud_name}/raw/destroy";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            return ['success' => true, 'result' => $result];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate signature for Cloudinary API
     * @param array $params - Parameters to sign
     * @param string $apiSecret - API secret
     * @return string - SHA256 signature
     */
    private function generateSignature($params, $apiSecret) {
        ksort($params);
        $stringToSign = '';
        
        foreach ($params as $key => $value) {
            // Skip empty values
            if ($value !== null && $value !== '') {
                $stringToSign .= $key . '=' . $value . '&';
            }
        }
        
        $stringToSign = rtrim($stringToSign, '&') . $apiSecret;
        
        // Debug logging
        error_log("String to sign: '" . $stringToSign . "'");
        $signature = hash('sha1', $stringToSign); // Use SHA1 for Cloudinary
        error_log("Generated signature: " . $signature);
        
        return $signature;
    }
    
    /**
     * Generate optimized Cloudinary URL for display
     * @param string $publicId - The public ID from Cloudinary
     * @param array $transformations - Optional transformations (width, height, quality, etc.)
     * @return string - Optimized image URL
     */
    public function getImageUrl($publicId, $transformations = []) {
        $baseUrl = "https://res.cloudinary.com/{$this->cloud_name}/image/upload/";
        
        // Default optimizations
        $defaultTransforms = [
            'f_auto', // Auto format (WebP when supported)
            'q_auto', // Auto quality
        ];
        
        // Add custom transformations
        $allTransforms = array_merge($defaultTransforms, $transformations);
        $transformString = implode(',', $allTransforms);
        
        return $baseUrl . $transformString . '/' . $publicId;
    }
}

/**
 * Helper function to get Cloudinary uploader instance
 * @return CloudinaryUploader
 */
function getCloudinaryUploader() {
    return new CloudinaryUploader();
}

/**
 * Helper function to check if Cloudinary is properly configured
 * @return bool
 */
function isCloudinaryConfigured() {
    return CLOUDINARY_CLOUD_NAME !== 'YOUR_CLOUD_NAME_HERE' && 
           CLOUDINARY_API_KEY !== 'YOUR_API_KEY_HERE' && 
           CLOUDINARY_API_SECRET !== 'YOUR_API_SECRET_HERE';
}
?>