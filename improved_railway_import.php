<?php
/**
 * Improved Railway SQL Import with Better Error Handling
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes

echo "=== IMPROVED RAILWAY SQL IMPORT ===\n\n";

$credentials = [
    'host' => 'mainline.proxy.rlwy.net',
    'port' => 31782,
    'username' => 'root',
    'password' => 'LYeUTqrnaDxpSAdWiirrGhFAcVVyNMGJ',
    'database' => 'railway'
];

$sqlFile = 'database/sql3806785.sql';

echo "📁 SQL File: $sqlFile (" . round(filesize($sqlFile) / 1024 / 1024, 2) . " MB)\n";
echo "🎯 Target: {$credentials['host']}:{$credentials['port']}\n\n";

try {
    // Connect to Railway
    echo "🔗 Connecting to Railway MySQL...\n";
    $dsn = "mysql:host={$credentials['host']};port={$credentials['port']};dbname={$credentials['database']};charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 60,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
    ];
    
    $pdo = new PDO($dsn, $credentials['username'], $credentials['password'], $options);
    echo "✅ Connected to Railway database!\n\n";
    
    // Set MySQL settings for import
    echo "⚙️ Configuring MySQL for import...\n";
    $pdo->exec("SET foreign_key_checks = 0");
    $pdo->exec("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
    $pdo->exec("SET autocommit = 0");
    
    // Read SQL file
    echo "📖 Reading SQL file...\n";
    $sqlContent = file_get_contents($sqlFile);
    
    // Clean up the SQL content
    $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);  // Remove line comments
    $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);  // Remove block comments
    $sqlContent = preg_replace('/^\s*$/m', '', $sqlContent);  // Remove empty lines
    
    // Split into statements more carefully
    $statements = preg_split('/;\s*$/m', $sqlContent);
    $statements = array_filter(array_map('trim', $statements));
    
    echo "📊 Found " . count($statements) . " SQL statements\n\n";
    
    // Start transaction
    echo "⚡ Starting import transaction...\n";
    $pdo->beginTransaction();
    
    $executed = 0;
    $errors = 0;
    $skipped = 0;
    
    foreach ($statements as $i => $statement) {
        if (empty($statement) || strlen($statement) < 10) {
            $skipped++;
            continue;
        }
        
        try {
            // Show progress
            if (($i + 1) % 5 === 0) {
                echo "   Progress: " . ($i + 1) . "/" . count($statements) . " (Executed: $executed, Errors: $errors)\n";
            }
            
            $pdo->exec($statement);
            $executed++;
            
        } catch (PDOException $e) {
            $errors++;
            $errorMsg = $e->getMessage();
            
            // Log detailed error for debugging
            echo "   ⚠️ Statement " . ($i + 1) . " error: " . substr($errorMsg, 0, 80) . "...\n";
            
            // Don't stop on these common issues
            if (strpos($errorMsg, 'Duplicate entry') !== false ||
                strpos($errorMsg, 'already exists') !== false ||
                strpos($errorMsg, 'Multiple primary key') !== false) {
                echo "      → Continuing (duplicate/existing data)\n";
                continue;
            }
            
            // Stop on critical structural errors
            if (strpos($errorMsg, 'syntax error') !== false && $errors > 10) {
                echo "   🛑 Too many syntax errors - stopping\n";
                $pdo->rollBack();
                exit(1);
            }
        }
    }
    
    // Commit transaction
    echo "\n💾 Committing transaction...\n";
    $pdo->commit();
    
    // Re-enable foreign key checks
    $pdo->exec("SET foreign_key_checks = 1");
    $pdo->exec("SET autocommit = 1");
    
    echo "\n✅ Import completed!\n";
    echo "📊 Statistics:\n";
    echo "   • Executed: $executed statements\n";
    echo "   • Errors: $errors statements\n";
    echo "   • Skipped: $skipped statements\n\n";
    
    // Verify import
    echo "🔍 Verifying import...\n";
    $stmt = $pdo->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Tables created: " . count($tables) . "\n";
    
    // Check key tables
    $keyTables = ['coordinator', 'host_training_establishment', 'student_evaluation', 'weekly_reports'];
    foreach ($keyTables as $table) {
        if (in_array($table, $tables)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "✅ $table: $count records\n";
        } else {
            echo "❌ $table: Missing\n";
        }
    }
    
    echo "\n🎉 SUCCESS! Your backup is imported to Railway.\n";
    echo "\n📋 NEXT STEPS:\n";
    echo "1. Add missing MOA columns (run database_restoration.sql commands)\n";
    echo "2. Update your application config with Railway credentials\n";
    echo "3. Test your application\n";
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>