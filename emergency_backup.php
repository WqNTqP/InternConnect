<?php
/**
 * Emergency Database Backup Script
 * Attempts multiple connection methods to recover data from expired FreeSQLDatabase
 */

// FreeSQLDatabase credentials
$configs = [
    [
        'host' => 'sql3.freesqldatabase.com',
        'port' => '3306',
        'username' => 'sql3806785',
        'password' => 'DAl9FGjxvF',
        'database' => 'sql3806785'
    ],
    [
        'host' => 'sql3.freesqldatabase.com',
        'port' => '3306',
        'username' => 'sql3806785',
        'password' => 'DAl9FGjxvF',
        'database' => 'sql3806785'
    ]
];

function attemptConnection($config) {
    try {
        echo "\n🔄 Attempting connection to {$config['host']}:{$config['port']}\n";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 60, // Extended timeout
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
            PDO::ATTR_PERSISTENT => false
        ];
        
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8";
        $conn = new PDO($dsn, $config['username'], $config['password'], $options);
        
        echo "✅ Connection successful!\n";
        return $conn;
        
    } catch(PDOException $e) {
        echo "❌ Connection failed: " . $e->getMessage() . "\n";
        return null;
    }
}

function backupDatabase($conn) {
    try {
        echo "\n📊 Getting table list...\n";
        $stmt = $conn->prepare("SHOW TABLES");
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tables)) {
            echo "❌ No tables found or access denied\n";
            return false;
        }
        
        echo "📋 Found " . count($tables) . " tables:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
        
        // Create backup SQL file
        $backupFile = 'emergency_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $fp = fopen($backupFile, 'w');
        
        if (!$fp) {
            echo "❌ Cannot create backup file\n";
            return false;
        }
        
        fwrite($fp, "-- Emergency Database Backup\n");
        fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- Database: sql3806785\n\n");
        
        foreach ($tables as $table) {
            echo "💾 Backing up table: $table\n";
            
            // Get table structure
            $stmt = $conn->prepare("SHOW CREATE TABLE `$table`");
            $stmt->execute();
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
            
            fwrite($fp, "-- Table structure for `$table`\n");
            fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($fp, $createTable['Create Table'] . ";\n\n");
            
            // Get table data
            $stmt = $conn->prepare("SELECT * FROM `$table`");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                fwrite($fp, "-- Data for table `$table`\n");
                
                foreach ($rows as $row) {
                    $values = array_map(function($value) use ($conn) {
                        return $value === null ? 'NULL' : $conn->quote($value);
                    }, array_values($row));
                    
                    $columns = '`' . implode('`, `', array_keys($row)) . '`';
                    $valuesStr = implode(', ', $values);
                    
                    fwrite($fp, "INSERT INTO `$table` ($columns) VALUES ($valuesStr);\n");
                }
                fwrite($fp, "\n");
            }
        }
        
        fclose($fp);
        echo "✅ Backup completed: $backupFile\n";
        return true;
        
    } catch(Exception $e) {
        echo "❌ Backup failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Main execution
echo "🚨 EMERGENCY DATABASE BACKUP SCRIPT\n";
echo "===================================\n";

foreach ($configs as $i => $config) {
    echo "\n--- Attempt " . ($i + 1) . " ---\n";
    $conn = attemptConnection($config);
    
    if ($conn) {
        if (backupDatabase($conn)) {
            echo "✅ Emergency backup completed successfully!\n";
            exit(0);
        }
    }
}

echo "\n❌ All connection attempts failed.\n";
echo "💡 Your options:\n";
echo "   1. Contact FreeSQLDatabase support for data recovery\n";
echo "   2. Check if you have any other backups\n";
echo "   3. Use your existing attendancetrackernp.sql file (from Nov 13, 2025)\n";

?>