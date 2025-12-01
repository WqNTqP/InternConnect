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
        header("Location: ./");
        die();
    }

// Function to generate student filter options based on coordinator
function generateStudentFilterOptions($coordinatorId) {
    try {
        require_once __DIR__ . '/config/path_config.php';
        require_once PathConfig::getDatabasePath();
        $dbo = new Database();
        
        $stmt = $dbo->conn->prepare("
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
        // Use PathConfig for flexible path resolution
        require_once __DIR__ . '/config/path_config.php';
        require_once PathConfig::getDatabasePath();
        
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
        // Old code - commented out
        // $path=$_SERVER['DOCUMENT_ROOT'];
        // require_once $path."/database/database.php";
        
        // New code - using __DIR__ for relative path
        require_once __DIR__ . '/database/database.php';
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
    <?php require_once __DIR__ . '/config/path_config.php'; $baseHref = PathConfig::getBaseUrl(); ?>
    <base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES); ?>">
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
    <link rel="icon" type="image/svg+xml" href="icon/graduation-cap-favicon.svg">
    <link rel="alternate icon" href="icon/graduation-cap-favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>InternConnect - Dashboard</title>
    <?php
        // Server-side: discover coordinator manual images dynamically
        function ic_list_coordinator_manual_images() {
            $base = __DIR__ . '/icon/COORDINATOR';
            $result = [];
            if (!is_dir($base)) return $result;

            // Map of folder labels we care about (prefix allows "1. ATTENDANCE" etc.)
            $targets = [
                'ATTENDANCE' => 'Attendance',
                'EVALUATION' => 'Evaluation',
                'CONTROL' => 'Control',
                'REPORT' => 'Report',
                'PREDICTION' => 'Prediction',
                'POST ANALYSIS' => 'Post Analysis'
            ];

            // Scan subdirectories and gather images
            $dir = new DirectoryIterator($base);
            foreach ($dir as $fileinfo) {
                if ($fileinfo->isDot() || !$fileinfo->isDir()) continue;
                $folderName = $fileinfo->getFilename();
                // Normalize: strip numeric prefix "1. ", "2. " etc.
                $normalized = preg_replace('/^\s*\d+\.?\s*/', '', strtoupper($folderName));
                foreach ($targets as $key => $label) {
                    if (strtoupper($key) === $normalized) {
                        $tabPath = $fileinfo->getPathname();
                        $images = [];
                        // Collect image files (png/jpg/jpeg)
                        $tabIt = new DirectoryIterator($tabPath);
                        foreach ($tabIt as $img) {
                            if ($img->isFile()) {
                                $ext = strtolower($img->getExtension());
                                if (in_array($ext, ['png','jpg','jpeg','gif'])) {
                                    $rel = 'icon/COORDINATOR/' . $folderName . '/' . $img->getFilename();
                                    $images[] = $rel;
                                }
                            }
                        }
                        // Natural sort by name (so 1.png < 2.png ...)
                        natsort($images);
                        $result[$label] = array_values($images);
                    }
                }
            }
            return $result;
        }
        $IC_COORDINATOR_MANUAL = ic_list_coordinator_manual_images();
    ?>
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

        /* Coordinator Onboarding Modal */
        .onboard-backdrop { background: rgba(0,0,0,0.6); }
        /* Align with student modal sizing; image fills wrapper height */
        .onboard-img { height: 100%; max-height: 100%; object-fit: contain; transition: max-height 160ms ease-in-out, width 160ms ease-in-out; }
        .onboard-thumb { height: 40px; width: 60px; object-fit: cover; }
        .onboard-clickshield { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: transparent; z-index: 61; }
        /* Coordinator Help button style (match admin boxed look) */
        .c-help-btn{padding:6px 10px;border-radius:6px;background:#eef2ff;color:#1d4ed8;border:1px solid #c7d2fe;cursor:pointer;margin-right:10px}
        .c-help-btn:hover{background:#e0e7ff}
        /* Onboard footer text sizing to prevent multi-line wrapping */
        #onboardFooter label { font-size: 12px; line-height: 1.2; white-space: nowrap; }
        #onboardFooter label .fa-circle-question { font-size: 12px; }
        #onboardFooter label span { font-size: 11px; line-height: 1.2; white-space: nowrap; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Report Tab Loader -->
    <div id="reportLoader" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600"></div>
    </div>

    <div class="min-h-screen" x-data="{ 
        sidebarOpen: false, 
        isMobile: window.innerWidth < 768,
        closeSidebar(event) {
            const toggleButton = event.target.closest('button[aria-label=\'Toggle Sidebar\']');
            if (!toggleButton && this.sidebarOpen) {
                this.sidebarOpen = false;
            }
        }
    }" @resize.window="isMobile = window.innerWidth < 768">
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
                        <div class="ml-4 cursor-pointer" onclick="window.location.href='dashboard';">
                            <h2 class="text-lg md:text-xl font-semibold text-gray-800">InternConnect</h2>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <!-- Help: open onboarding/manual anytime -->
                        <button id="openCoordinatorManual" class="c-help-btn hidden md:inline" title="Open Coordinator Manual">
                            <i class="fas fa-circle-question"></i>
                        </button>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                                <span class="text-xs md:text-sm font-medium hidden sm:inline"><?php echo htmlspecialchars($displayName); ?></span>
                                <span class="text-xs md:text-sm font-medium sm:hidden"><?php echo htmlspecialchars(strtoupper(substr($displayName, 0, 2))); ?></span>
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
             @click.outside="closeSidebar($event)"
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
                                <label class="text-xs md:text-sm font-medium text-gray-700 mb-1">TERM</label>
                                <select id="ddlclass" class="mt-1 block w-full text-xs md:text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><option value="-1">SELECT ONE</option></select>
                            </div>
                        </div>

                        <!-- Company List -->
                        <div id="classlistarea" class="bg-gray-50 rounded-lg shadow-sm p-3 md:p-4">
                            <div class="flex flex-col">
                                <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
                                <select id="company-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" disabled>
                                    <option value="">Select a session first</option>
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
                        <div id="studentlistarea" class="bg-white rounded-lg shadow-md p-3 md:p-4">
                            <div class="bg-gray-50 rounded-lg shadow-sm p-8">
                                <div class="text-center text-gray-500">
                                    <i class="fas fa-calendar-alt text-4xl mb-4 text-gray-400"></i>
                                    <h3 class="text-lg font-medium text-gray-700 mb-2">No Session Selected</h3>
                                    <p class="text-sm">Please select a session first to view companies and students.</p>
                                </div>
                            </div>
                        </div>
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
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div class="space-y-2">
                                <label for="filterStudent" class="block text-sm font-medium text-gray-700">Student:</label>
                                <input type="text" id="filterStudent" list="studentFilterList" 
                                       placeholder="Type student name or select from list" 
                                       autocomplete="off" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       title="Start typing to see available students">
                                <datalist id="studentFilterList">
                                    <option value="">All Students</option>
                                </datalist>
                            </div>
                            <div class="space-y-2">
                                <label for="reportTermFilter" class="block text-sm font-medium text-gray-700">Term:</label>
                                <select id="reportTermFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="all">All Terms</option>
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
                        <!-- Loading State -->
                        <div id="reportsLoadingState" class="mt-6">
                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="relative mb-4">
                                    <div class="animate-spin rounded-full h-16 w-16 border-4 border-blue-500 border-t-transparent"></div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i class="fas fa-file-alt text-blue-500 text-lg"></i>
                                    </div>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Loading Reports</h3>
                                <p class="text-gray-500 text-sm">Please wait while we fetch the latest weekly reports...</p>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div id="reportsEmptyState" class="mt-6 hidden">
                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-file-alt text-gray-400 text-3xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">No Reports Found</h3>
                                <p class="text-gray-500 text-sm mb-4">No approved weekly reports match your current filters.</p>
                                <div class="text-xs text-gray-400">
                                    <p>• Try selecting a different date range</p>
                                    <p>• Check if the student has submitted reports</p>
                                    <p>• Ensure reports have been approved</p>
                                </div>
                            </div>
                        </div>

                        <!-- Error State -->
                        <div id="reportsErrorState" class="mt-6 hidden">
                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Error Loading Reports</h3>
                                <p class="text-gray-500 text-sm mb-4">There was a problem loading the reports. Please try again.</p>
                                <button id="retryLoadReports" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">
                                    <i class="fas fa-redo mr-2"></i>Try Again
                                </button>
                            </div>
                        </div>

                        <!-- Reports List Content -->
                        <div id="approvedReportsList" class="mt-6 hidden"></div>
                    </div>
                </div>
            </div>
            <!-- End Report Tab Content -->
        
<!-- Evaluation Tab Content -->
<div id="evaluationContent" class="bg-white rounded-lg shadow-md hidden min-h-screen">
    <div class="border-b">
        <nav class="flex flex-wrap gap-2 md:space-x-4 px-3 md:px-6 py-3 overflow-x-auto bg-gradient-to-r from-gray-50 to-blue-50 rounded-t-lg">
            <button id="evalQuestionsTabBtn"
                class="px-4 md:px-6 py-2.5 text-xs md:text-sm font-semibold rounded-lg transition-all duration-200 whitespace-nowrap">
                All Questions
            </button>
            <button id="rateTabBtn"
                class="px-4 md:px-6 py-2.5 text-xs md:text-sm font-semibold rounded-lg transition-all duration-200 whitespace-nowrap">
                Pre-Assessment
            </button>
            <button id="reviewTabBtn"
                class="px-4 md:px-6 py-2.5 text-xs md:text-sm font-semibold rounded-lg transition-all duration-200 whitespace-nowrap">
                Review
            </button>
            <button id="postAssessmentTabBtn"
                class="px-4 md:px-6 py-2.5 text-xs md:text-sm font-semibold rounded-lg transition-all duration-200 whitespace-nowrap">
                Post-Assessment
            </button>
        </nav>
    </div>

    <div class="p-3 md:p-6">
        <!-- All Questions Tab -->
        <div id="evalQuestionsTabContent" class="space-y-6 active">
            <div class="all-questions-wrapper flex flex-col md:flex-row w-full">
                <!-- Left Column -->
                <div class="all-questions-categories-section left-col w-full md:w-1/5 max-w-xs md:pr-4 order-1 mb-4 md:mb-0">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Categories</h2>
                    <div class="mb-4">
                        <label for="questionCategoryDropdown"
                            class="mr-2 text-gray-700 font-medium">Category:</label>
                        <select id="questionCategoryDropdown"
                            class="border border-gray-300 rounded-md px-3 py-2 text-base focus:outline-none focus:ring-2 focus:ring-blue-500 w-full">
                            <option value="">Loading categories...</option>
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
                <div class="all-questions-content-section right-col w-full md:w-4/5 md:pl-4 order-2">
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
            <!-- Pre-Assessment Loading State -->
            <div id="preAssessmentLoadingState" class="hidden">
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="relative mb-4">
                        <div class="animate-spin rounded-full h-16 w-16 border-4 border-green-500 border-t-transparent"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-green-500 text-lg"></i>
                        </div>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-700 mb-2">Loading Pre-Assessment</h4>
                    <p class="text-gray-500 text-sm">Please wait while we fetch student pre-assessment data...</p>
                </div>
            </div>

            <!-- Pre-Assessment Error State -->
            <div id="preAssessmentErrorState" class="hidden">
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-700 mb-2">Error Loading Pre-Assessment</h4>
                    <p class="text-gray-500 text-sm mb-4">There was a problem loading the pre-assessment data. Please try again.</p>
                    <button id="retryLoadPreAssessment" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">
                        <i class="fas fa-redo mr-2"></i>Try Again
                    </button>
                </div>
            </div>

            <!-- Pre-Assessment Content -->
            <div id="preAssessmentContent">
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
        </div>

        <!-- Post-Assessment Tab -->
        <div id="postAssessmentTabContent" class="hidden">
            <!-- Post-Assessment Loading State -->
            <div id="postAssessmentLoadingState" class="hidden">
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="relative mb-4">
                        <div class="animate-spin rounded-full h-16 w-16 border-4 border-purple-500 border-t-transparent"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-chart-bar text-purple-500 text-lg"></i>
                        </div>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-700 mb-2">Loading Post-Assessment</h4>
                    <p class="text-gray-500 text-sm">Please wait while we fetch student post-assessment data...</p>
                </div>
            </div>

            <!-- Post-Assessment Error State -->
            <div id="postAssessmentErrorState" class="hidden">
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-700 mb-2">Error Loading Post-Assessment</h4>
                    <p class="text-gray-500 text-sm mb-4">There was a problem loading the post-assessment data. Please try again.</p>
                    <button id="retryLoadPostAssessment" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">
                        <i class="fas fa-redo mr-2"></i>Try Again
                    </button>
                </div>
            </div>

            <!-- Post-Assessment Content -->
            <div id="postAssessmentContentArea">
                <!-- Content will be rendered dynamically by JavaScript -->
            </div>
        </div>

        <!-- Review Tab -->
        <div id="reviewTabContent" class="hidden">
            <div class="review-main-wrapper flex flex-col md:flex-row w-full">
                <div class="review-student-list-section left-col w-full md:w-1/5 max-w-xs md:pr-4 order-1 mb-4 md:mb-0">
                    <div class="mb-4">
                        <input type="text" id="reviewStudentSearch" placeholder="Search student"
                            class="w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div class="mb-4">
                        <select id="reviewSessionFilter" class="w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <option value="all">All Terms</option>
                        </select>
                    </div>
                    <div id="reviewStudentListPanel"
                        class="overflow-y-auto max-h-[420px] flex flex-col gap-1">
                        <!-- Review list dynamically loaded -->
                    </div>
                </div>
                <div class="review-content-section right-col w-full md:w-4/5 md:pl-4 order-2">
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
                    <style>
                        .prediction-table-scroll { position: relative; max-height: 60vh; overflow-y: auto; }
                        .prediction-table-scroll thead th { position: sticky; top: 0; z-index: 2; background-color: #eff6ff; }
                    </style>
                    <!-- Prediction Controls -->
                    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-2">Student Placement Prediction</h2>
                            <p class="text-gray-600">Use machine learning to predict student placement outcomes based on pre-assessment data.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div id="predictionSpinner" class="hidden">
                                <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                            </div>
                            <button id="runPredictionBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow-md transition duration-200 flex items-center gap-2">
                                <i class="fas fa-brain"></i>
                                Run ML Prediction
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filter Controls -->
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label for="predictionTermFilter" class="text-sm font-medium text-gray-700">Term:</label>
                            <select id="predictionTermFilter" class="px-3 py-2 text-sm rounded-md border border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="all">All Terms</option>
                            </select>
                        </div>
                        <span class="text-sm font-medium text-gray-700">Filter by Status:</span>
                        <div class="flex gap-2">
                            <button id="filterAll" class="px-4 py-2 text-sm font-medium rounded-md bg-gray-600 text-white shadow-sm transition duration-150 ease-in-out">
                                All Students
                            </button>
                            <button id="filterReady" class="px-4 py-2 text-sm font-medium rounded-md bg-gray-200 text-gray-700 hover:bg-green-100 hover:text-green-700 shadow-sm transition duration-150 ease-in-out">
                                <i class="fas fa-check-circle mr-1"></i>Ready
                            </button>
                            <button id="filterNotReady" class="px-4 py-2 text-sm font-medium rounded-md bg-gray-200 text-gray-700 hover:bg-red-100 hover:text-red-700 shadow-sm transition duration-150 ease-in-out">
                                <i class="fas fa-exclamation-circle mr-1"></i>Not Ready
                            </button>
                        </div>
                    </div>
                        <div class="mt-4 md:mt-6 overflow-x-auto">
                            <div class="prediction-table-scroll">
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
                            <button id="btnAddSession" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-purple-100 text-purple-600 hover:bg-purple-200 focus:outline-none focus:ring-2 focus:ring-purple-500" aria-label="Add Term" title="Add Term">
                                <i class="fas fa-calendar-plus text-sm"></i>
                            </button>
                            <button id="btnDeleteStudent" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-red-100 text-red-600 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500" aria-label="Delete Student" title="Delete Student">
                                <i class="fas fa-user-minus text-sm"></i>
                            </button>
                            <button id="btnDeleteHTE" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-yellow-100 text-yellow-600 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500" aria-label="Delete HTE" title="Delete HTE">
                                <i class="fas fa-building text-sm"></i>
                            </button>
                            <button id="btnDeleteSession" class="flex items-center justify-center w-12 h-12 md:w-10 md:h-10 rounded-full bg-orange-100 text-orange-600 hover:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-500" aria-label="Delete Term" title="Delete Term">
                                <i class="fas fa-calendar-minus text-sm"></i>
                            </button>
                        </div>
                    </div>

                                        <!-- Control Intro (default view) -->
                    <div id="controlIntroContainer" class="mb-6">
                        <div class="rounded-xl border border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-6">
                            <h2 class="text-2xl font-bold text-blue-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-sliders-h text-blue-600"></i>
                                Control Center
                            </h2>
                            <p class="text-gray-600 mb-4 leading-relaxed">
                                Manage core operational data for your coordination workflow. Use the action buttons above to view and administer students, companies (HTEs), sessions, and batch maintenance tasks. Data preloads silently for faster access; nothing is shown until you pick a specific action.
                            </p>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                                <div class="bg-white border border-blue-100 rounded-lg p-4 shadow-sm">
                                    <div class="font-semibold text-blue-700 mb-1 flex items-center gap-2"><i class="fas fa-users"></i> Students</div>
                                    <p class="text-gray-600">View all assigned students, filter by term, and perform batch deletion.</p>
                                </div>
                                <div class="bg-white border border-indigo-100 rounded-lg p-4 shadow-sm">
                                    <div class="font-semibold text-indigo-700 mb-1 flex items-center gap-2"><i class="fas fa-city"></i> Companies (HTE)</div>
                                    <p class="text-gray-600">Review partner HTEs, update logos and MOA information, and maintain assignments.</p>
                                </div>
                                <div class="bg-white border border-purple-100 rounded-lg p-4 shadow-sm">
                                    <div class="font-semibold text-purple-700 mb-1 flex items-center gap-2"><i class="fas fa-calendar-plus"></i> Terms</div>
                                    <p class="text-gray-600">Add academic terms and link student or HTE participation context.</p>
                                </div>
                                <div class="bg-white border border-green-100 rounded-lg p-4 shadow-sm">
                                    <div class="font-semibold text-green-700 mb-1 flex items-center gap-2"><i class="fas fa-user-plus"></i> Add Student</div>
                                    <p class="text-gray-600">Register new students with optional immediate term & HTE assignment.</p>
                                </div>
                                <div class="bg-white border border-yellow-100 rounded-lg p-4 shadow-sm">
                                    <div class="font-semibold text-yellow-700 mb-1 flex items-center gap-2"><i class="fas fa-building"></i> Add / Delete HTE</div>
                                    <p class="text-gray-600">Maintain host training establishments and clean up unused entries.</p>
                                </div>
                                <div class="bg-white border border-red-100 rounded-lg p-4 shadow-sm">
                                    <div class="font-semibold text-red-700 mb-1 flex items-center gap-2"><i class="fas fa-user-minus"></i> Batch Delete</div>
                                    <p class="text-gray-600">Safely remove multiple students while preserving data integrity.</p>
                                </div>
                            </div>
                            <div class="mt-5 flex items-start gap-3 bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                <div class="text-blue-600 mt-1"><i class="fas fa-lightbulb"></i></div>
                                <p class="text-gray-600 text-sm">Select any action button above to open its management panel. You can return here by closing all panels or reloading the tab.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="space-y-6">
                            <!-- Add Term Form -->
                                <div id="sessionFormContainer" class="form-container" style="display: none;">
                                <h3 class="text-xl font-bold mb-4">Add New Term</h3>
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
                                    <div class="grid grid-cols-1 gap-4">
                                        <div>
                                            <label for="sessionYear" class="block text-sm font-medium text-gray-700 mb-1">School Year</label>
                                            <input type="number" id="sessionYear" name="year" min="2000" max="2050" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Year (e.g., 2025)">
                                            <p class="text-xs text-gray-500 mt-1">This will be displayed as "S.Y. 2025-2026"</p>
                                        </div>
                                    </div>
                                    <div class="mt-6 flex gap-4">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition">Add Term</button>
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
                                            <label for="sessionSelectStudent" class="block text-sm font-medium text-gray-700 mb-1">Assign to Term:</label>
                                            <select id="sessionSelectStudent" name="sessionId" required="" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select Term</option>
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
                                                    <label for="singleSessionSelectStudent" class="block text-sm font-medium text-gray-700 mb-1">Assign to Term:</label>
                                                    <select id="singleSessionSelectStudent" name="sessionId" required="" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <option value="">Select Term</option>
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
                                            <label for="sessionSelect" class="block text-sm font-medium text-gray-700 mb-1">Assign to Term</label>
                                            <select id="sessionSelect" name="sessionId" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select Term</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="hteLogo" class="block text-sm font-medium text-gray-700 mb-1">Company Logo <span class="text-red-500">*</span></label>
                                            <input type="file" id="hteLogo" name="LOGO" accept="image/*" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <small class="text-gray-500">Upload a logo image for the company.</small>
                                        </div>
                                    </div>
                                    
                                    <!-- MOA Section -->
                                    <div class="mt-6 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                            </svg>
                                            Memorandum of Agreement (MOA) <span class="text-red-500">*</span>
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="md:col-span-3">
                                                <label for="moaFile" class="block text-sm font-medium text-gray-700 mb-1">MOA Document <span class="text-red-500">*</span></label>
                                                <input type="file" id="moaFile" name="MOA_FILE" accept=".pdf" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <small class="text-gray-500">Upload MOA PDF document (Max: 5MB, PDF only)</small>
                                            </div>
                                            <div>
                                                <label for="moaStartDate" class="block text-sm font-medium text-gray-700 mb-1">MOA Start Date <span class="text-red-500">*</span></label>
                                                <input type="date" id="moaStartDate" name="MOA_START_DATE" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label for="moaEndDate" class="block text-sm font-medium text-gray-700 mb-1">MOA End Date <span class="text-red-500">*</span></label>
                                                <input type="date" id="moaEndDate" name="MOA_END_DATE" required="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div class="flex items-end">
                                                <div class="w-full">
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">MOA Status</label>
                                                    <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm text-gray-600">
                                                        <span id="moaStatusPreview" class="font-medium">Will be calculated automatically</span>
                                                    </div>
                                                    <small class="text-gray-500">Status determined by MOA validity dates</small>
                                                </div>
                                            </div>
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
                                            <label for="deleteStudentSessionSelect" class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                                            <select id="deleteStudentSessionSelect" name="sessionId" required="" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                                                <option value="">Select Term</option>
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
                                        <button type="button" id="deleteSelectedStudentsBtn" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-6 rounded-md shadow transition duration-150 ease-in-out flex items-center gap-2">
                                            <i class="fas fa-user-minus"></i> Delete Selected
                                        </button>
                                        <button type="button" id="closeDeleteStudentForm" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-6 rounded-md shadow transition duration-150 ease-in-out">Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Delete Term Form -->
                            <!-- Delete Term Form - Modern Design -->
                            <div id="deleteSessionFormContainer" class="form-container p-8 bg-white rounded-xl shadow-lg border border-gray-200 max-w-md mx-auto" style="display:none;">
                                <h3 class="text-2xl font-bold text-orange-600 mb-6 flex items-center gap-3">
                                    <i class="fas fa-calendar-minus"></i> Delete Term
                                </h3>
                                <form id="deleteSessionFormSubmit" class="space-y-6">
                                    <div>
                                        <label for="deleteSessionSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Term to Delete</label>
                                        <select id="deleteSessionSelect" name="sessionId" required="" class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-200 bg-gray-50 text-gray-800">
                                            <option value="">Select Term</option>
                                        </select>
                                    </div>
                                    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-lg text-orange-700 text-sm">
                                        <strong>Warning:</strong> This will delete the term and all associated students, HTEs, and attendance records.
                                    </div>
                                    <div class="flex gap-4 justify-end">
                                        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out flex items-center gap-2">
                                            <i class="fas fa-trash"></i> Delete Term
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
                                
                                <!-- Students Loading State -->
                                <div id="studentsLoadingState" class="hidden">
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <div class="relative mb-4">
                                            <div class="animate-spin rounded-full h-16 w-16 border-4 border-blue-500 border-t-transparent"></div>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <i class="fas fa-users text-blue-500 text-lg"></i>
                                            </div>
                                        </div>
                                        <h4 class="text-lg font-semibold text-gray-700 mb-2">Loading Students</h4>
                                        <p class="text-gray-500 text-sm">Please wait while we fetch all students under your coordination...</p>
                                    </div>
                                </div>

                                <!-- Students Error State -->
                                <div id="studentsErrorState" class="hidden">
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                                        </div>
                                        <h4 class="text-lg font-semibold text-gray-700 mb-2">Error Loading Students</h4>
                                        <p class="text-gray-500 text-sm mb-4">There was a problem loading the student data. Please try again.</p>
                                        <button id="retryLoadStudents" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">
                                            <i class="fas fa-redo mr-2"></i>Try Again
                                        </button>
                                    </div>
                                </div>

                                <!-- Students Content -->
                                <div id="studentsContent">
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
                            </div>

                            <!-- View All Companies Container -->

                            <div id="allCompaniesContainer" class="form-container p-6 bg-white rounded-lg shadow-md" style="display:none;">
                                <h3 class="text-2xl font-bold text-gray-800 mb-4">My Assigned Companies (HTEs)</h3>
                                
                                <!-- Companies Loading State -->
                                <div id="companiesLoadingState" class="hidden">
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <div class="relative mb-4">
                                            <div class="animate-spin rounded-full h-16 w-16 border-4 border-indigo-500 border-t-transparent"></div>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <i class="fas fa-building text-indigo-500 text-lg"></i>
                                            </div>
                                        </div>
                                        <h4 class="text-lg font-semibold text-gray-700 mb-2">Loading Companies</h4>
                                        <p class="text-gray-500 text-sm">Please wait while we fetch your assigned companies and HTEs...</p>
                                    </div>
                                </div>

                                <!-- Companies Error State -->
                                <div id="companiesErrorState" class="hidden">
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                                        </div>
                                        <h4 class="text-lg font-semibold text-gray-700 mb-2">Error Loading Companies</h4>
                                        <p class="text-gray-500 text-sm mb-4">There was a problem loading the company data. Please try again.</p>
                                        <button id="retryLoadCompanies" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out">
                                            <i class="fas fa-redo mr-2"></i>Try Again
                                        </button>
                                    </div>
                                </div>

                                <!-- Companies Content -->
                                <div id="companiesContent">
                                    <div class="overflow-x-auto">
                                        <table id="allCompaniesTable" class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Company Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Industry</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Address</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact Person</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact Number</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">MOA Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
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

                                <!-- Edit HTE Modal -->
                                <div id="editHTEModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">
                                    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl w-full max-h-[90vh] overflow-y-auto relative">
                                        <button class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl font-bold" id="closeEditHTEModal">×</button>
                                        <h3 class="text-2xl font-bold text-blue-700 mb-6 text-center">Edit Company (HTE)</h3>
                                        <form id="editHTEForm" enctype="multipart/form-data">
                                            <input type="hidden" id="editHteId" name="hteId">
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                                <div>
                                                    <label for="editHteName" class="block text-sm font-medium text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label>
                                                    <input type="text" id="editHteName" name="NAME" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter HTE Name">
                                                </div>
                                                <div>
                                                    <label for="editHteIndustry" class="block text-sm font-medium text-gray-700 mb-1">Industry <span class="text-red-500">*</span></label>
                                                    <input type="text" id="editHteIndustry" name="INDUSTRY" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Industry">
                                                </div>
                                                <div>
                                                    <label for="editHteAddress" class="block text-sm font-medium text-gray-700 mb-1">Address <span class="text-red-500">*</span></label>
                                                    <input type="text" id="editHteAddress" name="ADDRESS" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Address">
                                                </div>
                                                <div>
                                                    <label for="editHteEmail" class="block text-sm font-medium text-gray-700 mb-1">Contact Email <span class="text-red-500">*</span></label>
                                                    <input type="email" id="editHteEmail" name="CONTACT_EMAIL" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Contact Email">
                                                </div>
                                                <div>
                                                    <label for="editHteContactPerson" class="block text-sm font-medium text-gray-700 mb-1">Contact Person <span class="text-red-500">*</span></label>
                                                    <input type="text" id="editHteContactPerson" name="CONTACT_PERSON" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Contact Person">
                                                </div>
                                                <div>
                                                    <label for="editHteContactNumber" class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                                                    <input type="text" id="editHteContactNumber" name="CONTACT_NUMBER" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter Contact Number">
                                                </div>
                                            </div>
                                            
                                            <!-- MOA Section -->
                                            <div class="mt-6 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    Memorandum of Agreement (MOA)
                                                </h4>
                                                
                                                <!-- Current MOA Status -->
                                                <div id="currentMOASection" class="mb-4 p-3 bg-blue-50 rounded-lg">
                                                    <h5 class="text-md font-medium text-blue-800 mb-2">Current MOA Status</h5>
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                        <div>
                                                            <span class="text-sm font-medium text-gray-600">Status:</span>
                                                            <div id="currentMOAStatus" class="text-sm"></div>
                                                        </div>
                                                        <div>
                                                            <span class="text-sm font-medium text-gray-600">Start Date:</span>
                                                            <div id="currentMOAStartDate" class="text-sm"></div>
                                                        </div>
                                                        <div>
                                                            <span class="text-sm font-medium text-gray-600">End Date:</span>
                                                            <div id="currentMOAEndDate" class="text-sm"></div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2" id="currentMOAFileSection" style="display:none;">
                                                        <button type="button" id="viewCurrentMOA" class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-xs">📄 View Current MOA</button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Update MOA -->
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                    <div class="md:col-span-3">
                                                        <label for="editMoaFile" class="block text-sm font-medium text-gray-700 mb-1">Update MOA Document</label>
                                                        <input type="file" id="editMoaFile" name="MOA_FILE" accept=".pdf" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <small class="text-gray-500">Upload new MOA PDF document (Max: 5MB, PDF only). Leave empty to keep current MOA.</small>
                                                    </div>
                                                    <div>
                                                        <label for="editMoaStartDate" class="block text-sm font-medium text-gray-700 mb-1">MOA Start Date <span class="text-red-500">*</span></label>
                                                        <input type="date" id="editMoaStartDate" name="MOA_START_DATE" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label for="editMoaEndDate" class="block text-sm font-medium text-gray-700 mb-1">MOA End Date <span class="text-red-500">*</span></label>
                                                        <input type="date" id="editMoaEndDate" name="MOA_END_DATE" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div class="flex items-end">
                                                        <div class="w-full">
                                                            <label class="block text-sm font-medium text-gray-700 mb-1">New MOA Status</label>
                                                            <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm text-gray-600">
                                                                <span id="editMoaStatusPreview" class="font-medium">Select dates to see status</span>
                                                            </div>
                                                            <small class="text-gray-500">Status determined by MOA validity dates</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-6 flex gap-4 justify-end">
                                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow transition">Save Changes</button>
                                                <button type="button" id="cancelEditHTE" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow">Cancel</button>
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
                            <div class="mb-3 md:mb-4">
                                <input type="text" id="postAnalysisStudentSearch" placeholder="Search student" class="w-full px-3 md:px-4 py-2 text-sm md:text-base rounded-lg border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-200 text-gray-900 font-medium shadow-sm transition">
                            </div>
                            <div class="mb-3 md:mb-4">
                                <label for="postAnalysisTermFilter" class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                                <select id="postAnalysisTermFilter" class="w-full px-3 py-2 text-sm rounded-md border border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="all">All Terms</option>
                                </select>
                            </div>
                            <div id="postAnalysisStudentListPanel" class="flex-1 overflow-y-auto postanalysis-student-list">
                                <!-- Student items will be dynamically rendered here -->
                            </div>
                            <style>
                            #postAnalysisStudentListPanel {
                                margin-top: 0.5rem;
                                max-height: 60vh; /* keep page static; list scrolls */
                                overflow-y: auto;
                            }
                            .postanalysis-student-item {
                                display: block;
                                padding: 0.5rem 0.75rem;
                                margin-bottom: 0.25rem;
                                border-radius: 8px;
                                font-size: 0.95rem; /* smaller to prevent wrap */
                                font-weight: 400; /* lighter for compactness */
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
                                <div id="postAnalysisContentArea" class="max-h-96 md:max-h-[500px] lg:max-h-[620px] overflow-y-auto pr-2">
                                    <!-- Initial Welcome Message for Post-Analysis -->
                                    <div class="flex flex-col items-center justify-center h-full min-h-[400px] text-center">
                                        <div class="bg-indigo-50 rounded-full p-8 mb-6 shadow-md">
                                            <svg class="w-16 h-16 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                            </svg>
                                        </div>
                                        <div class="max-w-md mx-auto">
                                            <h3 class="text-2xl font-bold text-indigo-700 mb-4">Welcome to Post-Analysis</h3>
                                            <p class="text-gray-600 text-base leading-relaxed mb-6">
                                                Comprehensive analysis and insights comparing pre-assessment predictions with actual OJT performance outcomes.
                                            </p>
                                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                                                <h4 class="text-blue-800 font-semibold mb-3">📊 What you'll find here:</h4>
                                                <ul class="text-blue-700 text-sm space-y-2 text-left">
                                                    <li class="flex items-start">
                                                        <span class="inline-block w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                                        <span>Post-assessment evaluation results and averages</span>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="inline-block w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                                        <span>Supervisor feedback and professional comments</span>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="inline-block w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                                        <span>Comparative analysis between predictions and outcomes</span>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="inline-block w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                                        <span>Identified strengths and areas for improvement</span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="mt-6 bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-lg p-4">
                                                <p class="text-indigo-800 text-sm font-medium">
                                                    👈 Select a student from the list to view their comprehensive post-analysis report
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Post-Analysis Tab Content -->
        </div>
    </div>
<!-- End Main Content Wrapper -->

    <!-- Coordinator Onboarding Slideshow Modal -->
    <div id="coordinatorOnboardModal" class="fixed inset-x-0 bottom-0 hidden z-[60]" style="top:56px;">
        <div class="onboard-clickshield" aria-hidden="true"></div>
        <div class="absolute inset-0 onboard-backdrop"></div>
        <div id="coordinatorOnboardPanel" class="relative mx-auto max-h-[80vh] overflow-hidden bg-white rounded-2xl shadow-2xl mt-6 p-4 md:pt-6 md:pl-6 md:pr-6 pb-10" style="max-width:1000px;width:auto;">
            <div id="onboardHeader" class="onboard-header">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg md:text-2xl font-bold text-blue-700">Coordinator Quick Guide</h3>
                    <button id="coordinatorOnboardClose" class="text-gray-500 hover:text-gray-700 text-xl font-bold">×</button>
                </div>
                <p class="text-gray-600 text-sm mb-4">Swipe or use Back/Next to navigate. These images explain the key tabs.</p>
                <!-- Tab buttons for sections -->
                <div id="onboardTabs" class="flex flex-wrap gap-2 mb-2"></div>
                <div id="onboardTabTitle" class="text-sm font-semibold text-gray-700 mb-2"></div>
            </div>

            <!-- Slideshow -->
            <div class="relative bg-gray-50 rounded-xl border p-2 md:p-4">
                <div id="onboardImgWrap" class="bg-slate-50 border border-gray-200 rounded-xl p-2">
                    <img id="onboardMainImg" src="" alt="Coordinator guide" class="w-full onboard-img rounded-lg bg-white" />
                </div>
            </div>

            <div id="onboardFooter" class="mt-4 grid grid-cols-1 sm:[grid-template-columns:2fr_auto_1fr] items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700 justify-self-start">
                    <input id="onboardDontShow" type="checkbox" class="rounded border-gray-300"> Don't show again
                    <span class="text-xs text-gray-500 inline-flex items-center gap-1">Tip: reopen this guide anytime via the Help button in the top bar. <i class="fas fa-circle-question text-blue-600"></i></span>
                </label>
                <div class="justify-self-center">
                    <div class="inline-flex items-center gap-3">
                        <button id="onboardPrev" class="px-3 py-1 rounded-md border text-gray-700 bg-white hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Previous">Back</button>
                        <span id="onboardCounter" class="text-sm font-semibold text-gray-700">0/0</span>
                        <button id="onboardNext" class="px-3 py-1 rounded-md border text-gray-700 bg-white hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed" title="Next">Next</button>
                    </div>
                </div>
                <div class="flex gap-2 justify-self-end">
                    <button id="onboardCloseBtn" class="px-3 py-2 rounded-md bg-gray-200 text-gray-800 text-sm">Close</button>
                </div>
            </div>
        </div>
    </div>

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
        // Global coordinator ID for AJAX requests
        const COORDINATOR_ID = <?php echo isset($_SESSION["current_user"]) ? $_SESSION["current_user"] : 'null'; ?>;
        // Coordinator manual discovered server-side
        const COORDINATOR_MANUAL = <?php echo json_encode($IC_COORDINATOR_MANUAL, JSON_UNESCAPED_SLASHES); ?>;
        
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
            
            // Load sessions for main attendance dropdown on page load
            if (typeof loadSeassions === 'function') {
                loadSeassions();
            }
            
            // Load coordinator-specific data when tabs are activated
            // This ensures data is loaded dynamically based on the logged-in coordinator
            // Make dataLoaded global to avoid scope issues in later scripts
            window.dataLoaded = window.dataLoaded || {
                companies: false,
                students: false,
                predictions: false,
                evaluation: false,
                control: false
            };
            
            // Load companies when attendance tab is shown
            $(document).on('click', '[data-tab="attendance"]', function() {
                if (!dataLoaded.companies) {
                    loadCoordinatorCompanies();
                    dataLoaded.companies = true;
                } else {
                    // If companies are already loaded, restore the HTE selection state
                    restoreHTESelectionState();
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
                    // DISABLED: Now handled by new JavaScript system in mainDashboard.js
                    // if (typeof loadReviewStudents === 'function') {
                    //     loadReviewStudents();
                    // }
                    // Keep loading evaluation questions (for the questions tab)
                    // Note: Categories and questions are loaded by loadQuestionCategories()
                    // Load categories for the dropdown
                    if (typeof loadQuestionCategories === 'function') {
                        loadQuestionCategories();
                    }
                    dataLoaded.evaluation = true;
                }
                
                // Ensure All Questions tab is visible and active when evaluation tab is opened
                setTimeout(function() {
                    $('#evalQuestionsTabBtn').addClass('active');
                    $('#rateTabBtn, #postAssessmentTabBtn, #reviewTabBtn').removeClass('active');
                    $('#evalQuestionsTabContent').show();
                    $('#rateTabContent, #postAssessmentTabContent, #reviewTabContent').hide();
                }, 50);
            });
            
            // Preload students silently when control tab is first opened
            $(document).on('click', '[data-tab="control"]', function() {
                if (!dataLoaded.control) {
                    $('.form-container').hide();
                    $('#controlIntroContainer').show();
                    if (typeof loadAllStudentsData === 'function') {
                        loadAllStudentsData(false); // silent preload
                    }
                    dataLoaded.control = true;
                } else {
                    restoreControlTabDefaultView();
                }
            });
            
            // Function to restore default view when returning to control tab
            function restoreControlTabDefaultView() {
                // Always restore to intro view; do not auto open students list
                $('#allStudentsContainer').hide();
                $('#allCompaniesContainer').hide();
                $('#studentFormContainer').hide();
                $('#addHTEFormContainer').hide();
                $('#sessionFormContainer').hide();
                $('#deleteStudentFormContainer').hide();
                $('#deleteHTEFormContainer').hide();
                $('#deleteSessionFormContainer').hide();
                $('#controlIntroContainer').show();
            }
            
            // Function to restore HTE selection state when returning to attendance tab
            function restoreHTESelectionState() {
                let savedSessionId = $('#hiddenSelectedSessionId').val();
                let savedHteId = $('#hiddenSelectedHteID').val();
                
                // Restore session selection
                if (savedSessionId && savedSessionId !== '-1') {
                    $('#ddlclass').val(savedSessionId);
                }
                
                // Restore HTE selection if we have both session and HTE saved
                if (savedSessionId && savedSessionId !== '-1' && savedHteId && savedHteId !== '-1') {
                    setTimeout(function() {
                        let companySelect = $('#company-select');
                        if (companySelect.length > 0) {
                            companySelect.val(savedHteId);
                            // Trigger the change event to restore the HTE details and student list
                            companySelect.trigger('change');
                        }
                    }, 100);
                }
            }
            
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
                            if (typeof getHTEHTML === 'function') {
                                let html = getHTEHTML(response);
                                $("#classlistarea").html(html);
                            } else {
                                console.error('getHTEHTML function not found');
                            }
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
            
            // Note: Prediction tab loading is handled by js/mainDashboard.js

            // Coordinator onboarding slideshow
            (function initCoordinatorOnboard(){
                const LS_KEY = 'coordinator_onboard_dont_show';
                const modal = document.getElementById('coordinatorOnboardModal');
                const panel = document.getElementById('coordinatorOnboardPanel');
                const headerWrap = document.getElementById('onboardHeader');
                const footerWrap = document.getElementById('onboardFooter');
                const img = document.getElementById('onboardMainImg');
                const imgWrap = document.getElementById('onboardImgWrap');
                const prev = document.getElementById('onboardPrev');
                const next = document.getElementById('onboardNext');
                const tabsEl = document.getElementById('onboardTabs');
                const dontShow = document.getElementById('onboardDontShow');
                const closeX = document.getElementById('coordinatorOnboardClose');
                const closeBtn = document.getElementById('onboardCloseBtn');
                const openAttendance = document.getElementById('onboardOpenAttendance');
                const openManualBtn = document.getElementById('openCoordinatorManual');
                const counterEl = document.getElementById('onboardCounter');

                // Prepare grouped slides by tab using server-provided map
                const order = ['Attendance','Evaluation','Control','Report','Prediction','Post Analysis'];
                const availableTabs = order.filter(l => (COORDINATOR_MANUAL[l] || []).length > 0);
                let currentTab = availableTabs.length ? availableTabs[0] : null;
                let slides = currentTab ? (COORDINATOR_MANUAL[currentTab] || []) : [];

                // Fallback: filter out missing files if needed (image load error handler)
                let idx = 0;
                function setSlide(i){
                    if (!slides || slides.length === 0) {
                        img.src='';
                        updateNavState();
                        updateCounter();
                        recalcOnboardMaxHeight();
                        return;
                    }
                    // Clamp (no looping)
                    idx = Math.max(0, Math.min(i, slides.length - 1));
                    img.src = slides[idx];
                    img.alt = currentTab + ' guide';
                    updateNavState();
                    updateCounter();
                    recalcOnboardMaxHeight();
                }

                function updateNavState(){
                    const atStart = idx <= 0;
                    const atEnd = !slides || idx >= slides.length - 1;
                    prev.disabled = atStart;
                    next.disabled = atEnd;
                    prev.classList.toggle('opacity-50', atStart);
                    prev.classList.toggle('cursor-not-allowed', atStart);
                    next.classList.toggle('opacity-50', atEnd);
                    next.classList.toggle('cursor-not-allowed', atEnd);
                }

                function updateCounter(){
                    if(!counterEl) return;
                    const total = slides ? slides.length : 0;
                    const current = total ? (idx + 1) : 0;
                    counterEl.textContent = `${current}/${total}`;
                }

                function renderTabs(){
                    tabsEl.innerHTML = '';
                    availableTabs.forEach(label => {
                        const btn = document.createElement('button');
                        btn.className = 'px-3 py-1 rounded-md text-sm border transition-colors';
                        btn.textContent = label;
                        if(label === currentTab){
                            btn.classList.add('bg-blue-600','text-white','border-blue-600','hover:bg-blue-700');
                        } else {
                            btn.classList.add('bg-white','text-gray-700','border-gray-300','hover:bg-gray-100');
                        }
                        btn.addEventListener('click', ()=>{
                            currentTab = label;
                            slides = COORDINATOR_MANUAL[currentTab] || [];
                            renderTabs();
                            setSlide(0);
                            recalcOnboardMaxHeight();
                        });
                        tabsEl.appendChild(btn);
                    });
                    const titleEl = document.getElementById('onboardTabTitle');
                    if (titleEl) { titleEl.textContent = currentTab ? currentTab : ''; }
                    recalcOnboardMaxHeight();
                }
                function start(){
                    if(!currentTab){ modal.classList.add('hidden'); return; }
                    renderTabs();
                    setSlide(0);
                    if(!dontShow.checked){ show(); }
                    recalcOnboardMaxHeight();
                }

                prev.addEventListener('click', ()=> setSlide(idx-1));
                next.addEventListener('click', ()=> setSlide(idx+1));
                closeX.addEventListener('click', hide);
                closeBtn.addEventListener('click', hide);
                if (openAttendance) {
                    openAttendance.addEventListener('click', ()=>{
                        hide();
                        const att = document.getElementById('attendanceTab');
                        if (att) att.click();
                    });
                }
                if(openManualBtn){
                    openManualBtn.addEventListener('click', ()=>{
                        // Reset to first available tab and first slide for a clean reopen
                        const firstTab = availableTabs.length ? availableTabs[0] : null;
                        if (firstTab) {
                            currentTab = firstTab;
                            slides = COORDINATOR_MANUAL[currentTab] || [];
                            renderTabs();
                            setSlide(0);
                        } else {
                            // No images available; clear src to avoid broken state
                            slides = [];
                            setSlide(0);
                        }
                        // Open via show() to keep consistent behavior and force recalculation
                        show();
                        // Extra guard: recalc height after opening in case fonts/layout changed
                        setTimeout(recalcOnboardMaxHeight, 50);
                    });
                }

                // Avoid infinite loops: if current image fails, show a simple placeholder and stop auto-advance
                img.addEventListener('error', ()=>{
                    img.src = 'icon/graduation-cap-favicon.svg';
                });

                function show(){ modal.classList.remove('hidden'); }
                function hide(){ 
                    modal.classList.add('hidden'); 
                    if(dontShow.checked){ 
                        localStorage.setItem(LS_KEY,'1'); 
                    } else {
                        localStorage.removeItem(LS_KEY);
                    }
                }

                // Restore checkbox state
                dontShow.checked = localStorage.getItem(LS_KEY) === '1';

                // Initial show based on discovered images
                start();

                // Keyboard navigation and accessibility
                document.addEventListener('keydown', (e)=>{
                    if(modal.classList.contains('hidden')) return;
                    if(e.key === 'ArrowLeft'){ setSlide(idx-1); }
                    else if(e.key === 'ArrowRight'){ setSlide(idx+1); }
                    else if(e.key === 'Escape'){ hide(); }
                });

                // Touch swipe support
                let touchStartX = 0;
                let touchEndX = 0;
                img.addEventListener('touchstart', (e)=>{ touchStartX = e.changedTouches[0].screenX; });
                img.addEventListener('touchend', (e)=>{
                    touchEndX = e.changedTouches[0].screenX;
                    const diff = touchEndX - touchStartX;
                    if(Math.abs(diff) > 40){
                        if(diff < 0) setSlide(idx+1); else setSlide(idx-1);
                    }
                });

                // Resize handling to avoid snapping on viewport changes
                let resizeTimer;
                function recalcOnboardMaxHeight(){
                    if (!panel || !img) return;
                    const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
                    const shield = document.querySelector('.onboard-clickshield');
                    const topOffset = shield ? shield.offsetHeight : 56;
                    // panel uses max-h 92vh relative to the visible area below header
                    const panelMax = Math.floor((vh - topOffset) * 0.80);
                    const headerH = headerWrap ? headerWrap.offsetHeight : 0;
                    const footerH = footerWrap ? footerWrap.offsetHeight : 0;
                    const paddingApprox = 48; // increased to leave extra bottom room
                    const available = Math.max(160, panelMax - headerH - footerH - paddingApprox);
                    if (imgWrap) imgWrap.style.height = available + 'px';
                    img.style.maxHeight = '100%';
                }
                window.addEventListener('resize', ()=>{
                    window.clearTimeout(resizeTimer);
                    resizeTimer = window.setTimeout(()=>{
                        recalcOnboardMaxHeight();
                    }, 120);
                });
                window.addEventListener('orientationchange', ()=>{
                    setTimeout(recalcOnboardMaxHeight, 120);
                });
            })();
            
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
                            
                    // Store all students for filtering
                    window.allReviewStudents = response.students;
                    
                    response.students.forEach(function(student) {
                        // Generate HTML for review tab students only
                        reviewHTML += `
                            <div class="review-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent text-gray-800" data-studentid="${student.id}" data-sessionname="${student.SESSION_NAME || ''}">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 616 0z"></path>
                                    </svg>
                                </span>
                                <span class="truncate">${student.STUDENT_ID}</span>
                            </div>
                        `;
                    });                            // Update only review list (Pre-Assessment handled by new JS system)
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
            
            // Cache for questions to avoid repeated AJAX calls
            let cachedQuestions = null;
            
            // Function to load categories and questions (only once)
            function loadQuestionCategories() {
                console.log("Loading question categories...");
                $.ajax({
                    url: "ajaxhandler/coordinatorEvaluationQuestionsAjax.php?action=getCategories",
                    type: "GET",
                    dataType: "json",
                    success: function(response) {
                        console.log("Categories response:", response);
                        if (response && response.success && response.categories && response.categories.length > 0) {
                            let optionsHTML = '<option value="">All Categories</option>';
                            response.categories.forEach(function(category) {
                                optionsHTML += `<option value="${category}">${category}</option>`;
                            });
                            console.log("Setting dropdown HTML:", optionsHTML);
                            // Temporarily disable change handler to prevent triggering during setup
                            $('#questionCategoryDropdown').off('change');
                            $('#questionCategoryDropdown').html(optionsHTML);
                            console.log("Dropdown HTML set. Current value:", $('#questionCategoryDropdown').html());
                            
                            // Re-enable change handler after a small delay
                            setTimeout(function() {
                                $('#questionCategoryDropdown').on('change', function() {
                                    const selectedCategory = $(this).val();
                                    console.log('Selected category:', selectedCategory || 'All');
                                    // Use cached filtering instead of AJAX
                                    filterAndDisplayQuestions(selectedCategory);
                                });
                            }, 100);
                            
                            // Load questions for all categories initially (only once)
                            setTimeout(function() {
                                loadEvaluationQuestionsByCategory('');
                            }, 200);
                        } else {
                            console.log("No categories found in response");
                            $('#questionCategoryDropdown').html('<option value="">No categories found</option>');
                        }
                    },
                    error: function(e) {
                        console.error("Error loading categories:", e);
                        $('#questionCategoryDropdown').html('<option value="">Error loading categories</option>');
                    }
                });
            }
            
            // Function to filter and display cached questions (no AJAX)
            function filterAndDisplayQuestions(selectedCategory) {
                if (!cachedQuestions || cachedQuestions.length === 0) {
                    console.log("No cached questions available, loading from server...");
                    loadEvaluationQuestionsByCategory(selectedCategory);
                    return;
                }
                
                console.log('Filtering', cachedQuestions.length, 'cached questions for category:', selectedCategory || 'All');
                
                let filteredQuestions = cachedQuestions;
                
                // Filter by category if one is selected
                if (selectedCategory && selectedCategory !== '') {
                    filteredQuestions = cachedQuestions.filter(function(question) {
                        return question.category === selectedCategory;
                    });
                }
                
                displayQuestions(filteredQuestions, selectedCategory);
            }
            
            // Function to display questions in the UI
            function displayQuestions(questions, categoryName) {
                let $targetElement = $('#questionsByCategory ul');
                
                // If ul doesn't exist, create it
                if ($targetElement.length === 0) {
                    console.log('Creating missing ul element in #questionsByCategory');
                    $('#questionsByCategory').html('<ul class="space-y-3"></ul>');
                    $targetElement = $('#questionsByCategory ul');
                }
                
                if (questions.length === 0) {
                    const noQuestionsHTML = `
                        <li class="text-center py-8 text-gray-500">
                            <i class="fas fa-question-circle text-2xl text-gray-400 mb-2"></i>
                            <p>No questions found for this category.</p>
                        </li>
                    `;
                    $targetElement.html(noQuestionsHTML);
                    console.log('No questions found for category:', categoryName || 'All');
                    return;
                }
                
                let questionsHTML = '';
                questions.forEach(function(question) {
                    questionsHTML += `
                        <li class="bg-white rounded-lg shadow p-4">
                            <div class="text-sm text-gray-500 mb-1">${question.category}</div>
                            <div class="text-gray-700 text-base font-medium" contenteditable="true" data-questionid="${question.question_id}">${question.question_text}</div>
                        </li>
                    `;
                });
                
                $targetElement.html(questionsHTML);
                console.log('Displayed', questions.length, 'questions for category:', categoryName || 'All');
            }

            // Note: Old loadEvaluationQuestions function removed - using loadEvaluationQuestionsByCategory instead
            
            // Function to load evaluation questions by category (AJAX - only called once)
            function loadEvaluationQuestionsByCategory(selectedCategory) {
                // Check if the target container exists
                if ($('#questionsByCategory').length === 0) {
                    console.log('#questionsByCategory container not found, skipping load');
                    return;
                }
                
                // If we already have cached questions, use local filtering
                if (cachedQuestions && cachedQuestions.length > 0) {
                    console.log('Using cached questions, no AJAX call needed');
                    filterAndDisplayQuestions(selectedCategory);
                    return;
                }
                
                console.log('Loading questions from server (first time)...');
                $.ajax({
                    url: "ajaxhandler/coordinatorEvaluationQuestionsAjax.php",
                    type: "GET",
                    dataType: "json",
                    success: function(response) {
                        if (response && response.success && response.questions && response.questions.length > 0) {
                            // Cache the questions for future use
                            cachedQuestions = response.questions;
                            console.log('Cached', cachedQuestions.length, 'questions for future filtering');
                            
                            // Display the questions using local filtering
                            filterAndDisplayQuestions(selectedCategory);
                        } else {
                            console.log('No questions received from server');
                            displayQuestions([], selectedCategory);
                        }
                    },
                    error: function(e) {
                        console.error("Error loading evaluation questions:", e);
                        let $ajaxErrorTarget = $('#questionsByCategory ul');
                        if ($ajaxErrorTarget.length === 0) {
                            $('#questionsByCategory').html('<ul class="space-y-3"></ul>');
                            $ajaxErrorTarget = $('#questionsByCategory ul');
                        }
                        const errorHTML = `
                            <li class="text-center py-8 text-red-500">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                <p>Error loading questions. Please try again.</p>
                            </li>
                        `;
                        $ajaxErrorTarget.html(errorHTML);
                    }
                });
            }
            
            // Note: Category dropdown change handler is now set up dynamically in loadQuestionCategories()
            
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
                            html += '<div class="report-grid-compact">';
                            ["monday","tuesday","wednesday","thursday","friday"].forEach(function(day) {
                                html += `<div class='day-section-compact ${day}'>`;
                                html += `<h4>${day.charAt(0).toUpperCase() + day.slice(1)}</h4>`;
                                html += `<div class='day-content-compact'>`;
                                html += `<div class='day-image-preview'>`;
                                if (report.imagesPerDay && report.imagesPerDay[day] && report.imagesPerDay[day].length > 0) {
                                    html += `<img src='${report.imagesPerDay[day][0].url}' alt='${day} activity' class='preview-image'>`;
                                } else {
                                    html += `<div class='no-image-placeholder'>No image</div>`;
                                }
                                html += '</div>';
                                
                                // Show truncated description
                                let desc = "";
                                if (report[day + 'Description']) {
                                    desc = report[day + 'Description'];
                                } else if (report.contentPerDay && report.contentPerDay[day]) {
                                    desc = report.contentPerDay[day];
                                }
                                let shortDesc = desc.length > 50 ? desc.substring(0, 50) + '...' : desc || 'No description';
                                html += `<div class='day-description-preview'><p>${shortDesc}</p></div>`;
                                
                                // Add View button
                                html += `<button class='btn-view-day' data-report-id='${report.report_id}' data-day='${day}' data-student='${report.student_name}' onclick='viewDayReport(${report.report_id}, "${day}", "${report.student_name}")'>
                                    <i class='fas fa-eye'></i> View
                                </button>`;
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

        // Function to view individual day report
        function viewDayReport(reportId, day, studentName) {
            // Create modal HTML
            const modalHtml = `
                <div id="dayReportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900">${studentName} - ${day.charAt(0).toUpperCase() + day.slice(1)} Report</h3>
                                <button onclick="closeDayModal()" class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                            <div id="dayReportContent" class="p-4">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                                    <p class="mt-2 text-gray-600">Loading report details...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#dayReportModal').remove();
            
            // Add modal to body
            $('body').append(modalHtml);
            
            // Load report details via AJAX
            $.ajax({
                url: 'ajaxhandler/coordinatorWeeklyReportAjax.php',
                type: 'POST',
                dataType: 'json',
                data: { 
                    action: 'getDayReport',
                    reportId: reportId,
                    day: day
                },
                success: function(response) {
                    if (response.status === 'success' && response.dayData) {
                        const dayData = response.dayData;
                        let content = '<div class="day-detail">';
                        
                        // Images section
                        if (dayData.images && dayData.images.length > 0) {
                            content += '<div class="mb-4"><h4 class="font-medium text-gray-900 mb-2">Images</h4>';
                            content += '<div class="grid grid-cols-2 md:grid-cols-3 gap-2">';
                            dayData.images.forEach(img => {
                                content += `<img src="${img.url}" alt="${day} activity" class="w-full h-32 object-cover rounded border">`;
                            });
                            content += '</div></div>';
                        }
                        
                        // Description section
                        content += '<div class="mb-4"><h4 class="font-medium text-gray-900 mb-2">Description</h4>';
                        content += `<div class="bg-gray-50 p-3 rounded border"><p class="text-gray-700">${dayData.description || 'No description provided for this day.'}</p></div>`;
                        content += '</div></div>';
                        
                        $('#dayReportContent').html(content);
                    } else {
                        $('#dayReportContent').html('<p class="text-red-600">Error loading report details.</p>');
                    }
                },
                error: function() {
                    $('#dayReportContent').html('<p class="text-red-600">Error loading report details.</p>');
                }
            });
        }

        // Function to close day modal
        function closeDayModal() {
            $('#dayReportModal').remove();
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
            
            // Trigger tab-specific initialization
            if (tabName === 'report') {
                // Trigger report tab initialization with a flag to prevent infinite loops
                setTimeout(function() {
                    // Only trigger if not already triggered by this function
                    if (!window.switchTabReportTriggered) {
                        window.switchTabReportTriggered = true;
                        $('#reportTab').trigger('click');
                        // Reset the flag after a short delay
                        setTimeout(function() {
                            window.switchTabReportTriggered = false;
                        }, 1000);
                    }
                }, 100);
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
                // DISABLED: Now handled by new JavaScript system in mainDashboard.js  
                // if (typeof loadReviewStudents === 'function') {
                //     loadReviewStudents();
                // }
                // Note: Categories already loaded in previous call
                
                // Ensure All Questions sub-tab is properly activated
                $('#evalQuestionsTabBtn').addClass('active');
                $('#rateTabBtn, #postAssessmentTabBtn, #reviewTabBtn').removeClass('active');
                
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

            // Coordinator Change Password functionality
            $(document).on('click', '#coordinatorChangePassword', function() {
                $('#coordinatorChangePasswordModal').removeClass('hidden');
            });

            // Close Change Password Modal
            $(document).on('click', '#closeCoordinatorChangePassword', function() {
                $('#coordinatorChangePasswordModal').addClass('hidden');
                $('#coordinatorChangePasswordForm')[0].reset();
                $('#changePasswordError').hide();
            });

            // Change Password Form Submission
            $(document).on('click', '#coordinatorSubmitPassword', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const currentPassword = $('#coordinatorCurrentPassword').val();
                const newPassword = $('#coordinatorNewPassword').val();
                const confirmPassword = $('#coordinatorConfirmPassword').val();
                
                // Clear previous errors
                $('#changePasswordError').hide();
                
                // Basic validation
                if (!currentPassword || !newPassword || !confirmPassword) {
                    $('#changePasswordError').text('All fields are required.').show();
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    $('#changePasswordError').text('New passwords do not match.').show();
                    return false;
                }
                
                if (newPassword.length < 6) {
                    $('#changePasswordError').text('Password must be at least 6 characters long.').show();
                    return false;
                }
                
                // Disable submit button to prevent double submission
                const submitBtn = $(this);
                const originalText = submitBtn.text();
                submitBtn.prop('disabled', true).text('Changing...');
                
                // AJAX request to change password
                $.ajax({
                    url: 'ajaxhandler/change_password.php',
                    type: 'POST',
                    data: {
                        current_password: currentPassword,
                        new_password: newPassword,
                        user_type: 'coordinator'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Password changed successfully!');
                            $('#coordinatorChangePasswordModal').addClass('hidden');
                            $('#coordinatorChangePasswordForm')[0].reset();
                            $('#changePasswordError').hide();
                        } else {
                            $('#changePasswordError').text(response.message || 'Failed to change password.').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Change password error:', error);
                        $('#changePasswordError').text('An error occurred. Please try again.').show();
                    },
                    complete: function() {
                        // Re-enable submit button
                        submitBtn.prop('disabled', false).text(originalText);
                    }
                });
                
                return false;
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
                                
                                // Invalidate student cache since new student was added
                                let cdrid = $('#hiddencdrid').val();
                                const cacheKey = `allStudents_${cdrid}`;
                                invalidateCache('students', cacheKey);
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
                            let options = '<option value="">Select Term</option>';
                            response.forEach(function(session) {
                                const sessionId = session.ID;
                                const sessionName = session.DISPLAY_NAME || ('S.Y. ' + session.YEAR + '-' + (parseInt(session.YEAR) + 1));
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

            // MOA Date validation and status preview
            function updateMOAStatus() {
                const startDate = $('#moaStartDate').val();
                const endDate = $('#moaEndDate').val();
                const statusPreview = $('#moaStatusPreview');
                
                if (startDate && endDate) {
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (end < start) {
                        statusPreview.html('<span class="text-red-600">❌ Invalid: End date must be after start date</span>');
                        return false;
                    } else if (today > end) {
                        statusPreview.html('<span class="text-red-600">🔴 Expired</span>');
                    } else if (today >= start && today <= end) {
                        statusPreview.html('<span class="text-green-600">🟢 Active</span>');
                    } else {
                        statusPreview.html('<span class="text-blue-600">⏳ Future (Will be active on start date)</span>');
                    }
                    return true;
                } else {
                    statusPreview.html('<span class="text-gray-600">Select both dates to see status</span>');
                    return startDate !== '' && endDate !== '';
                }
            }
            
            // MOA date change handlers
            $('#moaStartDate, #moaEndDate').on('change', updateMOAStatus);
            
            // Add HTE Form Submission with MOA support
            $('#hteForm').submit(function(e) {
                e.preventDefault();
                
                // Validate MOA dates first
                if (!updateMOAStatus()) {
                    alert('Please check MOA dates. End date must be after start date.');
                    return;
                }
                
                // Create FormData object to handle file uploads
                let formData = new FormData(this);
                formData.append('action', 'addHTEControl');
                
                // Disable submit button to prevent double submission
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.text();
                submitBtn.prop('disabled', true).text('Adding HTE...');

                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: formData,
                    processData: false,  // Important for file uploads
                    contentType: false,  // Important for file uploads
                    success: function(response) {
                        if (response.success) {
                            alert("HTE added successfully!");
                            $('#addHTEFormContainer').slideUp();
                            $('#hteForm')[0].reset();
                            $('#moaStatusPreview').text('Will be calculated automatically');
                            
                            // Invalidate companies cache since new HTE was added
                            let cdrid = $('#hiddencdrid').val();
                            const cacheKey = `allCompanies_${cdrid}`;
                            invalidateCache('htes', cacheKey);
                        } else {
                            alert("Error adding HTE: " + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert("Error adding HTE. Please try again.");
                        console.error('HTE submission error:', xhr.responseText);
                    },
                    complete: function() {
                        // Re-enable submit button
                        submitBtn.prop('disabled', false).text(originalText);
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
                            let options = '<option value="">Select Term</option>';
                            response.forEach(function(session) {
                                const sessionId = session.ID;
                                const sessionName = session.DISPLAY_NAME || ('S.Y. ' + session.YEAR + '-' + (parseInt(session.YEAR) + 1));
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
                                
                                // Invalidate companies cache since HTE was deleted
                                let cdrid = $('#hiddencdrid').val();
                                const cacheKey = `allCompanies_${cdrid}`;
                                invalidateCache('htes', cacheKey);
                                
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
                            // Refresh session options in all dropdowns
                            if (typeof loadSeassions === 'function') {
                                loadSeassions();
                            }
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

            // Prevent form submission on delete student form
            $('#deleteStudentForm').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Form submission prevented - use Delete Selected button instead');
                return false;
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
                            let options = '<option value="">Select Term</option>';
                            response.forEach(function(session) {
                                const sessionId = session.ID;
                                const sessionName = session.DISPLAY_NAME || ('S.Y. ' + session.YEAR + '-' + (parseInt(session.YEAR) + 1));
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
            // Handle delete selected students button click
            $(document).on('click', '#deleteSelectedStudentsBtn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                let selectedStudents = [];
                $('.deleteStudentCheckbox:checked').each(function() {
                    selectedStudents.push($(this).val());
                });
                
                if (selectedStudents.length === 0) {
                    alert("Please select at least one student to delete.");
                    return false;
                }
                
                if (!confirm("Are you sure you want to delete the selected student(s)? This action cannot be undone.")) {
                    return false;
                }
                
                // Disable button during processing
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
                
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    dataType: "json",
                    data: {studentIds: selectedStudents, action: "deleteStudents"},
                    success: function(response) {
                        console.log('Delete response:', response);
                        if (response.success) {
                            alert("Selected student(s) deleted successfully!");
                            $('#deleteStudentFormContainer').slideUp();
                            $('#deleteStudentForm')[0].reset();
                            $('#deleteStudentList').empty();
                            
                            // Invalidate student cache since students were deleted
                            let cdrid = $('#hiddencdrid').val();
                            const cacheKey = `allStudents_${cdrid}`;
                            invalidateCache('students', cacheKey);
                            
                            // Refresh the student list if visible
                            if ($('#allStudentsContainer').is(':visible')) {
                                if (typeof loadAllStudentsData === 'function') {
                                    loadAllStudentsData();
                                }
                            }
                        } else {
                            alert("Error deleting students: " + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error deleting students:", {
                            xhr: xhr,
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        alert("Error deleting students. Please check the console for details.");
                    },
                    complete: function() {
                        // Re-enable button
                        $('#deleteSelectedStudentsBtn').prop('disabled', false).html('<i class="fas fa-user-minus"></i> Delete Selected');
                    }
                });
                
                return false;
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
                            let options = '<option value="">Select Term</option>';
                            response.forEach(function(session) {
                                const sessionId = session.ID;
                                const sessionName = session.DISPLAY_NAME || ('S.Y. ' + session.YEAR + '-' + (parseInt(session.YEAR) + 1));
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
                                // Refresh session options in all forms
                                if (typeof loadSeassions === 'function') {
                                    loadSeassions();
                                }
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
                    <button id="coordinatorChangePassword" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Change Password
                    </button>
                    <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors" onclick="$('#closeCoordinatorProfile').click()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Coordinator Change Password Modal -->
    <div id="coordinatorChangePasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Change Password</h3>
                <button id="closeCoordinatorChangePassword" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="coordinatorChangePasswordForm" class="mt-6" onsubmit="return false;">
                <div class="mb-4">
                    <label for="coordinatorCurrentPassword" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                    <input type="password" id="coordinatorCurrentPassword" name="currentPassword" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="mb-4">
                    <label for="coordinatorNewPassword" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                    <input type="password" id="coordinatorNewPassword" name="newPassword" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required minlength="6">
                </div>
                
                <div class="mb-4">
                    <label for="coordinatorConfirmPassword" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" id="coordinatorConfirmPassword" name="confirmPassword" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required minlength="6">
                </div>

                <div id="changePasswordError" class="mb-4 text-red-600 text-sm hidden"></div>

                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors" onclick="$('#closeCoordinatorChangePassword').click()">
                        Cancel
                    </button>
                    <button type="button" id="coordinatorSubmitPassword" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Change Password
                    </button>
                </div>
            </form>
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
/* Pre-Assessment Evaluation Styling */
.student-eval-block {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.student-eval-block:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

.eval-question-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.eval-answer-cell {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    color: #1565c0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    font-size: 0.95rem;
    font-weight: 500;
    border: 2px solid #90caf9;
    margin-bottom: 1rem;
    word-break: break-word;
    white-space: pre-line;
}

.eval-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 1rem;
}

.eval-rating-header {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    color: white;
    font-size: 0.95rem;
    font-weight: 700;
    padding: 0.75rem;
    text-align: center;
    letter-spacing: 0.5px;
}

.eval-rating-cell {
    background: #f8f9fa;
    padding: 1rem 0.5rem;
    text-align: center;
    border-right: 1px solid #e2e8f0;
    transition: background 0.2s ease;
}

.eval-rating-cell:last-child {
    border-right: none;
}

.eval-rating-cell:hover {
    background: #e3f2fd;
}

.eval-rating-cell label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    transition: color 0.2s ease;
}

.eval-rating-cell label:hover {
    color: #1976d2;
}

.eval-rating-cell input[type="radio"] {
    width: 1.5rem;
    height: 1.5rem;
    cursor: pointer;
    accent-color: #1976d2;
    transition: transform 0.2s ease;
}

.eval-rating-cell input[type="radio"]:hover {
    transform: scale(1.1);
}

.eval-rating-cell input[type="radio"]:checked {
    transform: scale(1.2);
}

.btn-clear-table {
    background: none;
    border: none;
    color: #6c757d;
    text-decoration: underline;
    cursor: pointer;
    padding: 0.5rem 0;
    font-size: 0.875rem;
    transition: color 0.2s ease;
}

.btn-clear-table:hover {
    color: #dc3545;
}

.btn-save-all-ratings {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 0.875rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.btn-save-all-ratings::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-save-all-ratings:hover::before {
    width: 300px;
    height: 300px;
}

.btn-save-all-ratings:hover {
    background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(25, 118, 210, 0.4);
}

.btn-save-all-ratings:active {
    transform: translateY(0);
}

.btn-save-all-ratings:disabled {
    background: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.save-rating-btn {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.625rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
}

.save-rating-btn:hover {
    background: linear-gradient(135deg, #20c997 0%, #1e9e85 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
}

.save-rating-btn:active {
    transform: translateY(0);
}

.rate-status {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.rate-status.success {
    background: #d4edda;
    color: #155724;
}

.rate-status.error {
    background: #f8d7da;
    color: #721c24;
}

.preassessment-student-item {
    transition: all 0.2s ease;
}

.preassessment-student-item:hover {
    transform: translateX(4px);
}

/* Likert Scale Radio Buttons - Enhanced Styling */
.likert-radio {
    width: 1.5rem;
    height: 1.5rem;
    cursor: pointer;
    accent-color: #1976d2;
    transition: transform 0.2s ease;
}

.likert-radio:hover {
    transform: scale(1.15);
}

.likert-radio:checked {
    transform: scale(1.2);
}

/* Evaluation Container Full Height */
#evaluationContent {
    min-height: calc(100vh - 140px); /* Account for header and padding */
}

/* Ensure the content wrapper maintains proper height */
#evaluationContent .p-3,
#evaluationContent .md\\:p-6 {
    min-height: calc(100vh - 200px);
}

/* Maintain existing layout structure while adding height */
.preassessment-content-wrapper {
    min-height: calc(100vh - 260px);
}

/* Evaluation Tab Buttons Active State */
#evalQuestionsTabBtn.active,
#rateTabBtn.active,
#postAssessmentTabBtn.active,
#reviewTabBtn.active {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    color: white;
    border-color: #1565c0;
    box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
}

#evalQuestionsTabBtn:not(.active),
#rateTabBtn:not(.active),
#postAssessmentTabBtn:not(.active),
#reviewTabBtn:not(.active) {
    background: white;
    color: #495057;
    border: 2px solid #dee2e6;
}

#evalQuestionsTabBtn:not(.active):hover,
#rateTabBtn:not(.active):hover,
#postAssessmentTabBtn:not(.active):hover,
#reviewTabBtn:not(.active):hover {
    background: #e3f2fd;
    border-color: #1976d2;
    color: #1976d2;
    transform: translateY(-2px);
}

/* All Questions Tab Mobile Layout */
@media (max-width: 768px) {
    /* All Questions wrapper - stack vertically */
    .all-questions-wrapper {
        flex-direction: column !important;
    }
    
    /* Categories section - appears first on mobile */
    .all-questions-categories-section {
        width: 100% !important;
        max-width: 100% !important;
        padding-right: 0 !important;
        margin-bottom: 1.5rem !important;
        order: 1 !important;
    }
    
    /* Questions content section - appears second on mobile */
    .all-questions-content-section {
        width: 100% !important;
        padding-left: 0 !important;
        order: 2 !important;
    }
}

/* Review Tab Mobile Layout */
@media (max-width: 768px) {
    /* Review wrapper - stack vertically */
    .review-main-wrapper {
        flex-direction: column !important;
    }
    
    /* Student list section - appears first on mobile */
    .review-student-list-section {
        width: 100% !important;
        max-width: 100% !important;
        padding-right: 0 !important;
        margin-bottom: 1.5rem !important;
        order: 1 !important;
    }
    
    /* Review content section - appears second on mobile */
    .review-content-section {
        width: 100% !important;
        padding-left: 0 !important;
        order: 2 !important;
    }
    
    /* Review student list panel adjustments */
    #reviewStudentListPanel {
        max-height: 300px !important;
    }
}

/* Post-Assessment Tab Mobile Layout */
@media (max-width: 768px) {
    /* Post-Assessment wrapper - stack vertically */
    .postassessment-main-wrapper {
        flex-direction: column !important;
    }
    
    /* Student list section - appears first on mobile */
    .postassessment-student-list-section {
        width: 100% !important;
        max-width: 100% !important;
        padding-right: 0 !important;
        margin-bottom: 1.5rem !important;
        order: 1 !important;
    }
    
    /* Evaluation section - appears second on mobile */
    .postassessment-evaluation-section {
        width: 100% !important;
        padding-left: 0 !important;
        order: 2 !important;
    }
    
    /* Post-Assessment student list panel adjustments */
    #postStudentListPanel {
        max-height: 300px !important;
    }
}

/* Pre-Assessment Mobile Layout */
@media (max-width: 768px) {
    /* Main wrapper - stack vertically */
    .preassessment-main-wrapper {
        flex-direction: column !important;
    }
    
    /* Student list section - appears first on mobile */
    .preassessment-student-list-section {
        width: 100% !important;
        max-width: 100% !important;
        padding-right: 0 !important;
        margin-bottom: 1.5rem !important;
        order: 1 !important;
    }
    
    /* Content section wrapper - stack vertically */
    .preassessment-content-wrapper {
        flex-direction: column !important;
        width: 100% !important;
    }
    
    /* Academic section - appears second on mobile */
    .preassessment-academic-section {
        width: 100% !important;
        order: 2 !important;
        margin-bottom: 1.5rem !important;
    }
    
    /* Evaluation section - appears third on mobile */
    .preassessment-evaluation-section {
        width: 100% !important;
        order: 3 !important;
        padding-left: 0 !important;
    }
    
    /* Student list panel adjustments */
    #studentListPanel {
        max-height: 300px !important;
    }
    
    /* Evaluation block adjustments */
    .student-eval-block {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .eval-question-box {
        font-size: 0.9rem;
        padding: 0.75rem 1rem;
    }
    
    .eval-answer-cell {
        font-size: 0.85rem;
        padding: 0.75rem 1rem;
    }
    
    .eval-rating-cell {
        padding: 0.75rem 0.25rem;
    }
    
    .eval-rating-cell label {
        font-size: 0.75rem;
        gap: 0.25rem;
    }
    
    .eval-rating-cell input[type="radio"] {
        width: 1.25rem;
        height: 1.25rem;
    }
    
    .btn-save-all-ratings {
        padding: 0.75rem 1.5rem;
        font-size: 0.9rem;
        width: 100%;
    }
    
    .save-rating-btn {
        width: 100%;
        padding: 0.75rem 1rem;
    }
    
    .eval-table {
        font-size: 0.85rem;
    }
    
    /* Academic grades section mobile adjustments */
    .preassessment-academic-section .bg-white {
        padding: 1rem !important;
    }
    
    .preassessment-academic-section .max-h-96 {
        max-height: 250px !important;
    }
}

/* Prediction Analysis Modal Responsive Styles */
#analysisModal {
    padding: 1rem !important;
}

#analysisModal > div {
    margin: 0 auto !important;
    width: 90% !important;
    max-width: 600px !important;
    min-width: 400px !important;
}

@media (max-width: 640px) {
    #analysisModal > div {
        width: 95% !important;
        min-width: 300px !important;
        max-width: 500px !important;
        padding: 1rem !important;
        border-radius: 0.75rem !important;
    }
    
    #analysisModal h2 {
        font-size: 1.25rem !important;
        margin-bottom: 1rem !important;
    }
    
    #analysisModalContent .space-y-4 > * {
        margin-bottom: 0.75rem !important;
    }
    
    #analysisModalContent .text-base {
        font-size: 0.875rem !important;
    }
    
    #analysisModalContent .text-sm {
        font-size: 0.75rem !important;
    }
    
    #analysisModalContent .grid {
        gap: 0.5rem !important;
    }
    
    #analysisModalContent .px-3 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    
    #analysisModalContent .py-2 {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
}

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

/* Compact Report Card Styles */
.report-grid-compact {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin: 1rem 0;
}

.day-section-compact {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
}

.day-section-compact:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.day-section-compact h4 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.day-content-compact {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.day-image-preview {
    width: 100%;
    height: 60px;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #dee2e6;
}

.preview-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-size: 0.75rem;
    color: #6c757d;
    background: #f8f9fa;
}

.day-description-preview {
    min-height: 40px;
    display: flex;
    align-items: center;
}

.day-description-preview p {
    font-size: 0.75rem;
    color: #6c757d;
    line-height: 1.3;
    margin: 0;
}

.btn-view-day {
    background: #007bff;
    color: white;
    border: none;
    padding: 0.375rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

.btn-view-day:hover {
    background: #0056b3;
}

.btn-view-day i {
    font-size: 0.75rem;
}

/* Responsive adjustments for compact cards */
@media (max-width: 768px) {
    .report-grid-compact {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .report-grid-compact {
        grid-template-columns: 1fr;
    }
}
</style>

</body>
</html>

