<?php
/**
 * PHP-based SQL Import for Railway
 * Alternative to command line import
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== RAILWAY SQL FILE IMPORT (PHP METHOD) ===\n\n";

// Railway credentials - FILLED WITH YOUR ACTUAL VALUES
$credentials = [
    'host' => 'mainline.proxy.rlwy.net',
    'port' => 31782,
    'username' => 'root',
    'password' => 'LYeUTqrnaDxpSAdWiirrGhFAcVVyNMGJ',
    'database' => 'railway'
];

$sqlFile = 'database/sql3806785.sql';

// Validate inputs
if (empty($credentials['host']) || empty($credentials['username'])) {
    echo "❌ Please fill in Railway credentials first!\n";
    echo "Edit this file and add your Railway MySQL credentials.\n\n";
    echo "Get credentials from: Railway Dashboard → MySQL → Variables tab\n";
    exit(1);
}

if (!file_exists($sqlFile)) {
    echo "❌ SQL file not found: $sqlFile\n";
    exit(1);
}

echo "📁 SQL File: $sqlFile (" . round(filesize($sqlFile) / 1024 / 1024, 2) . " MB)\n";
echo "🎯 Target: {$credentials['host']}:{$credentials['port']}\n";
echo "📊 Database: {$credentials['database']}\n\n";

try {
    // Connect to Railway
    echo "🔗 Connecting to Railway MySQL...\n";
    $dsn = "mysql:host={$credentials['host']};port={$credentials['port']};dbname={$credentials['database']};charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 60,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
    ];
    
    $pdo = new PDO($dsn, $credentials['username'], $credentials['password'], $options);
    echo "✅ Connected to Railway database!\n\n";
    
    // Read and prepare SQL file
    echo "📖 Reading SQL file...\n";
    $sqlContent = file_get_contents($sqlFile);
    
    // Remove comments and split into statements
    $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);  // Remove comments
    $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);  // Remove block comments
    
    // Split by semicolons but be careful with string literals
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    
    for ($i = 0; $i < strlen($sqlContent); $i++) {
        $char = $sqlContent[$i];
        
        if (!$inString && ($char === '"' || $char === "'")) {
            $inString = true;
            $stringChar = $char;
        } elseif ($inString && $char === $stringChar) {
            $inString = false;
        } elseif (!$inString && $char === ';') {
            $current = trim($current);
            if (!empty($current)) {
                $statements[] = $current;
            }
            $current = '';
            continue;
        }
        
        $current .= $char;
    }
    
    // Add final statement if any
    $current = trim($current);
    if (!empty($current)) {
        $statements[] = $current;
    }
    
    echo "📊 Found " . count($statements) . " SQL statements\n\n";
    
    // Execute statements
    echo "⚡ Importing data...\n";
    $pdo->beginTransaction();
    
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $i => $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Show progress every 10 statements
            if (($i + 1) % 10 === 0) {
                echo "   Executed: " . ($i + 1) . "/" . count($statements) . "\n";
            }
            
        } catch (PDOException $e) {
            $errors++;
            echo "   ⚠️ Error in statement " . ($i + 1) . ": " . substr($e->getMessage(), 0, 100) . "...\n";
            
            // Stop on critical errors
            if (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
                echo "   🛑 Critical error - stopping import\n";
                $pdo->rollBack();
                exit(1);
            }
        }
    }
    
    $pdo->commit();
    
    echo "\n✅ Import completed!\n";
    echo "📊 Executed: $executed statements\n";
    echo "⚠️ Errors: $errors statements\n\n";
    
    // Verify import
    echo "🔍 Verifying import...\n";
    $stmt = $pdo->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Tables created: " . count($tables) . "\n";
    
    if (in_array('coordinator', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM coordinator");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "👥 Coordinators imported: $count\n";
    }
    
    if (in_array('host_training_establishment', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM host_training_establishment");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "🏢 HTEs imported: $count\n";
    }
    
    echo "\n🎉 SUCCESS! Your backup is now imported to Railway.\n";
    echo "Next step: Add missing MOA columns using database_restoration.sql\n";
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
    echo "\nCheck your Railway credentials and try again.\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>