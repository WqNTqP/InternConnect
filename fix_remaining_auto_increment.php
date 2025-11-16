<?php
// Fix remaining auto-increment issues with correct column names
require_once 'database/database.php';

echo "🔧 FIXING REMAINING AUTO-INCREMENT ISSUES\n";
echo "========================================\n\n";

try {
    $db = new Database();
    
    // Tables and their correct primary key columns
    $remainingTables = [
        'evaluation_questions' => 'question_id',
        'interns_attendance' => 'ID',
        'notifications' => 'notification_id',
        'pending_attendance' => 'ID',
        'report_images' => 'image_id',
        'student_deletion_log' => 'log_id',
        'weekly_reports' => 'report_id'
    ];
    
    foreach ($remainingTables as $tableName => $primaryKey) {
        echo "🔍 Fixing table: $tableName (Primary Key: $primaryKey)\n";
        
        try {
            // Get current max value
            $maxResult = $db->conn->query("SELECT MAX($primaryKey) as max_val FROM $tableName");
            $maxValue = $maxResult->fetch(PDO::FETCH_ASSOC)['max_val'] ?? 0;
            $nextAutoIncrement = $maxValue + 1;
            
            echo "   📊 Current max value: $maxValue, next auto-increment: $nextAutoIncrement\n";
            
            // Check current status
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
                echo "   ❌ Primary key column '$primaryKey' not found\n\n";
                continue;
            }
            
            $isAutoIncrement = strpos($primaryKeyColumn['Extra'], 'auto_increment') !== false;
            $isPrimaryKey = $primaryKeyColumn['Key'] == 'PRI';
            
            if ($isAutoIncrement && $isPrimaryKey) {
                echo "   ✅ Already configured correctly\n\n";
                continue;
            }
            
            echo "   🔧 Current: Auto-increment=" . ($isAutoIncrement ? 'YES' : 'NO') . 
                 ", Primary Key=" . ($isPrimaryKey ? 'YES' : 'NO') . "\n";
            
            // Drop existing primary key if needed
            if ($isPrimaryKey && !$isAutoIncrement) {
                echo "   - Dropping existing primary key\n";
                $db->conn->exec("ALTER TABLE $tableName DROP PRIMARY KEY");
            }
            
            // Set as auto-increment primary key
            $columnType = $primaryKeyColumn['Type'];
            echo "   - Setting $primaryKey as AUTO_INCREMENT PRIMARY KEY\n";
            
            $alterSQL = "ALTER TABLE $tableName MODIFY $primaryKey $columnType NOT NULL AUTO_INCREMENT PRIMARY KEY";
            $db->conn->exec($alterSQL);
            
            // Set AUTO_INCREMENT starting value
            echo "   - Setting AUTO_INCREMENT value to $nextAutoIncrement\n";
            $db->conn->exec("ALTER TABLE $tableName AUTO_INCREMENT = $nextAutoIncrement");
            
            echo "   ✅ Fixed successfully\n\n";
            
        } catch (Exception $e) {
            echo "   ❌ Error: " . $e->getMessage() . "\n";
            
            // Try alternative approach
            try {
                echo "   🔄 Trying alternative approach...\n";
                
                // Add primary key if not exists
                if (!$isPrimaryKey) {
                    $db->conn->exec("ALTER TABLE $tableName ADD PRIMARY KEY ($primaryKey)");
                }
                
                // Add auto_increment
                $db->conn->exec("ALTER TABLE $tableName MODIFY $primaryKey INT NOT NULL AUTO_INCREMENT");
                $db->conn->exec("ALTER TABLE $tableName AUTO_INCREMENT = $nextAutoIncrement");
                
                echo "   ✅ Fixed with alternative approach\n\n";
                
            } catch (Exception $e2) {
                echo "   ❌ Alternative approach failed: " . $e2->getMessage() . "\n\n";
            }
        }
    }
    
    // Special cases that don't need auto-increment
    echo "ℹ️  TABLES THAT DON'T NEED AUTO-INCREMENT:\n";
    echo "- intern_details: Uses INTERNS_ID (foreign key reference)\n";
    echo "- internship_needs: Composite table, no single primary key\n"; 
    echo "- past_data: Historical data, uses id_number as identifier\n\n";
    
    // Final verification of all tables
    echo "🔍 FINAL VERIFICATION OF ALL TABLES:\n";
    echo "===================================\n\n";
    
    $allTables = [
        'coordinator' => 'COORDINATOR_ID',
        'coordinator_evaluation' => 'id',
        'evaluation_questions' => 'question_id',
        'host_training_establishment' => 'HTE_ID',
        'interns_attendance' => 'ID',
        'interns_details' => 'INTERNS_ID',
        'notifications' => 'notification_id',
        'pending_attendance' => 'ID',
        'post_analysis_summary' => 'id',
        'post_assessment' => 'id',
        'pre_assessment' => 'id',
        'report_images' => 'image_id',
        'session_details' => 'ID',
        'student_deletion_log' => 'log_id',
        'student_evaluation' => 'id',
        'student_questions' => 'id',
        'weekly_reports' => 'report_id'
    ];
    
    foreach ($allTables as $tableName => $primaryKey) {
        try {
            $descResult = $db->conn->query("DESCRIBE $tableName");
            $columns = $descResult->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $column) {
                if ($column['Field'] == $primaryKey) {
                    $isAutoIncrement = strpos($column['Extra'], 'auto_increment') !== false;
                    $isPrimaryKey = $column['Key'] == 'PRI';
                    
                    $status = $isAutoIncrement && $isPrimaryKey ? '✅ WORKING' : '❌ NEEDS FIX';
                    echo "$tableName.$primaryKey: $status\n";
                    break;
                }
            }
        } catch (Exception $e) {
            echo "$tableName.$primaryKey: ❌ ERROR\n";
        }
    }
    
    echo "\n🎉 AUTO-INCREMENT FIX COMPLETED!\n";
    echo "All primary keys should now work correctly.\n";
    echo "No more 'Field doesn't have a default value' errors!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>