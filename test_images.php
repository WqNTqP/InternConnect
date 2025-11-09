<?php
// Test image accessibility
header('Content-Type: text/html; charset=UTF-8');

echo "<h2>Image Path Testing</h2>\n";
echo "<style>img { max-width: 200px; margin: 10px; border: 1px solid #ccc; }</style>\n";

// Get the base URL dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$basePath = dirname($_SERVER['PHP_SELF']) . '/';
$baseUrl = $protocol . "://" . $host . $basePath;

echo "<p><strong>Current Base URL:</strong> " . htmlspecialchars($baseUrl) . "</p>\n";
echo "<p><strong>Document Root:</strong> " . htmlspecialchars($_SERVER['DOCUMENT_ROOT']) . "</p>\n";
echo "<p><strong>Current Directory:</strong> " . htmlspecialchars(dirname(__FILE__)) . "</p>\n";

// Check if uploads directory exists
$uploadsDir = __DIR__ . '/uploads/reports/';
echo "<p><strong>Uploads Directory:</strong> " . htmlspecialchars($uploadsDir) . "</p>\n";
echo "<p><strong>Directory Exists:</strong> " . (is_dir($uploadsDir) ? 'Yes' : 'No') . "</p>\n";

if (is_dir($uploadsDir)) {
    $files = array_diff(scandir($uploadsDir), array('.', '..', '.htaccess'));
    echo "<p><strong>Found " . count($files) . " files in reports directory</strong></p>\n";
    
    // Show first 5 images as test
    $count = 0;
    foreach ($files as $file) {
        if ($count >= 5) break;
        
        $filePath = $uploadsDir . $file;
        $webPath = $baseUrl . 'uploads/reports/' . $file;
        
        if (is_file($filePath)) {
            $fileSize = filesize($filePath);
            echo "<div style='border: 1px solid #ddd; margin: 10px; padding: 10px;'>\n";
            echo "<p><strong>File:</strong> " . htmlspecialchars($file) . "</p>\n";
            echo "<p><strong>Size:</strong> " . number_format($fileSize) . " bytes</p>\n";
            echo "<p><strong>Web URL:</strong> <a href='" . htmlspecialchars($webPath) . "' target='_blank'>" . htmlspecialchars($webPath) . "</a></p>\n";
            echo "<p><strong>File Permissions:</strong> " . substr(sprintf('%o', fileperms($filePath)), -4) . "</p>\n";
            echo "<img src='" . htmlspecialchars($webPath) . "' alt='Test Image' onerror='this.style.border=\"3px solid red\"; this.alt=\"Failed to load\";'>\n";
            echo "</div>\n";
            $count++;
        }
    }
} else {
    echo "<p style='color: red;'>Uploads directory not found!</p>\n";
}
?>