<?php
// Debug Database Configuration
echo "<h1>🔍 Database Configuration Debug</h1>";

// Test loadEnvFile function
require_once 'database/database.php';

echo "<h2>Environment Variables After Loading:</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Variable</th><th>Value</th></tr>";

$envVars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME'];
foreach ($envVars as $var) {
    $envValue = $_ENV[$var] ?? 'Not Set';
    $getenvValue = getenv($var) ?: 'Not Set';
    echo "<tr><td>{$var} (from \$_ENV)</td><td>{$envValue}</td></tr>";
    echo "<tr><td>{$var} (from getenv)</td><td>{$getenvValue}</td></tr>";
}

echo "</table>";

echo "<h2>Testing Database Connection:</h2>";
try {
    $db = new Database();
    if ($db->conn !== null) {
        echo "<div style='color: green;'>✅ Connection successful!</div>";
        
        // Test query to get connection info
        $stmt = $db->conn->prepare("SELECT USER() as current_user, DATABASE() as current_db");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Connected as:</strong> {$result['current_user']}</p>";
        echo "<p><strong>Database:</strong> {$result['current_db']}</p>";
    } else {
        echo "<div style='color: red;'>❌ Connection failed</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}
?>