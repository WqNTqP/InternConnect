<?php

class Database 
{
private $servername;
private $username;
private $password;
private $dbname;
public $conn = null;

public function __construct() {
    // Load .env file if it exists (for local development)
    $this->loadEnvFile();
    
    // Use environment variables for production, fallback to local values for development
    // On Render, check if we're in production environment
    $isProduction = isset($_SERVER['RENDER']) || !file_exists(__DIR__ . '/../.env');
    
    if ($isProduction) {
        // Production: Use Render environment variables or hardcoded FreeSQLDatabase
        $this->servername = getenv('DB_HOST') ?: 'sql3.freesqldatabase.com:3306';
        $this->username = getenv('DB_USERNAME') ?: 'sql3806785';
        $this->password = getenv('DB_PASSWORD') ?: 'DAl9FGjxvF';
        $this->dbname = getenv('DB_NAME') ?: 'sql3806785';
    } else {
        // Local development: Use .env file or localhost
        $this->servername = $_ENV['DB_HOST'] ?? 'localhost:3306';
        $this->username = $_ENV['DB_USERNAME'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->dbname = $_ENV['DB_NAME'] ?? 'attendancetrackernp';
    }
    
    // Connection established using environment variables
    
    try {
        // Handle port separately if needed
        $hostParts = explode(':', $this->servername);
        $hostname = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : 3306;
        
        // Add connection timeout and additional options for remote connections
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 30,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
            PDO::ATTR_PERSISTENT => false
        ];
        
        $dsn = "mysql:host=$hostname;port=$port;dbname=$this->dbname;charset=utf8";
        $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        
        //echo "connected successfully";
      } catch(PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        error_log("Host: $hostname:$port, Database: " . $this->dbname . ", Username: " . $this->username);
        // Don't echo here as it interferes with session_start()
        $this->conn = null;
      }
}

private function loadEnvFile() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
    }
}

}


?>

