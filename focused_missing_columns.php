<?php
/**
 * Focused Database Schema Analysis
 * Identifies legitimate missing columns by analyzing specific code patterns
 */

// Known table structures from backup
$backupTables = [
    'host_training_establishment' => [
        'HTE_ID', 'NAME', 'INDUSTRY', 'ADDRESS', 'CONTACT_EMAIL', 
        'CONTACT_PERSON', 'CONTACT_NUMBER', 'LOGO'
    ],
    'session_details' => [
        'ID', 'YEAR', 'TERM'
    ],
    'interns_attendance' => [
        'COORDINATOR_ID', 'HTE_ID', 'ID', 'INTERNS_ID', 'ON_DATE', 'TIMEIN', 'TIMEOUT'
    ],
    'pre_assessment' => [
        'id', 'STUDENT_ID', 'CC 102', 'CC 103', 'PF 101', 'CC 104', 'IPT 101', 'IPT 102', 
        'CC 106', 'CC 105', 'IM 101', 'IM 102', 'HCI 101', 'HCI 102', 'WS 101', 'NET 101', 
        'NET 102', 'IAS 101', 'IAS 102', 'CAP 101', 'CAP 102', 'SP 101', 'soft_skill', 
        'communication_skill', 'technical_skill', 'ojt_placement', 'prediction_reasoning', 
        'prediction_probabilities', 'post_systems_development_avg', 'post_research_avg', 
        'post_business_operations_avg', 'post_technical_support_avg', 'self_systems_development_avg', 
        'self_research_avg', 'self_business_operations_avg', 'self_technical_support_avg', 'supervisor_comment'
    ]
];

// Expected columns based on code analysis
$expectedColumns = [
    'host_training_establishment' => [
        'HTE_ID', 'NAME', 'INDUSTRY', 'ADDRESS', 'CONTACT_EMAIL', 
        'CONTACT_PERSON', 'CONTACT_NUMBER', 'LOGO',
        // MOA columns found in code
        'MOA_FILE_URL', 'MOA_PUBLIC_ID', 'MOA_START_DATE', 'MOA_END_DATE', 'MOA_UPLOAD_DATE'
    ],
    'session_details' => [
        'ID', 'YEAR', 'TERM',
        // Additional columns found in code
        'SESSION_ID', 'SESSION_NAME', 'START_DATE', 'END_DATE'
    ],
    'interns_attendance' => [
        'COORDINATOR_ID', 'HTE_ID', 'ID', 'INTERNS_ID', 'ON_DATE', 'TIMEIN', 'TIMEOUT',
        // Alternative column names found in code
        'ATTENDANCE_DATE', 'TIME_IN', 'TIME_OUT'
    ]
];

echo "=== FOCUSED MISSING COLUMNS ANALYSIS ===\n\n";

$criticalMissing = [];

foreach ($expectedColumns as $table => $expected) {
    $backup = $backupTables[$table] ?? [];
    $missing = array_diff($expected, $backup);
    
    if (!empty($missing)) {
        $criticalMissing[$table] = $missing;
        echo "TABLE: $table\n";
        echo "  Current backup columns: " . implode(', ', $backup) . "\n";
        echo "  ❌ MISSING: " . implode(', ', $missing) . "\n\n";
    }
}

if (!empty($criticalMissing)) {
    echo "=== REQUIRED ALTER TABLE STATEMENTS ===\n\n";
    
    // Host Training Establishment MOA columns
    if (isset($criticalMissing['host_training_establishment'])) {
        echo "-- Add MOA columns to host_training_establishment\n";
        echo "ALTER TABLE `host_training_establishment`\n";
        echo "  ADD COLUMN `MOA_FILE_URL` varchar(500) DEFAULT NULL,\n";
        echo "  ADD COLUMN `MOA_PUBLIC_ID` varchar(255) DEFAULT NULL,\n";
        echo "  ADD COLUMN `MOA_START_DATE` date DEFAULT NULL,\n";
        echo "  ADD COLUMN `MOA_END_DATE` date DEFAULT NULL,\n";
        echo "  ADD COLUMN `MOA_UPLOAD_DATE` timestamp NULL DEFAULT NULL;\n\n";
    }
    
    // Session details improvements
    if (isset($criticalMissing['session_details'])) {
        echo "-- Add improved session tracking to session_details\n";
        echo "ALTER TABLE `session_details`\n";
        echo "  ADD COLUMN `SESSION_ID` int(11) AUTO_INCREMENT,\n";
        echo "  ADD COLUMN `SESSION_NAME` varchar(100) DEFAULT NULL,\n";
        echo "  ADD COLUMN `START_DATE` date DEFAULT NULL,\n";
        echo "  ADD COLUMN `END_DATE` date DEFAULT NULL,\n";
        echo "  ADD PRIMARY KEY (`SESSION_ID`);\n\n";
    }
    
    // Attendance standardization
    if (isset($criticalMissing['interns_attendance'])) {
        echo "-- Add standardized attendance columns to interns_attendance\n";
        echo "ALTER TABLE `interns_attendance`\n";
        echo "  ADD COLUMN `ATTENDANCE_DATE` date DEFAULT NULL,\n";
        echo "  ADD COLUMN `TIME_IN` time DEFAULT NULL,\n";
        echo "  ADD COLUMN `TIME_OUT` time DEFAULT NULL;\n\n";
    }
}

// Check for specific files that use MOA functionality
echo "=== CODE FILES USING MOA FUNCTIONALITY ===\n\n";

$moaFiles = [
    'ajaxhandler/attendanceAJAX.php',
    'database/attendanceDetails.php',
    'mainDashboard.php'
];

foreach ($moaFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $moaReferences = [];
        
        // Find MOA-related function calls and references
        if (preg_match_all('/(MOA_[A-Z_]+|addHTEWithMOA|getAdminHTEWithMOA)/i', $content, $matches)) {
            $moaReferences = array_unique($matches[1]);
        }
        
        if (!empty($moaReferences)) {
            echo "File: $file\n";
            echo "  MOA references: " . implode(', ', $moaReferences) . "\n";
        }
    }
}

echo "\n=== RESTORATION PRIORITY ===\n\n";
echo "🔴 CRITICAL - MOA Functionality:\n";
echo "   The host_training_establishment table is missing 5 MOA columns\n";
echo "   This will cause errors when adding/updating HTEs with MOA documents\n\n";

echo "🟡 MODERATE - Session Management:\n";
echo "   The session_details table could benefit from better session tracking\n\n";

echo "🟢 LOW - Attendance Column Names:\n";
echo "   Alternative column names for attendance (compatibility issue)\n\n";

echo "=== RECOMMENDED ACTION PLAN ===\n\n";
echo "1. Import backup to new InfinityFree database:\n";
echo "   mysql -u [user] -p [database] < database/sql3806785.sql\n\n";

echo "2. Add critical MOA columns (REQUIRED):\n";
echo "   Run the ALTER TABLE statement for host_training_establishment above\n\n";

echo "3. Update database configuration:\n";
echo "   Update database/database.php with new InfinityFree credentials\n\n";

echo "4. Test MOA upload functionality:\n";
echo "   Try adding a new HTE with MOA document to verify everything works\n\n";
?>