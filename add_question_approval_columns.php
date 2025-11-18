<?php
/**
 * Script to add approval workflow columns to student_questions table
 * This enables the question approval system for post-assessments
 */

require_once 'database/database.php';

try {
    $db = new Database();
    $conn = $db->conn;
    
    echo "Adding approval workflow columns to student_questions table...\n";
    
    // Add approval status column
    $sql1 = "ALTER TABLE student_questions 
             ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'";
    $conn->exec($sql1);
    echo "✓ Added approval_status column\n";
    
    // Add approved_by column (references coordinator ID)
    $sql2 = "ALTER TABLE student_questions 
             ADD COLUMN approved_by INT NULL";
    $conn->exec($sql2);
    echo "✓ Added approved_by column\n";
    
    // Add approval_date column
    $sql3 = "ALTER TABLE student_questions 
             ADD COLUMN approval_date TIMESTAMP NULL";
    $conn->exec($sql3);
    echo "✓ Added approval_date column\n";
    
    // Add rejection_reason column
    $sql4 = "ALTER TABLE student_questions 
             ADD COLUMN rejection_reason TEXT NULL";
    $conn->exec($sql4);
    echo "✓ Added rejection_reason column\n";
    
    // Update existing records to 'approved' status (for backwards compatibility)
    $sql5 = "UPDATE student_questions 
             SET approval_status = 'approved', 
                 approval_date = created_at 
             WHERE approval_status = 'pending'";
    $result = $conn->exec($sql5);
    echo "✓ Updated {$result} existing records to 'approved' status\n";
    
    echo "\n✅ Question approval system successfully added to database!\n";
    echo "\nNew workflow:\n";
    echo "1. Students submit questions → status: 'pending'\n";
    echo "2. Admin approves/rejects → status: 'approved'/'rejected'\n";
    echo "3. Only approved questions allow assessment submission\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nIf columns already exist, this is normal.\n";
}
?>