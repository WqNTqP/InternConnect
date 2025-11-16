<?php
// Check which tables have ID columns and compare with original backup
require_once 'database/database.php';

echo "🔍 CHECKING ID COLUMNS ACROSS ALL TABLES\n";
echo "=========================================\n\n";

try {
    $db = new Database();
    
    if ($db->conn === null) {
        echo "❌ Database connection failed!\n";
        exit(1);
    }
    
    // Get all tables
    $tablesResult = $db->conn->query("SHOW TABLES");
    $tables = $tablesResult->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Analyzing " . count($tables) . " tables for ID columns:\n\n";
    
    $tablesWithIds = [];
    $tablesWithoutIds = [];
    $autoIncrementTables = [];
    
    foreach ($tables as $table) {
        echo "🔍 Table: $table\n";
        
        // Check columns
        $columnsResult = $db->conn->query("DESCRIBE `$table`");
        $columns = $columnsResult->fetchAll(PDO::FETCH_ASSOC);
        
        $hasIdColumn = false;
        $idColumnInfo = null;
        
        foreach ($columns as $column) {
            $columnName = strtolower($column['Field']);
            if ($columnName === 'id' || strpos($columnName, '_id') !== false) {
                $hasIdColumn = true;
                $idColumnInfo = $column;
                
                echo "   ✅ ID Column: {$column['Field']}\n";
                echo "      Type: {$column['Type']}\n";
                echo "      Key: {$column['Key']}\n";
                echo "      Extra: {$column['Extra']}\n";
                
                if (strpos($column['Extra'], 'auto_increment') !== false) {
                    $autoIncrementTables[] = $table;
                    echo "      🔄 AUTO_INCREMENT: YES\n";
                } else {
                    echo "      ⚠️  AUTO_INCREMENT: NO\n";
                }
                break;
            }
        }
        
        if ($hasIdColumn) {
            $tablesWithIds[] = $table;
        } else {
            $tablesWithoutIds[] = $table;
            echo "   ❌ NO ID COLUMN FOUND\n";
        }
        
        echo "\n";
    }
    
    // Now check what the original backup had
    echo "📂 COMPARING WITH ORIGINAL BACKUP:\n";
    echo "=================================\n\n";
    
    $sqlContent = file_get_contents('database/sql3806785.sql');
    
    // Extract CREATE TABLE statements
    preg_match_all('/CREATE TABLE `([^`]+)`[^;]+;/s', $sqlContent, $matches, PREG_SET_ORDER);
    
    echo "🔍 Original backup analysis:\n\n";
    
    foreach ($matches as $match) {
        $tableName = $match[1];
        $createStatement = $match[0];
        
        echo "📋 Original table: $tableName\n";
        
        // Check if it had ID columns originally
        $hasOriginalId = false;
        if (preg_match('/`([^`]*id[^`]*)`\s+int/i', $createStatement, $idMatch)) {
            $originalIdColumn = $idMatch[1];
            echo "   ✅ Original ID column: $originalIdColumn\n";
            
            // Check if it was auto_increment
            if (preg_match('/`' . preg_quote($originalIdColumn) . '`[^,]+AUTO_INCREMENT/i', $createStatement)) {
                echo "   🔄 Original AUTO_INCREMENT: YES\n";
            } else {
                echo "   ⚠️  Original AUTO_INCREMENT: NO\n";
            }
            $hasOriginalId = true;
        } else {
            echo "   ❌ Original: NO ID COLUMN\n";
        }
        
        echo "\n";
    }
    
    echo "\n📊 SUMMARY:\n";
    echo "===========\n";
    echo "Current tables with ID columns: " . count($tablesWithIds) . "/" . count($tables) . "\n";
    echo "Current tables with AUTO_INCREMENT: " . count($autoIncrementTables) . "/" . count($tables) . "\n";
    echo "Current tables WITHOUT ID columns: " . count($tablesWithoutIds) . "\n";
    
    if (count($tablesWithoutIds) > 0) {
        echo "\nTables without ID columns:\n";
        foreach ($tablesWithoutIds as $table) {
            echo "   - $table\n";
        }
    }
    
    echo "\nTables with AUTO_INCREMENT:\n";
    foreach ($autoIncrementTables as $table) {
        echo "   ✅ $table\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>