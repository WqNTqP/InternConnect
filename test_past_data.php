<?php
// Test database connection and check past_data table
require_once 'database/database.php';

try {
    $db = new Database();
    echo "<h2>Database Connection Test</h2>\n";
    echo "<p>✅ Successfully connected to database</p>\n";
    
    // Check if past_data table exists
    $stmt = $db->conn->prepare("SHOW TABLES LIKE 'past_data'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p>✅ past_data table exists</p>\n";
        
        // Get table structure
        $stmt = $db->conn->prepare("DESCRIBE past_data");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table Structure:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
        
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Get record count
        $stmt = $db->conn->prepare("SELECT COUNT(*) as count FROM past_data");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p>📊 Total records: {$count['count']}</p>\n";
        
        // Sample a few records
        if ($count['count'] > 0) {
            $stmt = $db->conn->prepare("SELECT * FROM past_data LIMIT 3");
            $stmt->execute();
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Sample Records:</h3>\n";
            echo "<pre>" . print_r($samples, true) . "</pre>\n";
        }
        
    } else {
        echo "<p>❌ past_data table does NOT exist</p>\n";
        echo "<p>Available tables:</p>\n";
        
        $stmt = $db->conn->prepare("SHOW TABLES");
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<ul>\n";
        foreach ($tables as $table) {
            echo "<li>$table</li>\n";
        }
        echo "</ul>\n";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>\n";
}
?>