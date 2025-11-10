<?php 

//kani na code kay condition ni siya 
//para if wala naka login mo balik raka sa login page
session_start();
// Ensure coordinator session variables are set for backend access
if (isset($_SESSION["current_user"]) && !isset($_SESSION["coordinator_user"])) {
    $_SESSION["coordinator_user"] = $_SESSION["current_user"];
    $_SESSION["user_type"] = "coordinator";
    $_SESSION["user_id"] = $_SESSION["current_user"];
}
    if(isset($_SESSION["current_user"]))
        {
            $cdrid=$_SESSION["current_user"];
        }
    else{
        header("location:index.php");
        die();
    }

// Function to generate student filter options based on coordinator
function generateStudentFilterOptions($coordinatorId) {
    try {
        $path = $_SERVER['DOCUMENT_ROOT'];
        require_once $path."/database/database.php";
        $db = new Database();
        
        $stmt = $db->conn->prepare("
            SELECT DISTINCT s.STUDENT_ID, s.NAME, s.SURNAME 
            FROM student s 
            JOIN coordinator c ON s.COORDINATOR_ID = c.COORDINATOR_ID 
            WHERE c.COORDINATOR_ID = ? 
            ORDER BY s.SURNAME, s.NAME
        ");
        $stmt->execute([$coordinatorId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $options = '<option value="all">All Students</option>';
        foreach ($students as $student) {
            $fullName = trim($student['SURNAME'] . ', ' . $student['NAME']);
            $options .= '<option value="' . htmlspecialchars($student['STUDENT_ID']) . '">' . htmlspecialchars($fullName) . '</option>';
        }
        
        return $options;
    } catch (Exception $e) {
        error_log("Error generating student filter options: " . $e->getMessage());
        return '<option value="all">All Students</option>';
    }
}
    
    // Get display name for user
    $displayName = 'User';
    if(isset($_SESSION["current_user_name"])) {
        $displayName = $_SESSION["current_user_name"];
    } elseif(isset($_SESSION["current_user"])) {
        // For backwards compatibility, fetch name from database
        $path=$_SERVER['DOCUMENT_ROOT'];
        require_once $path."/database/database.php";
        try {
            $db = new Database();
            $stmt = $db->conn->prepare("SELECT NAME FROM coordinator WHERE COORDINATOR_ID = ?");
            $stmt->execute([$_SESSION["current_user"]]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $displayName = $result['NAME'];
                $_SESSION["current_user_name"] = $result['NAME']; // Cache for future use
            } else {
                $displayName = $_SESSION["current_user"];
            }
        } catch (Exception $e) {
            $displayName = $_SESSION["current_user"];
        }
    } elseif(isset($_SESSION["student_user"])) {
        // For student users, fetch name from database
        $path=$_SERVER['DOCUMENT_ROOT'];
        require_once $path."/database/database.php";
        try {
            $db = new Database();
            $stmt = $db->conn->prepare("SELECT NAME FROM student WHERE STUDENT_ID = ?");
            $stmt->execute([$_SESSION["student_user"]]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $displayName = $result['NAME'];
                $_SESSION["student_name"] = $result['NAME']; // Cache for future use
            } else {
                $displayName = $_SESSION["student_user"];
            }
        } catch (Exception $e) {
            $displayName = $_SESSION["student_user"];
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1a56db',
                    },
                },
            },
            plugins: [
                function({ addBase, theme }) {
                    addBase({
                        'select': {
                            'backgroundColor': theme('colors.white'),
                            'borderColor': theme('colors.gray.300'),
                            'borderRadius': theme('borderRadius.md'),
                            'paddingTop': theme('spacing.2'),
                            'paddingBottom': theme('spacing.2'),
                            'paddingLeft': theme('spacing.3'),
                            'paddingRight': theme('spacing.3'),
                            'fontSize': theme('fontSize.sm'),
                            'lineHeight': theme('lineHeight.normal'),
                            '&:focus': {
                                'outline': 'none',
                                'ring': '2px',
                                'ringColor': theme('colors.blue.500'),
                                'borderColor': theme('colors.blue.500'),
                            },
                        },
                    })
                },
            ],
        }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" type="image/x-icon" href="icon/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>InternConnect - Dashboard</title>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        
        /* Modal transition styles */
        #changePasswordModal {
            transition: opacity 300ms ease-in-out;
        }
        #changePasswordModal.opacity-0 {
            opacity: 0;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 768px) {
            .mobile-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            /* Ensure tables are scrollable on mobile */
            table {
                min-width: 600px;
            }
            
            /* Better button sizing on mobile */
            .mobile-btn {
                padding: 8px 12px;
                font-size: 14px;
            }
            
            /* Prevent horizontal overflow */
            body {
                overflow-x: hidden;
            }
            
            /* Better select dropdown styling on mobile */
            select {
                padding: 8px 12px;
                font-size: 14px;
            }
        }
        
        /* Hide scrollbar for webkit browsers */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Report Tab Loader -->
    <div id="reportLoader" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600"></div>
    </div>

    <div class="min-h-screen" x-data="{ sidebarOpen: false, isMobile: window.innerWidth < 768 }" @resize.window="isMobile = window.innerWidth < 768">
        <!-- Mobile Overlay -->
        <div x-show="sidebarOpen && isMobile" @click="sidebarOpen = false" class="fixed inset-0 bg-black bg-opacity-50 z-35 md:hidden"></div>
        
        <nav class="bg-white shadow-lg fixed w-full top-0 z-30">
            <div class="px-4">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none" aria-label="Toggle Sidebar">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <div class="ml-4 cursor-pointer" onclick="window.location.href='mainDashboard.php';">
                            <h2 class="text-lg md:text-xl font-semibold text-gray-800">InternConnect</h2>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                                <span class="text-xs md:text-sm font-medium hidden sm:inline">KIM CHARLES</span>
                                <span class="text-xs md:text-sm font-medium sm:hidden">KC</span>
                                <svg class="h-4 w-4 md:h-5 md:w-5" :class="{'transform rotate-180': open}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 py-1 z-50" style="display: none;">
                                <button id="btnProfile" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</button>
                                <button id="logoutBtn" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar -->
        <div class="fixed left-0 bg-gray-800 text-white shadow-lg transition-all duration-300 ease-in-out z-40" 
             :class="{
                'w-64': sidebarOpen && !isMobile,
                'w-16': !sidebarOpen && !isMobile,
                'w-64 translate-x-0': sidebarOpen && isMobile,
                '-translate-x-full w-64': !sidebarOpen && isMobile
             }" 
             style="top: 64px; height: calc(100vh - 64px);" id="sidebar">
            <ul class="py-2">
                <li class="px-3 md:px-6 py-3 cursor-pointer hover:bg-gray-700 flex items-center space-x-3 overflow-hidden" id="attendanceTab" data-tab="attendance" @click="isMobile && (sidebarOpen = false)">
                    <i class="fas fa-calendar-check min-w-[16px] text-sm md:text-base"></i>
                    <span :class="{'opacity-0': !sidebarOpen && !isMobile}" class="transition-opacity duration-300 text-sm md:text-base" :class="isMobile ? 'opacity-100' : (!sidebarOpen ? 'opacity-0' : 'opacity-100')">Attendance</span>
                </li>
                <li class="px-3 md:px-6 py-3 cursor-pointer hover:bg-gray-700 flex items-center space-x-3 overflow-hidden bg-gray-700" id="evaluationTab" data-tab="evaluation" @click="isMobile && (sidebarOpen = false)">
                    <i class="fas fa-clipboard-list min-w-[16px] text-sm md:text-base"></i>
                    <span :class="{'opacity-0': !sidebarOpen && !isMobile}" class="transition-opacity duration-300 text-sm md:text-base" :class="isMobile ? 'opacity-100' : (!sidebarOpen ? 'opacity-0' : 'opacity-100')">Evaluation</span>
                </li>
                <li class="px-3 md:px-6 py-3 cursor-pointer hover:bg-gray-700 flex items-center space-x-3 overflow-hidden" id="controlTab" data-tab="control" @click="isMobile && (sidebarOpen = false)">
                    <i class="fas fa-cogs min-w-[16px] text-sm md:text-base"></i>
                    <span :class="{'opacity-0': !sidebarOpen && !isMobile}" class="transition-opacity duration-300 text-sm md:text-base" :class="isMobile ? 'opacity-100' : (!sidebarOpen ? 'opacity-0' : 'opacity-100')">Control</span>
                </li>
                <li class="px-3 md:px-6 py-3 cursor-pointer hover:bg-gray-700 flex items-center space-x-3 overflow-hidden" id="reportTab" data-tab="report" @click="isMobile && (sidebarOpen = false)">
                    <i class="fas fa-file-alt min-w-[16px] text-sm md:text-base"></i>
                    <span :class="{'opacity-0': !sidebarOpen && !isMobile}" class="transition-opacity duration-300 text-sm md:text-base" :class="isMobile ? 'opacity-100' : (!sidebarOpen ? 'opacity-0' : 'opacity-100')">Report</span>
                </li>
                <li class="px-3 md:px-6 py-3 cursor-pointer hover:bg-gray-700 flex items-center space-x-3 overflow-hidden" id="predictionTab" data-tab="prediction" @click="isMobile && (sidebarOpen = false)">
                    <i class="fas fa-chart-line min-w-[16px] text-sm md:text-base"></i>
                    <span :class="{'opacity-0': !sidebarOpen && !isMobile}" class="transition-opacity duration-300 text-sm md:text-base" :class="isMobile ? 'opacity-100' : (!sidebarOpen ? 'opacity-0' : 'opacity-100')">Prediction</span>
                </li>
                <li class="px-3 md:px-6 py-3 cursor-pointer hover:bg-gray-700 flex items-center space-x-3 overflow-hidden" id="postAnalysisTab" data-tab="postAnalysis" @click="isMobile && (sidebarOpen = false)">
                    <i class="fas fa-chart-bar min-w-[16px] text-sm md:text-base"></i>
                    <span :class="{'opacity-0': !sidebarOpen && !isMobile}" class="transition-opacity duration-300 text-sm md:text-base" :class="isMobile ? 'opacity-100' : (!sidebarOpen ? 'opacity-0' : 'opacity-100')">Post-Analysis</span>
                </li>
            </ul>
        </div>

    <!-- Main Content -->
<div class="transition-all duration-300 p-3 md:p-6 bg-gray-100 min-h-screen pt-20 md:pt-24 ml-0" :class="{
            'ml-64': sidebarOpen &amp;&amp; !isMobile,
            'ml-16': !sidebarOpen &amp;&amp; !isMobile,
            'ml-0': isMobile
         }">
            <!-- Attendance Tab Content -->
            <div id="attendanceContent" class="bg-white rounded-lg shadow-md p-3 md:p-6 mb-6">
                <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">
                    <!-- Left Column - Controls -->
                    <div class="w-full lg:w-1/4 xl:w-1/5 space-y-4">
                        <!-- Session Selection -->
                        <div class="bg-gray-50 rounded-lg shadow-sm p-3 md:p-4">
                            <div class="flex flex-col">
                                <label class="text-xs md:text-sm font-medium text-gray-700 mb-1">SESSION</label>
                                <select id="ddlclass" class="mt-1 block w-full text-xs md:text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><option value="-1">SELECT ONE</option><option value="1">2024 FIRST SEMESTER</option><option value="9">2025 SECOND SEMESTER</option></select>
                            </div>
                        </div>

                        <!-- Company List -->
                        <div id="classlistarea" class="bg-gray-50 rounded-lg shadow-sm p-3 md:p-4">
                            <div class="flex flex-col">
                                <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
                                <select id="company-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Loading companies...</option>
                                </select>
                            </div>
                        </div>

                        <!-- Company Details -->
                        <div id="classdetailsarea" class="bg-gray-50 rounded-lg shadow-sm p-3 md:p-4"></div>
                        
                        <!-- Generate Report Button -->
                        <div class="bg-gray-50 rounded-lg shadow-sm p-3 md:p-4">
                            <button id="btnReport" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out text-xs md:text-sm">
                                Generate Report
                            </button>
                        </div>
                    </div>

                    <!-- Right Column - Main Content -->
                    <div class="w-full lg:w-3/4 xl:w-4/5">
                        <div id="studentlistarea" class="bg-white rounded-lg shadow-md p-3 md:p-4"><div class="text-gray-500 text-center py-8">No students found.</div></div>
                    </div>
                </div>
            </div>
            <!-- End Attendance Tab Content -->

            <!-- Report Tab Content --><div id="reportContent" class="hidden">
            <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="space-y-6">
                        <div class="border-b pb-4">
                            <h2 class="text-2xl font-bold text-gray-800">Approved Weekly Reports</h2>
                            <p class="text-gray-600 mt-1">Attendance Tracker System</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div class="space-y-2">
                                <label for="filterStudent" class="block text-sm font-medium text-gray-700">Student:</label>
                                <select id="filterStudent" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <?php echo generateStudentFilterOptions($cdrid); ?>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label for="filterDate" class="block text-sm font-medium text-gray-700">Date:</label>
                                <input type="date" id="filterDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <button id="applyReportFilters" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                        <div id="approvedReportsList" class="mt-6 hidden"></div>
                    </div>
                </div>
            </div>
            <!-- End Report Tab Content -->
        
<!-- Evaluation Tab Content -->
<div id="evaluationContent" class="bg-white rounded-lg shadow-md hidden">
    <div class="border-b">
        <nav class="flex flex-wrap gap-2 md:space-x-4 px-3 md:px-6 py-3 overflow-x-auto">
            <button id="evalQuestionsTabBtn"
                class="px-3 md:px-4 py-2 text-xs md:text-sm font-medium text-gray-900 bg-gray-100 rounded-md active whitespace-nowrap">
                All Questions
            </button>
            <button id="rateTabBtn"
                class="px-3 md:px-4 py-2 text-xs md:text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-md whitespace-nowrap">
                Pre-Assessment
            </button>
            <button id="postAssessmentTabBtn"
                class="px-3 md:px-4 py-2 text-xs md:text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-md whitespace-nowrap">
                Post-Assessment
            </button>
            <button id="reviewTabBtn"
                class="px-3 md:px-4 py-2 text-xs md:text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-md whitespace-nowrap">
                Review
            </button>
        </nav>
    </div>

    <div class="p-3 md:p-6">
        <!-- All Questions Tab -->
        <div id="evalQuestionsTabContent" class="space-y-6 active">
            <div class="flex w-full">
                <!-- Left Column -->
                <div class="left-col w-1/5 max-w-xs pr-4">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Categories</h2>
                    <div class="mb-4">
                        <label for="questionCategoryDropdown"
                            class="mr-2 text-gray-700 font-medium">Category:</label>
                        <select id="questionCategoryDropdown"
                            class="border border-gray-300 rounded-md px-3 py-2 text-base focus:outline-none focus:ring-2 focus:ring-blue-500 w-full">
                            <option value="Soft Skills">Soft Skills</option>
                            <option value="Communication Skills">Communication Skills</option>
                            <option value="Technical Skills">Technical Skills</option>
                            <option value="Personal and Interpersonal Skills">Personal and Interpersonal Skills</option>
                        </select>
                    </div>
                    <div class="flex items-center mb-2">
                        <span
                            class="inline-flex items-center px-3 py-1 text-sm font-medium text-blue-700 bg-blue-100 rounded-full mr-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.232 5.232l3.536 3.536M9 13h3l9-9a1.414 1.414 0 00-2-2l-9 9v3z" />
                            </svg>
                            Editable: Click question text to edit
                        </span>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-col w-4/5 pl-4">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">All Evaluation Questions</h2>
                    <div id="questionsByCategory" class="max-h-[calc(100vh-320px)] overflow-y-auto">
                        <ul class="space-y-3">
                            <!-- Evaluation questions will be loaded here dynamically -->
                        </ul>
                    </div>

                    <div class="flex flex-col items-center mt-6 mb-4">
                        <button id="btnSaveAllQuestions"
                            class="px-8 py-2 text-lg font-semibold bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition-all duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            Save All Changes
                        </button>
                        <span id="saveAllStatus"
                            class="mt-4 px-4 py-2 rounded-lg text-base font-medium hidden"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pre-Assessment Tab -->
        <div id="rateTabContent" class="hidden">
            <div class="flex w-full">
                <!-- Left column will be rendered by JavaScript -->
                <div class="right-col w-4/5 pl-4">
                    <div class="flex flex-col items-center justify-center h-full">
                        <div class="bg-blue-50 rounded-full p-6 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-blue-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <!-- This will be replaced by JavaScript rendering -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Post-Assessment Tab -->
        <div id="postAssessmentTabContent" class="hidden">
            <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">
                <div class="w-full lg:w-1/3 bg-gray-50 rounded-lg shadow-md p-3 md:p-4">
                    <div class="mb-4">
                        <input type="text" id="postStudentSearch" placeholder="Search student"
                            class="w-full px-3 md:px-4 py-2 text-sm md:text-base text-gray-700 bg-white border border-gray-300 rounded-md focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div id="postStudentListPanel"
                        class="overflow-y-auto max-h-64 lg:max-h-[calc(100vh-16rem)] divide-y divide-gray-200">
                        <!-- Student list will be loaded here dynamically -->
                    </div>
                </div>
                <div class="w-full lg:w-2/3 bg-white rounded-lg shadow-md p-3 md:p-4">
                    <div id="postEvalList" class="space-y-4">
                        <!-- Post-assessment evaluation cards/messages will be loaded here dynamically -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Tab -->
        <div id="reviewTabContent" class="hidden">
            <div class="flex w-full">
                <div class="left-col w-1/5 max-w-xs pr-4">
                    <div class="mb-4">
                        <input type="text" id="reviewStudentSearch" placeholder="Search student"
                            class="w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div id="reviewStudentListPanel"
                        class="overflow-y-auto max-h-[420px] flex flex-col gap-1">
                        <!-- Review list dynamically loaded -->
                    </div>
                </div>
                <div class="right-col w-4/5 pl-4">
                    <div class="flex flex-col items-center justify-center h-full">
                        <div class="bg-blue-50 rounded-full p-6 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-blue-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div class="text-xl font-semibold text-blue-700 mb-2">No student selected</div>
                        <div class="text-gray-500 text-base">Select a student from the list to view their reviewed
                            assessment details.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Evaluation Tab Content -->


            <!-- Prediction Tab Content -->
            <div id="predictionContent" class="hidden">
                <div class="bg-white rounded-lg shadow-md p-3 md:p-6">
                        <div class="mt-4 md:mt-6 overflow-x-auto">
                            <table id="predictionTable" class="min-w-full rounded-xl shadow-lg overflow-hidden border border-gray-200">
                                <thead class="bg-blue-50">
                                    <tr>
                                        <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Student Name</th>
                                        <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">HTE Assigned</th>
                                        <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
                                        <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Predicted Placement</th>
                                        <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Analysis</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <div class="animate-pulse mb-2">
                                                    <i class="fas fa-chart-line text-2xl text-blue-400"></i>
                                                </div>
                                                <p>Loading prediction data...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
            </div>
            <!-- End Prediction Tab Content -->
            
            <!-- Control Tab Content -->
            <div id="controlContent" class="hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="mb-4 md:mb-6">
                        <div class="grid grid-cols-4 md:flex md:flex-wrap gap-2 md:gap-4">
                            <button id="btnViewAllStudents" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-blue-100 text-blue-600 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="View All Students" title="View All Students">
                                <i class="fas fa-users text-sm"></i>
                            </button>
                            <button id="btnViewAllCompanies" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-indigo-100 text-indigo-600 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="View My Companies" title="View My Assigned Companies">
                                <i class="fas fa-city text-sm"></i>
                            </button>
                            <button id="btnAddStudent" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-green-100 text-green-600 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500" aria-label="Add Student" title="Add Student">
                                <i class="fas fa-user-plus text-sm"></i>
                            </button>
                            <button id="btnAddHTE" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-indigo-100 text-indigo-600 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Add HTE" title="Add HTE">
                                <i class="fas fa-building text-sm"></i>
                            </button>
                            <button id="btnAddSession" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-purple-100 text-purple-600 hover:bg-purple-200 focus:outline-none focus:ring-2 focus:ring-purple-500" aria-label="Add Session" title="Add Session">
                                <i class="fas fa-calendar-plus text-sm"></i>
                            </button>
                            <button id="btnDeleteStudent" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-red-100 text-red-600 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500" aria-label="Delete Student" title="Delete Student">
                                <i class="fas fa-user-minus text-sm"></i>
                            </button>
                            <button id="btnDeleteHTE" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-yellow-100 text-yellow-600 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500" aria-label="Delete HTE" title="Delete HTE">
                                <i class="fas fa-building text-sm"></i>
                            </button>
                            <button id="btnDeleteSession" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-orange-100 text-orange-600 hover:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-500" aria-label="Delete Session" title="Delete Session">
                                <i class="fas fa-calendar-minus text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="space-y-6">
                            <!-- Add Session Form -->
                                <div id="sessionFormContainer" class="form-container" style="display: none;">
                                <h3 class="text-xl font-bold mb-4">Add New Session</h3>
                                <form id="sessionForm" class="bg-white rounded-lg shadow p-6">
                                    <style>
                                        #sessionFormContainer input[type="number"],
                                        #sessionFormContainer select {
                                            background-color: #f5f7fa;
                                            border: 1px solid gray;
                                            color: #222;
                                            font-size: 1.08rem;
                                            font-weight: 500;
                                            padding: 0.75rem 1rem;
                                            margin-bottom: 0.5rem;
                                            transition: border-color 0.2s, box-shadow 0.2s;
                                        }
                                        #sessionFormContainer input[type="number"]:focus,
                                        #sessionFormContainer select:focus {
                                            border-color: #6d28d9;
                                            box-shadow: 0 0 0 2px #7c3aed33;
                                            background-color: #fff;
                                        }
                                    </style>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="sessionYear" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                            <input type="number" id="sessionYear" name="year" min="2000" max="2050" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Year">
                                        </div>
                                        <div>
                                            <label for="sessionTerm" class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                                            <select id="sessionTerm" name="term" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select Term</option>
                                                <option value="FIRST SEMESTER">FIRST SEMESTER</option>
                                                <option value="SECOND SEMESTER">SECOND SEMESTER</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-6 flex gap-4">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition">Add Session</button>
                                        <button type="button" id="closeSessionForm" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-md transition">Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Add Student Form -->
                            <div id="studentFormContainer" class="form-container" style="display:none;">
                                <h3 class="text-xl font-bold mb-4">Add Students (CSV Upload Recommended)</h3>
                                <form id="studentForm" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 mb-6">
                                    <style>
                                        #studentFormContainer input[type="text"],
                                        #studentFormContainer input[type="email"],
                                        #studentFormContainer input[type="file"],
                                        #studentFormContainer input[type="number"],
                                        #studentFormContainer select {
                                            background-color: #f5f7fa;
                                            border: 1px solid gray;
                                            color: #222;
                                            font-size: 1.08rem;
                                            font-weight: 500;
                                            padding: 0.75rem 1rem;
                                            margin-bottom: 0.5rem;
                                            transition: border-color 0.2s, box-shadow 0.2s;
                                        }
                                        #studentFormContainer input[type="text"]:focus,
                                        #studentFormContainer input[type="email"]:focus,
                                        #studentFormContainer input[type="file"]:focus,
                                        #studentFormContainer input[type="number"]:focus,
                                        #studentFormContainer select:focus {
                                            border-color: gray;
                                            box-shadow: 0 0 0 2px #2563eb33;
                                            background-color: #fff;
                                        }
                                        #singleStudentFormWrapper input[type="text"],
                                        #singleStudentFormWrapper input[type="email"],
                                        #singleStudentFormWrapper input[type="number"],
                                        #singleStudentFormWrapper select {
                                            background-color: #f5f7fa;
                                            border: 1px solid gray;
                                            color: #222;
                                            font-size: 1.08rem;
                                            font-weight: 500;
                                            padding: 0.75rem 1rem;
                                            margin-bottom: 0.5rem;
                                            transition: border-color 0.2s, box-shadow 0.2s;
                                        }
                                        #singleStudentFormWrapper input[type="text"]:focus,
                                        #singleStudentFormWrapper input[type="email"]:focus,
                                        #singleStudentFormWrapper input[type="number"]:focus,
                                        #singleStudentFormWrapper select:focus {
                                            border-color: gray;
                                            box-shadow: 0 0 0 2px #2563eb33;
                                            background-color: #fff;
                                        }
                                    </style>
                                    <div class="mb-6">
                                        <label for="csvFile" class="block text-sm font-medium text-gray-700 mb-2">Upload CSV File <span class="text-blue-600 font-semibold">(Recommended)</span></label>
                                        <input type="file" id="csvFile" name="csvFile" accept=".csv" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <small class="block mt-2 text-gray-500">CSV format: student_id,name,surname,age,gender,email,contact_number</small>
                                        <a href="sample_students.csv" download="" class="block mt-1 text-blue-600 hover:underline text-sm">Download Sample CSV Format</a>
                                    </div>
                                    <div class="flex gap-4">
                                        <div class="flex-1">
                                            <label for="sessionSelectStudent" class="block text-sm font-medium text-gray-700 mb-1">Assign to Session:</label>
                                            <select id="sessionSelectStudent" name="sessionId" required="" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select Session</option>
                                            </select>
                                        </div>
                                        <div class="flex-1">
                                            <label for="hteSelectStudent" class="block text-sm font-medium text-gray-700 mb-1">Assign to HTE:</label>
                                            <select id="hteSelectStudent" name="hteId" required="" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select HTE</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-6 flex gap-4">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition">Add Students</button>
                                        <button type="button" id="closeStudentForm" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-md transition">Close</button>
                                    </div>
                                </form>
                                <div class="mt-8">
                                    <button id="toggleSingleEntry" class="text-sm text-blue-600 hover:underline mb-2">Add Single Student (Optional)</button>
                                    <div id="singleStudentFormWrapper" style="display:none;">
                                        <form id="singleStudentForm" class="bg-gray-50 rounded-lg shadow p-6">
                                            <div class="mb-6 flex gap-4">
                                                <div class="flex-1">
                                                    <label for="singleSessionSelectStudent" class="block text-sm font-medium text-gray-700 mb-1">Assign to Session:</label>
                                                    <select id="singleSessionSelectStudent" name="sessionId" required="" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <option value="">Select Session</option>
                                                    </select>
                                                </div>
                                                <div class="flex-1">
                                                    <label for="singleHteSelectStudent" class="block text-sm font-medium text-gray-700 mb-1">Assign to HTE:</label>
                                                    <select id="singleHteSelectStudent" name="hteId" required="" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <option value="">Select HTE</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="studentId" class="block text-sm font-medium text-gray-700">Student ID</label>
                                                    <input type="text" id="studentId" name="studentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Student ID">
                                                </div>
                                                <div>
                                                    <label for="name" class="block text-sm font-medium text-gray-700">First Name</label>
                                                    <input type="text" id="name" name="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter First Name">
                                                </div>
                                                <div>
                                                    <label for="surname" class="block text-sm font-medium text-gray-700">Last Name</label>
                                                    <input type="text" id="surname" name="surname" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Last Name">
                                                </div>
                                                <div>
                                                    <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
                                                    <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <option value="" disabled="" selected="">Select Gender</option>
                                                        <option value="Male">Male</option>
                                                        <option value="Female">Female</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="age" class="block text-sm font-medium text-gray-700">Age</label>
                                                    <input type="number" id="age" name="age" min="15" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Age">
                                                </div>
                                                <div>
                                                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                                    <input type="email" id="email" name="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Email">
                                                </div>
                                                <div>
                                                    <label for="contactNumber" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                                    <input type="tel" id="contactNumber" name="contactNumber" pattern="[0-9+\-\s()]{7,20}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Contact Number">
                                                </div>
                                            </div>
                                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="cc102" class="block text-sm font-medium text-gray-700">CC 102</label>
                                                    <input type="number" id="cc102" name="cc102" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="cc103" class="block text-sm font-medium text-gray-700">CC 103</label>
                                                    <input type="number" id="cc103" name="cc103" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="pf101" class="block text-sm font-medium text-gray-700">PF 101</label>
                                                    <input type="number" id="pf101" name="pf101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="cc104" class="block text-sm font-medium text-gray-700">CC 104</label>
                                                    <input type="number" id="cc104" name="cc104" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="ipt101" class="block text-sm font-medium text-gray-700">IPT 101</label>
                                                    <input type="number" id="ipt101" name="ipt101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="ipt102" class="block text-sm font-medium text-gray-700">IPT 102</label>
                                                    <input type="number" id="ipt102" name="ipt102" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="cc106" class="block text-sm font-medium text-gray-700">CC 106</label>
                                                    <input type="number" id="cc106" name="cc106" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="cc105" class="block text-sm font-medium text-gray-700">CC 105</label>
                                                    <input type="number" id="cc105" name="cc105" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="im101" class="block text-sm font-medium text-gray-700">IM 101</label>
                                                    <input type="number" id="im101" name="im101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="im102" class="block text-sm font-medium text-gray-700">IM 102</label>
                                                    <input type="number" id="im102" name="im102" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="hci101" class="block text-sm font-medium text-gray-700">HCI 101</label>
                                                    <input type="number" id="hci101" name="hci101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="hci102" class="block text-sm font-medium text-gray-700">HCI 102</label>
                                                    <input type="number" id="hci102" name="hci102" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="ws101" class="block text-sm font-medium text-gray-700">WS 101</label>
                                                    <input type="number" id="ws101" name="ws101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="net101" class="block text-sm font-medium text-gray-700">NET 101</label>
                                                    <input type="number" id="net101" name="net101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="net102" class="block text-sm font-medium text-gray-700">NET 102</label>
                                                    <input type="number" id="net102" name="net102" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="ias101" class="block text-sm font-medium text-gray-700">IAS 101</label>
                                                    <input type="number" id="ias101" name="ias101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="ias102" class="block text-sm font-medium text-gray-700">IAS 102</label>
                                                    <input type="number" id="ias102" name="ias102" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="cap101" class="block text-sm font-medium text-gray-700">CAP 101</label>
                                                    <input type="number" id="cap101" name="cap101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="cap102" class="block text-sm font-medium text-gray-700">CAP 102</label>
                                                    <input type="number" id="cap102" name="cap102" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                                <div>
                                                    <label for="sp101" class="block text-sm font-medium text-gray-700">SP 101</label>
                                                    <input type="number" id="sp101" name="sp101" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                            </div>
                                            <div class="mt-6 flex gap-4">
                                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md transition">Add Single Student</button>
                                                <button type="button" id="closeSingleStudentForm" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-md transition">Close</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Add HTE Form -->
                            <div id="addHTEFormContainer" class="form-container" style="display:none;">
                                <h3 class="text-xl font-bold mb-4">Add New HTE</h3>
                                <form id="hteForm" class="bg-white rounded-lg shadow p-6" enctype="multipart/form-data">
                                    <style>
                                        #addHTEFormContainer input[type="text"],
                                        #addHTEFormContainer input[type="email"],
                                        #addHTEFormContainer select {
                                            background-color: #f5f7fa;
                                            border: 1px solid gray;
                                            color: #222;
                                            font-size: 1.08rem;
                                            font-weight: 500;
                                            padding: 0.75rem 1rem;
                                            margin-bottom: 0.5rem;
                                            transition: border-color 0.2s, box-shadow 0.2s;
                                        }
                                        #addHTEFormContainer input[type="text"]:focus,
                                        #addHTEFormContainer input[type="email"]:focus,
                                        #addHTEFormContainer select:focus {
                                            border-color: #1d4ed8;
                                            box-shadow: 0 0 0 2px #2563eb33;
                                            background-color: #fff;
                                        }
                                    </style>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="hteName" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                            <input type="text" id="hteName" name="NAME" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter HTE Name">
                                        </div>
                                        <div>
                                            <label for="hteIndustry" class="block text-sm font-medium text-gray-700 mb-1">Industry</label>
                                            <input type="text" id="hteIndustry" name="INDUSTRY" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Industry">
                                        </div>
                                        <div>
                                            <label for="hteAddress" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                            <input type="text" id="hteAddress" name="ADDRESS" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Address">
                                        </div>
                                        <div>
                                            <label for="hteEmail" class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                                            <input type="email" id="hteEmail" name="CONTACT_EMAIL" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Contact Email">
                                        </div>
                                        <div>
                                            <label for="hteContactPerson" class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                                            <input type="text" id="hteContactPerson" name="CONTACT_PERSON" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Contact Person">
                                        </div>
                                        <div>
                                            <label for="hteContactNumber" class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                                            <input type="text" id="hteContactNumber" name="CONTACT_NUMBER" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Contact Number">
                                        </div>
                                        <div>
                                            <label for="sessionSelect" class="block text-sm font-medium text-gray-700 mb-1">Assign to Session</label>
                                            <select id="sessionSelect" name="sessionId" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select Session</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="hteLogo" class="block text-sm font-medium text-gray-700 mb-1">Company Logo <span class="text-red-500">*</span></label>
                                            <input type="file" id="hteLogo" name="LOGO" accept="image/*" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <small class="text-gray-500">Upload a logo image for the company.</small>
                                        </div>
                                    </div>
                                    <div class="mt-6 flex gap-4">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition">Add HTE</button>
                                        <button type="button" id="closeHTEForm" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-md transition">Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Delete HTE Form - Modern Design -->
                            <div id="deleteHTEFormContainer" class="form-container p-8 bg-white rounded-xl shadow-lg border border-gray-200 max-w-md mx-auto" style="display:none;">
                                <h3 class="text-2xl font-bold text-yellow-600 mb-6 flex items-center gap-3">
                                    <i class="fas fa-building"></i> Delete HTE
                                </h3>
                                <form id="deleteHTEFormSubmit" class="space-y-6">
                                    <div>
                                        <label for="deleteHteSelect" class="block text-sm font-medium text-gray-700 mb-2">Select My Assigned HTE to Delete</label>
                                        <select id="deleteHteSelect" name="hteId" required="" class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 bg-gray-50 text-gray-800">
                                            <option value="">Select HTE</option>
                                        </select>
                                    </div>
                                    <div class="flex gap-4 justify-end">
                                        <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out flex items-center gap-2">
                                            <i class="fas fa-trash"></i> Delete HTE
                                        </button>
                                        <button type="button" id="closeDeleteHTEForm" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out flex items-center gap-2">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Delete Student Form -->
                            <div id="deleteStudentFormContainer" class="form-container p-6 bg-white rounded-lg shadow-md" style="display:none;">
                                <h3 class="text-2xl font-bold text-red-600 mb-4 flex items-center gap-2">
                                    <i class="fas fa-user-minus"></i> Delete Student(s)
                                </h3>
                                <form id="deleteStudentForm" class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="deleteStudentSessionSelect" class="block text-sm font-medium text-gray-700 mb-1">Session</label>
                                            <select id="deleteStudentSessionSelect" name="sessionId" required="" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                                                <option value="">Select Session</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="deleteStudentHteSelect" class="block text-sm font-medium text-gray-700 mb-1">HTE</label>
                                            <select id="deleteStudentHteSelect" name="hteId" required="" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                                                <option value="">Select HTE</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Students</label>
                                        <div id="deleteStudentList" class="max-h-96 overflow-y-auto border border-gray-200 rounded-md p-3 bg-gray-50">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-100">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Student ID</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Surname</th>
                                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 uppercase">Select</th>
                                            </tr>
                                            </thead>
                                            <tbody id="deleteStudentTableBody" class="bg-white divide-y divide-gray-200">
                                            <!-- Student rows will be dynamically inserted here -->
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-4 mt-4">
                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-6 rounded-md shadow transition duration-150 ease-in-out flex items-center gap-2">
                                            <i class="fas fa-user-minus"></i> Delete Selected
                                        </button>
                                        <button type="button" id="closeDeleteStudentForm" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-6 rounded-md shadow transition duration-150 ease-in-out">Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Delete Session Form -->
                            <!-- Delete Session Form - Modern Design -->
                            <div id="deleteSessionFormContainer" class="form-container p-8 bg-white rounded-xl shadow-lg border border-gray-200 max-w-md mx-auto" style="display:none;">
                                <h3 class="text-2xl font-bold text-orange-600 mb-6 flex items-center gap-3">
                                    <i class="fas fa-calendar-minus"></i> Delete Session
                                </h3>
                                <form id="deleteSessionFormSubmit" class="space-y-6">
                                    <div>
                                        <label for="deleteSessionSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Session to Delete</label>
                                        <select id="deleteSessionSelect" name="sessionId" required="" class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-200 bg-gray-50 text-gray-800">
                                            <option value="">Select Session</option>
                                        </select>
                                    </div>
                                    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-lg text-orange-700 text-sm">
                                        <strong>Warning:</strong> This will delete the session and all associated students, HTEs, and attendance records.
                                    </div>
                                    <div class="flex gap-4 justify-end">
                                        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out flex items-center gap-2">
                                            <i class="fas fa-trash"></i> Delete Session
                                        </button>
                                        <button type="button" id="closeDeleteSessionForm" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out flex items-center gap-2">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- View All Students Container -->
                            <div id="allStudentsContainer" class="form-container p-6 bg-white rounded-lg shadow-md">
                                <h3 class="text-2xl font-bold text-gray-800 mb-4">All Students Under Coordinator</h3>
                                <div class="overflow-x-auto">
                                    <table id="allStudentsTable" class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Surname</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HTE Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                                            </tr>
                                        </thead>
                                        <tbody id="allStudentsTableBody" class="bg-white divide-y divide-gray-200">
                                            <!-- Student rows will be dynamically inserted here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="flex justify-end mt-4">
                                    <button id="closeAllStudents" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">Close</button>
                                </div>
                            </div>

                            <!-- View All Companies Container -->

                            <div id="allCompaniesContainer" class="form-container p-6 bg-white rounded-lg shadow-md" style="display:none;">
                                <h3 class="text-2xl font-bold text-gray-800 mb-4">My Assigned Companies (HTEs)</h3>
                                <div class="overflow-x-auto">
                                    <table id="allCompaniesTable" class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Company Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Industry</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Address</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact Person</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact Number</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="allCompaniesTableBody" class="bg-white divide-y divide-gray-200">
                                            <!-- Company rows will be dynamically inserted here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="flex justify-end mt-4">
                                    <button id="closeAllCompanies" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">Close</button>
                                </div>
                            </div>
                                <!-- Update Company Logo Modal -->
                                <div id="updateCompanyLogoModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">
                                    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full relative">
                                        <button class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl font-bold" id="closeUpdateLogoModal">×</button>
                                        <h3 class="text-2xl font-bold text-blue-700 mb-6 text-center">Update Company Logo</h3>
                                        <form id="updateCompanyLogoForm" enctype="multipart/form-data">
                                            <input type="hidden" id="updateLogoHteId" name="hteId">
                                            <div class="mb-4">
                                                <label for="updateLogoFile" class="block text-sm font-medium text-gray-700 mb-1">Select New Logo</label>
                                                <input type="file" id="updateLogoFile" name="LOGO" accept="image/*" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <small class="text-gray-500">Upload a new logo image for the company.</small>
                                            </div>
                                            <div class="mb-4">
                                                <img id="updateLogoPreview" src="#" alt="Logo Preview" class="hidden w-24 h-24 rounded-full object-cover border mx-auto">
                                            </div>
                                            <div class="flex gap-4 justify-end">
                                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow transition">Save Logo</button>
                                                <button type="button" id="cancelUpdateLogo" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Control Tab Content -->

            <!-- Post-Analysis Tab Content -->
            <div id="postAnalysisContent" class="tab-content hidden">
                    <div class="flex flex-col lg:flex-row w-full min-h-[500px]">
                        <!-- Left Column: Student List/Search -->
                        <div class="w-full lg:w-1/4 lg:min-w-[220px] lg:max-w-xs bg-white lg:border-r border-gray-200 p-3 md:p-6 flex flex-col">
                            <div class="mb-4 md:mb-6">
                                <input type="text" id="postAnalysisStudentSearch" placeholder="Search student" class="w-full px-3 md:px-4 py-2 text-sm md:text-base rounded-lg border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-200 text-gray-900 font-medium shadow-sm transition">
                            </div>
                            <div id="postAnalysisStudentListPanel" class="flex-1 overflow-y-auto postanalysis-student-list max-h-64 lg:max-h-none">
                                <!-- Student items will be dynamically rendered here -->
                            </div>
                            <style>
                            #postAnalysisStudentListPanel {
                                margin-top: 0.5rem;
                            }
                            .postanalysis-student-item {
                                display: block;
                                padding: 0.65rem 1rem;
                                margin-bottom: 0.25rem;
                                border-radius: 8px;
                                font-size: 1.08rem;
                                font-weight: 500;
                                color: #222;
                                cursor: pointer;
                                transition: background 0.18s, color 0.18s;
                            }
                            .postanalysis-student-item:hover {
                                background: #f3f4f6;
                                color: #2563eb;
                            }
                            .postanalysis-student-item.selected {
                                background: #2563eb;
                                color: #fff;
                            }
                            </style>
                        </div>
                        <!-- Right Column: Analysis Display -->
                        <div class="w-full lg:w-3/4 p-4 md:p-6 lg:p-10 bg-gray-50 flex flex-col">
                            <div id="postAnalysisEvalPanel">
                                <div class="mb-4 md:mb-8">
                                    <h2 class="text-xl md:text-2xl lg:text-3xl font-extrabold text-blue-800 tracking-tight mb-2">Post-Analysis</h2>
                                    <p class="text-sm md:text-base lg:text-lg text-gray-500 font-medium">Insights and analysis after all evaluations and predictions.</p>
                                </div>
                                <div id="postAnalysisContentArea" class="max-h-96 md:max-h-[500px] lg:max-h-[620px] overflow-y-auto pr-2"><!-- Post-analysis content will be dynamically rendered here --></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Post-Analysis Tab Content -->
        </div>
    </div>
<!-- End Main Content Wrapper -->

    <!-- Profile Modal -->
        <div id="profileModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Profile Details</h2>
                    <button class="modal-close" id="closeProfileModal">×</button>
                </div>
                <div class="modal-body hidden" id="profileModalContent">
                    <!-- Profile content will be loaded here -->
                </div>
            </div>
    </div>

    <input type="hidden" id="hiddencdrid" value="<?php echo htmlspecialchars($cdrid); ?>">
    <input type="hidden" id="hiddenSelectedHteID" value="-1">
    <input type="hidden" id="hiddenSelectedSessionId" value="1">

    <script src="js/jquery.js"></script>
    <script src="js/mainDashboard.js"></script>

    <script>
        // Clear hardcoded data and load dynamic content based on coordinator
        $(document).ready(function() {
            // Clear ALL hardcoded student lists and show loading state
            const loadingHTML = `
                <div class="text-center py-8 text-gray-500">
                    <div class="animate-pulse mb-2">
                        <i class="fas fa-users text-2xl text-blue-400"></i>
                    </div>
                    <p>Loading students...</p>
                </div>
            `;
            
            // Clear hardcoded student lists in evaluation tabs
            $('#studentListPanel').html(loadingHTML);
            $('#reviewStudentListPanel').html(loadingHTML);
            $('#postStudentListPanel').html(loadingHTML);
            $('#postAnalysisStudentListPanel').html(loadingHTML);
            

            
            // Clear hardcoded evaluation questions
            $('#questionsByCategory ul').html(`
                <li class="text-center py-8 text-gray-500">
                    <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-blue-600 rounded-full" role="status" aria-label="loading">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Loading evaluation questions...</p>
                </li>
            `);
            
            console.log('Cleared hardcoded student data and evaluation questions - all lists now show loading state');
            
            // Load coordinator-specific data when tabs are activated
            // This ensures data is loaded dynamically based on the logged-in coordinator
            let dataLoaded = {
                companies: false,
                students: false,
                predictions: false,
                evaluation: false
            };
            
            // Load companies when attendance tab is shown
            $(document).on('click', '[data-tab="attendance"]', function() {
                if (!dataLoaded.companies) {
                    loadCoordinatorCompanies();
                    dataLoaded.companies = true;
                }
            });
            
            // Load evaluation students when evaluation tab is shown
            $(document).on('click', '[data-tab="evaluation"]', function() {
                if (!dataLoaded.evaluation) {
                    // Use new JavaScript system for Pre-Assessment
                    if (typeof loadPreassessmentStudentList === 'function') {
                        loadPreassessmentStudentList();
                    }
                    // Load students for Review tab using old system
                    loadReviewStudents();
                    // Keep loading evaluation questions (for the questions tab)
                    loadEvaluationQuestions();
                    dataLoaded.evaluation = true;
                }
            });
            
            // Function to load coordinator's companies
            function loadCoordinatorCompanies() {
                let cdrid = $('#hiddencdrid').val();
                let sessionid = $('#hiddenSelectedSessionId').val();
                
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {cdrid: cdrid, sessionid: sessionid, action: "getHTE"},
                    success: function(response) {
                        if (response && response.length > 0) {
                            let html = getHTEHTML(response);
                            $("#classlistarea").html(html);
                        } else {
                            $("#classlistarea").html(`
                                <div class="flex flex-col">
                                    <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
                                    <select id="company-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">No companies assigned</option>
                                    </select>
                                </div>
                            `);
                        }
                    },
                    error: function(e) {
                        console.error("Error fetching companies:", e);
                        $("#classlistarea").html(`
                            <div class="flex flex-col">
                                <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
                                <select id="company-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Error loading companies</option>
                                </select>
                            </div>
                        `);
                    }
                });
            }
            
            // Load prediction data when prediction tab is shown
            $(document).on('click', '[data-tab="prediction"]', function() {
                if (!dataLoaded.predictions) {
                    loadCoordinatorPredictions();
                    dataLoaded.predictions = true;
                }
            });
            
            // Function to load coordinator's prediction data
            function loadCoordinatorPredictions() {
                let cdrid = $('#hiddencdrid').val();
                
                $.ajax({
                    url: "ajaxhandler/predictionAjax.php",
                    type: "POST",
                    dataType: "json",
                    data: {cdrid: cdrid, action: "getPredictions"},
                    success: function(response) {
                        if (response && response.success && response.data) {
                            let tbody = '';
                            response.data.forEach(function(student, index) {
                                tbody += `
                                    <tr data-row="${index}" class="hover:bg-blue-50 transition">
                                        <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm font-medium text-gray-900">${student.name || ''}</td>
                                        <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm text-gray-700">${student.hte_name || ''}</td>
                                        <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm ${student.status === 'Rated' ? 'text-green-600' : 'text-red-600'} font-semibold">
                                            ${student.status || 'Not Rated'} <span class="ml-1">${student.status === 'Rated' ? '✔️' : '❌'}</span>
                                        </td>
                                        <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm">
                                            <span class="inline-block ${student.placement ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'} font-bold px-2 md:px-3 py-1 rounded-full text-xs">
                                                ${student.placement || 'Incomplete Data'}
                                            </span>
                                        </td>
                                        <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm">
                                            <button class="analysis-btn ${student.analysis ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 hover:bg-gray-500'} text-white px-2 md:px-4 py-1 md:py-2 rounded-lg shadow transition text-xs md:text-sm" 
                                                    data-analysis="${encodeURIComponent(JSON.stringify(student.analysis || {}))}">
                                                Analysis
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                            $('#predictionTable tbody').html(tbody);
                        } else {
                            $('#predictionTable tbody').html(`
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        No prediction data available for this coordinator.
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function(e) {
                        console.error("Error fetching prediction data:", e);
                        $('#predictionTable tbody').html(`
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-red-500">
                                    Error loading prediction data. Please try again.
                                </td>
                            </tr>
                        `);
                    }
                });
            }
            
            // Function to load students for Review tab only (Pre-Assessment now uses new JavaScript system)
            function loadReviewStudents() {
                let cdrid = $('#hiddencdrid').val();
                
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {cdrid: cdrid, action: "getAllStudentsUnderCoordinator"},
                    success: function(response) {
                        if (response && response.success && response.students && response.students.length > 0) {
                            let reviewHTML = '';
                            
                            response.students.forEach(function(student) {
                                // Generate HTML for review tab students only
                                reviewHTML += `
                                    <div class="review-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent text-gray-800" data-studentid="${student.id}">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 616 0z"></path>
                                            </svg>
                                        </span>
                                        <span class="truncate">${student.STUDENT_ID}</span>
                                    </div>
                                `;
                            });
                            
                            // Update only review list (Pre-Assessment handled by new JS system)
                            $('#reviewStudentListPanel').html(reviewHTML);
                            console.log('Loaded', response.students.length, 'students for review tab');
                        } else {
                            const noStudentsHTML = `
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-users text-2xl text-gray-400 mb-2"></i>
                                    <p>No students assigned to this coordinator.</p>
                                </div>
                            `;
                            $('#reviewStudentListPanel').html(noStudentsHTML);
                        }
                    },
                    error: function(e) {
                        console.error("Error loading review students:", e);
                        const errorHTML = `
                            <div class="text-center py-8 text-red-500">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                <p>Error loading students. Please try again.</p>
                            </div>
                        `;
                        $('#reviewStudentListPanel').html(errorHTML);
                    }
                });
            }
            
            // Function to load evaluation questions dynamically
            function loadEvaluationQuestions() {
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {action: "getEvaluationQuestions"},
                    success: function(response) {
                        if (response && response.success && response.questions && response.questions.length > 0) {
                            let questionsHTML = '';
                            response.questions.forEach(function(question) {
                                questionsHTML += `
                                    <li class="bg-white rounded-lg shadow p-4">
                                        <div class="text-gray-700 text-base font-medium" contenteditable="true" data-questionid="${question.question_id}">${question.question_text}</div>
                                    </li>
                                `;
                            });
                            
                            $('#questionsByCategory ul').html(questionsHTML);
                            console.log('Loaded', response.questions.length, 'evaluation questions');
                        } else {
                            const noQuestionsHTML = `
                                <li class="text-center py-8 text-gray-500">
                                    <i class="fas fa-question-circle text-2xl text-gray-400 mb-2"></i>
                                    <p>No evaluation questions found.</p>
                                </li>
                            `;
                            $('#questionsByCategory ul').html(noQuestionsHTML);
                        }
                    },
                    error: function(e) {
                        console.error("Error loading evaluation questions:", e);
                        const errorHTML = `
                            <li class="text-center py-8 text-red-500">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                <p>Error loading questions. Please try again.</p>
                            </li>
                        `;
                        $('#questionsByCategory ul').html(errorHTML);
                    }
                });
            }
            
            // Load initial data for default tab
            loadCoordinatorCompanies();
        });
        
    </script>

    <script>
        // Load approved weekly reports when Report tab is shown
        function loadApprovedWeeklyReports() {
            $.ajax({
                url: 'ajaxhandler/coordinatorWeeklyReportAjax.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'getWeeklyReports' },
                success: function(response) {
                    if (response.status === 'success' && response.reports && response.reports.length > 0) {
                        let html = '<div class="reports-list">';
                        response.reports.forEach(function(report) {
                            // Calculate week number
                            const weekNumber = (function(start) {
                                const d = new Date(start);
                                d.setHours(0,0,0,0);
                                d.setDate(d.getDate() + 4 - (d.getDay()||7));
                                const yearStart = new Date(d.getFullYear(),0,1);
                                return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
                            })(report.week_start);
                            html += '<div class="report-card admin-report-preview">';
                            html += '<div class="report-header">';
                            html += `<h3>${report.student_name || ''} - Week ${weekNumber}</h3>`;
                            html += '<div class="report-meta">';
                            html += `<span class="report-period">Period: ${report.week_start} to ${report.week_end}</span>`;
                            html += `<span class="approval-status approved">Approved</span>`;
                            html += '</div></div>';
                            html += '<div class="report-grid">';
                            ["monday","tuesday","wednesday","thursday","friday"].forEach(function(day) {
                                html += `<div class='day-section ${day}'>`;
                                html += `<h4>${day.charAt(0).toUpperCase() + day.slice(1)}</h4>`;
                                html += `<div class='day-content'>`;
                                html += `<div class='day-images'>`;
                                if (report.imagesPerDay && report.imagesPerDay[day]) {
                                    report.imagesPerDay[day].forEach(function(img) {
                                        html += `<img src='${img.url}' alt='${day} activity' class='activity-image'>`;
                                    });
                                }
                                html += '</div>';
                                // Show description for each day (prefer dayDescription if available)
                                let desc = "";
                                if (report[day + 'Description']) {
                                    desc = report[day + 'Description'];
                                } else if (report.contentPerDay && report.contentPerDay[day]) {
                                    desc = report.contentPerDay[day];
                                }
                                html += `<div class='day-description'><p>${desc}</p></div>`;
                                html += '</div>';
                                html += '</div>';
                            });
                            html += '</div>';
                            html += `<div class='report-footer'><div class='footer-left'><span class='updated-date'>Last Updated: ${report.updated_at || ''}</span></div></div>`;
                            html += '</div>';
                        });
                        html += '</div>';
                        $('#approvedReportsList').html(html);
                    } else {
                        $('#approvedReportsList').html('<p>No approved weekly reports found.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#approvedReportsList').html('<p>Error loading reports. Please try again.</p>');
                }
            });
        }

        // Hook into tab switching to load reports when Report tab is activated
        $(document).ready(function() {
    // Guard variable for student form submission
    let isSubmittingStudentForm = false;
            $('.sidebar-item').click(function() {
                var tabName = $(this).data('tab');
                if (tabName === 'report') {
                    loadApprovedWeeklyReports();
                }
            });
        });
        // Tab switching functionality
        function switchTab(tabName) {
            
            // Hide all tab contents by adding hidden class
            document.querySelectorAll('[id$="Content"]').forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all sidebar items
            document.querySelectorAll('[data-tab]').forEach(item => {
                item.classList.remove('bg-gray-700');
            });

            // Show selected tab content by removing hidden class
            var tabContent = document.getElementById(tabName + 'Content');
            if(tabContent) {
                tabContent.classList.remove('hidden');
            }

            // Add active class to selected sidebar item
            var tabSidebar = document.getElementById(tabName + 'Tab');
            if(tabSidebar) {
                tabSidebar.classList.add('bg-gray-700');
            }
        }

        // Control Panel JavaScript
        $(document).ready(function() {
            // Set initial tab
            switchTab('attendance');
            
            // Initialize Pre-Assessment content for the default tab
            setTimeout(function() {
                if (typeof loadPreassessmentStudentList === 'function') {
                    loadPreassessmentStudentList();
                }
                // Load students for Review tab
                loadReviewStudents();
                // Load evaluation questions
                loadEvaluationQuestions();
                
                // Ensure All Questions sub-tab is properly activated
                $('#evalQuestionsTabBtn').removeClass('text-gray-500 hover:text-gray-700 hover:bg-gray-50')
                                         .addClass('text-gray-900 bg-gray-100');
                $('#rateTabBtn, #postAssessmentTabBtn, #reviewTabBtn').removeClass('text-gray-900 bg-gray-100')
                                                                      .addClass('text-gray-500 hover:text-gray-700 hover:bg-gray-50');
                
                dataLoaded.evaluation = true;
            }, 100);
            
            // Tab click event for sidebar
            $('[data-tab]').click(function() {
                var tabName = $(this).data('tab');
                switchTab(tabName);
            });

            // Function to close all form containers
            function closeAllForms() {
                $('.form-container').slideUp();
                $('#studentForm')[0].reset();
                $('#hteForm')[0].reset();
                $('#deleteHTEFormSubmit')[0].reset();
                $('#sessionForm')[0].reset();
                $('#deleteSessionFormSubmit')[0].reset();
                $('#studentlistarea').html(''); // Hide student list
            }

            // Show Add Student Form with session and HTE loading
            $('#btnAddStudent').click(function() {
                closeAllForms();
                $('#studentlistarea').html(''); // Hide student list
                $('#studentFormContainer').slideDown();
                loadSessionOptionsForStudent();
                $('#studentForm')[0].reset();
                $('#studentForm input, #studentForm select').prop('disabled', false);
            });

            // Show Add HTE Form
            $('#btnAddHTE').click(function() {
                closeAllForms();
                loadSessionOptions();
                $('#addHTEFormContainer').slideDown();
            });

            // Show Delete HTE Form and populate HTE dropdown
            $('#btnDeleteHTE').click(function() {
                closeAllForms();
                loadHTEOptions();
                $('#deleteHTEFormContainer').slideDown();
            });

            // Show Add Session Form
            $('#btnAddSession').click(function() {
                closeAllForms();
                $('#sessionFormContainer').slideDown();
            });

            // Show Delete Session Form and populate session dropdown
            $('#btnDeleteSession').click(function() {
                closeAllForms();
                loadSessionOptionsForDelete();
                $('#deleteSessionFormContainer').slideDown();
            });

            // Close forms
            $('#closeStudentForm').click(function() {
                $('#studentFormContainer').slideUp();
                $('#studentForm')[0].reset();
            });

            $('#closeHTEForm').click(function() {
                $('#addHTEFormContainer').slideUp();
                $('#hteForm')[0].reset();
            });

            $('#closeDeleteHTEForm').click(function() {
                $('#deleteHTEFormContainer').slideUp();
            });

            // Enhanced Add Student Form Submission with CSV support
            $('#studentForm').submit(function(e) {
                e.preventDefault();
                if (isSubmittingStudentForm) {
                    console.warn('[StudentForm] Submission blocked: already submitting.');
                    return;
                }
                isSubmittingStudentForm = true;

                let formData = new FormData(this);
                let hasCsvFile = $('#csvFile').get(0).files.length > 0;

                // Helper to log FormData contents
                function logFormData(fd) {
                    let out = {};
                    for (let pair of fd.entries()) {
                        out[pair[0]] = pair[1];
                    }
                    return out;
                }

                if (hasCsvFile) {
                    // Handle CSV upload
                    $.ajax({
                        url: "ajaxhandler/uploadCSV.php",
                        type: "POST",
                        data: formData,
                        contentType: false,
                        processData: false,
                        dataType: 'json',
                        success: function(response) {
                            isSubmittingStudentForm = false;
                            if (response.success) {
                                alert(response.message || "Students added successfully from CSV!");
                                $('#studentFormContainer').slideUp();
                                $('#studentForm')[0].reset();
                            } else {
                                alert("Error: " + (response.message || "Failed to process CSV"));
                            }
                        },
                        error: function(xhr, status, error) {
                            isSubmittingStudentForm = false;
                            console.error("CSV upload error:", error);
                            console.error('[StudentForm] Rejected by uploadCSV.php:', xhr.responseText);
                            alert("Error uploading CSV file. Please check the format and try again.");
                        }
                    });
                } else {
                    // Handle single student addition
                    let sessionId = $('#sessionSelectStudent').val();
                    let hteId = $('#hteSelectStudent').val();

                    // Session and HTE are now optional
                    formData.append('action', 'addStudent');
                    if (sessionId) formData.append('sessionId', sessionId);
                    if (hteId) formData.append('hteId', hteId);

                    $.ajax({
                        url: "ajaxhandler/attendanceAJAX.php",
                        type: "POST",
                        data: formData,
                        contentType: false,
                        processData: false,
                        dataType: 'json',
                        success: function(response) {
                            isSubmittingStudentForm = false;
                            if (response.success) {
                                alert("Student added successfully!");
                                $('#studentFormContainer').slideUp();
                                $('#studentForm')[0].reset();
                            } else {
                                alert("Error adding student: " + (response.message || "Unknown error"));
                            }
                        },
                        error: function(xhr, status, error) {
                            isSubmittingStudentForm = false;
                            alert("Error adding student. Please check your input and try again.");
                        }
                    });
                }
            });

            // Load sessions for student form
            function loadSessionOptionsForStudent() {
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {action: "getSession"},
                    success: function(response) {
                        if (response && response.length > 0) {
                            let options = '<option value="">Select Session</option>';
                            response.forEach(function(session) {
                                const sessionId = session.ID;
                                const sessionName = session.YEAR + " " + session.TERM;
                                options += `<option value="${sessionId}">${sessionName}</option>`;
                            });
                            $('#sessionSelectStudent').html(options);
                        } else {
                            alert("No sessions found. Please add a session first.");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading sessions:", error);
                        alert("Error loading sessions. Please try again.");
                    }
                });
            }

            // Load HTEs based on selected session for student form
            function loadHTEOptionsForStudent(sessionId) {
                if (!sessionId) {
                    $('#hteSelectStudent').html('<option value="">Select HTE</option>');
                    return;
                }

                let cdrid = $('#hiddencdrid').val();
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {cdrid: cdrid, sessionid: sessionId, action: "getHTE"},
                    success: function(response) {
                        if (response && response.length > 0) {
                            let options = '<option value="">Select HTE</option>';
                            response.forEach(function(hte) {
                                options += `<option value="${hte.HTE_ID}">${hte.NAME} (${hte.INDUSTRY})</option>`;
                            });
                            $('#hteSelectStudent').html(options);
                        } else {
                            $('#hteSelectStudent').html('<option value="">No HTEs found for this session</option>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading HTEs:", error);
                        alert("Error loading HTEs. Please try again.");
                    }
                });
            }

            // CSV file change handler
            $(document).on('change', '#csvFile', function() {
                let hasFile = $(this).get(0).files.length > 0;
                // Disable all except CSV, SESSION, and HTE dropdowns
                $('#studentForm input[type="text"], #studentForm input[type="number"], #studentForm input[type="email"], #studentForm input[type="tel"], #studentForm select')
                    .not('#csvFile, #sessionSelectStudent, #hteSelectStudent')
                    .prop('disabled', hasFile);
                // Always keep SESSION and HTE enabled
                $('#sessionSelectStudent, #hteSelectStudent').prop('disabled', false);
            });

            // Session change handler for student form
            $(document).on('change', '#sessionSelectStudent', function() {
                let sessionId = $(this).val();
                loadHTEOptionsForStudent(sessionId);
            });

            // Add HTE Form Submission
            $('#hteForm').submit(function(e) {
                e.preventDefault();
                let formData = $(this).serialize();

                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: formData + "&action=addHTEControl",
                        success: function(response) {
                            if (response.success) {
                                alert("HTE added successfully!");
                                $('#addHTEFormContainer').slideUp();
                                $('#hteForm')[0].reset();
                            } else {
                                alert("Error adding HTE: " + response.message);
                            }
                        },
                    error: function(xhr, status, error) {
                        alert("Error adding HTE. Please try again.");
                    }
                });
            });

            // Function to load coordinator-specific HTE options for delete dropdown
            function loadHTEOptions() {
                let cdrid = $("#hiddencdrid").val();
                
                if (!cdrid) {
                    $('#deleteHteSelect').html('<option value="">Coordinator ID not found</option>');
                    return;
                }

                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {action: "getHTEList", cdrid: cdrid},
                    success: function(response) {
                        if (response.success) {
                            let options = '<option value="">Select HTE</option>';
                            if (response.htes && response.htes.length > 0) {
                                response.htes.forEach(function(hte) {
                                    options += `<option value="${hte.HTE_ID}">${hte.NAME}</option>`;
                                });
                            } else {
                                options = '<option value="">No HTEs assigned to you</option>';
                            }
                            $('#deleteHteSelect').html(options);
                        } else {
                            $('#deleteHteSelect').html('<option value="">Error loading HTEs</option>');
                            alert("Error loading HTEs: " + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#deleteHteSelect').html('<option value="">Error loading HTEs</option>');
                        alert("Error loading HTEs. Please try again.");
                    }
                });
            }

            // Function to load session options for HTE form
            function loadSessionOptions() {
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {action: "getSession"},
                    success: function(response) {
                        console.log("Session response:", response);
                        if (response && response.length > 0) {
                            let options = '<option value="">Select Session</option>';
                            response.forEach(function(session) {
                                const sessionId = session.ID;
                                const sessionName = session.YEAR + " " + session.TERM;
                                options += `<option value="${sessionId}">${sessionName}</option>`;
                            });
                            $('#sessionSelect').html(options);
                        } else {
                            alert("Error loading sessions: No sessions found");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading sessions:", error);
                        alert("Error loading sessions. Please try again.");
                    }
                });
            }

            // Delete HTE Form Submission - fixed endpoint name
            $('#deleteHTEFormSubmit').submit(function(e) {
                e.preventDefault();
                let hteId = $('#deleteHteSelect').val();

                if (confirm("Are you sure you want to delete this HTE?")) {
                    $.ajax({
                        url: "ajaxhandler/attendanceAJAX.php",
                        type: "POST",
                        dataType: "json",
                        data: {hteId: hteId, action: "deleteHTE"},
                        success: function(response) {
                            if (response.success) {
                                alert("HTE deleted successfully!");
                                $('#deleteHTEFormContainer').slideUp();
                                loadHTEOptions(); // Refresh the dropdown
                            } else {
                                alert("Error deleting HTE: " + response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error("AJAX Error:", error);
                            alert("Error deleting HTE. Please try again.");
                        }
                    });
                }
            });

            // Show Add Session Form
            $('#btnAddSession').click(function() {
                $('#sessionFormContainer').slideDown();
            });

            // Close Session Form
            $('#closeSessionForm').click(function() {
                $('#sessionFormContainer').slideUp();
                $('#sessionForm')[0].reset();
            });

            // Add Session Form Submission
            $('#sessionForm').submit(function(e) {
                e.preventDefault();
                let formData = $(this).serialize();

                $.ajax({
                    url: "ajaxhandler/addSessionAjax.php",
                    type: "POST",
                    dataType: "json",
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert("Session added successfully!");
                            $('#sessionFormContainer').slideUp();
                            $('#sessionForm')[0].reset();
                            // Refresh session options in HTE form
                            loadSessionOptions();
                        } else {
                            alert("Error adding session: " + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error adding session:", error);
                        alert("Error adding session. Please try again.");
                    }
                });
            });

            // Show Delete Session Form and populate session dropdown
            $('#btnDeleteSession').click(function() {
                loadSessionOptionsForDelete();
                $('#deleteSessionFormContainer').slideDown();
            });

            // Show Delete Student Form
            $('#btnDeleteStudent').click(function() {
                closeAllForms();
                loadSessionOptionsForDeleteStudent();
                $('#deleteStudentFormContainer').slideDown();
            });

            // Close Delete Student Form
            $('#closeDeleteStudentForm').click(function() {
                $('#deleteStudentFormContainer').slideUp();
                $('#deleteStudentForm')[0].reset();
                $('#deleteStudentList').empty();
            });

            // Load sessions for Delete Student form
            function loadSessionOptionsForDeleteStudent() {
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {action: "getSession"},
                    success: function(response) {
                        if (response && response.length > 0) {
                            let options = '<option value="">Select Session</option>';
                            response.forEach(function(session) {
                                const sessionId = session.ID;
                                const sessionName = session.YEAR + " " + session.TERM;
                                options += `<option value="${sessionId}">${sessionName}</option>`;
                            });
                            $('#deleteStudentSessionSelect').html(options);
                        } else {
                            alert("No sessions found. Please add a session first.");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading sessions:", error);
                        alert("Error loading sessions. Please try again.");
                    }
                });
            }

            // Load HTEs based on selected session for Delete Student form
            $('#deleteStudentSessionSelect').change(function() {
                let sessionId = $(this).val();
                if (!sessionId) {
                    $('#deleteStudentHteSelect').html('<option value="">Select HTE</option>');
                    $('#deleteStudentList').empty();
                    return;
                }
                let cdrid = $('#hiddencdrid').val();
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {cdrid: cdrid, sessionid: sessionId, action: "getHTE"},
                    success: function(response) {
                        if (response && response.length > 0) {
                            let options = '<option value="">Select HTE</option>';
                            response.forEach(function(hte) {
                                options += `<option value="${hte.HTE_ID}">${hte.NAME} (${hte.INDUSTRY})</option>`;
                            });
                            $('#deleteStudentHteSelect').html(options);
                        } else {
                            $('#deleteStudentHteSelect').html('<option value="">No HTEs found for this session</option>');
                            $('#deleteStudentList').empty();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading HTEs:", error);
                        alert("Error loading HTEs. Please try again.");
                    }
                });
            });

            // Load students based on selected session and HTE for Delete Student form
            $('#deleteStudentHteSelect').change(function() {
                let sessionId = $('#deleteStudentSessionSelect').val();
                let hteId = $(this).val();
                if (!sessionId || !hteId) {
                    $('#deleteStudentList').empty();
                    return;
                }
                let cdrid = $('#hiddencdrid').val();
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {
                        cdrid: cdrid,
                        sessionid: sessionId,
                        hteid: hteId,
                        action: "getStudentsBySessionAndHTE"
                    },
                    success: function(response) {
                        if (response && response.length > 0) {
                            // Build the base table
                            let html = `
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Student ID</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Surname</th>
                                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 uppercase">Select</th>
                                        </tr>
                                    </thead>
                                    <tbody id="deleteStudentTableBody" class="divide-y divide-gray-100 bg-white">
                            `;

                            // Add each student row
                            response.forEach(function(student) {
                                html += `
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-2 text-sm text-gray-700">${student.STUDENT_ID}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700">${student.NAME}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700">${student.SURNAME}</td>
                                        <td class="px-4 py-2 text-center">
                                            <input type="checkbox"
                                                class="deleteStudentCheckbox accent-blue-600"
                                                value="${student.STUDENT_ID}"
                                                id="student_${student.STUDENT_ID}">
                                        </td>
                                    </tr>
                                `;
                            });

                            html += `
                                    </tbody>
                                </table>
                            `;

                            $('#deleteStudentList').html(html);
                        } else {
                            $('#deleteStudentList').html(`
                                <div class="text-center text-gray-500 py-6">
                                    No students found for the selected session and HTE.
                                </div>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading students:", error);
                        $('#deleteStudentList').html(`
                            <div class="text-center text-red-500 py-6">
                                Error loading students. Please try again.
                            </div>
                        `);
                    }
                });

            });

            // Handle Delete Student form submission
            $('#deleteStudentForm').submit(function(e) {
                e.preventDefault();
                let selectedStudents = [];
                $('.deleteStudentCheckbox:checked').each(function() {
                    selectedStudents.push($(this).val());
                });
                if (selectedStudents.length === 0) {
                    alert("Please select at least one student to delete.");
                    return;
                }
                if (!confirm("Are you sure you want to delete the selected student(s)?")) {
                    return;
                }
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {studentIds: selectedStudents, action: "deleteStudents"},
                    success: function(response) {
                        if (response.success) {
                            alert("Selected student(s) deleted successfully!");
                            $('#deleteStudentFormContainer').slideUp();
                            $('#deleteStudentForm')[0].reset();
                            $('#deleteStudentList').empty();
                        } else {
                            alert("Error deleting students: " + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error deleting students:", error);
                        alert("Error deleting students. Please try again.");
                    }
                });
            });

            // Close Delete Session Form
            $('#closeDeleteSessionForm').click(function() {
                $('#deleteSessionFormContainer').slideUp();
            });

            // Function to load session options for delete dropdown
            function loadSessionOptionsForDelete() {
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {action: "getSession"},
                    success: function(response) {
                        console.log("Session response for delete:", response);
                        if (response && response.length > 0) {
                            let options = '<option value="">Select Session</option>';
                            response.forEach(function(session) {
                                const sessionId = session.ID;
                                const sessionName = session.YEAR + " " + session.TERM;
                                options += `<option value="${sessionId}">${sessionName}</option>`;
                            });
                            $('#deleteSessionSelect').html(options);
                        } else {
                            alert("Error loading sessions: No sessions found");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading sessions for delete:", error);
                        alert("Error loading sessions. Please try again.");
                    }
                });
            }

            // Delete Session Form Submission
            $('#deleteSessionFormSubmit').submit(function(e) {
                e.preventDefault();
                let sessionId = $('#deleteSessionSelect').val();

                if (confirm("Are you sure you want to delete this session? This will delete all associated students, HTEs, and attendance records.")) {
                    $.ajax({
                        url: "ajaxhandler/deleteSessionAjax.php",
                        type: "POST",
                        dataType: "json",
                        data: {sessionId: sessionId},
                        success: function(response) {
                            if (response.success) {
                                alert("Session deleted successfully! " + response.message);
                                $('#deleteSessionFormContainer').slideUp();
                                // Refresh session options in other forms
                                loadSessionOptions();
                                loadSessionOptionsForDelete();
                            } else {
                                alert("Error deleting session: " + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Error deleting session:", error);
                            alert("Error deleting session. Please try again.");
                        }
                    });
                }
            });
        });
    </script>

    <!-- Student Stats Dashboard Modal -->
    <div id="studentDashboardModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-3/5 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-xl font-semibold text-gray-900">Student Stats</h3>
                <button id="closeStudentDashboardModal" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mt-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Present Count Card -->
                    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Present Count (This Week)</h3>
                            <div class="p-2 bg-blue-100 rounded-md">
                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                        </div>
                        <p id="weeklyPresentCount" class="text-2xl font-bold text-gray-900">0</p>
                    </div>

                    <!-- Total Hours Card -->
                    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Hours</h3>
                            <div class="p-2 bg-green-100 rounded-md">
                                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <p id="totalHours" class="text-2xl font-bold text-gray-900">0h</p>
                    </div>

                    <!-- Attendance Percentage Card -->
                    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Attendance Percentage</h3>
                            <div class="p-2 bg-purple-100 rounded-md">
                                <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                        </div>
                        <p id="attendancePercentage" class="text-2xl font-bold text-gray-900">0%</p>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                        <div class="p-2 bg-gray-100 rounded-md">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div id="dashboardRecentActivityTable" class="overflow-y-auto max-h-64">
                        <p class="text-gray-500 text-center py-4">No recent weekly report submissions found.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Coordinator Profile Modal -->
    <div id="coordinatorProfileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/5 lg:w-2/5 shadow-lg rounded-md bg-white">
            <div class="flex justify-end items-center pb-3 border-b">
                <button id="closeCoordinatorProfile" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Loading spinner -->
            <div id="coordinatorProfileLoading" class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
            </div>

            <!-- Profile content -->
            <div id="coordinatorProfileContent" class="mt-6 hidden">
                <!-- Profile Picture -->
                <div class="flex justify-center mb-6">
                    <div id="coordinatorProfilePicture" class="relative">
                        <img src="" alt="Profile" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg" style="display: none;">
                        <div class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center shadow-lg">
                            <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path>
                            </svg>
                        </div>
                        <!-- Edit Profile Picture Button -->
                        <button id="editProfilePicture" class="absolute bottom-0 right-0 bg-white rounded-full p-2 shadow-lg hover:bg-gray-50 border border-gray-200">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </button>
                        <!-- Hidden File Input -->
                        <input type="file" id="profilePictureInput" accept="image/*" class="hidden" />
                    </div>
                </div>

                <!-- Profile Picture Upload Dialog -->
                                                <div id="profilePictureUploadDialog" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
                                                    <div class="bg-white rounded-xl shadow-2xl p-8 w-full max-w-sm relative">
                                                        <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-600" id="closeUploadDialog">
                                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                        <h3 class="text-xl font-semibold text-center mb-6">Update Profile Picture</h3>
                                                        <div class="flex flex-col items-center mb-6">
                                                            <div class="relative w-28 h-28 mb-2">
                                                                <img id="picturePreview" class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-lg bg-gray-100" src="" alt="Preview" />
                                                                <button id="editProfilePicture" class="absolute bottom-2 right-2 bg-blue-600 hover:bg-blue-700 text-white rounded-full p-2 shadow-lg">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                            <button id="choosePictureBtn" class="mb-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Choose Image</button>
                                                            <input type="file" id="uploadPictureInput" accept="image/*" class="hidden" />
                                                        </div>
                                                        <div class="flex justify-center gap-4 mb-4">
                                                            <button id="cancelUpload" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                                                            <button id="saveProfilePicture" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Changes</button>
                                                        </div>
                                                        <div id="uploadProgress" class="hidden">
                                                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                                                <div class="h-full bg-blue-600 transition-all duration-300" style="width: 0%"></div>
                                                            </div>
                                                            <p class="text-sm text-gray-600 text-center mt-2">Uploading...</p>
                                                        </div>
                                                    </div>
                                                </div>

                <!-- Full Name Section -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <input type="text" id="coordinatorFirstName" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">First Name</div>
                        </div>
                        <div>
                            <input type="text" id="coordinatorLastName" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Last Name</div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Information</label>
                    <div class="space-y-4">
                        <div>
                            <input type="email" id="coordinatorEmail" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Email Address</div>
                        </div>
                        <div>
                            <input type="text" id="coordinatorContact" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Contact Number</div>
                        </div>
                    </div>
                </div>

                <!-- Department Information -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department Information</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <input type="text" id="coordinatorDepartment" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Department</div>
                        </div>
                        <div>
                            <input type="text" id="coordinatorPosition" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Position</div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 mt-8">
                    <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors" onclick="$('#closeCoordinatorProfile').click()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Profile Modal -->
    <div id="studentProfileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/5 shadow-lg rounded-md bg-white">
            <div class="flex justify-end items-center pb-3 border-b">
                <button id="closeStudentProfile" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Loading spinner -->
            <div id="profileLoading" class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
            </div>

            <!-- Profile content -->
            <div id="studentProfileContent" class="mt-6 hidden">
                <!-- Profile Picture -->
                <div class="flex justify-center mb-6">
                    <div id="profilePicture" class="relative">
                        <img src="" alt="Profile" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg" style="display: none;">
                        <div class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center shadow-lg">
                            <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Full Name Section -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <input type="text" id="profileFirstName" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">First Name</div>
                        </div>
                        <div>
                            <input type="text" id="profileLastName" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Last Name</div>
                        </div>
                    </div>
                </div>

                <!-- Student Details -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Student Details</label>
                    <div class="space-y-4">
                        <div>
                            <input type="text" id="profileStudentId" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                        </div>
                        <div>
                            <input type="email" id="profileEmail" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Information</label>
                    <input type="text" id="profileContact" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                </div>

                <!-- Additional Info -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Additional Information</label>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <input type="text" id="profileGender" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Gender</div>
                        </div>
                        <div>
                            <input type="text" id="profileAge" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Age</div>
                        </div>
                        <div>
                            <input type="text" id="profileCompany" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" disabled>
                            <div class="text-xs text-gray-500 mt-1">Company</div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 mt-8">
                    <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors" onclick="$('#closeStudentProfile').click()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

<style>
/* Additional Mobile Responsive Styles for Dynamic Content */
@media (max-width: 768px) {
    /* Modal improvements */
    .modal-content {
        width: 95vw !important;
        margin: 5vh auto !important;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    /* Table improvements */
    .table-responsive {
        font-size: 0.75rem;
    }
    
    .table-responsive th,
    .table-responsive td {
        padding: 0.375rem !important;
        font-size: 0.75rem;
    }
    
    /* Form improvements */
    .form-container {
        padding: 1rem !important;
        margin: 0.5rem !important;
    }
    
    .form-container .grid {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }
    
    /* Dynamic button improvements */
    .analysis-btn {
        font-size: 0.75rem !important;
        padding: 0.375rem 0.75rem !important;
    }
    
    /* Student list items */
    .student-item,
    .postanalysis-student-item {
        font-size: 0.875rem !important;
        padding: 0.5rem !important;
    }
    
    /* Evaluation cards */
    .evaluation-card {
        padding: 0.75rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    /* Search inputs */
    input[type="text"][placeholder*="Search"],
    input[type="text"][placeholder*="search"] {
        font-size: 0.875rem !important;
    }
    
    /* Tab navigation */
    .nav-tabs {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .nav-tabs button {
        font-size: 0.75rem !important;
        padding: 0.375rem 0.5rem !important;
        min-width: auto;
    }
    
    /* Dynamic content areas */
    #rateEvalList,
    #postEvalList,
    #reviewedEvalList,
    #postAnalysisContentArea {
        font-size: 0.875rem;
    }
    
    /* Student profile in additional info section */
    .grid-cols-3 {
        grid-template-columns: 1fr !important;
    }
    
    /* Flex layouts adjustments */
    .space-x-6 {
        gap: 1rem !important;
    }
    
    .space-x-4 {
        gap: 0.75rem !important;
    }
    
    /* Touch-friendly improvements */
    button, .btn {
        min-height: 44px;
        min-width: 44px;
    }
    
    /* Notification adjustments */
    .notification {
        font-size: 0.875rem !important;
        padding: 0.5rem !important;
    }
}

/* Landscape mobile improvements */
@media (max-width: 768px) and (orientation: landscape) {
    .sidebar {
        width: 60px !important;
    }
    
    .sidebar .nav-link span {
        display: none !important;
    }
    
    .main-content {
        margin-left: 60px !important;
    }
}

/* Very small screens */
@media (max-width: 480px) {
    .px-6 {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }
    
    .py-4 {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
    
    .text-lg {
        font-size: 1rem !important;
    }
    
    .text-xl {
        font-size: 1.125rem !important;
    }
    
    .text-2xl {
        font-size: 1.25rem !important;
    }
    
    .text-3xl {
        font-size: 1.5rem !important;
    }
}
</style>

</body>
</html>

