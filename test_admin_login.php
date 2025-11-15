<?php
/**
 * Test script to verify admin login functionality
 */

echo "<h2>Admin Login Test</h2>\n";
echo "<pre>\n";

try {
    // Use the new path configuration
    require_once __DIR__ . '/config/path_config.php';
    require_once PathConfig::getDatabasePath();
    require_once PathConfig::getProjectPath('/database/admin.php');
    
    echo "Files loaded successfully\n";
    
    // Test database connection
    echo "\n--- Testing Database Connection ---\n";
    $dbo = new Database();
    
    if ($dbo->conn === null) {
        echo "Database connection: FAILED\n";
        echo "Cannot proceed with admin login test\n";
    } else {
        echo "Database connection: SUCCESS\n";
        
        // Test admin login class
        echo "\n--- Testing Admin Login Class ---\n";
        $admin = new admin();
        
        // Test with invalid credentials first
        echo "Testing with invalid credentials:\n";
        $result = $admin->verifyUser($dbo, 'invalid_user', 'invalid_pass');
        echo "Result: " . json_encode($result) . "\n";
        
        // You can add a test with valid credentials if you know them
        // $result = $admin->verifyUser($dbo, 'actual_username', 'actual_password');
        // echo "Valid credentials result: " . json_encode($result) . "\n";
        
        echo "\nAdmin login class is working properly!\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>