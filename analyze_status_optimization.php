<?php
require_once 'config/path_config.php';
require_once PathConfig::getDatabasePath();

echo "Analyzing status column optimization...\n";
echo str_repeat("=", 60) . "\n";

try {
    $dbo = new Database();
    
    // Check current column details
    echo "1. Current Column Information:\n";
    $stmt = $dbo->conn->query("SHOW COLUMNS FROM evaluation_questions LIKE 'status'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Current Type: " . $column['Type'] . "\n";
    echo "   Current Default: " . ($column['Default'] ?: 'NULL') . "\n";
    echo "   Storage: ENUM typically uses 1-2 bytes\n";
    echo "   Proposed: TINYINT(1) uses 1 byte\n";
    
    // Check all current status values
    echo "\n2. Current Status Values:\n";
    $stmt = $dbo->conn->prepare("SELECT DISTINCT status FROM evaluation_questions");
    $stmt->execute();
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($statuses as $status) {
        echo "   '" . $status . "'\n";
    }
    
    // Count total questions
    $stmt = $dbo->conn->prepare("SELECT COUNT(*) FROM evaluation_questions");
    $stmt->execute();
    $totalQuestions = $stmt->fetchColumn();
    echo "   Total Questions: " . $totalQuestions . "\n";
    
    echo "\n3. Storage Savings Analysis:\n";
    echo "   Current ENUM: ~1-2 bytes per row\n";
    echo "   Proposed TINYINT(1): 1 byte per row\n";
    echo "   Rows: " . $totalQuestions . "\n";
    echo "   Potential savings: Minimal but cleaner code\n";
    
    echo "\n4. Performance Benefits:\n";
    echo "   ✓ Faster comparisons (integer vs string)\n";
    echo "   ✓ Smaller indexes\n";
    echo "   ✓ More standard boolean logic\n";
    echo "   ✓ Compatible with most boolean libraries\n";
    
    echo "\n5. Files that need updating:\n";
    
    $filesToUpdate = [
        'ajaxhandler/attendanceAJAX.php' => "WHERE status = 'active' → WHERE status = 1",
        'ajaxhandler/coordinatorEvaluationQuestionsAjax.php' => "status = 'active' → status = 1",
        'ajaxhandler/getPersonalSkillsQuestionsAjax.php' => "WHERE status = 'active' → WHERE status = 1",
        'ajaxhandler/studentEvaluationAjax.php' => "WHERE status = 'active' → WHERE status = 1",
        'ajaxhandler/generateSampleQuestions.php' => "status = 'active' → status = 1",
        'debug_evaluation.php' => "WHERE status = 'active' → WHERE status = 1"
    ];
    
    foreach ($filesToUpdate as $file => $change) {
        echo "   - " . $file . "\n     " . $change . "\n";
    }
    
    echo "\n6. Migration Strategy:\n";
    echo "   Step 1: ALTER TABLE to add new is_active TINYINT(1) DEFAULT 1\n";
    echo "   Step 2: UPDATE to populate: is_active = IF(status='active', 1, 0)\n";
    echo "   Step 3: Update all PHP files to use is_active instead of status\n";
    echo "   Step 4: Test thoroughly\n";
    echo "   Step 5: DROP old status column\n";
    echo "   Step 6: RENAME is_active to status (optional)\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "RECOMMENDATION: ✅ YES, convert to TINYINT(1)\n";
    echo "Benefits: Cleaner code, faster queries, standard boolean logic\n";
    echo "Risk: Low (simple find-replace in 6 files)\n";
    echo "Effort: ~30 minutes including testing\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>