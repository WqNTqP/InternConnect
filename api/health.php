<?php
// Flask API proxy for health endpoint
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
    
    if ($isProduction) {
        // On Render, Flask runs on localhost:5000 internally
        $flask_url = 'http://localhost:5000/health';
    } else {
        // For local development, use local Flask
        $flask_url = 'http://localhost:5000/health';
    }
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $flask_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Execute and get response
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        // Flask API not available
        http_response_code(503);
        echo json_encode([
            'status' => 'error',
            'message' => 'Flask API not available',
            'flask_error' => curl_error($ch),
            'timestamp' => date('Y-m-d H:i:s'),
            'mock_data' => true
        ]);
    } else {
        http_response_code($httpCode);
        echo $response;
    }
    
    curl_close($ch);
} else {
    echo json_encode(['error' => 'Only GET method allowed']);
}
?>