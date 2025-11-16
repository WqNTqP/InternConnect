<?php
// Quick fix for timezone in weeklyReportAjax.php
require_once 'database/database.php';

echo "🔧 APPLYING TIMEZONE FIX TO weeklyReportAjax.php\n";
echo "===============================================\n\n";

// Read the current file
$filePath = 'ajaxhandler/weeklyReportAjax.php';
$content = file_get_contents($filePath);

if ($content === false) {
    echo "❌ Failed to read $filePath\n";
    exit(1);
}

// Find the specific function and add timezone conversion
$searchPattern = '/(\$result = \$rows\[0\];\s+\/\/ Remove image-specific fields from the main result\s+unset\(\$result\[\'image_filename\'\], \$result\[\'day_of_week\'\]\);)/';

$replacement = '$result = $rows[0];
    // Remove image-specific fields from the main result
    unset($result[\'image_filename\'], $result[\'day_of_week\']);
    
    // Convert timestamps from UTC to Philippine timezone
    $philippineTimezone = new DateTimeZone(\'Asia/Manila\');
    
    if (!empty($result[\'created_at\'])) {
        try {
            $result[\'created_at\'] = (new DateTime($result[\'created_at\'], new DateTimeZone(\'UTC\')))
                ->setTimezone($philippineTimezone)->format(\'Y-m-d H:i:s\');
        } catch (Exception $e) {
            // Keep original if conversion fails
        }
    }
    
    if (!empty($result[\'updated_at\'])) {
        try {
            $result[\'updated_at\'] = (new DateTime($result[\'updated_at\'], new DateTimeZone(\'UTC\')))
                ->setTimezone($philippineTimezone)->format(\'Y-m-d H:i:s\');
        } catch (Exception $e) {
            // Keep original if conversion fails
        }
    }
    
    if (!empty($result[\'submitted_at\'])) {
        try {
            $result[\'submitted_at\'] = (new DateTime($result[\'submitted_at\'], new DateTimeZone(\'UTC\')))
                ->setTimezone($philippineTimezone)->format(\'Y-m-d H:i:s\');
        } catch (Exception $e) {
            // Keep original if conversion fails
        }
    }
    
    if (!empty($result[\'approved_at\'])) {
        try {
            $result[\'approved_at\'] = (new DateTime($result[\'approved_at\'], new DateTimeZone(\'UTC\')))
                ->setTimezone($philippineTimezone)->format(\'Y-m-d H:i:s\');
        } catch (Exception $e) {
            // Keep original if conversion fails
        }
    }';

// Apply the replacement
$newContent = preg_replace($searchPattern, $replacement, $content, 1);

if ($newContent !== null && $newContent !== $content) {
    // Backup the original file
    $backupPath = $filePath . '.backup.' . date('Y-m-d-H-i-s');
    file_put_contents($backupPath, $content);
    echo "✅ Created backup: $backupPath\n";
    
    // Write the new content
    file_put_contents($filePath, $newContent);
    echo "✅ Applied timezone fix to $filePath\n";
    
    echo "\n🎉 Timezone fix applied successfully!\n";
    echo "   The draft save time should now show correct Philippine time.\n";
    echo "   Try saving a draft again and check the timestamp.\n";
} else {
    echo "❌ Failed to apply timezone fix\n";
    echo "   The pattern might not match or file might already be updated\n";
}
?>