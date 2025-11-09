<?php
// Flask API proxy for post_analysis endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if we're on Render (production) or local development
    $isProduction = isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'onrender.com') !== false;
    
    // Get query parameters
    $queryString = $_SERVER['QUERY_STRING'];
    
    if ($isProduction) {
        // On Render, Flask runs on localhost:5000 internally
        $flask_url = 'http://localhost:5000/post_analysis';
    } else {
        // For local development, use local Flask
        $flask_url = 'http://localhost:5000/post_analysis';
    }
    
    if ($queryString) {
        $flask_url .= '?' . $queryString;
    }
    
    // Debug the request URL being made
    error_log("Flask API Debug - URL: " . $flask_url);
    $is_local = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false;
    error_log("Flask API Debug - Is Local: " . ($is_local ? 'true' : 'false'));
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $flask_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute and get response
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Debug logging
    error_log("Flask API Debug - HTTP Code: " . $httpCode);
    error_log("Flask API Debug - cURL Error: " . $curl_error);
    error_log("Flask API Debug - Response: " . substr($response, 0, 200));
    
    if ($curl_error) {
        // Return error details for debugging
        $error_response = [
            'success' => false,
            'error' => 'Flask API error or missing data.',
            'debug' => [
                'url' => $flask_url,
                'http_code' => $httpCode,
                'curl_error' => $curl_error,
                'response' => $response,
                'is_local' => $is_local
            ]
        ];
        echo json_encode($error_response);
    } else {
        http_response_code($httpCode);
        echo $response;
    }
    
    curl_close($ch);
} else {
    echo json_encode(['error' => 'Only GET method allowed']);
}
?>