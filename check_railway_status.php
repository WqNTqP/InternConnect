<?php
/**
 * Railway Database Status Check and MOA Column Addition
 */

$credentials = [
    'host' => 'mainline.proxy.rlwy.net',
    'port' => 31782,
    'username' => 'root',
    'password' => 'LYeUTqrnaDxpSAdWiirrGhFAcVVyNMGJ',
    'database' => 'railway'
];

echo "=== RAILWAY DATABASE STATUS CHECK ===\n\n";

try {
    $dsn = "mysql:host={$credentials['host']};port={$credentials['port']};dbname={$credentials['database']};charset=utf8";
    $pdo = new PDO($dsn, $credentials['username'], $credentials['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    echo "✅ Connected to Railway database\n\n";
    
    // Check current tables
    $stmt = $pdo->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Current tables (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        echo "• $table\n";
    }
    echo "\n";
    
    // Check key tables data
    $keyTables = ['coordinator', 'host_training_establishment', 'student_evaluation'];
    foreach ($keyTables as $table) {
        if (in_array($table, $tables)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "📊 $table: $count records\n";
        }
    }
    
    echo "\n=== CHECKING MOA COLUMNS ===\n";
    
    // Check if MOA columns exist in HTE table
    if (in_array('host_training_establishment', $tables)) {
        $stmt = $pdo->prepare("DESCRIBE host_training_establishment");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $moaColumns = ['MOA_FILE_URL', 'MOA_PUBLIC_ID', 'MOA_START_DATE', 'MOA_END_DATE', 'MOA_UPLOAD_DATE'];
        $existingMoaColumns = [];
        
        foreach ($columns as $column) {
            if (in_array($column['Field'], $moaColumns)) {
                $existingMoaColumns[] = $column['Field'];
            }
        }
        
        echo "Current HTE columns: " . count($columns) . "\n";
        echo "MOA columns found: " . count($existingMoaColumns) . "/5\n";
        
        if (count($existingMoaColumns) < 5) {
            echo "Missing MOA columns: " . implode(', ', array_diff($moaColumns, $existingMoaColumns)) . "\n\n";
            
            echo "🔧 Adding missing MOA columns...\n";
            
            $alterStatements = [
                "ALTER TABLE `host_training_establishment` ADD COLUMN `MOA_FILE_URL` varchar(500) DEFAULT NULL",
                "ALTER TABLE `host_training_establishment` ADD COLUMN `MOA_PUBLIC_ID` varchar(255) DEFAULT NULL", 
                "ALTER TABLE `host_training_establishment` ADD COLUMN `MOA_START_DATE` date DEFAULT NULL",
                "ALTER TABLE `host_training_establishment` ADD COLUMN `MOA_END_DATE` date DEFAULT NULL",
                "ALTER TABLE `host_training_establishment` ADD COLUMN `MOA_UPLOAD_DATE` timestamp NULL DEFAULT NULL"
            ];
            
            foreach ($alterStatements as $sql) {
                try {
                    $pdo->exec($sql);
                    echo "✅ Added: " . preg_replace('/.*ADD COLUMN `([^`]+)`.*/', '$1', $sql) . "\n";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                        echo "⚠️ Already exists: " . preg_replace('/.*ADD COLUMN `([^`]+)`.*/', '$1', $sql) . "\n";
                    } else {
                        echo "❌ Error adding: " . preg_replace('/.*ADD COLUMN `([^`]+)`.*/', '$1', $sql) . " - " . $e->getMessage() . "\n";
                    }
                }
            }
            
            echo "\n✅ MOA columns addition completed!\n";
        } else {
            echo "✅ All MOA columns already exist!\n";
        }
    }
    
    echo "\n=== FINAL STATUS ===\n";
    
    // Re-check MOA columns
    $stmt = $pdo->prepare("DESCRIBE host_training_establishment");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $moaColumns = [];
    foreach ($columns as $column) {
        if (strpos($column['Field'], 'MOA_') === 0) {
            $moaColumns[] = $column['Field'];
        }
    }
    
    echo "✅ HTE table MOA columns (" . count($moaColumns) . "/5):\n";
    foreach ($moaColumns as $col) {
        echo "• $col\n";
    }
    
    if (count($moaColumns) == 5) {
        echo "\n🎉 SUCCESS! Railway database is ready!\n";
        echo "\n📋 NEXT STEPS:\n";
        echo "1. Update your application configuration\n";
        echo "2. Update Render environment variables\n";
        echo "3. Test your application\n\n";
        
        echo "=== RAILWAY CREDENTIALS FOR YOUR APP ===\n";
        echo "Host: {$credentials['host']}\n";
        echo "Port: {$credentials['port']}\n";  
        echo "Username: {$credentials['username']}\n";
        echo "Password: {$credentials['password']}\n";
        echo "Database: {$credentials['database']}\n";
    } else {
        echo "\n❌ Some MOA columns are still missing\n";
    }
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>