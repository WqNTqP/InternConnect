<?php

class Database 
{
private $servername;
private $username;
private $password;
private $dbname;
private $fallbackConfig;
public $conn = null;

public function __construct() {
    // Load .env file if it exists (for local development)
    $this->loadEnvFile();
    
    // Use environment variables for production, fallback to local values for development
    // On Render, check if we're in production environment
    $isProduction = isset($_SERVER['RENDER']) || !file_exists(__DIR__ . '/../.env');
    
    if ($isProduction) {
        // Production: Use Railway database with proper SSL configuration
        $this->servername = getenv('DB_HOST') ?: 'mainline.proxy.rlwy.net:31782';
        $this->username = getenv('DB_USERNAME') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: 'LYeUTqrnaDxpSAdWiirrGhFAcVVyNMGJ';
        $this->dbname = getenv('DB_NAME') ?: 'railway';
    } else {
        // Local development: Try localhost first, fallback to live DB if needed
        $this->servername = $_ENV['DB_HOST'] ?? 'localhost:3306';
        $this->username = $_ENV['DB_USERNAME'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->dbname = $_ENV['DB_NAME'] ?? 'attendancetrackernp';
    }
    
    // Store fallback credentials for Railway database (local development)
    $this->fallbackConfig = [
        'servername' => 'mainline.proxy.rlwy.net:31782',
        'username' => 'root',
        'password' => 'LYeUTqrnaDxpSAdWiirrGhFAcVVyNMGJ',
        'dbname' => 'railway'
    ];
    
    // Connection established using environment variables
    
    try {
        // Handle port separately if needed
        $hostParts = explode(':', $this->servername);
        $hostname = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : 3306;
        
        // Add connection timeout and SSL options for Railway external connections
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 60, // Increased timeout for Railway connection
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        // Add SSL configuration for Railway external connections (production only)
        if ($isProduction && strpos($this->servername, 'railway') !== false) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        
        $dsn = "mysql:host=$hostname;port=$port;dbname=$this->dbname;charset=utf8mb4";
        
        // For Railway connections, try SSL first, fallback to non-SSL
        if ($isProduction && strpos($this->servername, 'railway') !== false) {
            try {
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $sslError) {
                // If SSL connection fails, try without SSL
                error_log("Railway SSL connection failed, trying without SSL: " . $sslError->getMessage());
                unset($options[PDO::MYSQL_ATTR_SSL_CA]);
                unset($options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            }
        } else {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        }
        
        //echo "connected successfully";
      } catch(PDOException $e) {
        error_log("Primary database connection failed: " . $e->getMessage());
        error_log("Host: $hostname:$port, Database: " . $this->dbname . ", Username: " . $this->username);
        
        // If local connection fails and we have fallback config, try live database
        if (!$isProduction && $this->fallbackConfig) {
            try {
                error_log("Attempting fallback to live database...");
                $fallbackParts = explode(':', $this->fallbackConfig['servername']);
                $fallbackHost = $fallbackParts[0];
                $fallbackPort = isset($fallbackParts[1]) ? $fallbackParts[1] : 3306;
                
                $fallbackDsn = "mysql:host=$fallbackHost;port=$fallbackPort;dbname={$this->fallbackConfig['dbname']};charset=utf8";
                $this->conn = new PDO($fallbackDsn, $this->fallbackConfig['username'], $this->fallbackConfig['password'], $options);
                error_log("Successfully connected to live database as fallback");
            } catch(PDOException $fallbackError) {
                error_log("Fallback connection also failed: " . $fallbackError->getMessage());
                $this->conn = null;
            }
        } else {
            $this->conn = null;
        }
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