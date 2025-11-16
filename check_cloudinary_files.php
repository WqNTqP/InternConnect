<?php
require_once 'database/database.php';
$db = new Database();

echo "Cloudinary files currently in database:\n\n";

// Check coordinator profile pictures
try {
    $stmt = $db->conn->query("SELECT COUNT(*) as count FROM coordinator WHERE PROFILE_PIC LIKE 'https://res.cloudinary.com%'");
    $result = $stmt->fetch();
    echo "✅ Coordinator profiles: {$result['count']} Cloudinary URLs\n";
} catch(Exception $e) {
    echo "❌ Coordinator profiles: Column check failed\n";
}

// Check HTE logos
try {
    $stmt = $db->conn->query("SELECT COUNT(*) as count FROM host_training_establishment WHERE LOGO LIKE 'https://res.cloudinary.com%'");
    $result = $stmt->fetch();
    echo "✅ HTE logos: {$result['count']} Cloudinary URLs\n";
} catch(Exception $e) {
    echo "❌ HTE logos: Column check failed\n";
}

// Check MOA files
try {
    $stmt = $db->conn->query("SELECT COUNT(*) as count FROM host_training_establishment WHERE MOA_FILE_URL LIKE 'https://res.cloudinary.com%'");
    $result = $stmt->fetch();
    echo "✅ MOA files: {$result['count']} Cloudinary URLs\n";
} catch(Exception $e) {
    echo "❌ MOA files: Column check failed\n";
}

// Show some actual URLs
echo "\nSample Cloudinary URLs found:\n";
try {
    $stmt = $db->conn->query("SELECT NAME, LOGO FROM host_training_establishment WHERE LOGO LIKE 'https://res.cloudinary.com%' LIMIT 3");
    while($row = $stmt->fetch()) {
        echo "  - {$row['NAME']}: " . substr($row['LOGO'], 0, 60) . "...\n";
    }
} catch(Exception $e) {
    echo "  Could not retrieve sample URLs\n";
}
?>