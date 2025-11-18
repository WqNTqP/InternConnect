<?php
/**
 * Database Cleanup Script
 * Deletes ALL data from all tables EXCEPT coordinator and past_data tables
 * Use with EXTREME caution - this will permanently delete all data!
 */

require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

// Initialize database connection
try {
    $db = new Database();
    $conn = $db->conn;
    
    if ($conn === null) {
        die("❌ Database connection failed. Please check your connection settings.\n");
    }
    
    echo "🗃️  Database Cleanup Script\n";
    echo "=========================\n\n";
    
    // Confirm before proceeding
    echo "⚠️  WARNING: This will DELETE ALL DATA except from 'coordinator' and 'past_data' tables!\n";
    echo "📊 This action is IRREVERSIBLE!\n\n";
    
    // In command line, we'll skip the confirmation for automation
    // But log what we're doing
    echo "🚀 Starting database cleanup...\n\n";
    
    // Get all table names
    $stmt = $conn->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Tables to preserve (don't delete data from these)
    $preserveTables = [
        'coordinator',
        'past_data'
    ];
    
    // Tables to clean (delete all data but keep structure)
    $tablesToClean = array_diff($allTables, $preserveTables);
    
    echo "📋 Found " . count($allTables) . " tables total\n";
    echo "🔒 Preserving data in: " . implode(", ", $preserveTables) . "\n";
    echo "🧹 Cleaning " . count($tablesToClean) . " tables\n\n";
    
    // Start transaction
    $conn->beginTransaction();
    
    // Disable foreign key checks to avoid constraint issues
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $cleanedTables = [];
    $errorTables = [];
    $totalRowsDeleted = 0;
    
    foreach ($tablesToClean as $table) {
        try {
            // Get row count before deletion
            $countStmt = $conn->prepare("SELECT COUNT(*) FROM `$table`");
            $countStmt->execute();
            $rowCount = $countStmt->fetchColumn();
            
            if ($rowCount > 0) {
                // Delete all data from table
                $deleteStmt = $conn->prepare("DELETE FROM `$table`");
                $deleteStmt->execute();
                
                // Reset auto increment to 1
                $conn->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
                
                $cleanedTables[] = $table;
                $totalRowsDeleted += $rowCount;
                
                echo "✅ Cleaned '$table' - Deleted $rowCount rows\n";
            } else {
                echo "⚪ '$table' - Already empty\n";
            }
            
        } catch (Exception $e) {
            $errorTables[] = [
                'table' => $table,
                'error' => $e->getMessage()
            ];
            echo "❌ Error cleaning '$table': " . $e->getMessage() . "\n";
        }
    }
    
    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Commit transaction
    $conn->commit();
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎯 CLEANUP SUMMARY\n";
    echo str_repeat("=", 50) . "\n";
    echo "✅ Successfully cleaned: " . count($cleanedTables) . " tables\n";
    echo "❌ Errors encountered: " . count($errorTables) . " tables\n";
    echo "🗑️  Total rows deleted: " . number_format($totalRowsDeleted) . "\n";
    echo "🔒 Preserved tables: " . implode(", ", $preserveTables) . "\n\n";
    
    if (!empty($cleanedTables)) {
        echo "📋 Successfully cleaned tables:\n";
        foreach ($cleanedTables as $table) {
            echo "   - $table\n";
        }
        echo "\n";
    }
    
    if (!empty($errorTables)) {
        echo "⚠️  Tables with errors:\n";
        foreach ($errorTables as $error) {
            echo "   - {$error['table']}: {$error['error']}\n";
        }
        echo "\n";
    }
    
    // Verify preserved tables still have data
    echo "🔍 VERIFICATION - Preserved Tables:\n";
    foreach ($preserveTables as $table) {
        try {
            $countStmt = $conn->prepare("SELECT COUNT(*) FROM `$table`");
            $countStmt->execute();
            $rowCount = $countStmt->fetchColumn();
            echo "   ✅ '$table' - $rowCount rows preserved\n";
        } catch (Exception $e) {
            echo "   ❌ '$table' - Error checking: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✨ Database cleanup completed!\n";
    echo "🔄 You can now safely test with fresh data.\n";
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "🔄 Database changes have been rolled back.\n";
    exit(1);
}
?>