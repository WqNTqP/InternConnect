<?php
// Simple Railway Connection Test
// Quick test to verify Railway database connectivity

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🚂 Simple Railway Connection Test</h2>";

require_once __DIR__ . '/database/database.php';

try {
    echo "<p>🔄 Attempting Railway connection...</p>";
    
    $db = new Database();
    
    if ($db->conn !== null) {
        echo "<p style='color: green;'>✅ <strong>SUCCESS!</strong> Railway database connected!</p>";
        
        // Test a simple query
        $stmt = $db->conn->prepare("SELECT DATABASE() as db_name, NOW() as current_time");
        $stmt->execute();
        $result = $stmt->fetch();
        
        echo "<p>📋 <strong>Database:</strong> " . $result['db_name'] . "</p>";
        echo "<p>🕒 <strong>Server Time:</strong> " . $result['current_time'] . "</p>";
        
        // Test table access
        $stmt = $db->conn->prepare("SHOW TABLES");
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p>📊 <strong>Tables Found:</strong> " . count($tables) . "</p>";
        
        if (count($tables) > 0) {
            echo "<ul>";
            foreach (array_slice($tables, 0, 5) as $table) {
                echo "<li>{$table}</li>";
            }
            if (count($tables) > 5) {
                echo "<li><em>... and " . (count($tables) - 5) . " more</em></li>";
            }
            echo "</ul>";
        }
        
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>🎉 Railway Database Ready!</h3>";
        echo "<p>✅ Connection successful<br>";
        echo "✅ Database accessible<br>";
        echo "✅ Tables available<br>";
        echo "✅ Ready for production deployment</p>";
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>❌ <strong>FAILED!</strong> Could not connect to Railway database.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Recommendation:</strong> Check Railway service status and credentials.</p>";
}

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>