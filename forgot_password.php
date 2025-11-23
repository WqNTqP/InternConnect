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
    <title>InternConnect - Forgot Password</title>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.5s ease-out; }
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
</head>
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
                    <a href="student_login.php" class="text-slate-700 hover:text-slate-900 font-medium">Student Login</a>
                    <a href="admin.php" class="text-slate-700 hover:text-slate-900 font-medium">Admin Login</a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 -mt-20 relative z-10">
        <div class="max-w-md mx-auto">
            <!-- Forgot Password Card -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden fade-in">
                <div class="p-8">
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
                        <h1 class="text-2xl font-bold text-gray-800 mb-2">Forgot Password?</h1>
                        <p class="text-gray-600">No worries! Enter your email address and we'll send you a link to reset your password.</p>
                    </div>

                    <!-- Success Message -->
                    <div id="successMessage" class="hidden mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <div>
                                <p class="text-green-800 font-medium">Email Sent Successfully!</p>
                                <p class="text-green-700 text-sm">Check your email for the password reset link.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div id="errorMessage" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <div>
                                <p class="text-red-800 font-medium">Error</p>
                                <p id="errorText" class="text-red-700 text-sm">Something went wrong. Please try again.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Forgot Password Form -->
                    <form id="forgotPasswordForm" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input type="email" id="email" name="email" required 
                                       class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                                       placeholder="Enter your email address">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Account Type</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="relative">
                                    <input type="radio" name="userType" value="student" class="sr-only" checked>
                                    <div class="account-type-option border-2 border-blue-500 bg-blue-50 text-blue-700 rounded-lg p-3 cursor-pointer text-center transition-colors">
                                        <i class="fas fa-user-graduate text-xl mb-1 block"></i>
                                        <span class="text-sm font-medium">Student</span>
                                        <p class="text-xs mt-1 text-blue-600">Intern Account</p>
                                    </div>
                                </label>
                                <label class="relative">
                                    <input type="radio" name="userType" value="admin" class="sr-only">
                                    <div class="account-type-option border-2 border-gray-300 bg-white text-gray-700 rounded-lg p-3 cursor-pointer text-center transition-colors hover:border-blue-300">
                                        <i class="fas fa-user-tie text-xl mb-1 block"></i>
                                        <span class="text-sm font-medium">Staff</span>
                                        <p class="text-xs mt-1 text-gray-500">Admin/Coordinator</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <button type="submit" id="submitBtn" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex items-center justify-center">
                            <span id="submitText">Send Reset Link</span>
                            <i id="submitLoader" class="fas fa-spinner fa-spin ml-2 hidden"></i>
                        </button>
                    </form>

                    <!-- Back to Login -->
                    <div class="mt-6 text-center">
                        <p class="text-gray-600">Remember your password? 
                            <a href="#" id="backToLogin" class="text-blue-600 hover:text-blue-700 font-medium">Back to Login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Pre-fill form if test parameters are provided
            const urlParams = new URLSearchParams(window.location.search);
            const testEmail = urlParams.get('test_email');
            const testType = urlParams.get('type');
            
            if (testEmail && testType) {
                $('#email').val(testEmail);
                if (testType === 'student') {
                    $('input[name="userType"][value="student"]').prop('checked', true);
                } else if (testType === 'admin') {
                    $('input[name="userType"][value="admin"]').prop('checked', true);
                }
                // Trigger change event to update UI
                $('input[name="userType"]:checked').trigger('change');
            }
            
            // Handle account type selection
            $('input[name="userType"]').change(function() {
                $('.account-type-option').removeClass('border-blue-500 bg-blue-50 text-blue-700')
                                        .addClass('border-gray-300 bg-white text-gray-700');
                $(this).parent().find('.account-type-option')
                       .removeClass('border-gray-300 bg-white text-gray-700')
                       .addClass('border-blue-500 bg-blue-50 text-blue-700');
            });

            // Handle back to login based on selected account type
            $('#backToLogin').click(function(e) {
                e.preventDefault();
                const userType = $('input[name="userType"]:checked').val();
                if (userType === 'student') {
                    window.location.href = 'student_login.php';
                } else {
                    window.location.href = 'admin.php';
                }
            });

            // Handle form submission
            $('#forgotPasswordForm').submit(function(e) {
                e.preventDefault();
                
                const email = $('#email').val().trim();
                const userType = $('input[name="userType"]:checked').val();
                
                if (!email) {
                    showError('Please enter your email address.');
                    return;
                }
                
                // Show loading state
                $('#submitBtn').prop('disabled', true);
                $('#submitText').text('Sending...');
                $('#submitLoader').removeClass('hidden');
                
                // Hide previous messages
                $('#successMessage, #errorMessage').addClass('hidden');
                
                // Send AJAX request
                $.ajax({
                    url: 'ajaxhandler/forgot_password_ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'send_reset_link',
                        email: email,
                        userType: userType
                    },
                    success: function(response) {
                        if (response.success) {
                            showSuccess();
                            $('#forgotPasswordForm')[0].reset();
                        } else {
                            showError(response.message || 'Failed to send reset link.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        showError('An error occurred. Please try again later.');
                    },
                    complete: function() {
                        // Reset button state
                        $('#submitBtn').prop('disabled', false);
                        $('#submitText').text('Send Reset Link');
                        $('#submitLoader').addClass('hidden');
                    }
                });
            });
            
            function showSuccess() {
                $('#successMessage').removeClass('hidden');
                $('#errorMessage').addClass('hidden');
            }
            
            function showError(message) {
                $('#errorText').text(message);
                $('#errorMessage').removeClass('hidden');
                $('#successMessage').addClass('hidden');
            }
        });
    </script>
</body>
</html>