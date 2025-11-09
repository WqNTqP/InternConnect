<?php
// Debug script to check what baseUrl is being generated
echo "<h2>Base URL Debug Information</h2>";
echo "<pre>";
echo "Current script: " . __FILE__ . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "\n";
echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'not set') . "\n";
echo "dirname(\$_SERVER['PHP_SELF'], 1): " . dirname($_SERVER['PHP_SELF'], 1) . "\n";
echo "dirname(\$_SERVER['PHP_SELF'], 2): " . dirname($_SERVER['PHP_SELF'], 2) . "\n";
echo "\n";

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/';
echo "Generated baseUrl: " . $baseUrl . "\n";
echo "Full image path example: " . $baseUrl . "uploads/reports/68f3410513764_1760772357_friday.jpg\n";
echo "\n";

echo "Alternative calculations:\n";
$baseUrl2 = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 1) . '/';
echo "Using dirname(..., 1): " . $baseUrl2 . "\n";

$baseUrl3 = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . '/InternConnect/';
echo "Hardcoded '/InternConnect/': " . $baseUrl3 . "\n";

echo "</pre>";

// Test if the actual image file exists
$imagePath = "uploads/reports/68f3410513764_1760772357_friday.jpg";
echo "<h3>File Existence Check</h3>";
echo "<pre>";
echo "Checking: " . $imagePath . "\n";
echo "File exists: " . (file_exists($imagePath) ? "YES" : "NO") . "\n";
if (file_exists($imagePath)) {
    echo "File size: " . filesize($imagePath) . " bytes\n";
    echo "File is readable: " . (is_readable($imagePath) ? "YES" : "NO") . "\n";
}
echo "</pre>";

// Try to display the image
echo "<h3>Image Display Test</h3>";
if (file_exists($imagePath)) {
    echo '<img src="' . $imagePath . '" alt="Test image" style="max-width: 200px; border: 1px solid #ccc;">';
    echo "<p>If you can see the image above, the path is working correctly.</p>";
} else {
    echo "<p style='color: red;'>Image file not found at: $imagePath</p>";
}
?>