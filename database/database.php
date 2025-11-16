<?php

class Database 
{
private $servername;
private $username;
private $password;
private $dbname;
public $conn = null;

public function __construct() {
    // Load .env file for configuration
    $this->loadEnvFile();
    
    // Always use Railway database - production ready configuration
    $this->servername = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'mainline.proxy.rlwy.net:31782';
    $this->username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
    $this->password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'LYeUTqrnaDxpSAdWiirrGhFAcVVyNMGJ';
    $this->dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'railway';
    
    // Log environment detection for debugging
    error_log("Railway DB Config - Host: {$this->servername}, DB: {$this->dbname}, User: {$this->username}");
    error_log("Environment: " . (isset($_SERVER['RENDER']) ? 'Render Production' : 'Local Development'));
    
    $this->connect();
}

private function connect() {
    try {
        // Parse host and port
        $hostParts = explode(':', $this->servername);
        $hostname = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : 3306;
        
        // Railway-optimized connection options
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 60, // Extended timeout for Railway external connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        
        // Add SSL configuration for Railway external connections
        if (strpos($this->servername, 'railway') !== false || strpos($this->servername, 'rlwy.net') !== false) {
            // Try multiple SSL certificate paths for different environments
            $sslPaths = [
                '/etc/ssl/certs/ca-certificates.crt', // Ubuntu/Debian (Render)
                '/etc/pki/tls/certs/ca-bundle.crt',   // CentOS/RHEL
                '/usr/local/share/certs/ca-root-nss.crt', // FreeBSD
                '/etc/ssl/cert.pem' // macOS
            ];
            
            $sslFound = false;
            foreach ($sslPaths as $sslPath) {
                if (file_exists($sslPath)) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslPath;
                    $sslFound = true;
                    error_log("Using SSL certificate: {$sslPath}");
                    break;
                }
            }
            
            if (!$sslFound) {
                error_log("No SSL certificate found, attempting connection without SSL CA file");
            }
            
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        
        $dsn = "mysql:host=$hostname;port=$port;dbname={$this->dbname};charset=utf8mb4";
        
        error_log("Attempting Railway connection with DSN: {$dsn}");
        error_log("SSL options present: " . (isset($options[PDO::MYSQL_ATTR_SSL_CA]) ? 'Yes' : 'No'));
        
        // Try SSL connection first, fallback to non-SSL if needed
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            error_log("Railway database connected successfully with SSL");
        } catch (PDOException $sslError) {
            // Remove SSL options and retry
            error_log("Railway SSL connection failed, trying without SSL: " . $sslError->getMessage());
            
            // Remove all SSL-related options
            unset($options[PDO::MYSQL_ATTR_SSL_CA]);
            unset($options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);
            
            try {
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
                error_log("Railway database connected successfully without SSL");
            } catch (PDOException $nonSslError) {
                error_log("Railway connection failed even without SSL: " . $nonSslError->getMessage());
                throw $nonSslError;
            }
        }
        
    } catch(PDOException $e) {
        error_log("Railway database connection failed: " . $e->getMessage());
        error_log("Host: $hostname:$port, Database: " . $this->dbname . ", Username: " . $this->username);
        $this->conn = null;
        throw new Exception("Database connection failed: " . $e->getMessage());
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