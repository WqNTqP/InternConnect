<?php
// Test both AJAX handlers for correct timezone conversion
require_once 'database/database.php';

echo "🕒 FINAL TIMEZONE VERIFICATION TEST\n";
echo "==================================\n\n";

// Simulate an AJAX request to test the timezone conversion
$_POST['action'] = 'getRecentReportStatus';
$_POST['studentId'] = '59828881'; // Use existing student ID

echo "🧪 Testing studentDashboardAjax timezone conversion:\n";

// Capture output from studentDashboardAjax
ob_start();
include 'ajaxhandler/studentDashboardAjax.php';
$dashboardResponse = ob_get_clean();

$dashboardData = json_decode($dashboardResponse, true);
if ($dashboardData && $dashboardData['status'] === 'success' && !empty($dashboardData['data'])) {
    $reports = $dashboardData['data'];
    if (!empty($reports)) {
        $firstReport = $reports[0];
        echo "   ✅ Response received\n";
        echo "   Updated at: {$firstReport['updated_at']}\n";
        
        $testTime = new DateTime($firstReport['updated_at']);
        echo "   Display format: " . $testTime->format('g:i A') . "\n";
    } else {
        echo "   ⚠️ No reports found\n";
    }
} else {
    echo "   ❌ Error in response: " . substr($dashboardResponse, 0, 100) . "...\n";
}

echo "\n🧪 Testing weeklyReportAjax timezone conversion:\n";

// Reset POST for weekly report test
$_POST = [
    'action' => 'getWeeklyReport',
    'studentId' => '59828881',
    'week' => date('Y-m-d') // Current week
];

// Capture output from weeklyReportAjax
ob_start();
include 'ajaxhandler/weeklyReportAjax.php';
$weeklyResponse = ob_get_clean();

$weeklyData = json_decode($weeklyResponse, true);
if ($weeklyData && $weeklyData['status'] === 'success' && !empty($weeklyData['data']['report'])) {
    $report = $weeklyData['data']['report'];
    echo "   ✅ Response received\n";
    echo "   Updated at: {$report['updated_at']}\n";
    
    $testTime = new DateTime($report['updated_at']);
    echo "   Display format: " . $testTime->format('g:i A') . "\n";
} else {
    echo "   ⚠️ No report found or different response structure\n";
    echo "   Response: " . substr($weeklyResponse, 0, 200) . "...\n";
}

echo "\n📊 FINAL RESULT:\n";
echo "================\n";
echo "Both AJAX handlers should now return timestamps in Philippine timezone.\n";
echo "The draft save time should show around 12:30 PM instead of 4:30 AM.\n";
echo "\n🎯 Next steps:\n";
echo "1. Test by saving a draft in the student dashboard\n";
echo "2. Check that the 'Draft last saved at' shows correct Philippine time\n";
echo "3. The time should match your current local time (around 12:30 PM)\n";
?>