<?php
/**
 * Complete Database Schema Comparison Tool
 * Compares current code expectations vs November 13 backup to find missing columns
 */

// Parse the backup SQL file to extract table structures
function parseBackupSchema($sqlFile) {
    if (!file_exists($sqlFile)) {
        die("Backup file not found: $sqlFile\n");
    }
    
    $content = file_get_contents($sqlFile);
    $tables = [];
    
    // Extract CREATE TABLE statements
    preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE/s', $content, $matches);
    
    for ($i = 0; $i < count($matches[1]); $i++) {
        $tableName = $matches[1][$i];
        $tableContent = $matches[2][$i];
        
        // Parse columns from table definition
        preg_match_all('/`([^`]+)`\s+([^,\n]+)/m', $tableContent, $colMatches);
        
        $columns = [];
        for ($j = 0; $j < count($colMatches[1]); $j++) {
            $columnName = $colMatches[1][$j];
            $columnDef = trim($colMatches[2][$j]);
            $columns[$columnName] = $columnDef;
        }
        
        $tables[$tableName] = $columns;
    }
    
    return $tables;
}

// Scan PHP code for database column references
function scanCodeForColumns($directory) {
    $expectedColumns = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            
            // Find SQL queries and column references
            $patterns = [
                // SELECT statements
                '/SELECT\s+[^F].*?FROM\s+`?([a-zA-Z_]+)`?/is',
                // INSERT INTO statements
                '/INSERT\s+INTO\s+`?([a-zA-Z_]+)`?\s*\([^)]*`([^`]+)`/is',
                // UPDATE statements with SET
                '/UPDATE\s+`?([a-zA-Z_]+)`?.*?SET\s+([^W].*?)WHERE/is',
                // ALTER TABLE ADD COLUMN
                '/ALTER\s+TABLE\s+`?([a-zA-Z_]+)`?.*?ADD\s+COLUMN\s+`?([a-zA-Z_]+)`?/is',
                // Column references in quotes
                '/["\']([A-Z_][A-Z0-9_]*)["\'](?=\s*[=,)\]])/m',
                // Array key references like $_POST['COLUMN_NAME']
                '/\[\s*["\']([A-Z_][A-Z0-9_]*)["\']/', 
                // Direct column references in SQL
                '/`([a-z_][a-z0-9_]*)`/i'
            ];
            
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $content, $matches);
                
                if (isset($matches[1])) {
                    foreach ($matches[1] as $match) {
                        $match = trim($match);
                        if (strlen($match) > 2 && !is_numeric($match)) {
                            // Try to categorize by table based on context
                            $context = substr($content, max(0, strpos($content, $match) - 100), 200);
                            $table = guessTableFromContext($context, $match);
                            
                            if ($table) {
                                if (!isset($expectedColumns[$table])) {
                                    $expectedColumns[$table] = [];
                                }
                                $expectedColumns[$table][] = $match;
                            }
                        }
                    }
                }
                
                if (isset($matches[2])) {
                    foreach ($matches[2] as $match) {
                        $match = trim($match);
                        if (strlen($match) > 2) {
                            // Extract table from first capture group if available
                            $tableIndex = array_search($match, $matches[2]);
                            if (isset($matches[1][$tableIndex])) {
                                $table = $matches[1][$tableIndex];
                                if (!isset($expectedColumns[$table])) {
                                    $expectedColumns[$table] = [];
                                }
                                $expectedColumns[$table][] = $match;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Clean up and deduplicate
    foreach ($expectedColumns as $table => $columns) {
        $expectedColumns[$table] = array_unique($columns);
        sort($expectedColumns[$table]);
    }
    
    return $expectedColumns;
}

// Guess table name from context
function guessTableFromContext($context, $column) {
    $tableHints = [
        'host_training_establishment' => ['HTE_ID', 'MOA_FILE_URL', 'MOA_PUBLIC_ID', 'MOA_START_DATE', 'MOA_END_DATE', 'MOA_UPLOAD_DATE', 'LOGO', 'INDUSTRY', 'CONTACT_EMAIL', 'CONTACT_PERSON', 'CONTACT_NUMBER'],
        'coordinator' => ['COORDINATOR_ID', 'USERNAME', 'PASSWORD', 'ROLE', 'PROFILE', 'DEPARTMENT'],
        'student_evaluation' => ['STUDENT_ID', 'evaluation_id', 'question_id', 'answer_text'],
        'weekly_reports' => ['report_id', 'student_id', 'week_number', 'report_content'],
        'interns_attendance' => ['STUDENT_ID', 'TIME_IN', 'TIME_OUT', 'ATTENDANCE_DATE'],
        'session_details' => ['SESSION_ID', 'SESSION_NAME', 'START_DATE', 'END_DATE'],
        'past_data' => ['id_number', 'student_name', 'year_graduated', 'OJT Placement'],
        'post_assessment' => ['assessment_id', 'student_id', 'question_id', 'score'],
        'pre_assessment' => ['assessment_id', 'student_id', 'question_id', 'answer']
    ];
    
    // Check if column matches any table's known columns
    foreach ($tableHints as $table => $knownColumns) {
        if (in_array($column, $knownColumns)) {
            return $table;
        }
    }
    
    // Check context for table name mentions
    foreach ($tableHints as $table => $cols) {
        if (stripos($context, $table) !== false) {
            return $table;
        }
    }
    
    return null;
}

// Main execution
echo "=== DATABASE SCHEMA COMPARISON TOOL ===\n\n";

$backupFile = __DIR__ . '/database/sql3806785.sql';
$codeDirectory = __DIR__;

echo "1. Parsing backup schema from: $backupFile\n";
$backupTables = parseBackupSchema($backupFile);
echo "   Found " . count($backupTables) . " tables in backup\n\n";

echo "2. Scanning code for expected columns in: $codeDirectory\n";
$expectedColumns = scanCodeForColumns($codeDirectory);
echo "   Found column references in " . count($expectedColumns) . " tables\n\n";

echo "=== COMPARISON RESULTS ===\n\n";

$missingColumns = [];
$allTables = array_unique(array_merge(array_keys($backupTables), array_keys($expectedColumns)));
sort($allTables);

foreach ($allTables as $table) {
    $backupCols = isset($backupTables[$table]) ? array_keys($backupTables[$table]) : [];
    $expectedCols = isset($expectedColumns[$table]) ? $expectedColumns[$table] : [];
    
    $missing = array_diff($expectedCols, $backupCols);
    
    if (!empty($missing)) {
        $missingColumns[$table] = $missing;
        echo "TABLE: $table\n";
        echo "  Backup columns (" . count($backupCols) . "): " . implode(', ', $backupCols) . "\n";
        echo "  Expected columns (" . count($expectedCols) . "): " . implode(', ', $expectedCols) . "\n";
        echo "  ❌ MISSING (" . count($missing) . "): " . implode(', ', $missing) . "\n";
        echo "\n";
    } else if (isset($backupTables[$table])) {
        echo "✅ TABLE: $table - All columns present\n";
    }
}

if (empty($missingColumns)) {
    echo "\n🎉 No missing columns detected!\n";
} else {
    echo "\n=== REQUIRED ALTER TABLE STATEMENTS ===\n\n";
    
    foreach ($missingColumns as $table => $columns) {
        echo "-- Table: $table\n";
        echo "ALTER TABLE `$table`\n";
        
        $alterParts = [];
        foreach ($columns as $column) {
            // Guess column type based on name patterns
            $columnType = guessColumnType($column);
            $alterParts[] = "ADD COLUMN `$column` $columnType";
        }
        
        echo "  " . implode(",\n  ", $alterParts) . ";\n\n";
    }
    
    echo "=== SUMMARY ===\n";
    echo "Total tables with missing columns: " . count($missingColumns) . "\n";
    $totalMissing = array_sum(array_map('count', $missingColumns));
    echo "Total missing columns: $totalMissing\n\n";
    
    echo "=== INSTRUCTIONS ===\n";
    echo "1. Import your backup: mysql -u user -p database < database/sql3806785.sql\n";
    echo "2. Run the ALTER TABLE statements above\n";
    echo "3. Update database configuration with new hosting credentials\n";
}

// Guess column type based on naming patterns
function guessColumnType($columnName) {
    $patterns = [
        '/ID$/' => 'int(11) NOT NULL',
        '/_ID$/' => 'int(11) DEFAULT NULL',
        '/^(MOA_|HTE_).*_URL$/' => 'varchar(500) DEFAULT NULL',
        '/PUBLIC_ID$/' => 'varchar(255) DEFAULT NULL', 
        '/_DATE$/' => 'date DEFAULT NULL',
        '/UPLOAD_DATE$/' => 'timestamp NULL DEFAULT NULL',
        '/TIME$/' => 'time DEFAULT NULL',
        '/TIMESTAMP$/' => 'timestamp NULL DEFAULT CURRENT_TIMESTAMP',
        '/PASSWORD/' => 'varchar(255) DEFAULT NULL',
        '/EMAIL/' => 'varchar(100) DEFAULT NULL',
        '/PHONE|CONTACT/' => 'varchar(20) DEFAULT NULL',
        '/NAME$/' => 'varchar(100) NOT NULL',
        '/ROLE$/' => 'enum(\'STUDENT\',\'COORDINATOR\',\'ADMIN\',\'SUPERADMIN\') DEFAULT NULL',
        '/STATUS$/' => 'enum(\'active\',\'inactive\') DEFAULT \'active\'',
        '/SCORE$/' => 'decimal(5,2) DEFAULT NULL',
        '/RATING$/' => 'int(11) DEFAULT NULL',
        '/TEXT$|CONTENT$|DESCRIPTION$/' => 'text DEFAULT NULL'
    ];
    
    foreach ($patterns as $pattern => $type) {
        if (preg_match($pattern, $columnName)) {
            return $type;
        }
    }
    
    return 'varchar(255) DEFAULT NULL'; // Default fallback
}
?>