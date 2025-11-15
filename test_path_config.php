<?php
/**
 * Test script to verify that the path configuration works correctly
 * on both localhost and live environments
 */

require_once __DIR__ . '/config/path_config.php';

echo "<h2>Path Configuration Test</h2>\n";
echo "<pre>\n";

// Display debug information
$debug = PathConfig::debug();
foreach ($debug as $key => $value) {
    echo "$key: $value\n";
}

echo "\n--- Testing Database Connection ---\n";

// Test database connection
try {
    if (file_exists(PathConfig::getDatabasePath())) {
        echo "Database file found at: " . PathConfig::getDatabasePath() . "\n";
        
        require_once PathConfig::getDatabasePath();
        $db = new Database();
        
        if ($db->conn) {
            echo "Database connection: SUCCESS\n";
            echo "Connected to database successfully!\n";
        } else {
            echo "Database connection: FAILED - Connection object is null\n";
        }
    } else {
        echo "Database file NOT FOUND at: " . PathConfig::getDatabasePath() . "\n";
    }
} catch (Exception $e) {
    echo "Database connection: ERROR - " . $e->getMessage() . "\n";
}

echo "\n--- Testing Other File Paths ---\n";

$testPaths = [
    'coordinator.php' => '/database/coordinator.php',
    'admin.php' => '/database/admin.php',
    'student.php' => '/database/student.php'
];

foreach ($testPaths as $name => $path) {
    $fullPath = PathConfig::getProjectPath($path);
    echo "$name: " . (file_exists($fullPath) ? "EXISTS" : "NOT FOUND") . " at $fullPath\n";
}

echo "</pre>\n";
?>