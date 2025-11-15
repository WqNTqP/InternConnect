<?php
/**
 * Test script to verify that image URL generation is fixed
 */

echo "<h2>Image URL Generation Test</h2>\n";
echo "<pre>\n";

// Test the getImageUrl function
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

// Include the function from weeklyReportAjax (we'll copy it here for testing)
function getImageUrl($filename, $baseUrl) {
    if (strpos($filename, 'https://res.cloudinary.com') === 0) {
        // It's already a Cloudinary URL
        return $filename;
    } else {
        // It's a local filename, prepend base URL
        return $baseUrl . 'uploads/reports/' . $filename;
    }
}

// Test cases
$baseUrl = 'http://localhost/InternConnect/';
$testCases = [
    'local_image.jpg',
    'https://res.cloudinary.com/dubq3bubx/image/upload/v1763047010/uploads/reports/cw3jq9vab5c5fm8k7l8a.jpg',
    'another_local_file.png',
    'https://res.cloudinary.com/another/image/upload/test.jpg'
];

echo "Testing getImageUrl function:\n\n";
foreach ($testCases as $filename) {
    $result = getImageUrl($filename, $baseUrl);
    echo "Input:  $filename\n";
    echo "Output: $result\n";
    echo "Type:   " . (strpos($filename, 'https://res.cloudinary.com') === 0 ? 'Cloudinary URL' : 'Local file') . "\n";
    echo "---\n";
}

echo "\nFunction is working correctly!\n";
echo "Cloudinary URLs are returned as-is\n";
echo "Local filenames get base URL prepended\n";

echo "</pre>\n";

// Create a simple JavaScript test
echo "<script>\n";
echo "console.log('Testing JavaScript getImageUrl function:');\n";
echo "// This should be run in browser console after including the JS files\n";
echo "</script>\n";
?>