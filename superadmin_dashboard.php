<?php
session_start();
if (!isset($_SESSION['current_user_role']) || $_SESSION['current_user_role'] !== 'SUPERADMIN') {
    header("Location: index.php");
    exit();
}

// Include necessary files for database connection and functions
// Old code - commented out
require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();


// Initialize database connection
$dbo = new Database();

// Check if database connection is successful
if ($dbo->conn === null) {
    die("Database connection failed. Please check your connection settings.");
}

// Dashboard stats (wrap queries in try/catch to avoid unhandled exceptions)
try {
    $totalUsers = (int)$dbo->conn->query("SELECT COUNT(*) FROM interns_details")->fetchColumn();
    $totalCoordinators = (int)$dbo->conn->query("SELECT COUNT(*) FROM coordinator WHERE ROLE = 'COORDINATOR'")->fetchColumn();
    $totalAdmins = (int)$dbo->conn->query("SELECT COUNT(*) FROM coordinator WHERE ROLE = 'ADMIN'")->fetchColumn();
    $totalSuperadmins = (int)$dbo->conn->query("SELECT COUNT(*) FROM coordinator WHERE ROLE = 'SUPERADMIN'")->fetchColumn();
    $totalHTEs = (int)$dbo->conn->query("SELECT COUNT(*) FROM host_training_establishment")->fetchColumn();
    $totalReports = (int)$dbo->conn->query("SELECT COUNT(*) FROM interns_attendance")->fetchColumn();

    // Users list
    $students = $dbo->conn->query("SELECT * FROM interns_details")->fetchAll(PDO::FETCH_ASSOC);
    $allCoordinators = $dbo->conn->query("SELECT * FROM coordinator")->fetchAll(PDO::FETCH_ASSOC);

    // HTEs list
    $htes = $dbo->conn->query("SELECT * FROM host_training_establishment")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch existing coordinators/admins for display
    $stmt = $dbo->conn->prepare("SELECT * FROM coordinator");
    $stmt->execute();
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // In production you may want to log this error.
    $students = $allCoordinators = $htes = $coordinators = [];
    $totalUsers = $totalCoordinators = $totalAdmins = $totalSuperadmins = $totalHTEs = $totalReports = 0;
}

// Get superadmin details
$superadminName = $_SESSION['current_user_name'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Super Admin Dashboard - InternConnect</title>
    <!-- Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/superadmin.css">
    <link rel="icon" type="image/x-icon" href="icon/favicon.ico">

    <!-- jQuery (only include once) -->
    <script src="js/jquery.js"></script>
    <script src="js/superadmin_dashboard.js"></script>

</head>
<body>
    <div class="page">
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="sidebar closed" id="sidebar">
            <div style="padding: 1rem 1.5rem; font-weight:bold; font-size:1.3rem; cursor:pointer; border-bottom: 1px solid rgba(255,255,255,0.1);" onclick="window.location.href='superadmin_dashboard.php'">InternConnect</div>
            <ul class="sidebar-menu">
                <li class="sidebar-item active" data-tab="dashboard"><span>Dashboard</span></li>
                <li class="sidebar-item" data-tab="users"><span>Users</span></li>
                <li class="sidebar-item" data-tab="htes"><span>HTEs</span></li>
                <li class="sidebar-item" data-tab="reports"><span>Reports</span></li>
                <li class="sidebar-item" data-tab="coordinators"><span>Coordinators/Admins</span></li>
            </ul>
        </div>

        <div class="content-area full-width">
            <div class="top-header">
                <div style="display:flex; align-items:center; gap:12px;">
                    <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle Sidebar">&#9776;</button>
                    <h2 style="margin:0;">Super Admin Dashboard</h2>
                </div>

                <div class="user-profile" id="userProfile">
                    <div class="user-avatar-circle" id="userAvatarCircle">
                        <i class="fas fa-user"></i>
                    </div>
                    <span id="userName" class="user-name-text"><?php echo htmlspecialchars($superadminName); ?> &#x25BC;</span>
                    <div class="user-dropdown" id="userDropdown" style="display:none;">
                        <div class="user-dropdown-header">
                            <div class="user-dropdown-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="user-dropdown-name" id="userDropdownName"><?php echo htmlspecialchars($superadminName); ?></div>
                        </div>
                        <button id="btnProfile">Profile</button>
                        <button id="logoutBtn">Logout</button>
                    </div>
                </div>
            </div>

            <!-- Dashboard tab -->
            <div class="tab-content" id="dashboard" style="display:block;">
                <div class="dashboard-welcome">
                    <p>Welcome, <strong><?php echo htmlspecialchars($superadminName); ?></strong>! Here you can view system statistics and quick actions.</p>
                </div>
                <div class="dashboard-stats" aria-live="polite">
                    <div class="stat-card">
                        Total Users
                        <span><?php echo $totalUsers; ?></span>
                    </div>
                    <div class="stat-card">
                        Total Coordinators
                        <span><?php echo $totalCoordinators; ?></span>
                    </div>
                    <div class="stat-card">
                        Total Admins
                        <span><?php echo $totalAdmins; ?></span>
                    </div>
                    <div class="stat-card">
                        Total Superadmins
                        <span><?php echo $totalSuperadmins; ?></span>
                    </div>
                    <div class="stat-card">
                        Total HTEs
                        <span><?php echo $totalHTEs; ?></span>
                    </div>
                </div>
            </div>

            <!-- Users tab -->
            <div class="tab-content" id="users" style="display:none;">
                <h3>User Management</h3>
                <p>View, add, edit, and manage all users (students, coordinators, admins, superadmins).</p>

                <div style="margin: 12px 0;">
                    <label for="userTypeFilter">Filter by User Type:</label>
                    <select id="userTypeFilter">
                        <option value="all">All</option>
                        <option value="student">Student</option>
                        <option value="coordinator">Coordinator</option>
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>

                <div class="table-wrapper">
                    <table id="userTable" aria-describedby="user-table-desc">
                        <caption id="user-table-desc" style="text-align:left; padding:8px 0;">List of users</caption>
                        <thead>
                            <tr>
                                <th>User Type</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact Number</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr class="user-row" data-user-type="student">
                                    <td>Student</td>
                                    <td><?php echo htmlspecialchars($student['INTERNS_ID'] ?? $student['STUDENT_ID'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($student['NAME'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($student['EMAIL'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($student['CONTACT_NUMBER'] ?? ''); ?></td>
                                    <td>Student</td>
                                </tr>
                            <?php endforeach; ?>

                            <?php foreach ($allCoordinators as $user): ?>
                                <tr class="user-row" data-user-type="<?php echo strtolower(htmlspecialchars($user['ROLE'] ?? 'coordinator')); ?>">
                                    <td>Coordinator/Admin/Superadmin</td>
                                    <td><?php echo htmlspecialchars($user['COORDINATOR_ID'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['NAME'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['EMAIL'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['CONTACT_NUMBER'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['ROLE'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- HTEs tab -->
            <div class="tab-content" id="htes" style="display:none;">
                <h3>Host Training Establishments (HTEs)</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>HTE ID</th><th>Name</th><th>Industry</th><th>Address</th><th>Contact Email</th><th>Contact Person</th><th>Contact Number</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($htes as $hte): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($hte['HTE_ID'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($hte['NAME'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($hte['INDUSTRY'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($hte['ADDRESS'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($hte['CONTACT_EMAIL'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($hte['CONTACT_PERSON'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($hte['CONTACT_NUMBER'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reports tab -->
            <div class="tab-content" id="reports" style="display:none;">
                <h3>Reports</h3>

                <div style="display:flex; gap:12px; align-items:center; margin-bottom:12px;">
                    <label for="reportType">Select Report Type:</label>
                    <select id="reportType">
                        <option value="attendance" selected>Attendance</option>
                        <option value="weekly">Weekly</option>
                        <option value="evaluation">Evaluation</option>
                    </select>

                    <label for="reportUserInput">Student:</label>
                    <input id="reportUserInput" list="studentList" placeholder="Type student name..." autocomplete="off">
                    <datalist id="studentList">
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo htmlspecialchars($student['NAME'] ?? ''); ?>" data-id="<?php echo htmlspecialchars($student['INTERNS_ID'] ?? $student['STUDENT_ID'] ?? ''); ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <label for="reportHTEInput">HTE:</label>
                    <input id="reportHTEInput" list="hteList" placeholder="Type HTE name..." autocomplete="off">
                    <datalist id="hteList">
                        <?php foreach ($htes as $h): ?>
                            <option value="<?php echo htmlspecialchars($h['NAME']); ?>" data-id="<?php echo htmlspecialchars($h['HTE_ID']); ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <button class="btn" id="btnRefreshReports">Refresh</button>
                </div>

                <div id="reportTableContainer">
                    <h4 id="reportTableTitle">Attendance Reports</h4>
                    <div class="table-wrapper">
                        <table id="reportTable">
                            <thead id="reportTableHeader">
                                <tr>
                                    <th>Date</th>
                                    <th>Student Name</th>
                                    <th>Coordinator Name</th>
                                    <th>HTE Name</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                </tr>
                            </thead>
                            <tbody id="reportTableBody">
                                <tr><td colspan="6">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Coordinators/Admins tab -->
            <div class="tab-content" id="coordinators" style="display:none;">
                <h3>Coordinators/Admins Management</h3>
                <button id="btnAddCoordinator" class="btn" style="margin-bottom: 12px;">Add New Coordinator/Admin</button>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Coordinator ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact Number</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count superadmins
                            $superadminCount = 0;
                            foreach ($coordinators as $c) {
                                if (($c['ROLE'] ?? '') === 'SUPERADMIN') $superadminCount++;
                            }
                            foreach ($coordinators as $coordinator):
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($coordinator['COORDINATOR_ID'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($coordinator['NAME'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($coordinator['EMAIL'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($coordinator['CONTACT_NUMBER'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($coordinator['DEPARTMENT'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($coordinator['ROLE'] ?? ''); ?></td>
                                    <td>
                                        <?php if (($coordinator['ROLE'] ?? '') === 'SUPERADMIN' && $superadminCount <= 1): ?>
                                            <button class="btn-decline" id="lastSuperadminBtn" type="button">Delete</button>
                                        <?php else: ?>
                                            <button onclick="deleteCoordinator('<?php echo htmlspecialchars($coordinator['COORDINATOR_ID']); ?>')" class="btn-decline">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- .content-area -->
    </div><!-- .page -->

    <!-- Consolidated JS -->
    <script>
        (function() {
            // Tab switching
            function switchTab(tabName) {
                document.querySelectorAll('.tab-content').forEach(function(el) {
                    el.style.display = (el.id === tabName) ? 'block' : 'none';
                });
                document.querySelectorAll('.sidebar-item').forEach(function(it) {
                    it.classList.toggle('active', it.getAttribute('data-tab') === tabName);
                });
            }
            document.querySelectorAll('.sidebar-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    var tab = this.getAttribute('data-tab');
                    switchTab(tab);
                });
            });

            // Initialize sidebar state on page load
            function initializeSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const contentArea = document.querySelector('.content-area');
                
                // Sidebar should be closed by default on all screen sizes
                sidebar.classList.remove('sidebar-open');
                sidebar.classList.add('closed');
                if (contentArea) contentArea.classList.remove('sidebar-open');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            // Initialize on page load
            initializeSidebar();
            
            // Sidebar toggle for all screens
            document.getElementById('sidebarToggle').addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const contentArea = document.querySelector('.content-area');
                const isMobile = window.innerWidth <= 768;
                
                // Toggle sidebar-open class (remove closed if present)
                if (sidebar.classList.contains('sidebar-open')) {
                    sidebar.classList.remove('sidebar-open');
                    sidebar.classList.add('closed');
                    if (contentArea) contentArea.classList.remove('sidebar-open');
                } else {
                    sidebar.classList.add('sidebar-open');
                    sidebar.classList.remove('closed');
                    if (contentArea) contentArea.classList.add('sidebar-open');
                }
                
                // Toggle overlay on mobile only
                if (isMobile && overlay) {
                    if (sidebar.classList.contains('sidebar-open')) {
                        overlay.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    } else {
                        overlay.classList.remove('active');
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
                    const sidebar = document.getElementById('sidebar');
                    const contentArea = document.querySelector('.content-area');
                    sidebar.classList.remove('sidebar-open');
                    sidebar.classList.add('closed');
                    if (contentArea) contentArea.classList.remove('sidebar-open');
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
            
            // Handle window resize
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    const contentArea = document.querySelector('.content-area');
                    
                    if (window.innerWidth > 768) {
                        // On desktop, keep current sidebar state but remove overlay
                        if (overlay) overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    } else {
                        // On mobile, close sidebar if it was open
                        if (sidebar.classList.contains('sidebar-open')) {
                            sidebar.classList.remove('sidebar-open');
                            sidebar.classList.add('closed');
                            if (contentArea) contentArea.classList.remove('sidebar-open');
                        }
                        if (overlay) overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }, 100);
            });

            // User profile dropdown - handle both avatar and name clicks
            var userProfile = document.getElementById('userProfile');
            var userDropdown = document.getElementById('userDropdown');
            var userAvatar = document.getElementById('userAvatarCircle');
            
            if (userProfile) {
                userProfile.addEventListener('click', function(e) {
                    // Only toggle if clicking on avatar or name, not on dropdown itself
                    if (e.target.closest('.user-avatar-circle') || e.target.closest('#userName') || e.target === this) {
                        e.stopPropagation();
                        userDropdown.style.display = userDropdown.style.display === 'flex' ? 'none' : 'flex';
                    }
                });
            }
            
            // Also handle avatar click directly
            if (userAvatar) {
                userAvatar.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.style.display = userDropdown.style.display === 'flex' ? 'none' : 'flex';
                });
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (userProfile && !userProfile.contains(e.target)) {
                    userDropdown.style.display = 'none';
                }
            });

            // Logout
            document.getElementById('logoutBtn').addEventListener('click', function() {
                window.location.href = 'ajaxhandler/adminLogout.php';
            });

            // Filter users by type
            var userTypeFilter = document.getElementById('userTypeFilter');
            if (userTypeFilter) {
                userTypeFilter.addEventListener('change', function() {
                    var val = this.value;
                    document.querySelectorAll('#userTable .user-row').forEach(function(row) {
                        if (val === 'all') {
                            row.style.display = '';
                        } else {
                            row.style.display = (row.getAttribute('data-user-type') === val) ? '' : 'none';
                        }
                    });
                });
            }

            // Delete coordinator function
            window.deleteCoordinator = function(coordinatorId) {
                if (!coordinatorId) return;
                if (!confirm('Are you sure you want to delete coordinator ID ' + coordinatorId + '? This action cannot be undone.')) return;
                // Use AJAX call to server-side delete handler
                $.ajax({
                    url: 'ajaxhandler/deleteCoordinator.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { coordinatorId: coordinatorId },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            alert('Coordinator deleted successfully.');
                            location.reload();
                        } else {
                            alert('Failed to delete coordinator. ' + (resp.message || ''));
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the coordinator.');
                    }
                });
            };

            // Prevent accidental delete of last superadmin button
            document.addEventListener('click', function(e) {
                if (e.target && e.target.id === 'lastSuperadminBtn') {
                    alert('You cannot delete the last remaining Super Admin. Please create another Super Admin before deleting this account.');
                }
            });

            // Reports handling
            var reportTypeElem = document.getElementById('reportType');
            var reportUserInput = document.getElementById('reportUserInput');
            var reportHTEInput = document.getElementById('reportHTEInput');
            var reportTableTitle = document.getElementById('reportTableTitle');
            var reportTableHeader = document.getElementById('reportTableHeader');
            var reportTableBody = document.getElementById('reportTableBody');
            var btnRefreshReports = document.getElementById('btnRefreshReports');

            function formatTime24to12(timeStr) {
                if (!timeStr) return '';
                var parts = timeStr.split(':');
                if (parts.length < 2) return timeStr;
                var h = parseInt(parts[0], 10);
                var m = parts[1];
                var ampm = h >= 12 ? 'pm' : 'am';
                h = h % 12;
                if (h === 0) h = 12;
                return h + ':' + m + ' ' + ampm;
            }

            function loadAttendanceReports() {
                reportTableTitle.textContent = 'Attendance Reports';
                reportTableHeader.innerHTML = '<tr><th>Date</th><th>Student Name</th><th>Coordinator Name</th><th>HTE Name</th><th>Time In</th><th>Time Out</th></tr>';
                reportTableBody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';

                $.ajax({
                    url: 'ajaxhandler/superadminAttendanceAjax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { 
                        action: 'getAttendanceReports', 
                        user: getStudentIdByName(reportUserInput.value), 
                        hte: getHTEIdByName(reportHTEInput.value) 
                    },
                    success: function(response) {
                        var rows = '';
                        if (response && response.status === 'success') {
                            if (!response.reports || response.reports.length === 0) {
                                rows = '<tr><td colspan="6">No attendance reports found.</td></tr>';
                            } else {
                                response.reports.forEach(function(report) {
                                    rows += '<tr>' +
                                        '<td>' + (report.ON_DATE || '') + '</td>' +
                                        '<td>' + (report.student_name || '') + '</td>' +
                                        '<td>' + (report.coordinator_name || '') + '</td>' +
                                        '<td>' + (report.hte_name || '') + '</td>' +
                                        '<td>' + formatTime24to12(report.TIMEIN) + '</td>' +
                                        '<td>' + formatTime24to12(report.TIMEOUT) + '</td>' +
                                    '</tr>';
                                });
                            }
                        } else {
                            rows = '<tr><td colspan="6">Error loading attendance reports.</td></tr>';
                        }
                        reportTableBody.innerHTML = rows;
                    },
                    error: function() {
                        reportTableBody.innerHTML = '<tr><td colspan="6">Error loading attendance reports.</td></tr>';
                    }
                });
            }

            function loadWeeklyReports() {
                reportTableTitle.textContent = 'Weekly Reports';
                reportTableHeader.innerHTML = '<tr><th>Report ID</th><th>Intern Name</th><th>Week Start</th><th>Week End</th><th>Approval Status</th><th>Created At</th></tr>';
                reportTableBody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';

                $.ajax({
                    url: 'ajaxhandler/superadminWeeklyReportAjax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { 
                        action: 'getWeeklyReports', 
                        fromDate: '', 
                        toDate: '', 
                        user: getStudentIdByName(reportUserInput.value), 
                        hte: getHTEIdByName(reportHTEInput.value) 
                    },
                    success: function(response) {
                        var rows = '';
                        if (response && response.status === 'success') {
                            if (!response.reports || response.reports.length === 0) {
                                rows = '<tr><td colspan="6">No weekly reports found.</td></tr>';
                            } else {
                                response.reports.forEach(function(report) {
                                    rows += '<tr>' +
                                        '<td>' + (report.report_id || '') + '</td>' +
                                        '<td>' + (report.student_name || '') + '</td>' +
                                        '<td>' + (report.week_start || '') + '</td>' +
                                        '<td>' + (report.week_end || '') + '</td>' +
                                        '<td>' + (report.approval_status || '') + '</td>' +
                                        '<td>' + (report.created_at || '') + '</td>' +
                                    '</tr>';
                                });
                            }
                        } else {
                            rows = '<tr><td colspan="6">Error loading weekly reports.</td></tr>';
                        }
                        reportTableBody.innerHTML = rows;
                    },
                    error: function() {
                        reportTableBody.innerHTML = '<tr><td colspan="6">Error loading weekly reports.</td></tr>';
                    }
                });
            }

            function loadEvaluationReports() {
                reportTableTitle.textContent = 'Evaluation Reports';
                reportTableHeader.innerHTML = '<tr><th>STUDENT ID</th><th>STUDENT NAME</th><th>COORDINATOR NAME</th><th>HTE (Assigned)</th><th>TIMESTAMP</th></tr>';
                reportTableBody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';

                $.ajax({
                    url: 'ajaxhandler/superadminEvaluationReportAjax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { 
                        action: 'getEvaluationReports', 
                        user: getStudentIdByName(reportUserInput.value), 
                        hte: getHTEIdByName(reportHTEInput.value) 
                    },
                    success: function(response) {
                        var rows = '';
                        if (response && response.status === 'success') {
                            if (!response.reports || response.reports.length === 0) {
                                rows = '<tr><td colspan="5">No evaluation reports found.</td></tr>';
                            } else {
                                response.reports.forEach(function(report) {
                                    rows += '<tr>' +
                                        '<td>' + (report.STUDENT_ID || '') + '</td>' +
                                        '<td>' + (report.student_name || '') + '</td>' +
                                        '<td>' + (report.coordinator_name || '') + '</td>' +
                                        '<td>' + (report.hte_name || '') + '</td>' +
                                        '<td>' + (report.timestamp || '') + '</td>' +
                                    '</tr>';
                                });
                            }
                        } else {
                            rows = '<tr><td colspan="5">Error loading evaluation reports.</td></tr>';
                        }
                        reportTableBody.innerHTML = rows;
                    },
                    error: function() {
                        reportTableBody.innerHTML = '<tr><td colspan="5">Error loading evaluation reports.</td></tr>';
                    }
                });
            }

            function refreshReports() {
                var type = reportTypeElem ? reportTypeElem.value : 'attendance';
                if (type === 'attendance') {
                    loadAttendanceReports();
                } else if (type === 'weekly') {
                    loadWeeklyReports();
                } else if (type === 'evaluation') {
                    loadEvaluationReports();
                }
            }

            if (btnRefreshReports) {
                btnRefreshReports.addEventListener('click', refreshReports);
            }
            if (reportTypeElem) {
                reportTypeElem.addEventListener('change', refreshReports);
            }
            if (reportUserInput) {
                reportUserInput.addEventListener('input', refreshReports);
            }
            if (reportHTEInput) {
                reportHTEInput.addEventListener('input', refreshReports);
            }
            // Helper functions to map name to ID for AJAX
            function getStudentIdByName(name) {
                var options = document.getElementById('studentList').options;
                for (var i = 0; i < options.length; i++) {
                    if (options[i].value === name) {
                        return options[i].getAttribute('data-id');
                    }
                }
                return '';
            }
            function getHTEIdByName(name) {
                var options = document.getElementById('hteList').options;
                for (var i = 0; i < options.length; i++) {
                    if (options[i].value === name) {
                        return options[i].getAttribute('data-id');
                    }
                }
                return '';
            }


            // Load attendance by default on script load
            refreshReports();

        })();
    </script>

    <!-- Modal for Adding Coordinator -->
    <div id="addCoordinatorModal" style="display:none;">
        <form id="addCoordinatorForm">
            <h2>Add New Coordinator/Admin</h2>

            <div class="form-group">
                <label for="coordinatorId">Coordinator ID:</label>
                <input type="text" id="coordinatorId" name="coordinatorId" required 
                       placeholder="Enter unique ID">
            </div>

            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required 
                       placeholder="Enter full name">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required 
                       placeholder="Enter email address">
            </div>

            <div class="form-group">
                <label for="contactNumber">Contact Number:</label>
                <input type="text" id="contactNumber" name="contactNumber" required 
                       placeholder="Enter contact number">
            </div>

            <div class="form-group">
                <label for="department">Department:</label>
                <input type="text" id="department" name="department" required 
                       placeholder="Enter department">
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required 
                       placeholder="Enter username">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter password">
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="COORDINATOR">Coordinator</option>
                    <option value="ADMIN">Admin</option>
                    <option value="SUPERADMIN">Superadmin</option>
                </select>
            </div>

            <div class="form-group" id="hteDropdownContainer" style="display:none;">
                <label for="hteSelect">Select HTE:</label>
                <select id="hteSelect" name="hteId">
                    <option value="">Select HTE</option>
                </select>
            </div>

            <div class="button-group">
                <button type="submit">Add</button>
                <button type="button" id="closeModal">Cancel</button>
            </div>
        </form>
    </div>
</body>
</html>

