<?php
// Fix all auto-increment issues in Railway database
require_once 'database/database.php';

echo "🔧 FIXING AUTO-INCREMENT ISSUES IN RAILWAY DATABASE\n";
echo "===================================================\n\n";

try {
    $db = new Database();
    
    if ($db->conn === null) {
        echo "❌ Database connection failed!\n";
        exit(1);
    }
    
    echo "✅ Connected to Railway database\n\n";
    
    // Get all tables
    $tablesResult = $db->conn->query("SHOW TABLES");
    $tables = $tablesResult->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Checking and fixing auto-increment for all tables...\n\n";
    
    // Define tables and their primary key columns that should be auto-increment
    $autoIncrementTables = [
        'coordinator' => 'COORDINATOR_ID',
        'coordinator_evaluation' => 'id',
        'evaluation_questions' => 'id',
        'host_training_establishment' => 'HTE_ID',
        'intern_details' => 'INTERN_ID',
        'interns_attendance' => 'ATTENDANCE_ID',
        'interns_details' => 'INTERNS_ID',
        'internship_needs' => 'NEEDS_ID',
        'notifications' => 'id',
        'past_data' => 'id',
        'pending_attendance' => 'id',
        'post_analysis_summary' => 'id',
        'post_assessment' => 'id',
        'pre_assessment' => 'id',
        'report_images' => 'id',
        'session_details' => 'ID',
        'student_deletion_log' => 'id',
        'student_evaluation' => 'id',
        'student_questions' => 'id',
        'weekly_reports' => 'REPORT_ID'
    ];
    
    foreach ($autoIncrementTables as $tableName => $primaryKey) {
        if (!in_array($tableName, $tables)) {
            echo "⚠️  Table '$tableName' not found, skipping...\n";
            continue;
        }
        
        echo "🔍 Checking table: $tableName (Primary Key: $primaryKey)\n";
        
        try {
            // Check current table structure
            $descResult = $db->conn->query("DESCRIBE $tableName");
            $columns = $descResult->fetchAll(PDO::FETCH_ASSOC);
            
            $primaryKeyColumn = null;
            foreach ($columns as $column) {
                if ($column['Field'] == $primaryKey) {
                    $primaryKeyColumn = $column;
                    break;
                }
            }
            
            if (!$primaryKeyColumn) {
                echo "   ❌ Primary key column '$primaryKey' not found\n";
                continue;
            }
            
            $isAutoIncrement = strpos($primaryKeyColumn['Extra'], 'auto_increment') !== false;
            $isPrimaryKey = $primaryKeyColumn['Key'] == 'PRI';
            
            echo "   Current status: Auto-increment=" . ($isAutoIncrement ? 'YES' : 'NO') . 
                 ", Primary Key=" . ($isPrimaryKey ? 'YES' : 'NO') . "\n";
            
            if ($isAutoIncrement && $isPrimaryKey) {
                echo "   ✅ Already configured correctly\n\n";
                continue;
            }
            
            // Get current max value for the next AUTO_INCREMENT
            $maxResult = $db->conn->query("SELECT MAX($primaryKey) as max_val FROM $tableName");
            $maxValue = $maxResult->fetch(PDO::FETCH_ASSOC)['max_val'] ?? 0;
            $nextAutoIncrement = $maxValue + 1;
            
            echo "   🔧 Fixing auto-increment (next value will be: $nextAutoIncrement)\n";
            
            // Drop existing primary key if it exists but isn't auto-increment
            if ($isPrimaryKey && !$isAutoIncrement) {
                echo "   - Dropping existing primary key\n";
                $db->conn->exec("ALTER TABLE $tableName DROP PRIMARY KEY");
            }
            
            // Modify column to be auto-increment and primary key
            $columnType = $primaryKeyColumn['Type'];
            echo "   - Setting $primaryKey as AUTO_INCREMENT PRIMARY KEY\n";
            
            $alterSQL = "ALTER TABLE $tableName MODIFY $primaryKey $columnType NOT NULL AUTO_INCREMENT PRIMARY KEY";
            $db->conn->exec($alterSQL);
            
            // Set the AUTO_INCREMENT starting value
            echo "   - Setting AUTO_INCREMENT value to $nextAutoIncrement\n";
            $db->conn->exec("ALTER TABLE $tableName AUTO_INCREMENT = $nextAutoIncrement");
            
            echo "   ✅ Fixed successfully\n\n";
            
        } catch (Exception $e) {
            echo "   ❌ Error fixing $tableName: " . $e->getMessage() . "\n\n";
            
            // Try alternative approach for tables that might have issues
            try {
                echo "   🔄 Trying alternative approach...\n";
                
                // First ensure it's a primary key
                if (!$isPrimaryKey) {
                    $db->conn->exec("ALTER TABLE $tableName ADD PRIMARY KEY ($primaryKey)");
                }
                
                // Then modify to add auto_increment
                $db->conn->exec("ALTER TABLE $tableName MODIFY $primaryKey INT NOT NULL AUTO_INCREMENT");
                
                // Set auto_increment value
                $db->conn->exec("ALTER TABLE $tableName AUTO_INCREMENT = $nextAutoIncrement");
                
                echo "   ✅ Fixed with alternative approach\n\n";
                
            } catch (Exception $e2) {
                echo "   ❌ Alternative approach also failed: " . $e2->getMessage() . "\n\n";
            }
        }
    }
    
    // Final verification
    echo "🔍 FINAL VERIFICATION\n";
    echo "==================\n\n";
    
    foreach ($autoIncrementTables as $tableName => $primaryKey) {
        if (!in_array($tableName, $tables)) continue;
        
        try {
            $descResult = $db->conn->query("DESCRIBE $tableName");
            $columns = $descResult->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $column) {
                if ($column['Field'] == $primaryKey) {
                    $isAutoIncrement = strpos($column['Extra'], 'auto_increment') !== false;
                    $isPrimaryKey = $column['Key'] == 'PRI';
                    
                    $status = $isAutoIncrement && $isPrimaryKey ? '✅ FIXED' : '❌ NEEDS MANUAL FIX';
                    echo "$tableName.$primaryKey: $status\n";
                    break;
                }
            }
        } catch (Exception $e) {
            echo "$tableName.$primaryKey: ❌ ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 AUTO-INCREMENT FIX COMPLETED!\n";
    echo "All primary key columns should now be properly configured.\n";
    echo "You should no longer see 'Field doesn't have a default value' errors.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>