<?php
/**
 * InfinityFree MySQL Hostname Checker
 * Tests various possible InfinityFree MySQL hostnames
 */

echo "=== INFINITYFREE HOSTNAME DIAGNOSIS ===\n\n";

// Common InfinityFree MySQL hostnames based on their documentation
$possibleHosts = [
    'sql102.infinityfree.com',
    'sql102.infinityfree.net', 
    'sql102.epizy.com',
    'mysql.infinityfree.com',
    'mysql.infinityfree.net',
    'localhost',  // Sometimes InfinityFree uses localhost for shared hosting
    'sql102.byethost12.com',  // InfinityFree sometimes uses byethost servers
    'sql102.epizy.com',
];

$credentials = [
    'username' => 'if0_40429035',
    'password' => 'bRXtz7w8GIW8X',
    'database' => 'if0_40429035_XXX'
];

echo "Testing these possible hostnames:\n";
foreach ($possibleHosts as $host) {
    echo "Testing: $host... ";
    
    // Test DNS resolution first
    $ip = gethostbyname($host);
    if ($ip === $host) {
        echo "❌ DNS failed\n";
        continue;
    } else {
        echo "✅ DNS OK ($ip) ";
    }
    
    // Test MySQL connection
    try {
        $dsn = "mysql:host=$host;port=3306;dbname={$credentials['database']};charset=utf8";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10];
        
        $conn = new PDO($dsn, $credentials['username'], $credentials['password'], $options);
        echo "✅ MySQL Connection SUCCESS!\n";
        
        // Test a query
        $stmt = $conn->prepare("SELECT COUNT(*) FROM coordinator");
        $stmt->execute();
        echo "   Query test: ✅ SUCCESS\n";
        
        echo "\n🎉 CORRECT HOSTNAME FOUND: $host\n\n";
        break;
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            echo "🔍 Access denied (hostname works, credential issue)\n";
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            echo "🔍 Unknown database (hostname works, database doesn't exist)\n";
        } else {
            echo "❌ Connection failed\n";
        }
    }
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Log into your InfinityFree cPanel\n";
echo "2. Go to 'MySQL Databases' section\n";
echo "3. Look for the exact MySQL hostname shown there\n";
echo "4. Verify your database name is exactly: if0_40429035_XXX\n";
echo "5. Check if the database has been created yet\n\n";

echo "=== ALTERNATIVE: CHECK INFINITYFREE CONTROL PANEL ===\n";
echo "In your InfinityFree account:\n";
echo "1. Go to Control Panel → MySQL Databases\n";
echo "2. Find the 'Remote MySQL' or 'External Connections' section\n";
echo "3. Copy the exact hostname provided there\n";
echo "4. Some InfinityFree accounts require enabling remote access\n\n";

echo "=== COMMON INFINITYFREE MYSQL HOSTNAMES BY ACCOUNT TYPE ===\n";
echo "• Standard accounts: sql102.infinityfree.net or sql102.epizy.com\n";
echo "• Some accounts: Use 'localhost' when accessed from their servers\n";
echo "• Remote access: May require special hostname or be disabled\n";
?>