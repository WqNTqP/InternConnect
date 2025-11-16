<?php
// Debug the actual timezone issue in detail
require_once 'database/database.php';

echo "🕒 DEBUGGING TIMEZONE ISSUE IN DETAIL\n";
echo "====================================\n\n";

try {
    $db = new Database();
    
    // Check database timezone settings
    echo "🗄️ Database timezone settings:\n";
    $timezoneQuery = $db->conn->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz, NOW() as db_now");
    $timezoneInfo = $timezoneQuery->fetch(PDO::FETCH_ASSOC);
    echo "   Global timezone: {$timezoneInfo['global_tz']}\n";
    echo "   Session timezone: {$timezoneInfo['session_tz']}\n";
    echo "   Database NOW(): {$timezoneInfo['db_now']}\n\n";
    
    // Check PHP timezone
    echo "🐘 PHP timezone settings:\n";
    echo "   Default timezone: " . date_default_timezone_get() . "\n";
    echo "   PHP time(): " . date('Y-m-d H:i:s') . "\n";
    echo "   PHP time() with timezone: " . date('Y-m-d H:i:s T') . "\n\n";
    
    // Get the most recent draft report
    echo "📄 Latest draft report analysis:\n";
    $reportQuery = $db->conn->query("
        SELECT report_id, updated_at, 
               UNIX_TIMESTAMP(updated_at) as unix_timestamp,
               CONVERT_TZ(updated_at, '+00:00', '+08:00') as manual_ph_conversion
        FROM weekly_reports 
        WHERE status = 'draft' 
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    $report = $reportQuery->fetch(PDO::FETCH_ASSOC);
    
    if ($report) {
        echo "   Report ID: {$report['report_id']}\n";
        echo "   Raw updated_at: {$report['updated_at']}\n";
        echo "   Unix timestamp: {$report['unix_timestamp']}\n";
        echo "   Manual PH conversion: {$report['manual_ph_conversion']}\n\n";
        
        // Test different timezone interpretations
        echo "🧪 Testing different timezone interpretations:\n";
        
        // Scenario 1: Assume DB time is UTC, convert to PH
        echo "   Scenario 1 (DB=UTC → PH):\n";
        $utcDateTime = new DateTime($report['updated_at'], new DateTimeZone('UTC'));
        $phDateTime1 = clone $utcDateTime;
        $phDateTime1->setTimezone(new DateTimeZone('Asia/Manila'));
        echo "     Result: " . $phDateTime1->format('Y-m-d H:i:s T') . " (" . $phDateTime1->format('g:i A') . ")\n";
        
        // Scenario 2: Assume DB time is already in PH timezone
        echo "   Scenario 2 (DB=PH already):\n";
        $phDateTime2 = new DateTime($report['updated_at'], new DateTimeZone('Asia/Manila'));
        echo "     Result: " . $phDateTime2->format('Y-m-d H:i:s T') . " (" . $phDateTime2->format('g:i A') . ")\n";
        
        // Scenario 3: Use unix timestamp
        echo "   Scenario 3 (Unix timestamp → PH):\n";
        $unixDateTime = new DateTime('@' . $report['unix_timestamp']);
        $unixDateTime->setTimezone(new DateTimeZone('Asia/Manila'));
        echo "     Result: " . $unixDateTime->format('Y-m-d H:i:s T') . " (" . $unixDateTime->format('g:i A') . ")\n\n";
        
        // Current time for comparison
        echo "🕐 Current time comparisons:\n";
        $now = new DateTime('now');
        $nowUTC = new DateTime('now', new DateTimeZone('UTC'));
        $nowPH = new DateTime('now', new DateTimeZone('Asia/Manila'));
        
        echo "   System time: " . $now->format('Y-m-d H:i:s T') . " (" . $now->format('g:i A') . ")\n";
        echo "   UTC time: " . $nowUTC->format('Y-m-d H:i:s T') . " (" . $nowUTC->format('g:i A') . ")\n";
        echo "   PH time: " . $nowPH->format('Y-m-d H:i:s T') . " (" . $nowPH->format('g:i A') . ")\n\n";
        
        echo "❓ Which scenario matches your expected 12:24 PM?\n";
        echo "   If none match, the database might be storing times in a different timezone.\n";
    } else {
        echo "   No draft reports found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>