<?php
/**
 * Script to fix image URL generation in weeklyReportAjax.php
 * This replaces hardcoded base URL concatenation with getImageUrl function calls
 */

$file = __DIR__ . '/ajaxhandler/weeklyReportAjax.php';
$content = file_get_contents($file);

// Pattern to match the problematic lines
$pattern = '/\$baseUrl \. \'uploads\/reports\/\' \. \$row\[\'image_filename\'\]/';
$replacement = 'getImageUrl($row[\'image_filename\'], $baseUrl)';

// Count matches before replacement
$matches = [];
preg_match_all($pattern, $content, $matches);
echo "Found " . count($matches[0]) . " matches to replace\n";

// Perform replacement
$newContent = preg_replace($pattern, $replacement, $content);

if ($newContent !== $content) {
    // Backup original file
    copy($file, $file . '.backup.' . date('Y-m-d-H-i-s'));
    echo "Created backup file\n";
    
    // Write updated content
    file_put_contents($file, $newContent);
    echo "Updated file successfully\n";
    
    // Count replacements
    $newMatches = [];
    preg_match_all($pattern, $newContent, $newMatches);
    echo "Remaining matches after replacement: " . count($newMatches[0]) . "\n";
} else {
    echo "No changes needed or pattern not found\n";
}

echo "Done!\n";
?>