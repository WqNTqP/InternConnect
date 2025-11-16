<?php
// List all tables in Railway database
require_once 'database/database.php';

try {
    $db = new Database();
    
    if ($db->conn === null) {
        echo "❌ Database connection failed!\n";
        exit(1);
    }
    
    echo "✅ Connected to Railway database\n\n";
    
    // Get all tables
    $tablesQuery = $db->conn->query("SHOW TABLES");
    $tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Tables in Railway database (" . count($tables) . " total):\n";
    foreach ($tables as $index => $table) {
        echo ($index + 1) . ". $table\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>