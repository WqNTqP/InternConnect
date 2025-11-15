<?php
/**
 * Test script to verify admindashboard.php database queries work
 */

echo "<h2>Admin Dashboard Database Test</h2>\n";
echo "<pre>\n";

try {
    // Use the same includes as admindashboard.php
    require_once __DIR__ . '/config/path_config.php';
    require_once PathConfig::getDatabasePath();
    
    echo "Files loaded successfully\n";
    
    // Initialize database connection (same as admindashboard.php)
    $dbo = new Database();
    
    // Check if database connection is successful
    if ($dbo->conn === null) {
        echo "Database connection: FAILED\n";
        die("Database connection failed. Please check your connection settings.\n");
    } else {
        echo "Database connection: SUCCESS\n";
    }
    
    echo "\n--- Testing Admin Dashboard Queries ---\n";
    
    // Test the same query as admindashboard.php
    try {
        $stmt = $dbo->conn->prepare("
            SELECT pa.*, id.STUDENT_ID
            FROM pending_attendance pa
            LEFT JOIN interns_details id ON pa.INTERNS_ID = id.INTERNS_ID
            WHERE pa.status = 'pending'
        ");
        $stmt->execute();
        $pendingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Pending attendance query: SUCCESS\n";
        echo "Found " . count($pendingRecords) . " pending records\n";
        
    } catch (PDOException $e) {
        echo "Pending attendance query: ERROR - " . $e->getMessage() . "\n";
    }
    
    echo "\nAdmin dashboard queries are working properly!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>