<?php

/**
 * Path Configuration Helper
 * Automatically detects whether running on localhost or live server
 * and sets appropriate paths for database and file includes
 */

class PathConfig {
    private static $basePath = null;
    
    /**
     * Get the base path for the project
     * Handles both localhost (XAMPP) and live server environments
     */
    public static function getBasePath() {
        if (self::$basePath !== null) {
            return self::$basePath;
        }
        
        // Get the current script's directory
        $currentDir = __DIR__;
        
        // Go up one level to get the project root (since this file is in config/)
        $projectRoot = dirname($currentDir);
        
        // For localhost detection, check if we're running on XAMPP or similar
        $isLocalhost = (
            strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
            strpos($_SERVER['HTTP_HOST'] ?? '', '::1') !== false ||
            !isset($_SERVER['HTTP_HOST'])
        );
        
        if ($isLocalhost) {
            // Localhost: Use the project directory directly
            self::$basePath = $projectRoot;
        } else {
            // Live server: Use document root or project root based on structure
            // Check if we're in a subdirectory of document root
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
            
            // If project root contains document root path, we're probably in a subfolder
            if (!empty($docRoot) && strpos($projectRoot, $docRoot) === 0) {
                // We're in a subfolder of document root, use project root
                self::$basePath = $projectRoot;
            } else {
                // We're at document root level, use document root
                self::$basePath = $docRoot;
            }
        }
        
        return self::$basePath;
    }
    
    /**
     * Get the database include path
     */
    public static function getDatabasePath() {
        return self::getBasePath() . '/database/database.php';
    }
    
    /**
     * Get path for any file relative to project root
     */
    public static function getProjectPath($relativePath = '') {
        $basePath = self::getBasePath();
        if (!empty($relativePath)) {
            return $basePath . '/' . ltrim($relativePath, '/');
        }
        return $basePath;
    }
    
    /**
     * Check if running on localhost
     */
    public static function isLocalhost() {
        return (
            strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
            strpos($_SERVER['HTTP_HOST'] ?? '', '::1') !== false ||
            !isset($_SERVER['HTTP_HOST'])
        );
    }
    
    /**
     * Debug function to show current path configuration
     */
    public static function debug() {
        return [
            'isLocalhost' => self::isLocalhost(),
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'Not set',
            'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'Not set',
            'Current __DIR__' => __DIR__,
            'Calculated basePath' => self::getBasePath(),
            'Database path' => self::getDatabasePath(),
            'Database file exists' => file_exists(self::getDatabasePath()) ? 'Yes' : 'No'
        ];
    }
}

// Legacy compatibility: Set $path variable for existing code
$path = PathConfig::getProjectPath();

?>