<?php
// Render Deployment Diagnostic Script
header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔍 Render Deployment Diagnostics</h1>\n";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>\n";

echo "=== BASIC SERVER INFO ===\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "Host: " . $_SERVER['HTTP_HOST'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "\n";

echo "=== ENVIRONMENT DETECTION ===\n";
$isRender = strpos($_SERVER['HTTP_HOST'], 'onrender.com') !== false;
echo "Is Render: " . ($isRender ? '✅ Yes' : '❌ No (Local)') . "\n";
echo "Environment: " . ($isRender ? 'Production' : 'Development') . "\n";
echo "\n";

echo "=== DIRECTORY STRUCTURE ===\n";
$currentDir = getcwd();
echo "Current Directory: $currentDir\n";

// Check if key files exist
$keyFiles = [
    'start.sh',
    'requirements.txt', 
    'ML/sample_frontend/app.py',
    'ML/model/pre-assessment.joblib',
    'api/health.php',
    'api/predict.php',
    'api/post_analysis.php'
];

foreach ($keyFiles as $file) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;
    echo sprintf("%-30s: %s", $file, $exists ? "✅ ({$size} bytes)" : "❌ Missing") . "\n";
}
echo "\n";

echo "=== FLASK CONNECTION TEST ===\n";
$flaskUrl = 'http://localhost:5000/health';
echo "Testing: $flaskUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $flaskUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Flask Connection: FAILED\n";
    echo "   Error: $error\n";
    echo "   This means Flask is NOT running on port 5000\n";
} else {
    echo "✅ Flask Connection: SUCCESS (HTTP $httpCode)\n";
    echo "   Response: " . substr($response, 0, 100) . "...\n";
}
echo "\n";

echo "=== PROCESS CHECK ===\n";
// Try to check if Flask process is running (Linux only)
if (function_exists('shell_exec') && !stripos(PHP_OS, 'WIN') !== false) {
    $processes = shell_exec('ps aux | grep python 2>/dev/null');
    if ($processes) {
        echo "Python Processes:\n";
        echo $processes . "\n";
    } else {
        echo "❌ No Python processes found or cannot execute shell commands\n";
    }
} else {
    echo "⚠️ Cannot check processes (Windows or restricted environment)\n";
}
echo "\n";

echo "=== PORT USAGE CHECK ===\n";
if (function_exists('shell_exec') && !stripos(PHP_OS, 'WIN') !== false) {
    $ports = shell_exec('netstat -tlnp 2>/dev/null | grep :5000');
    if ($ports) {
        echo "Port 5000 Status:\n";
        echo $ports . "\n";
    } else {
        echo "❌ Port 5000 is not in use - Flask is not listening\n";
    }
} else {
    echo "⚠️ Cannot check port usage\n";
}
echo "\n";

echo "=== RECOMMENDATIONS ===\n";
if ($isRender) {
    echo "🔧 Since this is Render deployment:\n";
    echo "1. Check Render logs for Flask startup messages\n";
    echo "2. Verify environment variables are set\n";
    echo "3. Ensure start.sh has execute permissions\n";
    echo "4. Check if Python dependencies installed correctly\n";
} else {
    echo "🏠 Since this is local development:\n";
    echo "1. Start Flask manually: python ML/sample_frontend/app.py\n";
    echo "2. Check if XAMPP is running\n";
    echo "3. Verify environment variables in .env file\n";
}

echo "</pre>\n";

echo "<div style='margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px;'>\n";
echo "<h3>📋 Next Steps:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Copy the output above</strong></li>\n";
echo "<li><strong>Check your Render service logs</strong> (Dashboard → Service → Logs)</li>\n";
echo "<li><strong>Compare this diagnostic with the Render logs</strong></li>\n";
echo "<li><strong>Look for error messages</strong> in the startup sequence</li>\n";
echo "</ol>\n";
echo "</div>\n";
?>