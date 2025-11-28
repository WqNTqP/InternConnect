<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="icon/graduation-cap-favicon.svg">
    <link rel="alternate icon" href="icon/graduation-cap-favicon.svg">
    <title>InternConnect - Student Login</title>
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

            </div>
        </nav>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-8">
            <!-- Left side: Welcome Message -->
            <div class="w-full lg:w-5/12 space-y-6">
                <h1 class="text-4xl font-bold text-gray-800 message-text">Welcome, Future Professional!</h1>
                <p class="text-xl text-gray-600 leading-relaxed">
                    Your journey towards professional growth starts here. Log in to access your OJT portal and manage your internship experience effectively.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="text-gray-600">Time Tracking</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <span class="text-gray-600">Weekly Reports</span>
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
                    <svg class="w-20 h-20" viewBox="0 0 220 160" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="gradcap-detailed">
                    <title id="gradcap-detailed">Detailed Graduation Cap with Tassel on Left</title>
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
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Student Portal</h2>
                <p class="text-gray-600">Access Your Internship</p>
            </div>

            <!-- Login Type Navigation -->
            <div class="mb-6">
                <p class="text-sm text-gray-500 text-center mb-3">Choose your login type:</p>
                <div class="flex space-x-2">
                    <a href="../" class="flex-1 bg-gray-50 hover:bg-gray-100 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium text-center border border-gray-200 transition-colors">
                        <i class="fas fa-user-tie mr-1"></i> Coordinator
                    </a>
                    <a href="supervisor" class="flex-1 bg-gray-50 hover:bg-gray-100 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium text-center border border-gray-200 transition-colors">
                        <i class="fas fa-user-shield mr-1"></i> Supervisor
                    </a>
                    <button class="flex-1 bg-blue-100 text-blue-600 px-3 py-2 rounded-lg text-sm font-medium border border-blue-200" disabled>
                        <i class="fas fa-user-graduate mr-1"></i> Student
                    </button>
                </div>
            </div>

        <!-- Form -->
        <div class="space-y-6">
            <div class="relative">
                <label for="txtEmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <div class="relative">
                    <input type="email" id="txtEmail" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-50 hover:bg-white"
                        placeholder="Enter your email">
                </div>
            </div>

            <div class="relative">
                <label for="txtStudentID" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="txtStudentID" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-50 hover:bg-white"
                        placeholder="Use Student ID initially">
                    <p class="mt-1 text-xs text-gray-500">Use your Student ID as initial password</p>
                </div>
            </div>

            <div>
                <button id="btnLogin" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 transform hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center"
                    x-bind:class="{ 'opacity-75 cursor-wait': loading }"
                    x-on:click="loading = true; setTimeout(() => loading = false, 2000)">
                    <span x-show="!loading">LOGIN</span>
                    <svg x-show="loading" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>

            <!-- Forgot Password Link -->
            <div class="text-center">
                <a href="forgot-password" class="text-sm text-blue-600 hover:text-blue-800 hover:underline transition duration-200">
                    <i class="fas fa-key mr-1"></i>Forgot your password?
                </a>
            </div>

            <div id="diverror" class="hidden">
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-red-500 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <label id="errormessage" class="text-red-700 text-sm font-medium">ERROR GOES HERE</label>
                    </div>
                </div>
            </div>
        </div>


    </div>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/student_login.js"></script>
</body>
</html>
