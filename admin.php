<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" type="image/x-icon" href="icon/favicon.ico">
    <title>InternConnect - Supervisor Login</title>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.5s ease-out; }
    </style>
</head>
<style>
    .bg-custom-top {
        background-image: url('icon/hcdcwebbg.png');
        background-size: cover;
        background-position: top center;
        background-repeat: no-repeat;
        height: 240px;
        position: relative;
    }
    .bg-overlay {
        background: linear-gradient(to bottom, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.8));
    }
    .message-text {
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }
</style>
<body class="min-h-screen bg-gray-50">
    <!-- Top Background Image -->
    <div class="bg-custom-top">
        <div class="absolute inset-0 bg-overlay"></div>
        <!-- Navigation -->
        <nav class="relative p-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <svg class="w-10 h-10" viewBox="0 0 220 160" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="gradcap-detailed-nav">
                    <title id="gradcap-detailed-nav">Detailed Graduation Cap with Tassel on Left</title>
                    <defs>
                        <linearGradient id="capTopGradient-nav" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#1e293b"/>
                            <stop offset="100%" stop-color="#0f172a"/>
                        </linearGradient>
                        <linearGradient id="capBandGradient-nav" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#334155"/>
                            <stop offset="100%" stop-color="#0f172a"/>
                        </linearGradient>
                        <linearGradient id="tasselGradient-nav" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#fbbf24"/>
                            <stop offset="100%" stop-color="#f59e0b"/>
                        </linearGradient>
                    </defs>
                    <ellipse cx="110" cy="130" rx="70" ry="10" fill="rgba(0,0,0,0.15)" />
                    <polygon points="110,20 25,60 110,100 195,60" fill="url(#capTopGradient-nav)" stroke="#0f172a" stroke-width="3" stroke-linejoin="round"/>
                    <path d="M50 85 Q110 115, 170 85 L170 100 Q110 130, 50 100 Z" fill="url(#capBandGradient-nav)" stroke="#0f172a" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M70 55 C60 70, 50 80, 40 95" stroke="url(#tasselGradient-nav)" stroke-width="5" stroke-linecap="round" fill="none"/>
                    <circle cx="70" cy="55" r="6" fill="url(#tasselGradient-nav)" stroke="#d97706" stroke-width="1"/>
                    <line x1="40" y1="95" x2="35" y2="110" stroke="url(#tasselGradient-nav)" stroke-width="3" stroke-linecap="round"/>
                    <line x1="40" y1="95" x2="45" y2="110" stroke="url(#tasselGradient-nav)" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="40" cy="95" r="5" fill="url(#tasselGradient-nav)" stroke="#d97706" stroke-width="1"/>
                    <circle cx="150" cy="50" r="4" fill="rgba(255,255,255,0.2)"/>
                </svg>
                <span class="flex items-center">
                    <span class="text-3xl font-bold text-blue-600">Intern</span><span class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-blue-400">Connect</span>
                </span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="student_login.php" class="text-gray-600 hover:text-blue-600">Student</a>
                <span class="text-gray-300">|</span>
                <a href="index.php" class="text-gray-600 hover:text-blue-600">Coordinator</a>
            </div>
        </div>
    </nav>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-8">
            <!-- Left side: Welcome Message -->
            <div class="w-full lg:w-5/12 space-y-6">
                <h1 class="text-4xl font-bold text-gray-800 message-text">Welcome to Holy Cross of Davao College</h1>
                <p class="text-xl text-gray-600 leading-relaxed">
                    Monitor and manage student internships effectively. Access comprehensive tools for attendance tracking and performance evaluation.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <span class="text-gray-600">Report Management</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                            </svg>
                        </div>
                        <span class="text-gray-600">Performance Analytics</span>
                    </div>
                </div>
            </div>

            <!-- Right side: Login Form -->
            <div class="w-full lg:w-6/12 lg:max-w-md">
                <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-8 fade-in relative" x-data="{ loading: false }">
                    <!-- Loading Overlay -->
                    <div x-show="loading" 
                        class="absolute inset-0 bg-white/80 backdrop-blur-sm rounded-2xl flex items-center justify-center z-50"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0">
                        <div class="flex flex-col items-center">
                            <svg class="animate-spin h-8 w-8 text-blue-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm text-gray-500 font-medium">Authenticating...</span>
                        </div>
                    </div>

                    <!-- Logo -->
                    <div class="text-center mb-8">
                        <div class="flex justify-center mb-4">
                            <svg class="w-20 h-20" viewBox="0 0 220 160" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="admin-login-icon">
                                <title id="admin-login-icon">Admin Portal Icon</title>
                            <defs>
                                <linearGradient id="capTopGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#1e293b"/>
                                    <stop offset="100%" stop-color="#0f172a"/>
                                </linearGradient>
                                <linearGradient id="capBandGradient" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#334155"/>
                                    <stop offset="100%" stop-color="#0f172a"/>
                                </linearGradient>
                                <linearGradient id="tasselGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#fbbf24"/>
                                    <stop offset="100%" stop-color="#f59e0b"/>
                                </linearGradient>
                            </defs>
                            <ellipse cx="110" cy="130" rx="70" ry="10" fill="rgba(0,0,0,0.15)" />
                            <polygon points="110,20 25,60 110,100 195,60" fill="url(#capTopGradient)" stroke="#0f172a" stroke-width="3" stroke-linejoin="round"/>
                            <path d="M50 85 Q110 115, 170 85 L170 100 Q110 130, 50 100 Z" fill="url(#capBandGradient)" stroke="#0f172a" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M70 55 C60 70, 50 80, 40 95" stroke="url(#tasselGradient)" stroke-width="5" stroke-linecap="round" fill="none"/>
                            <circle cx="70" cy="55" r="6" fill="url(#tasselGradient)" stroke="#d97706" stroke-width="1"/>
                            <line x1="40" y1="95" x2="35" y2="110" stroke="url(#tasselGradient)" stroke-width="3" stroke-linecap="round"/>
                            <line x1="40" y1="95" x2="45" y2="110" stroke="url(#tasselGradient)" stroke-width="3" stroke-linecap="round"/>
                            <circle cx="40" cy="95" r="5" fill="url(#tasselGradient)" stroke="#d97706" stroke-width="1"/>
                            <circle cx="150" cy="50" r="4" fill="rgba(255,255,255,0.2)"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Admin Portal</h2>
                    <p class="text-gray-600 mb-6">HTE Supervision Access</p>

                    <!-- Login Form -->
                    <form id="adminLoginForm" class="space-y-6">
                        <div>
                            <label for="txtAdminEmail" class="block text-sm font-medium text-gray-700 mb-1">HTE Email</label>
                            <input type="email" id="txtAdminEmail" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter your HTE email">
                        </div>
                        <div>
                            <label for="txtAdminPassword" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" id="txtAdminPassword" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter your password">
                        </div>
                        
                        <button type="submit" id="btnAdminLogin" 
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                            LOGIN
                        </button>
                    </form>

                    <!-- Error Message -->
                    <div id="diverror" class="hidden mt-4">
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-red-500 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                                <label id="errormessage" class="text-red-700 text-sm font-medium">ERROR GOES HERE</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 text-center text-sm">
                        <span class="text-gray-600">Login as:</span>
                        <a href="student_login.php" class="text-blue-600 hover:text-blue-700 font-medium ml-1">Student</a>
                        <span class="text-gray-400 mx-1">|</span>
                        <a href="index.php" class="text-blue-600 hover:text-blue-700 font-medium">Coordinator</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/admin.js"></script>
</body>
</html>
    </div>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/admin.js"></script>
</body>
</html>

