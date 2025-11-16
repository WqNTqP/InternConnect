<?php
// Create missing post_assessment table in Railway database
require_once 'database/database.php';

echo "Creating missing post_assessment table in Railway database...\n";

try {
    $db = new Database();
    $pdo = $db->conn;
    
    // First, create the table structure
    $createTableSQL = "CREATE TABLE `post_assessment` (
        `id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `question_id` int(11) NOT NULL,
        `self_rating` int(11) DEFAULT NULL,
        `category` varchar(50) DEFAULT NULL,
        `supervisor_id` int(11) DEFAULT NULL,
        `supervisor_rating` int(11) DEFAULT NULL,
        `comment` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    echo "Creating table structure...\n";
    $pdo->exec($createTableSQL);
    echo "✓ Table structure created successfully\n";
    
    // Add primary key and auto_increment
    $alterTableSQL = "ALTER TABLE `post_assessment` ADD PRIMARY KEY (`id`), MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=942";
    echo "Adding primary key and auto_increment...\n";
    $pdo->exec($alterTableSQL);
    echo "✓ Primary key and auto_increment added\n";
    
    // Now insert the data
    echo "Inserting data...\n";
    
    // Read the SQL file to extract INSERT statements for post_assessment
    $sqlContent = file_get_contents('database/sql3806785.sql');
    
    // Extract INSERT statements for post_assessment
    $pattern = '/INSERT INTO `post_assessment`.*?;/s';
    preg_match($pattern, $sqlContent, $matches);
    
    if ($matches) {
        $insertSQL = $matches[0];
        echo "Executing INSERT statement...\n";
        $pdo->exec($insertSQL);
        echo "✓ Data inserted successfully\n";
    } else {
        echo "! No INSERT data found for post_assessment\n";
    }
    
    // Verify the table was created and populated
    $countResult = $pdo->query("SELECT COUNT(*) as count FROM post_assessment");
    $count = $countResult->fetch(PDO::FETCH_ASSOC);
    echo "✓ post_assessment table created with {$count['count']} records\n";
    
    // Final verification: count all tables
    $tablesResult = $pdo->query("SHOW TABLES");
    $tables = $tablesResult->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Railway database now has " . count($tables) . " tables\n";
    
    echo "\nMigration complete! All tables are now present in Railway database.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>