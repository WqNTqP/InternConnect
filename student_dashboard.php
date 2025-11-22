<?php 
session_start();
if(!isset($_SESSION["student_user"]))
{
    header("location:student_login.php");
    die();
}
$student_id = $_SESSION["student_user"];

require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

try {
    $db = new Database();
    $stmt = $db->conn->prepare("SELECT SESSION_ID, HTE_ID FROM intern_details WHERE INTERNS_ID = ?");
    $stmt->execute([$student_id]);
    $internDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($internDetails) {   
        $sessionId = $internDetails['SESSION_ID'];
        $hteId = $internDetails['HTE_ID'];
    } else {
        $sessionId = null;
        $hteId = null;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $sessionId = null;
    $hteId = null;
}

$currentDate = date('Y-m-d');

// Fetch student details for display
try {
$stmt = $db->conn->prepare("SELECT NAME, SURNAME, profile_picture FROM interns_details WHERE INTERNS_ID = ?");
    $stmt->execute([$student_id]);
    $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
$studentName = ($studentDetails['NAME'] ?? '') . ' ' . ($studentDetails['SURNAME'] ?? 'Student');
error_log("Profile Picture: " . ($studentDetails['profile_picture'] ?? 'Not Found'));
} catch (PDOException $e) {
    $studentName = 'Student';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="css/student_dashboard.css">
    <!-- <link rel="stylesheet" href="css/attendance.css"> -->
    <link rel="icon" type="image/x-icon" href="icon/favicon.ico">
    <title>Student Dashboard - Attendance Tracker</title>
        <style>
            /* Enhanced Post-Assessment Styles */
            .eval-table input[type="radio"] {
                width: 28px;
                height: 28px;
                accent-color: #007bff;
                margin: 0 6px;
            }

            /* Post-Assessment Navigation */
            .post-assessment-nav {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 2rem;
                margin-bottom: 2rem;
                text-align: center;
            }

            .nav-header h3 {
                color: #2c3e50;
                margin-bottom: 0.5rem;
                font-size: 1.75rem;
                font-weight: 600;
            }

            .nav-header p {
                color: #6c757d;
                margin-bottom: 1.5rem;
            }

            .category-selector {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 1rem;
                flex-wrap: wrap;
            }

            .selector-label {
                font-weight: 600;
                color: #495057;
                white-space: nowrap;
                font-size: 1.1rem;
            }

            .modern-select {
                padding: 0.75rem 1.25rem;
                border: 2px solid #dee2e6;
                border-radius: 6px;
                background: white;
                color: #495057;
                font-size: 1rem;
                font-weight: 500;
                transition: all 0.3s ease;
                min-width: 280px;
            }

            .modern-select:focus {
                outline: none;
                border-color: #007bff;
                box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            }

            .modern-select option {
                background: white;
                color: #495057;
                padding: 0.5rem;
            }

            /* Modern Category Card */
            .modern-category-card {
                border: none;
                border-radius: 12px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
                overflow: hidden;
                transition: all 0.3s ease;
            }

            .modern-category-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 35px rgba(0,0,0,0.15);
            }

            .category-header {
                background: #f8f9fa;
                padding: 1.5rem;
                display: flex;
                align-items: center;
                gap: 1rem;
                border-bottom: 2px solid #e9ecef;
            }

            .header-icon {
                font-size: 2rem;
                color: #007bff;
            }

            .category-title {
                color: #2c3e50;
                margin: 0 0 0.5rem 0;
                font-size: 1.75rem;
                font-weight: 700;
            }

            .category-description {
                color: #6c757d;
                margin: 0;
                font-size: 1.1rem;
                line-height: 1.4;
            }

            /* Enhanced Table Styles */
            .modern-eval-table {
                margin: 0;
                border-collapse: separate;
                border-spacing: 0;
            }

            .modern-eval-table th {
                background: #f8f9fa;
                color: #495057;
                font-weight: 600;
                padding: 1rem;
                text-align: center;
                border-bottom: 2px solid #dee2e6;
            }

            .modern-eval-table th.question-col {
                text-align: left;
                width: 40%;
                background: #007bff;
                color: white;
            }

            .rating-headers th {
                font-size: 0.875rem;
                padding: 0.75rem 0.5rem;
                background: #e9ecef;
                color: #495057;
            }

            .modern-eval-table td {
                padding: 1.25rem;
                border-bottom: 1px solid #e9ecef;
                vertical-align: middle;
            }

            .modern-eval-table tbody tr:hover {
                background-color: #f8f9fa;
            }

            /* Skills Progress */
            .skills-container {
                padding: 2rem;
            }

            .skills-progress {
                margin-top: 1.5rem;
                padding: 1rem;
                background: #f8f9fa;
                border-radius: 8px;
                text-align: center;
            }

            .progress-info {
                color: #495057;
                font-weight: 500;
            }

            /* Enhanced Form Actions */
            .form-actions-wrapper {
                background: #f8f9fa;
                padding: 2rem;
                border-top: 3px solid #007bff;
                margin-top: 2rem;
            }

            .action-buttons-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1rem;
                flex-wrap: wrap;
                gap: 1rem;
            }

            .btn {
                padding: 0.75rem 2rem;
                border-radius: 8px;
                font-weight: 600;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                cursor: pointer;
                transition: all 0.3s ease;
                border: 2px solid transparent;
                font-size: 1rem;
            }

            .btn-outline-primary {
                background: white;
                color: #007bff;
                border-color: #007bff;
            }

            .btn-outline-primary:hover {
                background: #007bff;
                color: white;
                box-shadow: 0 2px 4px rgba(0,123,255,0.2);
            }

            .btn-primary {
                background: #007bff;
                color: white;
                border-color: #007bff;
            }

            .btn-primary:hover {
                background: #0056b3;
                border-color: #0056b3;
                box-shadow: 0 2px 4px rgba(0,123,255,0.2);
            }

            .submission-info {
                text-align: center;
                opacity: 0.8;
            }

            /* Enhanced Messages */
            .assessment-messages {
                margin: 1.5rem 0;
            }

            .assessment-messages .message {
                padding: 1rem 1.5rem;
                border-radius: 8px;
                margin-bottom: 1rem;
                border-left: 4px solid;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                font-weight: 500;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .assessment-messages .message.success {
                background: #d4edda;
                color: #155724;
                border-left-color: #28a745;
            }

            .assessment-messages .message.error {
                background: #f8d7da;
                color: #721c24;
                border-left-color: #dc3545;
            }

            .assessment-messages .message.info {
                background: #d1ecf1;
                color: #0c5460;
                border-left-color: #17a2b8;
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .category-header {
                    flex-direction: column;
                    text-align: center;
                    padding: 1.5rem;
                }

                .header-icon {
                    font-size: 2.5rem;
                }

                .category-selector {
                    flex-direction: column;
                    gap: 0.75rem;
                }

                .modern-select {
                    width: 100%;
                    min-width: unset;
                }

                .action-buttons-container {
                    justify-content: center;
                }

                .skills-container {
                    padding: 1rem;
                }

                .form-actions-wrapper {
                    padding: 1.5rem;
                }
            }
        </style>
</head>
<body>
    <input type="hidden" id="hiddenStudentId" value="<?php echo htmlspecialchars($student_id); ?>">
    <!-- Top Header with Hamburger Menu -->
    <div class="top-header">
        <div class="header-left">
            <button id="sidebarToggle" class="hamburger-menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="sidebar-logo">
                <div class="logo" onclick="window.location.href='student_dashboard.php'">
                    <span>InternConnect</span>
                </div>
            </div>
        </div>
        <div class="user-profile" id="userProfile">
            <div class="relative">
                <button id="userDropdownToggle" class="modern-user-dropdown">
                    <div class="user-avatar">
                        <?php 
                            $nameParts = explode(' ', $studentName);
                            $initials = (isset($nameParts[0]) ? substr($nameParts[0], 0, 1) : 'S') . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : '');
                        ?>
                        <?php if (!empty($studentDetails['profile_picture']) && $studentDetails['profile_picture'] !== null): ?>
                            <img src="<?php echo htmlspecialchars($studentDetails['profile_picture']); ?>" alt="Profile" class="avatar-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <span class="avatar-initials" style="display: none;"><?php echo $initials; ?></span>
                        <?php else: ?>
                            <span class="avatar-initials"><?php echo $initials; ?></span>
                        <?php endif; ?>
                    </div>
                    <span id="userName" class="user-name" style="display: none;"><?php echo htmlspecialchars($studentName); ?></span>
                    <div class="dropdown-arrow">
                        <i class="fas fa-chevron-down" id="dropdownArrow"></i>
                    </div>
                </button>
                <div id="userDropdown" class="modern-dropdown-menu">
                    <div class="dropdown-header">
                        <div class="header-avatar">
                            <?php if (!empty($studentDetails['profile_picture']) && $studentDetails['profile_picture'] !== null): ?>
                                <img src="<?php echo htmlspecialchars($studentDetails['profile_picture']); ?>" alt="Profile" class="header-avatar-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <span class="header-initials" style="display: none;"><?php echo $initials; ?></span>
                            <?php else: ?>
                                <span class="header-initials"><?php echo $initials; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="header-info">
                            <div class="header-name"><?php echo htmlspecialchars($studentName); ?></div>
                            <div class="header-email">Student</div>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-section">
                        <button onclick="loadProfileDetails()" class="dropdown-item">
                            <div class="item-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="item-content">
                                <span class="item-title">Profile</span>
                                <span class="item-subtitle">View and edit profile</span>
                            </div>
                        </button>
                        <button onclick="window.location.href='ajaxhandler/studentLogout.php'" class="dropdown-item logout-item">
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

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo" onclick="window.location.href='student_dashboard.php'">
                <i class="fas fa-calendar-check"></i>
                <span>InternConnect</span>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <li class="sidebar-item active" data-tab="dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </li>
                <li class="sidebar-item" data-tab="attendance">
                    <i class="fas fa-clock"></i>
                    <span>Attendance</span>
                </li>
                <li class="sidebar-item" data-tab="evaluation">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Evaluation</span>
                </li>
                <li class="sidebar-item" data-tab="history">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </li>
                <li class="sidebar-item" data-tab="report">
                    <i class="fas fa-file-alt"></i>
                    <span>Report</span>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-bottom-buttons">
                <!-- Removed Coordinator Details Button -->
                <!-- Removed Logout Button from Sidebar Footer -->
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="content-area transition-all duration-300 p-3 md:p-6 bg-gray-100 min-h-screen pt-20 md:pt-24 ml-0" id="mainContent">
        <main class="main-content bg-white rounded-lg shadow-md p-3 md:p-6 mb-6">
            <!-- Header -->
            <header class="main-header">
                <div class="header-left">
                    <h1>Student Dashboard</h1>
                    <p class="welcome-message">Welcome back, <?php echo htmlspecialchars(explode(' ', $studentName)[0]); ?>!</p>
                </div>
                <div class="header-right">
                    <div class="current-date" id="currentDateHeader">
                        <i class="fas fa-calendar-day"></i>
                        <span>Loading date...</span>
                    </div>
                </div>
            </header>

<!-- Main Content Tabs -->
<div class="content-tabs">
    <!-- Dashboard Tab -->
    <div class="tab-content active" id="dashboardTab">
        <!-- Quick Stats -->
        <section class="stats-grid" id="statsSection">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Present Days</h3>
                    <div class="stat-loading" id="presentDaysLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading...</span>
                    </div>
                    <span class="stat-value" id="presentDays" style="display: none;"><?php echo htmlspecialchars($presentDays); ?></span>
                    <span class="stat-label">This Week</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Hours</h3>
                    <div class="stat-loading" id="totalHoursLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading...</span>
                    </div>
                    <span class="stat-value" id="totalHours" style="display: none;">0h</span>
                    <span class="stat-label">Total Hours</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-content">
                    <h3>Current Week</h3>
                    <div class="stat-loading" id="currentWeekRangeLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading...</span>
                    </div>
                    <span class="stat-value" id="currentWeekRange" style="display: none;">Loading...</span>
                    <span class="stat-label"><?php echo date('Y'); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3>Attendance Rate</h3>
                    <div class="stat-loading" id="attendanceRateLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading...</span>
                    </div>
                    <span class="stat-value" id="attendanceRate" style="display: none;">0%</span>
                    <span class="stat-label">Overall</span>
                </div>
            </div>
        </section>
        
        <div class="dashboard-grid">
            <!-- Recent Activity Card -->
            <div class="card activity-card">
                <div class="card-header">
                    <h3><i class="fas fa-list-alt"></i> Recent Activity</h3>
                </div>
                <div class="card-body">
                    <div class="recent-activity" id="recentActivity">
                        <div class="activity-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading recent activity...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Tab -->
    <div class="tab-content" id="attendanceTab">
        <div class="card attendance-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-day"></i> Today's Attendance</h3>
                <span class="card-badge" id="todayStatusBadge">Not Checked In</span>
            </div>
            <div class="card-body">
                <div class="attendance-display">
                    <div class="time-display">
                        <div class="time-box">
                            <label>Time In</label>
                            <div class="time-value" id="timeInDisplay">--:--</div>
                        </div>
                        <div class="time-box">
                            <label>Time Out</label>
                            <div class="time-value" id="timeOutDisplay">--:--</div>
                        </div>
                    </div>
                    
                    <div class="attendance-actions">
                        <button id="timeInButton" class="btn btn-primary btn-large">
                            <i class="fas fa-sign-in-alt"></i>
                            Time In
                        </button>
                        <button id="timeOutButton" class="btn btn-secondary btn-large">
                            <i class="fas fa-sign-out-alt"></i>
                            Time Out
                        </button>
                    </div>
                    
                    <div id="attendanceStatusMessage" class="status-message"></div>
                </div>
            </div>
        </div>
    </div>


    <!--Evaluation tab-->
    <!-- Evaluation Tab Content -->
    <div class="tab-content" id="evaluationTab">
        <!-- Top navigation for Pre/Post Assessment (only in evaluation tab) -->
        <div class="eval-topnav" style="display: flex; gap: 16px; margin-bottom: 24px;">
            <button id="preAssessmentTabBtn" class="eval-tab-btn active" type="button">Pre-Assessment</button>
            <button id="postAssessmentTabBtn" class="eval-tab-btn" type="button">Post-Assessment</button>
        </div>
        <!-- Pre-Assessment Form -->
        <div class="assessment-tab" id="preAssessmentTab">
            <form id="evaluationForm">
                <!-- Category dropdown header above the content, centered -->
                <div class="category-toggle" style="width: 100%; display: flex; justify-content: center; align-items: center; margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; gap: 16px; width: 100%; max-width: 400px;">
                        <label for="preAssessmentCategoryDropdown" style="font-weight: 600; font-size: 1rem; color: #495057; white-space: nowrap;">Select Category:</label>
                        <select id="preAssessmentCategoryDropdown" name="preAssessmentCategoryDropdown" class="category-dropdown-select">
                            <option value="soft" selected>Soft Skills</option>
                            <option value="comm">Communication Skills</option>
                            <option value="tech">Technical Skills</option>
                        </select>
                    </div>
                </div>
                <div class="student-eval-unique-container">
                    <!-- Category content below header -->
                    <div id="preAssessmentCategoryContent" style="width: 100%; display: flex; justify-content: center;">
                        <!-- Soft Skills Category -->
                        <div class="eval-card pre-category" id="softSkillsCategory">
                            <h2 class="student-eval-unique-title">Soft Skills</h2>
                            <div id="softSkillQuestions">
                                <!-- Soft skill questions will be loaded here dynamically -->
                            </div>
                        </div>
                        <!-- Communication Skills Category -->
                        <div class="eval-card pre-category" id="commSkillsCategory" style="display:none;">
                            <h2 class="student-eval-unique-title">Communication Skills</h2>
                            <div id="commSkillQuestions">
                                <!-- Communication skill questions will be loaded here dynamically -->
                            </div>
                        </div>
                        <!-- Technical Skills Category -->
                        <div class="eval-card pre-category" id="techSkillsCategory" style="display:none;">
                            <h2 class="student-eval-unique-title">Technical Skills</h2>
                            <div id="techSkillQuestions">
                                <!-- Technical skill questions will be loaded here dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; margin-top: 32px;">
                    <button type="submit" class="btn student-eval-unique-submit" id="submitAnswersBtn">Submit Answers</button>
                </div>
                <div id="evaluationFormMessage" style="margin-top: 24px;"></div>
            </form>
        </div>
        <!-- Post-Assessment Form -->
        <div class="assessment-tab" id="postAssessmentTab" style="display:none;">
            <form id="postAssessmentForm">
                <!-- Improved Category Navigation -->
                <div class="post-assessment-nav">
                    <div class="nav-header">
                        <h3><i class="fas fa-clipboard-check"></i> Post-Assessment Evaluation</h3>
                        <p>Select a category to evaluate your skills and create questions</p>
                    </div>
                    <div class="category-selector">
                        <label for="categoryDropdown" class="selector-label">Select Category:</label>
                        <select id="categoryDropdown" name="categoryDropdown" class="modern-select">
                            <option value="0">🔧 System Development</option>
                            <option value="1">📊 Research Competency</option>
                            <option value="2">🛠️ Technical Support</option>
                            <option value="3">💼 Business Operations</option>
                            <option value="4" selected>👥 Personal & Interpersonal Skills</option>
                        </select>
                    </div>
                </div>
                <div class="student-eval-unique-container">
                    <!-- Category content below header -->
                    <div id="categoryContent" style="width: 100%; display: flex; justify-content: center;">
                        <!-- Category tables will be toggled here -->
                        <div class="eval-card post-category" id="sysdevCategory">
                            <h2 class="student-eval-unique-title">COMPETENCY ON SYSTEM DEVELOPMENT</h2>
                            <table class="eval-table">
                                <thead>
                                    <tr>
                                        <th>Discipline/Task</th>
                                        <th colspan="5">Rating (Likert Scale)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="sysdev_q1" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="sysdev_r1" value="5" required> 5</td>
                                        <td><input type="radio" name="sysdev_r1" value="4"> 4</td>
                                        <td><input type="radio" name="sysdev_r1" value="3"> 3</td>
                                        <td><input type="radio" name="sysdev_r1" value="2"> 2</td>
                                        <td><input type="radio" name="sysdev_r1" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="sysdev_q2" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="sysdev_r2" value="5" required> 5</td>
                                        <td><input type="radio" name="sysdev_r2" value="4"> 4</td>
                                        <td><input type="radio" name="sysdev_r2" value="3"> 3</td>
                                        <td><input type="radio" name="sysdev_r2" value="2"> 2</td>
                                        <td><input type="radio" name="sysdev_r2" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="sysdev_q3" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="sysdev_r3" value="5" required> 5</td>
                                        <td><input type="radio" name="sysdev_r3" value="4"> 4</td>
                                        <td><input type="radio" name="sysdev_r3" value="3"> 3</td>
                                        <td><input type="radio" name="sysdev_r3" value="2"> 2</td>
                                        <td><input type="radio" name="sysdev_r3" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="sysdev_q4" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="sysdev_r4" value="5" required> 5</td>
                                        <td><input type="radio" name="sysdev_r4" value="4"> 4</td>
                                        <td><input type="radio" name="sysdev_r4" value="3"> 3</td>
                                        <td><input type="radio" name="sysdev_r4" value="2"> 2</td>
                                        <td><input type="radio" name="sysdev_r4" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="sysdev_q5" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="sysdev_r5" value="5" required> 5</td>
                                        <td><input type="radio" name="sysdev_r5" value="4"> 4</td>
                                        <td><input type="radio" name="sysdev_r5" value="3"> 3</td>
                                        <td><input type="radio" name="sysdev_r5" value="2"> 2</td>
                                        <td><input type="radio" name="sysdev_r5" value="1"> 1</td>
                                    </tr>
                                </tbody>
                            </table>

                        </div>
                        <div class="eval-card post-category" id="researchCategory" style="display:none;">
                            <h2 class="student-eval-unique-title">COMPETENCY ON RESEARCH</h2>
                            <table class="eval-table">
                                <thead>
                                    <tr>
                                        <th>Discipline/Task</th>
                                        <th colspan="5">Rating (Likert Scale)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="research_q1" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="research_r1" value="5" required> 5</td>
                                        <td><input type="radio" name="research_r1" value="4"> 4</td>
                                        <td><input type="radio" name="research_r1" value="3"> 3</td>
                                        <td><input type="radio" name="research_r1" value="2"> 2</td>
                                        <td><input type="radio" name="research_r1" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="research_q2" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="research_r2" value="5" required> 5</td>
                                        <td><input type="radio" name="research_r2" value="4"> 4</td>
                                        <td><input type="radio" name="research_r2" value="3"> 3</td>
                                        <td><input type="radio" name="research_r2" value="2"> 2</td>
                                        <td><input type="radio" name="research_r2" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="research_q3" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="research_r3" value="5" required> 5</td>
                                        <td><input type="radio" name="research_r3" value="4"> 4</td>
                                        <td><input type="radio" name="research_r3" value="3"> 3</td>
                                        <td><input type="radio" name="research_r3" value="2"> 2</td>
                                        <td><input type="radio" name="research_r3" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="research_q4" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="research_r4" value="5" required> 5</td>
                                        <td><input type="radio" name="research_r4" value="4"> 4</td>
                                        <td><input type="radio" name="research_r4" value="3"> 3</td>
                                        <td><input type="radio" name="research_r4" value="2"> 2</td>
                                        <td><input type="radio" name="research_r4" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="research_q5" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="research_r5" value="5" required> 5</td>
                                        <td><input type="radio" name="research_r5" value="4"> 4</td>
                                        <td><input type="radio" name="research_r5" value="3"> 3</td>
                                        <td><input type="radio" name="research_r5" value="2"> 2</td>
                                        <td><input type="radio" name="research_r5" value="1"> 1</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="eval-card post-category" id="techsupCategory" style="display:none;">
                            <h2 class="student-eval-unique-title">COMPETENCY ON TECHNICAL SUPPORT</h2>
                            <table class="eval-table">
                                <thead>
                                    <tr>
                                        <th>Discipline/Task</th>
                                        <th colspan="5">Rating (Likert Scale)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="techsup_q1" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="techsup_r1" value="5" required> 5</td>
                                        <td><input type="radio" name="techsup_r1" value="4"> 4</td>
                                        <td><input type="radio" name="techsup_r1" value="3"> 3</td>
                                        <td><input type="radio" name="techsup_r1" value="2"> 2</td>
                                        <td><input type="radio" name="techsup_r1" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="techsup_q2" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="techsup_r2" value="5" required> 5</td>
                                        <td><input type="radio" name="techsup_r2" value="4"> 4</td>
                                        <td><input type="radio" name="techsup_r2" value="3"> 3</td>
                                        <td><input type="radio" name="techsup_r2" value="2"> 2</td>
                                        <td><input type="radio" name="techsup_r2" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="techsup_q3" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="techsup_r3" value="5" required> 5</td>
                                        <td><input type="radio" name="techsup_r3" value="4"> 4</td>
                                        <td><input type="radio" name="techsup_r3" value="3"> 3</td>
                                        <td><input type="radio" name="techsup_r3" value="2"> 2</td>
                                        <td><input type="radio" name="techsup_r3" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="techsup_q4" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="techsup_r4" value="5" required> 5</td>
                                        <td><input type="radio" name="techsup_r4" value="4"> 4</td>
                                        <td><input type="radio" name="techsup_r4" value="3"> 3</td>
                                        <td><input type="radio" name="techsup_r4" value="2"> 2</td>
                                        <td><input type="radio" name="techsup_r4" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="techsup_q5" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="techsup_r5" value="5" required> 5</td>
                                        <td><input type="radio" name="techsup_r5" value="4"> 4</td>
                                        <td><input type="radio" name="techsup_r5" value="3"> 3</td>
                                        <td><input type="radio" name="techsup_r5" value="2"> 2</td>
                                        <td><input type="radio" name="techsup_r5" value="1"> 1</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="eval-card post-category" id="bizopCategory" style="display:none;">
                            <h2 class="student-eval-unique-title">COMPETENCY ON BUSINESS OPERATION</h2>
                            <table class="eval-table">
                                <thead>
                                    <tr>
                                        <th>Discipline/Task</th>
                                        <th colspan="5">Rating (Likert Scale)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="bizop_q1" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="bizop_r1" value="5" required> 5</td>
                                        <td><input type="radio" name="bizop_r1" value="4"> 4</td>
                                        <td><input type="radio" name="bizop_r1" value="3"> 3</td>
                                        <td><input type="radio" name="bizop_r1" value="2"> 2</td>
                                        <td><input type="radio" name="bizop_r1" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="bizop_q2" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="bizop_r2" value="5" required> 5</td>
                                        <td><input type="radio" name="bizop_r2" value="4"> 4</td>
                                        <td><input type="radio" name="bizop_r2" value="3"> 3</td>
                                        <td><input type="radio" name="bizop_r2" value="2"> 2</td>
                                        <td><input type="radio" name="bizop_r2" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="bizop_q3" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="bizop_r3" value="5" required> 5</td>
                                        <td><input type="radio" name="bizop_r3" value="4"> 4</td>
                                        <td><input type="radio" name="bizop_r3" value="3"> 3</td>
                                        <td><input type="radio" name="bizop_r3" value="2"> 2</td>
                                        <td><input type="radio" name="bizop_r3" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="bizop_q4" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="bizop_r4" value="5" required> 5</td>
                                        <td><input type="radio" name="bizop_r4" value="4"> 4</td>
                                        <td><input type="radio" name="bizop_r4" value="3"> 3</td>
                                        <td><input type="radio" name="bizop_r4" value="2"> 2</td>
                                        <td><input type="radio" name="bizop_r4" value="1"> 1</td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="bizop_q5" class="form-control" placeholder="Write your question here" required></td>
                                        <td><input type="radio" name="bizop_r5" value="5" required> 5</td>
                                        <td><input type="radio" name="bizop_r5" value="4"> 4</td>
                                        <td><input type="radio" name="bizop_r5" value="3"> 3</td>
                                        <td><input type="radio" name="bizop_r5" value="2"> 2</td>
                                        <td><input type="radio" name="bizop_r5" value="1"> 1</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="eval-card post-category modern-category-card" id="personalSkillsCategory" style="display: none;">
                            <div class="category-header">
                                <div class="header-icon">👥</div>
                                <div class="header-content">
                                    <h2 class="category-title">Personal and Interpersonal Skills</h2>
                                    <p class="category-description">Rate your communication, teamwork, and personal development abilities</p>
                                </div>
                            </div>
                            <div class="skills-container">
                                <div class="table-wrapper">
                                    <table class="eval-table modern-eval-table" id="personalSkillsTable">
                                        <thead>
                                            <tr>
                                                <th class="question-col">Skill Area</th>
                                                <th colspan="5" class="rating-col">Self-Rating (1-5 Scale)</th>
                                            </tr>
                                            <tr class="rating-headers">
                                                <th></th>
                                                <th>Excellent<br><small>(5)</small></th>
                                                <th>Good<br><small>(4)</small></th>
                                                <th>Average<br><small>(3)</small></th>
                                                <th>Fair<br><small>(2)</small></th>
                                                <th>Poor<br><small>(1)</small></th>
                                            </tr>
                                        </thead>
                                        <tbody id="personalSkillsTableBody">
                                            <!-- Skills will be populated here by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="skills-progress">
                                    <div class="progress-info">
                                        <span id="skillsProgress">0 of 0 skills rated</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions-wrapper">
                    <div class="action-buttons-container">
                        <button type="button" class="btn btn-outline-primary" id="saveProgressBtn">
                            <i class="fas fa-save"></i> Save Questions
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitPostAssessmentBtn">
                            <i class="fas fa-paper-plane"></i> Submit Assessment
                        </button>
                    </div>
                    <div class="submission-info">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Your progress is automatically saved. You can submit when all categories are complete.
                        </small>
                    </div>
                </div>
                <div id="postAssessmentFormMessage" class="assessment-messages"></div>
            </form>
        </div>
    </div>
    </div>









                <!-- Attendance History Tab -->
                <div class="tab-content" id="historyTab">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Attendance History</h3>
                    <div class="date-filter">
                        <button id="clearFiltersBtn" class="btn-clear-filter" style="display: none;" title="Clear all filters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <select id="monthFilter" style="display: none;">
                            <option value="">Select Month</option>
                        </select>
                        <select id="yearFilter" style="display: none;">
                            <option value="">Select Year</option>
                        </select>
                        <select id="historyFilter">
                            <option value="week">This Week</option>
                            <option value="lastweek">Last Week</option>
                            <option value="month">This Month</option>
                            <option value="lastmonth">Last Month</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                        </div>
                        <div class="card-body">

                            <div id="attendanceHistoryArea">
                                <!-- Loading state -->
                                <div id="attendanceHistoryLoading" class="history-loading">
                                    <div class="loading-content">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <span>Loading attendance history...</span>
                                    </div>
                                </div>
                                <!-- History will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Tab -->
                <div class="tab-content" id="profileTab">
                    <!-- Profile tab content removed -->
                </div>
<div class="tab-content" id="reportTab">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Weekly Activity Report</h3>
        </div>
        <div class="card-body">
            <input type="hidden" id="reportWeek" value="<?php echo date('W'); ?>">
<div id="reportContainer">
                <div class="report-editor">

                    <div class="weekly-report-inputs" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                        <button id="prevDayBtn" type="button" class="btn btn-light" style="font-size: 1.2em;">&#8592;</button>
                        <span id="currentDayLabel" style="font-weight: bold; font-size: 1.1em;"></span>
                        <button id="nextDayBtn" type="button" class="btn btn-light" style="font-size: 1.2em;">&#8594;</button>
                    </div>

                    <div class="everyday-image">
                        <div class="day-report" id="reportMonday">
                            <div class="day-description">
                                <label for="mondayDescription">Monday Description:</label>
                                <textarea id="mondayDescription" name="mondayDescription" rows="4" placeholder="Describe your activities for Monday..."></textarea>
                            </div>
                            <label>Monday Images:</label>
                            <div class="image-upload-area" data-day="monday">
                                <div class="upload-placeholder" id="uploadPlaceholderMonday" onclick="document.getElementById('imageUploadMonday').click();">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag & drop images here or click to browse</p>
                                    <input type="file" id="imageUploadMonday" multiple accept="image/*" style="display: none;">
                                </div>
                                <div id="imagePreviewMonday" class="image-preview"></div>
                            </div>
                        </div>
                        <div class="day-report" id="reportTuesday">
                            <div class="day-description">
                                <label for="tuesdayDescription">Tuesday Description:</label>
                                <textarea id="tuesdayDescription" name="tuesdayDescription" rows="4" placeholder="Describe your activities for Tuesday..."></textarea>
                            </div>
                            <label>Tuesday Images:</label>
                            <div class="image-upload-area" data-day="tuesday">
                                <div class="upload-placeholder" id="uploadPlaceholderTuesday" onclick="document.getElementById('imageUploadTuesday').click();">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag & drop images here or click to browse</p>
                                    <input type="file" id="imageUploadTuesday" multiple accept="image/*" style="display: none;">
                                </div>
                                <div id="imagePreviewTuesday" class="image-preview"></div>
                            </div>
                        </div>
                        <div class="day-report" id="reportWednesday">
                            <div class="day-description">
                                <label for="wednesdayDescription">Wednesday Description:</label>
                                <textarea id="wednesdayDescription" name="wednesdayDescription" rows="4" placeholder="Describe your activities for Wednesday..."></textarea>
                            </div>
                            <label>Wednesday Images:</label>
                            <div class="image-upload-area" data-day="wednesday">
                                <div class="upload-placeholder" id="uploadPlaceholderWednesday" onclick="document.getElementById('imageUploadWednesday').click();">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag & drop images here or click to browse</p>
                                    <input type="file" id="imageUploadWednesday" multiple accept="image/*" style="display: none;">
                                </div>
                                <div id="imagePreviewWednesday" class="image-preview"></div>
                            </div>
                        </div>
                        <div class="day-report" id="reportThursday">
                            <div class="day-description">
                                <label for="thursdayDescription">Thursday Description:</label>
                                <textarea id="thursdayDescription" name="thursdayDescription" rows="4" placeholder="Describe your activities for Thursday..."></textarea>
                            </div>
                            <label>Thursday Images:</label>
                            <div class="image-upload-area" data-day="thursday">
                                <div class="upload-placeholder" id="uploadPlaceholderThursday" onclick="document.getElementById('imageUploadThursday').click();">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag & drop images here or click to browse</p>
                                    <input type="file" id="imageUploadThursday" multiple accept="image/*" style="display: none;">
                                </div>
                                <div id="imagePreviewThursday" class="image-preview"></div>
                            </div>
                        </div>
                        <div class="day-report" id="reportFriday">
                            <div class="day-description">
                                <label for="fridayDescription">Friday Description:</label>
                                <textarea id="fridayDescription" name="fridayDescription" rows="4" placeholder="Describe your activities for Friday..."></textarea>
                            </div>
                            <label>Friday Images:</label>
                            <div class="image-upload-area" data-day="friday">
                                <div class="upload-placeholder" id="uploadPlaceholderFriday" onclick="document.getElementById('imageUploadFriday').click();">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag & drop images here or click to browse</p>
                                    <input type="file" id="imageUploadFriday" multiple accept="image/*" style="display: none;">
                                </div>
                                <div id="imagePreviewFriday" class="image-preview"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="report-actions">
                        <!-- Removed Browse Images button as requested -->
                        <button id="previewReportBtn" class="btn btn-info">
                            <i class="fas fa-eye"></i> Preview Report
                        </button>
                        <button id="saveDraftBtn" class="btn btn-secondary">
                            <i class="fas fa-save"></i> Save Draft
                        </button>
                        <button id="submitReportBtn" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Report
                        </button>
                        <button id="exportPdfBtn" class="btn btn-success" style="display: none;">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                    
<div class="status-container">
    <div id="reportStatus" class="report-status"></div>
    <div id="returnReasonContainer"></div>
</div>
<div id="draftPreview" class="draft-preview">
    <h4><i class="fas fa-eye"></i> Draft Preview</h4>
    <div id="draftContentPreview"></div>
    <div id="draftImagesPreview"></div>
</div>
                </div>

                <div id="reportDraft" class="report-draft">
                    <div class="report-header">
                        <h3><i class="fas fa-file-alt"></i> Weekly Activity Report Preview</h3>
                        <div class="report-meta">
                            <div class="report-period">Week <span id="draftWeekNumber"><?php echo date('W'); ?></span> (<span id="draftWeekRange"><?php echo date('M d', strtotime('monday this week')); ?> - <?php echo date('M d', strtotime('friday this week')); ?>, <?php echo date('Y'); ?></span>)</div>
                            <div class="report-status draft">Draft</div>
                        </div>
                    </div>

                    <div class="report-grid">
                        <div class="day-section">
                            <h4>Monday</h4>
                            <div class="day-content">
                                <div class="day-images" id="draftMondayImages">
                                    <!-- Images will be populated here -->
                                </div>
                                <div class="day-description" id="draftMondayDescription">
                                    No description provided for Monday.
                                </div>
                            </div>
                        </div>

                        <div class="day-section">
                            <h4>Tuesday</h4>
                            <div class="day-content">
                                <div class="day-images" id="draftTuesdayImages">
                                    <!-- Images will be populated here -->
                                </div>
                                <div class="day-description" id="draftTuesdayDescription">
                                    No description provided for Tuesday.
                                </div>
                            </div>
                        </div>

                        <div class="day-section">
                            <h4>Wednesday</h4>
                            <div class="day-content">
                                <div class="day-images" id="draftWednesdayImages">
                                    <!-- Images will be populated here -->
                                </div>
                                <div class="day-description" id="draftWednesdayDescription">
                                    No description provided for Wednesday.
                                </div>
                            </div>
                        </div>

                        <div class="day-section">
                            <h4>Thursday</h4>
                            <div class="day-content">
                                <div class="day-images" id="draftThursdayImages">
                                    <!-- Images will be populated here -->
                                </div>
                                <div class="day-description" id="draftThursdayDescription">
                                    No description provided for Thursday.
                                </div>
                            </div>
                        </div>

                        <div class="day-section">
                            <h4>Friday</h4>
                            <div class="day-content">
                                <div class="day-images" id="draftFridayImages">
                                    <!-- Images will be populated here -->
                                </div>
                                <div class="day-description" id="draftFridayDescription">
                                    No description provided for Friday.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="report-footer">
                        <div class="submitted-date">Created: <span id="draftCreatedDate"><?php echo date('M d, Y'); ?></span></div>
                        <div class="updated-date">Last Updated: <span id="draftUpdatedDate"><?php echo date('M d, Y'); ?></span></div>
                    </div>
                </div>

                <div id="submittedReports" class="submitted-reports" style="display: none;">
                    <h4>Submitted Reports</h4>
                    <div id="reportsList"></div>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>
        </main>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
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

    <!-- Hidden Fields -->
    <input type="hidden" id="hiddenStudentId" value="<?php echo htmlspecialchars($student_id); ?>">
    <input type="hidden" id="hiddenSessionId" value="<?php echo htmlspecialchars($sessionId ?? ''); ?>">
    <input type="hidden" id="hiddenHteId" value="<?php echo htmlspecialchars($hteId ?? ''); ?>">

    <style>
    /* Loading spinner for evaluation questions */
    .eval-loading-spinner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        text-align: center;
        color: #666;
    }
    
    .eval-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #007bff;
        border-radius: 50%;
        animation: eval-spin 1s linear infinite;
        margin-bottom: 16px;
    }
    
    @keyframes eval-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .eval-loading-text {
        font-size: 14px;
        font-weight: 500;
        margin-top: 8px;
    }
    </style>

    <script src="js/jquery.js"></script>
    <script src="js/student_dashboard.js"></script>

        <script>
        $(document).ready(function() {
            // If ratings exist, disable the toggle button for this category
            <?php
            // Only output JS if ratings exist
            if (isset($personalSkills) && count($personalSkills) > 0) {
            ?>
                $('#personalSkillsCategoryBtn').prop('disabled', true);
            <?php } ?>
        });
        </script>

        <script>
            // Hide remove-image buttons if report status is approved or pending
            $(document).ready(function() {
                function updateRemoveImageButtons() {
                    var statusText = $('#reportStatus').text().toLowerCase();
                    if (statusText.includes('approved') || statusText.includes('pending')) {
                        $('.remove-image').hide();
                    } else {
                        $('.remove-image').show();
                    }
                }
                updateRemoveImageButtons();
                // If status can change dynamically, observe changes
                const observer = new MutationObserver(updateRemoveImageButtons);
                observer.observe(document.getElementById('reportStatus'), { childList: true, subtree: true });
            });
        </script>
    <script>
        // Initialize dashboard
        $(document).ready(function() {
            // Load initial data
            loadDashboardStats();
            loadAttendanceStatus();
            loadCurrentWeek();
            loadRecentActivity();
            updateCurrentDate();
            
            // Tab navigation
$('.sidebar-item').click(function() {
    const tab = $(this).data('tab');
    $('.sidebar-item').removeClass('active');
    $(this).addClass('active');
    $('.tab-content').removeClass('active');
    $(`#${tab}Tab`).addClass('active');

    // Load attendance history if the history tab is selected
    if (tab === 'history') {
        loadAttendanceHistory();
    }
    // Load evaluation questions if the evaluation tab is selected
    if (tab === 'evaluation') {
        if (typeof loadStudentEvaluationQuestions === 'function') {
            loadStudentEvaluationQuestions();
        }
    }
});
            
            // Sidebar toggle - handled by external student_dashboard.js
            // This prevents conflict with the click-outside functionality
            
            // Update date every minute
            setInterval(updateCurrentDate, 60000);
        });
        
        function updateCurrentDate() {
            const today = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                timeZone: 'Asia/Manila'
            };
            const formattedDate = today.toLocaleDateString('en-PH', options);
            $('#currentDateHeader span').text(formattedDate);
        }
        
        function printAttendance() {
            // Placeholder for print functionality
            alert('Print functionality will be implemented soon');
        }

        // Modern user dropdown functionality and sidebar collapse
        $(document).ready(function() {
            let sidebarOpen = false;
            let sidebarCollapsed = false;
            
            // Initialize sidebar state based on screen size
            function initializeSidebar() {
                const sidebar = $('.sidebar');
                const body = $('body');
                
                console.log('Initializing sidebar for screen width:', $(window).width());
                
                if ($(window).width() >= 769) {
                    // Desktop: sidebar is always open, start in expanded state
                    sidebarOpen = true;
                    sidebarCollapsed = false;
                    sidebar.addClass('sidebar-open').removeClass('collapsed');
                    body.addClass('sidebar-open').removeClass('sidebar-collapsed');
                    console.log('Desktop: Sidebar initialized as open/expanded');
                } else {
                    // Mobile: can be closed completely
                    sidebarOpen = false;
                    sidebarCollapsed = false;
                    sidebar.removeClass('sidebar-open').removeClass('collapsed');
                    body.removeClass('sidebar-open').removeClass('sidebar-collapsed');
                    $('#sidebarOverlay').removeClass('active');
                    console.log('Mobile: Sidebar initialized as closed');
                }
                
                console.log('Initialization complete:', {
                    sidebarOpen: sidebarOpen,
                    sidebarCollapsed: sidebarCollapsed,
                    bodyClasses: body.attr('class')
                });
            }
            
            // Initialize on page load
            initializeSidebar();
            
            // Reinitialize on window resize
            $(window).resize(function() {
                initializeSidebar();
            });

            // Toggle sidebar visibility
            $('#sidebarToggle').click(function() {
                const sidebar = $('.sidebar');
                const body = $('body');
                
                console.log('Before toggle:', {
                    sidebarOpen: sidebarOpen,
                    sidebarCollapsed: sidebarCollapsed,
                    bodyClasses: body.attr('class')
                });
                
                if ($(window).width() >= 769) {
                    // Desktop behavior - TWO STATES ONLY: expanded <-> collapsed
                    // Sidebar is ALWAYS open on desktop, just toggles between expanded and collapsed
                    if (!sidebarCollapsed) {
                        // State 1: Open (expanded) -> Open (collapsed/icons only)
                        sidebarCollapsed = true;
                        sidebar.addClass('collapsed');
                        body.addClass('sidebar-collapsed');
                        console.log('Desktop: Expanded -> Collapsed (icons only)');
                    } else {
                        // State 2: Open (collapsed/icons only) -> Open (expanded)
                        sidebarCollapsed = false;
                        sidebar.removeClass('collapsed');
                        body.removeClass('sidebar-collapsed');
                        console.log('Desktop: Collapsed (icons only) -> Expanded');
                    }
                    // sidebarOpen remains TRUE on desktop - never false
                } else {
                    // Mobile behavior - toggle show/hide (can be completely closed)
                    sidebarOpen = !sidebarOpen;
                    if (sidebarOpen) {
                        sidebar.addClass('sidebar-open');
                        body.addClass('sidebar-open');
                        $('#sidebarOverlay').addClass('active');
                        console.log('Mobile: Sidebar opened');
                    } else {
                        sidebar.removeClass('sidebar-open');
                        body.removeClass('sidebar-open');
                        $('#sidebarOverlay').removeClass('active');
                        console.log('Mobile: Sidebar closed');
                    }
                }
                
                console.log('After toggle:', {
                    sidebarOpen: sidebarOpen,
                    sidebarCollapsed: sidebarCollapsed,
                    bodyClasses: body.attr('class')
                });
            });

            // Close sidebar when clicking overlay (mobile)
            $('#sidebarOverlay').click(function() {
                const sidebar = $('.sidebar');
                const body = $('body');
                sidebarOpen = false;
                sidebar.removeClass('sidebar-open');
                body.removeClass('sidebar-open');
                $(this).removeClass('active');
            });

            // Click outside sidebar to close/collapse it
            $(document).on('click', function(e) {
                const sidebar = $('.sidebar');
                const sidebarToggle = $('#sidebarToggle');
                const body = $('body');
                
                // Check if click is outside sidebar and not on UI elements that should be ignored
                if (!sidebar.is(e.target) && !sidebar.has(e.target).length && 
                    !sidebarToggle.is(e.target) && !sidebarToggle.has(e.target).length &&
                    !$(e.target).closest('#userProfile, .user-profile, .dropdown, .modal').length) {
                    
                    if ($(window).width() >= 769) {
                        // Desktop: collapse to icons if expanded, or do nothing if already collapsed
                        if (sidebarOpen && !sidebarCollapsed) {
                            sidebarCollapsed = true;
                            sidebar.addClass('collapsed');
                            body.addClass('sidebar-collapsed');
                            console.log('Desktop: Auto-collapsed to icons due to outside click');
                        }
                    } else {
                        // Mobile: close completely
                        if (sidebarOpen) {
                            sidebarOpen = false;
                            sidebar.removeClass('sidebar-open');
                            body.removeClass('sidebar-open');
                            $('#sidebarOverlay').removeClass('active');
                            console.log('Mobile: Auto-closed due to outside click');
                        }
                    }
                }
            });



            // Modern user dropdown functionality
            $('#userDropdownToggle').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const dropdown = $('#userDropdown');
                const arrow = $('#dropdownArrow');
                
                if (dropdown.hasClass('show')) {
                    dropdown.removeClass('show').css('display', 'none');
                    arrow.css('transform', 'rotate(0deg)');
                } else {
                    dropdown.addClass('show').css('display', 'flex');
                    arrow.css('transform', 'rotate(180deg)');
                }
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(event) {
                const userProfile = $('#userProfile');
                const dropdown = $('#userDropdown');
                const arrow = $('#dropdownArrow');
                
                if (!userProfile.is(event.target) && userProfile.has(event.target).length === 0) {
                    dropdown.removeClass('show').css('display', 'none');
                    arrow.css('transform', 'rotate(0deg)');
                }
            });
            
            // Prevent dropdown from closing when clicking inside
            $('#userDropdown').on('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>

