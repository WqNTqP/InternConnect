<?php
/**
 * Quick diagnostic endpoint for live debugging
 * Returns JSON with system status
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

try {
    $path = $_SERVER['DOCUMENT_ROOT'];
    $basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
    
    $diagnostics = [
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'extensions' => [
            'curl' => extension_loaded('curl'),
            'json' => extension_loaded('json'),
            'fileinfo' => extension_loaded('fileinfo'),
            'gd' => extension_loaded('gd')
        ],
        'directories' => [
            'base_path' => $basePath,
            'base_exists' => is_dir($basePath),
            'config_exists' => is_dir($basePath . '/config'),
            'uploads_exists' => is_dir($basePath . '/uploads'),
            'uploads_writable' => is_writable($basePath . '/uploads')
        ],
        'files' => [
            'cloudinary_config' => file_exists($basePath . '/config/cloudinary.php'),
            'safe_upload' => file_exists($basePath . '/config/safe_upload.php'),
            'database_config' => file_exists($basePath . '/database/database.php')
        ],
        'limits' => [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'file_uploads' => ini_get('file_uploads') ? true : false
        ]
    ];
    
    // Test Cloudinary config if available
    if ($diagnostics['files']['cloudinary_config']) {
        try {
            require_once $basePath . '/config/cloudinary.php';
            $diagnostics['cloudinary'] = [
                'constants_defined' => defined('CLOUDINARY_CLOUD_NAME') && defined('CLOUDINARY_API_KEY') && defined('CLOUDINARY_API_SECRET'),
                'cloud_name' => defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : null,
                'uploader_function' => function_exists('getCloudinaryUploader')
            ];
        } catch (Exception $e) {
            $diagnostics['cloudinary'] = [
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Test cURL if available
    if ($diagnostics['extensions']['curl']) {
        $ch = curl_init();
        if ($ch) {
            curl_setopt($ch, CURLOPT_URL, 'https://httpbin.org/status/200');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            $diagnostics['curl_test'] = [
                'http_code' => $httpCode,
                'success' => ($httpCode === 200),
                'error' => $error ?: null
            ];
            
            curl_close($ch);
        }
    }
    
    $diagnostics['status'] = 'success';
    
} catch (Exception $e) {
    $diagnostics = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>