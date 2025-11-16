<?php
/**
 * Compare Railway tables vs Original backup tables
 */

echo "=== TABLE COMPARISON: RAILWAY vs ORIGINAL BACKUP ===\n\n";

// Tables currently in Railway (from your list)
$railwayTables = [
    'coordinator',
    'coordinator_evaluation', 
    'evaluation_questions',
    'host_training_establishment',
    'intern_details',
    'interns_attendance',
    'interns_details', 
    'internship_needs',
    'notifications',
    'past_data',
    'pending_attendance',
    'post_analysis_summary',
    'pre_assessment',
    'report_images',
    'session_details',
    'student_deletion_log',
    'student_evaluation',
    'student_questions',
    'weekly_reports'
];

// Tables that should be there (from original backup analysis)
$originalTables = [
    'coordinator',
    'coordinator_evaluation',
    'evaluation_questions', 
    'host_training_establishment',
    'internship_needs',
    'interns_attendance',
    'interns_details',
    'intern_details',
    'notifications',
    'past_data',
    'pending_attendance',
    'post_analysis_summary',
    'post_assessment',  // <- THIS IS THE MISSING ONE!
    'pre_assessment',
    'report_images',
    'session_details',
    'student_deletion_log',
    'student_evaluation',
    'student_questions',
    'weekly_reports'
];

echo "Railway tables: " . count($railwayTables) . "\n";
echo "Original tables: " . count($originalTables) . "\n\n";

$missing = array_diff($originalTables, $railwayTables);
$extra = array_diff($railwayTables, $originalTables);

if (!empty($missing)) {
    echo "❌ MISSING TABLES (" . count($missing) . "):\n";
    foreach ($missing as $table) {
        echo "• $table\n";
    }
    echo "\n";
}

if (!empty($extra)) {
    echo "➕ EXTRA TABLES (" . count($extra) . "):\n";
    foreach ($extra as $table) {
        echo "• $table\n";
    }
    echo "\n";
}

echo "🔍 THE MISSING TABLE: post_assessment\n\n";

echo "This explains why the import had errors with statements referencing 'post_assessment'.\n";
echo "The table creation for post_assessment probably failed during import.\n\n";

echo "=== SOLUTION ===\n";
echo "I'll check the Railway database and create the missing post_assessment table.\n";
?>