<?php 
session_start();
if(!isset($_SESSION["student_user"]))
{
    header("location:student_login.php");
    die();
}
$student_id = $_SESSION["student_user"];

$path = $_SERVER['DOCUMENT_ROOT'];
require_once $path."/database/database.php";

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
            /* Make radio buttons larger in post-assessment tables */
            .eval-table input[type="radio"] {
                width: 28px;
                height: 28px;
                accent-color: #007bff;
                margin: 0 6px;
            }
        </style>
</head>
<body>
    <input type="hidden" id="hiddenStudentId" value="<?php echo htmlspecialchars($student_id); ?>">
    <!-- Top Header with Hamburger Menu -->
    <div class="top-header">
        <button id="sidebarToggle" class="hamburger-menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="sidebar-logo">
            <div class="logo" onclick="window.location.href='student_dashboard.php'">
                <span>InternConnect</span>
            </div>
        </div>
        <div class="user-profile">
            <div class="notification-icon" id="notificationIcon">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">0</span>
                <div class="notification-dropdown" id="notificationDropdown">
                    <!-- Notifications will be dynamically added here -->
                </div>
            </div>
            <div class="user-avatar-circle" id="userAvatarCircle">
                <i class="fas fa-user"></i>
            </div>
            <span id="userName" class="user-name-text"><?php echo htmlspecialchars($studentName); ?></span>
            <span id="draftUserName" style="display:none;"></span>
            <div class="user-dropdown" id="userDropdown">
                <div class="user-dropdown-header">
                    <div class="user-dropdown-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-dropdown-name" id="userDropdownName"><?php echo htmlspecialchars($studentName); ?></div>
                </div>
                <button onclick="window.location.href='student_dashboard.php'">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </button>
                <button onclick="loadProfileDetails()">
                    <i class="fas fa-user"></i>
                    Profile
                </button>
                <button onclick="window.location.href='ajaxhandler/studentLogout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
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
    <div class="content-area">
        <main class="main-content">
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
                    <span class="stat-value" id="presentDays"><?php echo htmlspecialchars($presentDays); ?></span>
                    <span class="stat-label">This Week</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Hours</h3>
                    <span class="stat-value" id="totalHours">0h</span>
                    <span class="stat-label">Total Hours</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-content">
                    <h3>Current Week</h3>
                    <span class="stat-value" id="currentWeekRange">Loading...</span>
                    <span class="stat-label"><?php echo date('Y'); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3>Attendance Rate</h3>
                    <span class="stat-value" id="attendanceRate">0%</span>
                    <span class="stat-label">Overall</span>
                </div>
            </div>
        </section>
        
        <div class="dashboard-grid">
            <!-- Weekly Overview Card -->
            <div class="card overview-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Weekly Overview</h3>
                </div>
                <div class="card-body">
                    <div class="week-overview" id="weekOverview">
                        <div class="overview-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading weekly data...</span>
                        </div>
                    </div>
                </div>
            </div>

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
                <div class="student-eval-unique-container">
                    <div class="eval-card">
                        <h2 class="student-eval-unique-title">Soft Skills</h2>
                        <div id="softSkillQuestions">
                            <!-- Soft skill questions will be loaded here dynamically -->
                        </div>
                    </div>
                    <div class="eval-card">
                        <h2 class="student-eval-unique-title">Communication Skills</h2>
                        <div id="commSkillQuestions">
                            <!-- Communication skill questions will be loaded here dynamically -->
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
                <!-- Category dropdown header above the table, centered (moved outside student-eval-unique-container) -->
                <div class="category-toggle" style="width: 100%; display: flex; justify-content: center; align-items: center; margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; gap: 16px; width: 100%; max-width: 400px;">
                        <label for="categoryDropdown" style="font-weight: 600; font-size: 1rem; color: #495057; white-space: nowrap;">Select Category:</label>
                        <select id="categoryDropdown" name="categoryDropdown" class="category-dropdown-select">
                            <option value="0">System Development</option>
                            <option value="1">Research</option>
                            <option value="2">Technical Support</option>
                            <option value="3">Business Operation</option>
                            <option value="4">Personal and Interpersonal Skills</option>
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
                                        <th>Question</th>
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
                            <!-- Save Questions Button -->
                            <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
                                <div id="postAssessmentFormMessage" style="margin-top: 24px; color: blue;"></div>
                                <button type="button" id="saveQuestionsBtn" class="btn btn-warning">Save Questions</button>
                            </div>
                        </div>
                        <div class="eval-card post-category" id="researchCategory" style="display:none;">
                            <h2 class="student-eval-unique-title">COMPETENCY ON RESEARCH</h2>
                            <table class="eval-table">
                                <thead>
                                    <tr>
                                        <th>Question</th>
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
                                        <th>Question</th>
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
                                        <th>Question</th>
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
    <div class="eval-card post-category" id="personalSkillsCategory" style="display: none;">
        <h2 class="student-eval-unique-title">B. PERSONAL AND INTERPERSONAL SKILLS</h2>
        <table class="eval-table" id="personalSkillsTable">
            <thead>
                <tr>
                    <th>Question</th>
                    <th colspan="5">Rating (Likert Scale)</th>
                </tr>
            </thead>
            <tbody>
                <!-- JS will populate questions and rating inputs here -->
            </tbody>
        </table>
    </div>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; margin-top: 32px;">
                    <button type="submit" class="btn student-eval-unique-submit" id="submitPostAssessmentBtn">Submit Post-Assessment</button>
                </div>
                <div id="postAssessmentFormMessage" style="margin-top: 24px;"></div>
            </form>
        </div>
    </div>
    </div>

    <!-- Post-Assessment Form (only visible in evaluation tab) -->
    <div class="tab-content assessment-tab" id="postAssessmentTab" style="display:none;">
        <form id="postAssessmentForm">
            <div class="student-eval-unique-container">
                <!-- System Development Table -->
                <div class="eval-card">
                    <h2 class="student-eval-unique-title">System Development</h2>
                    <table class="eval-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Rating (Likert Scale)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="sysdev_q1" class="form-control" placeholder="Write your question here" required></td>
                                <td>
                                    <select name="sysdev_r1" class="form-control" required>
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </td>
                                <td><input type="radio" name="techsup_r1" value="5"> 5</td>
                                <td><input type="radio" name="techsup_r1" value="4"> 4</td>
                                <td><input type="radio" name="techsup_r1" value="3"> 3</td>
                                <td><input type="radio" name="techsup_r1" value="2"> 2</td>
                                <td><input type="radio" name="techsup_r1" value="1"> 1</td>
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </td>
                                <td><input type="radio" name="techsup_r2" value="5"> 5</td>
                                <td><input type="radio" name="techsup_r2" value="4"> 4</td>
                                <td><input type="radio" name="techsup_r2" value="3"> 3</td>
                                <td><input type="radio" name="techsup_r2" value="2"> 2</td>
                                <td><input type="radio" name="techsup_r2" value="1"> 1</td>
                <div class="eval-card">
                    <h2 class="student-eval-unique-title">Research</h2>
                    <table class="eval-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Rating (Likert Scale)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="research_q1" class="form-control" placeholder="Write your question here" required></td>
                                <td>
                                    <select name="research_r1" class="form-control" required>
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><input type="text" name="research_q2" class="form-control" placeholder="Write your question here"></td>
                                <td>
                                    <select name="research_r2" class="form-control">
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Technical Support Table -->
                <div class="eval-card">
                    <h2 class="student-eval-unique-title">Technical Support</h2>
                    <table class="eval-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Rating (Likert Scale)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="techsup_q1" class="form-control" placeholder="Write your question here" required></td>
                                <td>
                                    <select name="techsup_r1" class="form-control" required>
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><input type="text" name="techsup_q2" class="form-control" placeholder="Write your question here"></td>
                                <td>
                                    <select name="techsup_r2" class="form-control">
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Business Operation Table -->
                <div class="eval-card">
                    <h2 class="student-eval-unique-title">Business Operation</h2>
                    <table class="eval-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Rating (Likert Scale)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="bizop_q1" class="form-control" placeholder="Write your question here" required></td>
                                <td>
                                    <select name="bizop_r1" class="form-control" required>
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><input type="text" name="bizop_q2" class="form-control" placeholder="Write your question here"></td>
                                <td>
                                    <select name="bizop_r2" class="form-control">
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 32px;">
                <button type="submit" class="btn student-eval-unique-submit" id="submitPostAssessmentBtn">Submit Post-Assessment</button>
            </div>
            <div id="postAssessmentFormMessage" style="margin-top: 24px;"></div>
        </form>
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
            loadWeekOverview();
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
    </script>
</body>
</html>

