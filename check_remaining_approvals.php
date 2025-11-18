<?php
echo "=== CHECKING REMAINING APPROVAL REFERENCES ===\n\n";

// Check for approval references in admin files
$adminFiles = [
    'js/admindashboard.js',
    'css/admindashboard.css', 
    'admindashboard.php'
];

foreach ($adminFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "File: $file\n";
        $content = file_get_contents($fullPath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNum => $line) {
            if (stripos($line, 'approval') !== false) {
                echo "  Line " . ($lineNum + 1) . ": " . trim($line) . "\n";
            }
        }
        echo "\n";
    } else {
        echo "File not found: $file\n\n";
    }
}

// Check if adminQuestionApprovalAjax.php still exists
$approvalFile = __DIR__ . '/ajaxhandler/adminQuestionApprovalAjax.php';
if (file_exists($approvalFile)) {
    echo "❌ ALERT: adminQuestionApprovalAjax.php still exists!\n";
} else {
    echo "✅ adminQuestionApprovalAjax.php successfully removed\n";
}

echo "\n=== SUMMARY ===\n";
echo "The remaining 'approval' references appear to be for:\n";
echo "1. Weekly report approval system (separate from questions)\n";  
echo "2. Attendance approval system (separate from questions)\n";
echo "3. Generic approval text that may need updating\n";
?>