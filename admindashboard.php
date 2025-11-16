<?php 
// Include the database and session handling
// Old code - commented out
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

// Initialize database connection
$dbo = new Database();

// Check if database connection is successful
if ($dbo->conn === null) {
    die("Database connection failed. Please check your connection settings.");
}

// Get coordinator's HTE_ID first for filtering
session_start();
$coordinatorId = $_SESSION["admin_user"] ?? null;
if (!$coordinatorId) {
    header("Location: admin.php");
    exit();
}

// Get coordinator's HTE_ID for proper filtering
$coordinatorHteId = null;
try {
    $stmt = $dbo->conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $stmt->execute([$coordinatorId]);
    $coordinatorData = $stmt->fetch(PDO::FETCH_ASSOC);
    $coordinatorHteId = $coordinatorData['HTE_ID'] ?? null;
} catch (Exception $e) {
    error_log("Error getting coordinator HTE_ID: " . $e->getMessage());
}

// Fetch pending attendance records ONLY for coordinator's HTE
$pendingRecords = [];
if ($coordinatorHteId) {
    $stmt = $dbo->conn->prepare("
        SELECT pa.*, id.STUDENT_ID
        FROM pending_attendance pa
        LEFT JOIN interns_details id ON pa.INTERNS_ID = id.INTERNS_ID
        WHERE pa.status = 'pending' AND pa.HTE_ID = ?
    ");
    $stmt->execute([$coordinatorHteId]);
    $pendingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Set adminId for use in the template
$adminId = $coordinatorId;

// Debug: Log the admin ID for troubleshooting
error_log("Admin Dashboard Debug - coordinatorId: " . ($coordinatorId ?? 'NULL'));
error_log("Admin Dashboard Debug - adminId: " . ($adminId ?? 'NULL'));

// Get admin details for display
try {
    $stmt = $dbo->conn->prepare("
        SELECT c.*, h.NAME as HTE_NAME 
        FROM coordinator c 
        LEFT JOIN host_training_establishment h ON c.HTE_ID = h.HTE_ID 
        WHERE c.COORDINATOR_ID = ?
    ");
    $stmt->execute([$coordinatorId]);
    $adminDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    $adminName = $adminDetails ? ($adminDetails['NAME'] ?? 'Admin') : 'Admin';
    $name = $adminName;
} catch (Exception $e) {
    error_log("Error loading admin details: " . $e->getMessage());
    $adminName = 'Admin';
    $name = 'Admin';
}

// Initialize dashboard variables with default values
$name = $name ?? 'Admin';
$attendanceStats = ['present' => 0, 'on_time' => 0, 'late' => 0];
$presentList = [];
$onTimeList = [];
$lateList = [];

// Get students assigned to this coordinator's HTE
try {
    
    if ($coordinatorHteId) {
        // Get students assigned to this coordinator's HTE
        $stmt = $dbo->conn->prepare("
            SELECT
                id.STUDENT_ID,
                id.INTERNS_ID,
                id.NAME,
                id.SURNAME,
                id.EMAIL,
                id.CONTACT_NUMBER
            FROM interns_details id
            JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
            WHERE itd.HTE_ID = ?
            ORDER BY id.SURNAME ASC, id.NAME ASC
        ");
        $stmt->execute([$coordinatorHteId]);
        $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log("Coordinator $coordinatorId has no HTE_ID assigned");
        $allStudents = [];
    }
} catch (Exception $e) {
    error_log("Error loading students for evaluation: " . $e->getMessage());
    $allStudents = [];
}

// Set total students count from the query result
$totalStudents = count($allStudents);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admindashboard.css">
    <link rel="icon" type="image/x-icon" href="icon/favicon.ico">
    <title>Admin Dashboard - Attendance Tracker</title>
</head>
<body>
    <div class="page">
        <div class="top-header">
            <div class="header-left">
                <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle Sidebar">&#9776;</button>
                <div class="sidebar-logo">
                    <div class="logo" onclick="window.location.href='admindashboard.php';">
                        <span>InternConnect</span>
                    </div>
                </div>
            </div>
            <div class="user-profile" id="userProfile">
                <div class="relative">
                    <button id="userDropdownToggle" class="modern-user-dropdown">
                        <div class="user-avatar">
                            <?php 
                                $nameParts = explode(' ', $adminName);
                                $initials = (isset($nameParts[0]) ? substr($nameParts[0], 0, 1) : 'A') . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : '');
                            ?>
                            <span class="avatar-initials"><?php echo $initials; ?></span>
                        </div>
                        <span id="userName" class="user-name" data-admin-id="<?php echo htmlspecialchars($adminId); ?>" style="display: none;"><?php echo htmlspecialchars($adminName); ?></span>
                        <!-- Debug: Admin ID for troubleshooting -->
                        <!-- AdminID: <?php echo htmlspecialchars($adminId ?? 'NOT SET'); ?> -->
                        <div class="dropdown-arrow">
                            <i class="fas fa-chevron-down" id="dropdownArrow"></i>
                        </div>
                    </button>
                    <div id="userDropdown" class="modern-dropdown-menu">
                        <div class="dropdown-header">
                            <div class="header-avatar">
                                <span class="header-initials"><?php echo $initials; ?></span>
                            </div>
                            <div class="header-info">
                                <div class="header-name"><?php echo htmlspecialchars($adminName); ?></div>
                                <div class="header-email"><?php echo htmlspecialchars($adminDetails['EMAIL'] ?? 'Not available'); ?></div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-section">
                            <button id="btnProfile" class="dropdown-item">
                                <div class="item-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="item-content">
                                    <span class="item-title">Profile</span>
                                    <span class="item-subtitle">View and edit profile</span>
                                </div>
                            </button>
                            <button id="logoutBtn" class="dropdown-item logout-item">
                                <div class="item-icon">
                                    <i class="fas fa-sign-out-alt"></i>
                                </div>
                                <div class="item-content">
                                    <span class="item-title">Sign Out</span>
                                    <span class="item-subtitle">Logout from account</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="sidebar">
            <ul class="sidebar-menu">
                <li class="sidebar-item active" id="dashboardTab"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></li>
                <li class="sidebar-item" id="pendingTab"><i class="fas fa-user-check"></i> <span>Attendance</span></li>
                <li class="sidebar-item" id="historyTab"><i class="fas fa-history"></i> <span>History</span></li>
                <li class="sidebar-item" id="reportsTab"><i class="fas fa-file-alt"></i> <span>Reports</span></li>
                <li class="sidebar-item" id="evaluationTab"><i class="fas fa-star"></i> <span>Evaluation</span></li>
                <li class="sidebar-item" id="contralTab"><i class="fas fa-cogs"></i> <span>Control</span></li>
            </ul>
        </div>

        <div class="content-area">
            <h1>Welcome, <?php echo htmlspecialchars($name); ?></h1>




    <!-- Evaluation Tab Content -->
        <div id="evaluationTabContent" class="tab-content" style="display: none;">
            <div class="admin-eval-container admin-eval-main-wrapper" style="display: flex; gap: 32px; padding: 24px 0;">
                <div class="admin-eval-sidebar admin-eval-student-list-section" style="width: 260px; background: #f7f7f7; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 16px; display: flex; flex-direction: column;">
                    <input type="text" class="admin-eval-search" placeholder="Search students..." style="padding: 8px 12px; border-radius: 4px; border: 1px solid #ccc; margin-bottom: 16px; font-size: 16px;">
                    <ul class="admin-eval-student-list" style="list-style: none; padding: 0; margin: 0; max-height: 600px; overflow-y: auto; border: 1px solid #e5e7eb; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                        <?php if (!empty($allStudents)): ?>
                            <?php foreach ($allStudents as $i => $student): ?>
                                <li class="admin-eval-student<?php echo $i === 0 ? ' active' : ''; ?>" style="padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; cursor: pointer; background: <?php echo $i === 0 ? 'linear-gradient(135deg, #3b82f6, #1d4ed8)' : '#fff'; ?>; color: <?php echo $i === 0 ? '#fff' : '#374151'; ?>; font-weight: <?php echo $i === 0 ? '600' : '500'; ?>; border: 2px solid <?php echo $i === 0 ? 'transparent' : '#e5e7eb'; ?>; transition: all 0.3s ease;" data-student-id="<?php echo htmlspecialchars($student['STUDENT_ID']); ?>">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-graduate" style="color: <?php echo $i === 0 ? '#93c5fd' : '#9ca3af'; ?>; font-size: 14px;"></i>
                                        <span><?php echo htmlspecialchars($student['SURNAME'] . (isset($student['NAME']) ? ', ' . $student['NAME'] : '')); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="empty-state-list-item">
                                <div class="empty-state-mini">
                                    <i class="fas fa-user-graduate"></i>
                                    <span>No students assigned to you yet</span>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="admin-eval-main admin-eval-content-section" style="flex: 1; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 24px;">
                    <!-- Empty state shown when no student is selected -->
                    <div class="admin-eval-empty-state" style="display: block; text-align: center; padding: 60px 20px; color: #6b7280;">
                        <div class="empty-state-icon" style="margin-bottom: 24px; color: #9ca3af;">
                            <i class="fas fa-clipboard-check" style="font-size: 64px;"></i>
                        </div>
                        <h3 style="color: #374151; margin-bottom: 12px; font-size: 24px; font-weight: 600;">Student Evaluation Center</h3>
                        <p style="font-size: 16px; margin-bottom: 8px;">Select a student from the sidebar to begin their evaluation.</p>
                        <p style="font-size: 14px; color: #9ca3af;">View competency assessments, provide ratings, and add recommendations.</p>
                        <div class="evaluation-features" style="margin-top: 32px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; max-width: 600px; margin-left: auto; margin-right: auto;">
                            <div class="feature-item" style="padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #3b82f6;">
                                <i class="fas fa-star" style="color: #3b82f6; margin-bottom: 8px; font-size: 20px;"></i>
                                <h4 style="color: #374151; font-size: 14px; font-weight: 600; margin-bottom: 4px;">Rate Competencies</h4>
                                <p style="color: #6b7280; font-size: 12px;">Evaluate student performance across different skill areas</p>
                            </div>
                            <div class="feature-item" style="padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #10b981;">
                                <i class="fas fa-comments" style="color: #10b981; margin-bottom: 8px; font-size: 20px;"></i>
                                <h4 style="color: #374151; font-size: 14px; font-weight: 600; margin-bottom: 4px;">Add Feedback</h4>
                                <p style="color: #6b7280; font-size: 12px;">Provide constructive comments and recommendations</p>
                            </div>
                            <div class="feature-item" style="padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #f59e0b;">
                                <i class="fas fa-chart-line" style="color: #f59e0b; margin-bottom: 8px; font-size: 20px;"></i>
                                <h4 style="color: #374151; font-size: 14px; font-weight: 600; margin-bottom: 4px;">Track Progress</h4>
                                <p style="color: #6b7280; font-size: 12px;">Monitor development over time</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading state shown during data fetch -->
                    <div class="admin-eval-loading" style="display: none; text-align: center; padding: 80px 20px;">
                        <div class="loading-spinner" style="width: 50px; height: 50px; border: 4px solid #f3f4f6; border-top: 4px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 24px;"></div>
                        <h4 style="color: #374151; margin-bottom: 8px; font-size: 18px; font-weight: 600;">Loading Evaluation Data</h4>
                        <p style="color: #6b7280; font-size: 14px;">Fetching student assessments and previous ratings...</p>
                    </div>
                    
                    <!-- Evaluation content will be loaded here dynamically -->
                    <div class="admin-eval-content" style="display: none;"></div>
                </div>
            </div>
        </div>

        
    <!-- Profile Modal -->
    <div id="profileModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Profile Details</h2>
                <button class="modal-close" id="closeProfileModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="profileModalContent">
                    <!-- Profile modal content will be populated here -->
                </div>
            </div>
        </div>
    </div>

<!-- Dashboard Tab Content -->
<div id="dashboardTabContent" class="tab-content" style="display: block;">
    <!-- Attendance Statistics -->
    <div class="attendance-dashboard">
        <div class="total-students-section">
            <div class="total-students-label">TOTAL STUDENT</div>
            <div class="total-students-value"><?php echo htmlspecialchars($totalStudents); ?></div>
        </div>
        <div class="stats-grid">
            <div class="stat-card present">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h4>Present</h4>
                    <span class="stat-number"><?php echo htmlspecialchars($attendanceStats['present'] ?? 0); ?></span>
                </div>
            </div>
            <div class="stat-card on-time">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h4>On Time</h4>
                    <span class="stat-number"><?php echo htmlspecialchars($attendanceStats['on_time'] ?? 0); ?></span>
                </div>
            </div>
            <div class="stat-card late">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                    <h4>Late</h4>
                    <span class="stat-number"><?php echo htmlspecialchars($attendanceStats['late'] ?? 0); ?></span>
                </div>
            </div>

        </div>
    </div>

                <!-- Attendance Lists -->
                <div class="attendance-lists">
                    <div class="list-container">
                        <div class="list present-list">
                            <h4>Present</h4>
                            <ul id="presentList">
                                <?php if (!empty($presentList)): ?>
                                    <?php foreach ($presentList as $student): ?>
                                        <li><?php echo htmlspecialchars($student['SURNAME']); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>No students present</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="list on-time-list">
                            <h4>On Time</h4>
                            <ul id="onTimeList">
                                <?php if (!empty($onTimeList)): ?>
                                    <?php foreach ($onTimeList as $student): ?>
                                        <li><?php echo htmlspecialchars($student['SURNAME']); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>No students on time</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="list late-list">
                            <h4>Late</h4>
                            <ul id="lateList">
                                <?php if (!empty($lateList)): ?>
                                    <?php foreach ($lateList as $student): ?>
                                        <li><?php echo htmlspecialchars($student['SURNAME']); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>No late students</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Pending Attendance Tab Content -->
            <div id="pendingTabContent" class="tab-content" style="display: none;">
                <!-- Pending Attendance Records Section (at the top) -->
                <div class="pending-attendance" style="margin-bottom: 2rem;">
                    <h3>Pending Attendance Records</h3>
                    <div class="table-wrapper">
                        <table>
                            <tr>
                                <th>Student ID</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Action</th>
                            </tr>
                            <?php if (!empty($pendingRecords)): ?>
                                <?php foreach ($pendingRecords as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['STUDENT_ID']); ?></td>
                                    <td><?php echo htmlspecialchars($record['TIMEIN'] ? date('h:i A', strtotime($record['TIMEIN'])) : '--:--'); ?></td>
                                    <td><?php echo htmlspecialchars($record['TIMEOUT'] ? date('h:i A', strtotime($record['TIMEOUT'])) : '--:--'); ?></td>
                                    <td>
                                        <button onclick="approveAttendance(<?php echo $record['ID']; ?>)" class="btn-accept">Approve</button>
                                        <button onclick="deletePendingAttendance(<?php echo $record['ID']; ?>)" class="btn-decline">Decline</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-table-message">
                                        <div class="empty-state-table">
                                            <i class="fas fa-check-circle"></i>
                                            <span>All attendance records have been processed!</span>
                                            <small>New submissions will appear here for approval.</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- All Students Section (below pending attendance) -->
                <div class="pending-attendance">
                    <h3>All Students Under Your Management</h3>
                    <div class="table-wrapper">
                        <table>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact Number</th>
                                <th>Analysis</th>
                            </tr>
                            <?php if (!empty($allStudents)): ?>
                                <?php foreach ($allStudents as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['STUDENT_ID']); ?></td>
                                    <td><?php echo htmlspecialchars($student['SURNAME']); ?></td>
                                    <td><?php echo htmlspecialchars($student['EMAIL']); ?></td>
                                    <td><?php echo htmlspecialchars($student['CONTACT_NUMBER']); ?></td>
                                    <td><button class="analysis-btn" data-student-id="<?php echo htmlspecialchars($student['STUDENT_ID']); ?>">Analysis</button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-table-message">
                                        <div class="empty-state-table">
                                            <i class="fas fa-users"></i>
                                            <span>No students assigned yet</span>
                                            <small>Students will appear here once they are assigned to your supervision.</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- History Tab Content -->
            <div id="historyTabContent" class="tab-content" style="display: none;">
                <div class="date-selector" style="margin-bottom: 20px;">
                    <label for="historyDate" style="margin-right: 10px;">Select Date:</label>
                    <input type="date" id="historyDate" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer;" 
                           value="<?php echo date('Y-m-d'); ?>" />
                </div>

                <h3>Historical Attendance Records</h3>
                
                <!-- History Loading State -->
                <div id="historyLoading" class="loading-state">
                    <div class="loading-content">
                        <i class="fas fa-spinner fa-spin loading-spinner"></i>
                        <span>Loading attendance records...</span>
                    </div>
                </div>
                
                <div id="historySummary" style="display: none;">
                    <p>Date: <span id="selectedDate"></span></p>
                    
                    <!-- Total Students Section (matches dashboard layout) -->
                    <div class="total-students-section">
                        <div class="total-students-label">TOTAL STUDENT</div>
                        <div class="total-students-value" id="historyTotal">0</div>
                    </div>
                    
                    <!-- Statistics Grid (4 stats like dashboard) -->
                    <div class="stats-grid">
                        <div class="stat-card present">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h4>Present</h4>
                                <span id="historyPresent" class="stat-number">0</span>
                            </div>
                        </div>
                        <div class="stat-card on-time">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h4>On Time</h4>
                                <span id="historyOnTime" class="stat-number">0</span>
                            </div>
                        </div>
                        <div class="stat-card late">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h4>Late</h4>
                                <span id="historyLate" class="stat-number">0</span>
                            </div>
                        </div>

                    </div>
                    <div class="attendance-lists">
                        <div id="historyTableContainer" style="display: block;">
                            <table id="historyTable" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTableBody">
                                    <!-- Records will be populated here -->
                                </tbody>
                            </table>
                            <div id="historyNoRecords" class="empty-state-container" style="display: none;">
                                <div class="empty-state-content">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <div class="empty-state-title">No Records Found</div>
                                    <div class="empty-state-message">
                                        No attendance records exist for the selected date.<br>
                                        Try selecting a different date or check if students were present.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="historyTableContainer" style="display: none;">
                    <table id="historyTable" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <!-- Records will be populated here -->
                        </tbody>
                    </table>
                    <div id="historyNoRecords" style="display: none;">No records found for the selected date.</div>
                </div>
            </div>

            <!-- Reports Tab Content -->
            <div id="reportsTabContent" class="tab-content" style="display: none;">
                <div class="modern-report-header">
                    <div class="header-title-section">
                        <h3 class="header-title">
                            <i class="fas fa-file-chart-line"></i>
                            Weekly Reports
                        </h3>
                        <p class="header-subtitle">Review and manage student weekly activity reports</p>
                    </div>
                    <div class="reports-controls-modern">
                        <div class="filter-group">
                            <div class="input-wrapper has-autocomplete">
                                <label for="studentFilter" class="modern-label">
                                    <i class="fas fa-user"></i>
                                    Filter by Student
                                </label>
                                <input type="text" id="studentFilter" list="studentList" 
                                       placeholder="Type student name or select from list" 
                                       autocomplete="off" class="modern-input"
                                       title="Start typing to see available students or click the dropdown arrow">
                                <datalist id="studentList">
                                <option value="">All Students</option>
                                <?php
                                // Debug: Check adminId and coordinatorHteId
                                echo "<!-- Debug: adminId = " . htmlspecialchars($adminId ?? 'NOT SET') . " -->";
                                echo "<!-- Debug: coordinatorHteId = " . htmlspecialchars($coordinatorHteId ?? 'NOT SET') . " -->";

                                // Fetch all students under this admin's management
                                $studentsStmt = $dbo->conn->prepare("
                                    SELECT id.INTERNS_ID, id.NAME, id.SURNAME
                                    FROM interns_details id
                                    JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
                                    WHERE itd.HTE_ID = :hteId
                                    ORDER BY id.SURNAME, id.NAME
                                ");
                                $studentsStmt->bindParam(':hteId', $coordinatorHteId);
                                $studentsStmt->execute();
                                $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

                                // Debug: Check students array
                                echo "<!-- Debug: students count = " . count($students) . " -->";
                                if (count($students) == 0) {
                                    echo "<!-- Debug: No students found for adminId = " . htmlspecialchars($adminId ?? 'NOT SET') . " -->";
                                }

                                foreach ($students as $student): ?>
                                    <option value="<?php echo htmlspecialchars($student['SURNAME'] . ', ' . $student['NAME']); ?>" data-intern-id="<?php echo htmlspecialchars($student['INTERNS_ID']); ?>">
                                        <?php echo htmlspecialchars($student['SURNAME'] . ', ' . $student['NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>

                            </div>
                        </div>
                        
                        <div class="date-group">
                            <div class="input-wrapper">
                                <label for="dateFilter" class="modern-label">
                                    <i class="fas fa-calendar"></i>
                                    Select Date
                                </label>
                                <input type="date" id="dateFilter" name="dateFilter" class="modern-input date-input"
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="action-group">
                            <button id="loadReportsBtn" class="modern-load-btn">
                                <i class="fas fa-search"></i>
                                <span>Load Reports</span>
                            </button>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Report Preview Section -->
                <div id="adminReportPreview" class="admin-report-preview" style="display: none;">
                    <div class="report-header">
                        <h3><i class="fas fa-file-alt"></i> Weekly Activity Report</h3>
                        <div class="report-meta">
                            <div class="report-period">Week <span id="previewWeekNumber">1</span> (<span id="previewWeekRange">2024-01-01 to 2024-01-07</span>)</div>
                            <div class="report-status submitted">Submitted</div>
                        </div>
                    </div>

                    <div class="report-grid">
                        <div class="day-section">
                            <h4>Monday</h4>
                            <div class="day-content">
                                <p id="mondayContent">No activities reported for Monday.</p>
                                <div class="day-images" id="mondayImages">
                                    <!-- Images will be populated here -->
                                </div>
                            </div>
                        </div>

                        <div class="day-section">
                            <h4>Tuesday</h4>
                            <div class="day-content">
                                <p id="tuesdayContent">No activities reported for Tuesday.</p>
                                <div class="day-images" id="tuesdayImages">
                                    <!-- Images will be populated here -->
                                </div>
                            </div>
                        </div>

                        <div class="day-section">
                            <h4>Wednesday</h4>
                            <div class="day-content">
                                <p id="wednesdayContent">No activities reported for Wednesday.</p>
                                <div class="day-images" id="wednesdayImages">
                                    <!-- Images will be populated here -->
                                </div>
                            </div>
                        </div>

                        <div class="day-section">
                            <h4>Thursday</h4>
                            <div class="day-content">
                                <p id="thursdayContent">No activities reported for Thursday.</p>
                                <div class="day-images" id="thursdayImages">
                                    <!-- Images will be populated here -->
                                </div>
                            </div>
                        </div>

                        <div class="day-section">
                            <h4>Friday</h4>
                            <div class="day-content">
                                <p id="fridayContent">No activities reported for Friday.</p>
                                <div class="day-images" id="fridayImages">
                                    <!-- Images will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="report-summary">
                        <div class="summary-section">
                            <h4>Total Days</h4>
                            <p id="totalDays">5</p>
                        </div>
                        <div class="summary-section">
                            <h4>Activities Logged</h4>
                            <p id="activitiesLogged">5</p>
                        </div>
                        <div class="summary-section">
                            <h4>Images Uploaded</h4>
                            <p id="imagesUploaded">0</p>
                        </div>
                    </div>

                    <div class="report-footer">
                        <div class="submitted-date">Submitted: <span id="submittedDate">2024-01-08</span></div>
                        <div class="updated-date">Last Updated: <span id="updatedDate">2024-01-08</span></div>
                    </div>
                </div>

                <div id="reportsContainer" class="reports-main-container">
                    <div id="reportsLoading" class="loading-state" style="display: none;">
                        <div class="loading-content">
                            <i class="fas fa-spinner fa-spin loading-spinner"></i>
                            <span>Loading reports...</span>
                        </div>
                    </div>
                    <div id="reportsList" style="display: none;">
                        <!-- Reports will be populated here -->
                    </div>
                    <div id="noReports" class="empty-state-container" style="display: none;">
                        <div class="empty-state-content">
                            <div class="empty-state-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="empty-state-title">No Weekly Reports Found</div>
                            <div class="empty-state-message">
                                No reports match your current search criteria.<br>
                                Try adjusting the date range or selecting a different student.
                            </div>
                            <div class="empty-state-suggestions">
                                <div class="suggestion-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Check different date ranges</span>
                                </div>
                                <div class="suggestion-item">
                                    <i class="fas fa-user"></i>
                                    <span>Select "All Students" to see all reports</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
      

            <!-- Control Tab Content -->
            <div id="contralTabContent" class="tab-content" style="display: none;">
                <div class="control-panel-container">
                    <!-- Enhanced Header -->
                    <div class="control-panel-header">
                        <div class="header-content">
                            <div class="header-text">
                                <h2><i class="fas fa-cogs"></i> Control Panel</h2>
                                <p>Manage company partnerships and view system information</p>
                            </div>
                            <div class="header-actions">
                                <button id="refreshCompaniesBtn" class="control-refresh-btn">
                                    <i class="fas fa-sync-alt"></i>
                                    <span>Refresh Data</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats Dashboard -->
                    <div class="control-stats-grid">
                        <div class="stat-card active-card">
                            <div class="stat-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number" id="activeMOAs">0</div>
                                <div class="stat-label">Active MOAs</div>
                                <div class="stat-description">Currently active agreements</div>
                            </div>
                        </div>
                        <div class="stat-card warning-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number" id="expiringSoonMOAs">0</div>
                                <div class="stat-label">Expiring Soon</div>
                                <div class="stat-description">Require attention within 30 days</div>
                            </div>
                        </div>
                        <div class="stat-card danger-card">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number" id="expiredMOAs">0</div>
                                <div class="stat-label">Expired MOAs</div>
                                <div class="stat-description">Need renewal or replacement</div>
                            </div>
                        </div>
                        <div class="stat-card info-card">
                            <div class="stat-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number" id="noMOACompanies">0</div>
                                <div class="stat-label">No MOA</div>
                                <div class="stat-description">Companies without agreements</div>
                            </div>
                        </div>
                    </div>

                    <!-- Company Information Section -->
                    <div class="control-section">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-building"></i>
                                <h3>My Assigned Company</h3>
                            </div>
                            <div class="section-description">
                                <p>View and manage Memorandum of Agreement for your assigned company</p>
                            </div>
                        </div>

                        <div class="company-table-container">
                            <div class="table-wrapper">
                                <table class="companies-table-modern">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-building"></i> Company Details</th>
                                            <th><i class="fas fa-industry"></i> Industry</th>
                                            <th><i class="fas fa-user"></i> Contact Person</th>
                                            <th><i class="fas fa-file-contract"></i> MOA Status</th>
                                            <th><i class="fas fa-calendar-alt"></i> Agreement Period</th>
                                            <th><i class="fas fa-cog"></i> Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="companiesMOATableBody">
                                        <tr class="loading-row">
                                            <td colspan="6">
                                                <div class="loading-state">
                                                    <div class="loading-spinner"></div>
                                                    <div class="loading-text">
                                                        <h4>Loading Company Information</h4>
                                                        <p>Retrieving your assigned company details...</p>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- System Information Section -->
                    <div class="control-section">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-info-circle"></i>
                                <h3>System Information</h3>
                            </div>
                            <div class="section-description">
                                <p>Current system status and administrative information</p>
                            </div>
                        </div>

                        <div class="system-info-grid">
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Admin Role</h4>
                                    <p id="adminRoleInfo">Loading...</p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Last Login</h4>
                                    <p id="lastLoginInfo">Loading...</p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Managed Students</h4>
                                    <p id="managedStudentsInfo">Loading...</p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-server"></i>
                                </div>
                                <div class="info-content">
                                    <h4>System Status</h4>
                                    <p id="systemStatusInfo"><span class="status-online">Online</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>




    <script src="js/jquery.js"></script>
    <script src="js/admindashboard.js"></script>
    <script>
        // Automatically load the weekly report for the current week on page load
        document.addEventListener('DOMContentLoaded', function() {
            var loadReportsBtn = document.getElementById('loadReportsBtn');
            if (loadReportsBtn) {
                loadReportsBtn.click();
            }
            
            // Enhanced datalist functionality for student filter
            const studentFilter = document.getElementById('studentFilter');
            if (studentFilter) {
                // Show datalist options when input is focused or clicked
                studentFilter.addEventListener('focus', function() {
                    this.setAttribute('size', Math.min(this.list.options.length, 8));
                    // Trigger datalist display by simulating input
                    setTimeout(() => {
                        if (this.value === '') {
                            // Show all options when empty
                            this.value = ' ';
                            this.value = '';
                        }
                        this.showPicker ? this.showPicker() : null;
                    }, 10);
                });
                
                // Also show on click
                studentFilter.addEventListener('click', function() {
                    if (this.list && this.list.options.length > 0) {
                        // Force show datalist by triggering focus and input events
                        this.focus();
                        setTimeout(() => {
                            this.value = this.value + ' ';
                            this.value = this.value.trim();
                        }, 50);
                    }
                });
                
                // Handle datalist selection and real-time search
                studentFilter.addEventListener('input', function() {
                    // Remove any previous error styling
                    this.style.borderColor = '';
                    this.style.backgroundColor = '';
                    
                    // Debounce the search to avoid too many requests
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        const searchValue = this.value.trim();
                        if (searchValue.length >= 2 || searchValue === '') {
                            // Auto-trigger search when user types at least 2 characters or clears the field
                            const loadBtn = document.getElementById('loadReportsBtn');
                            if (loadBtn) {
                                loadBtn.click();
                            }
                        }
                    }, 800); // Increased to 800ms for better UX
                });
                
                // Also handle when user selects from datalist
                studentFilter.addEventListener('change', function() {
                    // Immediately trigger search when user selects from dropdown
                    const loadBtn = document.getElementById('loadReportsBtn');
                    if (loadBtn) {
                        loadBtn.click();
                    }
                });
                
                // Add a visual indicator when datalist has options
                const datalist = document.getElementById('studentList');
                if (datalist && datalist.options.length > 0) {
                    studentFilter.classList.add('has-datalist-options');
                }
            }
        });
        // Initialize sidebar state on page load
        function initializeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            // On desktop, sidebar should be open by default
            if (window.innerWidth > 768) {
                sidebar.classList.add('sidebar-open');
                document.querySelector('.content-area').classList.add('sidebar-open');
            } else {
                sidebar.classList.remove('sidebar-open');
                document.querySelector('.content-area').classList.remove('sidebar-open');
                if (overlay) overlay.classList.remove('active');
            }
        }
        
        // Initialize on page load
        initializeSidebar();
        
        // Add sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function(e) {
            e.stopPropagation();
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const contentArea = document.querySelector('.content-area');
            const isMobile = window.innerWidth <= 768;
            
            sidebar.classList.toggle('sidebar-open');
            contentArea.classList.toggle('sidebar-open');
            
            // Toggle overlay on mobile only
            if (isMobile && overlay) {
                overlay.classList.toggle('active');
                // Prevent body scroll when sidebar is open on mobile
                if (sidebar.classList.contains('sidebar-open')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            } else {
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Close sidebar when clicking overlay
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function(e) {
                e.stopPropagation();
                const sidebar = document.querySelector('.sidebar');
                const contentArea = document.querySelector('.content-area');
                sidebar.classList.remove('sidebar-open');
                contentArea.classList.remove('sidebar-open');
                this.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const contentArea = document.querySelector('.content-area');
            
            if (window.innerWidth > 768) {
                // On desktop, keep current sidebar state
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                // On mobile, close sidebar if it was open
                if (sidebar.classList.contains('sidebar-open')) {
                    sidebar.classList.remove('sidebar-open');
                    contentArea.classList.remove('sidebar-open');
                }
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // User profile dropdown - handle both avatar and name clicks
        document.getElementById('userProfile').addEventListener('click', function(e) {
            // Only toggle if clicking on avatar or name, not on dropdown itself
            if (e.target.closest('.user-avatar-circle') || e.target.closest('#userName') || e.target === this) {
                const dropdown = document.getElementById('userDropdown');
                dropdown.style.display = dropdown.style.display === 'none' ? 'flex' : 'none';
            }
        });
        
        // Also handle avatar click directly
        const userAvatar = document.getElementById('userAvatarCircle');
        if (userAvatar) {
            userAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = document.getElementById('userDropdown');
                dropdown.style.display = dropdown.style.display === 'none' ? 'flex' : 'none';
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userProfile = document.getElementById('userProfile');
            const userDropdown = document.getElementById('userDropdown');
            if (!userProfile.contains(event.target)) {
                userDropdown.style.display = 'none';
            }
        });

        // Logout functionality
        document.getElementById('logoutBtn').addEventListener('click', function() {
            window.location.href = 'ajaxhandler/adminLogout.php';
        });

        // Profile button
        document.getElementById('btnProfile').addEventListener('click', function() {
            loadAdminProfileDetails();
        });

        // Modal close functionality
        document.getElementById('closeProfileModal').addEventListener('click', function() {
            document.getElementById('profileModal').style.display = 'none';
        });

        // Close modal when clicking outside
        document.getElementById('profileModal').addEventListener('click', function(e) {
            if (e.target === this) {
                document.getElementById('profileModal').style.display = 'none';
            }
        });

        // Close modal with Escape key
        document.addEventListener('keyup', function(e) {
            if (e.key === "Escape") {
                document.getElementById('profileModal').style.display = 'none';
            }
        });


    
        // Return Report Modal Event Handlers
        $(document).ready(function() {
            $('#closeReturnModal, #cancelReturn').on('click', function() {
                $('#returnReportModal').hide();
                currentReportId = null;
            });

            $('#confirmReturn').on('click', function() {
                submitReturnReport();
            });

            // Close modal when clicking outside
            $('#returnReportModal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                    currentReportId = null;
                }
            });

            // Close modal with Escape key
            $(document).on('keyup', function(e) {
                if (e.key === "Escape") {
                    $('#returnReportModal').hide();
                    currentReportId = null;
                }
            });

            // Sidebar collapse functionality and user dropdown
            let sidebarOpen = false;
            let sidebarCollapsed = false;

            // Toggle sidebar visibility
            $('#sidebarToggle').click(function() {
                const sidebar = $('.sidebar');
                const contentArea = $('.content-area');
                
                if ($(window).width() >= 769) {
                    // Desktop behavior - toggle collapse/expand
                    if (!sidebarOpen) {
                        // Open sidebar
                        sidebarOpen = true;
                        sidebar.addClass('sidebar-open');
                        contentArea.addClass('sidebar-open');
                        if (sidebarCollapsed) {
                            contentArea.addClass('sidebar-collapsed');
                        }
                    } else {
                        // Toggle between expanded and collapsed
                        sidebarCollapsed = !sidebarCollapsed;
                        if (sidebarCollapsed) {
                            sidebar.addClass('collapsed');
                            contentArea.addClass('sidebar-collapsed');
                        } else {
                            sidebar.removeClass('collapsed');
                            contentArea.removeClass('sidebar-collapsed');
                        }
                    }
                } else {
                    // Mobile behavior - toggle show/hide
                    sidebarOpen = !sidebarOpen;
                    if (sidebarOpen) {
                        sidebar.addClass('sidebar-open');
                        $('#sidebarOverlay').addClass('active');
                    } else {
                        sidebar.removeClass('sidebar-open');
                        $('#sidebarOverlay').removeClass('active');
                    }
                }
            });

            // Close sidebar when clicking overlay (mobile)
            $('#sidebarOverlay').click(function() {
                const sidebar = $('.sidebar');
                sidebarOpen = false;
                sidebar.removeClass('sidebar-open');
                $(this).removeClass('active');
            });

            // Handle window resize
            $(window).resize(function() {
                if ($(window).width() >= 769) {
                    $('#sidebarOverlay').removeClass('active');
                }
            });

            // Modern user dropdown functionality
            $(document).ready(function() {
                $('#userDropdownToggle').off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const dropdown = $('#userDropdown');
                    const arrow = $('.dropdown-arrow');
                    
                    console.log('Dropdown clicked', dropdown.length);
                    console.log('Current classes:', dropdown.attr('class'));
                    
                    if (dropdown.hasClass('show')) {
                        dropdown.removeClass('show');
                        dropdown.css('display', '');
                        arrow.removeClass('rotate');
                        console.log('Hiding dropdown');
                    } else {
                        dropdown.addClass('show');
                        dropdown.css('display', 'block');
                        arrow.addClass('rotate');
                        console.log('Showing dropdown');
                        console.log('New classes:', dropdown.attr('class'));
                        console.log('Display style:', dropdown.css('display'));
                    }
                });

                // Close dropdown when clicking outside
                $(document).on('click', function(event) {
                    if (!$(event.target).closest('#userProfile').length) {
                        $('#userDropdown').removeClass('show');
                        $('.dropdown-arrow').removeClass('rotate');
                    }
                });
                
                // Prevent dropdown from closing when clicking inside
                $('#userDropdown').on('click', function(e) {
                    e.stopPropagation();
                });
            });
        });
    </script>

    <!-- Return Report Modal -->
    <div id="returnReportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Return Report</h2>
                <span class="close" id="closeReturnModal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Please provide the reason for returning this report. The student will be able to edit the report after it is returned.</p>
                <textarea id="returnReason" rows="4" placeholder="Enter return reason..."></textarea>
            </div>
            <div class="modal-footer">
                <button id="cancelReturn" class="modal-btn cancel-btn">Cancel</button>
                <button id="confirmReturn" class="modal-btn confirm-btn">Return Report</button>
            </div>
        </div>
    </div>
</body>
</html>

