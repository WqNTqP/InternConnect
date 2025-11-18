<?php
/**
 * Database Data Summary
 * Shows row counts for all tables before cleanup
 */

require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

try {
    $db = new Database();
    $conn = $db->conn;
    
    if ($conn === null) {
        die("❌ Database connection failed.\n");
    }
    
    echo "📊 DATABASE DATA SUMMARY\n";
    echo "========================\n\n";
    
    // Get all tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $preserveTables = ['coordinator', 'past_data'];
    $totalRows = 0;
    $preservedRows = 0;
    $tableData = [];
    
    foreach ($tables as $table) {
        try {
            $countStmt = $conn->prepare("SELECT COUNT(*) FROM `$table`");
            $countStmt->execute();
            $rowCount = $countStmt->fetchColumn();
            
            $tableData[] = [
                'table' => $table,
                'rows' => $rowCount,
                'preserve' => in_array($table, $preserveTables)
            ];
            
            $totalRows += $rowCount;
            if (in_array($table, $preserveTables)) {
                $preservedRows += $rowCount;
            }
        } catch (Exception $e) {
            $tableData[] = [
                'table' => $table,
                'rows' => 'ERROR: ' . $e->getMessage(),
                'preserve' => in_array($table, $preserveTables)
            ];
        }
    }
    
    // Sort by row count (descending)
    usort($tableData, function($a, $b) {
        if (is_numeric($a['rows']) && is_numeric($b['rows'])) {
            return $b['rows'] - $a['rows'];
        }
        return 0;
    });
    
    // Display table data
    printf("%-30s %-15s %-10s\n", "TABLE NAME", "ROW COUNT", "STATUS");
    echo str_repeat("-", 55) . "\n";
    
    foreach ($tableData as $data) {
        $status = $data['preserve'] ? "🔒 KEEP" : "🗑️  DELETE";
        printf("%-30s %-15s %-10s\n", 
            $data['table'], 
            is_numeric($data['rows']) ? number_format($data['rows']) : $data['rows'],
            $status
        );
    }
    
    echo "\n" . str_repeat("=", 55) . "\n";
    echo "📈 SUMMARY:\n";
    echo "   Total tables: " . count($tables) . "\n";
    echo "   Total rows: " . number_format($totalRows) . "\n";
    echo "   Rows to preserve: " . number_format($preservedRows) . "\n";
    echo "   Rows to delete: " . number_format($totalRows - $preservedRows) . "\n";
    echo "\n🔒 Tables to preserve: " . implode(", ", $preserveTables) . "\n";
    
    // Show disk space info if available
    $dbSize = 0;
    try {
        $sizeStmt = $conn->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB'
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        $dbSize = $sizeStmt->fetchColumn();
        if ($dbSize) {
            echo "💾 Database size: ~{$dbSize} MB\n";
        }
    } catch (Exception $e) {
        // Size calculation not available
    }
    
    echo "\n✨ Ready for cleanup!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>