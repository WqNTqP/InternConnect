<?php
// Railway Connection Verification Script
// This simulates the production environment connection to Railway

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; }
.error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; }
.info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 10px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 10px 0; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>";

echo "<h1>🔍 Railway Database Connection Verification</h1>";
echo "<p>Testing Railway connection with production-like settings...</p>";

// Simulate production environment
$_SERVER['RENDER'] = true; // Simulate Render environment

// Include your database class
require_once __DIR__ . '/database/database.php';

echo "<div class='info'>";
echo "<h2>🏗️ Environment Simulation</h2>";
echo "<ul>";
echo "<li><strong>Environment:</strong> Production (simulated)</li>";
echo "<li><strong>Platform:</strong> Render (simulated)</li>";
echo "<li><strong>SSL Support:</strong> " . (function_exists('openssl_get_cert_locations') ? 'Available' : 'Not Available') . "</li>";
echo "<li><strong>PDO Extensions:</strong> " . (extension_loaded('pdo_mysql') ? 'Available' : 'Not Available') . "</li>";
echo "</ul>";
echo "</div>";

// Test 1: Basic Database Connection
echo "<h2>🧪 Test 1: Database Connection</h2>";

try {
    $startTime = microtime(true);
    $db = new Database();
    $endTime = microtime(true);
    $connectionTime = round(($endTime - $startTime) * 1000, 2);
    
    if ($db->conn !== null) {
        echo "<div class='success'>";
        echo "<h3>✅ Connection Successful!</h3>";
        echo "<p><strong>Connection Time:</strong> {$connectionTime}ms</p>";
        echo "<p><strong>Connection Status:</strong> Active and Ready</p>";
        echo "</div>";
        
        // Test 2: Database Query
        echo "<h2>🧪 Test 2: Database Query Test</h2>";
        try {
            $stmt = $db->conn->prepare("SELECT 
                DATABASE() as current_db, 
                NOW() as server_time, 
                VERSION() as mysql_version,
                @@character_set_database as charset,
                @@collation_database as collation
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<div class='success'>";
            echo "<h3>✅ Query Successful!</h3>";
            echo "<table>";
            echo "<tr><th>Property</th><th>Value</th></tr>";
            foreach ($result as $key => $value) {
                echo "<tr><td>" . ucwords(str_replace('_', ' ', $key)) . "</td><td>{$value}</td></tr>";
            }
            echo "</table>";
            echo "</div>";
            
        } catch (Exception $queryError) {
            echo "<div class='error'>";
            echo "<h3>❌ Query Failed</h3>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($queryError->getMessage()) . "</p>";
            echo "</div>";
        }
        
        // Test 3: Table Access Test
        echo "<h2>🧪 Test 3: Table Access Test</h2>";
        try {
            $stmt = $db->conn->prepare("SHOW TABLES");
            $stmt->execute();
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) > 0) {
                echo "<div class='success'>";
                echo "<h3>✅ Tables Accessible!</h3>";
                echo "<p><strong>Found " . count($tables) . " tables:</strong></p>";
                echo "<ul>";
                foreach (array_slice($tables, 0, 10) as $table) { // Show first 10 tables
                    echo "<li>{$table}</li>";
                }
                if (count($tables) > 10) {
                    echo "<li><em>... and " . (count($tables) - 10) . " more tables</em></li>";
                }
                echo "</ul>";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "<h3>⚠️ No Tables Found</h3>";
                echo "<p>Database is accessible but appears to be empty.</p>";
                echo "</div>";
            }
            
        } catch (Exception $tableError) {
            echo "<div class='error'>";
            echo "<h3>❌ Table Access Failed</h3>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($tableError->getMessage()) . "</p>";
            echo "</div>";
        }
        
        // Test 4: Coordinator Login Test (if coordinator table exists)
        echo "<h2>🧪 Test 4: Login System Test</h2>";
        try {
            $stmt = $db->conn->prepare("SELECT COUNT(*) as user_count FROM coordinator LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<div class='success'>";
            echo "<h3>✅ Login System Ready!</h3>";
            echo "<p><strong>Coordinator Table:</strong> Accessible</p>";
            echo "<p><strong>User Count:</strong> " . $result['user_count'] . " coordinators found</p>";
            echo "</div>";
            
        } catch (Exception $loginError) {
            echo "<div class='error'>";
            echo "<h3>❌ Login System Issue</h3>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($loginError->getMessage()) . "</p>";
            echo "<p>This might indicate missing tables or permissions issues.</p>";
            echo "</div>";
        }
        
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ Connection Failed</h3>";
        echo "<p>Database connection returned null - check the detailed error below.</p>";
        echo "</div>";
    }
    
} catch (Exception $mainError) {
    echo "<div class='error'>";
    echo "<h3>❌ Connection Error</h3>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($mainError->getMessage()) . "</p>";
    echo "<p><strong>Error Code:</strong> " . $mainError->getCode() . "</p>";
    
    // Provide specific troubleshooting
    $errorMsg = $mainError->getMessage();
    echo "<h4>🔧 Troubleshooting:</h4>";
    echo "<ul>";
    
    if (strpos($errorMsg, 'Connection refused') !== false) {
        echo "<li style='color: red;'>Railway database service might be down</li>";
        echo "<li>Check Railway dashboard to ensure database is running</li>";
    } elseif (strpos($errorMsg, 'Access denied') !== false) {
        echo "<li style='color: red;'>Invalid credentials detected</li>";
        echo "<li>Verify username/password in Railway dashboard</li>";
    } elseif (strpos($errorMsg, 'timeout') !== false || strpos($errorMsg, 'timed out') !== false) {
        echo "<li style='color: red;'>Network timeout - this might happen on Render too</li>";
        echo "<li>Consider using InfinityFree database instead</li>";
    } elseif (strpos($errorMsg, 'SSL') !== false) {
        echo "<li style='color: red;'>SSL connection issue</li>";
        echo "<li>Railway might require different SSL configuration</li>";
    } else {
        echo "<li style='color: red;'>General connection issue</li>";
        echo "<li>Check all connection parameters</li>";
    }
    
    echo "</ul>";
    echo "</div>";
}

// Test 5: Connection Benchmarking
echo "<h2>🧪 Test 5: Connection Performance</h2>";
echo "<p>Testing connection speed (simulating production load)...</p>";

$attempts = 5;
$times = [];
$successful = 0;

for ($i = 1; $i <= $attempts; $i++) {
    try {
        $startTime = microtime(true);
        $testDb = new Database();
        if ($testDb->conn !== null) {
            $stmt = $testDb->conn->prepare("SELECT 1");
            $stmt->execute();
            $endTime = microtime(true);
            $time = round(($endTime - $startTime) * 1000, 2);
            $times[] = $time;
            $successful++;
            echo "<span style='color: green;'>Attempt {$i}: {$time}ms ✅</span><br>";
        } else {
            echo "<span style='color: red;'>Attempt {$i}: Failed ❌</span><br>";
        }
        $testDb = null; // Close connection
    } catch (Exception $e) {
        echo "<span style='color: red;'>Attempt {$i}: Error - " . htmlspecialchars($e->getMessage()) . " ❌</span><br>";
    }
}

if ($successful > 0) {
    $avgTime = round(array_sum($times) / count($times), 2);
    $successRate = round(($successful / $attempts) * 100, 1);
    
    echo "<div class='" . ($successRate >= 80 ? 'success' : 'warning') . "'>";
    echo "<h3>📊 Performance Results:</h3>";
    echo "<ul>";
    echo "<li><strong>Success Rate:</strong> {$successRate}% ({$successful}/{$attempts})</li>";
    echo "<li><strong>Average Response Time:</strong> {$avgTime}ms</li>";
    echo "<li><strong>Fastest:</strong> " . min($times) . "ms</li>";
    echo "<li><strong>Slowest:</strong> " . max($times) . "ms</li>";
    echo "</ul>";
    
    if ($successRate >= 80 && $avgTime < 1000) {
        echo "<p><strong>✅ Recommendation:</strong> Railway connection looks good for production!</p>";
    } elseif ($successRate >= 60) {
        echo "<p><strong>⚠️ Recommendation:</strong> Railway connection works but may be unstable. Consider monitoring.</p>";
    } else {
        echo "<p><strong>❌ Recommendation:</strong> Railway connection unreliable. Consider using InfinityFree instead.</p>";
    }
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ All Connection Attempts Failed</h3>";
    echo "<p><strong>Recommendation:</strong> Use InfinityFree database for production deployment.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<div class='info'>";
echo "<h2>🚀 Next Steps</h2>";
if ($successful >= 4) {
    echo "<ol>";
    echo "<li><strong>✅ Railway Connection Working:</strong> You can safely commit and deploy</li>";
    echo "<li><strong>Monitor:</strong> Watch for any timeout issues in production</li>";
    echo "<li><strong>Backup Plan:</strong> Keep InfinityFree credentials ready as fallback</li>";
    echo "</ol>";
} else {
    echo "<ol>";
    echo "<li><strong>❌ Railway Connection Issues:</strong> Consider switching to InfinityFree</li>";
    echo "<li><strong>Alternative:</strong> Update database config to use InfinityFree for production</li>";
    echo "<li><strong>Deploy:</strong> InfinityFree typically has better Render compatibility</li>";
    echo "</ol>";
}
echo "</div>";

echo "<div class='info'>";
echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s T') . "</p>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Server:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'Command Line') . "</p>";
echo "</div>";
?>