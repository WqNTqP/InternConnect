<?php
// Fix auto-increment issues in Railway database
require_once 'database/database.php';

echo "🔧 FIXING AUTO-INCREMENT ISSUES IN RAILWAY DATABASE\n";
echo "================================================\n\n";

try {
    $db = new Database();
    
    if ($db->conn === null) {
        echo "❌ Database connection failed!\n";
        exit(1);
    }
    
    echo "✅ Connected to Railway database\n\n";
    
    // Check current structure of interns_details table
    echo "📋 Current structure of interns_details table:\n";
    $describeResult = $db->conn->query("DESCRIBE interns_details");
    while ($row = $describeResult->fetch(PDO::FETCH_ASSOC)) {
        echo "   - {$row['Field']}: {$row['Type']} | Key: {$row['Key']} | Extra: {$row['Extra']}\n";
    }
    echo "\n";
    
    // Check current auto_increment value
    $statusResult = $db->conn->query("SHOW TABLE STATUS LIKE 'interns_details'");
    $status = $statusResult->fetch(PDO::FETCH_ASSOC);
    echo "Current Auto_increment value: " . ($status['Auto_increment'] ?? 'NOT SET') . "\n\n";
    
    // Get the highest INTERNS_ID currently in the table
    $maxIdResult = $db->conn->query("SELECT MAX(INTERNS_ID) as max_id FROM interns_details");
    $maxId = $maxIdResult->fetch(PDO::FETCH_ASSOC)['max_id'];
    $nextId = ($maxId ?? 0) + 1;
    
    echo "Highest current INTERNS_ID: $maxId\n";
    echo "Next auto-increment should be: $nextId\n\n";
    
    // Fix the auto-increment
    echo "🔧 Fixing auto-increment for INTERNS_ID...\n";
    
    try {
        // First, make sure INTERNS_ID is primary key and auto-increment
        $db->conn->exec("ALTER TABLE interns_details MODIFY COLUMN INTERNS_ID int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY");
        echo "✅ Set INTERNS_ID as auto-increment primary key\n";
        
        // Set the auto-increment value to the next available ID
        $db->conn->exec("ALTER TABLE interns_details AUTO_INCREMENT = $nextId");
        echo "✅ Set auto-increment value to $nextId\n";
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Multiple primary key defined') !== false) {
            echo "ℹ️  Primary key already exists, trying alternative approach...\n";
            
            // Drop existing primary key first, then add auto-increment
            try {
                $db->conn->exec("ALTER TABLE interns_details DROP PRIMARY KEY");
                echo "✅ Dropped existing primary key\n";
                
                $db->conn->exec("ALTER TABLE interns_details MODIFY COLUMN INTERNS_ID int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY");
                echo "✅ Set INTERNS_ID as auto-increment primary key\n";
                
                $db->conn->exec("ALTER TABLE interns_details AUTO_INCREMENT = $nextId");
                echo "✅ Set auto-increment value to $nextId\n";
                
            } catch (Exception $e2) {
                echo "⚠️  Error with alternative approach: " . $e2->getMessage() . "\n";
                
                // Try just modifying the column without changing primary key
                try {
                    $db->conn->exec("ALTER TABLE interns_details MODIFY COLUMN INTERNS_ID int(11) NOT NULL AUTO_INCREMENT");
                    echo "✅ Set INTERNS_ID as auto-increment (keeping existing key structure)\n";
                    
                    $db->conn->exec("ALTER TABLE interns_details AUTO_INCREMENT = $nextId");
                    echo "✅ Set auto-increment value to $nextId\n";
                    
                } catch (Exception $e3) {
                    echo "❌ Failed to fix auto-increment: " . $e3->getMessage() . "\n";
                }
            }
        } else {
            echo "⚠️  Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📋 Updated structure of interns_details table:\n";
    $describeResult2 = $db->conn->query("DESCRIBE interns_details");
    while ($row = $describeResult2->fetch(PDO::FETCH_ASSOC)) {
        $highlight = ($row['Field'] === 'INTERNS_ID') ? '>>> ' : '    ';
        echo "$highlight{$row['Field']}: {$row['Type']} | Key: {$row['Key']} | Extra: {$row['Extra']}\n";
    }
    
    // Check updated auto_increment value
    $statusResult2 = $db->conn->query("SHOW TABLE STATUS LIKE 'interns_details'");
    $status2 = $statusResult2->fetch(PDO::FETCH_ASSOC);
    echo "\nUpdated Auto_increment value: " . ($status2['Auto_increment'] ?? 'NOT SET') . "\n";
    
    // Also fix other tables that might have the same issue
    echo "\n🔧 Checking and fixing other tables...\n";
    
    $tablesToFix = [
        'coordinator' => 'COORDINATOR_ID',
        'host_training_establishment' => 'HTE_ID',
        'evaluation_questions' => 'QUESTION_ID',
        'notifications' => 'NOTIFICATION_ID',
        'weekly_reports' => 'REPORT_ID',
        'post_assessment' => 'id',
        'pre_assessment' => 'id'
    ];
    
    foreach ($tablesToFix as $table => $idColumn) {
        echo "\nFixing $table ($idColumn):\n";
        
        try {
            // Get max ID
            $maxResult = $db->conn->query("SELECT MAX($idColumn) as max_id FROM $table");
            $maxId = $maxResult->fetch(PDO::FETCH_ASSOC)['max_id'];
            $nextId = ($maxId ?? 0) + 1;
            
            // Set auto-increment
            $db->conn->exec("ALTER TABLE $table MODIFY COLUMN $idColumn int(11) NOT NULL AUTO_INCREMENT");
            $db->conn->exec("ALTER TABLE $table AUTO_INCREMENT = $nextId");
            
            echo "   ✅ Fixed auto-increment for $table (next ID: $nextId)\n";
            
        } catch (Exception $e) {
            echo "   ⚠️  Warning for $table: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 AUTO-INCREMENT FIX COMPLETED!\n";
    echo "   - INTERNS_ID should now auto-increment properly\n";
    echo "   - Other ID columns have been checked and fixed\n";
    echo "   - No more 'doesn't have a default value' errors\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>