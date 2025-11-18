<?php
/**
 * Safe Database Cleanup with Backup Option
 * Creates backup before cleaning database
 */

require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

function createBackup($conn) {
    $backupFile = __DIR__ . '/database/backup_before_cleanup_' . date('Y-m-d_H-i-s') . '.sql';
    
    echo "💾 Creating backup before cleanup...\n";
    echo "📁 Backup file: " . basename($backupFile) . "\n";
    
    // Get database name from config
    $dbName = 'internconnect'; // Adjust if different
    
    // Create backup using mysqldump equivalent
    $tables = [];
    $stmt = $conn->query("SHOW TABLES");
    while ($table = $stmt->fetchColumn()) {
        $tables[] = $table;
    }
    
    $backup = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- Before cleanup operation\n\n";
    
    foreach ($tables as $table) {
        // Get table structure
        $backup .= "-- Table structure for table `$table`\n";
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        
        $createStmt = $conn->query("SHOW CREATE TABLE `$table`");
        $createRow = $createStmt->fetch(PDO::FETCH_NUM);
        $backup .= $createRow[1] . ";\n\n";
        
        // Get table data (only for coordinator and past_data to save space)
        if (in_array($table, ['coordinator', 'past_data'])) {
            $backup .= "-- Dumping data for table `$table`\n";
            $dataStmt = $conn->query("SELECT * FROM `$table`");
            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_keys($row);
                $values = array_map(function($val) use ($conn) {
                    return $val === null ? 'NULL' : $conn->quote($val);
                }, array_values($row));
                
                $backup .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
            }
            $backup .= "\n";
        }
    }
    
    // Write backup to file
    if (file_put_contents($backupFile, $backup)) {
        echo "✅ Backup created successfully!\n\n";
        return $backupFile;
    } else {
        throw new Exception("Failed to create backup file");
    }
}

// Main execution
try {
    $db = new Database();
    $conn = $db->conn;
    
    if ($conn === null) {
        die("❌ Database connection failed.\n");
    }
    
    echo "🛡️  SAFE Database Cleanup with Backup\n";
    echo "====================================\n\n";
    
    // Create backup first
    $backupFile = createBackup($conn);
    
    // Now proceed with cleanup
    echo "🧹 Starting database cleanup...\n";
    
    // Get all tables
    $stmt = $conn->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $preserveTables = ['coordinator', 'past_data'];
    $tablesToClean = array_diff($allTables, $preserveTables);
    
    // Start cleanup
    $conn->beginTransaction();
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $totalDeleted = 0;
    
    foreach ($tablesToClean as $table) {
        try {
            $countStmt = $conn->prepare("SELECT COUNT(*) FROM `$table`");
            $countStmt->execute();
            $rowCount = $countStmt->fetchColumn();
            
            if ($rowCount > 0) {
                $conn->exec("DELETE FROM `$table`");
                $conn->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
                $totalDeleted += $rowCount;
                echo "✅ $table - $rowCount rows deleted\n";
            }
        } catch (Exception $e) {
            echo "❌ $table - Error: " . $e->getMessage() . "\n";
        }
    }
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    $conn->commit();
    
    echo "\n🎉 Cleanup completed!\n";
    echo "📊 Total rows deleted: " . number_format($totalDeleted) . "\n";
    echo "💾 Backup saved to: database/" . basename($backupFile) . "\n";
    echo "🔒 Preserved tables: " . implode(", ", $preserveTables) . "\n";
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>