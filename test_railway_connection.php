<?php
// Enhanced Railway Connection Test Script
// Comprehensive testing for Railway database connectivity in production environment

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<style>
body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; line-height: 1.6; }
.container { max-width: 1200px; margin: 0 auto; }
.success { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 15px 0; }
.error { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 15px 0; }
.info { background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 15px 0; }
table { border-collapse: collapse; width: 100%; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background-color: #f8f9fa; font-weight: 600; }
.metric { display: inline-block; margin: 10px; padding: 10px 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6; }
.status-good { color: #28a745; font-weight: bold; }
.status-warning { color: #ffc107; font-weight: bold; }
.status-error { color: #dc3545; font-weight: bold; }
.code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
.progress-bar { width: 100%; background: #f1f1f1; border-radius: 5px; margin: 10px 0; }
.progress-fill { height: 20px; background: #4caf50; border-radius: 5px; text-align: center; line-height: 20px; color: white; font-size: 12px; }
</style>";

echo "<div class='container'>";
echo "<h1>🚀 Enhanced Railway Database Connection Test</h1>";
echo "<p><em>Comprehensive production readiness verification for Render deployment</em></p>";

// Simulate production environment detection
$isProduction = isset($_SERVER['RENDER']) || isset($_SERVER['RAILWAY_ENVIRONMENT']) || 
                (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'onrender.com') !== false);

echo "<div class='info'>";
echo "<h2>🌍 Environment Detection</h2>";
echo "<table>";
echo "<tr><th>Property</th><th>Value</th><th>Status</th></tr>";
echo "<tr><td>Environment Type</td><td>" . ($isProduction ? 'Production' : 'Development/Testing') . "</td>";
echo "<td class='" . ($isProduction ? 'status-warning' : 'status-good') . "'>" . ($isProduction ? 'PROD MODE' : 'TEST MODE') . "</td></tr>";
echo "<tr><td>PHP Version</td><td>" . PHP_VERSION . "</td>";
echo "<td class='" . (version_compare(PHP_VERSION, '8.0.0', '>=') ? 'status-good' : 'status-warning') . "'>" . (version_compare(PHP_VERSION, '8.0.0', '>=') ? 'MODERN' : 'LEGACY') . "</td></tr>";
echo "<tr><td>PDO MySQL</td><td>" . (extension_loaded('pdo_mysql') ? 'Available' : 'Missing') . "</td>";
echo "<td class='" . (extension_loaded('pdo_mysql') ? 'status-good' : 'status-error') . "'>" . (extension_loaded('pdo_mysql') ? 'READY' : 'MISSING') . "</td></tr>";
echo "<tr><td>OpenSSL Support</td><td>" . (extension_loaded('openssl') ? 'Available' : 'Missing') . "</td>";
echo "<td class='" . (extension_loaded('openssl') ? 'status-good' : 'status-warning') . "'>" . (extension_loaded('openssl') ? 'SSL OK' : 'NO SSL') . "</td></tr>";
echo "<tr><td>cURL Support</td><td>" . (extension_loaded('curl') ? 'Available' : 'Missing') . "</td>";
echo "<td class='" . (extension_loaded('curl') ? 'status-good' : 'status-warning') . "'>" . (extension_loaded('curl') ? 'NET OK' : 'LIMITED') . "</td></tr>";
echo "</table>";
echo "</div>";

// Test database class availability
echo "<h2>📂 Database Class Test</h2>";
$dbClassPath = __DIR__ . '/database/database.php';
if (file_exists($dbClassPath)) {
    echo "<div class='success'>";
    echo "<h3>✅ Database Class Found</h3>";
    echo "<p>Path: <span class='code'>" . htmlspecialchars($dbClassPath) . "</span></p>";
    
    require_once $dbClassPath;
    
    if (class_exists('Database')) {
        echo "<p>✅ Database class loaded successfully</p>";
    } else {
        echo "<p class='status-error'>❌ Database class not found in file</p>";
        exit;
    }
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ Database Class Missing</h3>";
    echo "<p>Cannot find: <span class='code'>" . htmlspecialchars($dbClassPath) . "</span></p>";
    echo "</div>";
    exit;
}

// Simulate production environment for testing
if (!$isProduction) {
    $_SERVER['RENDER'] = 'true'; // Force production mode for testing
    echo "<div class='warning'>";
    echo "<h3>⚠️ Simulating Production Environment</h3>";
    echo "<p>Setting RENDER environment variable to test production database selection</p>";
    echo "</div>";
}

// Test 1: Database Connection
echo "<h2>🔌 Connection Test Suite</h2>";

$connectionResults = [];
$totalTests = 6;
$passedTests = 0;

try {
    // Test connection timing
    $startTime = microtime(true);
    $db = new Database();
    $endTime = microtime(true);
    $initialConnectionTime = round(($endTime - $startTime) * 1000, 2);
    
    if ($db->conn !== null) {
        $passedTests++;
        $connectionResults['connection'] = ['status' => 'success', 'time' => $initialConnectionTime];
        
        echo "<div class='success'>";
        echo "<h3>✅ Initial Connection Successful</h3>";
        echo "<div class='metric'>Connection Time: <strong>{$initialConnectionTime}ms</strong></div>";
        echo "</div>";
        
        // Test 2: Server Information
        echo "<h3>🖥️ Server Information Test</h3>";
        try {
            $stmt = $db->conn->prepare("SELECT 
                DATABASE() as current_database,
                NOW() as server_time,
                VERSION() as mysql_version,
                @@character_set_database as charset,
                @@collation_database as collation,
                @@hostname as hostname,
                @@port as port,
                USER() as current_user,
                CONNECTION_ID() as connection_id
            ");
            $stmt->execute();
            $serverInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $passedTests++;
            $connectionResults['server_info'] = ['status' => 'success', 'data' => $serverInfo];
            
            echo "<div class='success'>";
            echo "<h4>✅ Server Information Retrieved</h4>";
            echo "<table>";
            echo "<tr><th>Property</th><th>Value</th></tr>";
            foreach ($serverInfo as $key => $value) {
                $displayKey = ucwords(str_replace('_', ' ', $key));
                echo "<tr><td>{$displayKey}</td><td><span class='code'>{$value}</span></td></tr>";
            }
            echo "</table>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "<h4>❌ Server Information Failed</h4>";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
        
        // Test 3: Table Structure Analysis
        echo "<h3>📋 Database Structure Test</h3>";
        try {
            $stmt = $db->conn->prepare("SHOW TABLES");
            $stmt->execute();
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $passedTests++;
            $connectionResults['tables'] = ['status' => 'success', 'count' => count($tables)];
            
            if (count($tables) > 0) {
                echo "<div class='success'>";
                echo "<h4>✅ Database Tables Found ({" . count($tables) . "})</h4>";
                
                // Check for critical tables
                $criticalTables = ['coordinator', 'student', 'admin', 'hte'];
                $foundCritical = [];
                foreach ($criticalTables as $critical) {
                    if (in_array($critical, $tables)) {
                        $foundCritical[] = $critical;
                    }
                }
                
                echo "<table>";
                echo "<tr><th>Table Name</th><th>Status</th></tr>";
                foreach ($tables as $table) {
                    $isCritical = in_array($table, $criticalTables);
                    echo "<tr><td><span class='code'>{$table}</span></td>";
                    echo "<td class='" . ($isCritical ? 'status-good' : '') . "'>" . ($isCritical ? '⭐ CRITICAL' : '📁 Data') . "</td></tr>";
                }
                echo "</table>";
                
                echo "<div class='metric'>Critical Tables Found: <strong>" . count($foundCritical) . "/" . count($criticalTables) . "</strong></div>";
                echo "</div>";
                
            } else {
                echo "<div class='warning'>";
                echo "<h4>⚠️ Empty Database</h4>";
                echo "<p>Database exists but contains no tables. You may need to import your data.</p>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "<h4>❌ Table Structure Test Failed</h4>";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
        
        // Test 4: Authentication System Test
        echo "<h3>🔐 Authentication System Test</h3>";
        try {
            // Test coordinator table access
            $stmt = $db->conn->prepare("SELECT COUNT(*) as coordinator_count FROM coordinator LIMIT 1");
            $stmt->execute();
            $coordResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $passedTests++;
            
            echo "<div class='success'>";
            echo "<h4>✅ Authentication System Ready</h4>";
            echo "<div class='metric'>Coordinators: <strong>" . $coordResult['coordinator_count'] . "</strong></div>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "<h4>❌ Authentication System Issue</h4>";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>This may indicate missing coordinator table or permission issues.</p>";
            echo "</div>";
        }
        
        // Test 5: Performance Benchmarking
        echo "<h3>⚡ Performance Benchmark Test</h3>";
        
        $benchmarkAttempts = 10;
        $queryTimes = [];
        $connectionTimes = [];
        $successful = 0;
        
        echo "<div class='info'>";
        echo "<h4>🏃‍♂️ Running {$benchmarkAttempts} Performance Tests...</h4>";
        echo "</div>";
        
        for ($i = 1; $i <= $benchmarkAttempts; $i++) {
            try {
                // Test new connection
                $startTime = microtime(true);
                $testDb = new Database();
                $connEndTime = microtime(true);
                
                if ($testDb->conn !== null) {
                    // Test query
                    $queryStartTime = microtime(true);
                    $testStmt = $testDb->conn->prepare("SELECT COUNT(*) as test_count FROM coordinator");
                    $testStmt->execute();
                    $queryEndTime = microtime(true);
                    
                    $connTime = round(($connEndTime - $startTime) * 1000, 2);
                    $queryTime = round(($queryEndTime - $queryStartTime) * 1000, 2);
                    
                    $connectionTimes[] = $connTime;
                    $queryTimes[] = $queryTime;
                    $successful++;
                    
                    echo "<span style='color: green;'>Test {$i}: {$connTime}ms ✅</span><br>";
                } else {
                    echo "<span style='color: red;'>Test {$i}: Failed ❌</span><br>";
                }
                $testDb = null; // Force cleanup
                
            } catch (Exception $e) {
                echo "<span style='color: red;'>Test {$i}: Error ❌</span><br>";
            }
            
            flush();
        }
        
        if ($successful > 0) {
            $passedTests++;
            
            $avgConnTime = round(array_sum($connectionTimes) / count($connectionTimes), 2);
            $avgQueryTime = round(array_sum($queryTimes) / count($queryTimes), 2);
            $successRate = round(($successful / $benchmarkAttempts) * 100, 1);
            
            $performanceGrade = 'A';
            if ($avgConnTime > 1000 || $successRate < 90) $performanceGrade = 'B';
            if ($avgConnTime > 2000 || $successRate < 70) $performanceGrade = 'C';
            if ($avgConnTime > 3000 || $successRate < 50) $performanceGrade = 'D';
            
            $gradeClass = $performanceGrade === 'A' ? 'status-good' : ($performanceGrade === 'B' ? 'status-warning' : 'status-error');
            
            echo "<div class='success'>";
            echo "<h4>📊 Performance Results</h4>";
            echo "<table>";
            echo "<tr><th>Metric</th><th>Value</th><th>Grade</th></tr>";
            echo "<tr><td>Success Rate</td><td>{$successRate}% ({$successful}/{$benchmarkAttempts})</td><td class='{$gradeClass}'>{$performanceGrade}</td></tr>";
            echo "<tr><td>Avg Connection Time</td><td>{$avgConnTime}ms</td><td class='{$gradeClass}'>{$performanceGrade}</td></tr>";
            echo "<tr><td>Avg Query Time</td><td>{$avgQueryTime}ms</td><td class='{$gradeClass}'>{$performanceGrade}</td></tr>";
            echo "<tr><td>Fastest Connection</td><td>" . min($connectionTimes) . "ms</td><td>-</td></tr>";
            echo "<tr><td>Slowest Connection</td><td>" . max($connectionTimes) . "ms</td><td>-</td></tr>";
            echo "</table>";
            echo "</div>";
        }
        
        // Test 6: SSL Configuration Test
        echo "<h3>🔒 SSL Configuration Test</h3>";
        try {
            $stmt = $db->conn->prepare("SHOW STATUS LIKE 'Ssl_cipher'");
            $stmt->execute();
            $sslResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sslResult && !empty($sslResult['Value'])) {
                $passedTests++;
                echo "<div class='success'>";
                echo "<h4>✅ SSL Connection Active</h4>";
                echo "<p>SSL Cipher: <span class='code'>" . htmlspecialchars($sslResult['Value']) . "</span></p>";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "<h4>⚠️ Non-SSL Connection</h4>";
                echo "<p>Connection is working but not using SSL encryption.</p>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='info'>";
            echo "<h4>ℹ️ SSL Status Unknown</h4>";
            echo "<p>Cannot determine SSL status, but connection is working.</p>";
            echo "</div>";
        }
        
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ Initial Connection Failed</h3>";
        echo "<p>Database connection returned null.</p>";
        echo "</div>";
    }
    
} catch (Exception $mainError) {
    echo "<div class='error'>";
    echo "<h3>❌ Critical Connection Error</h3>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($mainError->getMessage()) . "</p>";
    echo "<p><strong>Error Code:</strong> " . $mainError->getCode() . "</p>";
    
    // Enhanced error analysis
    $errorMsg = strtolower($mainError->getMessage());
    echo "<h4>🔍 Error Analysis:</h4>";
    echo "<ul>";
    
    if (strpos($errorMsg, 'connection refused') !== false) {
        echo "<li class='status-error'>❌ Railway service is down or unreachable</li>";
        echo "<li>🔄 Recommendation: Try again or check Railway status</li>";
    } elseif (strpos($errorMsg, 'access denied') !== false) {
        echo "<li class='status-error'>❌ Authentication failed - invalid credentials</li>";
        echo "<li>🔑 Recommendation: Verify Railway database credentials</li>";
    } elseif (strpos($errorMsg, 'timeout') !== false || strpos($errorMsg, 'timed out') !== false) {
        echo "<li class='status-error'>❌ Network timeout - connection too slow</li>";
        echo "<li>🌐 Recommendation: Check Railway service status or network connectivity</li>";
    } elseif (strpos($errorMsg, 'ssl') !== false) {
        echo "<li class='status-error'>❌ SSL/TLS configuration issue</li>";
        echo "<li>🔒 Recommendation: Check SSL certificate paths</li>";
    } elseif (strpos($errorMsg, 'unknown host') !== false) {
        echo "<li class='status-error'>❌ DNS resolution failed</li>";
        echo "<li>🌍 Recommendation: Check Railway hostname</li>";
    } else {
        echo "<li class='status-error'>❌ General connection failure</li>";
        echo "<li>🔧 Recommendation: Review all connection parameters</li>";
    }
    
    echo "</ul>";
    echo "</div>";
}

// Final Results Summary
$successRate = round(($passedTests / $totalTests) * 100, 1);
echo "<h2>🎯 Final Results Summary</h2>";

if ($successRate >= 85) {
    echo "<div class='success'>";
    echo "<h3>🎉 Excellent! Railway Database Ready for Production</h3>";
    echo "<div class='progress-bar'>";
    echo "<div class='progress-fill' style='width: {$successRate}%;'>Passed: {$passedTests}/{$totalTests} tests ({$successRate}%)</div>";
    echo "</div>";
    echo "<h4>✅ Deployment Recommendation:</h4>";
    echo "<ul>";
    echo "<li><strong>Proceed with Railway database</strong> - All systems operational</li>";
    echo "<li><strong>Monitor performance</strong> - Set up logging for production issues</li>";
    echo "<li><strong>Production ready</strong> - Railway database fully configured</li>";
    echo "</ul>";
} elseif ($successRate >= 60) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Caution: Railway Database Has Issues</h3>";
    echo "<div class='progress-bar'>";
    echo "<div class='progress-fill' style='width: {$successRate}%; background: #ffc107;'>Passed: {$passedTests}/{$totalTests} tests ({$successRate}%)</div>";
    echo "</div>";
    echo "<h4>⚠️ Deployment Recommendation:</h4>";
    echo "<ul>";
    echo "<li><strong>Railway may work but watch for issues</strong></li>";
    echo "<li><strong>Monitor closely</strong> - Check for timeout or connection issues</li>";
    echo "<li><strong>Test thoroughly</strong> - Verify stability in production environment</li>";
    echo "</ul>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ Railway Database Not Ready for Production</h3>";
    echo "<div class='progress-bar'>";
    echo "<div class='progress-fill' style='width: {$successRate}%; background: #dc3545;'>Passed: {$passedTests}/{$totalTests} tests ({$successRate}%)</div>";
    echo "</div>";
    echo "<h4>❌ Deployment Recommendation:</h4>";
    echo "<ul>";
    echo "<li><strong>Railway connection issues detected</strong></li>";
    echo "<li><strong>Check Railway service status</strong> - Verify database is running</li>";
    echo "<li><strong>Review network connectivity</strong> - Test from different locations</li>";
    echo "</ul>";
}

echo "<div class='info'>";
echo "<h3>📋 Next Steps Checklist</h3>";
echo "<table>";
echo "<tr><th>Action</th><th>Status</th><th>Priority</th></tr>";

if ($successRate >= 85) {
    echo "<tr><td>✅ Commit current Railway configuration</td><td class='status-good'>READY</td><td>HIGH</td></tr>";
    echo "<tr><td>🚀 Deploy to Render with Railway</td><td class='status-good'>GO</td><td>HIGH</td></tr>";
    echo "<tr><td>📊 Set up production monitoring</td><td class='status-warning'>TODO</td><td>MEDIUM</td></tr>";
} else {
    echo "<tr><td>🔧 Debug Railway connection</td><td class='status-error'>NEEDED</td><td>HIGH</td></tr>";
    echo "<tr><td>📞 Contact Railway support</td><td class='status-warning'>TODO</td><td>HIGH</td></tr>";
    echo "<tr><td>📝 Check service status</td><td class='status-warning'>TODO</td><td>MEDIUM</td></tr>";
}

echo "</table>";
echo "</div>";

echo "<div class='info'>";
echo "<h4>📊 Test Summary</h4>";
echo "<ul>";
echo "<li><strong>Test Date:</strong> " . date('Y-m-d H:i:s T') . "</li>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Environment:</strong> " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'Command Line') . "</li>";
echo "<li><strong>Success Rate:</strong> {$successRate}% ({$passedTests}/{$totalTests})</li>";
echo "</ul>";
echo "</div>";

echo "</div>";
?>