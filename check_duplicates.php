<?php
// Check for duplicate data in Railway database tables
require_once 'database/database.php';

try {
    $db = new Database();
    
    if ($db->conn === null) {
        echo "❌ Database connection failed!\n";
        exit(1);
    }
    
    echo "🔍 Checking for duplicate data in Railway database tables...\n\n";
    
    // Check coordinator table for duplicates
    echo "📋 COORDINATOR TABLE:\n";
    $coordQuery = $db->conn->query("SELECT COORDINATOR_ID, NAME, username, COUNT(*) as count FROM coordinator GROUP BY COORDINATOR_ID, NAME, username HAVING COUNT(*) > 1");
    $coordDuplicates = $coordQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($coordDuplicates) > 0) {
        echo "❌ Found " . count($coordDuplicates) . " duplicate records:\n";
        foreach ($coordDuplicates as $dup) {
            echo "   - ID: {$dup['COORDINATOR_ID']}, Name: {$dup['NAME']}, Count: {$dup['count']}\n";
        }
    } else {
        echo "✅ No duplicates found\n";
    }
    
    // Get total count vs unique count
    $totalCoord = $db->conn->query("SELECT COUNT(*) as total FROM coordinator")->fetch()['total'];
    $uniqueCoord = $db->conn->query("SELECT COUNT(DISTINCT COORDINATOR_ID) as unique_count FROM coordinator")->fetch()['unique_count'];
    echo "   Total records: $totalCoord, Unique IDs: $uniqueCoord\n\n";
    
    // Check interns_details table
    echo "📋 INTERNS_DETAILS TABLE:\n";
    $internsQuery = $db->conn->query("SELECT STUDENT_ID, NAME, EMAIL, COUNT(*) as count FROM interns_details GROUP BY STUDENT_ID, NAME, EMAIL HAVING COUNT(*) > 1");
    $internsDuplicates = $internsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($internsDuplicates) > 0) {
        echo "❌ Found " . count($internsDuplicates) . " duplicate records:\n";
        foreach ($internsDuplicates as $dup) {
            echo "   - Student ID: {$dup['STUDENT_ID']}, Name: {$dup['NAME']}, Count: {$dup['count']}\n";
        }
    } else {
        echo "✅ No duplicates found\n";
    }
    
    $totalInterns = $db->conn->query("SELECT COUNT(*) as total FROM interns_details")->fetch()['total'];
    $uniqueInterns = $db->conn->query("SELECT COUNT(DISTINCT STUDENT_ID) as unique_count FROM interns_details")->fetch()['unique_count'];
    echo "   Total records: $totalInterns, Unique Student IDs: $uniqueInterns\n\n";
    
    // Check host_training_establishment table
    echo "📋 HOST_TRAINING_ESTABLISHMENT TABLE:\n";
    $hteQuery = $db->conn->query("SELECT HTE_ID, COMPANY_NAME, COUNT(*) as count FROM host_training_establishment GROUP BY HTE_ID, COMPANY_NAME HAVING COUNT(*) > 1");
    $hteDuplicates = $hteQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($hteDuplicates) > 0) {
        echo "❌ Found " . count($hteDuplicates) . " duplicate records:\n";
        foreach ($hteDuplicates as $dup) {
            echo "   - HTE ID: {$dup['HTE_ID']}, Company: {$dup['COMPANY_NAME']}, Count: {$dup['count']}\n";
        }
    } else {
        echo "✅ No duplicates found\n";
    }
    
    $totalHTE = $db->conn->query("SELECT COUNT(*) as total FROM host_training_establishment")->fetch()['total'];
    $uniqueHTE = $db->conn->query("SELECT COUNT(DISTINCT HTE_ID) as unique_count FROM host_training_establishment")->fetch()['unique_count'];
    echo "   Total records: $totalHTE, Unique HTE IDs: $uniqueHTE\n\n";
    
    // Check weekly_reports table
    echo "📋 WEEKLY_REPORTS TABLE:\n";
    $reportsQuery = $db->conn->query("SELECT REPORT_ID, STUDENT_ID, WEEK_NUMBER, COUNT(*) as count FROM weekly_reports GROUP BY REPORT_ID, STUDENT_ID, WEEK_NUMBER HAVING COUNT(*) > 1");
    $reportsDuplicates = $reportsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($reportsDuplicates) > 0) {
        echo "❌ Found " . count($reportsDuplicates) . " duplicate records:\n";
        foreach ($reportsDuplicates as $dup) {
            echo "   - Report ID: {$dup['REPORT_ID']}, Student: {$dup['STUDENT_ID']}, Week: {$dup['WEEK_NUMBER']}, Count: {$dup['count']}\n";
        }
    } else {
        echo "✅ No duplicates found\n";
    }
    
    $totalReports = $db->conn->query("SELECT COUNT(*) as total FROM weekly_reports")->fetch()['total'];
    $uniqueReports = $db->conn->query("SELECT COUNT(DISTINCT REPORT_ID) as unique_count FROM weekly_reports")->fetch()['unique_count'];
    echo "   Total records: $totalReports, Unique Report IDs: $uniqueReports\n\n";
    
    // Check post_assessment table (recently created)
    echo "📋 POST_ASSESSMENT TABLE:\n";
    $postQuery = $db->conn->query("SELECT id, student_id, question_id, COUNT(*) as count FROM post_assessment GROUP BY id, student_id, question_id HAVING COUNT(*) > 1");
    $postDuplicates = $postQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($postDuplicates) > 0) {
        echo "❌ Found " . count($postDuplicates) . " duplicate records:\n";
        foreach ($postDuplicates as $dup) {
            echo "   - ID: {$dup['id']}, Student: {$dup['student_id']}, Question: {$dup['question_id']}, Count: {$dup['count']}\n";
        }
    } else {
        echo "✅ No duplicates found\n";
    }
    
    $totalPost = $db->conn->query("SELECT COUNT(*) as total FROM post_assessment")->fetch()['total'];
    $uniquePost = $db->conn->query("SELECT COUNT(DISTINCT id) as unique_count FROM post_assessment")->fetch()['unique_count'];
    echo "   Total records: $totalPost, Unique IDs: $uniquePost\n\n";
    
    echo "🔍 SUMMARY:\n";
    echo "If any table shows 'Total records' > 'Unique IDs', that indicates duplicate data.\n";
    echo "This usually happens when import scripts run multiple times.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>