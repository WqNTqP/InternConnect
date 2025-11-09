<?php
// Flask startup diagnostic script
header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔍 Flask Startup Diagnostics</h1>\n";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>\n";

echo "=== PROCESS CHECK ===\n";
$psOutput = shell_exec('ps aux 2>/dev/null');
if ($psOutput) {
    echo "All processes:\n";
    echo $psOutput . "\n\n";
    
    // Look for Python processes specifically
    $pythonProcesses = shell_exec('ps aux | grep python 2>/dev/null');
    if ($pythonProcesses) {
        echo "Python processes found:\n";
        echo $pythonProcesses . "\n";
    } else {
        echo "❌ No Python processes found\n";
    }
} else {
    echo "❌ Cannot execute ps command\n";
}

echo "\n=== PORT USAGE ===\n";
$netstatOutput = shell_exec('netstat -tlnp 2>/dev/null');
if ($netstatOutput) {
    echo "Network connections:\n";
    echo $netstatOutput . "\n\n";
    
    // Check specifically for port 5000
    $port5000 = shell_exec('netstat -tlnp | grep :5000 2>/dev/null');
    if ($port5000) {
        echo "✅ Port 5000 is in use:\n";
        echo $port5000 . "\n";
    } else {
        echo "❌ Port 5000 is NOT in use\n";
    }
} else {
    echo "❌ Cannot execute netstat command\n";
}

echo "\n=== FILE SYSTEM CHECK ===\n";
$currentDir = getcwd();
echo "Current directory: $currentDir\n";

// Check if Flask files exist
$flaskFiles = [
    'start.sh',
    'requirements.txt',
    'ML/sample_frontend/app.py',
    'ML/model/pre-assessment.joblib'
];

foreach ($flaskFiles as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ $file ({$size} bytes)\n";
        
        // Show first few lines of start.sh
        if ($file === 'start.sh' && $size > 0) {
            echo "   First 10 lines of start.sh:\n";
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            for ($i = 0; $i < min(10, count($lines)); $i++) {
                echo "   " . ($i+1) . ": " . $lines[$i] . "\n";
            }
        }
    } else {
        echo "❌ $file (missing)\n";
    }
}

echo "\n=== PYTHON CHECK ===\n";
$pythonVersion = shell_exec('python3 --version 2>&1');
if ($pythonVersion) {
    echo "Python version: " . trim($pythonVersion) . "\n";
} else {
    echo "❌ Python3 not found\n";
}

$pipList = shell_exec('pip3 list 2>&1');
if ($pipList) {
    echo "Installed packages:\n";
    echo $pipList . "\n";
} else {
    echo "❌ Cannot list pip packages\n";
}

echo "\n=== DIRECT FLASK TEST ===\n";
echo "Trying to start Flask manually...\n";
$flaskTest = shell_exec('cd ML/sample_frontend && timeout 5s python3 app.py 2>&1');
if ($flaskTest) {
    echo "Flask startup output:\n";
    echo $flaskTest . "\n";
} else {
    echo "❌ No output from Flask startup test\n";
}

echo "</pre>\n";
?>