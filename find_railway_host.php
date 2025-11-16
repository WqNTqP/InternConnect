<?php
/**
 * Railway Connection Test with Known Credentials
 */

echo "=== TESTING RAILWAY CONNECTION ===\n\n";

// Your known credentials
$username = 'root';
$password = 'LYeUTqrnaDxpSAdWiirrGhFAcVVyNMGJ';

// Common Railway host patterns to try
$possibleHosts = [
    'containers-us-west-56.railway.app',
    'containers-us-west-55.railway.app', 
    'containers-us-west-57.railway.app',
    'containers-us-west-58.railway.app',
    'containers-us-east-56.railway.app',
    'roundhouse.proxy.rlwy.net',  // Alternative Railway hostname
];

// Common database names
$possibleDatabases = ['railway', 'mysql', 'main'];

echo "Testing Railway connection with:\n";
echo "Username: $username\n";
echo "Password: " . str_repeat('*', strlen($password)) . "\n\n";

foreach ($possibleHosts as $host) {
    echo "Testing host: $host\n";
    
    foreach ($possibleDatabases as $database) {
        try {
            $dsn = "mysql:host=$host;port=3306;dbname=$database;charset=utf8";
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10];
            
            $conn = new PDO($dsn, $username, $password, $options);
            
            echo "  ✅ SUCCESS with database: $database\n";
            echo "  🎉 WORKING CREDENTIALS FOUND!\n\n";
            
            echo "=== UPDATE IMPORT SCRIPT ===\n";
            echo "Host: $host\n";
            echo "Database: $database\n\n";
            
            // Test a simple query
            $stmt = $conn->prepare("SELECT 1 as test");
            $stmt->execute();
            echo "✅ Query test passed\n\n";
            
            echo "Ready to import! Use these credentials:\n";
            echo "Host: $host\n";
            echo "Username: $username\n";
            echo "Database: $database\n";
            
            exit(0);
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                echo "  🔍 Host works, access denied for database: $database\n";
            } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
                echo "  🔍 Host works, database '$database' doesn't exist\n";
            } else {
                echo "  ❌ Failed: " . substr($e->getMessage(), 0, 50) . "...\n";
            }
        }
    }
    echo "\n";
}

echo "❌ No working connection found.\n";
echo "Please check the Variables tab in Railway for exact MYSQL_HOST and MYSQL_DATABASE values.\n";
?>