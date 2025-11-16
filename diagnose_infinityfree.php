<?php
/**
 * TEMPORARY: Force InfinityFree Connection Test
 * This bypasses hostname issues by forcing the application to use InfinityFree
 */

echo "=== INFINITYFREE CONNECTION DIAGNOSIS ===\n\n";

// Test different hostname variations that InfinityFree commonly uses
$hostnameVariations = [
    'sql102.infinityfree.com',      // What they provided
    'sql102.infinityfree.net',      // Alternative TLD
    'sql102.epizy.com',             // InfinityFree's backend
    'sql102.byethost12.com',        // Another backend
    'mysql.infinityfree.com',       // Generic MySQL host
    'localhost'                     // Sometimes used for internal connections
];

$credentials = [
    'username' => 'if0_40429035',
    'password' => 'bRXtz7w8GIW8X', 
    'database' => 'if0_40429035_internconnect'
];

echo "Testing hostname variations:\n\n";

foreach ($hostnameVariations as $host) {
    echo "Testing: $host\n";
    
    try {
        $dsn = "mysql:host=$host;port=3306;dbname={$credentials['database']};charset=utf8";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10];
        
        $conn = new PDO($dsn, $credentials['username'], $credentials['password'], $options);
        
        echo "  ✅ CONNECTION SUCCESS with: $host\n";
        echo "  🎉 CORRECT HOSTNAME FOUND!\n\n";
        
        // Update the config with working hostname
        echo "=== UPDATE YOUR CONFIG ===\n";
        echo "Replace 'sql102.infinityfree.com' with '$host' in:\n";
        echo "• database/database.php\n";
        echo "• .env.example\n"; 
        echo "• .env.infinityfree\n\n";
        
        exit(0);
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'getaddrinfo') !== false || strpos($e->getMessage(), 'No such host') !== false) {
            echo "  ❌ Hostname not found\n";
        } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
            echo "  🔍 Hostname works but access denied (check credentials)\n";
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            echo "  🔍 Hostname works but database doesn't exist\n";
        } else {
            echo "  ❌ Other error: " . substr($e->getMessage(), 0, 50) . "...\n";
        }
    }
}

echo "\n=== INFINITYFREE SPECIFIC SOLUTIONS ===\n\n";

echo "🔧 SOLUTION 1: Check Remote MySQL Access\n";
echo "1. Log into InfinityFree Control Panel\n";
echo "2. Go to 'MySQL Databases'\n";
echo "3. Look for 'Remote MySQL' or 'External Access'\n";
echo "4. Enable remote access if disabled\n";
echo "5. Check if they provide a different hostname for external connections\n\n";

echo "🔧 SOLUTION 2: Use phpMyAdmin Method\n";
echo "InfinityFree often restricts external MySQL connections.\n";
echo "For testing, use phpMyAdmin in their control panel.\n";
echo "For production, you might need to:\n";
echo "1. Upload files to InfinityFree hosting (not just database)\n";
echo "2. Use their internal 'localhost' hostname\n\n";

echo "🔧 SOLUTION 3: Alternative Hosting\n";
echo "If InfinityFree doesn't allow remote MySQL:\n";
echo "1. Use Railway.app (has MySQL with remote access)\n";
echo "2. Use PlanetScale (free MySQL database)\n";
echo "3. Use Aiven.io (free tier MySQL)\n\n";

echo "🔧 SOLUTION 4: Temporary Local Fix\n";
echo "For immediate testing, I can modify your app to:\n";
echo "1. Skip the production check\n";
echo "2. Always use InfinityFree as primary\n";
echo "3. Import your backup locally to XAMPP for now\n\n";

echo "=== RECOMMENDED NEXT STEPS ===\n";
echo "1. Check InfinityFree control panel for remote MySQL settings\n";
echo "2. Try uploading your app files to InfinityFree hosting\n";
echo "3. Test connection from within InfinityFree's environment\n";
echo "4. If remote access is blocked, consider alternative database hosting\n";
?>