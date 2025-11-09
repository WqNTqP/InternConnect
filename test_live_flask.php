<?php
// Direct test of live Render Flask API - bypassing local proxies
header('Content-Type: text/html; charset=UTF-8');

echo "<h2>Testing Live Render Flask API Directly</h2>\n";
echo "<pre>\n";

// Your live Render deployment URL
$renderBaseUrl = 'https://internconnect-kjzb.onrender.com';

// Since Flask runs internally on Render, we need to test through the deployed system
// Let's first check if the main PHP app is accessible
echo "Step 1: Testing main PHP application accessibility...\n";
echo str_repeat('-', 60) . "\n";

$mainAppUrl = $renderBaseUrl . '/mainDashboard.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $mainAppUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Main App URL: $mainAppUrl\n";
echo "HTTP Status: $httpCode\n";
echo "Response Length: " . strlen($response) . " bytes\n";
if ($error) echo "Error: $error\n";
echo "\n";

// Now test the Flask API endpoints through the deployed proxies
echo "Step 2: Testing Flask API endpoints through deployed proxies...\n";
echo str_repeat('=', 60) . "\n\n";

$endpoints = [
    'health' => [
        'url' => $renderBaseUrl . '/api/health.php',
        'method' => 'GET',
        'data' => null
    ],
    'predict' => [
        'url' => $renderBaseUrl . '/api/predict.php',
        'method' => 'POST',
        'data' => json_encode([
            "CC 102" => 85,
            "CC 103" => 90,
            "PF 101" => 88,
            "CC 104" => 87,
            "CC 106" => 89
        ])
    ],
    'post_analysis' => [
        'url' => $renderBaseUrl . '/api/post_analysis.php?student_id=59828881&responses=[4,5,3,4,5]',
        'method' => 'GET',
        'data' => null
    ]
];

$results = [];

foreach ($endpoints as $name => $config) {
    echo "Testing $name endpoint...\n";
    echo "URL: {$config['url']}\n";
    echo str_repeat('-', 50) . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    if ($config['method'] === 'POST' && $config['data']) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $config['data']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        echo "Sending: {$config['data']}\n";
    }
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $results[$name] = [
        'url' => $config['url'],
        'method' => $config['method'],
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
    if ($response) {
        // Try to format JSON response nicely
        $decodedResponse = json_decode($response, true);
        if ($decodedResponse) {
            echo json_encode($decodedResponse, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo substr($response, 0, 500) . (strlen($response) > 500 ? '...' : '') . "\n";
        }
    } else {
        echo "(empty response)\n";
    }
    echo "\n" . str_repeat('=', 60) . "\n\n";
}

echo "</pre>\n";

// Summary
echo "<h3>Test Summary:</h3>\n";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
$allSuccess = true;
foreach ($results as $name => $result) {
    $status = ($result['http_code'] == 200) ? '✅ SUCCESS' : '❌ FAILED';
    if ($result['http_code'] != 200) $allSuccess = false;
    echo "<strong>$name:</strong> $status (HTTP {$result['http_code']}) - {$result['response_time_ms']}ms<br>\n";
}
echo "</div>\n";

echo "<div style='padding: 15px; margin: 10px 0; border-radius: 5px; ";
echo $allSuccess ? "background-color: #d4edda; color: #155724;'>" : "background-color: #f8d7da; color: #721c24;'>";
echo "<strong>Overall Status: " . ($allSuccess ? "🎉 All Flask API endpoints working!" : "⚠️ Some endpoints need attention") . "</strong>";
echo "</div>";
?>