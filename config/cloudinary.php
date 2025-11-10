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
            
            // cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return ['success' => false, 'error' => 'Upload failed with HTTP code: ' . $httpCode];
            }
            
            $result = json_decode($response, true);
            
            if (isset($result['secure_url'])) {
                return [
                    'success' => true,
                    'url' => $result['secure_url'],
                    'public_id' => $result['public_id'],
                    'format' => $result['format'] ?? 'jpg'
                ];
            } else {
                return ['success' => false, 'error' => 'Invalid response from Cloudinary'];
            }
            
        } catch (Exception $e) {
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
     * Generate signature for Cloudinary API
     * @param array $params - Parameters to sign
     * @param string $apiSecret - API secret
     * @return string - SHA256 signature
     */
    private function generateSignature($params, $apiSecret) {
        ksort($params);
        $stringToSign = '';
        
        foreach ($params as $key => $value) {
            $stringToSign .= $key . '=' . $value . '&';
        }
        
        $stringToSign = rtrim($stringToSign, '&') . $apiSecret;
        return hash('sha256', $stringToSign);
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