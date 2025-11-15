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

// Fetch pending attendance records with student details
$stmt = $dbo->conn->prepare("
    SELECT pa.*, id.STUDENT_ID
    FROM pending_attendance pa
    LEFT JOIN interns_details id ON pa.INTERNS_ID = id.INTERNS_ID
    WHERE pa.status = 'pending'
");
$stmt->execute();
$pendingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if admin is logged in and get coordinator ID
session_start();
$coordinatorId = $_SESSION["admin_user"] ?? null;
if (!$coordinatorId) {
    header("Location: admin.php");
    exit();
}

// Set adminId for use in the template
$adminId = $coordinatorId;

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

// Get coordinator's HTE_ID and then get students assigned to that HTE
try {
    // First, get the coordinator's HTE_ID
    $stmt = $dbo->conn->prepare("SELECT HTE_ID FROM coordinator WHERE COORDINATOR_ID = ?");
    $stmt->execute([$coordinatorId]);
    $coordinatorData = $stmt->fetch(PDO::FETCH_ASSOC);
    $coordinatorHteId = $coordinatorData['HTE_ID'] ?? null;
    
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
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle Sidebar">&#9776;</button>
            <div class="sidebar-logo" style="margin-left: 1rem; cursor: pointer;" onclick="window.location.href='admindashboard.php';">
                <h2 class="logo" style="cursor: pointer;">InternConnect</h2>
            </div>
            <div class="user-profile" id="userProfile">
                <div class="user-avatar-circle" id="userAvatarCircle">
                    <i class="fas fa-user"></i>
                </div>
                <span id="userName" class="user-name-text" data-admin-id="<?php echo htmlspecialchars($adminId); ?>"><?php echo htmlspecialchars($adminName); ?> &#x25BC;</span>
                <div class="user-dropdown" id="userDropdown" style="display:none;">
                    <div class="user-dropdown-header">
                        <div class="user-dropdown-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-dropdown-name" id="userDropdownName"><?php echo htmlspecialchars($adminName); ?></div>
                    </div>
                    <button id="btnProfile">Profile</button>
                    <button id="logoutBtn">Logout</button>
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
                                <li class="admin-eval-student<?php echo $i === 0 ? ' active' : ''; ?>" style="padding: 10px 14px; border-radius: 4px; margin-bottom: 6px; cursor: pointer; background: <?php echo $i === 0 ? '#e0e7ff' : '#fff'; ?>;<?php echo $i === 0 ? ' font-weight: bold;' : ''; ?>" data-student-id="<?php echo htmlspecialchars($student['STUDENT_ID']); ?>">
                                    <?php echo htmlspecialchars($student['SURNAME'] . (isset($student['NAME']) ? ', ' . $student['NAME'] : '')); ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="admin-eval-student" style="padding: 10px 14px; border-radius: 4px; margin-bottom: 6px; cursor: pointer; background: #fff;">No students found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="admin-eval-main admin-eval-content-section" style="flex: 1; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 24px;">
                    <!-- Evaluation table and ratings will be loaded dynamically via JS -->
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
                                    <td colspan="4">No pending attendance records found.</td>
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
                                    <td colspan="5">No students found under your management.</td>
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
                            <div id="historyNoRecords" style="display: none;">No records found for the selected date.</div>
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
                <div class="reportHeader">
                    <h3>Weekly Reports</h3>
                    <div class="reports-controls">
                        <div class="filter-controls">
                            <label for="studentFilter">Filter by Student:</label>
                            <input type="text" id="studentFilter" list="studentList" placeholder="Type student name or select from list" autocomplete="off">
                            <datalist id="studentList">
                                <option value="">All Students</option>
                                <?php
                                // Debug: Check adminId
                                echo "<!-- Debug: adminId = " . htmlspecialchars($adminId ?? 'NOT SET') . " -->";

                                // Fetch all students under this admin's management
                                $studentsStmt = $dbo->conn->prepare("
                                    SELECT id.INTERNS_ID, id.NAME, id.SURNAME
                                    FROM interns_details id
                                    JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
                                    WHERE itd.HTE_ID = :hteId
                                    ORDER BY id.SURNAME, id.NAME
                                ");
                                $studentsStmt->bindParam(':hteId', $hteId);
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

                            <label for="dateFilter">Select Date:</label>
                            <input type="date" id="dateFilter" name="dateFilter" class="form-control" style="width: auto; display: inline-block; margin-right: 10px;"
                                   value="<?php echo date('Y-m-d'); ?>"
                            >

                            <button id="loadReportsBtn">Load Reports</button>
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

                <div id="reportsContainer">
                    <div id="reportsLoading" style="display: none; text-align: center; padding: 20px;">
                        <i class="fas fa-spinner fa-spin"></i> Loading reports...
                    </div>
                    <div id="reportsList" style="display: none;">
                        <!-- Reports will be populated here -->
                    </div>
                    <div id="noReports" style="display: none; text-align: center; padding: 20px;">
                        No weekly reports found matching your criteria.
                    </div>
                </div>
            </div>

            <!-- Control Tab Content -->
            <div id="contralTabContent" class="tab-content" style="display: none;">
                <div class="dashboard-header">
                    <h2>Control Panel</h2>
                    <p>Manage company MOAs (Memorandums of Agreement) and view system information</p>
                </div>

                <!-- MOA Management Section -->
                <div class="section">
                    <div class="section-header">
                        <h3><i class="fas fa-file-contract"></i> My Assigned Company MOA</h3>
                        <p>View Memorandum of Agreement for your assigned company</p>
                    </div>

                    <!-- Refresh Button -->
                    <div class="action-container" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <div style="flex: 1;">
                                <p style="margin: 0; color: #555; font-style: italic;">Viewing MOA information for your assigned company</p>
                            </div>
                            <button id="refreshCompaniesBtn" class="btn" style="padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-refresh"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Companies MOA Table -->
                    <div class="table-container" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
                        <table class="companies-table" style="width: 100%; border-collapse: collapse;">
                            <thead style="background: #f1f3f4;">
                                <tr>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #ddd;">Company Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #ddd;">Industry</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #ddd;">Contact Person</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #ddd;">MOA Status</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #ddd;">MOA Dates</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #ddd;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="companiesMOATableBody">
                                <tr>
                                    <td colspan="6" style="padding: 40px; text-align: center; color: #666;">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                                            <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #007bff;"></i>
                                            <p>Loading your assigned company...</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOA Statistics -->
                    <div class="moa-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                        <div class="stat-card" style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #28a745;">
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;" id="activeMOAs">0</div>
                            <div style="font-size: 14px; color: #666;">Active MOAs</div>
                        </div>
                        <div class="stat-card" style="background: #fff3cd; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #ffc107;">
                            <div style="font-size: 24px; font-weight: bold; color: #ffc107;" id="expiringSoonMOAs">0</div>
                            <div style="font-size: 14px; color: #666;">Expiring Soon</div>
                        </div>
                        <div class="stat-card" style="background: #f8d7da; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #dc3545;">
                            <div style="font-size: 24px; font-weight: bold; color: #dc3545;" id="expiredMOAs">0</div>
                            <div style="font-size: 14px; color: #666;">Expired MOAs</div>
                        </div>
                        <div class="stat-card" style="background: #d1ecf1; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #17a2b8;">
                            <div style="font-size: 24px; font-weight: bold; color: #17a2b8;" id="noMOACompanies">0</div>
                            <div style="font-size: 14px; color: #666;">No MOA</div>
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

