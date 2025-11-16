<?php
// Check actual column names for tables that had missing primary keys
require_once 'database/database.php';

$db = new Database();
$problematicTables = [
    'evaluation_questions',
    'intern_details', 
    'interns_attendance',
    'internship_needs',
    'notifications',
    'past_data',
    'pending_attendance',
    'report_images',
    'student_deletion_log',
    'weekly_reports'
];

echo "🔍 CHECKING ACTUAL COLUMN NAMES FOR PROBLEMATIC TABLES\n";
echo "====================================================\n\n";

foreach ($problematicTables as $table) {
    echo "📋 Table: $table\n";
    try {
        $result = $db->conn->query("DESCRIBE $table");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Columns:\n";
        foreach ($columns as $column) {
            $autoInc = strpos($column['Extra'], 'auto_increment') !== false ? ' (AUTO_INCREMENT)' : '';
            $primaryKey = $column['Key'] == 'PRI' ? ' (PRIMARY KEY)' : '';
            echo "   - {$column['Field']} ({$column['Type']}){$primaryKey}{$autoInc}\n";
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    }
}
?>