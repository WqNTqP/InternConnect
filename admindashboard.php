<?php 
// Include the database and session handling
require_once $_SERVER['DOCUMENT_ROOT'] . "/database/admindashboarddb.php";

// Fetch pending attendance records with student details
$stmt = $dbo->conn->prepare("
    SELECT pa.*, id.STUDENT_ID
    FROM pending_attendance pa
    LEFT JOIN interns_details id ON pa.INTERNS_ID = id.INTERNS_ID
    WHERE pa.status = 'pending'
");
$stmt->execute();
$pendingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get admin details for display
$adminName = $name ?? 'Admin';
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
                <span id="userName" data-admin-id="<?php echo htmlspecialchars($adminId); ?>"><?php echo htmlspecialchars($adminName); ?> &#x25BC;</span>
                <div class="user-dropdown" id="userDropdown" style="display:none;">
                    <button id="btnProfile">Profile</button>
                    <button id="logoutBtn">Logout</button>
                </div>
            </div>
        </div>

        <div class="sidebar">
            <ul class="sidebar-menu">
                <li class="sidebar-item active" id="dashboardTab"><i class="fas fa-tachometer-alt"></i> Dashboard</li>
                <li class="sidebar-item" id="pendingTab"><i class="fas fa-user-check"></i> Attendance</li>
                <li class="sidebar-item" id="historyTab"><i class="fas fa-history"></i> History</li>
                <li class="sidebar-item" id="reportsTab"><i class="fas fa-file-alt"></i> Reports</li>
                <li class="sidebar-item" id="evaluationTab"><i class="fas fa-star"></i> Evaluation</li>
                <li class="sidebar-item" id="contralTab"><i class="fas fa-cogs"></i> Contral</li>
            </ul>
        </div>

        <div class="content-area">
            <h1>Welcome, <?php echo htmlspecialchars($name); ?></h1>




    <!-- Evaluation Tab Content -->
        <div id="evaluationTabContent" class="tab-content" style="display: none;">
            <div class="admin-eval-container" style="display: flex; gap: 32px; padding: 24px 0;">
                <div class="admin-eval-sidebar" style="width: 260px; background: #f7f7f7; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 16px; display: flex; flex-direction: column;">
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
                <div class="admin-eval-main" style="flex: 1; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 24px;">
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
                                <?php foreach ($presentList as $student): ?>
                                    <li><?php echo htmlspecialchars($student['SURNAME']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="list on-time-list">
                            <h4>On Time</h4>
                            <ul id="onTimeList">
                                <?php foreach ($onTimeList as $student): ?>
                                    <li><?php echo htmlspecialchars($student['SURNAME']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="list late-list">
                            <h4>Late</h4>
                            <ul id="lateList">
                                <?php foreach ($lateList as $student): ?>
                                    <li><?php echo htmlspecialchars($student['SURNAME']); ?></li>
                                <?php endforeach; ?>
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

                <!-- All Students Section (below pending attendance) -->
                <div class="pending-attendance">
                    <h3>All Students Under Your Management</h3>
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
        // Add sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('sidebar-open');
            document.querySelector('.content-area').classList.toggle('sidebar-open');
        });

        // User profile dropdown
        document.getElementById('userProfile').addEventListener('click', function() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'flex' : 'none';
        });

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

