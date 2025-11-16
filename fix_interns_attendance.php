<?php
// Fix the interns_attendance table duplicate entry issue
require_once 'database/database.php';

echo "🔧 FIXING INTERNS_ATTENDANCE TABLE DUPLICATE ENTRY ISSUE\n";
echo "========================================================\n\n";

try {
    $db = new Database();
    
    echo "🔍 Analyzing interns_attendance table...\n";
    
    // Check for duplicate IDs
    $duplicateCheck = $db->conn->query("
        SELECT ID, COUNT(*) as count 
        FROM interns_attendance 
        GROUP BY ID 
        HAVING COUNT(*) > 1
    ");
    $duplicates = $duplicateCheck->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicates) > 0) {
        echo "❌ Found " . count($duplicates) . " duplicate ID entries:\n";
        foreach ($duplicates as $dup) {
            echo "   - ID {$dup['ID']}: {$dup['count']} occurrences\n";
        }
        echo "\n";
        
        // Show the problematic records
        echo "📋 Showing all records with duplicate IDs:\n";
        foreach ($duplicates as $dup) {
            $records = $db->conn->prepare("SELECT * FROM interns_attendance WHERE ID = ?");
            $records->execute([$dup['ID']]);
            $results = $records->fetchAll(PDO::FETCH_ASSOC);
            
            echo "   ID {$dup['ID']}:\n";
            foreach ($results as $record) {
                echo "     - INTERNS_ID: {$record['INTERNS_ID']}, HTE_ID: {$record['HTE_ID']}, DATE: {$record['ON_DATE']}\n";
            }
        }
        echo "\n";
        
        // Fix duplicates by reassigning new IDs
        echo "🔧 Fixing duplicate entries...\n";
        
        // Get the maximum ID currently in use
        $maxIdResult = $db->conn->query("SELECT MAX(ID) as max_id FROM interns_attendance");
        $maxId = $maxIdResult->fetch(PDO::FETCH_ASSOC)['max_id'];
        $newId = $maxId + 1;
        
        foreach ($duplicates as $dup) {
            // Keep the first record with the original ID, update others
            $records = $db->conn->prepare("SELECT * FROM interns_attendance WHERE ID = ? ORDER BY ON_DATE");
            $records->execute([$dup['ID']]);
            $results = $records->fetchAll(PDO::FETCH_ASSOC);
            
            // Skip the first record (keep original ID), update the rest
            for ($i = 1; $i < count($results); $i++) {
                $record = $results[$i];
                $updateStmt = $db->conn->prepare("
                    UPDATE interns_attendance 
                    SET ID = ? 
                    WHERE COORDINATOR_ID = ? AND HTE_ID = ? AND INTERNS_ID = ? AND ON_DATE = ? AND TIMEIN = ? AND TIMEOUT = ? AND ID = ?
                    LIMIT 1
                ");
                
                $success = $updateStmt->execute([
                    $newId,
                    $record['COORDINATOR_ID'],
                    $record['HTE_ID'], 
                    $record['INTERNS_ID'],
                    $record['ON_DATE'],
                    $record['TIMEIN'],
                    $record['TIMEOUT'],
                    $dup['ID']
                ]);
                
                if ($success) {
                    echo "   ✅ Updated duplicate record to new ID: $newId\n";
                    $newId++;
                } else {
                    echo "   ❌ Failed to update record\n";
                }
            }
        }
        echo "\n";
    } else {
        echo "✅ No duplicate entries found\n\n";
    }
    
    // Now try to set up auto-increment again
    echo "🔧 Setting up auto-increment for interns_attendance...\n";
    
    try {
        // Get the new maximum ID
        $maxIdResult = $db->conn->query("SELECT MAX(ID) as max_id FROM interns_attendance");
        $maxId = $maxIdResult->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        $nextAutoIncrement = $maxId + 1;
        
        echo "   📊 Max ID: $maxId, next auto-increment: $nextAutoIncrement\n";
        
        // Set as primary key and auto-increment
        $db->conn->exec("ALTER TABLE interns_attendance ADD PRIMARY KEY (ID)");
        $db->conn->exec("ALTER TABLE interns_attendance MODIFY ID INT NOT NULL AUTO_INCREMENT");
        $db->conn->exec("ALTER TABLE interns_attendance AUTO_INCREMENT = $nextAutoIncrement");
        
        echo "   ✅ Auto-increment set up successfully!\n\n";
        
    } catch (Exception $e) {
        echo "   ❌ Error setting up auto-increment: " . $e->getMessage() . "\n\n";
    }
    
    // Final verification
    echo "🔍 Final verification:\n";
    $descResult = $db->conn->query("DESCRIBE interns_attendance");
    $columns = $descResult->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] == 'ID') {
            $isAutoIncrement = strpos($column['Extra'], 'auto_increment') !== false;
            $isPrimaryKey = $column['Key'] == 'PRI';
            
            $status = $isAutoIncrement && $isPrimaryKey ? '✅ WORKING' : '❌ STILL NEEDS FIX';
            echo "interns_attendance.ID: $status\n";
            break;
        }
    }
    
    echo "\n🎉 INTERNS_ATTENDANCE TABLE FIXED!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>