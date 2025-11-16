<?php
// Analyze Cloudinary account status and usage
require_once 'config/cloudinary.php';
require_once 'database/database.php';

echo "☁️ CLOUDINARY ACCOUNT ANALYSIS\n";
echo "=============================\n\n";

// Check current Cloudinary configuration
echo "🔧 Current Configuration:\n";
echo "   Cloud Name: " . CLOUDINARY_CLOUD_NAME . "\n";
echo "   API Key: " . CLOUDINARY_API_KEY . "\n";
echo "   API Secret: " . (defined('CLOUDINARY_API_SECRET') ? 'Configured' : 'Missing') . "\n\n";

// Test Cloudinary API access
echo "🌐 Testing Cloudinary API Access:\n";
try {
    $uploader = new CloudinaryUploader();
    echo "   ✅ CloudinaryUploader class loaded successfully\n";
    
    // Test API connectivity with a simple API call (check account details)
    $url = "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/usage";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, CLOUDINARY_API_KEY . ":" . CLOUDINARY_API_SECRET);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ API connection successful\n";
        
        $usage = json_decode($response, true);
        if ($usage) {
            echo "   📊 Usage Information:\n";
            
            // Credits/transformations
            if (isset($usage['credits'])) {
                $credits = $usage['credits'];
                echo "      Credits used: " . number_format($credits['used'] ?? 0) . "\n";
                echo "      Credits limit: " . number_format($credits['limit'] ?? 0) . "\n";
                echo "      Usage: " . round(($credits['used'] ?? 0) / ($credits['limit'] ?? 1) * 100, 1) . "%\n";
            }
            
            // Storage
            if (isset($usage['storage'])) {
                $storage = $usage['storage'];
                echo "      Storage used: " . round(($storage['used'] ?? 0) / 1024 / 1024, 2) . " MB\n";
                echo "      Storage limit: " . round(($storage['limit'] ?? 0) / 1024 / 1024, 2) . " MB\n";
            }
            
            // Bandwidth
            if (isset($usage['bandwidth'])) {
                $bandwidth = $usage['bandwidth'];
                echo "      Bandwidth used: " . round(($bandwidth['used'] ?? 0) / 1024 / 1024, 2) . " MB\n";
                echo "      Bandwidth limit: " . round(($bandwidth['limit'] ?? 0) / 1024 / 1024, 2) . " MB\n";
            }
        }
    } else {
        echo "   ⚠️ API connection failed (HTTP $httpCode)\n";
        echo "      Response: " . substr($response, 0, 200) . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n📁 Cloudinary Files in Database:\n";
try {
    $db = new Database();
    
    // Count Cloudinary URLs in different tables
    $tables = [
        'coordinator' => 'PROFILE_IMAGE',
        'host_training_establishment' => 'LOGO',
        'host_training_establishment' => 'MOA_FILE_URL',
        'report_images' => 'image_filename'
    ];
    
    $totalFiles = 0;
    
    foreach ($tables as $table => $column) {
        $stmt = $db->conn->prepare("SELECT COUNT(*) as count FROM `$table` WHERE `$column` LIKE 'https://res.cloudinary.com%'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'];
        $totalFiles += $count;
        
        echo "   $table.$column: $count files\n";
    }
    
    echo "   Total Cloudinary files: $totalFiles\n";
    
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n💰 CLOUDINARY PRICING & EXPIRATION:\n";
echo "===================================\n";
echo "📋 Free Tier Limits:\n";
echo "   • 25 credits/month (resets monthly)\n";
echo "   • 25,000 total images\n";
echo "   • 25 GB storage\n";
echo "   • 25 GB monthly bandwidth\n";
echo "   • ❌ NO EXPIRATION - Account stays active forever!\n\n";

echo "🔄 Credit Usage:\n";
echo "   • 1 credit = 1,000 transformations OR 1 GB bandwidth\n";
echo "   • Image uploads: FREE (don't count against credits)\n";
echo "   • Image delivery: 0.001 credits per image view\n";
echo "   • File storage: FREE up to 25GB\n\n";

echo "⚠️ Potential Issues:\n";
echo "   • Credits reset monthly (not cumulative)\n";
echo "   • Exceeding limits may throttle or suspend service temporarily\n";
echo "   • Account remains active, just limited functionality\n\n";

echo "✅ RECOMMENDATIONS:\n";
echo "===================\n";
echo "1. 📊 Monitor usage monthly via Cloudinary dashboard\n";
echo "2. 🗜️ Optimize images to reduce bandwidth usage\n";
echo "3. 💾 Keep database backup (your new one already includes Cloudinary URLs)\n";
echo "4. 🔄 If limits exceeded, consider paid plan ($99/year for 100 credits)\n";
echo "5. 🚀 Unlike Railway/Render, Cloudinary free tier is PERMANENT\n\n";

echo "🎯 SUMMARY:\n";
echo "===========\n";
echo "✅ Cloudinary does NOT expire like FreeSQLDatabase did\n";
echo "✅ Free tier is permanent with monthly credit refresh\n";
echo "✅ Your files are stored permanently (no data loss)\n";
echo "✅ Much more stable than temporary hosting solutions\n";
echo "⚠️ Just monitor monthly usage to stay within free limits\n";

?>