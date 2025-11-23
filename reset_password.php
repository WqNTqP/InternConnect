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
    <title>InternConnect - Reset Password</title>
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
        .password-strength {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 8px;
        }
        .password-strength-fill {
            height: 100%;
            transition: all 0.3s ease;
        }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
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
                    <svg class="w-10 h-10" viewBox="0 0 220 160" xmlns="http://www.w3.org/2000/svg" role="img">
                        <title>InternConnect Logo</title>
                        <defs>
                            <linearGradient id="capTopGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#1e293b"/>
                                <stop offset="100%" stop-color="#0f172a"/>
                            </linearGradient>
                        </defs>
                        <path d="M110 20 L190 60 L110 100 L30 60 Z" fill="url(#capTopGradient)" stroke="#374151" stroke-width="2"/>
                        <ellipse cx="110" cy="100" rx="80" ry="15" fill="#475569"/>
                        <rect x="105" y="100" width="10" height="40" fill="#6b7280" rx="2"/>
                        <ellipse cx="110" cy="140" rx="8" ry="4" fill="#4b5563"/>
                    </svg>
                    <span class="text-2xl font-bold text-slate-800">InternConnect</span>
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
            <!-- Loading State -->
            <div id="loadingState" class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden fade-in">
                <div class="p-8 text-center">
                    <div class="mx-auto w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900 mb-2">Verifying Reset Link...</h1>
                    <p class="text-gray-600">Please wait while we validate your password reset request.</p>
                </div>
            </div>

            <!-- Reset Password Card -->
            <div id="resetPasswordCard" class="hidden bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden fade-in">
                <div class="p-8">
                    <div class="text-center mb-8">
                        <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-lock text-2xl text-green-600"></i>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Reset Your Password</h1>
                        <p class="text-gray-600">Enter your new password below. Make sure it's strong and memorable.</p>
                    </div>

                    <!-- Success Message -->
                    <div id="successMessage" class="hidden mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <div>
                                <p class="text-green-800 font-medium">Password Reset Successfully!</p>
                                <p class="text-green-700 text-sm">You can now log in with your new password.</p>
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

                    <!-- Reset Password Form -->
                    <form id="resetPasswordForm" class="space-y-6">
                        <input type="hidden" id="resetToken" name="token" value="">
                        
                        <div>
                            <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="newPassword" name="new_password" required 
                                       class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                                       placeholder="Enter new password">
                                <button type="button" id="toggleNewPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div id="passwordStrengthFill" class="password-strength-fill"></div>
                            </div>
                            <p id="passwordStrengthText" class="mt-1 text-xs text-gray-500">Password strength will appear here</p>
                        </div>

                        <div>
                            <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="confirmPassword" name="confirm_password" required 
                                       class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                                       placeholder="Confirm new password">
                                <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                </button>
                            </div>
                            <p id="passwordMatchText" class="mt-1 text-xs text-gray-500">Passwords must match</p>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">Password Requirements:</h4>
                            <ul class="text-xs text-blue-700 space-y-1">
                                <li class="flex items-center">
                                    <i id="lengthCheck" class="fas fa-times text-red-500 mr-2 w-3"></i>
                                    At least 6 characters long
                                </li>
                                <li class="flex items-center">
                                    <i id="matchCheck" class="fas fa-times text-red-500 mr-2 w-3"></i>
                                    Both passwords must match
                                </li>
                            </ul>
                        </div>

                        <button type="submit" id="submitBtn" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex items-center justify-center disabled:bg-gray-400 disabled:cursor-not-allowed">
                            <span id="submitText">Reset Password</span>
                            <i id="submitLoader" class="fas fa-spinner fa-spin ml-2 hidden"></i>
                        </button>
                    </form>

                    <!-- Login Links -->
                    <div id="loginLinks" class="hidden mt-6 text-center space-y-2">
                        <p class="text-gray-600">Password reset successfully!</p>
                        <div class="flex justify-center space-x-4">
                            <a href="student_login.php" class="text-blue-600 hover:text-blue-700 font-medium">Student Login</a>
                            <span class="text-gray-400">•</span>
                            <a href="admin.php" class="text-blue-600 hover:text-blue-700 font-medium">Admin Login</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Card -->
            <div id="errorCard" class="hidden bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden fade-in">
                <div class="p-8 text-center">
                    <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900 mb-2">Invalid Reset Link</h1>
                    <p id="errorCardMessage" class="text-gray-600 mb-6">This password reset link is invalid or has expired. Please request a new one.</p>
                    <a href="forgot_password.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                        Request New Reset Link
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Get token from URL
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            
            if (!token) {
                showError('No reset token provided');
                return;
            }
            
            // Verify token
            verifyToken(token);
            
            // Password visibility toggles
            $('#toggleNewPassword').click(function() {
                togglePasswordVisibility('newPassword', this);
            });
            
            $('#toggleConfirmPassword').click(function() {
                togglePasswordVisibility('confirmPassword', this);
            });
            
            // Password strength checker
            $('#newPassword').on('input', function() {
                checkPasswordStrength($(this).val());
                checkPasswordMatch();
            });
            
            // Password match checker
            $('#confirmPassword').on('input', function() {
                checkPasswordMatch();
            });
            
            // Form submission
            $('#resetPasswordForm').submit(function(e) {
                e.preventDefault();
                
                const newPassword = $('#newPassword').val();
                const confirmPassword = $('#confirmPassword').val();
                
                if (newPassword.length < 6) {
                    showFormError('Password must be at least 6 characters long');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    showFormError('Passwords do not match');
                    return;
                }
                
                resetPassword(token, newPassword, confirmPassword);
            });
            
            function verifyToken(token) {
                $.ajax({
                    url: 'ajaxhandler/forgot_password_ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'verify_token',
                        token: token
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#resetToken').val(token);
                            showResetForm();
                        } else {
                            showError(response.message);
                        }
                    },
                    error: function() {
                        showError('An error occurred while verifying the reset link');
                    }
                });
            }
            
            function resetPassword(token, newPassword, confirmPassword) {
                // Show loading state
                $('#submitBtn').prop('disabled', true);
                $('#submitText').text('Resetting...');
                $('#submitLoader').removeClass('hidden');
                
                $.ajax({
                    url: 'ajaxhandler/forgot_password_ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'reset_password',
                        token: token,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    },
                    success: function(response) {
                        if (response.success) {
                            showFormSuccess();
                        } else {
                            showFormError(response.message);
                        }
                    },
                    error: function() {
                        showFormError('An error occurred while resetting password');
                    },
                    complete: function() {
                        $('#submitBtn').prop('disabled', false);
                        $('#submitText').text('Reset Password');
                        $('#submitLoader').addClass('hidden');
                    }
                });
            }
            
            function togglePasswordVisibility(fieldId, button) {
                const field = $('#' + fieldId);
                const icon = $(button).find('i');
                
                if (field.attr('type') === 'password') {
                    field.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    field.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            }
            
            function checkPasswordStrength(password) {
                const strengthFill = $('#passwordStrengthFill');
                const strengthText = $('#passwordStrengthText');
                const lengthCheck = $('#lengthCheck');
                
                let strength = 0;
                let message = '';
                
                if (password.length >= 6) {
                    strength += 1;
                    lengthCheck.removeClass('fa-times text-red-500').addClass('fa-check text-green-500');
                } else {
                    lengthCheck.removeClass('fa-check text-green-500').addClass('fa-times text-red-500');
                }
                
                if (password.length >= 8) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[a-z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                strengthFill.removeClass('strength-weak strength-medium strength-strong');
                
                if (strength <= 2) {
                    strengthFill.addClass('strength-weak');
                    message = 'Weak password';
                } else if (strength <= 4) {
                    strengthFill.addClass('strength-medium');
                    message = 'Medium strength password';
                } else {
                    strengthFill.addClass('strength-strong');
                    message = 'Strong password';
                }
                
                strengthText.text(message);
            }
            
            function checkPasswordMatch() {
                const newPassword = $('#newPassword').val();
                const confirmPassword = $('#confirmPassword').val();
                const matchText = $('#passwordMatchText');
                const matchCheck = $('#matchCheck');
                
                if (confirmPassword === '') {
                    matchText.text('Passwords must match').removeClass('text-red-500 text-green-500').addClass('text-gray-500');
                    matchCheck.removeClass('fa-check text-green-500').addClass('fa-times text-red-500');
                } else if (newPassword === confirmPassword) {
                    matchText.text('Passwords match').removeClass('text-red-500 text-gray-500').addClass('text-green-500');
                    matchCheck.removeClass('fa-times text-red-500').addClass('fa-check text-green-500');
                } else {
                    matchText.text('Passwords do not match').removeClass('text-green-500 text-gray-500').addClass('text-red-500');
                    matchCheck.removeClass('fa-check text-green-500').addClass('fa-times text-red-500');
                }
            }
            
            function showResetForm() {
                $('#loadingState').addClass('hidden');
                $('#resetPasswordCard').removeClass('hidden');
            }
            
            function showError(message) {
                $('#loadingState').addClass('hidden');
                $('#errorCardMessage').text(message);
                $('#errorCard').removeClass('hidden');
            }
            
            function showFormSuccess() {
                $('#successMessage').removeClass('hidden');
                $('#errorMessage').addClass('hidden');
                $('#resetPasswordForm').addClass('hidden');
                $('#loginLinks').removeClass('hidden');
            }
            
            function showFormError(message) {
                $('#errorText').text(message);
                $('#errorMessage').removeClass('hidden');
                $('#successMessage').addClass('hidden');
            }
        });
    </script>
</body>
</html>