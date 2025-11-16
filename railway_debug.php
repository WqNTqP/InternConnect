<?php
// Simple Railway Connection Debug Script
// Tests Railway connection with minimal configuration

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<style>body{font-family:Arial;margin:20px;} .error{color:red;} .success{color:green;} .info{color:blue;}</style>";
echo "<h1>Railway Database Debug Test</h1>";

// Railway credentials
$host = 'mainline.proxy.rlwy.net';
$port = 31782;
$username = 'root';
$password = 'LYeUTqrnaDxpSAdWiirrGhFAcVVyNMGJ';
$database = 'railway';

echo "<div class='info'><h2>🔧 Connection Details:</h2>";
echo "<ul>";
echo "<li><strong>Host:</strong> {$host}:{$port}</li>";
echo "<li><strong>Database:</strong> {$database}</li>";
echo "<li><strong>Username:</strong> {$username}</li>";
echo "<li><strong>Password:</strong> " . str_repeat('*', strlen($password)) . "</li>";
echo "</ul></div>";

echo "<div class='info'><h2>🌐 Environment Info:</h2>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Server:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'CLI') . "</li>";
echo "<li><strong>Render Environment:</strong> " . (isset($_SERVER['RENDER']) ? 'Yes' : 'No') . "</li>";
echo "<li><strong>PDO MySQL:</strong> " . (extension_loaded('pdo_mysql') ? 'Available' : 'Missing') . "</li>";
echo "<li><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? 'Available' : 'Missing') . "</li>";
echo "</ul></div>";

// Test 1: Basic TCP Connection
echo "<h2>🔌 Test 1: TCP Connection Test</h2>";
$tcpTest = @fsockopen($host, $port, $errno, $errstr, 10);
if ($tcpTest) {
    echo "<div class='success'>✅ TCP connection to {$host}:{$port} successful</div>";
    fclose($tcpTest);
} else {
    echo "<div class='error'>❌ TCP connection failed: {$errstr} (Error: {$errno})</div>";
}

// Test 2: DNS Resolution
echo "<h2>🌐 Test 2: DNS Resolution</h2>";
$ip = gethostbyname($host);
if ($ip !== $host) {
    echo "<div class='success'>✅ DNS resolved: {$host} → {$ip}</div>";
} else {
    echo "<div class='error'>❌ DNS resolution failed for {$host}</div>";
}

// Test 3: Basic PDO Connection (No SSL)
echo "<h2>🔐 Test 3: Basic PDO Connection (No SSL)</h2>";
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_PERSISTENT => false
    ];
    
    echo "<div class='info'>DSN: {$dsn}</div>";
    
    $startTime = microtime(true);
    $pdo = new PDO($dsn, $username, $password, $options);
    $endTime = microtime(true);
    
    $connectionTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "<div class='success'>✅ Basic PDO connection successful ({$connectionTime}ms)</div>";
    
    // Test query
    $stmt = $pdo->query("SELECT DATABASE() as db, NOW() as time, VERSION() as version");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<div class='success'>";
    echo "<h3>Database Info:</h3>";
    echo "<ul>";
    echo "<li><strong>Current DB:</strong> {$result['db']}</li>";
    echo "<li><strong>Server Time:</strong> {$result['time']}</li>";
    echo "<li><strong>MySQL Version:</strong> {$result['version']}</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ PDO connection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    
    // Analyze error
    $errorMsg = $e->getMessage();
    echo "<h3>Error Analysis:</h3>";
    echo "<ul>";
    
    if (strpos($errorMsg, 'Connection refused') !== false) {
        echo "<li class='error'>Railway service appears to be down or blocking connections</li>";
    } elseif (strpos($errorMsg, 'timeout') !== false) {
        echo "<li class='error'>Connection timeout - network connectivity issue</li>";
    } elseif (strpos($errorMsg, 'Access denied') !== false) {
        echo "<li class='error'>Invalid credentials - check Railway database settings</li>";
    } elseif (strpos($errorMsg, 'Unknown host') !== false) {
        echo "<li class='error'>DNS resolution problem</li>";
    } else {
        echo "<li class='error'>General connection error</li>";
    }
    
    echo "</ul>";
}

// Test 4: SSL Connection Test
echo "<h2>🔒 Test 4: SSL Connection Test</h2>";

$sslPaths = [
    '/etc/ssl/certs/ca-certificates.crt',
    '/etc/pki/tls/certs/ca-bundle.crt',
    '/etc/ssl/cert.pem',
    '/usr/local/share/certs/ca-root-nss.crt'
];

echo "<h3>Available SSL Certificates:</h3>";
echo "<ul>";
foreach ($sslPaths as $path) {
    if (file_exists($path)) {
        echo "<li class='success'>✅ {$path}</li>";
    } else {
        echo "<li class='error'>❌ {$path}</li>";
    }
}
echo "</ul>";

// Try SSL connection if certificates exist
$sslCert = null;
foreach ($sslPaths as $path) {
    if (file_exists($path)) {
        $sslCert = $path;
        break;
    }
}

if ($sslCert) {
    try {
        $sslOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 30,
            PDO::MYSQL_ATTR_SSL_CA => $sslCert,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ];
        
        $sslPdo = new PDO($dsn, $username, $password, $sslOptions);
        echo "<div class='success'>✅ SSL connection successful using: {$sslCert}</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ SSL connection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='error'>❌ No SSL certificates found on this system</div>";
}

echo "<hr>";
echo "<div class='info'>";
echo "<h2>📋 Summary & Recommendations:</h2>";

if (isset($pdo) && $pdo) {
    echo "<div class='success'>";
    echo "<h3>✅ Railway Connection Working!</h3>";
    echo "<ul>";
    echo "<li>Railway database is accessible from this environment</li>";
    echo "<li>Connection time: {$connectionTime}ms</li>";
    echo "<li>Database is operational and responding</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ Railway Connection Failed</h3>";
    echo "<ul>";
    echo "<li>Railway database is not accessible from this environment</li>";
    echo "<li>This could be due to Railway service issues or network restrictions</li>";
    echo "<li>Check Railway dashboard for service status</li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div>";

echo "<p><em>Test completed at: " . date('Y-m-d H:i:s T') . "</em></p>";
?>