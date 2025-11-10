<?php
/**
 * Logo File Diagnostic Script
 * Checks for mismatches between database LOGO entries and actual files in uploads/hte_logos/
 */

// Set up error reporting and output
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// Include database connection
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
require_once $basePath."/database/database.php";

echo "<html><head><title>Logo File Diagnostic</title></head><body>";
echo "<h1>Logo File Diagnostic Report</h1>";
echo "<hr>";

try {
    $dbo = new Database();
    
    // Get all HTE records with logos
    $stmt = $dbo->conn->prepare("SELECT HTE_ID, NAME, LOGO FROM host_training_establishment WHERE LOGO IS NOT NULL AND LOGO != ''");
    $stmt->execute();
    $hteRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all files in uploads/hte_logos/
    $logoDir = $basePath . "/uploads/hte_logos/";
    $actualFiles = [];
    if (is_dir($logoDir)) {
        $files = scandir($logoDir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && is_file($logoDir . $file)) {
                $actualFiles[] = $file;
            }
        }
    }
    
    echo "<h2>Summary</h2>";
    echo "<p><strong>Database records with logos:</strong> " . count($hteRecords) . "</p>";
    echo "<p><strong>Files in uploads/hte_logos/:</strong> " . count($actualFiles) . "</p>";
    echo "<p><strong>Logo directory:</strong> " . htmlspecialchars($logoDir) . "</p>";
    
    echo "<h2>Database Logo Entries</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>HTE_ID</th><th>Company Name</th><th>Logo Filename</th><th>File Exists?</th><th>Action</th></tr>";
    
    $missingFiles = [];
    $foundFiles = [];
    
    foreach ($hteRecords as $record) {
        $logoFilename = $record['LOGO'];
        
        // Handle both formats: just filename or full path
        if (strpos($logoFilename, '/') !== false) {
            $logoFilename = basename($logoFilename);
        }
        
        $fileExists = in_array($logoFilename, $actualFiles);
        $status = $fileExists ? '✅ Yes' : '❌ No';
        $action = $fileExists ? 'OK' : 'Re-upload needed';
        
        if (!$fileExists) {
            $missingFiles[] = [
                'hte_id' => $record['HTE_ID'],
                'name' => $record['NAME'],
                'filename' => $logoFilename
            ];
        } else {
            $foundFiles[] = $logoFilename;
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($record['HTE_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($record['NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($record['LOGO']) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "<td>" . $action . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Files on Disk</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Filename</th><th>Used in DB?</th><th>File Size</th><th>Action</th></tr>";
    
    $orphanedFiles = [];
    foreach ($actualFiles as $file) {
        $usedInDb = in_array($file, $foundFiles);
        $status = $usedInDb ? '✅ Yes' : '⚠️ Orphaned';
        $action = $usedInDb ? 'OK' : 'Consider deleting';
        $fileSize = filesize($logoDir . $file);
        
        if (!$usedInDb) {
            $orphanedFiles[] = $file;
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($file) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "<td>" . number_format($fileSize) . " bytes</td>";
        echo "<td>" . $action . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Summary and recommendations
    echo "<h2>Issues Found</h2>";
    
    if (count($missingFiles) > 0) {
        echo "<h3>❌ Missing Files (Database has entry but file doesn't exist)</h3>";
        echo "<ul>";
        foreach ($missingFiles as $missing) {
            echo "<li><strong>" . htmlspecialchars($missing['name']) . "</strong> (ID: " . $missing['hte_id'] . ") - Missing: " . htmlspecialchars($missing['filename']) . "</li>";
        }
        echo "</ul>";
        echo "<p><strong>Fix:</strong> Use the 'Update Logo' button in the dashboard to re-upload these logos.</p>";
    }
    
    if (count($orphanedFiles) > 0) {
        echo "<h3>⚠️ Orphaned Files (File exists but not used in database)</h3>";
        echo "<ul>";
        foreach ($orphanedFiles as $orphan) {
            echo "<li>" . htmlspecialchars($orphan) . "</li>";
        }
        echo "</ul>";
        echo "<p><strong>Fix:</strong> These files can be safely deleted to save space, or you may want to check if they should be assigned to a company.</p>";
    }
    
    if (count($missingFiles) == 0 && count($orphanedFiles) == 0) {
        echo "<p>✅ <strong>All good!</strong> Database and files are in sync.</p>";
    }
    
    // Quick fix options
    echo "<h2>Quick Fix Options</h2>";
    
    if (count($missingFiles) > 0) {
        echo "<h3>Option 1: Clear database entries for missing files</h3>";
        echo "<p>Run this SQL to remove logo references for missing files:</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
        echo "UPDATE host_training_establishment SET LOGO = NULL WHERE HTE_ID IN (";
        $ids = array_map(function($m) { return $m['hte_id']; }, $missingFiles);
        echo implode(', ', $ids);
        echo ");</pre>";
    }
    
    if (count($orphanedFiles) > 0) {
        echo "<h3>Option 2: Delete orphaned files</h3>";
        echo "<p>You can delete these files using:</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
        foreach ($orphanedFiles as $orphan) {
            echo "unlink('" . htmlspecialchars($logoDir . $orphan) . "');\n";
        }
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><em>Generated on: " . date('Y-m-d H:i:s') . "</em></p>";
echo "</body></html>";
?>