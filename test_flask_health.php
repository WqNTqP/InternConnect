<?php
// Flask health check for live Render deployment
header('Content-Type: text/html; charset=UTF-8');

echo "<h2>Testing Live Flask API Health</h2>\n";
echo "<pre>\n";

// Test Flask API endpoints via internal proxies (updated for production)
$urls = [
    'health' => 'https://internconnect-kjzb.onrender.com/api/health.php',
    'predict' => 'https://internconnect-kjzb.onrender.com/api/predict.php',
    'post_analysis' => 'https://internconnect-kjzb.onrender.com/api/post_analysis.php'
];

// Note: These external URLs are for testing only. 
// The actual application uses internal localhost calls for better performance.

$results = [];

foreach ($urls as $type => $url) {
    echo "Testing $type endpoint: $url\n";
    echo str_repeat('-', 60) . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Longer timeout for live API
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS
    
    // For predict endpoint, send POST data; post_analysis uses GET with query params
    if ($type === 'predict') {
        curl_setopt($ch, CURLOPT_POST, true);
        $testData = json_encode(["CC 102" => 85, "CC 103" => 90, "PF 101" => 88]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $testData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        echo "Sending test data: $testData\n";
    } elseif ($type === 'post_analysis') {
        // post_analysis uses GET with query parameters
        $queryParams = '?student_id=59829536&responses=' . urlencode('[4,5,3,4,5]');
        curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
        echo "Using query parameters: $queryParams\n";
    }
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $results[$type] = [
        'url' => $url,
        'http_code' => $httpCode,
        'error' => $error,
        'response_time_ms' => $responseTime,
        'response' => $response
    ];
    
    echo "HTTP Status: $httpCode\n";
    echo "Response Time: {$responseTime}ms\n";
    if ($error) {
        echo "cURL Error: $error\n";
    }
    echo "Response:\n";
    echo $response ? $response : "(empty response)\n";
    echo "\n" . str_repeat('=', 60) . "\n\n";
}

echo "</pre>\n";
echo "<h3>Summary Results:</h3>\n";
echo "<pre>\n";
echo json_encode($results, JSON_PRETTY_PRINT);
echo "</pre>\n";

// Display overall status
$allHealthy = true;
foreach ($results as $type => $result) {
    if ($result['http_code'] !== 200) {
        $allHealthy = false;
        break;
    }
}

echo "<div style='padding: 10px; margin: 10px 0; border-radius: 5px; ";
echo $allHealthy ? "background-color: #d4edda; color: #155724;'>" : "background-color: #f8d7da; color: #721c24;'>";
echo "<strong>Overall Status: " . ($allHealthy ? "✓ All endpoints healthy" : "⚠ Some endpoints have issues") . "</strong>";
echo "</div>";
?>