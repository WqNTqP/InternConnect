// Global URLs object accessible to all functions
const URLs = {
    logout: "ajaxhandler/adminLogout.php",
    attendance: "ajaxhandler/approveAttendanceAjax.php",
    history: "ajaxhandler/loadHistoryAjax.php",
    adminDashboard: "ajaxhandler/adminDashboardAjax.php",
    weeklyReports: "ajaxhandler/weeklyReportAjax.php",
    reports: "ajaxhandler/approveReportAjax.php",
    predictions: "ajaxhandler/predictionAjax.php"
};

// Helper function to get proper image URL (Cloudinary, absolute, or local)
function getImageUrl(filename) {
    if (!filename) return '';
    const f = String(filename).trim();
    if (/^https?:\/\//i.test(f)) return f;                    // Full URL (Cloudinary)
    if (f.startsWith('//')) return window.location.protocol + f; // Protocol-relative
    if (f.startsWith('/')) return window.location.protocol + '//' + window.location.host + f; // Absolute path
    return 'uploads/' + f;                                    // Local file with uploads path
}

// Global variable to store current report ID for modal operations
let currentReportId = null;

// Function to view individual day report in admin dashboard
function viewDayReportAdmin(reportId, day, studentName) {
    // Create modal HTML
    const modalHtml = `
        <div id="dayReportModalAdmin" class="modal-overlay">
            <div class="modal-content-admin">
                <div class="modal-header-admin">
                    <h3>${studentName} - ${day.charAt(0).toUpperCase() + day.slice(1)} Report</h3>
                    <button onclick="closeDayModalAdmin()" class="modal-close-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="dayReportContentAdmin" class="modal-body-admin">
                    <div class="loading-content">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading report details...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    $('#dayReportModalAdmin').remove();
    
    // Add modal to body
    $('body').append(modalHtml);
    
    // Load report details via AJAX
    $.ajax({
        url: URLs.weeklyReports,
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
                let content = '<div class="day-detail-admin">';
                
                // Images section
                if (dayData.images && dayData.images.length > 0) {
                    content += '<div class="images-section"><h4>Images</h4>';
                    content += '<div class="images-grid">';
                    dayData.images.forEach(img => {
                        content += `<img src="${img.url}" alt="${day} activity" class="detail-image">`;
                    });
                    content += '</div></div>';
                }
                
                // Description section
                content += '<div class="description-section"><h4>Description</h4>';
                content += `<div class="description-content"><p>${dayData.description || 'No description provided for this day.'}</p></div>`;
                content += '</div></div>';
                
                $('#dayReportContentAdmin').html(content);
            } else {
                $('#dayReportContentAdmin').html('<p class="error-message">Error loading report details.</p>');
            }
        },
        error: function() {
            $('#dayReportContentAdmin').html('<p class="error-message">Error loading report details.</p>');
        }
    });
}

// Function to close day modal in admin dashboard
function closeDayModalAdmin() {
    $('#dayReportModalAdmin').remove();
}

// Function to handle returning a report
function returnReport(reportId) {
    currentReportId = reportId;
    $('#returnReportModal').show();
    $('#returnReason').val(''); // Clear any previous reason
}

// Function to submit return report
function submitReturnReport() {
    if (!currentReportId) return;

    const reason = $('#returnReason').val().trim();
    if (!reason) {
        alert('Please provide a reason for returning the report.');
        return;
    }

    $.ajax({
        url: URLs.reports,
        type: 'POST',
        data: {
            action: 'returnReport',
            reportId: currentReportId,
            returnReason: reason
        },
        success: function(response) {
            window.isSubmittingReturn = false;
            if (response.status === 'success') {
                // First close the modal
                closeReturnModal();
                // Then show the alert
                alert('Report returned successfully! The student can now edit it.');
                
                // Reset any loading states
                isLoadingReports = false;
                window.isRefreshing = false;
                
                // Clear any existing timeouts
                if (window.refreshTimeout) clearTimeout(window.refreshTimeout);
                if (window.loadingTimeout) clearTimeout(window.loadingTimeout);
                if (window.reportsTimeout) clearTimeout(window.reportsTimeout);
                
                // Then refresh the reports once with current filters
                const studentId = $('#studentFilter').val();
                if (window.currentWeekNumber) {
                    loadWeeklyReports(studentId, window.currentWeekNumber);
                }
            } else {
                closeReturnModal();
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            alert('Error connecting to server');
            closeReturnModal();
        }
    });
}

// Function to format time to 12-hour format with AM/PM
function formatTimeToPH(timeString) {
    if (!timeString || timeString === '--:--') return '--:--';
    const timeParts = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]), parseInt(timeParts[2]));
    return date.toLocaleTimeString('en-PH', { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', hour12: true });
}

$(function () {
    // Analysis Button Click Handler
    $('.analysis-btn').on('click', function() {
        var studentId = $(this).data('student-id');
        if (!studentId) return;
        
        let html = '<p>Loading prediction analysis...</p>';
        if ($('#analysisModal').length === 0) {
            $('body').append('<div id="analysisModal" class="main-dashboard-modal-bg" style="display:none;"><div class="main-dashboard-modal-content"><div class="main-dashboard-modal-header"><h2 class="main-dashboard-modal-title">Prediction Analysis</h2><button class="main-dashboard-modal-close" id="closeAnalysisModal"><i class="fas fa-times"></i></button></div><div class="main-dashboard-modal-body" id="analysisModalContent"></div></div></div>');
        }
        $('#analysisModalContent').html(html);
        $('#analysisModal').fadeIn();

        $.ajax({
            url: URLs.predictions,
            type: 'POST',
            data: {
                action: 'getPreAssessment',
                student_id: studentId
            },
            success: function(response) {
                let html = '';
                const analysis = response;
                if (!analysis || analysis.error || !analysis.placement) {
                    html = `<div class="prediction-card">
                        <div class="no-prediction-message">
                            <i class="fas fa-info-circle"></i>
                            <p>No placement prediction available yet.</p>
                            <p class="prediction-hint">Please note: Predictions are generated by the coordinator in the main dashboard. The prediction will be available here once it has been completed.</p>
                        </div>
                    </div>`;
                } else {
                    function highlightGrades(text) {
                        return text.replace(/([A-Z]{2,} \d{3}: \d{1,3}\.\d+)/g, '<span class="subject-list">$1</span>');
                    }
                    let reasoningLine = '';
                    if (analysis.subjects && analysis.subjects.trim() !== '') {
                        reasoningLine = `<p>Recommended for <span class="highlight">${analysis.placement}</span> due to strong performance in: <span class="subject-list">${analysis.subjects}</span>.</p>`;
                    }
                    html = `<div class="prediction-card">
                        <h3 class="prediction-title">
                            Predicted OJT Placement:
                            <span class="prediction-badge">${analysis.placement}</span>
                        </h3>
                        <div class="prediction-reasoning">
                            <b>Reasoning:</b>
                            ${reasoningLine}
                            <p>
                                ${highlightGrades((analysis.reasoning || '').replace(/\s*\(average: [^)]+\)/g, ''))}
                            </p>
                        </div>
                        <div class="prediction-probability">
                            <b>Probability Explanation:</b>
                            <p>
                                The model is <span class="confidence-high">${analysis.confidence || ''}</span> that <span class="highlight">${analysis.placement}</span> is the best placement for this student. ${analysis.prob_explanation || ''}
                            </p>
                        </div>
                        <div class="probability-bars">
                            ${Object.entries(analysis.probabilities || {}).map(([k, v]) => {
                                let color = '#3867d6';
                                if (k === 'Systems Development') color = '#20bf6b';
                                else if (k === 'Business Operations') color = '#f7b731';
                                else if (k === 'OJT Placement') color = '#eb3b5a';
                                else if (k === 'Research') color = '#3867d6';
                                return `<div class="probability-row"><span>${k}</span><div class="bar" style="width:${v}%;background:${color};"></div><span>${v}%</span></div>`;
                            }).join('')}
                        </div>
                    </div>`;
                }
                $('#analysisModalContent').html(html);
            },
            error: function() {
                $('#analysisModalContent').html('<p>Error loading prediction analysis.</p>');
            }
        });
    });

    // Analysis Modal Close Button Handler
    $(document).on('click', '#closeAnalysisModal', function() {
        $('#analysisModal').fadeOut();
    });

    // Return Report Modal Event Handlers
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

    // Close sidebar when clicking outside
    $(document).click(function(e) {
        // Check if sidebar is open and click is outside sidebar and not on the toggle button
        if ($('.sidebar').hasClass('sidebar-open') && 
            !$(e.target).closest('.sidebar').length && 
            !$(e.target).closest('#sidebarToggle').length) {
            $('.sidebar').removeClass('sidebar-open');
            $('.content-area').removeClass('sidebar-open');
        }
    });

    // Analysis Button Click Handler
    $('.analysis-btn').on('click', function() {
        var studentId = $(this).data('student-id');
        if (!studentId) return;
        
        let html = '<p>Loading prediction analysis...</p>';
        if ($('#analysisModal').length === 0) {
            $('body').append('<div id="analysisModal" class="main-dashboard-modal-bg" style="display:none;"><div class="main-dashboard-modal-content"><div class="main-dashboard-modal-header"><h2 class="main-dashboard-modal-title">Prediction Analysis</h2><button class="main-dashboard-modal-close" id="closeAnalysisModal"><i class="fas fa-times"></i></button></div><div class="main-dashboard-modal-body" id="analysisModalContent"></div></div></div>');
        }
        $('#analysisModalContent').html(html);
        $('#analysisModal').fadeIn();

        $.ajax({
            url: 'ajaxhandler/getPreAssessmentAjax.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ student_id: studentId }),
            success: function(analysis) {
                let html = '';
                if (!analysis || analysis.error || !analysis.placement) {
                    html = `<div class="prediction-card">
                        <div class="no-prediction-message">
                            <i class="fas fa-info-circle"></i>
                            <p>No placement prediction available yet.</p>
                            <p class="prediction-hint">Please note: Predictions are generated when a student uses the Prediction tab in their main dashboard. Once a student accesses that tab, their prediction will be available here.</p>
                        </div>
                    </div>`;
                } else {
                    function highlightGrades(text) {
                        return text.replace(/([A-Z]{2,} \d{3}: \d{1,3}\.\d+)/g, '<span class="subject-list">$1</span>');
                    }
                    let reasoningLine = '';
                    if (analysis.subjects && analysis.subjects.trim() !== '') {
                        reasoningLine = `<p>Recommended for <span class="highlight">${analysis.placement}</span> due to strong performance in: <span class="subject-list">${analysis.subjects}</span>.</p>`;
                    }
                    html = `<div class="prediction-card">
                        <h3 class="prediction-title">
                            Predicted OJT Placement:
                            <span class="prediction-badge">${analysis.placement}</span>
                        </h3>
                        <div class="prediction-reasoning">
                            <b>Reasoning:</b>
                            ${reasoningLine}
                            <p>
                                ${highlightGrades((analysis.reasoning || '').replace(/\s*\(average: [^)]+\)/g, ''))}
                            </p>
                        </div>
                        <div class="prediction-probability">
                            <b>Probability Explanation:</b>
                            <p>
                                The model is <span class="confidence-high">${analysis.confidence || ''}</span> that <span class="highlight">${analysis.placement}</span> is the best placement for this student. ${analysis.prob_explanation || ''}
                            </p>
                        </div>
                        <div class="probability-bars">
                            ${Object.entries(analysis.probabilities || {}).map(([k, v]) => {
                                let color = '#3867d6';
                                if (k === 'Systems Development') color = '#20bf6b';
                                else if (k === 'Business Operations') color = '#f7b731';
                                else if (k === 'OJT Placement') color = '#eb3b5a';
                                else if (k === 'Research') color = '#3867d6';
                                return `<div class="probability-row"><span>${k}</span><div class="bar" style="width:${v}%;background:${color};"></div><span>${v}%</span></div>`;
                            }).join('')}
                        </div>
                    </div>`;
                }
                $('#analysisModalContent').html(html);
            },
            error: function() {
                $('#analysisModalContent').html('<p>Error loading prediction analysis.</p>');
            }
        });
    });

    // Analysis Modal Close Button Handler
    $(document).on('click', '#closeAnalysisModal', function() {
        $('#analysisModal').fadeOut();
    });

    // Close user dropdown when clicking outside
    $(document).click(function(e) {
        const userProfile = $('#userProfile');
        const userDropdown = $('#userDropdown');
        if (!userProfile.is(e.target) && userProfile.has(e.target).length === 0) {
            userDropdown.hide();
        }
    });

    // Tab functionality
    $('.sidebar-item').on('click', function() {
        const tabId = $(this).attr('id');
        
        // Hide all tab contents
        $('.tab-content').hide();
        
        // Hide reports container when switching away from reports tab
        $('#reportsContainer').hide();
        $('#reportsList').hide();
        $('#noReports').hide();
        $('#reportsLoading').hide();
        
        // Hide history loading when switching away from history tab
        $('#historyLoading').hide();
        
        // Show the selected tab content
        if (tabId === 'pendingTab') {
            $('#pendingTabContent').show();
        } else if (tabId === 'dashboardTab') {
            $('#dashboardTabContent').show();
        } else if (tabId === 'historyTab') {
            $('#historyTabContent').show();
            // Show loading spinner immediately
            $('#historyLoading').show();
            $('#historySummary').hide();
            // Auto-load current date when history tab is opened
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('historyDate');
            dateInput.value = today;
            loadHistoryRecords(today);
        } else if (tabId === 'reportsTab') {
            $('#reportsTabContent').show();
            $('#reportsContainer').show();
        } else if (tabId === 'questionApprovalsTab') {
            $('#questionApprovalsTabContent').show();
            // Note: AJAX functionality is now handled by inline JS in admindashboard.php
               // Automatically load the current week's report when Reports tab is opened
               const today = new Date().toISOString().split('T')[0];
               const dateInput = document.getElementById('dateFilter');
               if (dateInput) {
                   dateInput.value = today;
               }
               let studentId = document.getElementById('studentFilter').value;
               const date = new Date(today);
               const day = date.getDay();
               const diff = date.getDate() - day + (day === 0 ? -6 : 1);
               const weekStart = new Date(date.setDate(diff));
               const weekEnd = new Date(weekStart);
               weekEnd.setDate(weekStart.getDate() + 6);
               const formattedWeekStart = weekStart.toISOString().split('T')[0];
               const formattedWeekEnd = weekEnd.toISOString().split('T')[0];
               document.getElementById('previewWeekNumber').textContent = getWeekNumber(weekStart);
               document.getElementById('previewWeekRange').textContent = `${formattedWeekStart} to ${formattedWeekEnd}`;
               window.currentWeekStart = formattedWeekStart;
               window.currentWeekEnd = formattedWeekEnd;
               if (isNaN(studentId) && studentId !== "") {
                   const options = document.getElementById('studentList').options;
                   let matchFound = false;
                   
                   // First try exact match
                   for (let i = 0; i < options.length; i++) {
                       if (options[i].value === studentId) {
                           studentId = options[i].getAttribute('data-intern-id');
                           matchFound = true;
                           break;
                       }
                   }
                   
                   // If no exact match, try partial match (case insensitive)
                   if (!matchFound && studentId.trim() !== '') {
                       const searchTerm = studentId.toLowerCase().trim();
                       for (let i = 0; i < options.length; i++) {
                           const optionValue = options[i].value.toLowerCase();
                           if (optionValue.includes(searchTerm)) {
                               studentId = options[i].getAttribute('data-intern-id');
                               // Update the input field with the matched option
                               document.getElementById('studentFilter').value = options[i].value;
                               matchFound = true;
                               break;
                           }
                       }
                   }
                   
                   // If no match found, use special flag to show no results
                   if (!matchFound) {
                       studentId = "NO_MATCH_FOUND";
                   }
               }
               const weekNumber = getWeekNumber(new Date(window.currentWeekStart));
               loadWeeklyReports(studentId, weekNumber);
        } else if (tabId === 'evaluationTab') {
            $('#evaluationTabContent').show();
            // Reset evaluation tab to initial empty state if no student is selected
            if (!$('.admin-eval-student.active').length) {
                $('.admin-eval-loading').hide();
                $('.admin-eval-content').hide();
                $('.admin-eval-empty-state').show();
            }
        } else if (tabId === 'contralTab') {
            $('#contralTabContent').show();
            
            // Debug: Check if admin data is available before loading
            console.log('Control tab opened - checking admin data...');
            console.log('userName element exists:', $('#userName').length > 0);
            console.log('data-admin-id attribute value:', $('[data-admin-id]').first().attr('data-admin-id'));
            
            loadCompaniesMOAData(); // Load MOA data when control tab is opened
            loadSystemInformation(); // Load system information
        }
        
        // Update active tab
        $('.sidebar-item').removeClass('active');
        $(this).addClass('active');
    });

    function handleAjaxError(xhr, status, error) {
        console.error("AJAX Error:", error);
        console.log("Response text:", xhr.responseText);
        alert(`An error occurred (${status}): ${xhr.statusText}. Please try again.`);
    }

    // Logout functionality
    $(document).on("click", "#btnlogout", function () {
        $.ajax({
            url: URLs.logout,
            type: "POST",
            dataType: "json",
            data: { id: 1 },
            beforeSend: function () {
                $("#btnlogout").prop("disabled", true);
            },
            complete: function () {
                $("#btnlogout").prop("disabled", false);
            },
            success: function (response) {
                document.location.replace("supervisor");
            },
            error: handleAjaxError,
        });
    });

    // Approve attendance functionality
    window.approveAttendance = function (recordId) {
        if (!recordId) {
            alert("Invalid record ID. Please try again.");
            return;
        }

        if (confirm("Are you sure you want to approve this attendance?")) {
            $.ajax({
                url: URLs.attendance,
                type: "POST",
                dataType: "json",
                data: { action: 'approveAttendance', id: recordId },
                beforeSend: function () {
                    console.log(`Sending approval request for record ID: ${recordId}`);
                },
                success: function (response) {
                    if (response.status === 'success') {
                        alert(response.message); // Display success message
                        // Remove the approved record from the table
                        $(`button[onclick="approveAttendance(${recordId})"]`).closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            // Check if there are any remaining records
                            if ($('#pendingTabContent table tbody tr').length === 0) {
                                $('#pendingTabContent table tbody').append('<tr><td colspan="4">No pending attendance records found.</td></tr>');
                            }
                        });
                    } else {
                        alert(`Error: ${response.message}`); // Display error message
                    }
                },
                error: handleAjaxError,
            });
        }
    };

    // Decline attendance functionality
    window.deletePendingAttendance = function (recordId) {
        if (!recordId) {
            alert("Invalid record ID. Please try again.");
            return;
        }

        if (confirm("Are you sure you want to decline this attendance?")) {
            $.ajax({
                url: "ajaxhandler/deletePendingAttendanceAjax.php",
                type: "POST",
                dataType: "json",
                data: { action: 'deletePendingAttendance', id: recordId },
                beforeSend: function () {
                    console.log(`Sending deletion request for record ID: ${recordId}`);
                },
                success: function (response) {
                    if (response.status === 'success') {
                        alert(response.message); // Display success message
                        // Remove the declined record from the table
                        $(`button[onclick="deletePendingAttendance(${recordId})"]`).closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            // Check if there are any remaining records
                            if ($('#pendingTabContent table tbody tr').length === 0) {
                                $('#pendingTabContent table tbody').append('<tr><td colspan="4">No pending attendance records found.</td></tr>');
                            }
                        });
                    } else {
                        alert(`Error: ${response.message}`); // Display error message
                    }
                },
                error: function (xhr, status, error) {
                    console.error("AJAX Error:", error);
                    alert(`An error occurred: ${xhr.statusText}. Please try again.`);
                },
            });
        }
    };

    // Load history records functionality
    document.getElementById('historyDate').addEventListener('change', function() {
        const selectedDate = this.value;
        if (!selectedDate) {
            alert('Please select a date first.');
            return;
        }
        
        loadHistoryRecords(selectedDate);
    });

    // Function to get week range from a date
    function getWeekRange(date) {
        const currentDate = new Date(date);
        const dayOfWeek = currentDate.getDay();
        const diff = currentDate.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Adjust when day is sunday
        const monday = new Date(currentDate.setDate(diff));
        const sunday = new Date(currentDate.setDate(diff + 6));
        
        return {
            start: monday.toISOString().split('T')[0],
            end: sunday.toISOString().split('T')[0],
            weekNumber: getWeekNumber(monday)
        };
    }

    // Function to get week number
    function getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        const weekNo = Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
        return weekNo;
    }

    // Load weekly reports functionality
    document.getElementById('loadReportsBtn').addEventListener('click', function() {
        let studentId = document.getElementById('studentFilter').value;
        const selectedDate = document.getElementById('dateFilter').value;

        if (!selectedDate) {
            alert('Please select a date first.');
            return;
        }

        // Convert selected date to week start and end dates
        const date = new Date(selectedDate);
        const day = date.getDay();
        const diff = date.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is sunday
        const weekStart = new Date(date.setDate(diff));
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);

        // Format dates as YYYY-MM-DD to match database format
        const formattedWeekStart = weekStart.toISOString().split('T')[0];
        const formattedWeekEnd = weekEnd.toISOString().split('T')[0];
        
        // Update the preview week range display
        document.getElementById('previewWeekNumber').textContent = getWeekNumber(weekStart);
        document.getElementById('previewWeekRange').textContent = `${formattedWeekStart} to ${formattedWeekEnd}`;

        // Store week dates for the AJAX request
        window.currentWeekStart = formattedWeekStart;
        window.currentWeekEnd = formattedWeekEnd;

        // If studentId is not a number (because user typed a name), try to find matching INTERNS_ID from datalist options
        if (isNaN(studentId) && studentId !== "") {
            const options = document.getElementById('studentList').options;
            let matchFound = false;
            
            // First try exact match
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === studentId) {
                    studentId = options[i].getAttribute('data-intern-id');
                    matchFound = true;
                    break;
                }
            }
            
            // If no exact match, try partial match (case insensitive)
            if (!matchFound && studentId.trim() !== '') {
                const searchTerm = studentId.toLowerCase().trim();
                for (let i = 0; i < options.length; i++) {
                    const optionValue = options[i].value.toLowerCase();
                    // Check if search term matches the beginning of surname or anywhere in the name
                    const names = optionValue.split(',');
                    const surname = names[0] ? names[0].trim() : '';
                    const firstName = names[1] ? names[1].trim() : '';
                    
                    if (optionValue.includes(searchTerm) || 
                        surname.startsWith(searchTerm) || 
                        firstName.startsWith(searchTerm) ||
                        (surname + ' ' + firstName).includes(searchTerm)) {
                        studentId = options[i].getAttribute('data-intern-id');
                        // Update the input field with the matched option for better UX
                        document.getElementById('studentFilter').value = options[i].value;
                        matchFound = true;
                        break;
                    }
                }
            }
            
            // If still no match found, use a special flag to indicate no results should be shown
            if (!matchFound) {
                studentId = "NO_MATCH_FOUND";
                console.log('No matching student found for: ' + studentId);
            }
        }

        const weekNumber = getWeekNumber(new Date(window.currentWeekStart));
        loadWeeklyReports(studentId, weekNumber);
    });

    // Function to load history records via AJAX
    function loadHistoryRecords(date) {
        $.ajax({
            url: URLs.history,
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'loadHistory',
                date: date 
            },
            beforeSend: function() {
                // Show loading spinner and hide other content
                $('#historyLoading').show();
                $('#historySummary').hide();
                document.getElementById('historyNoRecords').style.display = 'none';
            },
            success: function(response) {
                // Hide loading spinner
                $('#historyLoading').hide();
                
                if (response.status === 'success') {
                    // Update summary and show content
                    document.getElementById('selectedDate').textContent = date;
                    document.getElementById('historyPresent').textContent = response.summary.present || 0;
                    document.getElementById('historyOnTime').textContent = response.summary.on_time || 0;
                    document.getElementById('historyLate').textContent = response.summary.late || 0;
                    document.getElementById('historyTotal').textContent = response.summary.total || 0;
                    $('#historySummary').show();

                    // Populate student lists
                    function populateList(listId, students) {
                        const ul = document.getElementById(listId);
                        ul.innerHTML = '';
                        if (students && students.length > 0) {
                            students.forEach(student => {
                                const li = document.createElement('li');
                                li.textContent = student.SURNAME;
                                ul.appendChild(li);
                            });
                        } else {
                            const li = document.createElement('li');
                            li.textContent = 'No students';
                            ul.appendChild(li);
                        }
                    }

                    // Populate student lists as tables with details
                    function populateTable(tableId, students) {
                        const tbody = document.querySelector(`#${tableId} tbody`);
                        tbody.innerHTML = '';
                        if (students && students.length > 0) {
                            students.forEach(student => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td>${student.STUDENT_ID}</td>
                                    <td>${student.display_name}</td>
                                    <td>${student.TIMEIN || '--:--'}</td>
                                    <td>${student.TIMEOUT || '--:--'}</td>
                                    <td>${student.status}</td>
                                `;
                                tbody.appendChild(tr);
                            });
                        } else {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `<td colspan="5" style="text-align:center;">No students</td>`;
                            tbody.appendChild(tr);
                        }
                    }

                    // populateTable('historyPresentList', response.presentList);
                    // populateTable('historyOnTimeList', response.onTimeList);
                    // populateTable('historyLateList', response.lateList);
                    // populateTable('historyAbsentList', response.absentList);

                    // Update table
                    if (response.records && response.records.length > 0) {
                        let tableHTML = '';
                        response.records.forEach(function(record) {
                            const statusClass = record.status.toLowerCase().replace(' ', '-');
                            tableHTML += `
                                <tr>
                                    <td>${record.STUDENT_ID}</td>
                                    <td>${record.SURNAME}</td>
                                    <td>${formatTimeToPH(record.TIMEIN)}</td>
                                    <td>${formatTimeToPH(record.TIMEOUT)}</td>
                                    <td class="status-${statusClass}">${record.status}</td>
                                </tr>
                            `;
                        });
                        document.getElementById('historyTableBody').innerHTML = tableHTML;
                        document.getElementById('historyTable').style.display = 'table';
                        document.getElementById('historyNoRecords').style.display = 'none';
                    } else {
                        document.getElementById('historyTable').style.display = 'none';
                        document.getElementById('historyNoRecords').style.display = 'block';
                    }
                } else {
                    alert('Error loading history: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Hide loading spinner on error
                $('#historyLoading').hide();
                console.error('AJAX Error:', error);
                alert('An error occurred while loading history records.');
            }
        });
    }

});

// Profile modal buttons event delegation (since buttons are dynamically loaded)
$(document).on('click', '.btn-edit', function() {
    showEditProfileModal();
});

$(document).on('click', '.btn-change-password', function() {
    showChangePasswordModal();
});

// Function to show Edit Profile modal
function showEditProfileModal() {
    const profileContent = `
        <div class="modal" id="editProfileModal" style="display: flex;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Profile Picture</h2>
                    <button class="modal-close" id="closeEditProfileModal">&times;</button>
                </div>
                <div class="modal-body">
<form id="editProfileForm" class="edit-profile-picture-form" enctype="multipart/form-data">
                        <div class="profile-picture-section">
                            <div class="current-profile-picture">
                                <div class="avatar-placeholder">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                            </div>
                        <div class="profile-picture-upload file-upload-group">
                            <label for="profilePicture">Upload Profile Picture:</label>
                            <input type="file" id="profilePicture" name="profilePicture" accept="image/*" class="file-input-wrapper">
                            <small>Max file size: 2MB (JPG, PNG, GIF)</small>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">Save Changes</button>
                        <button type="button" class="btn-cancel" id="closeEditProfileModalCancel">Cancel</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    // Append modal to body
    $('body').append(profileContent);

    // Close modal event
    $('#closeEditProfileModal, #closeEditProfileModalCancel').on('click', function() {
        $('#editProfileModal').remove();
    });

    // Submit form event
    $('#editProfileForm').on('submit', function(e) {
        e.preventDefault();

        // Validate profile picture file size and type if selected
        const fileInput = $('#profilePicture')[0];
        if (fileInput.files.length === 0) {
            alert('Please select a profile picture to upload.');
            return;
        }

        const file = fileInput.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 2 * 1024 * 1024; // 2MB

        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Only JPG, PNG, and GIF are allowed.');
            return;
        }
        if (file.size > maxSize) {
            alert('File size exceeds 2MB limit.');
            return;
        }

        // Prepare FormData for AJAX
        const formData = new FormData();
        formData.append('action', 'updateAdminProfilePicture');
        formData.append('adminId', $('#userName').data('admin-id'));
        formData.append('profilePicture', fileInput.files[0]);

        // Send AJAX request to update profile picture only
        $.ajax({
            url: URLs.adminDashboard,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Profile picture updated successfully.');
                    $('#editProfileModal').remove();
                    // Reload profile details in modal
                    loadAdminProfileDetails();
                } else {
                    alert('Error updating profile picture: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                alert('An error occurred while updating profile picture.');
            }
        });
    });
}

// Global loading flag to prevent multiple simultaneous calls
let isLoadingReports = false;

// Function to load weekly reports via AJAX
function loadWeeklyReports(studentId, weekNumber) {
    // Check if no matching student was found
    if (studentId === "NO_MATCH_FOUND") {
        // Hide loading and reports list
        $('#reportsLoading').hide();
        $('#reportsList').hide();
        
        // Update the no reports message to be more specific
        const searchValue = document.getElementById('studentFilter').value;
        const noReportsDiv = $('#noReports');
        const emptyStateTitle = noReportsDiv.find('.empty-state-title');
        const emptyStateMessage = noReportsDiv.find('.empty-state-message');
        
        if (emptyStateTitle.length && emptyStateMessage.length) {
            emptyStateTitle.text('Student Not Found');
            emptyStateMessage.html(`No student found matching "${searchValue}".<br>Please check the spelling or select from the dropdown list.`);
        }
        
        // Add visual feedback to the search input
        const searchInput = document.getElementById('studentFilter');
        if (searchInput) {
            searchInput.style.borderColor = '#e74c3c';
            searchInput.style.backgroundColor = 'rgba(231, 76, 60, 0.05)';
        }
        
        noReportsDiv.show();
        return;
    }
    
    // Allow the request if it's after a return, regardless of loading state
    if (isLoadingReports && !window.isAfterReturn) {
        console.log('Reports are already being loaded, skipping this request...');
        return;
    }
    
    // Clear any existing timeouts
    if (window.reportsTimeout) {
        clearTimeout(window.reportsTimeout);
    }
    if (window.loadingTimeout) {
        clearTimeout(window.loadingTimeout);
    }
    if (window.refreshTimeout) {
        clearTimeout(window.refreshTimeout);
    }
    
    // Set loading flag
    isLoadingReports = true;
    
    // Clear refresh flag if it was set
    window.isRefreshing = false;
    
    // Set a safety timeout to reset all flags after 5 seconds
    window.loadingTimeout = setTimeout(() => {
        isLoadingReports = false;
        window.isRefreshing = false;
        console.log('Loading flags reset by safety timeout');
    }, 5000);

    $.ajax({
        url: URLs.weeklyReports,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'getWeeklyReports',
            studentId: studentId,
            week: weekNumber,
            weekStart: window.currentWeekStart,
            weekEnd: window.currentWeekEnd,
            adminId: $('#userName').data('admin-id')
        },
        beforeSend: function() {
            // Show loading state
            document.getElementById('reportsLoading').style.display = 'block';
            document.getElementById('reportsList').style.display = 'none';
            document.getElementById('noReports').style.display = 'none';
        },
        success: function(response) {
            document.getElementById('reportsLoading').style.display = 'none';
            // Reset all flags
            isLoadingReports = false;
            window.isRefreshing = false;
            
            // Clear any timeouts
            if (window.loadingTimeout) clearTimeout(window.loadingTimeout);
            if (window.refreshTimeout) clearTimeout(window.refreshTimeout);
            if (window.reportsTimeout) clearTimeout(window.reportsTimeout);
            
            // Only log response if it's from a return report refresh
            if (window.isAfterReturn) {
                console.log("Weekly Reports Response after return:", response);
                window.isAfterReturn = false;
            }
            
            if (response.status === 'success') {
                if (response.reports && response.reports.length > 0) {
                    displayWeeklyReports(response.reports);
                    document.getElementById('reportsList').style.display = 'block';
                    document.getElementById('noReports').style.display = 'none';
                } else {
                    // Reset the empty state message to default
                    resetEmptyStateMessage();
                    document.getElementById('reportsList').style.display = 'none';
                    document.getElementById('noReports').style.display = 'block';
                }
            } else {
                alert('Error loading reports: ' + response.message);
                // Reset the empty state message to default
                resetEmptyStateMessage();
                document.getElementById('reportsList').style.display = 'none';
                document.getElementById('noReports').style.display = 'block';
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('An error occurred while loading weekly reports.');
            // Reset the empty state message to default
            resetEmptyStateMessage();
            document.getElementById('reportsLoading').style.display = 'none';
            document.getElementById('reportsList').style.display = 'none';
            document.getElementById('noReports').style.display = 'block';
            // Reset loading flag
            isLoadingReports = false;
        }
    });
}

// Function to reset empty state message to default
function resetEmptyStateMessage() {
    const noReportsDiv = $('#noReports');
    const emptyStateTitle = noReportsDiv.find('.empty-state-title');
    const emptyStateMessage = noReportsDiv.find('.empty-state-message');
    
    // Reset to original empty state message
    if (emptyStateTitle.length && emptyStateMessage.length) {
        emptyStateTitle.text('No Weekly Reports Found');
        emptyStateMessage.html('No reports match your current search criteria.<br>Try adjusting the date range or selecting a different student.');
    }
    
    // Reset search input styling
    const searchInput = document.getElementById('studentFilter');
    if (searchInput) {
        searchInput.style.borderColor = '';
        searchInput.style.backgroundColor = '';
    }
}

// Function to calculate week number from date
function getWeekNumber(date) {
    const firstDayOfYear = new Date(date.getFullYear(), 0, 1);
    const pastDaysOfYear = (date - firstDayOfYear) / 86400000;
    return Math.ceil((pastDaysOfYear + firstDayOfYear.getDay() + 1) / 7);
}

// Function to display weekly reports
function displayWeeklyReports(reports) {
    const reportsList = document.getElementById('reportsList');
    reportsList.innerHTML = '';

    reports.forEach(report => {
        const reportCard = document.createElement('div');
        reportCard.className = 'report-card admin-report-preview';

        // Calculate week number from week_start date
        let weekNumber = 'undefined';
        if (report.week_start) {
            const weekStartDate = new Date(report.week_start);
            weekNumber = getWeekNumber(weekStartDate);
        }

        // Use the pre-parsed contentPerDay from backend, or parse report_content if not available
        let contentPerDay = {};
        if (report.contentPerDay && typeof report.contentPerDay === 'object') {
            contentPerDay = report.contentPerDay;
        } else if (report.report_content) {
            try {
                // Try to parse as JSON first (new format)
                contentPerDay = JSON.parse(report.report_content);
            } catch (e) {
                // Fallback to old format - put all content in monday
                contentPerDay = {
                    monday: report.report_content,
                    tuesday: '',
                    wednesday: '',
                    thursday: '',
                    friday: ''
                };
            }
        } else {
            contentPerDay = {
                monday: '',
                tuesday: '',
                wednesday: '',
                thursday: '',
                friday: ''
            };
        }

        // Get images per day if available
        const imagesPerDay = report.imagesPerDay || {};
        const legacyImages = report.images || [];

        // Remove fallback that assigns all images to Monday
        // if (!Object.keys(imagesPerDay).length && legacyImages.length > 0) {
        //     imagesPerDay.monday = legacyImages;
        // }

        // Ensure all days have image arrays
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        days.forEach(day => {
            if (!imagesPerDay[day]) {
                imagesPerDay[day] = [];
            }
        });

        reportCard.innerHTML = `
            <div class="report-header">
                <h3>${report.student_name} - Week ${weekNumber}</h3>
                <div class="report-meta">
                    <span class="report-period">Period: ${report.week_start} to ${report.week_end}</span>
                    <span class="approval-status ${report.approval_status.toLowerCase()}">${report.approval_status.charAt(0).toUpperCase() + report.approval_status.slice(1)}</span>
                </div>
            </div>

            <div class="report-grid-compact">
                ${['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].map(day => {
                    const dayImages = imagesPerDay[day] || [];
                    const dayDesc = report[day + '_description'] || 'No description provided';
                    const shortDesc = dayDesc.length > 50 ? dayDesc.substring(0, 50) + '...' : dayDesc;
                    
                    return `
                        <div class='day-section-compact ${day}'>
                            <h4>${day.charAt(0).toUpperCase() + day.slice(1)}</h4>
                            <div class='day-content-compact'>
                                <div class='day-image-preview'>
                                    ${dayImages.length > 0 ? 
                                        `<img src='${dayImages[0].url || dayImages[0].image_path || ''}' alt='${day} activity' class='preview-image'>` : 
                                        `<div class='no-image-placeholder'>No image</div>`
                                    }
                                </div>
                                <div class='day-description-preview'><p>${shortDesc}</p></div>
                                <button class='btn-view-day' onclick='viewDayReportAdmin(${report.report_id}, "${day}", "${report.student_name}")'>
                                    <i class='fas fa-eye'></i> View
                                </button>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>

            <div class="report-footer">
                <div class="footer-left">
                    ${report.updated_at ? `<span class="updated-date">Last Updated: ${report.updated_at}</span>` : ''}
                </div>
                <div class="footer-right">
                    <button class="action-btn approve-btn" onclick="approveReport(${report.report_id})">Approve</button>
                    <button class="action-btn return-btn" onclick="returnReport(${report.report_id})">Return</button>
                </div>
            </div>
        `;

        reportsList.appendChild(reportCard);
    });
}

// Function to load admin profile details
function loadAdminProfileDetails() {
    // Clear previous content
    $('#profileModalContent').html('');
    
    // Show modal and set loading state
    $('#profileModalContent').html('<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading profile details...</div>');
    $('#profileModal').css('display', 'flex');
    
    // Get admin ID from the user dropdown span
    const adminId = $('[data-admin-id]').data('admin-id');
    
    // Fetch profile data from server
    $.ajax({
        url: URLs.adminDashboard,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'getAdminDetails',
            adminId: adminId
        },
        success: function(response) {
            if (response.status === 'success') {
                displayAdminProfileDetails(response.data);
            } else {
                alert('Error loading profile details: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", error);
            alert('An error occurred while loading profile details.');
        }
    });
}

// Function to approve a report
function approveReport(reportId) {
    if (!confirm('Are you sure you want to approve this report?')) {
        return;
    }

    $.ajax({
        url: URLs.reports,
        type: 'POST',
        data: {
            action: 'approveReport',
            reportId: reportId
        },
        success: function(response) {
            if (response.status === 'success') {
                alert('Report approved successfully!');
                // Refresh the reports list
                loadWeeklyReports();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('An error occurred while approving the report.');
        }
    });
}

// Modal event handlers
// Document ready handler for admin dashboard
$(document).ready(function() {
    $(document).on('click', '.analysis-btn', function() {
        var studentId = $(this).data('student-id');
        if (!studentId) return;
        
        let html = '<p>Loading prediction analysis...</p>';
        if ($('#analysisModal').length === 0) {
            $('body').append('<div id="analysisModal" class="main-dashboard-modal-bg" style="display:none;"><div class="main-dashboard-modal-content"><div class="main-dashboard-modal-header"><h2 class="main-dashboard-modal-title">Prediction Analysis</h2><button class="main-dashboard-modal-close" id="closeAnalysisModal"><i class="fas fa-times"></i></button></div><div class="main-dashboard-modal-body" id="analysisModalContent"></div></div></div>');
        }
        $('#analysisModalContent').html(html);
        $('#analysisModal').fadeIn();

        $.ajax({
            url: URLs.predictions,
            type: 'POST',
            data: {
                action: 'getPreAssessment',
                student_id: studentId
            },
            success: function(response) {
                let html = '';
                const analysis = response;
                if (!analysis || analysis.error || !analysis.placement) {
                    html = `<div class="prediction-card">
                        <div class="no-prediction-message">
                            <i class="fas fa-info-circle"></i>
                            <p>No placement prediction available yet.</p>
                            <p class="prediction-hint">Please note: Predictions are generated by the coordinator in the main dashboard. The prediction will be available here once it has been completed.</p>
                        </div>
                    </div>`;
                } else {
                    function highlightGrades(text) {
                        return text.replace(/([A-Z]{2,} \d{3}: \d{1,3}\.\d+)/g, '<span class="subject-list">$1</span>');
                    }
                    let reasoningLine = '';
                    if (analysis.subjects && analysis.subjects.trim() !== '') {
                        reasoningLine = `<p>Recommended for <span class="highlight">${analysis.placement}</span> due to strong performance in: <span class="subject-list">${analysis.subjects}</span>.</p>`;
                    }
                    html = `<div class="prediction-card">
                        <h3 class="prediction-title">
                            Predicted OJT Placement:
                            <span class="prediction-badge">${analysis.placement}</span>
                        </h3>
                        <div class="prediction-reasoning">
                            <b>Reasoning:</b>
                            ${reasoningLine}
                            <p>
                                ${highlightGrades((analysis.reasoning || '').replace(/\s*\(average: [^)]+\)/g, ''))}
                            </p>
                        </div>
                        <div class="prediction-probability">
                            <b>Probability Explanation:</b>
                            <p>
                                The model is <span class="confidence-high">${analysis.confidence || ''}</span> that <span class="highlight">${analysis.placement}</span> is the best placement for this student. ${analysis.prob_explanation || ''}
                            </p>
                        </div>
                        <div class="probability-bars">
                            ${Object.entries(analysis.probabilities || {}).map(([k, v]) => {
                                let color = '#3867d6';
                                if (k === 'Systems Development') color = '#20bf6b';
                                else if (k === 'Business Operations') color = '#f7b731';
                                else if (k === 'OJT Placement') color = '#eb3b5a';
                                else if (k === 'Research') color = '#3867d6';
                                return `<div class="probability-row"><span>${k}</span><div class="bar" style="width:${v}%;background:${color};"></div><span>${v}%</span></div>`;
                            }).join('')}
                        </div>
                    </div>`;
                }
                $('#analysisModalContent').html(html);
            },
            error: function() {
                $('#analysisModalContent').html('<p>Error loading prediction analysis.</p>');
            }
        });
    });

    // Analysis Modal Close Button Handler
    $(document).on('click', '#closeAnalysisModal', function() {
        $('#analysisModal').fadeOut();
    });

    // Evaluation tab: load questions and ratings for selected student
    $(document).on('click', '.admin-eval-student', function() {
        $('.admin-eval-student').removeClass('active').css({
            'background':'#fff',
            'color':'#374151',
            'font-weight':'500',
            'border':'2px solid #e5e7eb'
        }).find('i').css('color', '#9ca3af');
        $(this).addClass('active').css({
            'background':'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            'color':'#fff',
            'font-weight':'600',
            'border':'2px solid transparent'
        }).find('i').css('color', '#93c5fd');
        var studentId = $(this).data('student-id');
        
        // Hide empty state and show loading
        $('.admin-eval-empty-state').hide();
        $('.admin-eval-content').hide();
        $('.admin-eval-loading').show();
        
        $.get('ajaxhandler/getStudentQuestionsAndRatingsAjax.php', {student_id: studentId}, function(data) {
            var result = {};
            try { result = JSON.parse(data); } catch(e) { result = {}; }
            
            // Hide loading and show content
            $('.admin-eval-loading').hide();
            $('.admin-eval-content').show();
            
            var $main = $('.admin-eval-content');
            $main.empty();
            var hasCategories = false;
            $.each(result, function(category, catData) {
                hasCategories = true;
                var catTitle = 'COMPETENCY ON ' + category.toUpperCase();
                var $catDiv = $('<div class="admin-eval-category" style="margin-bottom:32px;"></div>');
                $catDiv.append('<h3 style="text-transform:uppercase;font-size:18px;margin-bottom:12px;color:#374151;letter-spacing:1px;">'+catTitle+'</h3>');
                    var tableHeader = '<table class="admin-eval-table" style="width:100%;border-collapse:collapse;margin-bottom:12px;"><thead><tr>' +
                        '<th style="border:1px solid #e5e7eb;padding:10px;background:#f3f4f6;font-weight:600;">Discipline/Task</th>' +
                        '<th class="self-rating-col" style="border:1px solid #e5e7eb;padding:10px;background:#f3f4f6;font-weight:600;width:100px;min-width:100px;max-width:100px;text-align:center;">Self-Rating</th>';
                    for(var i=5;i>=1;i--) {
                        tableHeader += '<th style="border:1px solid #e5e7eb;padding:0;background:#f3f4f6;font-weight:600;width:30px;text-align:center;">'+i+'</th>';
                    }
                    tableHeader += '</tr></thead><tbody></tbody></table>';
                    var $table = $(tableHeader);
                $.each(catData.questions, function(_, q) {
                    var ratingObj = (catData.ratings && catData.ratings[q.id]) ? catData.ratings[q.id] : {};
                    var selfRating = ratingObj.self_rating ? ratingObj.self_rating : '';
                    var $row = $('<tr></tr>').attr('data-question-id', q.question_id);
                    $row.append('<td style="border:1px solid #e5e7eb;padding:10px;">'+q.question_text+'</td>');
                    $row.append('<td class="self-rating-col" style="border:1px solid #e5e7eb;padding:10px;width:100px;min-width:100px;max-width:100px;text-align:center;">'+selfRating+'</td>');
                            for(var i=5;i>=1;i--) {
                                var radioName = 'admin_rating_'+category+'_'+q.id;
                            var supervisorRating = ratingObj.supervisor_rating ? ratingObj.supervisor_rating : '';
                            var checked = (supervisorRating == i) ? 'checked' : '';
                                $row.append('<td style="border:1px solid #e5e7eb;padding:0;width:30px;text-align:center;vertical-align:middle;">'+
                                    '<input type="radio" name="'+radioName+'" value="'+i+'" style="margin:0;" '+checked+'>'+
                                '</td>');
                            }
                        $table.find('tbody').append($row);
                });
                $catDiv.append($table);
                $main.append($catDiv);
            });
                // Add comments/recommendations table after all competency tables
                if(hasCategories) {
                    // Find the first non-empty comment for this student
                    var commentsText = '';
                    outer: for (var category in result) {
                        var catData = result[category];
                        for (var i = 0; i < catData.questions.length; i++) {
                            var q = catData.questions[i];
                            var ratingObj = (catData.ratings && catData.ratings[q.id]) ? catData.ratings[q.id] : {};
                            if (ratingObj.comment && ratingObj.comment.trim() !== '') {
                                commentsText = ratingObj.comment;
                                break outer;
                            }
                        }
                    }
                    var commentsTable = '<div class="admin-eval-comments-section" style="margin-top:32px;">'+
                        '<h3 style="font-size:17px;color:#2563eb;margin-bottom:12px;">Comments / Recommendations</h3>'+
                        '<table class="admin-eval-comments-table" style="width:100%;border-collapse:collapse;">'+
                            '<thead><tr>'+
                                '<th style="border:1px solid #e5e7eb;padding:10px;background:#f3f4f6;font-weight:600;width:200px;">Comment/Recommendation</th>'+
                                '<th style="border:1px solid #e5e7eb;padding:10px;background:#f3f4f6;font-weight:600;width:120px;">Date</th>'+
                            '</tr></thead>'+
                            '<tbody>'+
                                '<tr>'+
                                    '<td colspan="2" style="border:1px solid #e5e7eb;padding:10px;text-align:left;">'+
                                        '<textarea class="admin-eval-comment-input" name="comment" placeholder="Add a comment or recommendation..." style="width:100%;padding:8px 12px;border-radius:4px;border:1px solid #ccc;font-size:15px;min-height:60px;">'+commentsText+'</textarea>'+
                                    '</td>'+
                                '</tr>'+
                            '</tbody>'+
                        '</table>'+
                    '</div>';
                    $main.append(commentsTable);
                }
            if(hasCategories) {
                $main.append('<div class="admin-eval-actions" style="margin-top: 32px; padding-top: 24px; border-top: 2px solid #f3f4f6; text-align: center;"><button class="admin-eval-save-btn">Save Evaluation</button></div>');
            } else {
                $main.append('<div class="admin-eval-no-data" style="padding: 60px 20px; text-align: center; color: #6b7280;"><div style="margin-bottom: 16px; color: #9ca3af;"><i class="fas fa-info-circle" style="font-size: 48px;"></i></div><h4 style="color: #374151; margin-bottom: 8px; font-size: 18px; font-weight: 600;">No Evaluation Data Available</h4><p style="font-size: 14px;">This student hasn\'t completed their self-assessment yet, so there are no questions or ratings to display.</p><p style="font-size: 13px; color: #9ca3af; margin-top: 8px;">Please check back after the student completes their evaluation forms.</p></div>');
            }
        }).fail(function(xhr, status, error) {
            // Handle AJAX error
            $('.admin-eval-loading').hide();
            $('.admin-eval-content').show();
            var $main = $('.admin-eval-content');
            $main.html('<div class="admin-eval-error" style="padding: 60px 20px; text-align: center; color: #dc2626;"><div style="margin-bottom: 16px; color: #ef4444;"><i class="fas fa-exclamation-triangle" style="font-size: 48px;"></i></div><h4 style="color: #dc2626; margin-bottom: 8px; font-size: 18px; font-weight: 600;">Error Loading Evaluation Data</h4><p style="font-size: 14px;">Unable to load evaluation information for this student.</p><p style="font-size: 13px; color: #9ca3af; margin-top: 8px;">Please try refreshing the page or contact your administrator if the problem persists.</p><button onclick="location.reload()" style="margin-top: 16px; padding: 8px 16px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">Refresh Page</button></div>');
        });
    });
        // Save Evaluation button handler
        $(document).on('click', '.admin-eval-save-btn', function() {
            var $main = $('.admin-eval-content');
            var studentId = $('.admin-eval-student.active').data('student-id');
            var $saveBtn = $(this);
            if(!studentId) {
                alert('No student selected.');
                return;
            }
            // Collect ratings per category/question
            var ratings = [];
            $main.find('.admin-eval-category').each(function() {
                var category = $(this).find('h3').text().replace('COMPETENCY ON ','').trim();
                $(this).find('table.admin-eval-table tbody tr').each(function(idx) {
                    var questionText = $(this).find('td').eq(0).text();
                    var selfRating = $(this).find('td.self-rating-col').text();
                    var adminRating = $(this).find('input[type=radio]:checked').val() || null;
                    var questionId = $(this).attr('data-question-id');
                    ratings.push({
                        category: category,
                        question_id: questionId,
                        question_text: questionText,
                        self_rating: selfRating,
                        admin_rating: adminRating
                    });
                });
            });

            // Analysis Button Click Handler
            $('.analysis-btn').on('click', function() {
                var studentId = $(this).data('student-id');
                if (!studentId) return;
                
                let html = '<p>Loading prediction analysis...</p>';
                if ($('#analysisModal').length === 0) {
                    $('body').append('<div id="analysisModal" class="main-dashboard-modal-bg" style="display:none;"><div class="main-dashboard-modal-content"><div class="main-dashboard-modal-header"><h2 class="main-dashboard-modal-title">Prediction Analysis</h2><button class="main-dashboard-modal-close" id="closeAnalysisModal"><i class="fas fa-times"></i></button></div><div class="main-dashboard-modal-body" id="analysisModalContent"></div></div></div>');
                }
                $('#analysisModalContent').html(html);
                $('#analysisModal').fadeIn();

                $.ajax({
                    url: 'ajaxhandler/predictionAjax.php',
                    type: 'POST',
                    data: {
                        action: 'getPreAssessment',
                        student_id: studentId
                    },
                    success: function(analysis) {
                        let html = '';
                        if (!analysis || analysis.error || !analysis.placement) {
                            html = `<div class="prediction-card">
                                <div class="no-prediction-message">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No placement prediction available yet.</p>
                                    <p class="prediction-hint">Please note: Predictions are generated when a student uses the Prediction tab in their main dashboard. Once a student accesses that tab, their prediction will be available here.</p>
                                </div>
                            </div>`;
                        } else {
                            html = `<div class="prediction-card">
                                <h3 class="prediction-title">
                                    Predicted OJT Placement:
                                    <span class="prediction-badge">${analysis.placement}</span>
                                </h3>
                                <div class="prediction-reasoning">
                                    <b>Reasoning:</b>
                                    <p>${analysis.reasoning}</p>
                                </div>
                                <div class="prediction-probability">
                                    <b>Probability Explanation:</b>
                                    <p>
                                        The model is <span class="confidence-high">${analysis.confidence || ''}</span> that <span class="highlight">${analysis.placement}</span> is the best placement for this student. ${analysis.prob_explanation || ''}
                                    </p>
                                </div>
                                <div class="probability-bars">
                                    ${Object.entries(analysis.probabilities || {}).map(([k, v]) => {
                                        let color = '#3867d6';
                                        if (k === 'Systems Development') color = '#20bf6b';
                                        else if (k === 'Business Operations') color = '#f7b731';
                                        else if (k === 'OJT Placement') color = '#eb3b5a';
                                        else if (k === 'Research') color = '#3867d6';
                                        return `<div class="probability-row"><span>${k}</span><div class="bar" style="width:${v}%;background:${color};"></div><span>${v}%</span></div>`;
                                    }).join('')}
                                </div>
                                <div class="prediction-probability">
                                    <b>Probability Explanation:</b>
                                    <p>${analysis.prob_explanation}</p>
                                </div>
                            </div>`;
                        }
                        $('#analysisModalContent').html(html);
                    },
                    error: function() {
                        $('#analysisModalContent').html('<p>Error loading prediction analysis.</p>');
                    }
                });
            });

            // Analysis Modal Close Button Handler
            $(document).on('click', '#closeAnalysisModal', function() {
                $('#analysisModal').fadeOut();
            });

            console.log('DEBUG: Ratings to send:', ratings);
            
            // Show loading state on button
            var originalText = $saveBtn.text();
            $saveBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
            
            // Get comment
            var comment = $main.find('.admin-eval-comment-input').val();
            // Send AJAX to save
            $.ajax({
                url: 'ajaxhandler/saveAdminEvaluationAjax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    student_id: studentId,
                    ratings: JSON.stringify(ratings),
                    comment: comment
                },
                success: function(resp) {
                    if(resp.status==='success') {
                        // Show success feedback
                        $saveBtn.html('<i class="fas fa-check"></i> Saved Successfully!').css('background', 'linear-gradient(135deg, #10b981, #059669)');
                        setTimeout(function() {
                            $saveBtn.text(originalText).prop('disabled', false).css('background', '');
                        }, 2000);
                    } else {
                        alert('Error saving evaluation: '+resp.message);
                        $saveBtn.text(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX error: '+error);
                    $saveBtn.text(originalText).prop('disabled', false);
                }
            });
        });
    // Evaluation tab student search filter
    $(document).on('input', '.admin-eval-search', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.admin-eval-student-list .admin-eval-student').each(function() {
            var name = $(this).text().toLowerCase();
            if (name.indexOf(searchTerm) !== -1 || searchTerm === '') {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    const closeBtn = document.getElementById('closeReturnModal');
    const cancelBtn = document.getElementById('cancelReturn');
    const confirmBtn = document.getElementById('confirmReturn');

    if (closeBtn) closeBtn.onclick = closeReturnModal;
    if (cancelBtn) cancelBtn.onclick = closeReturnModal;
    if (confirmBtn) confirmBtn.onclick = submitReturnReport;
});

function closeReturnModal() {
    const modal = document.getElementById('returnReportModal');
    if (modal) {
        modal.style.display = 'none';
        $('#returnReason').val('');
        currentReportId = null;
    }
}

function submitReturnReport() {
    const reason = document.getElementById('returnReason').value.trim();
    if (!reason) {
        alert('Please provide a return reason');
        return;
    }

    if (!currentReportId) {
        alert('No report selected');
        return;
    }

    // Prevent duplicate submissions
    if (window.isSubmittingReturn) {
        return;
    }
    window.isSubmittingReturn = true;

    $.ajax({
        url: URLs.reports,
        type: 'POST',
        data: {
            action: 'returnReport',
            reportId: currentReportId,
            returnReason: reason
        },
            success: function(response) {
                let html = '';
                const analysis = response;
                if (!analysis || analysis.error || !analysis.placement) {
                    html = `<div class="prediction-card">
                        <div class="no-prediction-message">
                            <i class="fas fa-info-circle"></i>
                            <p>No placement prediction available yet.</p>
                            <p class="prediction-hint">Please note: Predictions are generated by the coordinator in the main dashboard. The prediction will be available here once it has been completed.</p>
                        </div>
                    </div>`;
                } else {
                    function highlightGrades(text) {
                        return text.replace(/([A-Z]{2,} \d{3}: \d{1,3}\.\d+)/g, '<span class="subject-list">$1</span>');
                    }
                    let reasoningLine = '';
                    if (analysis.subjects && analysis.subjects.trim() !== '') {
                        reasoningLine = `<p>Recommended for <span class="highlight">${analysis.placement}</span> due to strong performance in: <span class="subject-list">${analysis.subjects}</span>.</p>`;
                    }
                    html = `<div class="prediction-card">
                        <h3 class="prediction-title">
                            Predicted OJT Placement:
                            <span class="prediction-badge">${analysis.placement}</span>
                        </h3>
                        <div class="prediction-reasoning">
                            <b>Reasoning:</b>
                            ${reasoningLine}
                            <p>
                                ${highlightGrades((analysis.reasoning || '').replace(/\s*\(average: [^)]+\)/g, ''))}
                            </p>
                        </div>
                        <div class="prediction-probability">
                            <b>Probability Explanation:</b>
                            <p>
                                The model is <span class="confidence-high">${analysis.confidence || ''}</span> that <span class="highlight">${analysis.placement}</span> is the best placement for this student. ${analysis.prob_explanation || ''}
                            </p>
                        </div>
                        <div class="probability-bars">
                            ${Object.entries(analysis.probabilities || {}).map(([k, v]) => {
                                let color = '#3867d6';
                                if (k === 'Systems Development') color = '#20bf6b';
                                else if (k === 'Business Operations') color = '#f7b731';
                                else if (k === 'OJT Placement') color = '#eb3b5a';
                                else if (k === 'Research') color = '#3867d6';
                                return `<div class="probability-row"><span>${k}</span><div class="bar" style="width:${v}%;background:${color};"></div><span>${v}%</span></div>`;
                            }).join('')}
                        </div>
                    </div>`;
                }
                $('#analysisModalContent').html(html);
            },
            error: function() {
                $('#analysisModalContent').html('<p>Error loading prediction analysis.</p>');
            }
        });


    $(document).on('click', '#closeAnalysisModal', function() {
        $('#analysisModal').fadeOut();
    });

    $.ajax({
        url: URLs.reports,
        type: 'POST',
        data: {
            action: 'returnReport',
            reportId: currentReportId,
            returnReason: reason
        },
        success: function(response) {
            window.isSubmittingReturn = false;
            if (response.status === 'success') {
                // First close the modal
                closeReturnModal();
                // Then show the alert
                alert('Report returned successfully! The student can now edit it.');
                
                // Reset any loading states
                isLoadingReports = false;
                window.isRefreshing = false;
                
                // Clear any existing timeouts
                if (window.refreshTimeout) clearTimeout(window.refreshTimeout);
                if (window.loadingTimeout) clearTimeout(window.loadingTimeout);
                if (window.reportsTimeout) clearTimeout(window.reportsTimeout);
                
                // Set flag to indicate this refresh is after a return
                window.isAfterReturn = true;
                
                // Then refresh the reports with current filters
                const studentId = $('#studentFilter').val();
                // Get the current week number from the preview
                const weekNumber = parseInt($('#previewWeekNumber').text());
                if (weekNumber) {
                    loadWeeklyReports(studentId, weekNumber);
                }
            } else {
                closeReturnModal();
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            window.isSubmittingReturn = false;
            console.error('Error:', error);
            alert('An error occurred while returning the report.');
            closeReturnModal();
        }
    });
}

// Function to display admin profile details
function displayAdminProfileDetails(data) {
    // Check if profile picture exists and use proper URL handling
    const profilePicture = data.PROFILE ? getImageUrl(data.PROFILE) : null;
    
    const html = `
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    ${profilePicture ? 
                        `<img src="${profilePicture}" alt="Profile Picture" class="avatar-placeholder">` :
                        `<div class="avatar-placeholder">
                            <i class="fas fa-user-shield"></i>
                         </div>`
                    }
                </div>
                <h2>${data.NAME}</h2>
                <p class="profile-subtitle">Admin Profile</p>
            </div>
            
            <div class="profile-details">
                <div class="detail-row">
                    <span class="detail-label">Admin ID:</span>
                    <span class="detail-value">${data.COORDINATOR_ID || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Full Name:</span>
                    <span class="detail-value">${data.NAME || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${data.EMAIL || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Contact Number:</span>
                    <span class="detail-value">${data.CONTACT_NUMBER || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Department:</span>
                    <span class="detail-value">${data.DEPARTMENT || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Assigned HTE:</span>
                    <span class="detail-value">${data.HTE_NAME || 'No HTE Assigned'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Role:</span>
                    <span class="detail-value">ADMIN</span>
                </div>
            </div>
            
            <div class="profile-actions">
                <button type="button" class="btn-edit">Edit Profile</button>
                <button type="button" class="btn-change-password">Change Password</button>
            </div>
        </div>
    `;
    
    $('#profileModalContent').html(html);
}

// Function to show Change Password modal
function showChangePasswordModal() {
    const passwordContent = `
        <div class="modal" id="changePasswordModal" style="display: flex;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Change Password</h2>
                    <button class="modal-close" id="closeChangePasswordModal">&times;</button>
                </div>
                <div class="modal-body">
<form id="changePasswordForm" class="change-password-form">
    <h3>Update Your Password</h3>
    <div class="form-group">
        <label for="currentPassword">Current Password:</label>
        <input type="password" id="currentPassword" name="currentPassword" required>
    </div>
    <div class="form-group">
        <label for="newPassword">New Password:</label>
        <input type="password" id="newPassword" name="newPassword" required>
    </div>
    <div class="form-group">
        <label for="confirmPassword">Confirm New Password:</label>
        <input type="password" id="confirmPassword" name="confirmPassword" required>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn-submit">Change Password</button>
        <button type="button" class="btn-cancel" id="closeChangePasswordModalCancel">Cancel</button>
    </div>
</form>
                </div>
            </div>
        </div>
    `;
    // Append modal to body
    $('body').append(passwordContent);

    // Close modal event
    $('#closeChangePasswordModal, #closeChangePasswordModalCancel').on('click', function() {
        $('#changePasswordModal').remove();
    });

    // Submit form event
    $('#changePasswordForm').on('submit', function(e) {
        e.preventDefault();
        const currentPassword = $('#currentPassword').val().trim();
        const newPassword = $('#newPassword').val().trim();
        const confirmPassword = $('#confirmPassword').val().trim();

        if (!currentPassword || !newPassword || !confirmPassword) {
            alert('Please fill in all fields.');
            return;
        }

        if (newPassword !== confirmPassword) {
            alert('New password and confirmation do not match.');
            return;
        }

        // Send AJAX request to update password
        $.ajax({
            url: URLs.adminDashboard,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'updatePassword',
                adminId: $('#userName').data('admin-id'),
                currentPassword: currentPassword,
                newPassword: newPassword,
                confirmPassword: confirmPassword
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert('Password changed successfully.');
                    $('#changePasswordModal').remove();
                } else {
                    alert('Error changing password: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                alert('An error occurred while changing password.');
            }
        });
    });
}

// MOA Status Calculation Utility (reused from mainDashboard.js)
function calculateMOAStatus(startDate, endDate) {
    if (!startDate || !endDate) {
        return { status: 'No MOA', color: '#6c757d' };
    }
    
    const today = new Date();
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    // Remove time part for accurate comparison
    today.setHours(0, 0, 0, 0);
    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);
    
    if (today < start) {
        return { status: 'Future', color: '#17a2b8' };
    } else if (today >= start && today <= end) {
        // Check if expiring within 30 days
        const daysUntilExpiry = Math.ceil((end - today) / (1000 * 60 * 60 * 24));
        if (daysUntilExpiry <= 30) {
            return { status: 'Expiring Soon', color: '#ffc107' };
        }
        return { status: 'Active', color: '#28a745' };
    } else {
        return { status: 'Expired', color: '#dc3545' };
    }
}

// Load admin's assigned company MOA data for admin control panel
function loadCompaniesMOAData() {
    // Get admin ID from the userName data attribute (try multiple methods)
    let adminId = $('#userName').data('admin-id');
    
    // Fallback: try direct attribute access
    if (!adminId) {
        adminId = $('#userName').attr('data-admin-id');
    }
    
    // Another fallback: try finding any element with data-admin-id
    if (!adminId) {
        adminId = $('[data-admin-id]').first().data('admin-id') || $('[data-admin-id]').first().attr('data-admin-id');
    }
    
    // Debug: Log what we're getting
    console.log('loadCompaniesMOAData - adminId:', adminId);
    console.log('loadCompaniesMOAData - userName element:', $('#userName'));
    console.log('loadCompaniesMOAData - userName exists:', $('#userName').length);
    console.log('loadCompaniesMOAData - userName data-admin-id attr:', $('#userName').attr('data-admin-id'));
    
    if (!adminId) {
        $('#companiesMOATableBody').html(`
            <tr>
                <td colspan="6">
                    <div class="error-state" style="padding: 60px 20px; text-align: center; color: #dc2626;">
                        <div style="margin-bottom: 20px; color: #ef4444;">
                            <i class="fas fa-user-times" style="font-size: 48px;"></i>
                        </div>
                        <h4 style="color: #dc2626; margin-bottom: 8px; font-size: 18px; font-weight: 600;">Authentication Error</h4>
                        <p style="font-size: 14px; margin-bottom: 8px;">Admin ID not found in session data.</p>
                        <p style="font-size: 13px; color: #9ca3af; margin-bottom: 16px;">Please refresh the page or log in again.</p>
                        <button onclick="location.reload()" style="padding: 8px 16px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-redo"></i> Refresh Page
                        </button>
                    </div>
                </td>
            </tr>
        `);
        return;
    }
    
    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'getAllHTEList', adminId: adminId }, // Get admin's assigned HTE
        beforeSend: function() {
            $('#companiesMOATableBody').html(`
                <tr class="loading-row">
                    <td colspan="6">
                        <div class="loading-state">
                            <div class="loading-spinner"></div>
                            <div class="loading-text">
                                <h4>Loading Company Information</h4>
                                <p>Retrieving your assigned company details...</p>
                            </div>
                        </div>
                    </td>
                </tr>
            `);
        },
        success: function(response) {
            if (response.success && response.htes && Array.isArray(response.htes)) {
                if (response.htes.length > 0) {
                    renderCompaniesMOATable(response.htes);
                    updateMOAStatistics(response.htes);
                } else {
                    $('#companiesMOATableBody').html(`
                        <tr>
                            <td colspan="6">
                                <div class="empty-state" style="padding: 60px 20px; text-align: center; color: #6b7280;">
                                    <div style="margin-bottom: 20px; color: #9ca3af;">
                                        <i class="fas fa-building" style="font-size: 48px;"></i>
                                    </div>
                                    <h4 style="color: #374151; margin-bottom: 8px; font-size: 18px; font-weight: 600;">No Company Assigned</h4>
                                    <p style="font-size: 14px; margin-bottom: 8px;">You don't have any company assigned to your account yet.</p>
                                    <p style="font-size: 13px; color: #9ca3af;">Please contact your administrator to assign a company.</p>
                                </div>
                            </td>
                        </tr>
                    `);
                }
            } else {
                $('#companiesMOATableBody').html(`
                    <tr>
                        <td colspan="6">
                            <div class="error-state" style="padding: 60px 20px; text-align: center; color: #dc2626;">
                                <div style="margin-bottom: 20px; color: #ef4444;">
                                    <i class="fas fa-exclamation-triangle" style="font-size: 48px;"></i>
                                </div>
                                <h4 style="color: #dc2626; margin-bottom: 8px; font-size: 18px; font-weight: 600;">Error Loading Company Data</h4>
                                <p style="font-size: 14px; margin-bottom: 16px;">Unable to load company information: ${response.message || 'Unknown error'}</p>
                                <button onclick="loadCompaniesMOAData()" style="padding: 8px 16px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">
                                    <i class="fas fa-redo"></i> Try Again
                                </button>
                            </div>
                        </td>
                    </tr>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading companies:', error);
            $('#companiesMOATableBody').html(`
                <tr>
                    <td colspan="6">
                        <div class="error-state" style="padding: 60px 20px; text-align: center; color: #dc2626;">
                            <div style="margin-bottom: 20px; color: #ef4444;">
                                <i class="fas fa-wifi" style="font-size: 48px;"></i>
                            </div>
                            <h4 style="color: #dc2626; margin-bottom: 8px; font-size: 18px; font-weight: 600;">Connection Error</h4>
                            <p style="font-size: 14px; margin-bottom: 16px;">Unable to connect to the server. Please check your internet connection.</p>
                            <button onclick="loadCompaniesMOAData()" style="padding: 8px 16px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">
                                <i class="fas fa-redo"></i> Retry Connection
                            </button>
                        </div>
                    </td>
                </tr>
            `);
        }
    });
}

// Render companies MOA table
function renderCompaniesMOATable(companies) {
    let html = '';
    
    companies.forEach(function(company) {
        const moaStatus = calculateMOAStatus(company.MOA_START_DATE, company.MOA_END_DATE);
        
        // Format dates
        let moaDatesText = '-';
        if (company.MOA_START_DATE && company.MOA_END_DATE) {
            const startDate = new Date(company.MOA_START_DATE).toLocaleDateString();
            const endDate = new Date(company.MOA_END_DATE).toLocaleDateString();
            moaDatesText = `${startDate} - ${endDate}`;
        }
        
        // MOA view button
        let moaViewBtn = '';
        if (company.MOA_FILE_URL) {
            moaViewBtn = `<button class="view-moa-btn-admin" data-moa="${company.MOA_FILE_URL}" 
                style="padding: 4px 8px; background: #17a2b8; color: white; border: none; border-radius: 3px; cursor: pointer; margin-left: 5px;">
                <i class="fas fa-file-pdf"></i> View MOA
            </button>`;
        }
        
        html += `
            <tr data-company-id="${company.HTE_ID}">
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                            ${(company.NAME || 'N').charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div style="font-weight: 600; color: #1f2937; margin-bottom: 2px;">${company.NAME || 'No Name'}</div>
                            <div style="font-size: 12px; color: #6b7280;">${company.HTE_ID}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="padding: 4px 12px; background: #f3f4f6; border-radius: 20px; font-size: 13px; color: #374151;">
                        ${company.INDUSTRY || 'Not specified'}
                    </span>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-user" style="color: #9ca3af; font-size: 14px;"></i>
                        <span>${company.CONTACT_PERSON || 'Not assigned'}</span>
                    </div>
                </td>
                <td>
                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                        background-color: ${moaStatus.color}20; color: ${moaStatus.color}; border: 1px solid ${moaStatus.color}30;">
                        <i class="fas ${getStatusIcon(moaStatus.status)}"></i> ${moaStatus.status}
                    </span>
                </td>
                <td>
                    <div style="font-size: 13px; color: #374151;">
                        ${moaDatesText}
                    </div>
                </td>
                <td>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        ${moaViewBtn}
                        <button class="company-info-btn" data-company='${JSON.stringify(company)}' 
                            style="padding: 6px 12px; background: #f3f4f6; color: #374151; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    $('#companiesMOATableBody').html(html);
}

// Note: Company filter not needed for admin - they only see their assigned company

// Update MOA statistics
function updateMOAStatistics(companies) {
    let activeMOAs = 0;
    let expiringSoonMOAs = 0;
    let expiredMOAs = 0;
    let noMOACompanies = 0;
    
    companies.forEach(function(company) {
        const moaStatus = calculateMOAStatus(company.MOA_START_DATE, company.MOA_END_DATE);
        
        switch(moaStatus.status) {
            case 'Active':
                activeMOAs++;
                break;
            case 'Expiring Soon':
                expiringSoonMOAs++;
                break;
            case 'Expired':
                expiredMOAs++;
                break;
            case 'No MOA':
                noMOACompanies++;
                break;
        }
    });
    
    $('#activeMOAs').text(activeMOAs);
    $('#expiringSoonMOAs').text(expiringSoonMOAs);
    $('#expiredMOAs').text(expiredMOAs);
    $('#noMOACompanies').text(noMOACompanies);
}

// Event handlers for admin control panel
$(document).ready(function() {
    // Refresh companies button
    $(document).on('click', '#refreshCompaniesBtn', function() {
        loadCompaniesMOAData();
    });
    
    // No company filter needed - admin only sees their assigned company
    
    // MOA view button (read-only for admin)
    $(document).on('click', '.view-moa-btn-admin', function() {
        const moaPath = $(this).data('moa');
        if (moaPath) {
            window.open(moaPath, '_blank');
        }
    });
    
    // Company details modal
    $(document).on('click', '.company-info-btn', function() {
        const company = JSON.parse($(this).data('company'));
        showCompanyDetailsModal(company);
    });
});

// Helper function to get status icon
function getStatusIcon(status) {
    switch(status) {
        case 'Active': return 'fa-check-circle';
        case 'Expiring Soon': return 'fa-clock';
        case 'Expired': return 'fa-exclamation-triangle';
        case 'No MOA': return 'fa-times-circle';
        default: return 'fa-question-circle';
    }
}

// Load system information
function loadSystemInformation() {
    // Get admin info from username element
    const adminName = $('#userName').text() || 'Unknown Admin';
    let adminId = $('#userName').data('admin-id');
    
    // Fallback: try direct attribute access
    if (!adminId) {
        adminId = $('#userName').attr('data-admin-id');
    }
    
    // Another fallback: try finding any element with data-admin-id
    if (!adminId) {
        adminId = $('[data-admin-id]').first().data('admin-id') || $('[data-admin-id]').first().attr('data-admin-id');
    }
    
    // Debug: Log what we're getting
    console.log('loadSystemInformation - adminId:', adminId);
    console.log('loadSystemInformation - adminName:', adminName);
    
    // Update admin role info
    $('#adminRoleInfo').text('OJT Coordinator - ' + adminName);
    
    // Update last login (current date as placeholder)
    const currentDate = new Date().toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    $('#lastLoginInfo').text(currentDate);
    
    // Load managed students count
    if (adminId) {
        $.ajax({
            url: 'ajaxhandler/attendanceAJAX.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'getStudentCount', adminId: adminId },
            success: function(response) {
                if (response.success) {
                    $('#managedStudentsInfo').text(response.count + ' students');
                } else {
                    $('#managedStudentsInfo').text('Unable to load');
                }
            },
            error: function() {
                $('#managedStudentsInfo').text('Unable to load');
            }
        });
    } else {
        $('#managedStudentsInfo').text('Unable to load');
    }
}

// Show company details modal
function showCompanyDetailsModal(company) {
    const moaStatus = calculateMOAStatus(company.MOA_START_DATE, company.MOA_END_DATE);
    
    let moaDatesHtml = 'Not available';
    if (company.MOA_START_DATE && company.MOA_END_DATE) {
        const startDate = new Date(company.MOA_START_DATE).toLocaleDateString();
        const endDate = new Date(company.MOA_END_DATE).toLocaleDateString();
        moaDatesHtml = `
            <div style="margin-bottom: 8px;"><strong>Start Date:</strong> ${startDate}</div>
            <div><strong>End Date:</strong> ${endDate}</div>
        `;
    }
    
    const modalContent = `
        <div style="padding: 20px;">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">
                    ${(company.NAME || 'N').charAt(0).toUpperCase()}
                </div>
                <div>
                    <h3 style="margin: 0; color: #1f2937;">${company.NAME || 'No Name'}</h3>
                    <p style="margin: 4px 0 0 0; color: #6b7280;">Company ID: ${company.HTE_ID}</p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px; border-left: 4px solid #3b82f6;">
                    <h4 style="margin: 0 0 8px 0; color: #1f2937;"><i class="fas fa-industry"></i> Industry</h4>
                    <p style="margin: 0; color: #6b7280;">${company.INDUSTRY || 'Not specified'}</p>
                </div>
                
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px; border-left: 4px solid #10b981;">
                    <h4 style="margin: 0 0 8px 0; color: #1f2937;"><i class="fas fa-user"></i> Contact Person</h4>
                    <p style="margin: 0; color: #6b7280;">${company.CONTACT_PERSON || 'Not assigned'}</p>
                </div>
                
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px; border-left: 4px solid ${moaStatus.color};">
                    <h4 style="margin: 0 0 8px 0; color: #1f2937;"><i class="fas fa-file-contract"></i> MOA Status</h4>
                    <p style="margin: 0; color: ${moaStatus.color}; font-weight: 600;">${moaStatus.status}</p>
                </div>
                
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px; border-left: 4px solid #f59e0b;">
                    <h4 style="margin: 0 0 8px 0; color: #1f2937;"><i class="fas fa-calendar-alt"></i> Agreement Period</h4>
                    <div style="color: #6b7280; font-size: 14px;">${moaDatesHtml}</div>
                </div>
            </div>
            
            ${company.MOA_FILE_URL ? `
                <div style="margin-top: 24px; text-align: center;">
                    <button onclick="window.open('${company.MOA_FILE_URL}', '_blank')" 
                        style="padding: 12px 24px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-file-pdf"></i> View MOA Document
                    </button>
                </div>
            ` : ''}
        </div>
    `;
    
    // Create or update modal
    if ($('#companyDetailsModal').length === 0) {
        $('body').append(`
            <div id="companyDetailsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; backdrop-filter: blur(4px);">
                <div class="modal-dialog" style="position: relative; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    <div class="modal-header" style="padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; color: #1f2937;">Company Details</h2>
                        <button class="modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body" id="companyDetailsModalBody"></div>
                </div>
            </div>
        `);
        
        // Close modal handlers
        $('#companyDetailsModal .modal-close, #companyDetailsModal').on('click', function(e) {
            if (e.target === this) {
                $('#companyDetailsModal').fadeOut();
            }
        });
    }
    
    $('#companyDetailsModalBody').html(modalContent);
    $('#companyDetailsModal').fadeIn();
}

// Question Approvals Management
function initQuestionApprovals() {
    // Load students with questions when initializing
    loadStudentsWithQuestions();
    
    // Refresh button
    $('#refresh-questions').off('click').on('click', function() {
        loadStudentsWithQuestions();
    });
    
    // Student selection handler
    $('#student-selector').off('change').on('change', function() {
        const selectedStudentId = $(this).val();
        if (selectedStudentId) {
            loadStudentQuestions(selectedStudentId);
        } else {
            $('#questions-list-container').hide();
        }
    });
    
    // Bulk action handlers
    $('#bulk-approve-btn').off('click').on('click', function() {
        bulkApproveQuestions();
    });
    
    $('#bulk-reject-btn').off('click').on('click', function() {
        bulkRejectQuestions();
    });
}

function loadQuestionApprovalStats() {
    $.ajax({
        url: 'ajaxhandler/manageQuestionApprovalsAjax.php?action=getApprovalStats',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#pendingQuestionsCount').text(response.stats.pending);
                $('#approvedQuestionsCount').text(response.stats.approved);
                $('#rejectedQuestionsCount').text(response.stats.rejected);
            }
        },
        error: function() {
            console.error('Failed to load approval stats');
        }
    });
}

function loadStudentsWithQuestions() {
    $('#student-selector').html('<option value="">Loading students...</option>');
    $('#questions-list-container').hide();
    
    $.ajax({
        url: 'ajaxhandler/manageQuestionApprovalsAjax.php',
        type: 'POST',
        data: { action: 'getStudentsWithQuestions' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateStudentSelector(response.students);
            } else {
                const errorMsg = response.error.includes('HTE') 
                    ? 'You are not assigned to any HTE. Please contact system administrator.' 
                    : response.error;
                $('#student-selector').html(`<option value="">Error: ${errorMsg}</option>`);
            }
        },
        error: function() {
            $('#student-selector').html('<option value="">Failed to load students</option>');
        }
    });
}

function populateStudentSelector(students) {
    let html = '<option value="">Select a student to review their questions...</option>';
    
    if (students.length === 0) {
        html += '<option value="">No students with questions found</option>';
    } else {
        students.forEach(function(student) {
            const statusInfo = `(${student.pending_count} pending, ${student.approved_count} approved, ${student.rejected_count} rejected)`;
            html += `<option value="${student.INTERNS_ID}">${student.student_name} - ${student.question_count} questions ${statusInfo}</option>`;
        });
    }
    
    $('#student-selector').html(html);
}

function loadStudentQuestions(studentId) {
    $('#questions-list-container').show();
    $('#questions-list').html(`
        <div style="display: flex; align-items: center; justify-content: center; height: 200px; color: #6b7280;">
            <div style="text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <p>Loading student questions...</p>
            </div>
        </div>
    `);
    
    $.ajax({
        url: 'ajaxhandler/manageQuestionApprovalsAjax.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'getStudentQuestions',
            student_id: studentId
        }),
        success: function(response) {
            if (response.success) {
                displayQuestionsList(response.questions, response.questions[0]?.student_name || 'Unknown Student');
            } else {
                $('#questions-list').html(`
                    <div style="padding: 2rem; text-align: center; color: #ef4444;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>Error loading questions: ${response.error}</p>
                    </div>
                `);
            }
        },
        error: function() {
            $('#questions-list').html(`
                <div style="padding: 2rem; text-align: center; color: #ef4444;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>Failed to load questions. Please try again.</p>
                </div>
            `);
        }
    });
}

function displayQuestionsList(questions, studentName = null) {
    const container = studentName ? '#questions-list' : '#questionsList';
    
    if (questions.length === 0) {
        const message = studentName 
            ? `No questions found for ${studentName}.`
            : 'No questions pending approval at the moment.';
        
        $(container).html(`
            <div style="padding: 3rem; text-align: center; color: #6b7280;">
                <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; color: #6b7280;"></i>
                <h3 style="margin: 0 0 0.5rem 0; color: #374151;">${studentName ? 'No Questions' : 'All Caught Up!'}</h3>
                <p style="margin: 0;">${message}</p>
            </div>
        `);
        return;
    }
    
    // Show bulk action buttons if there are questions
    if (studentName) {
        const hasPendingQuestions = questions.some(q => q.approval_status === 'pending');
        if (hasPendingQuestions) {
            $('#bulk-actions').show();
        } else {
            $('#bulk-actions').hide();
        }
    }
    
    let html = '<div style="padding: 0;">';
    
    questions.forEach(function(question, index) {
        let statusBadge = '';
        switch(question.approval_status) {
            case 'rejected':
                statusBadge = '<span style="background: #fef2f2; color: #dc2626; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Rejected</span>';
                break;
            case 'approved':
                statusBadge = '<span style="background: #f0f9ff; color: #0284c7; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Approved</span>';
                break;
            default:
                statusBadge = '<span style="background: #fef3c7; color: #d97706; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Pending</span>';
        }
        
        // Add checkbox for pending questions when in student view
        const checkboxHtml = (studentName && question.approval_status === 'pending') 
            ? `<input type="checkbox" class="question-checkbox" data-question-id="${question.id}" style="margin-right: 0.5rem;">`
            : '';
            
        html += `
            <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; ${index === questions.length - 1 ? 'border-bottom: none;' : ''}">
                <div style="display: flex; justify-content: between; align-items: flex-start; margin-bottom: 1rem;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            ${checkboxHtml}
                            <h4 style="margin: 0; color: #374151; font-size: 1.1rem;">${studentName || question.student_name}</h4>
                            ${statusBadge}
                        </div>
                        <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
                            <strong>${question.category}</strong> - Question ${question.question_number} | 
                            Submitted: ${new Date(question.created_at).toLocaleDateString()}
                            ${question.approved_by ? ` | Approved by: Admin` : ''}
                            ${question.approval_date ? ` on ${new Date(question.approval_date).toLocaleDateString()}` : ''}
                            <br><small><i class="fas fa-info-circle"></i> This is a unique question created by this student for their post-assessment</small>
                        </p>
                    </div>
                </div>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="margin: 0; color: #374151; line-height: 1.5;">${question.question_text}</p>
                </div>
                
                ${question.rejection_reason ? `
                    <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 1rem;">
                        <p style="margin: 0; color: #7f1d1d; font-weight: 500;">Rejection Reason:</p>
                        <p style="margin: 0.25rem 0 0 0; color: #991b1b;">${question.rejection_reason}</p>
                    </div>
                ` : ''}
                
                ${question.approval_status === 'pending' ? `
                    <div style="display: flex; gap: 0.75rem;">
                        <button onclick="approveQuestion(${question.id})" style="background: #22c55e; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button onclick="showRejectModal(${question.id}, '${studentName || question.student_name}', '${question.category}')" style="background: #ef4444; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    html += '</div>';
    $(container).html(html);
}

function approveQuestion(questionId) {
    $.ajax({
        url: 'ajaxhandler/manageQuestionApprovalsAjax.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'approveQuestion',
            question_id: questionId
        }),
        success: function(response) {
            if (response.success) {
                // Refresh the data
                loadStudentsWithQuestions();
                
                // Reload current student questions if one is selected
                const selectedStudentId = $('#student-selector').val();
                if (selectedStudentId) {
                    loadStudentQuestions(selectedStudentId);
                }
                
                // Show success message
                alert('Question approved successfully!');
            } else {
                alert('Error approving question: ' + response.error);
            }
        },
        error: function() {
            alert('Error approving question. Please try again.');
        }
    });
}

function showRejectModal(questionId, studentName, category) {
    const modalHtml = `
        <div id="rejectQuestionModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
            <div style="background: white; padding: 2rem; border-radius: 0.5rem; width: 90%; max-width: 500px;">
                <h3 style="margin: 0 0 1rem 0; color: #374151;">Reject Question</h3>
                <p style="margin: 0 0 1rem 0; color: #6b7280;">Student: <strong>${studentName}</strong> | Category: <strong>${category}</strong></p>
                
                <label style="display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 500;">Rejection Reason:</label>
                <textarea id="rejectionReason" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; min-height: 100px; resize: vertical;" placeholder="Please provide a clear reason for rejection..."></textarea>
                
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button onclick="closeRejectModal()" style="background: #6b7280; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
                    <button onclick="submitRejection(${questionId})" style="background: #ef4444; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer;">Reject Question</button>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modalHtml);
}

function closeRejectModal() {
    $('#rejectQuestionModal').remove();
}

function submitRejection(questionId) {
    const reason = $('#rejectionReason').val().trim();
    if (!reason) {
        alert('Please provide a rejection reason.');
        return;
    }
    
    $.ajax({
        url: 'ajaxhandler/manageQuestionApprovalsAjax.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'rejectQuestion',
            question_id: questionId,
            rejection_reason: reason
        }),
        success: function(response) {
            if (response.success) {
                closeRejectModal();
                loadStudentsWithQuestions();
                
                // Reload current student questions if one is selected
                const selectedStudentId = $('#student-selector').val();
                if (selectedStudentId) {
                    loadStudentQuestions(selectedStudentId);
                }
                
                alert('Question rejected successfully!');
            } else {
                alert('Error rejecting question: ' + response.error);
            }
        },
        error: function() {
            alert('Error rejecting question. Please try again.');
        }
    });
}

function bulkApproveQuestions() {
    const checkedBoxes = $('.question-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one question to approve.');
        return;
    }
    
    if (!confirm(`Are you sure you want to approve ${checkedBoxes.length} selected questions?`)) {
        return;
    }
    
    const questionIds = [];
    checkedBoxes.each(function() {
        questionIds.push($(this).data('question-id'));
    });
    
    $.ajax({
        url: 'ajaxhandler/manageQuestionApprovalsAjax.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'bulkApproveQuestions',
            question_ids: questionIds
        }),
        success: function(response) {
            if (response.success) {
                loadStudentsWithQuestions();
                
                const selectedStudentId = $('#student-selector').val();
                if (selectedStudentId) {
                    loadStudentQuestions(selectedStudentId);
                }
                
                alert(`${questionIds.length} questions approved successfully!`);
            } else {
                alert('Error approving questions: ' + response.error);
            }
        },
        error: function() {
            alert('Error approving questions. Please try again.');
        }
    });
}

function bulkRejectQuestions() {
    const checkedBoxes = $('.question-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one question to reject.');
        return;
    }
    
    const reason = prompt(`Please provide a rejection reason for ${checkedBoxes.length} selected questions:`);
    if (!reason || !reason.trim()) {
        return;
    }
    
    const questionIds = [];
    checkedBoxes.each(function() {
        questionIds.push($(this).data('question-id'));
    });
    
    $.ajax({
        url: 'ajaxhandler/manageQuestionApprovalsAjax.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'bulkRejectQuestions',
            question_ids: questionIds,
            rejection_reason: reason.trim()
        }),
        success: function(response) {
            if (response.success) {
                loadStudentsWithQuestions();
                
                const selectedStudentId = $('#student-selector').val();
                if (selectedStudentId) {
                    loadStudentQuestions(selectedStudentId);
                }
                
                alert(`${questionIds.length} questions rejected successfully!`);
            } else {
                alert('Error rejecting questions: ' + response.error);
            }
        },
        error: function() {
            alert('Error rejecting questions. Please try again.');
        }
    });
}
