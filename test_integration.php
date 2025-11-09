<?php
/**
 * Comprehensive test for Flask+PHP integration
 */
function testFlaskAPI() {
    echo "<h2>Testing Flask API</h2>";
    
    // Proper test data for prediction
    $testData = [
        "GE 2" => 85, "GE 3" => 88, "GE 4" => 90, "GE 5" => 87, "GE 6" => 89,
        "GE 7" => 91, "GE 8" => 86, "PE 1" => 88, "PE 2" => 89, "PE 3" => 87,
        "NSTP 1" => 90, "NSTP 2" => 91, "IT 111" => 85, "IT 112" => 88,
        "IT 211" => 89, "IT 212" => 87, "IT 213" => 90, "IT 221" => 86,
        "IT 311" => 88, "IT 312" => 89, "CAP 101" => 87, "CAP 102" => 90, "SP 101" => 88
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:5000/predict');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "<p style='color: red;'>❌ Flask API Error: $error</p>";
        echo "<p><strong>Make sure Flask is running:</strong><br>";
        echo "<code>python ML\\sample_frontend\\app.py</code></p>";
        return false;
    } else if ($httpCode == 200) {
        echo "<p style='color: green;'>✅ Flask API Response (HTTP $httpCode):</p>";
        echo "<pre>$response</pre>";
        return true;
    } else {
        echo "<p style='color: orange;'>⚠️ Flask API HTTP $httpCode:</p>";
        echo "<pre>$response</pre>";
        return false;
    }
}

function testDatabase() {
    echo "<h2>Database Test</h2>";
    try {
        require_once 'database/database.php';
        $db = new Database();
        if ($db->conn) {
            echo "<p style='color: green;'>✅ Database connected successfully!</p>";
            $stmt = $db->conn->prepare("SHOW TABLES");
            $stmt->execute();
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<p>Number of tables: " . count($tables) . "</p>";
            return true;
        } else {
            echo "<p style='color: red;'>❌ Database connection failed</p>";
            return false;
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Main execution
echo "<h1>🧪 InternConnect Integration Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

$flaskWorking = testFlaskAPI();
$dbWorking = testDatabase();

echo "<h2>📋 Test Summary</h2>";
if ($flaskWorking && $dbWorking) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>✅ Integration Test PASSED!</h3>";
    echo "<p>Both Flask API and Database are working correctly.</p>";
    echo "<p><strong>Ready for deployment!</strong></p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>❌ Integration Test FAILED</h3>";
    echo "<ul>";
    if (!$flaskWorking) echo "<li>Flask API needs to be started</li>";
    if (!$dbWorking) echo "<li>Database connection needs to be fixed</li>";
    echo "</ul>";
    echo "</div>";
}
echo "<li>If both work: Your integration is ready for deployment!</li>";
echo "</ol>";
?>