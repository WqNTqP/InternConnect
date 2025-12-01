// Suppress debug logging in production to protect user information
(function(){
    try {
        const noop = function(){};
        if (typeof console !== 'undefined') {
            console.log = noop;
            console.warn = noop;
        }
    } catch(e) {}
})();

// Data caching system to avoid redundant fetches
const dataCache = {
    sessions: null,
    htesBySession: {}, // Cache HTEs by session ID
    studentsByHte: {}, // Cache students by HTE ID and date
    evaluationData: {}, // Cache evaluation data by student ID
    predictionStudentsByCoordinator: {}, // Cache prediction students by coordinator
    lastUpdated: {
        sessions: null,
        htesBySession: {},
        studentsByHte: {},
        evaluationData: {},
        predictionStudentsByCoordinator: {}
    }
};

// Cache expiry times (in milliseconds)
const CACHE_EXPIRY = {
    sessions: 5 * 60 * 1000, // 5 minutes
    htes: 3 * 60 * 1000,     // 3 minutes  
    students: 1 * 60 * 1000,   // 1 minute
    evaluation: 2 * 60 * 1000,  // 2 minutes for evaluation data
    prediction: 2 * 60 * 1000   // 2 minutes for prediction student list
};

// Check if cached data is still valid
function isCacheValid(cacheType, subKey = null) {
    const now = Date.now();
    
    if (cacheType === 'sessions') {
        const lastUpdated = dataCache.lastUpdated.sessions;
        return lastUpdated && (now - lastUpdated < CACHE_EXPIRY.sessions);
    } else if (cacheType === 'hte' && subKey) {
        const lastUpdated = dataCache.lastUpdated.htesBySession[subKey];
        return lastUpdated && (now - lastUpdated < CACHE_EXPIRY.htes);
    } else if (cacheType === 'student' && subKey) {
        const lastUpdated = dataCache.lastUpdated.studentsByHte[subKey];
        return lastUpdated && (now - lastUpdated < CACHE_EXPIRY.students);
    } else if (cacheType === 'evaluation' && subKey) {
        const lastUpdated = dataCache.lastUpdated.evaluationData[subKey];
        return lastUpdated && (now - lastUpdated < CACHE_EXPIRY.evaluation);
    } else if (cacheType === 'prediction' && subKey) {
        const lastUpdated = dataCache.lastUpdated.predictionStudentsByCoordinator[subKey];
        return lastUpdated && (now - lastUpdated < CACHE_EXPIRY.prediction);
    }
    
    return false;
}

// MOA Status Calculation Utility
function calculateMOAStatus(startDate, endDate) {
    if (!startDate || !endDate) {
        return { status: 'N/A', class: 'text-gray-500', icon: '' };
    }
    
    const today = new Date();
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    today.setHours(0, 0, 0, 0);
    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);
    
    if (today > end) {
        return { 
            status: 'Expired', 
            class: 'px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs',
            icon: '🔴'
        };
    } else if (today >= start && today <= end) {
        return { 
            status: 'Active', 
            class: 'px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs',
            icon: '🟢'
        };
    } else {
        return { 
            status: 'Future', 
            class: 'px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs',
            icon: '⏳'
        };
    }
}

// Dynamic base URL function to handle different environments
function getBaseUrl() {
    const protocol = window.location.protocol;
    const host = window.location.host;
    const pathname = window.location.pathname;
    
    // Determine if we're in local development or live deployment
    const isLocal = host.includes('localhost') || host.includes('127.0.0.1');
    
    let basePath;
    if (isLocal) {
        // Local development: extract subdirectory (e.g., "/InternConnect/")
        const pathArray = pathname.split('/');
        basePath = pathArray.length > 1 && pathArray[1] ? '/' + pathArray[1] + '/' : '/';
    } else {
        // Live deployment: app is at root level
        basePath = '/';
    }
    
    const fullUrl = protocol + '//' + host + basePath;
    
    // Debug logging to see what URL is being generated
    console.log('getBaseUrl() returning:', fullUrl);
    
    return fullUrl;
}

// Helper function to get proper image URL (Cloudinary, absolute, or local)
function getImageUrl(filename) {
    if (!filename) return '';
    const f = String(filename).trim();
    if (/^https?:\/\//i.test(f)) return f;                    // Full URL
    if (f.startsWith('//')) return window.location.protocol + f; // Protocol-relative
    if (f.startsWith('/')) return window.location.protocol + '//' + window.location.host + f; // Absolute path
    if (f.includes('uploads/') || f.includes('/')) return getBaseUrl() + f; // Relative path
    return getBaseUrl() + 'uploads/reports/' + f;               // Bare filename fallback
}

// Fetch and render coordinator-specific companies (HTEs) in the companies table
function loadAllCompaniesData() {
    let cdrid = $("#hiddencdrid").val();
    
    if (!cdrid) {
        // Show error state
        $('#companiesLoadingState').addClass('hidden');
        $('#companiesContent').addClass('hidden');
        $('#companiesErrorState').removeClass('hidden');
        return;
    }

    // Check cache first
    const cacheKey = `allCompanies_${cdrid}`;
    if (isCacheValid('hte', cacheKey) && dataCache.htesBySession[cacheKey]) {
        console.log("Using cached companies data for coordinator:", cdrid);
        const cachedData = dataCache.htesBySession[cacheKey];
        renderAllCompaniesData(cachedData);
        return;
    }

    // Show loading state
    $('#companiesLoadingState').removeClass('hidden');
    $('#companiesContent').addClass('hidden');
    $('#companiesErrorState').addClass('hidden');

    // Track start time for minimum loading duration
    const loadStartTime = Date.now();
    const minLoadingDuration = 800; // Minimum 800ms to show spinner

    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'getHTEList', cdrid: cdrid },
        success: function(response) {
            // Cache the data
            const cacheKey = `allCompanies_${cdrid}`;
            dataCache.htesBySession[cacheKey] = response;
            dataCache.lastUpdated.htesBySession[cacheKey] = Date.now();
            
            // Calculate remaining time to show spinner
            const elapsedTime = Date.now() - loadStartTime;
            const remainingTime = Math.max(0, minLoadingDuration - elapsedTime);
            
            setTimeout(function() {
                renderAllCompaniesData(response);
            }, remainingTime);
        },
        error: function() {
            // Calculate remaining time to show spinner for error case too
            const elapsedTime = Date.now() - loadStartTime;
            const remainingTime = Math.max(0, minLoadingDuration - elapsedTime);
            
            setTimeout(function() {
                // Show error state
                $('#companiesLoadingState').addClass('hidden');
                $('#companiesContent').addClass('hidden');
                $('#companiesErrorState').removeClass('hidden');
            }, remainingTime);
        }
    });
}

// Render all companies data (extracted for reuse with caching)
function renderAllCompaniesData(response) {
    // Hide loading state, show content
    $('#companiesLoadingState').addClass('hidden');
    $('#companiesErrorState').addClass('hidden');
    $('#companiesContent').removeClass('hidden');
    
    if (response.success && response.htes && Array.isArray(response.htes)) {
        if (response.htes.length > 0) {
            renderCompaniesList(response.htes);
        } else {
            $('#allCompaniesTableBody').html('<tr><td colspan="7" class="text-center text-gray-500 py-6">No companies assigned to you.</td></tr>');
        }
    } else {
        $('#allCompaniesTableBody').html('<tr><td colspan="7" class="text-center text-gray-500 py-6">No companies found.</td></tr>');
    }
}

// Render companies in the companies table
function renderCompaniesList(companies) {
    let html = '';
    companies.forEach(function(company) {
        // Calculate MOA status using utility function
        const moaStatus = calculateMOAStatus(company.moa_start_date, company.moa_end_date);
        let moaStatusHtml = moaStatus.status === 'N/A' ? 'N/A' : 
            `<span class="${moaStatus.class}">${moaStatus.icon} ${moaStatus.status}</span>`;
        
        // Add MOA view button if file exists
        let moaViewBtn = '';
        if (company.moa_file_url) {
            moaViewBtn = ` <button class='view-moa-btn bg-purple-500 hover:bg-purple-600 text-white px-2 py-1 rounded text-xs ml-1' data-moa='${company.moa_file_url}' title='View MOA'>📄</button>`;
        }
        
        // Action buttons
        let actionHtml = `
            <div class="flex gap-1">
                <button class='edit-hte-btn bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs' data-hteid='${company.HTE_ID}'>Edit</button>
                <button class='update-logo-btn bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs' data-hteid='${company.HTE_ID}'>Logo</button>${moaViewBtn}
            </div>
        `;
        
            html += `<tr>
                <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-900\">${company.NAME || '-'}<\/td>
                <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${company.INDUSTRY || '-'}<\/td>
                <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${company.ADDRESS || '-'}<\/td>
                <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${company.CONTACT_PERSON || '-'}<\/td>
                <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${company.CONTACT_NUMBER || '-'}<\/td>
                <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${moaStatusHtml}<\/td>
                <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${actionHtml}<\/td>
            <\/tr>`;
    });
    $('#allCompaniesTableBody').html(html);
}

// Handle MOA view button click (direct PDF viewing)
$(document).on('click', '.view-moa-btn', function() {
    const moaPath = $(this).data('moa');
    if (moaPath) {
        window.open(moaPath, '_blank');
    }
});
// Show All Companies container and hide others
$(document).on('click', '#btnViewAllCompanies', function(e) {
    e.preventDefault();
    // Hide all other form containers
    $('#studentFormContainer').hide();
    $('#addHTEFormContainer').hide();
    $('#allStudentsContainer').hide();
    $('#controlIntroContainer').hide();
    $('#deleteHTEFormContainer').hide();
    $('#deleteSessionFormContainer').hide();
    $('#deleteStudentFormContainer').hide();
    $('#sessionFormContainer').hide();
    // Show companies container
    $('#allCompaniesContainer').fadeIn();
    // Optionally, load companies data here
    if (typeof loadAllCompaniesData === 'function') {
        loadAllCompaniesData();
    }
});
// Retry loading companies on error
$(document).on('click', '#retryLoadCompanies', function(e) {
    e.preventDefault();
    loadAllCompaniesData();
});

// Show Add Session form and hide other forms
$(document).on('click', '#btnAddSession', function(e) {
    e.preventDefault();
    $('#studentFormContainer').hide();
    $('#addHTEFormContainer').hide();
    $('#allStudentsContainer').hide();
    $('#controlIntroContainer').hide();
    $('#deleteHTEFormContainer').hide();
    $('#deleteSessionFormContainer').hide();
    $('#deleteStudentFormContainer').hide();
    $('#sessionFormContainer').fadeIn();
});
// Handle batch student deletion form submission
$(document).on('submit', '#deleteStudentForm', function(e) {
    e.preventDefault();
    console.log('=== BATCH DELETE FORM SUBMITTED ===');
    
    // Get selected student IDs from checkboxes
    var selectedIds = [];
    $('.deleteStudentCheckbox:checked').each(function() {
        selectedIds.push($(this).val());
    });
    
    if (selectedIds.length === 0) {
        alert('Please select at least one student to delete.');
        return;
    }
    
    if (!confirm('Are you sure you want to delete the selected students?')) {
        return;
    }
    
    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'deleteStudents',
            studentIds: selectedIds
        },
        success: function(response) {
            if (response.success) {
                alert(response.message);
                location.reload(); // Refresh the page to update the list
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('An error occurred while deleting students.');
        }
    });
});

// More general handler for any button containing "Delete Selected"
$(document).on('click', 'button:contains("Delete Selected"), input[value*="Delete Selected"], button[type="submit"]', function(e) {
    // Check if this is the delete button by examining its context
    var buttonText = $(this).text() || $(this).val() || '';
    
    if (buttonText.toLowerCase().includes('delete selected') || 
        $(this).find('i.fa-user-minus').length > 0 ||
        $(this).closest('#deleteStudentForm').length > 0) {
        
    e.preventDefault();
    
    // Get selected student IDs from checkboxes
    var selectedIds = [];
    $('.deleteStudentCheckbox:checked').each(function() {
        selectedIds.push($(this).val());
    });
    
    if (selectedIds.length === 0) {
        alert('Please select at least one student to delete.');
        return;
    }
    
    if (!confirm('Are you sure you want to delete the selected students?')) {
        return;
    }
    
    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'deleteStudents',
            studentIds: selectedIds
        },
        success: function(response) {
            if (response.success) {
                alert(response.message);
                location.reload(); // Refresh the page to update the list
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('An error occurred while deleting students.');
        }
    });
    } else {
        return true; // Allow normal button behavior
    }
});

// Handle single student form submission
$(document).on('submit', '#singleStudentForm', function(e) {
    e.preventDefault();
    var $form = $(this);
    if ($form.data('submitted')) return;
    $form.data('submitted', true);
    
    // Show loading indicator on submit button
    const $submitBtn = $form.find('button[type="submit"]');
    const originalText = $submitBtn.text();
    $submitBtn.prop('disabled', true).html('<span class="spinner">⏳</span> Adding Student...');
    
    var formData = $form.serialize() + '&action=addStudent';
    // You may want to add session and HTE selection here if needed
    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Student added successfully!');
                $form[0].reset();
                $('#singleStudentFormWrapper').slideUp();
                $submitBtn.text('✅ Student Added');
            } else {
                alert(response.message || 'Error adding student.');
                $submitBtn.prop('disabled', false).text(originalText);
            }
            $form.data('submitted', false);
        },
        error: function(xhr, status, error) {
            alert('An error occurred while adding the student.');
            $submitBtn.prop('disabled', false).text(originalText);
            $form.data('submitted', false);
        }
    });
});
// Toggle single student entry form
$(document).on('click', '#toggleSingleEntry', function() {
        $('#singleStudentFormWrapper').slideToggle();
        // Populate session dropdown
        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php",
            type: "POST",
            dataType: "json",
            data: {action: "getSession" },
            success: function(rv) {
                let x = '<option value="">Select Term</option>';
                for(let i=0; i<rv.length; i++) {
                    let cs = rv[i];
                    let displayName = cs.DISPLAY_NAME || ('S.Y. ' + cs.YEAR + '-' + (parseInt(cs.YEAR) + 1));
                    x += `<option value="${cs.ID}">${displayName}</option>`;
                }
                $('#singleSessionSelectStudent').html(x);
                // Auto-select most recent session
                if(rv && rv.length > 0) {
                    $('#singleSessionSelectStudent').val(rv[rv.length-1].ID).trigger('change');
                }
            }
        });
        // Populate HTE dropdown based on selected session and coordinator
        function updateSingleHTEDropdown() {
            var cdrid = $('#hiddencdrid').val();
            var sessionid = $('#singleSessionSelectStudent').val();
            $.ajax({
                url: "ajaxhandler/attendanceAJAX.php",
                type: "POST",
                dataType: "json",
                data: {cdrid: cdrid, sessionid: sessionid, action: "getHTE"},
                success: function(rv) {
                    let x = '<option value="">Select HTE</option>';
                    for(let i=0; i<rv.length; i++) {
                        let cc = rv[i];
                        x += `<option value="${cc.HTE_ID}">${cc.NAME}</option>`;
                    }
                    $('#singleHteSelectStudent').html(x);
                }
            });
        }
        // Initial HTE population after session loads
        setTimeout(updateSingleHTEDropdown, 300);
        // Update HTE dropdown when session changes
        $('#singleSessionSelectStudent').off('change').on('change', updateSingleHTEDropdown);
});

// Optional: Close single student form
$(document).on('click', '#closeSingleStudentForm', function() {
    $('#singleStudentFormWrapper').slideUp();
});
let currentHteId;
let currentSessionId;

// Student Dashboard Modal Functions
$(document).on('click', '#btnStudentStats', function() {
    $('#studentDashboardModal').removeClass('hidden');
    loadStudentStats();
});

$(document).on('click', '#closeStudentDashboardModal, #studentDashboardModal', function(e) {
    if (e.target.id === 'studentDashboardModal' || e.target.id === 'closeStudentDashboardModal') {
        $('#studentDashboardModal').addClass('hidden');
    }
});

function loadStudentStats() {
    $.ajax({
        url: 'ajaxhandler/studentDashboardAjax.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'getStudentStats'
        },
        success: function(response) {
            if (response.success) {
                $('#weeklyPresentCount').text(response.data.weeklyPresent || '0');
                $('#totalHours').text(response.data.totalHours ? response.data.totalHours + 'h' : '0h');
                $('#attendancePercentage').text(response.data.attendancePercentage ? response.data.attendancePercentage + '%' : '0%');
                
                // Update recent activity
                if (response.data.recentActivity && response.data.recentActivity.length > 0) {
                    let activityHtml = '<div class="space-y-4">';
                    response.data.recentActivity.forEach(activity => {
                        activityHtml += `
                            <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-file-alt text-blue-500"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900">${activity.title}</p>
                                    <p class="text-xs text-gray-500">${activity.date}</p>
                                </div>
                            </div>`;
                    });
                    activityHtml += '</div>';
                    $('#dashboardRecentActivityTable').html(activityHtml);
                } else {
                    $('#dashboardRecentActivityTable').html('<p class="text-gray-500 text-center py-4">No recent weekly report submissions found.</p>');
                }
            }
        },
        error: function() {
            console.error('Failed to load student stats');
        }
    });
}

// --- Student Profile Logic ---
$(document).on('click', '.btnProfileStudent', function() {
    const studentId = $(this).data('studentid');
    
    // Show modal and loading state
    $('#studentProfileModal').removeClass('hidden');
    $('#profileLoading').show();
    $('#studentProfileContent').addClass('hidden');
    
    // Fetch student details from studentDashboardAjax.php
    $.ajax({
        url: 'ajaxhandler/studentDashboardAjax.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'getStudentProfile',
            studentId: studentId
        },
        success: function(response) {
            if ((response.status === 'success' || response.success) && response.data) {
                const student = response.data;
                
                // Update profile fields
                $('#profileFirstName').val(student.NAME || '-');
                $('#profileLastName').val(student.SURNAME || '-');
                $('#profileStudentId').val(student.STUDENT_ID || student.INTERNS_ID || '-');
                $('#profileEmail').val(student.EMAIL || '-');
                $('#profileContact').val(student.CONTACT_NUMBER || '-');
                $('#profileGender').val(student.GENDER || '-');
                $('#profileAge').val(student.AGE || '-');
                $('#profileCompany').val(student.HTE_NAME || '-');
                
                // Handle profile picture
                if (student.profile_picture) {
                    // Use getImageUrl function to handle all URL types properly
                    const profileUrl = getImageUrl(student.profile_picture);
                    $('#profilePicture img').attr('src', profileUrl).show();
                    $('#profilePicture div').hide();
                } else {
                    $('#profilePicture img').hide();
                    $('#profilePicture div').show();
                }
                
                // Update modal title with student name
                $('#studentProfileName').text('Profile: ' + student.NAME + ' ' + student.SURNAME);
                
                // Hide loading and show content
                $('#profileLoading').hide();
                $('#studentProfileContent').removeClass('hidden');
            } else {
                $('#profileLoading').hide();
                $('#studentProfileContent').removeClass('hidden').html('<div class="text-center py-4 text-red-500">Error loading student profile</div>');
            }
        },
        error: function() {
            $('#profileLoading').hide();
            $('#studentProfileContent').removeClass('hidden').html('<div class="text-center py-4 text-red-500">Failed to load student profile</div>');
        }
    });
});

// Close student profile modal
$(document).on('click', '#closeStudentProfile', function() {
    $('#studentProfileModal').addClass('hidden');
});

// Close modal when clicking outside
$(document).on('click', '#studentProfileModal', function(e) {
    if (e.target.id === 'studentProfileModal') {
        $('#studentProfileModal').addClass('hidden');
    }
});

// --- Post-Analysis Student List Logic ---
let allPostAnalysisStudents = [];
// Map of INTERN_ID -> SESSION_NAME for Post-Analysis term filtering
window.postAnalysisStudentSessionMap = window.postAnalysisStudentSessionMap || {};

function loadPostAnalysisTerms() {
    const cdrid = $('#hiddencdrid').val();
    if (!cdrid) { return; }
    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'getAllStudentsUnderCoordinator', cdrid: cdrid },
        success: function(resp) {
            if (resp && resp.success && Array.isArray(resp.students)) {
                const map = {};
                const terms = new Set();
                resp.students.forEach(function(s){
                    if (s.INTERNS_ID) map[s.INTERNS_ID] = s.SESSION_NAME || '';
                    if (s.SESSION_NAME) terms.add(s.SESSION_NAME);
                });
                window.postAnalysisStudentSessionMap = map;
                const $sel = $('#postAnalysisTermFilter');
                if ($sel && $sel.length) {
                    const getYear = (t) => {
                        const m = /([0-9]{4})/.exec(t || '');
                        return m ? parseInt(m[1], 10) : -Infinity;
                    };
                    const sorted = Array.from(terms).sort((a,b)=> getYear(a) - getYear(b));
                    const latest = sorted.length ? sorted[sorted.length - 1] : null;
                    // Build options (latest first for convenience)
                    let opts = '<option value="all">All Terms</option>';
                    const displayList = sorted.slice().reverse();
                    displayList.forEach(function(t){
                        const selectedAttr = (latest && t === latest) ? ' selected' : '';
                        opts += `<option value=\"${t}\"${selectedAttr}>${t}</option>`;
                    });
                    $sel.html(opts);
                    if (latest) {
                        $sel.val(latest);
                        // Trigger filtering if students already loaded
                        $sel.trigger('change');
                    }
                }
            }
        }
    });
}
// Handle student selection in Post-Analysis tab
$(document).on('click', '.postanalysis-student-item', function() {
    // Remove selection from all, add to clicked
    $('.postanalysis-student-item').removeClass('selected');
    $(this).addClass('selected');
    // Get student name and IDs
    const internsId = $(this).data('studentid');
    const student = allPostAnalysisStudents.find(s => s.INTERNS_ID == internsId);
    let displayName = student ? (student.NAME + ' ' + student.SURNAME) : '';
    // Use STUDENT_ID for API call
    const studentId = student && student.STUDENT_ID ? student.STUDENT_ID : internsId;
    // Show loading message
    $('#postAnalysisContentArea').html('<div class="loading">Loading post-analysis...</div>');

    // Fetch post-analysis summary from PHP endpoint
    $.ajax({
        url: 'ajaxhandler/postAssessmentAveragesAjax.php',
        type: 'POST',
        data: { student_id: studentId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.summary) {
                const summary = response.summary;
                let html = '';
                html += `<div class="postanalysis-summary-card" style="max-width:1100px;margin:0 auto;background:#fff;border-radius:2rem;box-shadow:0 8px 32px rgba(0,0,0,0.10);padding:2.5rem 2.5rem 2rem 2.5rem;">`;
                html += `<div class="text-3xl font-extrabold text-blue-700 mb-6 text-center">Post-Analysis Summary</div>`;
                // Placement
                if (summary.placement) {
                    html += `<div class="flex flex-col items-center mb-8">
                        <div class="text-xl font-bold text-gray-700 mb-2">Predicted Placement</div>
                        <span class="inline-block px-6 py-3 rounded-full font-extrabold text-2xl bg-yellow-100 text-yellow-800 shadow">${summary.placement}</span>
                    </div>`;
                }
                // Reasoning
                if (summary.reasoning) {
                    html += `<div class="mb-8 p-5 rounded-xl border-2 border-blue-200 bg-blue-50 shadow-sm">
                        <div class="font-bold text-blue-700 mb-2 text-lg">Reasoning</div>
                        <div class="text-gray-800 text-base">${summary.reasoning.replace(/\n/g, '<br>')}</div>
                    </div>`;
                }
                // Post-Assessment Averages Table
                if (summary.averages && summary.averages.length) {
                    const supervisorTotal = summary.averages.reduce((sum, row) => sum + (parseFloat(row.supervisor_avg) || 0), 0);
                    const selfTotal = summary.averages.reduce((sum, row) => sum + (parseFloat(row.self_avg) || 0), 0);
                    const count = summary.averages.length;
                    const supervisorAvg = count ? (supervisorTotal / count).toFixed(2) : '-';
                    const selfAvg = count ? (selfTotal / count).toFixed(2) : '-';
                    html += `<div class="mb-8 p-5 rounded-xl border-2 border-purple-200 bg-purple-50 shadow-sm">
                        <div class="font-bold text-purple-700 mb-2 text-lg">Post-Assessment Averages</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left rounded-lg overflow-hidden min-w-max">
                                <thead><tr class="bg-purple-100 text-purple-800"><th class="px-4 py-2 whitespace-nowrap">Category</th><th class="px-4 py-2 whitespace-nowrap">Supervisor Avg</th><th class="px-4 py-2 whitespace-nowrap">Self Avg</th></tr></thead>
                                <tbody>
                                ${summary.averages.map(row => `<tr><td class="px-4 py-2 whitespace-nowrap">${row.category}</td><td class="px-4 py-2 whitespace-nowrap">${row.supervisor_avg !== null ? row.supervisor_avg : '-'}</td><td class="px-4 py-2 whitespace-nowrap">${row.self_avg !== null ? row.self_avg : '-'}</td></tr>`).join('')}
                                <tr class="bg-purple-200 font-bold"><td class="px-4 py-2 whitespace-nowrap">Total Average</td><td class="px-4 py-2 whitespace-nowrap">${supervisorAvg}</td><td class="px-4 py-2 whitespace-nowrap">${selfAvg}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>`;
                }
                // Supervisor Comment
                if (summary.supervisor_comment) {
                    html += `<div class="mb-8 p-5 rounded-xl border-2 border-green-200 bg-green-50 shadow-sm">
                        <div class="font-bold text-green-700 mb-2 text-lg">Supervisor Comment</div>
                        <div class="text-gray-800 text-base">${summary.supervisor_comment.replace(/\n/g, '<br>')}</div>
                    </div>`;
                }
                // Comparative Analysis
                if (summary.comparative_analysis) {
                    html += `<div class="mb-8 p-5 rounded-xl border-2 border-indigo-200 bg-indigo-50 shadow-sm">
                        <div class="font-bold text-indigo-700 mb-2 text-lg">Comparative Analysis</div>
                        <div class="text-gray-800 text-base">${summary.comparative_analysis.replace(/\n/g, '<br>')}</div>
                    </div>`;
                }
                // Strengths Identified in Post-Assessment
                if (summary.strengths_post_assessment) {
                    html += `<div class="mb-8 p-5 rounded-xl border-2 border-yellow-200 bg-yellow-50 shadow-sm">
                        <div class="font-bold text-yellow-700 mb-2 text-lg">Strengths Identified in Post-Assessment</div>
                        <div class="text-gray-800 text-base">${summary.strengths_post_assessment}</div>
                    </div>`;
                }
                // Correlation Analysis
                if (summary.correlation_analysis) {
                    html += `<div class="mb-8 p-5 rounded-xl border-2 border-pink-200 bg-pink-50 shadow-sm">
                        <div class="font-bold text-pink-700 mb-2 text-lg">Correlation Analysis</div>
                        <div class="text-gray-800 text-base">${summary.correlation_analysis.replace(/\n/g, '<br>')}</div>
                    </div>`;
                }
                // Conclusion & Recommendation
                if (summary.conclusion_recommendation) {
                    html += `<div class="mb-8 p-5 rounded-xl border-2 border-blue-200 bg-blue-50 shadow-sm">
                        <div class="font-bold text-blue-700 mb-2 text-lg">Conclusion & Recommendation</div>
                        <div class="text-gray-800 text-base">${summary.conclusion_recommendation.replace(/\n/g, '<br>')}</div>
                    </div>`;
                }
                html += `</div>`;
                $('#postAnalysisContentArea').html(html);
            } else {
                // Show detailed message for incomplete post-assessment data
                $('#postAnalysisContentArea').html(`
                    <div class="text-center py-8">
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-orange-600 mb-2">Post-Assessment Not Completed</h3>
                        <p class="text-gray-600 text-sm max-w-md mx-auto leading-relaxed mb-6">
                            This student has not completed their post-assessment evaluation yet. Post-analysis requires supervisor and self-evaluation ratings from the completed OJT experience.
                        </p>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 max-w-md mx-auto">
                            <p class="text-blue-800 text-sm font-medium mb-2">📋 Post-assessment includes:</p>
                            <ul class="text-blue-700 text-sm text-left list-disc list-inside space-y-1">
                                <li>Supervisor evaluation ratings</li>
                                <li>Student self-evaluation ratings</li>
                                <li>Supervisor comments and feedback</li>
                                <li>OJT performance assessment</li>
                            </ul>
                        </div>
                        <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3 max-w-md mx-auto">
                            <p class="text-yellow-800 text-xs">
                                💡 Post-analysis will be available after the student completes their OJT period and both evaluations are submitted.
                            </p>
                        </div>
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            $('#postAnalysisContentArea').html('<div class="error">Failed to load post-analysis data.</div>');
        }
    });
});

function loadPostAnalysisStudents() {
    $.ajax({
        url: 'ajaxhandler/coordinatorPostAnalysisAjax.php',
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.students) {
                allPostAnalysisStudents = response.students;
                // Apply current search filter and term filter on first render
                const q = ($('#postAnalysisStudentSearch').val() || '').trim().toLowerCase();
                let filtered = allPostAnalysisStudents.filter(function(s){
                    const name = (s.NAME + ' ' + s.SURNAME).toLowerCase();
                    return name.includes(q);
                });
                renderPostAnalysisStudentList(filtered);
            } else {
                allPostAnalysisStudents = [];
                renderEmptyPostAnalysisState('No students found for post-analysis.');
            }
        },
        error: function() {
            allPostAnalysisStudents = [];
            renderEmptyPostAnalysisState('Error loading students.');
        }
    });
}

// Render empty state for post-analysis tab
function renderEmptyPostAnalysisState(message) {
    // Clear the student list panel
    $('#postAnalysisStudentListPanel').html('');
    
    // Update the content area with styled empty state
    $('#postAnalysisContentArea').html(`
        <div class="flex flex-col items-center justify-center h-full min-h-[400px]">
            <div class="bg-blue-50 rounded-full p-6 mb-4">
                <svg xmlns='http://www.w3.org/2000/svg' class='h-12 w-12 text-blue-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' /></svg>
            </div>
            <div class="text-xl font-semibold text-blue-700 mb-2">No Students Available</div>
            <div class="text-gray-500 text-base text-center">${message}</div>
        </div>
    `);
}

function renderPostAnalysisStudentList(students) {
    // Apply term filter using mapping
    const selectedTerm = (($('#postAnalysisTermFilter').val()) || 'all').trim();
    let list = students;
    if (selectedTerm !== 'all' && window.postAnalysisStudentSessionMap) {
        list = students.filter(function(s){
            const sess = window.postAnalysisStudentSessionMap[s.INTERNS_ID] || '';
            return sess === selectedTerm;
        });
    }
    let sorted = list.slice().sort((a, b) => (a.SURNAME + ', ' + a.NAME).localeCompare(b.SURNAME + ', ' + b.NAME));
    let html = '';
    sorted.forEach(function(student) {
        const displayName = student.SURNAME + ', ' + student.NAME;
        const isReady = !!(student.ready_for_post_analysis);
        const badgeClass = isReady ? 'text-green-700 bg-green-100' : 'text-gray-600 bg-gray-100';
        const badgeText = isReady ? 'Ready' : 'Not Ready';
        html += `<div class=\"postanalysis-student-item\" style=\"display:flex;align-items:center;gap:.5rem;\" data-studentid=\"${student.INTERNS_ID}\">`+
                `<span style=\"flex:1 1 auto;min-width:0;\">${displayName}</span>`+
                `<span class=\"ml-auto inline-block rounded-full ${badgeClass}\" style=\"font-size:.68rem;font-weight:500;padding:.15rem .5rem;white-space:nowrap;\">${badgeText}</span>`+
            `</div>`;
    });
    $('#postAnalysisStudentListPanel').html(html);
}

// Note: Global student list variables removed to prevent duplicate rendering
// All student list functionality is now handled by local functions within the document ready block

// Global loadPreassessmentStudentList function removed to prevent duplicate rendering

// Global renderStudentList function removed to prevent duplicate rendering

// Global loadReviewStudentList function removed to prevent duplicate rendering

// Global renderReviewStudentList function removed to prevent duplicate rendering


// Render empty state for pre-assessment tab
function renderEmptyPreAssessmentState(message) {
    // Create the full layout with empty state message
    let html = `<div class='flex w-full'>`;
    html += `<div class='left-col w-1/3 pr-4'>`;
    html += `<div class='mb-4'><input type='text' id='rateStudentSearch' placeholder='Search student' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200' disabled></div>`;
    html += `<div id='studentListPanel' class='overflow-y-auto min-h-[500px] max-h-[700px] flex flex-col gap-1'></div>`;
    html += `</div>`;
    html += `<div class='right-col w-2/3 pl-4'>`;
    html += `
        <div class="flex flex-col items-center justify-center h-full">
            <div class="bg-blue-50 rounded-full p-6 mb-4">
                <svg xmlns='http://www.w3.org/2000/svg' class='h-12 w-12 text-blue-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
            </div>
            <div class="text-xl font-semibold text-blue-700 mb-2">No Students Available</div>
            <div class="text-gray-500 text-base text-center">${message}</div>
        </div>
    `;
    html += `</div>`;
    html += `</div>`;
    $('#rateTabContent .p-6').html(html);
}

// Render empty state for review tab
function renderEmptyReviewState(message) {
    // Just put a styled message in the student list panel for review tab
    $('#reviewStudentListPanel').html(`
        <div class="flex flex-col items-center justify-center py-12">
            <div class="bg-blue-50 rounded-full p-4 mb-3">
                <svg xmlns='http://www.w3.org/2000/svg' class='h-8 w-8 text-blue-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' /></svg>
            </div>
            <div class="text-lg font-semibold text-blue-700 mb-1">No Students Available</div>
            <div class="text-gray-500 text-sm text-center">${message}</div>
        </div>
    `);
}

$(document).on('input', '#postAnalysisStudentSearch', function() {
    const query = $(this).val().trim().toLowerCase();
    let filtered = allPostAnalysisStudents.filter(s => {
        const displayName = (s.NAME + ' ' + s.SURNAME).toLowerCase();
        return displayName.includes(query);
    });
    renderPostAnalysisStudentList(filtered);
});

$(document).on('click', '#postAnalysisTab', function() {
    loadPostAnalysisTerms();
    loadPostAnalysisStudents();
});
$(document).ready(function() {
    if ($('#postAnalysisContent').is(':visible')) {
        loadPostAnalysisTerms();
        loadPostAnalysisStudents();
    }
});

// Change handler: re-render list when term changes (no refetch)
$(document).on('change', '#postAnalysisTermFilter', function(){
    const q = ($('#postAnalysisStudentSearch').val() || '').trim().toLowerCase();
    let filtered = allPostAnalysisStudents.filter(function(s){
        const name = (s.NAME + ' ' + s.SURNAME).toLowerCase();
        return name.includes(q);
    });
    renderPostAnalysisStudentList(filtered);
});

// --- Update Company Logo Modal Logic ---
$(document).on('click', '.update-logo-btn', function() {
    const hteId = $(this).data('hteid');
    $('#updateLogoHteId').val(hteId);
    $('#updateLogoFile').val('');
    $('#updateLogoPreview').attr('src', '#').addClass('hidden');
    $('#updateCompanyLogoModal').removeClass('hidden');
});

$(document).on('change', '#updateLogoFile', function(e) {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#updateLogoPreview').attr('src', e.target.result).removeClass('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        $('#updateLogoPreview').attr('src', '#').addClass('hidden');
    }
});

$(document).on('click', '#closeUpdateLogoModal, #cancelUpdateLogo', function() {
    $('#updateCompanyLogoModal').addClass('hidden');
    $('#updateLogoFile').val('');
    $('#updateLogoPreview').attr('src', '#').addClass('hidden');
});

// Handle update company logo form submit
$(document).on('submit', '#updateCompanyLogoForm', function(e) {
    e.preventDefault();
    const hteId = $('#updateLogoHteId').val();
    const fileInput = $('#updateLogoFile')[0];
    if (!hteId || !fileInput.files || !fileInput.files[0]) {
        alert('Please select a logo image to upload.');
        return;
    }
    
    // Show loading indicator on submit button
    const $submitBtn = $(this).find('button[type="submit"]');
    const originalText = $submitBtn.text();
    $submitBtn.prop('disabled', true).html('<span class="spinner">⏳</span> Uploading Logo...');
    const formData = new FormData();
    formData.append('action', 'updateHTELogo');
    formData.append('hteId', hteId);
    formData.append('LOGO', fileInput.files[0]);
    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('Company logo updated successfully!');
                $('#updateCompanyLogoModal').addClass('hidden');
                $('#updateLogoFile').val('');
                $('#updateLogoPreview').attr('src', '#').addClass('hidden');
                
                // Invalidate companies cache since logo was updated
                let cdrid = $("#hiddencdrid").val();
                const cacheKey = `allCompanies_${cdrid}`;
                invalidateCache('htes', cacheKey);
                
                loadAllCompaniesData();
                $submitBtn.text('✅ Logo Updated');
            } else {
                alert(response.message || 'Failed to update company logo.');
                $submitBtn.prop('disabled', false).text(originalText);
            }
        },
        error: function() {
            alert('An error occurred while uploading the logo.');
            $submitBtn.prop('disabled', false).text(originalText);
        }
    });
});

// --- Edit HTE Modal Logic ---
$(document).on('click', '.edit-hte-btn', function() {
    const hteId = $(this).data('hteid');
    
    // Fetch HTE details and show edit modal
    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'getHTEDetails', hteId: hteId },
        success: function(response) {
            if (response.success && response.hte) {
                populateEditHTEForm(response.hte);
                $('#editHTEModal').removeClass('hidden');
            } else {
                alert('Failed to load HTE details: ' + (response.message || 'Unknown error'));
            }
        },
        error: function() {
            alert('Error loading HTE details. Please try again.');
        }
    });
});

// Function to populate edit HTE form with data
function populateEditHTEForm(hte) {
    $('#editHteId').val(hte.HTE_ID);
    $('#editHteName').val(hte.NAME);
    $('#editHteIndustry').val(hte.INDUSTRY);
    $('#editHteAddress').val(hte.ADDRESS);
    $('#editHteEmail').val(hte.CONTACT_EMAIL);
    $('#editHteContactPerson').val(hte.CONTACT_PERSON);
    $('#editHteContactNumber').val(hte.CONTACT_NUMBER);
    
    // MOA information
    if (hte.moa_start_date) {
        $('#editMoaStartDate').val(hte.moa_start_date);
        $('#currentMOAStartDate').text(hte.moa_start_date);
    } else {
        $('#currentMOAStartDate').text('Not set');
    }
    
    if (hte.moa_end_date) {
        $('#editMoaEndDate').val(hte.moa_end_date);
        $('#currentMOAEndDate').text(hte.moa_end_date);
    } else {
        $('#currentMOAEndDate').text('Not set');
    }
    
    // Calculate and display current MOA status using utility function
    const currentMoaStatus = calculateMOAStatus(hte.moa_start_date, hte.moa_end_date);
    if (currentMoaStatus.status !== 'N/A') {
        const statusHtml = `<span class="${currentMoaStatus.class}">${currentMoaStatus.icon} ${currentMoaStatus.status}</span>`;
        $('#currentMOAStatus').html(statusHtml);
        
        // Show MOA file view button if exists
        if (hte.moa_file_url) {
            $('#currentMOAFileSection').show();
            $('#viewCurrentMOA').data('moa', hte.moa_file_url);
        } else {
            $('#currentMOAFileSection').hide();
        }
    } else {
        $('#currentMOAStatus').html('<span class="text-gray-500">No MOA data</span>');
        $('#currentMOAFileSection').hide();
    }
    
    // Update status preview
    updateEditMOAStatus();
}

// MOA Date validation and status preview for edit form
function updateEditMOAStatus() {
    const startDate = $('#editMoaStartDate').val();
    const endDate = $('#editMoaEndDate').val();
    const statusPreview = $('#editMoaStatusPreview');
    
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        if (end < start) {
            statusPreview.html('<span class="text-red-600">❌ Invalid: End date must be after start date</span>');
            return false;
        }
        
        const moaStatus = calculateMOAStatus(startDate, endDate);
        const statusHtml = `<span class="text-${moaStatus.status === 'Active' ? 'green' : moaStatus.status === 'Expired' ? 'red' : 'blue'}-600">${moaStatus.icon} ${moaStatus.status}${moaStatus.status === 'Future' ? ' (Will be active on start date)' : ''}</span>`;
        statusPreview.html(statusHtml);
        return true;
    } else {
        statusPreview.html('<span class="text-gray-600">Select both dates to see status</span>');
        return startDate !== '' && endDate !== '';
    }
}

// Edit MOA date change handlers
$(document).on('change', '#editMoaStartDate, #editMoaEndDate', updateEditMOAStatus);

// View current MOA button
$(document).on('click', '#viewCurrentMOA', function() {
    const moaPath = $(this).data('moa');
    if (moaPath) {
        window.open(moaPath, '_blank');
    }
});

// Close edit HTE modal
$(document).on('click', '#closeEditHTEModal, #cancelEditHTE', function() {
    $('#editHTEModal').addClass('hidden');
    $('#editHTEForm')[0].reset();
    $('#editMoaStatusPreview').text('Select dates to see status');
});

// Handle edit HTE form submission
$(document).on('submit', '#editHTEForm', function(e) {
    e.preventDefault();
    
    // Validate MOA dates first
    if (!updateEditMOAStatus()) {
        alert('Please check MOA dates. End date must be after start date.');
        return;
    }
    
    // Create FormData object to handle file uploads
    let formData = new FormData(this);
    formData.append('action', 'updateHTEDetails');
    
    // Disable submit button to prevent double submission
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.text();
    submitBtn.prop('disabled', true).html('<span class="spinner">⏳</span> Updating HTE...');

    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('HTE updated successfully!');
                $('#editHTEModal').addClass('hidden');
                $('#editHTEForm')[0].reset();
                $('#editMoaStatusPreview').text('Select dates to see status');
                
                // Invalidate companies cache since HTE was updated
                let cdrid = $("#hiddencdrid").val();
                const cacheKey = `allCompanies_${cdrid}`;
                invalidateCache('htes', cacheKey);
                
                loadAllCompaniesData(); // Refresh the companies list
            } else {
                alert('Error updating HTE: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error updating HTE. Please try again.');
            console.error('HTE update error:', xhr.responseText);
        },
        complete: function() {
            // Re-enable submit button
            submitBtn.prop('disabled', false).text(originalText);
        }
    });
});

// Function to format contact number to Philippine format: +63 951 3762 404
function formatPhilippineContactNumber(input) {
    let value = input.value.replace(/\D/g, ''); // Remove non-numeric characters

    if (value.startsWith('09') && value.length === 11) {
        // Convert 09123456789 to +63 912 345 6789
        value = '+63 ' + value.substring(1, 4) + ' ' + value.substring(4, 7) + ' ' + value.substring(7);
    } else if (value.startsWith('639') && value.length === 12) {
        // Convert 639123456789 to +63 912 345 6789
        value = '+63 ' + value.substring(2, 5) + ' ' + value.substring(5, 8) + ' ' + value.substring(8);
    } else if (value.length === 10 && !value.startsWith('0')) {
        // Assume it's 9123456789, format as +63 912 345 6789
        value = '+63 ' + value.substring(0, 3) + ' ' + value.substring(3, 6) + ' ' + value.substring(6);
    }

    input.value = value;
}

// Attach formatting to contact number inputs
$(document).on('blur', '#contactNumber, #hteContactNumber, #profileContact', function() {
    formatPhilippineContactNumber(this);
});

    // Tab switching functionality
function switchTab(tabName) {
    console.log('switchTab called with:', tabName);
    
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });

    // Remove active class from all sidebar items
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.classList.remove('active');
    });

    // Show selected tab content
    const tabContent = document.getElementById(tabName + 'Content');
    console.log('Tab content element:', tabContent);
    if (tabContent) {
        tabContent.classList.add('active');
        console.log('Added active class to tab content');
    } else {
        console.error('Tab content element not found:', tabName + 'Content');
    }

    // Add active class to selected sidebar item
    const sidebarItem = document.getElementById(tabName + 'Tab');
    console.log('Sidebar item element:', sidebarItem);
    if (sidebarItem) {
        sidebarItem.classList.add('active');
        console.log('Added active class to sidebar item');
    } else {
        console.error('Sidebar item not found:', tabName + 'Tab');
    }

    // Just log which tab we switched to
    console.log('Tab switched to: ' + tabName);
}

// Utility functions
function getSessionHTML(rv) {
    let x = `<option value=-1>SELECT ONE</option>`;
    // Add a message element for no matches in rateEvalList
    if ($('#rateEvalList .no-match-message').length === 0) {
        $('#rateEvalList').append('<div class="no-match-message" style="display:none; text-align:center; color:#a0aec0; font-size:1.2em; margin-top:2em;">No record for this student</div>');
    }
    let i = 0;
    for(i = 0; i < rv.length; i++) {
        let cs = rv[i];
        x = x + `<option value=${cs['ID']}>${cs['YEAR']+" "+cs['TERM']}</option>`;
    }
    return x;
}

    // Dashboard button click handler (use getDashboardStats)
    $(document).on("click", ".btnDashboardStudent", function() {
        let studentId = $(this).data('studentid');
        let $button = $(this);
        
        if (!studentId) {
            alert("Student ID not found.");
            return;
        }
        
        // Show loading spinner
        const originalText = $button.html();
        $button.prop('disabled', true).html(`
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading...
        `);
        
        $.ajax({
            url: "ajaxhandler/studentDashboardAjax.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "getDashboardStats",
                studentId: studentId
            },
            success: function(response) {
                if (response.status === "success" && response.data) {
                    let presentDays = response.data.presentDays || 0;
                    let totalHours = response.data.totalHours || 0;
                    let attendanceRate = response.data.attendanceRate || 0;
                    showStudentDashboardModal({ presentDays, totalHours, attendanceRate }, studentId);
                } else {
                    alert("Could not fetch student dashboard info.");
                }
            },
            error: function(xhr, status, error) {
                alert("Error fetching student dashboard info: " + error);
            },
            complete: function() {
                // Restore button state
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Modal for displaying student dashboard info (all stats)
    function showStudentDashboardModal(stats) {
        // If inline Tailwind modal exists (no #studentDashboardModalContent), populate it and exit
        if ($("#studentDashboardModal").length > 0 && $("#studentDashboardModalContent").length === 0) {
            const present = typeof stats.presentDays === 'number' ? stats.presentDays : 0;
            const hours = typeof stats.totalHours === 'number' ? stats.totalHours : 0;
            const rate = typeof stats.attendanceRate === 'number' ? stats.attendanceRate : 0;
            $('#weeklyPresentCount').text(present);
            $('#totalHours').text(`${hours}h`);
            $('#attendancePercentage').text(`${rate}%`);
            const studentId = arguments[1];
            $.ajax({
                url: 'ajaxhandler/studentDashboardAjax.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'getRecentReportStatus', studentId },
                success: function(response) {
                    let tableHtml = '';
                    if (response.status === 'success' && Array.isArray(response.data) && response.data.length > 0) {
                        tableHtml += `<div class="weekly-report-table-wrapper"><table class="weekly-report-table"><thead><tr><th>Week</th><th>Status</th><th>Submitted</th><th>Approved</th></tr></thead><tbody>`;
                        response.data.forEach(report => {
                            const approvedClass = report.approved_at ? '' : 'na';
                            tableHtml += `
                                <tr class="weekly-report-row">
                                    <td>${report.week_start} to ${report.week_end}</td>
                                    <td>${report.status} / ${report.approval_status}</td>
                                    <td>${report.created_at || 'N/A'}</td>
                                    <td class="weekly-report-approved ${approvedClass}">${report.approved_at || 'N/A'}</td>
                                </tr>
                            `;
                        });
                        tableHtml += '</tbody></table></div>';
                    } else {
                        tableHtml = '<p class="text-gray-500 text-center py-4">No recent weekly report submissions found.</p>';
                    }
                    $('#dashboardRecentActivityTable').html(tableHtml);
                },
                error: function() {
                    $('#dashboardRecentActivityTable').html('<p class="text-red-500 text-center py-4">Error loading recent weekly report status.</p>');
                }
            });
            $('#studentDashboardModal').removeClass('hidden').show();
            return;
        }
        // Create modal if not exists
        if ($("#studentDashboardModal").length === 0) {
            $("body").append(`
                <div id="studentDashboardModal" class="main-dashboard-modal-bg" style="display:none;">
                    <div class="main-dashboard-modal-content">
                        <div class="main-dashboard-modal-header">
                            <h2 class="main-dashboard-modal-title">Student Stats</h2>
                            <button class="main-dashboard-modal-close" id="closeStudentDashboardModal">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="main-dashboard-modal-body">
                            <div id="studentDashboardModalContent"></div>
                        </div>
                    </div>
                </div>
            `);
        }
        let statsHtml = `
            <div class="main-dashboard-modal-grid">
                <div class="main-dashboard-stats-row">
                    <div class='main-dashboard-card'>
                        <h3>Present Count (This Week)</h3>
                        <p>${stats.presentDays}</p>
                    </div>
                    <div class='main-dashboard-card'>
                        <h3>Total Hours</h3>
                        <p>${stats.totalHours}h</p>
                    </div>
                    <div class='main-dashboard-card'>
                        <h3>Attendance Percentage</h3>
                        <p>${stats.attendanceRate}%</p>
                    </div>
                </div>
                <div class="main-dashboard-recent-activity-card">
                    <h3>Recent Activity</h3>
                    <div id="dashboardRecentActivityTable"></div>
                </div>
            </div>
        `;
        // Fetch and render recent weekly report status
        return function(studentId) {
            $.ajax({
                url: "ajaxhandler/studentDashboardAjax.php",
                type: "POST",
                dataType: "json",
                data: {
                    action: "getRecentReportStatus",
                    studentId: studentId
                },
                success: function(response) {
                    $("#studentDashboardModalContent").html(statsHtml);
                    let tableHtml = "";
                    if (response.status === "success" && Array.isArray(response.data) && response.data.length > 0) {
                        tableHtml += `<div class=\"weekly-report-table-wrapper\"><table class=\"weekly-report-table\"><thead><tr><th>Week</th><th>Status</th><th>Submitted</th><th>Approved</th></tr></thead><tbody>`;
                        response.data.forEach(report => {
                            const approvedClass = report.approved_at ? '' : 'na';
                            tableHtml += `
                                <tr class=\"weekly-report-row\">
                                    <td>${report.week_start} to ${report.week_end}</td>
                                    <td>${report.status} / ${report.approval_status}</td>
                                    <td>${report.created_at || 'N/A'}</td>
                                    <td class=\"weekly-report-approved ${approvedClass}\">${report.approved_at || 'N/A'}</td>
                                </tr>
                            `;
                        });
                        tableHtml += '</tbody></table></div>';
                    } else {
                        tableHtml += '<p>No recent weekly report submissions found.</p>';
                    }
                    $("#dashboardRecentActivityTable").html(tableHtml);
                },
                error: function(xhr, status, error) {
                    $("#studentDashboardModalContent").html(statsHtml);
                    $("#dashboardRecentActivityTable").html('<p>Error loading recent weekly report status.</p>');
                }
            });
            $("#studentDashboardModal").fadeIn();
        }(arguments[1]);
    }

    $(document).on("click", "#closeStudentDashboardModal", function() {
        $("#studentDashboardModal").fadeOut();
    });

function loadSeassions() {
    $.ajax({
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: {action: "getSession"},
        success: function(response) {
            if (response.success) {
                $("#sessionSelect").html(getSessionHTML(response.data));
            }
        }
    });
}

// Document ready handler
$(function() {
    // Modern User Dropdown functionality
    $(document).on('click', '#userDropdownToggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const dropdown = $('#userDropdown');
        const arrow = $('#dropdownArrow');
        
        if (dropdown.hasClass('show')) {
            dropdown.removeClass('show');
            arrow.removeClass('rotated');
        } else {
            dropdown.addClass('show');
            arrow.addClass('rotated');
        }
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#userProfile').length) {
            $('#userDropdown').removeClass('show');
            $('#dropdownArrow').removeClass('rotated');
        }
    });

    // Prevent dropdown from closing when clicking inside
    $(document).on('click', '#userDropdown', function(e) {
        e.stopPropagation();
    });
    // Tab click event for sidebar
    $('.sidebar-item').click(function() {
        var tabName = $(this).data('tab');
        switchTab(tabName);
        if (tabName === 'control') {
            loadAllStudentsData();
        }
    });
    // --- Post-Assessment Student List Logic ---
    let allPostStudents = [];
    let selectedPostStudentId = null;
    let currentPostPage = 1;
    let postStudentsPerPage = 5;
    let totalPostStudents = 0;

    function renderPostStudentList(students, sessions = []) {
        // Pagination logic
        const startIndex = (currentPostPage - 1) * postStudentsPerPage;
        const endIndex = startIndex + postStudentsPerPage;
        const paginatedStudents = students.slice(startIndex, endIndex);
        const totalPages = Math.ceil(students.length / postStudentsPerPage);
        
        totalPostStudents = students.length;
        
        let sorted = paginatedStudents.slice().sort((a, b) => (a.SURNAME + ', ' + a.NAME).localeCompare(b.SURNAME + ', ' + b.NAME));
        let studentListHtml = '';
        sorted.forEach(function(student) {
            const displayName = student.SURNAME + ', ' + student.NAME;
            studentListHtml += `
                <div class="postassessment-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${student.INTERNS_ID === selectedPostStudentId ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${student.INTERNS_ID}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                    </span>
                    <span class="truncate">${displayName}</span>
                </div>
            `;
        });
        
        // Generate pagination HTML
        let paginationHtml = '';
        if (totalPages > 1) {
            paginationHtml += `<div class='flex flex-col items-center mt-4 px-2'>`;
            paginationHtml += `<div class='text-sm text-gray-600 mb-2'>Page ${currentPostPage} of ${totalPages}</div>`;
            paginationHtml += `<div class='flex gap-1'>`;
            
            // First page button
            const firstDisabled = currentPostPage <= 1;
            paginationHtml += `<button class='postassessment-page-btn px-3 py-2 text-sm border rounded ${firstDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='1' ${firstDisabled ? 'disabled' : ''}>&lt;&lt;</button>`;
            
            // Previous button
            const prevDisabled = currentPostPage <= 1;
            paginationHtml += `<button class='postassessment-page-btn px-3 py-2 text-sm border rounded ${prevDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentPostPage - 1}' ${prevDisabled ? 'disabled' : ''}>&lt;</button>`;
            
            // Next button
            const nextDisabled = currentPostPage >= totalPages;
            paginationHtml += `<button class='postassessment-page-btn px-3 py-2 text-sm border rounded ${nextDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentPostPage + 1}' ${nextDisabled ? 'disabled' : ''}>&gt;</button>`;
            
            // Last page button
            const lastDisabled = currentPostPage >= totalPages;
            paginationHtml += `<button class='postassessment-page-btn px-3 py-2 text-sm border rounded ${lastDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${totalPages}' ${lastDisabled ? 'disabled' : ''}>&gt;&gt;</button>`;
            
            paginationHtml += `</div>`;
            paginationHtml += `</div>`;
        }
        // 20/80 layout for Post-Assessment tab
        // On mobile, this will stack vertically with student list on top
    let html = `<div class='postassessment-main-wrapper flex flex-col md:flex-row w-full'>`;
    html += `<div class='postassessment-student-list-section left-col w-full md:w-1/5 max-w-xs md:pr-4 order-1 mb-4 md:mb-0'>`;
    const searchValue = window.postStudentSearchValue || '';
    html += `<div class='mb-4'><input type='text' id='postStudentSearch' value='${searchValue.replace(/'/g, "&#39;").replace(/"/g, '&quot;')}' placeholder='Search student name' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'></div>`;
    html += `<div class='mb-4'><select id='postAssessmentSessionFilter' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'><option value='all'>All Terms</option></select></div>`;
    html += `<div id='postStudentListPanel' class='overflow-y-auto min-h-[400px] max-h-[500px] flex flex-col gap-1'>${studentListHtml}</div>`;
    html += `<div id='postPaginationContainer'>${paginationHtml}</div>`;
        html += `</div>`;
        html += `<div class='postassessment-evaluation-section right-col w-full md:w-4/5 md:pl-4 order-2'>`;
        // Always render evaluation list container so AJAX population works after first selection
        html += `<div id='postEvalList' class='space-y-4 max-h-[700px] overflow-y-auto pr-2'>`;
        if (!selectedPostStudentId) {
            html += `
                <div class="flex flex-col items-center justify-center h-full">
                    <div class="bg-blue-50 rounded-full p-6 mb-4">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-12 w-12 text-blue-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                    </div>
                    <div class="text-xl font-semibold text-blue-700 mb-2">No student selected</div>
                    <div class="text-gray-500 text-base">Select a student from the list to view their post-assessment details.</div>
                </div>
            `;
        }
        html += `</div>`; // close postEvalList
        html += `</div>`;
        html += `</div>`;
        $('#postAssessmentContentArea').html(html);
        
        // Populate session dropdown immediately after HTML insertion
        if (sessions && sessions.length > 0) {
            let sessionOptions = '<option value="all">All Terms</option>';
            sessions.forEach(session => { 
                sessionOptions += `<option value='${session}'>${session}</option>`; 
            });
            
            console.log('[POST-ASSESSMENT] Session options HTML:', sessionOptions);
            console.log('[POST-ASSESSMENT] Dropdown element exists:', $('#postAssessmentSessionFilter').length > 0);
            
            // Populate dropdown
            $('#postAssessmentSessionFilter').html(sessionOptions);
            
            // Auto-select most recent session (same logic as Pre-Assessment and Review)
            console.log('[POST-ASSESSMENT] Auto-selecting session:', sessions[0]);
            $('#postAssessmentSessionFilter').val(sessions[0]);
        }
        
        // Restore focus and cursor position if search bar was focused
        if (window.shouldRefocusPostSearch) {
            const input = document.getElementById('postStudentSearch');
            if (input) {
                input.focus();
                const val = input.value;
                input.setSelectionRange(val.length, val.length);
            }
            window.shouldRefocusPostSearch = false;
        }
    }
    
    // Update only the Post-Assessment student list display without rebuilding dropdown or right panel
    function updatePostStudentListDisplay(students, resetPage = true) {
        console.log('[POST-ASSESSMENT] updatePostStudentListDisplay called with', students.length, 'students');
        
        if (resetPage) {
            currentPostPage = 1;
        }
        
        // Pagination logic
        const startIndex = (currentPostPage - 1) * postStudentsPerPage;
        const endIndex = startIndex + postStudentsPerPage;
        const paginatedStudents = students.slice(startIndex, endIndex);
        const totalPages = Math.ceil(students.length / postStudentsPerPage);
        
        totalPostStudents = students.length;
        
        let sorted = paginatedStudents.slice().sort((a, b) => (a.SURNAME + ', ' + a.NAME).localeCompare(b.SURNAME + ', ' + b.NAME));
        let studentListHtml = '';
        
        sorted.forEach(function(student) {
            const displayName = student.SURNAME + ', ' + student.NAME;
            studentListHtml += `
                <div class="postassessment-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${student.INTERNS_ID === selectedPostStudentId ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${student.INTERNS_ID}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                    </span>
                    <span class="truncate">${displayName}</span>
                </div>
            `;
        });
        
        // Generate pagination HTML
        let paginationHtml = '';
        if (totalPages > 1) {
            paginationHtml += `<div class='flex flex-col items-center mt-4 px-2'>`;
            paginationHtml += `<div class='text-sm text-gray-600 mb-2'>Page ${currentPostPage} of ${totalPages}</div>`;
            paginationHtml += `<div class='flex gap-1'>`;
            
            // First page button
            const firstDisabled = currentPostPage <= 1;
            paginationHtml += `<button class='postassessment-page-btn px-3 py-2 text-sm border rounded ${firstDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='1' ${firstDisabled ? 'disabled' : ''}>&lt;&lt;</button>`;
            
            // Previous button
            const prevDisabled = currentPostPage <= 1;
            paginationHtml += `<button class='postassessment-page-btn px-3 py-2 text-sm border rounded ${prevDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentPostPage - 1}' ${prevDisabled ? 'disabled' : ''}>&lt;</button>`;
            
            // Next button
            const nextDisabled = currentPostPage >= totalPages;
            paginationHtml += `<button class='postassessment-page-btn px-3 py-2 text-sm border rounded ${nextDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentPostPage + 1}' ${nextDisabled ? 'disabled' : ''}>&gt;</button>`;
            
            // Last page button
            const lastDisabled = currentPostPage >= totalPages;
            paginationHtml += `<button class='postassessment-page-btn px-3 py-2 text-sm border rounded ${lastDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${totalPages}' ${lastDisabled ? 'disabled' : ''}>&gt;&gt;</button>`;
            
            paginationHtml += `</div>`;
            paginationHtml += `</div>`;
        }
        
        // Update student list and pagination
        $('#postStudentListPanel').html(studentListHtml);
        $('#postPaginationContainer').html(paginationHtml);
        
        console.log('[POST-ASSESSMENT] Student list updated - Page', currentPostPage, 'of', totalPages, '- Showing', sorted.length, 'students');
    }
    
    // Function to change page in Post-Assessment student list
    function changePostAssessmentPage(page) {
        console.log('[POST-ASSESSMENT] changePostAssessmentPage called with page:', page);
        console.log('[POST-ASSESSMENT] Current page before change:', currentPostPage);
        currentPostPage = page;
        console.log('[POST-ASSESSMENT] Current page after change:', currentPostPage);
        
        // Get current filtered students
        const selectedSession = $('#postAssessmentSessionFilter').val() || 'all';
        const searchQuery = ($('#postStudentSearch').val() || '').trim();
        console.log('[POST-ASSESSMENT] Current session filter:', selectedSession);
        console.log('[POST-ASSESSMENT] Current search query:', searchQuery);
        
        let filteredStudents = allPostStudents.slice();
        
        // Apply session filter
        if (selectedSession && selectedSession !== 'all') {
            filteredStudents = filteredStudents.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === selectedSession;
            });
        }
        
        // Apply search filter
        if (searchQuery.length > 0) {
            filteredStudents = filteredStudents.filter(s => {
                const displayName = (s.SURNAME + ', ' + s.NAME).toLowerCase();
                return displayName.includes(searchQuery.toLowerCase());
            });
        }
        
        // Update display with current page (don't reset page number)
        updatePostStudentListDisplay(filteredStudents, false);
    }

    // Flag to track if post-assessment data has been loaded
    let postAssessmentDataLoaded = false;
    // Cache for individual student post-assessment evaluations to avoid repeated fetches
    // Keyed by INTERN_ID; value stores the full response object used to render
    let postAssessmentEvalCache = {};
    // Invalidate a single student's cached evaluation (call after an update/save if implemented later)
    function invalidatePostAssessmentEvaluation(studentId) {
        if (postAssessmentEvalCache[studentId]) {
            delete postAssessmentEvalCache[studentId];
        }
    }

    // Populate post-assessment student list when tab is activated
    // Load post-assessment students for coordinator
    function loadPostAssessmentStudents() {
        let cdrid = $("#hiddencdrid").val();
        
        if (!cdrid) {
            showPostAssessmentError();
            return;
        }
        
        showPostAssessmentLoading();

        $.ajax({
            url: 'ajaxhandler/coordinatorPostAssessmentAjax.php',
            type: 'POST',
            dataType: 'json',
            data: { coordinator_id: cdrid },
            success: function(response) {
                if (response.success && response.students) {
                    showPostAssessmentContent();
                    if (response.students.length > 0) {
                        allPostStudents = response.students;
                        displayPostAssessmentStudents(allPostStudents);
                    } else {
                        allPostStudents = [];
                        renderEmptyPostAssessmentState('No students assigned to you for post-assessment.');
                    }
                } else {
                    showPostAssessmentContent();
                    allPostStudents = [];
                    renderEmptyPostAssessmentState('No students found for post-assessment.');
                }
                // Mark data as loaded after successful response (regardless of whether students were found)
                postAssessmentDataLoaded = true;
            },
            error: function(xhr, status, error) {
                showPostAssessmentError();
                allPostStudents = [];
                // Don't mark as loaded if there was an error, allow retry
                postAssessmentDataLoaded = false;
            }
        });
    }

    // Render empty state for post-assessment tab
    function renderEmptyPostAssessmentState(message) {
        // Create the full 20/80 layout with empty state message
        // On mobile, this will stack vertically with student list on top
        let html = `<div class='postassessment-main-wrapper flex flex-col md:flex-row w-full'>`;
        html += `<div class='postassessment-student-list-section left-col w-full md:w-1/5 max-w-xs md:pr-4 order-1 mb-4 md:mb-0'>`;
        html += `<div class='mb-4'><input type='text' id='postStudentSearch' placeholder='Search student name' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200' disabled></div>`;
        html += `<div class='mb-4'><select id='postAssessmentSessionFilter' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200' disabled><option value='all'>All Terms</option></select></div>`;
        html += `<div id='postStudentListPanel' class='overflow-y-auto min-h-[400px] max-h-[500px] flex flex-col gap-1'></div>`;
        html += `<div id='postPaginationContainer'></div>`;
        html += `</div>`;
        html += `<div class='postassessment-evaluation-section right-col w-full md:w-4/5 md:pl-4 order-2'>`;
        html += `
            <div class="flex flex-col items-center justify-center h-full">
                <div class="bg-blue-50 rounded-full p-6 mb-4">
                    <svg xmlns='http://www.w3.org/2000/svg' class='h-12 w-12 text-blue-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z' /></svg>
                </div>
                <div class="text-xl font-semibold text-blue-700 mb-2">No Students Available</div>
                <div class="text-gray-500 text-base text-center">${message}</div>
            </div>
        `;
        html += `</div>`;
        html += `</div>`;
        $('#postAssessmentContentArea').html(html);
    }

    $(document).on('click', '#postAssessmentTabBtn', function() {
        selectedPostStudentId = null;
        // Loading state will be shown by the tab handler
    });

    // Function to manually refresh post-assessment data
    window.refreshPostAssessmentData = function() {
        postAssessmentDataLoaded = false;
        selectedPostStudentId = null;
        loadPostAssessmentStudents();
    };

    // Also populate on page load if tab is visible
    $(document).ready(function() {
        if ($('#postAssessmentTabContent').is(':visible')) {
            loadPostAssessmentStudents();
        }
    });

    // Function to display post-assessment students with session filtering (same logic as Pre-Assessment and Review)
    function displayPostAssessmentStudents(students) {
        console.log('[POST-ASSESSMENT] displayPostAssessmentStudents called with data:', students);
        
        // Debug: Check what session data we have
        console.log('[POST-ASSESSMENT] Sample student data:', students.slice(0, 3));
        console.log('[POST-ASSESSMENT] All SESSION_NAME values:', students.map(s => s.SESSION_NAME));
        console.log('[POST-ASSESSMENT] Students with SESSION_NAME:', students.filter(s => s.SESSION_NAME));
        console.log('[POST-ASSESSMENT] Students without SESSION_NAME:', students.filter(s => !s.SESSION_NAME));
        
        // Get unique sessions and sort (most recent first) - same logic as Pre-Assessment and Review
        const sessions = [...new Set((students || []).map(s => s.SESSION_NAME).filter(Boolean))];
        sessions.sort((a, b) => b.localeCompare(a));
        
        console.log('[POST-ASSESSMENT] Extracted sessions:', sessions);
        console.log('[POST-ASSESSMENT] Number of unique sessions:', sessions.length);
        
        // Store data globally for filtering
        allPostStudents = students;
        
        // Filter students by latest session first, then render
        let initialStudents = students;
        if (sessions && sessions.length > 0) {
            const latestSession = sessions[0];
            initialStudents = students.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === latestSession;
            });
            console.log('[POST-ASSESSMENT] Filtered students for latest session:', latestSession, '- Count:', initialStudents.length);
        }
        
        // Initial render with filtered students and populate dropdown
        renderPostStudentList(initialStudents, sessions);
        
        // Session filter change handler (same logic as Pre-Assessment and Review)
        $('#postAssessmentSessionFilter').off('change').on('change', function() {
            console.log('[POST-ASSESSMENT] Session filter changed to:', $(this).val());
            applyPostAssessmentFilters(allPostStudents);
        });
        
        // Also update search handler to work with session filter
        $('#postStudentSearch').off('input').on('input', function() {
            console.log('[POST-ASSESSMENT] Search input changed to:', $(this).val());
            applyPostAssessmentFilters(allPostStudents);
        });
        
        // Pagination click handler using event delegation
        $(document).off('click', '.postassessment-page-btn').on('click', '.postassessment-page-btn', function(e) {
            e.preventDefault();
            console.log('[POST-ASSESSMENT] Pagination button clicked!');
            const page = parseInt($(this).data('page'));
            if (!$(this).prop('disabled') && page > 0) {
                changePostAssessmentPage(page);
            }
        });
    }
    
    // Apply both session and search filters for Post-Assessment 
    function applyPostAssessmentFilters(allStudentsData, resetPage = true) {
        const selectedSession = $('#postAssessmentSessionFilter').val() || 'all';
        const searchQuery = ($('#postStudentSearch').val() || '').trim();
        
        console.log('[POST-ASSESSMENT] Applying filters - Session:', selectedSession, 'Search:', searchQuery);
        console.log('[POST-ASSESSMENT] Total students before filtering:', allStudentsData.length);
        
        let filtered = allStudentsData.slice();
        
        // Apply session filter first
        if (selectedSession && selectedSession !== 'all') {
            console.log('[POST-ASSESSMENT] Filtering by session:', selectedSession);
            console.log('[POST-ASSESSMENT] Sample student sessions:', allStudentsData.slice(0, 3).map(s => s.SESSION_NAME));
            
            filtered = filtered.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                const matches = studentSession === selectedSession;
                if (!matches) {
                    console.log('[POST-ASSESSMENT] Student session mismatch:', studentSession, 'vs', selectedSession);
                }
                return matches;
            });
            console.log('[POST-ASSESSMENT] After session filter:', filtered.length, 'students');
        }
        
        // Apply search filter
        if (searchQuery.length > 0) {
            // Store search value for restoration
            window.postStudentSearchValue = searchQuery;
            window.shouldRefocusPostSearch = true;
            
            filtered = filtered.filter(s => {
                const displayName = (s.SURNAME + ', ' + s.NAME).toLowerCase();
                return displayName.includes(searchQuery.toLowerCase());
            });
            console.log('[POST-ASSESSMENT] After search filter:', filtered.length, 'students');
        }
        
        // Update display; optionally reset page (default true for filter changes)
        updatePostStudentListDisplay(filtered, resetPage);
        
        // Restore search input focus if it was focused (dropdown value is already correct)
        const $input = $('#postStudentSearch');
        if ($input.length > 0) {
            $input.val(searchQuery);
            if (document.activeElement === $input[0]) {
                $input.focus();
            }
        }
    }

    // Search filter for post-assessment student list (updated to work with session filtering)
    $(document).on('input', '#postStudentSearch', function() {
        const query = $(this).val();
        window.postStudentSearchValue = query;
        window.shouldRefocusPostSearch = true;
        applyPostAssessmentFilters(allPostStudents);
    });

    // Handle student selection in Post-Assessment tab
    $(document).on('click', '.postassessment-student-item', function() {
    selectedPostStudentId = $(this).data('studentid');
    // Re-render student list to update highlight, preserving search value
    applyPostAssessmentFilters(allPostStudents, false);
    loadPostAssessmentEvaluation(selectedPostStudentId);
// Load and display post-assessment evaluation for selected student
function loadPostAssessmentEvaluation(studentId) {
    // If cached, render immediately without flicker
    if (postAssessmentEvalCache[studentId]) {
        renderPostAssessmentEvaluation(postAssessmentEvalCache[studentId]);
        return;
    }
    // Otherwise show enhanced loading skeleton then fetch
    const loadingHtml = `
        <div class="flex flex-col items-center py-10 animate-fade-in">
            <div class="mb-4">
                <div class="w-14 h-14 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
            </div>
            <div class="text-gray-600 font-medium mb-6">Loading post-assessment evaluation...</div>
            <div class="w-full max-w-2xl space-y-4">
                ${[1,2,3].map(i => `
                    <div class='bg-white rounded-lg shadow p-4 border border-gray-100'>
                        <div class='h-5 w-40 bg-gray-200 rounded mb-3 animate-pulse'></div>
                        <div class='space-y-2'>
                            <div class='h-4 w-full bg-gray-100 rounded animate-pulse'></div>
                            <div class='h-4 w-5/6 bg-gray-100 rounded animate-pulse'></div>
                            <div class='h-4 w-2/3 bg-gray-100 rounded animate-pulse'></div>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>`;
    $('#postEvalList').html(loadingHtml);
    $.ajax({
        url: 'ajaxhandler/coordinatorPostAssessmentAjax.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'getStudentPostAssessment', interns_id: studentId },
        success: function(response) {
            if (response.success) {
                // Cache raw response for future reuse
                postAssessmentEvalCache[studentId] = response;
                renderPostAssessmentEvaluation(response);
            } else {
                showPostAssessmentContent();
                $('#postAssessmentContentArea').html('<div class="flex flex-col items-center justify-center py-16 text-center"><div class="text-gray-500">No evaluation data found.</div></div>');
            }
        },
        error: function() {
            showPostAssessmentError();
        }
    });
}
    });
// Helper to render a post-assessment evaluation response (cached or fresh)
function renderPostAssessmentEvaluation(response) {
    const records = response.records || [];
    const hasAnyRecords = records.length > 0;
    const selfRatings = records.filter(r => r.self_rating !== null && r.self_rating !== '' && r.self_rating !== undefined);
    const supervisorRatings = records.filter(r => r.supervisor_rating !== null && r.supervisor_rating !== '' && r.supervisor_rating !== undefined);
    const studentCompleted = hasAnyRecords && selfRatings.length > 0;
    const supervisorCompleted = response.hasSupervisorRating && supervisorRatings.length > 0;

    if (!hasAnyRecords) {
        $('#postEvalList').html(`
            <div class='flex flex-col items-center justify-center py-12'>
                <div class='bg-red-50 rounded-full p-5 mb-4'>
                    <svg xmlns='http://www.w3.org/2000/svg' class='h-10 w-10 text-red-500' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4m0 4h.01M12 2a10 10 0 100 20 10 10 0 000-20z' />
                    </svg>
                </div>
                <div class='text-xl font-semibold text-red-600 mb-2'>Post-Assessment Not Started</div>
                <p class='text-gray-600 max-w-md text-center'>This student has not yet begun the post-assessment. Once the student submits their self ratings, you will be able to view them here.</p>
            </div>`);
        return;
    }

    if (!studentCompleted) {
        $('#postEvalList').html(`
            <div class='flex flex-col items-center justify-center py-12'>
                <div class='bg-yellow-50 rounded-full p-5 mb-4'>
                    <svg xmlns='http://www.w3.org/2000/svg' class='h-10 w-10 text-yellow-500' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4m0 4h.01M12 2a10 10 0 100 20 10 10 0 000-20z' />
                    </svg>
                </div>
                <div class='text-xl font-semibold text-yellow-600 mb-2'>Awaiting Student Submission</div>
                <p class='text-gray-600 max-w-md text-center'>The post-assessment form is open but the student has not provided self ratings yet. Encourage the student to complete their post-assessment.</p>
            </div>`);
        return;
    }

    const grouped = {};
    records.forEach(function(rec) {
        if (!grouped[rec.category]) grouped[rec.category] = [];
        grouped[rec.category].push(rec);
    });
    let html = `<div class="post-eval-container max-h-[700px] overflow-y-auto pr-2">
        <h3 class="text-2xl font-bold text-blue-700 mb-4">Post-Evaluation</h3>`;
    if (!supervisorCompleted) {
        html += `<div class='mb-6 bg-blue-50 border border-blue-200 rounded p-4 flex items-start gap-3'>
            <svg xmlns='http://www.w3.org/2000/svg' class='h-6 w-6 text-blue-500 flex-shrink-0' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M12 18a6 6 0 100-12 6 6 0 000 12z' />
            </svg>
            <div class='text-sm text-blue-700'>Student self ratings received. Awaiting supervisor evaluation.</div>
        </div>`;
    }
    Object.keys(grouped).forEach(function(category) {
        html += `<div class="mb-8">
            <h4 class="post-eval-category-header text-lg font-semibold text-gray-800 mb-3 border-b border-gray-200 pb-1">${category}</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg shadow border border-gray-200">
                    <thead class="bg-blue-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-bold text-blue-700">Disciplines/Task</th>
                            <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-32">Self Rating</th>
                            <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-40">${supervisorCompleted ? 'Supervisor Rating' : 'Supervisor Rating (Pending)'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${grouped[category].map(rec => `
                            <tr class="hover:bg-blue-50">
                                <td class="px-4 py-2 text-gray-700">${rec.question_text ?? rec.question_id}</td>
                                <td class="px-4 py-2 text-center text-blue-600 font-semibold w-32">${rec.self_rating ?? ''}</td>
                                <td class="px-4 py-2 text-center ${rec.supervisor_rating ? 'text-green-600 font-semibold' : 'text-gray-400 italic'} w-40">${rec.supervisor_rating ?? (supervisorCompleted ? '' : '—')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>`;
    });
    const firstComment = records.find(rec => rec.comment && rec.comment.trim() !== '');
    if (firstComment) {
        html += `<div class="mt-6">
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg shadow">
                <div class="font-bold text-yellow-700 mb-2">Comment/Recommendation</div>
                <div class="text-gray-800 text-base">${firstComment.comment}</div>
            </div>
        </div>`;
    }
    html += `</div>`;
    $('#postEvalList').html(html);
}
    // Evaluation tab bar navigation
    $('#evalQuestionsTabBtn, #rateTabBtn, #postAssessmentTabBtn, #reviewTabBtn').click(function() {
        // Remove active class from all tab buttons
        $('#evalQuestionsTabBtn, #rateTabBtn, #postAssessmentTabBtn, #reviewTabBtn').removeClass('active');
        
        // Add active class to clicked button
        $(this).addClass('active');

        // Hide all tab contents first
    $('#evalQuestionsTabContent, #rateTabContent, #postAssessmentTabContent, #reviewTabContent').hide();

        // Show the selected tab content
        if (this.id === 'evalQuestionsTabBtn') {
            $('#evalQuestionsTabContent').show();
            // Questions are now loaded via mainDashboard.php's loadQuestionCategories system
        } else if (this.id === 'rateTabBtn') {
            $('#rateTabContent').show();
            // Show loading state and load pre-assessment data
            showPreAssessmentLoading();
            // Single attempt; actual loader lives in inner tab logic (avoids retry spam)
            loadPreAssessmentData();
        } else if (this.id === 'postAssessmentTabBtn') {
            $('#postAssessmentTabContent').show();
            // Only load data if it hasn't been loaded before
            if (!postAssessmentDataLoaded) {
                // Show loading state and load post-assessment data
                showPostAssessmentLoading();
                setTimeout(function() {
                    loadPostAssessmentData();
                }, 300);
            } else {
                // Data already loaded, just show the content
                showPostAssessmentContent();
            }
        } else if (this.id === 'reviewTabBtn') {
            $('#reviewTabContent').show();
            // Show loading state and load review data
            showReviewLoading();
            setTimeout(function() {
                loadReviewData();
            }, 300);
        // Removed stats tab logic
        }
    });

    // Helper functions for evaluation loading states
    function showPreAssessmentLoading() {
        $('#preAssessmentLoadingState').removeClass('hidden');
        $('#preAssessmentErrorState').addClass('hidden');
        $('#preAssessmentContent').addClass('hidden');
    }
    
    function showPreAssessmentError() {
        $('#preAssessmentLoadingState').addClass('hidden');
        $('#preAssessmentErrorState').removeClass('hidden');
        $('#preAssessmentContent').addClass('hidden');
    }
    
    function showPreAssessmentContent() {
        $('#preAssessmentLoadingState').addClass('hidden');
        $('#preAssessmentErrorState').addClass('hidden');
        $('#preAssessmentContent').removeClass('hidden');
    }

    // Expose helpers globally for later closures referencing these functions
    window.showPreAssessmentLoading = showPreAssessmentLoading;
    window.showPreAssessmentError = showPreAssessmentError;
    window.showPreAssessmentContent = showPreAssessmentContent;
    
    function showPostAssessmentLoading() {
        $('#postAssessmentLoadingState').removeClass('hidden');
        $('#postAssessmentErrorState').addClass('hidden');
        $('#postAssessmentContentArea').addClass('hidden');
    }
    
    function showPostAssessmentError() {
        $('#postAssessmentLoadingState').addClass('hidden');
        $('#postAssessmentErrorState').removeClass('hidden');
        $('#postAssessmentContentArea').addClass('hidden');
    }
    
    function showPostAssessmentContent() {
        $('#postAssessmentLoadingState').addClass('hidden');
        $('#postAssessmentErrorState').addClass('hidden');
        $('#postAssessmentContentArea').removeClass('hidden');
    }
    
    function showReviewLoading() {
        $('#reviewLoadingState').removeClass('hidden');
        $('#reviewErrorState').addClass('hidden');
        $('#reviewContentArea').addClass('hidden');
    }
    
    function showReviewError() {
        $('#reviewLoadingState').addClass('hidden');
        $('#reviewErrorState').removeClass('hidden');
        $('#reviewContentArea').addClass('hidden');
    }
    
    function showReviewContent() {
        $('#reviewLoadingState').addClass('hidden');
        $('#reviewErrorState').addClass('hidden');
        $('#reviewContentArea').removeClass('hidden');
    }
    
    // Placeholder loading functions for evaluation tabs
    // Pre-Assessment loader: single attempt (will load again via inner tab handler once defined)
    function loadPreAssessmentData() {
        if (typeof loadPreassessmentStudentList === 'function') {
            loadPreassessmentStudentList();
        } else if (typeof window.loadPreassessmentStudentList === 'function') {
            window.loadPreassessmentStudentList();
        } else {
            console.warn('[Pre-Assessment] student loader not available yet (inner tab click will handle).');
        }
    }
    
    function loadPostAssessmentData() {
        loadPostAssessmentStudents();
    }
    
    function loadReviewData() {
        // Simulate loading delay
        setTimeout(() => {
            showReviewContent();
        }, 1000);
    }
    
    // Retry button handlers
    $(document).on('click', '#retryLoadPreAssessment', function() {
        loadPreAssessmentData();
    });
    
    $(document).on('click', '#retryLoadPostAssessment', function() {
        loadPostAssessmentData();
    });
    
    $(document).on('click', '#retryLoadReview', function() {
        loadReviewData();
    });


    // Show default tab on page load
    $('#evalQuestionsTabContent').show();
    $('#rateTabContent, #postAssessmentTabContent, #reviewTabContent').hide();
    
    // Load all evaluation content on page load
    loadAllEvaluationContent();
    
    // Unified function to load all evaluation tab content on page load
    function loadAllEvaluationContent() {
        // Note: Student lists are now loaded on-demand when tabs are clicked
        // This prevents double-loading and duplicate student lists
        
        // Load post-assessment student list (already loads on tab click, but let's ensure it's loaded)
        // Note: Post-assessment loads when tab is clicked, we don't need to load it here
        // as it's handled separately and may conflict with tab switching
    }
    
    // --- Prediction Tab Logic ---
    // Load students for prediction tab on tab switch or page load
    let predictionLoading = false; // Flag to prevent duplicate loading

    // Populate Prediction term dropdown and build mapping of INTERN_ID -> SESSION_NAME
    function loadPredictionTerms(callback) {
        const coordinatorId = $('#hiddencdrid').val();
        if (!coordinatorId) { if (typeof callback === 'function') callback(); return; }
        $.ajax({
            url: 'ajaxhandler/attendanceAJAX.php',
            type: 'POST',
            dataType: 'json',
            data: { cdrid: coordinatorId, action: 'getAllStudentsUnderCoordinator' },
            success: function(resp) {
                const students = (resp && (resp.students || resp.data)) || [];
                const sessions = [...new Set(students.map(s => s.SESSION_NAME).filter(Boolean))];
                sessions.sort((a,b) => b.localeCompare(a));
                let opts = '<option value="all">All Terms</option>';
                sessions.forEach(s => { opts += `<option value="${s}">${s}</option>`; });
                $('#predictionTermFilter').html(opts);
                if (sessions.length > 0) { $('#predictionTermFilter').val(sessions[0]); }
                window.predictionStudentSessionMap = {};
                students.forEach(s => { if (s.INTERNS_ID) window.predictionStudentSessionMap[s.INTERNS_ID] = s.SESSION_NAME || ''; });
                if (typeof callback === 'function') callback();
            },
            error: function() {
                $('#predictionTermFilter').html('<option value="all">All Terms</option>');
                window.predictionStudentSessionMap = {};
                if (typeof callback === 'function') callback();
            }
        });
    }
    
    function loadPredictionStudents() {
        if (predictionLoading) {
            console.log('[PREDICTION] Already loading, skipping duplicate request');
            return;
        }
        // Serve from cache when available to avoid refetch on term change
        const cdrid = $('#hiddencdrid').val() || '';
        const cacheKey = `prediction_${cdrid}`;
        if (isCacheValid('prediction', cacheKey) && dataCache.predictionStudentsByCoordinator[cacheKey]) {
            let students = dataCache.predictionStudentsByCoordinator[cacheKey] || [];
            const selectedTerm = ($('#predictionTermFilter').val() || 'all').trim();
            if (selectedTerm !== 'all' && window.predictionStudentSessionMap) {
                students = students.filter(function(stu){
                    const sess = window.predictionStudentSessionMap[stu.INTERNS_ID] || '';
                    return sess === selectedTerm;
                });
            }
            if (!students || students.length === 0) {
                $('#predictionTable tbody').html('<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No students assigned to you for prediction.</td></tr>');
                window.predictionStudents = [];
                return;
            }
            let tbody = '';
            students.forEach(function(student, idx) {
                const sessName = (window.predictionStudentSessionMap && window.predictionStudentSessionMap[student.INTERNS_ID]) || '';
                let statusDisplay = student.STATUS === 'Rated' 
                    ? '<span class="font-semibold text-green-600">Rated</span>'
                    : '<span class="font-semibold text-red-600" title="Missing: ' + (student.missing || []).join(', ') + '">Not Rated</span>';
                let hasPrediction = student.pre_assessment && student.pre_assessment.ojt_placement;
                let placementText;
                if (hasPrediction) {
                    placementText = `<span class=\"inline-block bg-green-100 text-green-700 font-bold px-2 md:px-3 py-1 rounded-full text-xs\">${student.pre_assessment.ojt_placement}</span>`;
                } else if (student.valid) {
                    placementText = `<span class=\"inline-block bg-blue-100 text-blue-700 font-bold px-2 md:px-3 py-1 rounded-full text-xs\">Ready for Prediction</span>`;
                } else {
                    placementText = `<span class=\"inline-block bg-gray-100 text-gray-500 font-bold px-2 md:px-3 py-1 rounded-full text-xs\">Incomplete Data</span>`;
                }
                let analysisData = {};
                if (hasPrediction) {
                    analysisData = {
                        placement: student.pre_assessment.ojt_placement,
                        reasoning: student.pre_assessment.prediction_reasoning || "",
                        probabilities: student.pre_assessment.prediction_probabilities ? JSON.parse(student.pre_assessment.prediction_probabilities) : {},
                        confidence: student.pre_assessment.prediction_confidence
                    };
                } else if (student.valid) {
                    analysisData = { ready: true, message: "This student has complete data and is ready for ML prediction. Click 'Run ML Prediction' to generate placement recommendation.", studentData: student };
                } else {
                    analysisData = { error: "Incomplete Data", message: "This student has not completed their pre-assessment or is missing required academic grades. Please ensure all required data is available before running predictions." };
                }
                let analysisBtnClass;
                if (!student.valid) analysisBtnClass = 'bg-gray-400 hover:bg-gray-500';
                else if (hasPrediction) analysisBtnClass = 'bg-green-600 hover:bg-green-700';
                else analysisBtnClass = 'bg-blue-600 hover:bg-blue-700';
                tbody += `<tr data-row=\"${idx}\" data-session=\"${sessName}\" class=\"hover:bg-blue-50 transition\">`+
                    `<td class=\"px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm font-medium text-gray-900\">${student.NAME}</td>`+
                    `<td class=\"px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm text-gray-700\">${student.HTE_ASSIGNED || ''}</td>`+
                    `<td class=\"px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm\">${statusDisplay}</td>`+
                    `<td class=\"px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm\">${placementText}</td>`+
                    `<td class=\"px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm\">`+
                    `<button class=\"analysis-btn ${analysisBtnClass} text-white px-2 md:px-4 py-1 md:py-2 rounded-lg shadow transition text-xs md:text-sm\" data-analysis=\"${encodeURIComponent(JSON.stringify(analysisData))}\">Analysis</button>`+
                    `</td></tr>`;
            });
            $('#predictionTable tbody').html(tbody);
            window.predictionStudents = students;
            return;
        }
        
        predictionLoading = true;
        $('#predictionSpinner').show();
        $.ajax({
            url: 'ajaxhandler/predictionAjax.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                predictionLoading = false; // Reset loading flag
                $('#predictionSpinner').hide();
                if (!response.success) {
                    alert('Error: ' + response.error);
                    return;
                }
                let students = response.students || [];
                // Cache full set for coordinator
                dataCache.predictionStudentsByCoordinator[cacheKey] = students.slice();
                dataCache.lastUpdated.predictionStudentsByCoordinator[cacheKey] = Date.now();
                // Apply selected term filter using mapping
                const selectedTerm = ($('#predictionTermFilter').val() || 'all').trim();
                if (selectedTerm !== 'all' && window.predictionStudentSessionMap) {
                    students = students.filter(function(stu){
                        const sess = window.predictionStudentSessionMap[stu.INTERNS_ID] || '';
                        return sess === selectedTerm;
                    });
                }
                
                // Handle empty student list
                if (!students || students.length === 0) {
                    $('#predictionTable tbody').html('<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No students assigned to you for prediction.</td></tr>');
                    window.predictionStudents = [];
                    return;
                }
                
                let tbody = '';
                students.forEach(function(student, idx) {
                    const sessName = (window.predictionStudentSessionMap && window.predictionStudentSessionMap[student.INTERNS_ID]) || '';
                        // Color-coded status text instead of icons
                        let statusDisplay = student.STATUS === 'Rated' 
                            ? '<span class="font-semibold text-green-600">Rated</span>'
                            : '<span class="font-semibold text-red-600" title="Missing: ' + student.missing.join(', ') + '">Not Rated</span>';
                        let hasPrediction = student.pre_assessment && student.pre_assessment.ojt_placement;
                        let placementText;
                        
                        if (hasPrediction) {
                            // Student has completed prediction
                            placementText = `<span class="inline-block bg-green-100 text-green-700 font-bold px-2 md:px-3 py-1 rounded-full text-xs">
                                ${student.pre_assessment.ojt_placement}
                            </span>`;
                        } else if (student.valid) {
                            // Student has complete data but no prediction yet
                            placementText = `<span class="inline-block bg-blue-100 text-blue-700 font-bold px-2 md:px-3 py-1 rounded-full text-xs">
                                Ready for Prediction
                            </span>`;
                        } else {
                            // Student has incomplete data
                            placementText = `<span class="inline-block bg-gray-100 text-gray-500 font-bold px-2 md:px-3 py-1 rounded-full text-xs">
                                Incomplete Data
                            </span>`;
                        }
                        let analysisData = {};
                        if (hasPrediction) {
                            analysisData = {
                                placement: student.pre_assessment.ojt_placement,
                                reasoning: student.pre_assessment.prediction_reasoning || "",
                                probabilities: student.pre_assessment.prediction_probabilities ? JSON.parse(student.pre_assessment.prediction_probabilities) : {},
                                confidence: student.pre_assessment.prediction_confidence
                            };
                        } else if (student.valid) {
                            // Student ready for prediction
                            analysisData = {
                                ready: true,
                                message: "This student has complete data and is ready for ML prediction. Click 'Run ML Prediction' to generate placement recommendation.",
                                studentData: student
                            };
                        } else {
                            // For incomplete data, provide proper error message for analysis modal
                            analysisData = {
                                error: "Incomplete Data",
                                message: "This student has not completed their pre-assessment or is missing required academic grades. Please ensure all required data is available before running predictions."
                            };
                        }
                        
                        // Set button colors based on student status and data completeness
                        let analysisBtnClass;
                        if (!student.valid) {
                            // Incomplete data - gray button
                            analysisBtnClass = 'bg-gray-400 hover:bg-gray-500';
                        } else if (hasPrediction) {
                            // Has prediction - green button
                            analysisBtnClass = 'bg-green-600 hover:bg-green-700';
                        } else {
                            // Ready for prediction - blue button
                            analysisBtnClass = 'bg-blue-600 hover:bg-blue-700';
                        }
                        tbody += `<tr data-row="${idx}" data-session="${sessName}" class="hover:bg-blue-50 transition">
                            <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm font-medium text-gray-900">${student.NAME}</td>
                            <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm text-gray-700">${student.HTE_ASSIGNED}</td>
                            <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm">
                                ${statusDisplay}
                            </td>
                            <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm">${placementText}</td>
                            <td class="px-3 md:px-6 py-3 md:py-4 whitespace-nowrap text-xs md:text-sm">
                                <button class="analysis-btn ${analysisBtnClass} text-white px-2 md:px-4 py-1 md:py-2 rounded-lg shadow transition text-xs md:text-sm" data-analysis="${encodeURIComponent(JSON.stringify(analysisData))}">
                                    Analysis
                                </button>
                            </td>
                        </tr>`;
                });
                $('#predictionTable tbody').html(tbody);
                // Store students for later prediction
                window.predictionStudents = students;
            },
            error: function() {
                predictionLoading = false; // Reset loading flag
                $('#predictionSpinner').hide();
                alert('Failed to fetch students for prediction.');
            }
        });
    }

    // Load students when prediction tab is shown (use .off() to prevent duplicate bindings)
    $('#predictionTab').off('click.predictionLoader').on('click.predictionLoader', function() {
        console.log('[PREDICTION] Tab clicked, loading term options and students...');
        loadPredictionTerms(function(){ loadPredictionStudents(); });
    });

    // Run prediction and update database
    $(document).on('click', '#runPredictionBtn', function() {
        if (!window.predictionStudents) return;
        $('#predictionSpinner').show();
        let totalStudents = window.predictionStudents.length;
        let processedCount = 0;

        // Count students who need predictions
        let studentsNeedingPrediction = window.predictionStudents.filter(student => {
            let hasPrediction = student.pre_assessment && student.pre_assessment.ojt_placement;
            return student.valid && !hasPrediction;
        }).length;
        
        if (studentsNeedingPrediction === 0) {
            $('#predictionSpinner').hide();
            alert('All eligible students already have predictions.');
            return;
        }

        window.predictionStudents.forEach(function(student, idx) {
            // Only run predictions for students who are valid AND don't already have predictions
            let hasPrediction = student.pre_assessment && student.pre_assessment.ojt_placement;
            if (student.valid && !hasPrediction) {
                // Send all data to Flask - let Flask filter what it needs for ML vs reasoning
                const allData = student.pre_assessment;
                
                console.log('Sending to Flask:', allData);
                
                // Use PHP proxy for Flask API (works in both local and production)
                const apiUrl = window.location.hostname === 'localhost' 
                    ? 'http://localhost:5000/predict'  // Direct to Flask in development
                    : 'api/predict.php';               // Use PHP proxy in production
                
                $.ajax({
                    url: apiUrl,
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(allData),
                    success: function(mlres) {
                        // Validate ML response
                        if (!mlres || !mlres.placement) {
                            console.error('Invalid ML response:', mlres);
                            let row = $('#predictionTable tbody tr[data-row="' + idx + '"]');
                            row.find('.predicted-placement').text('Error');
                            row.find('.analysis-btn').css({'background-color':'red','color':'white'});
                            return;
                        }

                        // Validate required fields first
                        if (!mlres.placement || !mlres.reasoning || !mlres.probabilities) {
                            console.error('Missing required data in ML response:', mlres);
                            let row = $('#predictionTable tbody tr[data-row="' + idx + '"]');
                            row.find('.predicted-placement').text('Error: Missing Data');
                            row.find('.analysis-btn').css({'background-color':'red','color':'white'});
                            return;
                        }

                        let predicted = mlres.placement;
                        let row = $('#predictionTable tbody tr[data-row="' + idx + '"]');
                        row.find('.predicted-placement').text(predicted);
                        
                        // Store analysis data for modal
                        row.find('.analysis-btn').attr('data-analysis', encodeURIComponent(JSON.stringify(mlres)));
                        row.find('.analysis-btn').css({'background-color':'green','color':'white'});

                        // Prepare prediction data with required fields
                        let predictionData = {
                            action: 'savePrediction',
                            student_id: student.STUDENT_ID,
                            ojt_placement: mlres.placement,
                            prediction_reasoning: mlres.reasoning,
                            prediction_probabilities: JSON.stringify(mlres.probabilities)
                        };
                        
                        // Log the data being sent
                        console.log('Sending prediction data:', predictionData);

                        // Add grade fields from pre_assessment selectively
                        const preAssessmentData = { ...student.pre_assessment };
                        
                        // Remove fields that shouldn't override prediction data
                        delete preAssessmentData.ojt_placement;
                        delete preAssessmentData.prediction_reasoning;
                        delete preAssessmentData.prediction_probabilities;
                        delete preAssessmentData.action;
                        
                        // Add the grades data
                        Object.assign(predictionData, preAssessmentData);
                        
                        // Save to database
                        $.ajax({
                            url: 'ajaxhandler/predictionAjax.php',
                            type: 'POST',
                            data: predictionData,
                            dataType: 'json',
                            success: function(resp) {
                                processedCount++;
                                if (!resp.success) {
                                    console.error("Failed to save prediction for student", student.STUDENT_ID, ":", resp.error);
                                }
                                // Hide spinner when all students needing prediction are processed
                                if (processedCount === studentsNeedingPrediction) {
                                    $('#predictionSpinner').hide();
                                    // Reload the student list to refresh the data
                                    loadPredictionStudents();
                                }
                            },
                            error: function(xhr, status, error) {
                                processedCount++;
                                console.error("Error saving prediction for student", student.STUDENT_ID, ":", error);
                                // Hide spinner when all students needing prediction are processed
                                if (processedCount === studentsNeedingPrediction) {
                                    $('#predictionSpinner').hide();
                                    // Reload the student list to refresh the data
                                    loadPredictionStudents();
                                }
                            }
                        });
                    },
                    error: function() {
                        let row = $('#predictionTable tbody tr[data-row="' + idx + '"]');
                        row.find('.predicted-placement').html('<span style="color:red;">ML Error</span>');
                        row.find('.analysis-btn').data('analysis', {error: 'ML Error'});
                        row.find('.analysis-btn').css({'background-color':'gray','color':'white'});
                    }
                });
            } else {
                let row = $('#predictionTable tbody tr[data-row="' + idx + '"]');
                row.find('.predicted-placement').html('<span style="color:gray;">Incomplete Data</span>');
                row.find('.analysis-btn').data('analysis', {error: 'Incomplete Data'});
                row.find('.analysis-btn').css({'background-color':'gray','color':'white'});
            }
        });
        $('#predictionSpinner').hide();
    });

    // Show analysis modal when Analysis button is clicked
    $(document).on('click', '.analysis-btn', function() {
        let analysisRaw = $(this).attr('data-analysis');
        let analysis = {};
        try {
            analysis = analysisRaw ? JSON.parse(decodeURIComponent(analysisRaw)) : {};
            if (analysis.probabilities && !analysis.confidence) {
                // Set confidence as the highest probability value
                analysis.confidence = Math.max(...Object.values(analysis.probabilities).map(Number));
            }
        } catch (e) {
            analysis = {};
        }
        let html = '';
        if (!analysis || analysis.error) {
            // Show detailed error message for incomplete data
            let errorTitle = analysis && analysis.error ? analysis.error : 'No Analysis Available';
            let errorMessage = analysis && analysis.message ? analysis.message : 'Unable to generate prediction analysis.';
            
            html = `
                <div class="text-center py-8">
                    <div class="mb-4">
                        <svg class="mx-auto h-16 w-16 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-red-600 mb-2">${errorTitle}</h3>
                    <p class="text-gray-600 text-sm max-w-md mx-auto leading-relaxed">${errorMessage}</p>
                    <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4 max-w-md mx-auto">
                        <p class="text-yellow-800 text-sm font-medium">💡 To enable prediction analysis:</p>
                        <ul class="text-yellow-700 text-sm mt-2 text-left list-disc list-inside space-y-1">
                            <li>Complete the pre-assessment evaluation</li>
                            <li>Ensure all academic grades are available</li>
                            <li>Verify soft skills and communication ratings</li>
                        </ul>
                    </div>
                </div>
            `;
        } else {
            // Highlight grades in reasoning text (more prominent)
            function highlightGrades(text) {
                // Match grades with or without decimals (e.g., SP 101: 94 or SP 101: 94.5)
                // Use a glowing effect to make grades stand out
                return text.replace(
                    /([A-Z]{2,} \d{3}: \d{1,3}(?:\.\d+)?)/g,
                    '<span style="background: #fffbe6; color: #222; font-weight: bold; padding: 2px 8px; border-radius: 6px; box-shadow: 0 0 8px 2px #ffe066, 0 0 2px 1px #ffd700; text-shadow: 0 0 6px #ffe066;">$1</span>'
                );
            }
            // Placement badge color
            const placementColors = {
                'Business Operations': 'bg-yellow-100 text-yellow-800',
                'Technical Support': 'bg-green-100 text-green-800',
                'Systems Development': 'bg-blue-100 text-blue-800',
                'Research': 'bg-purple-100 text-purple-800',
            };
            // Reasoning section - visually emphasized
            let reasoningHtml = '';
            if (analysis.reasoning) {
                // Always process reasoning text with highlightGrades
                reasoningHtml = `<div class="mb-3 md:mb-4 p-3 md:p-4 rounded-lg md:rounded-xl border-2 border-blue-200 bg-blue-50 shadow-sm"><div class="font-bold text-blue-700 mb-2 text-sm md:text-base lg:text-lg">Reasoning</div><div class="text-gray-800 text-sm md:text-base break-words">${highlightGrades(analysis.reasoning)}</div></div>`;
            }
            // Probability section - smaller badges, more spacing
            let probabilityHtml = '';
            if (analysis.probabilities && Object.keys(analysis.probabilities).length > 0) {
                probabilityHtml = `<div class="mb-3 md:mb-4"><div class="font-bold text-gray-700 mb-2 text-sm md:text-base">Probability Breakdown</div><div class="grid grid-cols-1 sm:grid-cols-2 gap-2 md:gap-3">${Object.entries(analysis.probabilities).map(([k, v]) => {
                    let color = 'bg-gray-200 text-gray-800';
                    if (k === 'Business Operations') color = 'bg-yellow-100 text-yellow-800';
                    else if (k === 'Technical Support') color = 'bg-green-100 text-green-800';
                    else if (k === 'Systems Development') color = 'bg-blue-100 text-blue-800';
                    else if (k === 'Research') color = 'bg-purple-100 text-purple-800';
                    return `<div class="flex items-center justify-between px-2 md:px-3 py-2 md:py-2 rounded text-xs md:text-sm font-semibold ${color}"><span class="truncate mr-2">${k}</span><span class="flex-shrink-0">${v}%</span></div>`;
                }).join('')}</div></div>`;
            }
            // Probability explanation
            let probExpHtml = '';
            if (analysis.prob_explanation && analysis.prob_explanation !== 'undefined') {
                probExpHtml = `<div class="mb-2 md:mb-3"><div class="font-bold text-gray-700 mb-1 text-sm md:text-base">Probability Explanation</div><div class="text-gray-800 text-sm md:text-base break-words">${analysis.prob_explanation}</div></div>`;
            }
            html = `
                <div class="space-y-4 md:space-y-6 lg:space-y-8">
                    <div class="flex flex-col items-center mb-2 md:mb-4">
                        <div class="text-base md:text-xl lg:text-2xl font-bold text-gray-700 mb-2 text-center">Predicted OJT Placement</div>
                        ${analysis.placement ? `<span class="inline-block px-3 py-2 md:px-5 md:py-3 lg:px-6 lg:py-3 rounded-full font-extrabold text-sm md:text-lg lg:text-2xl ${placementColors[analysis.placement] || 'bg-gray-100 text-gray-800'} text-center">${analysis.placement}</span>` : ''}
                    </div>
                    ${reasoningHtml}
                    ${probExpHtml}
                    ${probabilityHtml}
                </div>
            `;
        }
    if ($('#analysisModal').length === 0) {
        $('body').append(`
            <div id="analysisModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4" style="display:none; overflow-y:auto;">
                <div class="bg-white rounded-lg md:rounded-3xl shadow-2xl p-4 md:p-8 lg:p-16 max-w-6xl w-full relative my-auto max-h-[95vh] overflow-y-auto">
                    <button class="absolute top-2 right-2 md:top-4 md:right-4 lg:top-6 lg:right-6 text-gray-500 hover:text-gray-700 text-xl md:text-2xl font-bold z-10 bg-white rounded-full w-8 h-8 md:w-10 md:h-10 flex items-center justify-center shadow-md" id="closeAnalysisModal">&times;</button>
                    <h2 class="text-xl md:text-2xl lg:text-4xl font-extrabold text-blue-700 mb-4 md:mb-6 lg:mb-8 text-center pr-8 md:pr-12">Prediction Analysis</h2>
                    <div class="main-dashboard-modal-body" id="analysisModalContent"></div>
                </div>
            </div>
        `);
    }
    $('#analysisModalContent').html(html);
    $('#analysisModal').fadeIn();
    // Prevent body scroll when modal is open
    $('body').css('overflow', 'hidden');
});

$(document).on('click', '#closeAnalysisModal', function() {
    $('#analysisModal').fadeOut();
    // Restore body scroll when modal is closed
    $('body').css('overflow', '');
});

// Close modal when clicking outside of it
$(document).on('click', '#analysisModal', function(e) {
    if ($(e.target).attr('id') === 'analysisModal') {
        $('#analysisModal').fadeOut();
        // Restore body scroll when modal is closed
        $('body').css('overflow', '');
    }
});
    // Make prediction loaders available globally for external listeners
    window.loadPredictionStudents = loadPredictionStudents;
    if (typeof loadPredictionTerms === 'function') {
        window.loadPredictionTerms = loadPredictionTerms;
    }
});

    // --- Prediction Tab Filter Functionality ---
    let currentPredictionFilter = 'all'; // Track current filter state

    // Filter button click handlers
    $(document).on('click', '#filterAll', function() {
        currentPredictionFilter = 'all';
        updateFilterButtonStyles();
        filterPredictionTable();
    });

    $(document).on('click', '#filterReady', function() {
        currentPredictionFilter = 'ready';
        updateFilterButtonStyles();
        filterPredictionTable();
    });

    $(document).on('click', '#filterNotReady', function() {
        currentPredictionFilter = 'not-ready';
        updateFilterButtonStyles();
        filterPredictionTable();
    });

    // Update filter button styles
    function updateFilterButtonStyles() {
        // Reset all buttons to inactive style
        $('#filterAll, #filterReady, #filterNotReady').removeClass('bg-gray-600 bg-green-600 bg-red-600 text-white')
            .addClass('bg-gray-200 text-gray-700');

        // Apply active style to current filter
        switch(currentPredictionFilter) {
            case 'all':
                $('#filterAll').removeClass('bg-gray-200 text-gray-700').addClass('bg-gray-600 text-white');
                break;
            case 'ready':
                $('#filterReady').removeClass('bg-gray-200 text-gray-700').addClass('bg-green-600 text-white');
                break;
            case 'not-ready':
                $('#filterNotReady').removeClass('bg-gray-200 text-gray-700').addClass('bg-red-600 text-white');
                break;
        }
    }

    // Filter the prediction table based on current filter
    function filterPredictionTable() {
        const rows = $('#predictionTable tbody tr');
        const selectedTerm = ($('#predictionTermFilter').val() || 'all').trim();
        
        rows.each(function() {
            const $row = $(this);
            const statusCell = $row.find('td:nth-child(3)'); // Status column (3rd column)
            
            if (statusCell.length === 0) return; // Skip if no status cell (like loading/error rows)
            
            const statusText = statusCell.text().trim().toLowerCase();
            const sessionName = ($row.attr('data-session') || '').trim();
            let showRow = false;

            switch(currentPredictionFilter) {
                case 'all':
                    showRow = true;
                    break;
                case 'ready':
                    // Show students with "Rated" status (ready for prediction)
                    showRow = statusText.includes('rated') && !statusText.includes('not rated');
                    break;
                case 'not-ready':
                    // Show students with "Not Rated" status (not ready for prediction)
                    showRow = statusText.includes('not rated') || statusText.includes('incomplete data');
                    break;
            }

            if (selectedTerm !== 'all') {
                showRow = showRow && (sessionName === selectedTerm);
            }

            if (showRow) {
                $row.show();
            } else {
                $row.hide();
            }
        });
        
        // Update row count display or show/hide empty state
        updatePredictionTableDisplay();
    }

    // Update table display after filtering
    function updatePredictionTableDisplay() {
        const visibleRows = $('#predictionTable tbody tr:visible');
        const totalRows = $('#predictionTable tbody tr');
        
        // If no rows are visible and we have data, show a "no matches" message
        if (visibleRows.length === 0 && totalRows.length > 0) {
            if ($('#predictionTable tbody .no-matches-row').length === 0) {
                $('#predictionTable tbody').append(`
                    <tr class="no-matches-row">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-search text-2xl text-gray-400 mb-2"></i>
                                <p>No students match the selected filter.</p>
                            </div>
                        </td>
                    </tr>
                `);
            }
            $('#predictionTable tbody .no-matches-row').show();
        } else {
            $('#predictionTable tbody .no-matches-row').hide();
        }
    }

    // Initialize filter buttons when prediction tab loads
    $(document).on('click', '#predictionTab', function() {
        setTimeout(() => {
            updateFilterButtonStyles();
        }, 100);
    });
    // Apply term filter immediately on change
    $(document).on('change', '#predictionTermFilter', function() {
        // If loader function is available, reload to apply term strictly
        if (typeof window.loadPredictionStudents === 'function') {
            window.loadPredictionStudents();
            return;
        }
        // Fallback: if students are already rendered, filter locally
        filterPredictionTable();
    });
// --- End Prediction Tab Logic ---

    // --- Report Tab Filters ---
    function loadStudentFilterDropdown() {
        var coordinatorId = $("#hiddencdrid").val();
        // Fetch students under coordinator with session info to power both dropdowns
        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php",
            type: "POST",
            dataType: "json",
            data: { cdrid: coordinatorId, action: "getAllStudentsUnderCoordinator" },
            success: function(response) {
                const students = (response && (response.students || response.data)) || [];
                let options = ``;
                const uniqueStudents = new Set();

                // Build datalist options
                students.forEach(function(stu) {
                    const displayName = `${stu.SURNAME || ''}, ${stu.NAME || ''}`.trim();
                    const key = `${stu.INTERNS_ID}_${displayName}`;
                    if (!uniqueStudents.has(key) && stu.INTERNS_ID) {
                        uniqueStudents.add(key);
                        options += `<option value="${displayName}" data-id="${stu.INTERNS_ID}">${displayName}</option>`;
                    }
                });
                $("#studentFilterList").html(options);

                // Build term/session dropdown
                const sessions = [...new Set(students.map(s => s.SESSION_NAME).filter(Boolean))];
                sessions.sort((a,b) => b.localeCompare(a));
                let sessionOptions = `<option value="all">All Terms</option>`;
                sessions.forEach(sess => { sessionOptions += `<option value="${sess}">${sess}</option>`; });
                $("#reportTermFilter").html(sessionOptions);
                if (sessions.length > 0) {
                    $("#reportTermFilter").val(sessions[0]);
                }

                // Store a map for filtering reports later (interns_id -> session)
                window.reportStudentSessionMap = {};
                students.forEach(s => { if (s.INTERNS_ID) window.reportStudentSessionMap[s.INTERNS_ID] = s.SESSION_NAME || ''; });

                // If report tab just opened, load reports now that filters are ready
                if ($('#reportsLoadingState').is(':visible')) {
                    loadApprovedReportsWithFilters();
                }
            },
            error: function() {
                $("#studentFilterList").html('');
                $("#reportTermFilter").html('<option value="all">All Terms</option>');
                window.reportStudentSessionMap = {};
            }
        });
    }

    // Enhanced datalist functionality for student filter (similar to admin dashboard)
    $(document).on('focus click', '#filterStudent', function(evt) {
        const input = this;
        if (input.list && input.list.options.length > 0) {
            // When empty, briefly set a space to force all options to appear, then clear
            if (input.value === '') {
                input.value = ' ';
                input.value = '';
            }
            // showPicker must be called directly in a trusted user gesture (e.g. click)
            if (evt.type === 'click' && input.showPicker) {
                try { input.showPicker(); } catch (e) { /* silently ignore NotAllowedError */ }
            }
        }
    });

    // Close coordinator profile modal
$(document).on('click', '#closeCoordinatorProfile', function(e) {
    $('#coordinatorProfileModal').addClass('hidden');
});

$(document).on('click', '#coordinatorProfileModal', function(e) {
    if (e.target.id === 'coordinatorProfileModal') {
        $('#coordinatorProfileModal').addClass('hidden');
    }
});

// Profile Picture Upload Handlers
$(document).on('click', '#editProfilePicture', function(e) {
    e.preventDefault();
    $('#profilePictureUploadDialog').removeClass('hidden');
});

// Choose Picture button inside modal triggers file input
$(document).on('click', '#choosePictureBtn', function(e) {
    e.preventDefault();
    $('#uploadPictureInput').click();
});

$(document).on('click', '#closeUploadDialog, #cancelUpload', function() {
    $('#profilePictureUploadDialog').addClass('hidden');
    $('#picturePreview').addClass('hidden').attr('src', '');
    $('#uploadPictureInput').val('');
    $('#uploadProgress').addClass('hidden').find('div').css('width', '0%');
});

$(document).on('change', '#uploadPictureInput', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#picturePreview').attr('src', e.target.result).removeClass('hidden');
        };
        reader.readAsDataURL(this.files[0]);
    }
});

$(document).on('click', '#saveProfilePicture', function() {
    const fileInput = $('#uploadPictureInput')[0];
    if (!fileInput.files || !fileInput.files[0]) {
        alert('Please select a profile picture to upload.');
        return;
    }

    const formData = new FormData();
    formData.append('profilePicture', fileInput.files[0]);
    formData.append('action', 'updateAdminProfilePicture');
    formData.append('adminId', $('#hiddencdrid').val());

    $('#uploadProgress').removeClass('hidden');

    $.ajax({
        url: 'ajaxhandler/adminDashboardAjax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    $('#uploadProgress div').css('width', percent + '%');
                }
            });
            return xhr;
        },
        success: function(response) {
            if (response.status === 'success' && response.data && response.data.filename) {
                // Use backend filename for image src - handle both local and Cloudinary URLs
                const imgPath = response.data.filename.startsWith('http') ? response.data.filename : 'uploads/' + response.data.filename + '?t=' + new Date().getTime();
                // Update modal image
                $('#coordinatorProfilePicture img').attr('src', imgPath).show();
                $('#coordinatorProfilePicture div').hide();
                // Update main profile image in the background (replace with your actual selector)
                $(".main-profile-picture, #profilePicture img").attr('src', imgPath).show();
                // Always reset modal and progress bar
                $('#profilePictureUploadDialog').addClass('hidden');
                $('#picturePreview').addClass('hidden');
                $('#uploadPictureInput').val('');
                $('#uploadProgress').addClass('hidden').find('div').css('width', '0%');
            } else {
                // Also reset modal and progress bar on error
                $('#uploadProgress').addClass('hidden').find('div').css('width', '0%');
                alert(response.message || 'Failed to update profile picture.');
            }
        },
        error: function() {
            alert('An error occurred while uploading the profile picture.');
            $('#uploadProgress').addClass('hidden').find('div').css('width', '0%');
        }
    });
});

function loadApprovedReportsWithFilters() {
        let inputValue = $("#filterStudent").val() || "";
        let studentId = "all";
        
        // If a student is selected, find the corresponding INTERNS_ID
        if (inputValue && inputValue !== "" && inputValue !== "All Students") {
            const option = $("#studentFilterList option").filter(function() {
                return $(this).val() === inputValue;
            }).first();
            
            if (option.length > 0) {
                studentId = option.attr('data-id') || "all";
            }
        }
        
        let date = $("#filterDate").val();
        let weekStart = date || null;
        let weekEnd = date || null;
        $.ajax({
            url: "ajaxhandler/coordinatorWeeklyReportAjax.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "getWeeklyReports",
                studentId: studentId,
                weekStart: weekStart,
                weekEnd: weekEnd
            },
            beforeSend: function() {
                // Show loading state, hide others
                $('#reportsLoadingState').removeClass('hidden');
                $('#approvedReportsList').addClass('hidden');
                $('#reportsEmptyState').addClass('hidden');
                $('#reportsErrorState').addClass('hidden');
            },
            success: function(rv) {
                // Render reports from rv.reports if status is success
                if (rv && rv.status === "success" && Array.isArray(rv.reports) && rv.reports.length > 0) {
                    // Apply client-side term filter using session mapping
                    const selectedTerm = (($("#reportTermFilter").val() || 'all') + '').trim();
                    let reports = rv.reports.slice();
                    if (selectedTerm && selectedTerm !== 'all' && window.reportStudentSessionMap) {
                        reports = reports.filter(function(rep) {
                            const sid = rep.interns_id || rep.INTERNS_ID;
                            const sess = window.reportStudentSessionMap[sid] || '';
                            return sess === selectedTerm;
                        });
                    }
                    if (!reports || reports.length === 0) {
                        // Hide loading state, show empty state
                        $('#reportsLoadingState').addClass('hidden');
                        $('#approvedReportsList').addClass('hidden');
                        $('#reportsErrorState').addClass('hidden');
                        $('#reportsEmptyState').removeClass('hidden');
                        return;
                    }
                    let html = "";
                    reports.forEach(function(report, reportIdx) {
                        html += `<div class="bg-white rounded-2xl shadow-lg p-6 mb-8 transition hover:shadow-2xl relative">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-xl font-bold">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">${report.student_name}</h3>
                                        <span class="text-xs text-gray-500">Week ${getWeekNumber(report.week_start)}</span>
                                    </div>
                                </div>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-medium ${report.approval_status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">
                                    ${report.approval_status.charAt(0).toUpperCase() + report.approval_status.slice(1)}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-4 mb-4">
                                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">Period: ${report.week_start} to ${report.week_end}</span>
                                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs"><i class="fas fa-clock mr-1"></i>Last Updated: ${report.updated_at}</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                                ${['monday','tuesday','wednesday','thursday','friday'].map(function(day, dayIdx) {
                                    let imagesHtml = "";
                                    if (report.imagesPerDay && report.imagesPerDay[day] && report.imagesPerDay[day].length > 0) {
                                        report.imagesPerDay[day].forEach(function(img) {
                                            // Support both Cloudinary URLs and legacy local paths
                                            let imageUrl;
                                            if (img.filename.startsWith('https://res.cloudinary.com/')) {
                                                // Cloudinary URL - use directly
                                                imageUrl = img.filename;
                                            } else {
                                                // Legacy local path
                                                imageUrl = getImageUrl(img.filename);
                                            }
                                            imagesHtml += `<img src='${imageUrl}' alt='${capitalize(day)} activity' class='rounded-lg border border-gray-200 shadow-sm w-full h-24 object-cover mb-2 hover:scale-105 transition'>`;
                                        });
                                    } else {
                                        imagesHtml = `<div class='flex items-center justify-center h-24 bg-gray-50 text-gray-400 rounded-lg border border-dashed border-gray-200'><i class='fas fa-image'></i></div>`;
                                    }
                                    return `
                                        <div class='flex flex-col items-stretch bg-gray-50 rounded-xl p-4 shadow-sm hover:bg-blue-50 transition min-h-[260px]'>
                                            <h4 class='text-sm font-bold text-blue-700 mb-3 text-center'>${capitalize(day)}</h4>
                                            <div class='w-full flex justify-center mb-3'>
                                                <div class='w-full max-w-[180px] aspect-[16/9] bg-gray-200 rounded-lg overflow-hidden flex items-center justify-center border border-gray-300'>
                                                    ${imagesHtml}
                                                </div>
                                            </div>
                                            <div class='border-t border-gray-200 pt-3 mt-auto'>
                                                <div class='day-description text-xs text-gray-600 text-center max-w-[220px] mx-auto overflow-y-auto' style='max-height:80px;'>
                                                    <p class='whitespace-pre-line'>${report[day+'_description'] || report[day+'Description'] || '<span class="italic text-gray-400">No description</span>'}</p>
                                                </div>
                                            </div>
                                            <button class='mt-3 bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 transition flex items-center justify-center gap-1' onclick='showZoomModal(${reportIdx}, "${day}")'>
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>`;
                    });
                    // Modal HTML (only one, reused)
                    html += `
                        <div id="zoomDayModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">
                            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-lg w-full relative">
                                <button class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl font-bold" onclick="hideZoomModal()">&times;</button>
                                <div id="zoomDayModalContent"></div>
                            </div>
                        </div>
                    `;
                    // Hide loading state, show content
                    $('#reportsLoadingState').addClass('hidden');
                    $('#reportsEmptyState').addClass('hidden');
                    $('#reportsErrorState').addClass('hidden');
                    $("#approvedReportsList").html(html);
                    $("#approvedReportsList").removeClass("hidden");
                    // Modal JS
                    window.showZoomModal = function(reportIdx, day) {
                        const report = reports[reportIdx];
                        let imagesHtml = "";
                        if (report.imagesPerDay && report.imagesPerDay[day] && report.imagesPerDay[day].length > 0) {
                            report.imagesPerDay[day].forEach(function(img) {
                                imagesHtml += `<img src='${getImageUrl(img.filename)}' alt='${capitalize(day)} activity' class='rounded-lg border border-gray-200 shadow w-full h-56 object-cover mb-4'>`;
                            });
                        } else {
                            imagesHtml = `<div class='flex items-center justify-center h-56 bg-gray-50 text-gray-400 rounded-lg border border-dashed border-gray-200'><i class='fas fa-image text-4xl'></i></div>`;
                        }
                        const desc = report[day+'_description'] || report[day+'Description'] || '<span class="italic text-gray-400">No description</span>';
                        const modalHtml = `
                            <h3 class='text-2xl font-extrabold text-blue-700 mb-6 text-center tracking-wide'>${capitalize(day)}</h3>
                            <div class='flex justify-center mb-6'>
                                <div class='rounded-2xl overflow-hidden shadow-lg border border-gray-200 bg-gray-100 p-2 flex items-center justify-center' style='min-width:320px; min-height:180px;'>
                                    ${imagesHtml}
                                </div>
                            </div>
                            <div class='bg-gray-50 rounded-xl p-4 shadow-inner text-base text-gray-700 text-center max-w-[420px] mx-auto whitespace-pre-line' style='max-height:220px; overflow-y:auto;'>${desc}</div>
                        `;
                        $("#zoomDayModalContent").html(modalHtml);
                        $("#zoomDayModal").removeClass("hidden");
                    };
                    window.hideZoomModal = function() {
                        $("#zoomDayModal").addClass("hidden");
                    };

                    // Helper to capitalize day names
                    function capitalize(str) { return str.charAt(0).toUpperCase() + str.slice(1); }
                } else {
                    // Hide loading state, show empty state
                    $('#reportsLoadingState').addClass('hidden');
                    $('#approvedReportsList').addClass('hidden');
                    $('#reportsErrorState').addClass('hidden');
                    $('#reportsEmptyState').removeClass('hidden');
                }

                // Helper to get week number from date
                function getWeekNumber(dateStr) {
                    const date = new Date(dateStr);
                    const firstJan = new Date(date.getFullYear(),0,1);
                    const days = Math.floor((date - firstJan) / (24*60*60*1000));
                    return Math.ceil((days + firstJan.getDay()+1) / 7);
                }
            },
            error: function() {
                // Hide loading state, show error state
                $('#reportsLoadingState').addClass('hidden');
                $('#approvedReportsList').addClass('hidden');
                $('#reportsEmptyState').addClass('hidden');
                $('#reportsErrorState').removeClass('hidden');
            }
        });
    }


    // Track if report tab has loaded
    let reportTabLoaded = false;
    $(document).on('click', '#reportTab', function() {
        if (!reportTabLoaded) {
            // Show loading state immediately when tab is clicked
            $('#reportsLoadingState').removeClass('hidden');
            $('#approvedReportsList').addClass('hidden');
            $('#reportsEmptyState').addClass('hidden');
            $('#reportsErrorState').addClass('hidden');
            
            // Load students + sessions; actual report load occurs on success
            loadStudentFilterDropdown();
            reportTabLoaded = true;
        }
    });

    // Reset flag if user changes filter (forces reload)
    $(document).on('change', '#filterStudent, #filterDate, #reportTermFilter', function() {
        reportTabLoaded = false;
    });

    // Apply filters button
    $(document).on('click', '#applyReportFilters', function() {
        loadApprovedReportsWithFilters();
    });

    // Retry loading reports button
    $(document).on('click', '#retryLoadReports', function() {
        loadApprovedReportsWithFilters();
    });

    // Profile button click handler
    $(document).on('click', '#btnProfile', function() {
        // Show coordinator profile modal
        $('#coordinatorProfileModal').removeClass('hidden');
        $('#coordinatorProfileLoading').show();
        $('#coordinatorProfileContent').addClass('hidden');
        
        let cdrid = $("#hiddencdrid").val();

        if (!cdrid) {
            $('#coordinatorProfileContent').html('<div class="text-center py-4 text-red-500">Error: Coordinator ID not found</div>');
            return;
        }

        // Fetch coordinator profile details
        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php",
            type: "POST",
            dataType: "json",
            data: {
                cdrid: cdrid,
                action: "getCoordinatorDetails"
            },
            success: function(response) {
                if (response.success && response.data) {
                    const coordinator = response.data;
                    
                    // Split name into first and last name
                    const nameParts = (coordinator.NAME || '').split(' ');
                    const firstName = nameParts[0] || '';
                    const lastName = nameParts.slice(1).join(' ') || '';
                    
                    // Update form fields
                    $('#coordinatorFirstName').val(firstName);
                    $('#coordinatorLastName').val(lastName);
                    $('#coordinatorEmail').val(coordinator.EMAIL || '');
                    $('#coordinatorContact').val(coordinator.CONTACT_NUMBER || '');
                    $('#coordinatorDepartment').val(coordinator.DEPARTMENT || '');
                    $('#coordinatorPosition').val('Coordinator');
                    
                    // Handle profile picture
                    if (coordinator.PROFILE) {
                        const imgPath = getImageUrl(coordinator.PROFILE);
                        $('#coordinatorProfilePicture img').attr('src', imgPath).show();
                        $('#coordinatorProfilePicture div').hide();
                    } else {
                        $('#coordinatorProfilePicture img').hide();
                        $('#coordinatorProfilePicture div').show();
                    }
                    
                    // Hide loading, show content
                    $('#coordinatorProfileLoading').hide();
                    $('#coordinatorProfileContent').removeClass('hidden');
                } else {
                    $('#coordinatorProfileLoading').hide();
                    $('#coordinatorProfileContent').removeClass('hidden')
                        .html('<div class="text-center py-4 text-red-500">Error loading coordinator profile</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", status, error);
                $('#coordinatorProfileLoading').hide();
                $('#coordinatorProfileContent').removeClass('hidden')
                    .html('<div class="text-center py-4 text-red-500">Failed to load coordinator profile</div>');
                alert("Error fetching coordinator details. Please check the console for more information.");
            }
        });
    });

    // Function to display coordinator details modal with Edit Profile and Change Password buttons
    function displayCoordinatorDetails(coordinatorData) {
        let html = `
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        ${coordinatorData.PROFILE 
                            ? `<img src="${getImageUrl(coordinatorData.PROFILE)}" alt="Profile" class="profile-image">` 
                            : `<div class="avatar-placeholder">${coordinatorData.NAME.charAt(0)}</div>`
                        }
                    </div>
                    <h2>${coordinatorData.NAME}</h2>
                    <p class="profile-subtitle">Coordinator</p>
                </div>

                <div class="profile-details">
                    <div class="detail-row">
                        <span class="detail-label">Coordinator ID</span>
                        <span class="detail-value">${coordinatorData.COORDINATOR_ID}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Department</span>
                        <span class="detail-value">${coordinatorData.DEPARTMENT}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contact Number</span>
                        <span class="detail-value">${coordinatorData.CONTACT_NUMBER}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value">${coordinatorData.EMAIL}</span>
                    </div>
                </div>

                <div class="profile-actions">
                    <button type="button" id="btnEditProfile" class="btn-edit">Edit Profile</button>
                    <button type="button" id="btnChangePassword" class="btn-change-password">Change Password</button>
                </div>
            </div>
        `;

        // Clear existing content and add new content to the modal body
        $("#profileModalContent").html(html);
        
        // Show the profile modal
        $("#profileModal").css('display', 'flex');
    }

    // Close profile modal and all other modals
    $(document).on('click', '#closeProfileModal', function() {
        // Hide the profile modal
        $("#profileModal").fadeOut();
        
        // Close any other open modals
        $('#editableProfileModal, #changePasswordModal').fadeOut(function() {
            $(this).remove();
        });
    });

    // Edit Profile button click handler inside coordinator details modal
    $(document).on('click', '#btnEditProfile', function() {
        loadEditableProfile();
    });

    // Change Password button click handler inside coordinator details modal
    $(document).on('click', '#btnChangePassword', function() {
        showChangePasswordModal();
    });

    // Function to load editable profile modal
    function loadEditableProfile() {
        let cdrid = $("#hiddencdrid").val();

        if (!cdrid) {
            alert("Coordinator ID not found.");
            return;
        }

        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php",
            type: "POST",
            dataType: "json",
            data: {cdrid: cdrid, action: "getCoordinatorDetails"},
            success: function(response) {
                if (response.success) {
                    displayEditableProfileModal(response.data);
                } else {
                    alert("Error: " + (response.message || "Unknown error occurred."));
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", status, error);
                alert("Error fetching coordinator details. Please check the console for more information.");
            }
        });
    }

    // Function to display editable profile modal
    function displayEditableProfileModal(coordinatorData) {
        let html = `
            <div id="editableProfileModal" class="modal" style="display: flex;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Edit Profile Picture</h2>
                        <button class="modal-close" id="closeEditableProfileModal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="editProfileForm" class="edit-profile-picture-form" enctype="multipart/form-data">
                            <div class="profile-picture-section">
                                <div class="current-profile-picture">
                                    ${coordinatorData.PROFILE_PICTURE ? 
                                        `<img src="${getImageUrl(coordinatorData.PROFILE_PICTURE)}" alt="Current Profile" class="current-image">` :
                                        `<div class="avatar-placeholder">
                                            <i class="fas fa-user"></i>
                                        </div>`
                                    }
                                </div>
                                <div class="profile-picture-upload file-upload-group">
                                    <label for="profilePicture">Upload Profile Picture:</label>
                                    <input type="file" id="profilePicture" name="profilePicture" accept="image/*" class="file-input-wrapper">
                                    <small>Max file size: 2MB (JPG, PNG, GIF)</small>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-submit">Save Changes</button>
                                <button type="button" class="btn-cancel" id="closeEditableProfileModalCancel">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        $('body').append(html);
        $("#editableProfileModal").hide().fadeIn();
    }

    // Close editable profile modal
    $(document).on('click', '#closeEditableProfileModal', function(e) {
        e.stopPropagation(); // Prevent event from bubbling up
        $('#editableProfileModal').fadeOut(function() {
            $(this).remove();
        });
    });

    // Cancel profile edit
    $(document).on('click', '#closeEditableProfileModalCancel', function(e) {
        e.stopPropagation(); // Prevent event from bubbling up
        $('#editableProfileModal').fadeOut(function() {
            $(this).remove();
        });
    });

    // Close modal when clicking outside
    $(document).on('click', '#editableProfileModal', function(e) {
        if (e.target === this) {
            $(this).fadeOut(function() {
                $(this).remove();
            });
        }
    });

    // Prevent clicks inside the modal from propagating to parent
    $(document).on('click', '#editableProfileModal .modal-content', function(e) {
        e.stopPropagation();
    });

    // Handle file input change for preview
    $(document).on('change', '#profilePicture', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('.image-preview').show();
                $('.preview-image').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        } else {
            $('.image-preview').hide();
        }
    });

    // Handle profile form submission
    function handleProfileFormSubmit(e) {
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
        formData.append('action', 'updateCoordinatorProfilePicture');
        formData.append('cdrid', $('#hiddencdrid').val());
        formData.append('profilePicture', file);

        // Show loading state
        const $submitButton = $('.btn-submit');
        $submitButton.prop('disabled', true).text('Uploading...');

        // Create loading indicator
        const $loadingIndicator = $('<div class="upload-progress">Uploading profile picture...</div>');
        $submitButton.after($loadingIndicator);

        console.log('Uploading profile picture for coordinator:', $('#hiddencdrid').val());
        
        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                console.log('Upload response:', response);
                $submitButton.prop('disabled', false).text('Update Profile Picture');
                $loadingIndicator.remove();
                
                try {
                    // Try to parse the response as JSON if it's a string
                    let jsonResponse = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (jsonResponse.success) {
                        console.log('Profile picture updated successfully:', jsonResponse.filename);
                        // Show success message
                        const $successMessage = $('<div class="alert alert-success">Profile picture updated successfully!</div>');
                        $submitButton.after($successMessage);
                        
                        // Update the profile picture display
                        const imageUrl = jsonResponse.filename.startsWith('http') ? jsonResponse.filename : 'uploads/' + jsonResponse.filename;
                        $('.profile-image, .profile-image-preview').attr('src', imageUrl);
                        
                        // Fade out the modal and show profile after a short delay
                        setTimeout(() => {
                            $('#editableProfileModal').fadeOut(function() {
                                $(this).remove();
                                // Show coordinator details modal again
                                let cdrid = $("#hiddencdrid").val();
                                $.ajax({
                                    url: "ajaxhandler/attendanceAJAX.php",
                                    type: "POST",
                                    dataType: "json",
                                    data: {cdrid: cdrid, action: "getCoordinatorDetails"},
                                    success: function(response) {
                                        if (response.success) {
                                            displayCoordinatorDetails(response.data);
                                        } else {
                                            console.error("Error fetching updated profile:", response.message);
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error("Error fetching updated profile:", error);
                                    }
                                });
                            });
                        }, 1500);
                    } else {
                        // Show error message with details from server
                        const errorMessage = jsonResponse.message || "Unknown error occurred";
                        const $errorMessage = $('<div class="alert alert-danger">Error: ' + errorMessage + '</div>');
                        $submitButton.after($errorMessage);
                        console.error("Server error:", errorMessage);
                    }
                } catch (e) {
                    console.error("Error parsing response:", e);
                    console.error("Raw response:", response);
                    
                    let errorMessage;
                    if (typeof response === 'string') {
                        if (response.includes('Warning') || response.includes('Notice')) {
                            errorMessage = "Server error: " + response.split("\n")[0];
                            console.error("PHP Error:", response);
                        } else {
                            errorMessage = "Invalid server response format";
                        }
                    } else {
                        errorMessage = "Unexpected response type";
                    }
                    
                    const $errorMessage = $('<div class="alert alert-danger">' + errorMessage + '</div>');
                    $submitButton.after($errorMessage);
                }
            },
            error: function(xhr, status, error) {
                $('.btn-submit').prop('disabled', false).text('Update Profile Picture');
                console.error("Error updating profile - Status:", status);
                console.error("Error updating profile - Error:", error);
                console.error("Server response:", xhr.responseText);
                console.error("Response headers:", xhr.getAllResponseHeaders());
                
                let errorMessage = "Error updating profile picture. ";
                if (xhr.responseText) {
                    try {
                        let response = JSON.parse(xhr.responseText);
                        errorMessage += response.message || "Please try again.";
                    } catch (e) {
                        if (xhr.responseText.includes('Warning') || xhr.responseText.includes('Error')) {
                            errorMessage += "Server error encountered. Please try again or contact support.";
                            console.error("PHP Error:", xhr.responseText);
                        } else {
                            errorMessage += "Unexpected server response. Please try again.";
                        }
                    }
                } else {
                    errorMessage += xhr.statusText || "Please try again.";
                }
                
                alert(errorMessage);
            }
        });
    }

    // Attach submit handler for profile form
    $(document).on('submit', '#editProfileForm', handleProfileFormSubmit);

    // Change password button handler
    $(document).on('click', '#changePasswordBtn', function() {
        showChangePasswordModal();
    });

    // Function to show change password modal with Tailwind styling
    function showChangePasswordModal() {
        let html = `
            <div id="changePasswordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">Change Password</h2>
                        <button type="button" id="closeChangePasswordModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="p-6">
                        <form id="changePasswordForm" class="space-y-4">
                            <!-- Current Password -->
                            <div class="space-y-2">
                                <label for="currentPassword" class="block text-sm font-medium text-gray-700">
                                    Current Password
                                </label>
                                <input 
                                    type="password" 
                                    id="currentPassword" 
                                    name="current_password" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    placeholder="Enter your current password"
                                >
                            </div>
                            
                            <!-- New Password -->
                            <div class="space-y-2">
                                <label for="newPassword" class="block text-sm font-medium text-gray-700">
                                    New Password
                                </label>
                                <input 
                                    type="password" 
                                    id="newPassword" 
                                    name="new_password" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    placeholder="Enter new password (min. 6 characters)"
                                >
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="space-y-2">
                                <label for="confirmPassword" class="block text-sm font-medium text-gray-700">
                                    Confirm New Password
                                </label>
                                <input 
                                    type="password" 
                                    id="confirmPassword" 
                                    name="confirm_password" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    placeholder="Confirm your new password"
                                >
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-4">
                                <button 
                                    type="submit" 
                                    class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors font-medium"
                                >
                                    Change Password
                                </button>
                                <button 
                                    type="button" 
                                    id="cancelPasswordChange" 
                                    class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-400 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors font-medium"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        $('body').append(html);
        $("#changePasswordModal").removeClass('hidden');
    }

    // Modal event handlers for Tailwind change password modal
    $(document).on('click', '#closeChangePasswordModal', function() {
        $('#changePasswordModal').addClass('opacity-0');
        setTimeout(function() {
            $('#changePasswordModal').remove();
        }, 300);
    });

    $(document).on('click', '#cancelPasswordChange', function() {
        $('#changePasswordModal').addClass('opacity-0');
        setTimeout(function() {
            $('#changePasswordModal').remove();
        }, 300);
    });

    // Close modal when clicking outside
    $(document).on('click', '#changePasswordModal', function(e) {
        if (e.target === this) {
            $(this).addClass('opacity-0');
            setTimeout(function() {
                $('#changePasswordModal').remove();
            }, 300);
        }
    });

    // Prevent closing when clicking inside modal content
    $(document).on('click', '#changePasswordModal > div', function(e) {
        e.stopPropagation();
    });

    // Load sessions on page load
    loadSeassions();
// End of document ready

    // Handle change password form submission
    $(document).on('submit', '#changePasswordForm', function(e) {
        e.preventDefault();

        let currentPassword = $('#currentPassword').val();
        let newPassword = $('#newPassword').val();
        let confirmPassword = $('#confirmPassword').val();
        let coordinatorId = $('#hiddencdrid').val();

        console.log('Attempting to change password for coordinator:', coordinatorId);

        if (!coordinatorId) {
            alert("Error: Coordinator ID not found. Please try logging in again.");
            return;
        }

        if (newPassword !== confirmPassword) {
            alert("New passwords do not match!");
            return;
        }

        if (newPassword.length < 6) {
            alert("New password must be at least 6 characters long!");
            return;
        }

        // Show loading state
        $('#changePasswordForm button[type="submit"]').prop('disabled', true).html('<span class="spinner">⏳</span> Verifying...');
        
        console.log("Starting password change for coordinator:", coordinatorId);
        
        // First verify the current password
        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php",
            type: "POST",
            dataType: "json",
            data: {
                action: 'verifyCoordinatorPassword',
                coordinator_id: coordinatorId,
                current_password: currentPassword
            },
            success: function(response) {
                console.log("Password verification response:", response);
                $('#changePasswordForm button[type="submit"]').prop('disabled', false).text('Change Password');
                
                if (response.success) {
                    console.log("Password verified, proceeding with update");
                    // Password verified, now update it
                    $('#changePasswordForm button[type="submit"]').html('<span class="spinner">⏳</span> Updating Password...');
                    
                    $.ajax({
                        url: "ajaxhandler/attendanceAJAX.php",
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: 'updateCoordinatorPassword',
                            coordinator_id: coordinatorId,
                            current_password: currentPassword,
                            new_password: newPassword
                        },
                        success: function(updateResponse) {
                            console.log("Password update response:", updateResponse);
                            if (updateResponse.success) {
                                alert("Password changed successfully!");
                                $('#changePasswordModal').addClass('opacity-0');
                                setTimeout(function() {
                                    $('#changePasswordModal').remove();
                                    console.log("Change password modal removed");
                                    // Show coordinator details modal again
                                    $.ajax({
                                        url: "ajaxhandler/attendanceAJAX.php",
                                        type: "POST",
                                        dataType: "json",
                                        data: {
                                            cdrid: coordinatorId, 
                                            action: "getCoordinatorDetails"
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                displayCoordinatorDetails(response.data);
                                            }
                                        }
                                    });
                                }, 300);
                            } else {
                                alert("Error: " + (updateResponse.message || "Failed to update password"));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Error updating password:", error);
                            alert("Error updating password. Please try again.");
                        }
                    });
                } else {
                    alert("Current password is incorrect!");
                }
            },
            error: function(xhr, status, error) {
                $('#changePasswordForm button[type="submit"]').prop('disabled', false).text('Change Password');
                console.error("Error verifying password - Status:", status);
                console.error("Error verifying password - Error:", error);
                
                let errorMessage = "Error verifying password. ";
                try {
                    // Try to parse error response as JSON
                    let response = JSON.parse(xhr.responseText);
                    errorMessage += response.message || "Please try again.";
                } catch (e) {
                    // If response is not JSON, use status text
                    errorMessage += xhr.statusText || "Please try again.";
                }
                
                alert(errorMessage);
            }
        });
    });

    // Sidebar toggle button click handler
    $('#sidebarToggle').on('click', function() {
        $('.sidebar').toggleClass('sidebar-open');
    });

    // Click outside to close modals and sidebar
    $(document).on('click', function(event) {
        // Close coordinator details modal
        if (!$(event.target).closest('#coordinatorDetailsModal .modal-content').length && !$(event.target).is('#btnProfile')) {
            $('#coordinatorDetailsModal').fadeOut(function() {
                $(this).remove();
            });
        }

        // Close add student form
        if (!$(event.target).closest('#addStudentForm').length && !$(event.target).is('.btnAdd')) {
            $('#addStudentForm').fadeOut(function() {
                $('#studentForm')[0].reset();
                $('#studentForm input, #studentForm select').prop('disabled', false);
            });
        }

        // Close add HTE form
        if (!$(event.target).closest('#addHTEForm').length && !$(event.target).is('.btnAddHTE')) {
            $('#addHTEForm').fadeOut();
        }

        // Close sidebar when clicking outside
        if (!$(event.target).closest('.sidebar').length && !$(event.target).is('#sidebarToggle')) {
            $('.sidebar').removeClass('sidebar-open');
        }
    });

    // Function to load all students data
    // Load all students for autocomplete on page load
    $(document).ready(function() {
        let cdrid = $("#hiddencdrid").val();
        if (cdrid) {
            $.ajax({
                url: "ajaxhandler/attendanceAJAX.php",
                type: "POST",
                dataType: "json",
                data: {cdrid: cdrid, action: "getAllStudentsUnderCoordinator"},
                success: function(response) {
                    if (response.success) {
                        window.allStudentsList = response.data || [];
                    } else {
                        window.allStudentsList = [];
                    }
                },
                error: function() {
                    window.allStudentsList = [];
                }
            });
        } else {
            window.allStudentsList = [];
        }
    });
    function loadAllStudentsData(showContainer = true) {
        // Close other forms
        $('#studentFormContainer').fadeOut(function() {
            $('#studentForm')[0].reset();
            $('#studentForm input, #studentForm select').prop('disabled', false);
        });
        $('#addHTEFormContainer').hide();
        $('#allCompaniesContainer').hide();
        $('#sessionFormContainer').fadeOut();
        $('#deleteHTEFormContainer').hide();
        $('#deleteSessionFormContainer').hide();
        $('#deleteStudentFormContainer').hide();
        // Only hide the intro if we intend to show a replacement container.
        // For silent preloads (showContainer === false) keep the intro visible.
        if (showContainer) {
            $('#controlIntroContainer').hide();
        }

        let cdrid = $("#hiddencdrid").val();

        if (!cdrid) {
            $('#studentsLoadingState').addClass('hidden');
            $('#studentsContent').addClass('hidden');
            if (showContainer) {
                $('#studentsErrorState').removeClass('hidden');
                $('#allStudentsContainer').fadeIn();
            }
            return;
        }

        // Check cache first
        const cacheKey = `allStudents_${cdrid}`;
        if (isCacheValid('student', cacheKey) && dataCache.studentsByHte[cacheKey]) {
            console.log("Using cached student data for coordinator:", cdrid);
            const cachedData = dataCache.studentsByHte[cacheKey];
            renderAllStudentsData(cachedData, showContainer);
            return;
        }

        // Prepare states (only reveal container if requested)
        $('#studentsLoadingState').removeClass('hidden');
        $('#studentsContent').addClass('hidden');
        $('#studentsErrorState').addClass('hidden');
        if (showContainer) {
            $('#allStudentsContainer').fadeIn();
        }

        console.log('Making AJAX request with cdrid:', cdrid);
        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php",
            type: "POST",
            dataType: "json",
            data: {cdrid: cdrid, action: "getAllStudentsUnderCoordinator"},
            success: function(response) {
                console.log('AJAX response received:', response);
                if (response.success) {
                    console.log('Success - Displaying students data');
                    
                    // Cache the data
                    const cacheKey = `allStudents_${cdrid}`;
                    const studentsData = response.students || response.data || [];
                    dataCache.studentsByHte[cacheKey] = studentsData;
                    dataCache.lastUpdated.studentsByHte[cacheKey] = Date.now();
                    
                    // Render / cache the data
                    renderAllStudentsData(studentsData, showContainer);
                } else {
                    console.error('Error in response:', response.message);
                    $('#studentsLoadingState').addClass('hidden');
                    $('#studentsContent').addClass('hidden');
                    if (showContainer) {
                        $('#studentsErrorState').removeClass('hidden');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", status, error);
                $('#studentsLoadingState').addClass('hidden');
                $('#studentsContent').addClass('hidden');
                if (showContainer) {
                    $('#studentsErrorState').removeClass('hidden');
                }
            }
        });
    }

    // Render all students data (extracted for reuse with caching)
    function renderAllStudentsData(studentsData, showContainer = true) {
        if (showContainer) {
            $('#studentsLoadingState').addClass('hidden');
            $('#studentsErrorState').addClass('hidden');
            $('#studentsContent').removeClass('hidden');
            displayAllStudents(studentsData);
        } else {
            // Silent cache only
            window.allStudentsList = studentsData || [];
        }
    }

    // View All Students button click handler
    $(document).on('click', '#btnViewAllStudents', function() {
        loadAllStudentsData(true);
    });

    // Retry loading students button
    $(document).on('click', '#retryLoadStudents', function() {
        loadAllStudentsData(true);
    });

    // Retry loading companies button
    $(document).on('click', '#retryLoadCompanies', function() {
        loadAllCompaniesData();
    });

    // Function to display all students under coordinator
    function displayAllStudents(students) {
        console.log('displayAllStudents called with data:', students);
        // Get unique sessions and sort (most recent first)
        const sessions = [...new Set((students || []).map(s => s.SESSION_NAME).filter(Boolean))];
        sessions.sort((a, b) => b.localeCompare(a));
        // Build dropdown
        let filterHtml = `<div class='flex items-center mb-4'><label for='sessionFilter' class='mr-2 font-semibold text-gray-700'>Filter by Session:</label><select id='sessionFilter' class='rounded-md border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-blue-500'>`;
        filterHtml += `<option value='all'>All Sessions</option>`;
        sessions.forEach(session => { filterHtml += `<option value='${session}'>${session}</option>`; });
        filterHtml += `</select></div>`;

        // Insert filter above table
        $('#allStudentsFilterContainer').remove();
        $('#allStudentsContainer').prepend(`<div id='allStudentsFilterContainer'>${filterHtml}</div>`);

        // Default to most recent session
        setTimeout(() => {
            $('#sessionFilter').val(sessions[0] || 'all').trigger('change');
        }, 0);

        // Filtering logic
        function renderTable(filteredStudents) {
            let tbodyHtml = '';
            if (filteredStudents && filteredStudents.length > 0) {
                filteredStudents.forEach((student, idx) => {
                    const rowClass = idx % 2 === 0 ? 'bg-white hover:bg-blue-50 transition' : 'bg-gray-50 hover:bg-blue-50 transition';
                    tbodyHtml += `
                        <tr class="${rowClass}">
                            <td class="px-6 py-4 text-sm text-gray-700 font-medium">${student.STUDENT_ID || ''}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${student.NAME || ''}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${student.SURNAME || ''}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${student.AGE || ''}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${student.GENDER || ''}</td>
                            <td class="px-6 py-4 text-sm text-blue-600 underline">${student.EMAIL || ''}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${student.CONTACT_NUMBER || ''}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${student.HTE_NAME || 'Not Assigned'}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${student.SESSION_NAME || 'Not Assigned'}</td>
                        </tr>
                    `;
                });
            } else {
                tbodyHtml = `<tr><td colspan="9" class="px-6 py-4 text-center text-gray-500">No students found under this coordinator.</td></tr>`;
            }
            $('#allStudentsTableBody').html(tbodyHtml);
        }

        // Initial render
        renderTable(students);

        // Store all students globally for autocomplete
        window.allStudentsList = students || [];
        $('#allStudentsContainer').fadeIn();

        // On filter change
        $('#sessionFilter').off('change').on('change', function() {
            const selectedSession = $(this).val();
            if (selectedSession === 'all') {
                renderTable(students);
            } else {
                renderTable(students.filter(s => s.SESSION_NAME === selectedSession));
            }
        });
    }

    // Close all students container
    $(document).on('click', '#closeAllStudents', function() {
        $('#allStudentsContainer').fadeOut();
    });

function getSessionHTML(rv)
{
    let x = ``;
    x=`<option value=-1>SELECT ONE</option>`;
    let i=0;
    for(i=0;i<rv.length;i++)
    {
        let cs = rv[i];
        // Use the new DISPLAY_NAME format or fallback to S.Y. YEAR-YEAR+1
        let displayName = cs['DISPLAY_NAME'] || ('S.Y. ' + cs['YEAR'] + '-' + (parseInt(cs['YEAR']) + 1));
        x=x+ `<option value=${cs['ID']}>${displayName}</option>`;
    }

    return x;
}

// Render session data (extracted for reuse with caching)
function renderSessionData(rv) {
    let x = getSessionHTML(rv);
    $("#ddlclass").html(x);

    // Auto-select the first session if available
    if (rv && rv.length > 0) {
        $("#ddlclass").val(rv[0].ID);
        // Trigger the change event to load HTEs for the selected session
        $("#ddlclass").trigger("change");
    }
}

function loadSeassions()
{
    // Check cache first
    if (isCacheValid('sessions') && dataCache.sessions) {
        console.log("Using cached session data");
        const cachedData = dataCache.sessions;
        renderSessionData(cachedData);
        return;
    }
    
    $.ajax({
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: {action: "getSession" },
        success: function(rv) {
            // Cache the data
            dataCache.sessions = rv;
            dataCache.lastUpdated.sessions = Date.now();
            
            console.log("Session data loaded and cached");
            renderSessionData(rv);
        },
        error: function(xhr, status, error) {
            console.log("AJAX Error Details:", {
                status: status,
                error: error,
                responseText: xhr.responseText,
                url: "ajaxhandler/attendanceAJAX.php"
            });
            alert("OOPS! Session loading failed. Check console for details.");
        }
    });
}

// Function to load coordinator companies (used by mainDashboard.php)
function loadCoordinatorCompanies() {
    console.log('loadCoordinatorCompanies called');
    loadAllCompaniesData();
}

function getHTEHTML(classlist)
{
    let x = `<div class="flex flex-col">
        <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
        <select id="company-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Select Company</option>`;
    
    for(let i = 0; i < classlist.length; i++) {
        let cc = classlist[i];
        x += `<option value="${cc.HTE_ID}" data-building='${JSON.stringify(cc)}'>${cc.NAME}</option>`;
    }
    
    x += `</select></div>`;
    
    // Also update student list area to show proper message
    setTimeout(function() {
        $("#studentlistarea").html(`
            <div class="bg-gray-50 rounded-lg shadow-sm p-8">
                <div class="text-center text-gray-500">
                    <i class="fas fa-users text-4xl mb-4 text-gray-400"></i>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">No Company Selected</h3>
                    <p class="text-sm">Please select a company from the dropdown above to view students.</p>
                </div>
            </div>
        `);
    }, 50);
    
    return x;
}

function fetchTHE(cdrid,sessionid)
{   console.log("Fetching THE for session:", sessionid);
    
    // Check cache first
    const cacheKey = `${cdrid}_${sessionid}`;
    if (isCacheValid('hte', cacheKey) && dataCache.htesBySession[cacheKey]) {
        console.log("Using cached HTE data for session:", sessionid);
        const cachedData = dataCache.htesBySession[cacheKey];
        renderHTEData(cachedData);
        return;
    }
    
    // Track start time for minimum loading duration
    const loadStartTime = Date.now();
    const minLoadingDuration = 600; // Minimum 600ms to show spinner
    
    // Show loading state
    $("#classlistarea").html(`
        <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
            <div class="flex items-center justify-center py-8">
                <div class="flex items-center space-x-3">
                    <div class="animate-spin rounded-full h-6 w-6 border-2 border-blue-500 border-t-transparent"></div>
                    <span class="text-gray-600">Loading companies...</span>
                </div>
            </div>
        </div>
    `);
    
    //kuhaon tanan mga H.T.E nga gi handle sa current login coordinator
    //didto sa database gamit ang ajax call
    $.ajax({
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: {cdrid:cdrid,sessionid:sessionid,action:"getHTE"},
        success: function(rv) {
            // Cache the data
            const cacheKey = `${cdrid}_${sessionid}`;
            dataCache.htesBySession[cacheKey] = rv;
            dataCache.lastUpdated.htesBySession[cacheKey] = Date.now();
            
            // Calculate remaining time to show spinner
            const elapsedTime = Date.now() - loadStartTime;
            const remainingTime = Math.max(0, minLoadingDuration - elapsedTime);
            
            setTimeout(function() {
                renderHTEData(rv);
            }, remainingTime);
        },
        error: function(e) {
            console.error("Error fetching THE:", e);
            
            // Calculate remaining time to show spinner for error case too
            const elapsedTime = Date.now() - loadStartTime;
            const remainingTime = Math.max(0, minLoadingDuration - elapsedTime);
            
            setTimeout(function() {
                // Show error state
                $("#classlistarea").html(`
                    <div class="flex flex-col">
                        <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
                        <div class="flex items-center justify-center py-8">
                            <div class="text-center text-red-500">
                                <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                                <p>Error loading companies</p>
                                <button onclick="fetchTHE('${cdrid}', '${sessionid}')" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                    <i class="fas fa-redo mr-1"></i>Try Again
                                </button>
                            </div>
                        </div>
                    </div>
                `);
            }, remainingTime);
        }
    });
}

// Render HTE data (extracted from fetchTHE for reuse with caching)
function renderHTEData(rv) {
    if (rv && Array.isArray(rv) && rv.length > 0) {
        let x = getHTEHTML(rv);
        $("#classlistarea").html(x);
        
        // Restore HTE selection if we have a saved HTE ID
        let savedHteId = $("#hiddenSelectedHteID").val();
        if (savedHteId && savedHteId !== '-1') {
            // Wait a bit for the DOM to update, then restore selection
            setTimeout(function() {
                let companySelect = $("#company-select");
                if (companySelect.length > 0) {
                    companySelect.val(savedHteId);
                    // Trigger the change event to restore the HTE details and student list
                    companySelect.trigger('change');
                }
            }, 50);
        }
    } else {
        // Show no companies found state
        $("#classlistarea").html(`
            <div class="flex flex-col">
                <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
                <div class="flex items-center justify-center py-8 text-gray-500">
                    <div class="text-center">
                        <i class="fas fa-building text-3xl mb-2 text-gray-400"></i>
                        <p>No companies assigned for this session</p>
                    </div>
                </div>
            </div>
        `);
    }
}

 function getClassdetailsAreaHTML(building)
{
    let dobj=new Date();
    let year=dobj.getFullYear();//format ni sa data
    let month=dobj.getMonth()+1;//para sa 0-11 na months
    if(month<10)
    {
        month="0"+month;//para ma string siya
    }
    let day=dobj.getDate();//para sa 1-31 na days
    if(day<10)
        {
            day="0"+day;//para ma string siya
        }
    let ondate=year+"-"+month+"-"+day;
    
    // Calculate yesterday's date for quick access
    let yesterday = new Date(dobj);
    yesterday.setDate(yesterday.getDate() - 1);
    let yesterdayYear = yesterday.getFullYear();
    let yesterdayMonth = yesterday.getMonth() + 1;
    if(yesterdayMonth < 10) yesterdayMonth = "0" + yesterdayMonth;
    let yesterdayDay = yesterday.getDate();
    if(yesterdayDay < 10) yesterdayDay = "0" + yesterdayDay;
    let yesterdayDate = yesterdayYear + "-" + yesterdayMonth + "-" + yesterdayDay;
    
    let logoHtml = '';
    if (building['LOGO']) {
        // Support both Cloudinary URLs and legacy local paths
        let logoUrl;
        if (building['LOGO'].startsWith('https://res.cloudinary.com/')) {
            // Cloudinary URL - use directly
            logoUrl = building['LOGO'];
        } else if (building['LOGO'].startsWith('uploads/')) {
            // Legacy path format
            logoUrl = `${getBaseUrl()}${building['LOGO']}`;
        } else {
            // Legacy filename only
            logoUrl = `${getBaseUrl()}uploads/hte_logos/${building['LOGO']}`;
        }
        logoHtml = `<img src='${logoUrl}' alt='Company Logo' class='w-20 h-20 object-cover rounded-full border-2 border-blue-300 shadow mb-2 bg-white' />`;
    } else {
        logoHtml = `<div class='w-20 h-20 flex items-center justify-center bg-gray-100 rounded-full border-2 border-gray-300 text-gray-400 mb-2'>No Logo</div>`;
    }
    let x = `<div class="company-card flex flex-col items-center p-4 bg-white rounded-xl shadow-md border border-gray-200">
                ${logoHtml}
                <div class="font-semibold text-lg text-gray-800 mb-1">${building['NAME']}</div>
                <div class="text-sm text-gray-500 mb-2">${building['INDUSTRY']}</div>
                <div class="flex flex-col gap-2 text-sm text-gray-600 mb-2">
                    <label class="font-medium text-gray-700">Select Date:</label>
                    <input type="date" value='${ondate}' id='dtpondate' class='border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300' />
                    <div class="flex gap-1">
                        <button onclick="setDateAndRefresh('${ondate}')" class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">Today</button>
                        <button onclick="setDateAndRefresh('${yesterdayDate}')" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200">Yesterday</button>
                    </div>
                </div>
            </div>`;
    return x;
}
///////

function convertTo12Hour(time24) {
    if (!time24 || time24 === '--:--') return '--:--';
    
    // Parse the time string
    const [hours24, minutes] = time24.split(':');
    let hours = parseInt(hours24, 10);
    const ampm = hours >= 12 ? 'PM' : 'AM';
    
    // Convert to 12-hour format
    hours = hours % 12;
    hours = hours ? hours : 12; // Convert 0 to 12
    
    // Format the time
    return `${hours}:${minutes} ${ampm}`;
}

function getStudentListHTML(studentList) {
    // Handle empty student list
    if (!studentList || studentList.length === 0) {
        const selectedDate = $("#dtpondate").val() || 'the selected date';
        const today = new Date().toISOString().split('T')[0];
        const isToday = selectedDate === today;
        
        return `
            <div class="bg-gray-50 rounded-lg shadow-sm p-8">
                <div class="text-center text-gray-500">
                    <i class="fas fa-user-slash text-4xl mb-4 text-gray-400"></i>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">No Students Found</h3>
                    <p class="text-sm mb-4">There are no students assigned to this company for ${selectedDate}.</p>
                    ${!isToday ? `
                        <div class="text-xs text-blue-600 bg-blue-50 p-3 rounded-lg">
                            <i class="fas fa-info-circle mr-1"></i>
                            Tip: If you're looking for recent attendance, try selecting yesterday's date or check if students were assigned to this company.
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    // Start table layout
    let x = `
    <div class="overflow-hidden rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">`;

    // Helper: format time in AM/PM
    const formatTime = (time) => {
        if (!time) return '--:-- --';
        let [hours, minutes] = time.split(':');
        let suffix = 'AM';
        hours = parseInt(hours);
        if (hours >= 12) {
            suffix = 'PM';
            if (hours > 12) hours -= 12;
        } else if (hours === 0) {
            hours = 12;
        }
        return `${hours}:${minutes} ${suffix}`;
    };

    // Generate student rows
    studentList.forEach((cs, index) => {
        x += `
        <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'} hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${cs['STUDENT_ID']}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${cs['SURNAME']}, ${cs['NAME']}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <span class="cbtimein font-medium">${convertTo12Hour(cs['timeIn'] || cs['TIME_IN'] || cs['timein'])}</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <span class="cbtimeout font-medium">${convertTo12Hour(cs['timeout'] || cs['TIME_OUT'] || cs['timeOut'])}</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button class="btnProfileStudent inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2"
                        data-studentid="${cs['INTERNS_ID']}">Profile</button>
                <button class="btnDashboardStudent inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        data-studentid="${cs['INTERNS_ID']}">Stats</button>
            </td>
        </tr>`;
    });

    // Close table
    x += `</tbody></table></div>`;

    // Optional report section (you had this at the end)
    x += `<div class="reportsection mt-4"></div>`;

    return x;
}














function fetchStudentList(sessionid,classid,cdrid,ondate)
{
    console.log("fetchStudentList called with sessionid: " + sessionid + ", classid: " + classid + ", cdrid: " + cdrid + ", ondate: " + ondate);
    
    // Check cache first
    const cacheKey = `${sessionid}_${classid}_${cdrid}_${ondate}`;
    if (isCacheValid('student', cacheKey) && dataCache.studentsByHte[cacheKey]) {
        console.log("Using cached student data for:", cacheKey);
        const cachedData = dataCache.studentsByHte[cacheKey];
        renderStudentData(cachedData);
        return;
    }
    
    // Show loading state
    $("#studentlistarea").html(`
        <div class="bg-gray-50 rounded-lg shadow-sm p-8">
            <div class="text-center text-gray-500">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-blue-500 border-t-transparent mx-auto mb-4"></div>
                <h3 class="text-lg font-medium text-gray-700 mb-2">Loading Students</h3>
                <p class="text-sm">Fetching student attendance data...</p>
            </div>
        </div>
    `);
    
    $.ajax({
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: {cdrid:cdrid,ondate:ondate,sessionid:sessionid,classid:classid, action: "getStudentList" },
        success: function(rv) {
            // Cache the data
            dataCache.studentsByHte[cacheKey] = rv;
            dataCache.lastUpdated.studentsByHte[cacheKey] = Date.now();
            
            console.log('Student data loaded and cached for', ondate, ':', rv);
            console.log('Found', rv.length, 'total students,', rv.filter(s => s.ispresent === 'YES').length, 'with attendance');
            
            // Log students with attendance for debugging
            const studentsWithAttendance = rv.filter(s => s.ispresent === 'YES');
            if (studentsWithAttendance.length > 0) {
                console.log('Students with attendance:', studentsWithAttendance.map(s => ({
                    id: s.STUDENT_ID,
                    name: s.SURNAME + ', ' + s.NAME,
                    timeIn: s.timein,
                    timeOut: s.timeout
                })));
            }
            
            renderStudentData(rv);
        },
        error: function(xhr, status, error) {
            console.log("fetchStudentList error: " + error);
            $("#studentlistarea").html(`
                <div class="bg-red-50 rounded-lg shadow-sm p-8">
                    <div class="text-center text-red-600">
                        <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                        <h3 class="text-lg font-medium mb-2">Error Loading Students</h3>
                        <p class="text-sm">Please try again or contact support.</p>
                        <button onclick="fetchStudentList('${sessionid}','${classid}','${cdrid}','${ondate}')" 
                                class="mt-3 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                            <i class="fas fa-redo mr-1"></i>Retry
                        </button>
                    </div>
                </div>
            `);
        }
    });
}

// Render student data (extracted for reuse with caching)
function renderStudentData(rv) {
    let x = getStudentListHTML(rv);
    $("#studentlistarea").html(x);
}

// Function to invalidate cache when data changes (e.g., after saving attendance)
function invalidateCache(type, key = null) {
    if (type === 'students' && key) {
        delete dataCache.studentsByHte[key];
        delete dataCache.lastUpdated.studentsByHte[key];
    } else if (type === 'htes' && key) {
        delete dataCache.htesBySession[key];
        delete dataCache.lastUpdated.htesBySession[key];
    } else if (type === 'sessions') {
        dataCache.sessions = null;
        dataCache.lastUpdated.sessions = null;
    } else if (type === 'all') {
        // Clear all caches
        dataCache.sessions = null;
        dataCache.htesBySession = {};
        dataCache.studentsByHte = {};
        dataCache.lastUpdated = {
            sessions: null,
            htesBySession: {},
            studentsByHte: {}
        };
        console.log("All caches cleared");
    }
}

// Force refresh function (bypasses cache)
function forceRefreshData() {
    invalidateCache('all');
    // Reload current data
    loadSeassions();
}

// after sa  page mag loading kani na ang e call or e execute

function  saveAttendance(studentid,hteid,coordinatorid,sessionid,ondate,timein,timeout)
{
    $.ajax({
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: {studentid:studentid,hteid:hteid,coordinatorid:coordinatorid,sessionid:sessionid,ondate:ondate,timein:timein,timeout:timeout, action: "saveattendance" },
        beforeSend: function() {
            // para mo show ni siya loading
        },
        success: function(rv) {
            if(rv[0] == 1) {
                alert("Attendance saved successfully!");
                
                // Invalidate student cache for this HTE and date since attendance was updated
                let sessionid = $("#ddlclass").val();
                let classid = $("#hiddenSelectedHteID").val();
                let cdrid = $("#hiddencdrid").val();
                let ondate = $("#dtpondate").val();
                const cacheKey = `${sessionid}_${classid}_${cdrid}_${ondate}`;
                invalidateCache('students', cacheKey);
            } else {
                alert("Error saving attendance: " + rv[0]);
            }
        },
        error: function(xhr, status, error) {
            alert("OOPSIE FROM saveAttendance! " + error);
        }
    });
}


function downloadPDF(sessionid, classid, cdrid) {
    $.ajax({
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: { 
            sessionid: sessionid, 
            classid: classid, 
            cdrid: cdrid, 
            ondate: $("#dtpondate").val(), 
            action: "downloadReport" 
        },
        beforeSend: function() {
            console.log("Downloading PDF...");
        },
        success: function(rv) {
            console.log(rv);
            if (rv.filename) {
                var a = document.createElement('a');
                a.href = "ajaxhandler/" + rv.filename;
                a.download = 'attendance_report.pdf';
                a.click();
            } else {
                console.log("Error: No filename returned");
            }
        },
        error: function(xhr, status, error) {
            console.log("Error: " + error);
        }
    });
}



$(function(e)
{
    $(document).on("click","#logoutBtn",function(ee)
    {
            $.ajax(
            {
                // para mo connect ni siya sa logoutAjac.php
                url: "ajaxhandler/logoutAjax.php",
                type: "POST",
                dataType: "json",
                data: {id:1 },
                beforeSend: function(e) {
                    
                },
                success: function(rv) {
                    if (typeof getBaseUrl === 'function') {
                        document.location.replace(getBaseUrl());
                    } else {
                        var p = window.location.pathname.split('/');
                        var base = (p.length > 1 ? '/' + p[1] + '/' : '/');
                        document.location.replace(base);
                    }
                },
                error: function(xhr, status, error) {
                    alert("Something went wrong!")
                }
            }
        );

    });



    loadSeassions();

    // Initialize proper UI states after page load
    setTimeout(function() {
        // Ensure companies dropdown shows correct initial state
        if ($("#company-select").length === 0 || $("#company-select").find("option").first().text().includes("Loading")) {
            $("#classlistarea").html(`
                <div class="flex flex-col">
                    <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
                    <select id="company-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" disabled>
                        <option value="">Select a session first</option>
                    </select>
                </div>
            `);
        }
        
        // Ensure student list shows correct initial message
        if ($("#studentlistarea").text().includes("No students found.")) {
            $("#studentlistarea").html(`
                <div class="bg-gray-50 rounded-lg shadow-sm p-8">
                    <div class="text-center text-gray-500">
                        <i class="fas fa-calendar-alt text-4xl mb-4 text-gray-400"></i>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Session Selected</h3>
                        <p class="text-sm">Please select a session first to view companies and students.</p>
                    </div>
                </div>
            `);
        }
    }, 500);

    $(document).on("change", "#ddlclass", function(e) {
        currentSessionId = $(this).val();
        $("#hiddenSelectedSessionId").val(currentSessionId);
        
        // Clear previous data immediately including HTE selection
        $("#classdetailsarea").html(``);
        $("#hiddenSelectedHteID").val(-1);
        currentHteId = null;
        
        if (currentSessionId != -1) {
            let cdrid = $("#hiddencdrid").val();
            
            // Show loading message for student list
            $("#studentlistarea").html(`
                <div class="bg-gray-50 rounded-lg shadow-sm p-8">
                    <div class="text-center text-gray-500">
                        <div class="animate-spin rounded-full h-8 w-8 border-2 border-blue-500 border-t-transparent mx-auto mb-4"></div>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">Loading Companies</h3>
                        <p class="text-sm">Please wait while we load available companies...</p>
                    </div>
                </div>
            `);
            
            // Show loading state immediately before fetching
            fetchTHE(cdrid, currentSessionId); // Fetch HTEs for the selected session
        } else {
            // Show empty state for companies when no session selected
            $("#classlistarea").html(`
                <div class="flex flex-col">
                    <label class="text-sm font-medium text-gray-700 mb-1">COMPANIES</label>
                    <select id="company-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" disabled>
                        <option value="">Select a session first</option>
                    </select>
                </div>
            `);
            
            // Show message for student list when no session selected
            $("#studentlistarea").html(`
                <div class="bg-gray-50 rounded-lg shadow-sm p-8">
                    <div class="text-center text-gray-500">
                        <i class="fas fa-calendar-alt text-4xl mb-4 text-gray-400"></i>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Session Selected</h3>
                        <p class="text-sm">Please select a session first to view companies and students.</p>
                    </div>
                </div>
            `);
        }
    });

    $(document).on("change", "#company-select", function(e) {
        // Hide control panel forms when showing student list
        $('.form-container').slideUp();
        
        let selectedOption = $(this).find("option:selected");
        let building = selectedOption.data("building");
        
        if (!building || $(this).val() === "") {
            // No company selected - show message
            $("#classdetailsarea").html(``);
            $("#studentlistarea").html(`
                <div class="bg-gray-50 rounded-lg shadow-sm p-8">
                    <div class="text-center text-gray-500">
                        <i class="fas fa-users text-4xl mb-4 text-gray-400"></i>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Company Selected</h3>
                        <p class="text-sm">Please select a company from the dropdown above to view students.</p>
                    </div>
                </div>
            `);
            return;
        }

        currentHteId = building.HTE_ID;
        $("#hiddenSelectedHteID").val(currentHteId);
        let x = getClassdetailsAreaHTML(building);
        $("#classdetailsarea").html(x);
        let cdrid = $("#hiddencdrid").val();
        let ondate = $("#dtpondate").val();
        
        if (currentSessionId != -1) {
            // Show loading state while fetching students
            $("#studentlistarea").html(`
                <div class="bg-gray-50 rounded-lg shadow-sm p-8">
                    <div class="text-center text-gray-500">
                        <div class="animate-spin rounded-full h-8 w-8 border-2 border-blue-500 border-t-transparent mx-auto mb-4"></div>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">Loading Students</h3>
                        <p class="text-sm">Fetching student list for ${building.NAME}...</p>
                    </div>
                </div>
            `);
            fetchStudentList(currentSessionId, currentHteId, cdrid, ondate);
        }
    });



    $(document).on("click",".cbpresent",function(e){
        let studentid=$(this).data('studentid');
        let hteid=$("#hiddenSelectedHteID").val();
        let coordinatorid= $("#hiddencdrid").val();
        let sessionid=$("#ddlclass").val();
        let ondate=$("#dtpondate").val();
        let timein = $(this).closest(".studentdetails").find(".cbtimein").val();
        let timeout = $(this).closest(".studentdetails").find(".cbtimeout").val();
        if(timein === undefined || timein === null || timein === ""){
            timein = "";
        }
        if(timeout === undefined || timeout === null || timeout === ""){
            timeout = "";
        }
        saveAttendance(studentid, hteid, coordinatorid, sessionid, ondate, timein, timeout);
    });

    $(document).on("change", ".cbtimein", function() {
        let studentid = $(this).data('studentid');
        let hteid = $("#hiddenSelectedHteID").val();
        let coordinatorid = $("#hiddencdrid").val();
        let sessionid = $("#ddlclass").val();
        let ondate = $("#dtpondate").val();
        let timein = $(this).val();
        let timeout = $(this).closest(".studentdetails").find(".cbtimeout").val();
    
        saveAttendance(studentid, hteid, coordinatorid, sessionid, ondate, timein, timeout);
    });
    
    $(document).on("change", ".cbtimeout", function() {
        let studentid = $(this).data('studentid');
        let hteid = $("#hiddenSelectedHteID").val();
        let coordinatorid = $("#hiddencdrid").val();
        let sessionid = $("#ddlclass").val();
        let ondate = $("#dtpondate").val();
        let timein = $(this).closest(".studentdetails").find(".cbtimein").val();
        let timeout = $(this).val();
    
        saveAttendance(studentid, hteid, coordinatorid, sessionid, ondate, timein, timeout);
    });

    $(document).on("change","#dtpondate",function(e){

        let sessionid=$("#ddlclass").val();
        let classid= $("#hiddenSelectedHteID").val();
        let cdrid=$("#hiddencdrid").val();
        let ondate=$("#dtpondate").val();
        console.log("Date changed to:", ondate, "for session:", sessionid, "HTE:", classid, "coordinator:", cdrid);
        if(sessionid!=-1)
        {
        
            fetchStudentList(sessionid,classid,cdrid,ondate);
        }
    });

    // Function to quickly set date and refresh attendance
    window.setDateAndRefresh = function(date) {
        $("#dtpondate").val(date);
        $("#dtpondate").trigger('change');
    };
    $(document).on("click", "#btnReport", function() {
        if (currentSessionId != -1 && currentHteId) {
            let cdrid = $("#hiddencdrid").val();
            downloadPDF(currentSessionId, currentHteId, cdrid);
        } else {
            alert("Please select an HTE first.");
        }
    });
    $(document).ready(function() {
    // Tab click event for stats tab
    $('#statsTabBtn').click(function() {
        $(this).addClass('active');
        $('#evalQuestionsTabBtn').removeClass('active');
        $('#rateTabBtn').removeClass('active');
        $('#reviewTabBtn').removeClass('active');
        $('#statsTabContent').addClass('active');
        $('#evalQuestionsTabContent').removeClass('active');
        $('#rateTabContent').removeClass('active');
        $('#reviewTabContent').removeClass('active');
        loadEvaluationStats();
    });

    // Hide stats cards when switching away from stats tab
    $('#evalQuestionsTabBtn, #rateTabBtn, #reviewTabBtn').click(function() {
        $('#statsSummary').html('');
        // Optionally remove per-question stats table if present
        $('.per-question-stats-table').remove();
        // Hide the entire stats-eval-container
        $('.stats-eval-container').hide();
    });

    // Show stats-eval-container only when stats tab is active
    $('#statsTabBtn').click(function() {
        $('.stats-eval-container').show();
    });

    // Load stats and render charts
    function loadEvaluationStats() {
        // Load Chart.js if not loaded
        if (typeof Chart === 'undefined') {
            $.getScript('https://cdn.jsdelivr.net/npm/chart.js', function() {
                fetchAndRenderStats();
            });
        } else {
            fetchAndRenderStats();
        }
    }

    function fetchAndRenderStats() {
        $.ajax({
            url: 'ajaxhandler/evaluationStatsAjax.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                // Summary as individual cards
                $('#statsSummary').html(`
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-blue-50 rounded-lg p-6 flex flex-col items-center justify-center shadow">
                            <div class="text-lg font-semibold text-blue-700 mb-2">Students Answered</div>
                            <div class="text-3xl font-bold text-blue-900">${data.answeredCount} <span class="text-gray-500 text-xl">/ ${data.totalStudents}</span></div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-6 flex flex-col items-center justify-center shadow">
                            <div class="text-lg font-semibold text-green-700 mb-2">Students Rated</div>
                            <div class="text-3xl font-bold text-green-900">${data.ratedCount} <span class="text-gray-500 text-xl">/ ${data.totalStudents}</span></div>
                        </div>
                    </div>
                `);

                // Prepare chart data
                let labels = [];
                let datasets = [
                    { label: '5', backgroundColor: '#3182ce', data: [] },
                    { label: '4', backgroundColor: '#4299e1', data: [] },
                    { label: '3', backgroundColor: '#63b3ed', data: [] },
                    { label: '2', backgroundColor: '#90cdf4', data: [] },
                    { label: '1', backgroundColor: '#bee3f8', data: [] }
                ];
                let questionRows = '';
                let allZero = true;
                if (!data.questionStats || Object.keys(data.questionStats).length === 0) {
                    allZero = true;
                } else {
                    // Check if all ratings for all questions are zero
                    Object.values(data.questionStats).forEach(q => {
                        let sum = Object.values(q.ratings).reduce((a, b) => a + b, 0);
                        if (sum > 0) allZero = false;
                    });
                }
                if (allZero) {
                    $('#tableContainer').html('<div class="no-eval-message" style="text-align:center;color:#e53e3e;font-size:1.2em;margin-top:2em;">No evaluation received yet.</div>');
                    $('#chartContainer').html('');
                    return;
                }
                Object.values(data.questionStats).forEach(q => {
                    labels.push(q.question_text);
                    let row = `<tr><td style='font-weight:500;'>${q.question_text}</td>`;
                    for (let i = 5; i >= 1; i--) {
                        datasets[5-i].data.push(q.ratings[i] || 0);
                        row += `<td style='text-align:center;'>${q.ratings[i] || 0}</td>`;
                    }
                    row += '</tr>';
                    questionRows += row;
                });

                // Destroy previous chart if exists and is a Chart.js instance
                if (window.questionRatingsChart && typeof window.questionRatingsChart.destroy === 'function') {
                    window.questionRatingsChart.destroy();
                }
                // Ensure chart and table containers exist
                if ($('#chartContainer').length === 0) {
                    $('#statsSummary').after('<div id="chartContainer" style="margin-top:2rem;"></div>');
                }
                if ($('#tableContainer').length === 0) {
                    $('#chartContainer').after('<div id="tableContainer"></div>');
                }
                // Render chart in chartContainer
                $('#chartContainer').html('<div class="flex justify-center mb-8"><canvas id="questionRatingsChart" class="max-w-3xl w-full h-96"></canvas></div>');
                let ctx = document.getElementById('questionRatingsChart').getContext('2d');
                window.questionRatingsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'top' },
                            title: { display: true, text: 'Ratings Distribution per Question' }
                        },
                        scales: {
                            x: { title: { display: true, text: 'Questions' }, stacked: true },
                            y: { title: { display: true, text: 'Number of Ratings' }, beginAtZero: true, stacked: true }
                        }
                    }
                });

                // Render per-question stats table in tableContainer
                $('.per-question-stats-table').remove();
                let statsTableHtml = `
                    <div class="per-question-stats-table mt-10">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4">Per-Question Rating Stats</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white rounded-lg shadow border border-gray-200">
                                <thead class="bg-blue-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-bold text-blue-700">Question</th>
                                        <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-16">5</th>
                                        <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-16">4</th>
                                        <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-16">3</th>
                                        <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-16">2</th>
                                        <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-16">1</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${questionRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                $('#tableContainer').html(statsTableHtml);
            },
            error: function() {
                $('#statsSummary').html('<span style="color:red;">Error loading stats.</span>');
            }
        });
    }
    // Student search filter for Review & Rate Student Answers
    $(document).on('input', '#reviewStudentSearch', function() {
        const query = $(this).val().toLowerCase();
        let lastMatch = [];
        $('.student-review-group').each(function() {
            const name = $(this).find('.student-review-title').text().toLowerCase();
            if (name.includes(query)) {
                $(this).show();
                lastMatch.push(this);
            } else {
                $(this).hide();
            }
        });
        if (lastMatch.length === 0 && query.length > 0) {
            // If no match, show last matched students for previous query
            if (window.lastStudentSearchMatch && window.lastStudentSearchMatch.length > 0) {
                $(window.lastStudentSearchMatch).show();
            }
        } else {
            window.lastStudentSearchMatch = lastMatch;
        }
    });
        $(document).on("click", ".btnAdd", function(e) {
            e.preventDefault();
            let hteId = $("#hiddenSelectedHteID").val();

            if (!hteId || hteId === "") {
                alert("Please Select HTE Before Adding Student");
                return;
            }
          // Close other forms
          $('#allStudentsContainer').fadeOut();
          $('#addHTEForm').fadeOut();
          $('#sessionFormContainer').fadeOut();
          $("#addStudentForm").show();
        });
    


        // $(document).on("submit", "#studentEditForm", function(e) {
        //     e.preventDefault(); // Prevent the default form submission
        
        //     // Get the student ID from the form (should be populated)
        //     var studentId = $("#editStudentId").val();
        //     console.log("Student ID before submission:", studentId); // Debug: Check if studentId is populated
        
               
        //     // Ensure studentId is not empty
        //     if (!studentId) {
        //         alert("Student ID is required!");
        //         return; // Stop the submission if the student ID is missing
        //     }
        
        //     // Serialize the form data, including the studentId
        //     var formData = $(this).serialize();
        //     console.log("Serialized Form Data:", formData); // Debug: Check serialized form data
        
        //     // Ensure the action is appended to the form data
        //     formData += "&action=updateStudent"; 
        
        //     // Log the final form data before sending the request
        //     console.log("Final Form Data:", formData); 
        
        //     // Send AJAX request to update student details
        //     $.ajax({
        //         url: 'ajaxhandler/coordinatorEditStudentAjax.php',
        //         type: 'POST',
        //         data: formData,
        //         dataType: 'json',
        //         success: function(response) {
        //             if (response.status === 'success') {
        //                 alert("Student details updated successfully!");
        //                 $("#editStudentForm").hide(); // Hide the modal after successful update
        //                 loadStudentDetails(studentId); // Optionally refresh the student details
        //             } else {
        //                 alert("Error: " + response.message);
        //             }
        //         },
        //         error: function(xhr, status, error) {
        //             alert("An error occurred: " + error);
        //         }
        //     });
        // });
        
        
        
        $(document).on("submit", "#studentForm", function(e) {
            e.preventDefault(); // Prevent the default form submission
            if ($(this).data('submitted')) {
                return;
            }
            $(this).data('submitted', true);

            let studentId = $("#studentId").val();  // Ensure this is correctly populated
            let action = studentId ? "updateStudent" : "addStudent"; // Determine action based on studentId

            var formData = new FormData(this);
            // Use the actual selected dropdown values for HTE and session
            formData.append('hte_id', $("#hteSelectStudent").val());
            formData.append('session_id', $("#sessionSelectStudent").val());
            formData.append('action', action);  // Append action to FormData
            formData.append('student_id', studentId); // Append studentId for update

            // Check if a CSV file is selected
            if ($("#csvFile").get(0).files.length > 0) {
                console.log($("#csvFile").get(0).files); // Log selected files
                
                // Show loading indicator on submit button
                const $submitBtn = $(this).find('button[type="submit"]');
                const originalText = $submitBtn.text();
                $submitBtn.prop('disabled', true).html('<span class="spinner">⏳</span> Uploading CSV...');
                
                $("#studentForm input, #studentForm select").prop("disabled", true); // Disable form inputs during submission

                // AJAX request to upload CSV
                $.ajax({
                    url: "ajaxhandler/uploadCSV.php",
                    type: "POST",
                    data: formData,
                    contentType: false, // Important for file uploads
                    processData: false, // Important for file uploads
                    success: function(response) {
                        console.log("CSV upload response:", response); // Log the full response for debugging
                        if (response.success) {
                            alert("Students added successfully from CSV!");
                            $("#addStudentForm").hide(); // Hide the form after success
                            let cdrid = $("#hiddencdrid").val();
                            let ondate = $("#dtpondate").val();
                            fetchStudentList(currentSessionId, currentHteId, cdrid, ondate); // Refresh the student list
                            $submitBtn.text('✅ CSV Uploaded');
                        } else {
                            // Only show error if no students were inserted
                            if (!response.inserted || response.inserted === 0) {
                                alert(response.message); // Show error message only if nothing was inserted
                            }
                            $submitBtn.prop('disabled', false).text(originalText);
                        }
                        $("#studentForm input, #studentForm select").prop("disabled", false);
                        $("#studentForm").data('submitted', false);
                    },
                    error: function(xhr, status, error) {
                        console.error("Error uploading CSV:", error);
                        if (xhr && xhr.responseText) {
                            console.log("CSV upload error response:", xhr.responseText);
                        }
                        alert("An error occurred while uploading the CSV file. Please check the console for details.");
                        $submitBtn.prop('disabled', false).text(originalText);
                        $("#studentForm input, #studentForm select").prop("disabled", false);
                        $("#studentForm").data('submitted', false);
                    }
                });
            } else {
                // Check if HTE and Session are selected
                // Handle single student addition
                if (!currentHteId || currentHteId === "") {
                    alert("Please select an HTE before adding a student.");
                    $("#studentForm").data('submitted', false);
                    return;
                }
                if (!currentSessionId || currentSessionId === "-1") {
                    alert("Please select a session before adding a student.");
                    $("#studentForm").data('submitted', false);
                    return;
                }
                let formData = $(this).serialize();
                formData += "&action=addStudent&hteId=" + currentHteId + "&sessionId=" + currentSessionId;
                console.log("Form data being sent:", formData);
                $.ajax({
                    url: "ajaxhandler/attendanceAJAX.php",
                    type: "POST",
                    data: formData,
                    dataType: "json",
                    success: function(response) {
                        console.log("Server response:", response);
                        if (response.success) {
                            alert("Student added successfully!");
                            $("#addStudentForm").hide();
                            let cdrid = $("#hiddencdrid").val();
                            let ondate = $("#dtpondate").val();
                            fetchStudentList(currentSessionId, currentHteId, cdrid, ondate);
                            $("#studentForm")[0].reset();
                        } else {
                            console.error(response.message);
                            alert(response.message);
                        }
                        $("#studentForm").data('submitted', false);
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", status, error);
                        console.log("Response text:", xhr.responseText);
                        let errorMessage = "An error occurred while adding the student.";
                        try {
                            let errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.message) {
                                errorMessage = errorResponse.message;
                            }
                        } catch (e) {
                            console.error("Error parsing error response:", e);
                        }
                        alert("Error: " + errorMessage + "\nPlease check the console for more details.");
                        $("#studentForm").data('submitted', false);
                    }
                });
            }
        });      

        $(document).on("click", "#closeForm", function() {
            if ($("#addStudentForm").is(":visible")) {
                $("#addStudentForm").fadeOut(function() {
                    $("#studentForm")[0].reset();
                    $("#studentForm input, #studentForm select").prop("disabled", false);
                });
            }
        });
    
        $(document).on("change", "#csvFile", function() {
            // Disable all except HTE and Session dropdowns
            $("#studentForm input[type='text'], #studentForm input[type='number'], #studentForm input[type='email']").prop("disabled", $(this).get(0).files.length > 0);
            // Keep HTE and Session enabled
            $("#hteSelectStudent, #sessionSelectStudent").prop("disabled", false);
            // Disable other selects except HTE and Session
            $("#studentForm select").not("#hteSelectStudent, #sessionSelectStudent").prop("disabled", $(this).get(0).files.length > 0);
        });
    });
    
    


    
    $(document).ready(function() {

        $(document).on("click", ".btnAddHTE", function(e) {
            e.preventDefault();
          $('#studentFormContainer').hide();
          $('#sessionFormContainer').fadeOut();
          $('#allStudentsContainer').hide();
          $('#deleteHTEFormContainer').hide();
          $('#deleteSessionFormContainer').hide();
          $('#deleteStudentFormContainer').hide();
          $('#addHTEFormContainer').fadeIn();
        });
    


    
    
        $(document).on("click", "#closeHTEForm", function(e) {
            $("#addHTEForm").fadeOut();
        });


    $(document).on("click", ".btnDelete", function() {
        let studentId = $(this).data('studentid'); 
        if (confirm("Are you sure you want to delete this student?")) { 
            deleteStudent(studentId); 
        }
    });

    function deleteStudent(studentId) {
        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php", // URL to your AJAX handler
            type: "POST",
            dataType: "json",
            data: {
                studentId: studentId,
                action: "deleteStudent" // Specify the action to be performed
            },
            success: function(response) {
                if (response.logResults) {
                    console.log('Batch Deletion Log Results:', response.logResults);
                }
                if (response.success) {
                    alert("Student deleted successfully!");
                    // Automatically refresh the list after successful deletion
                    fetchStudentList(currentSessionId, currentHteId, cdrid, ondate); // Refresh the list
                } else {
                    alert("Error deleting student: " + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error deleting student:", error);
                alert("An error occurred while deleting the student.");
            }
        });
    }

///////////////////////////////////

$(document).on("click", ".btnDeleteHTE", function () {
    const hteId = $(this).data("hteid");
    const sessionId = $(this).data("sessionid");

    // Log to verify the values of hteId and sessionId
    console.log("HTE ID:", hteId);
    console.log("Session ID:", sessionId);

    if (!hteId || !sessionId) {
        alert("HTE ID or Session ID not found!");
        return;
    }

    // Proceed with the AJAX request if both values are valid
    $('#hiddenSelectedHteID').val(hteId);
    $('#hiddenSelectedSessionId').val(sessionId);

    if (confirm("Are you sure you want to delete this HTE and all associated students?")) {
        $.ajax({
            url: "ajaxhandler/attendanceAJAX.php",
            type: "POST",
            data: {
                action: "deleteHTE",
                hteId: hteId,
                sessionId: sessionId,
            },
            success: function(response) {
                console.log("Server Response: ", response);  // Log the response to check what it contains

                if (typeof response === 'string') {
                    try {
                        const result = JSON.parse(response);  // Parse if it's a string
                        handleResponse(result);
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        alert('Error: Unable to parse server response');
                    }
                } else {
                    // If it's already an object, just pass it to the handler
                    handleResponse(response);
                }
            },
            error: function (xhr, status, error) {
                // alert("AJAX Error: " + error);
            },
        });

        function handleResponse(result) {
            if (result.success) {
                alert(result.message); // Success message

                // Remove HTE row from the frontend
                // Assuming the HTML structure has an element with id `hteRow_${hteId}`
                $('#hteRow_' + hteId).remove(); // Adjust the selector based on your HTML structure

                // Optionally, fetch and update the student list to ensure data is in sync
                fetchUpdatedStudentList();
            } else {
                alert("Error: " + result.message);
            }
        }

        function fetchUpdatedStudentList() {
            // Assuming you have a function to fetch and update the student list
            $.ajax({
                url: "ajaxhandler/attendanceAJAX.php",
                type: "POST",
                data: {
                    action: "fetchStudentList",
                    sessionId: sessionId,
                    classId: $("#classId").val(),  // Assuming classId is available
                    cdrid: $("#cdrid").val(),  // Assuming cdrid is available
                    ondate: $("#ondate").val(),  // Assuming ondate is available
                },
                success: function(response) {
                    if (response && response.success) {
                        // Update the student list UI based on the new data
                        $('#studentListContainer').html(getStudentListHTML(response.studentList));
                    } else {
                        alert("Error fetching updated student list.");
                    }
                },
                error: function(xhr, status, error) {
                    // alert("AJAX Error: " + error);
                }
            });
        }
    }
});




$(document).on("submit", "#coordinatorForm", function(e) {
    e.preventDefault(); // Prevent the default form submission

    // Serialize the form data
    var formData = $(this).serialize();

    $.ajax({
        url: "ajaxhandler/addCoordinatorAjax.php", // Your server-side script to handle the addition
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(response) {
            if (response.success) {
                alert("Coordinator/Admin added successfully!");
                $("#addCoordinatorForm").hide(); // Hide the form after success
                // Optionally refresh the coordinator list or do something else
            } else {
                alert("Error: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error("Error adding coordinator/admin:", error);
            alert("An error occurred while adding the coordinator/admin.");
        }
    });
});



// --- Coordinator Evaluation Question Management ---
// Note: Question loading is now handled by mainDashboard.php's loadQuestionCategories system

// Add new evaluation question
$('#addEvaluationQuestionForm').on('submit', function(e) {
    e.preventDefault();
    const category = $('#questionCategory').val();
    const question_text = $('#questionText').val();
    $.ajax({
        url: 'ajaxhandler/coordinatorEvaluationQuestionsAjax.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ category, question_text }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Refresh questions via mainDashboard.php system
                if (typeof loadQuestionCategories === 'function') {
                    loadQuestionCategories();
                }
                $('#addEvaluationQuestionForm')[0].reset();
            } else {
                alert('Failed to add question.');
            }
        },
        error: function() {
            alert('Error adding question.');
        }
    });
});

// Edit or deactivate question
$(document).on('click', '.btn-edit-question, .btn-deactivate-question', function() {
    const question_id = $(this).data('id');
    let updateData = { question_id };
    if ($(this).hasClass('btn-edit-question')) {
        // For simplicity, prompt for new text and category
        updateData.question_text = prompt('Enter new question text:');
        updateData.category = prompt('Enter new category:');
    } else {
        updateData.status = 'inactive';
    }
    $.ajax({
        url: 'ajaxhandler/coordinatorEvaluationQuestionsAjax.php',
        type: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify(updateData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Refresh questions via mainDashboard.php system
                if (typeof loadQuestionCategories === 'function') {
                    loadQuestionCategories();
                }
            } else {
                alert('Failed to update question.');
            }
        },
        error: function() {
            alert('Error updating question.');
        }
    });
});

$(document).ready(function() {
    // Load all active questions for reference in Evaluation tab
    function loadAllQuestionsList() {
        $.ajax({
            url: 'ajaxhandler/coordinatorEvaluationQuestionsAjax.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.questions) {
                    const activeQuestions = response.questions.filter(q => {
                        const s = q.status;
                        return s === 'active' || s === 1 || s === '1' || s === true;
                    });
                    // Collect unique categories
                    const categories = [...new Set(activeQuestions.map(q => q.category ? q.category.trim() : 'Other'))];
                    // On mobile, this will stack vertically with categories on top
                    let html = `<div class='all-questions-wrapper flex flex-col md:flex-row w-full'>`;
                    // Left column (category selector)
                    html += `<div class='all-questions-categories-section left-col w-full md:w-1/5 max-w-xs md:pr-4 order-1 mb-4 md:mb-0'>`;
                    html += `<h2 class='text-xl font-bold text-gray-800 mb-4'>Categories</h2>`;
                    html += `<div class='mb-4'><label for='questionCategoryDropdown' class='mr-2 text-gray-700 font-medium'>Category:</label><select id='questionCategoryDropdown' class='border border-gray-300 rounded-md px-3 py-2 text-base focus:outline-none focus:ring-2 focus:ring-blue-500 w-full'>`;
                    categories.forEach(cat => {
                        html += `<option value='${cat}'>${cat}</option>`;
                    });
                    html += `</select></div>`;
                    html += `<div class='flex items-center mb-2'>
                        <span class='inline-flex items-center px-3 py-1 text-sm font-medium text-blue-700 bg-blue-100 rounded-full mr-2'>
                            <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15.232 5.232l3.536 3.536M9 13h3l9-9a1.414 1.414 0 00-2-2l-9 9v3z' /></svg>
                            Editable: Click question text to edit
                        </span>
                    </div>`;
                    html += `</div>`;
                    // Right column (questions)
                    html += `<div class='all-questions-content-section right-col w-full md:w-4/5 md:pl-4 order-2'>`;
                    html += `<h2 class='text-2xl font-bold text-gray-800 mb-4'>All Evaluation Questions</h2>`;
                    html += `<div id='questionsByCategory' style='max-height:calc(100vh - 320px);overflow-y:auto;'></div>`;
                    html += `<div class='flex flex-col items-center mt-6 mb-4'>
                        <button id='btnSaveAllQuestions' class='px-8 py-2 text-lg font-semibold bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition-all duration-150'>
                            <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 inline-block mr-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7' /></svg>
                            Save All Changes
                        </button>
                        <span id='saveAllStatus' class='mt-4 px-4 py-2 rounded-lg text-base font-medium hidden'></span>
                    </div>`;
                    html += `</div>`;
                    html += `</div>`;
                    $('#evalQuestionsTabContent').html(html);

                    // Render questions by selected category
                    function renderQuestions(selectedCategory) {
                        let filtered = activeQuestions.filter(q => (q.category ? q.category.trim() : 'Other') === selectedCategory);
                        let sectionHtml = '';
                        if (filtered.length === 0) {
                            sectionHtml = `<div class='text-center text-gray-500 py-6'>No questions found for this category.</div>`;
                        } else {
                            sectionHtml += `<ul class='space-y-3'>`;
                            filtered.forEach(function(q) {
                                sectionHtml += `<li class='bg-white rounded-lg shadow p-4'>
                                  <div class='text-gray-700 text-base font-medium' contenteditable='true' data-questionid='${q.question_id}'>${q.question_text}</div>
                                </li>`;
                            });
                            sectionHtml += `</ul>`;
                        }
                        $('#questionsByCategory').html(sectionHtml);
                    }
                    // Initial render (first category)
                    if (categories.length > 0) {
                        renderQuestions(categories[0]);
                        $('#questionCategoryDropdown').val(categories[0]);
                    }
                    // Dropdown change event
                    $('#questionCategoryDropdown').on('change', function() {
                        renderQuestions($(this).val());
                    });

                    $(document).off('click', '#btnSaveAllQuestions').on('click', '#btnSaveAllQuestions', function() {
                        // Show loading indicator on button
                        const $saveBtn = $(this);
                        const originalText = $saveBtn.text();
                        $saveBtn.prop('disabled', true).html('<span class="spinner">⏳</span> Saving Questions...');
                        
                        var questions = [];
                        $('.question-body[contenteditable="true"], .text-gray-700[contenteditable="true"]').each(function() {
                            var questionId = $(this).data('questionid');
                            var newText = $(this).text().trim();
                            questions.push({ question_id: questionId, question_text: newText });
                        });
                        var $status = $('#saveAllStatus');
                        $status.removeClass('hidden');
                        $status.text('Saving...').removeClass('bg-green-100 text-green-700 bg-red-100 text-red-700').addClass('bg-blue-100 text-blue-700');
                        $.ajax({
                            url: 'ajaxhandler/coordinatorEvaluationQuestionsAjax.php',
                            type: 'PUT',
                            contentType: 'application/json',
                            data: JSON.stringify({ questions: questions }),
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $status.text('All changes saved!').removeClass('bg-blue-100 text-blue-700 bg-red-100 text-red-700').addClass('bg-green-100 text-green-700');
                                    $saveBtn.prop('disabled', false).text('✅ Questions Saved');
                                    // Reset button text after 2 seconds
                                    setTimeout(() => {
                                        $saveBtn.text(originalText);
                                    }, 2000);
                                } else {
                                    $status.text('Failed to save changes.').removeClass('bg-blue-100 text-blue-700 bg-green-100 text-green-700').addClass('bg-red-100 text-red-700');
                                    $saveBtn.prop('disabled', false).text(originalText);
                                }
                            },
                            error: function() {
                                $status.text('Error saving changes.').removeClass('bg-blue-100 text-blue-700 bg-green-100 text-green-700').addClass('bg-red-100 text-red-700');
                                $saveBtn.prop('disabled', false).text(originalText);
                            }
                        });
                    });
                } else {
                    $('#evalQuestionsTabContent').html("<div class='all-questions-container'><h2 class='text-2xl font-bold text-gray-800 mb-4'>No active questions found.</h2></div>");
                }
            },
            error: function() {
                $('#evalQuestionsTabContent').html("<div class='all-questions-container'><h2 class='text-2xl font-bold text-gray-800 mb-4'>Error loading questions.</h2></div>");
            }
        });
    }

    // Initial load for Evaluation tab
    loadAllQuestionsList();

    // Load student answers for coordinator review/rating
    function loadReviewEvalList() {
        // Get reviewed student IDs first
        $.ajax({
            url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
            type: 'POST',
            dataType: 'json',
            data: JSON.stringify({ action: 'getReviewedStudents' }),
            contentType: 'application/json',
            success: function(reviewedResp) {
                let reviewedIds = reviewedResp.reviewedIds || [];
                let ratingLookup = {};
                if (Array.isArray(reviewedResp.ratings)) {
                    reviewedResp.ratings.forEach(function(r) {
                        ratingLookup[r.student_evaluation_id] = r.rating;
                    });
                }
                // Now fetch all answers
                $.ajax({
                    url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.answers) {
                            let html = '';
                            let students = {};
                            response.answers.forEach(function(ans) {
                                // Always use STUDENT_ID for grouping
                                const sid = ans.STUDENT_ID || ans.student_id;
                                if (!students[sid]) students[sid] = {};
                                if (!students[sid][ans.category]) students[sid][ans.category] = [];
                                students[sid][ans.category].push(ans);
                            });
                            // REVIEW TAB: Only show reviewed students
                            const reviewedIdsStr = reviewedIds.map(String);
                            let reviewedStudents = Object.keys(students).filter(sid => reviewedIdsStr.includes(String(sid)));
                            if (reviewedStudents.length === 0) {
                                html = `<div class="no-match-message" style="text-align:center; color:#a0aec0; font-size:1.2em; margin-top:2em;">No reviewed students</div>`;
                            } else {
                                reviewedStudents.forEach(function(studentId) {
                                    let firstAns = null;
                                    Object.keys(students[studentId]).forEach(function(category) {
                                        if (!firstAns) firstAns = students[studentId][category][0];
                                    });
                                    let fullName = firstAns ? `${firstAns.SURNAME}, ${firstAns.NAME}` : '';
                                    html += `<div class='student-review-group'><h3 class='student-review-title'>${fullName}<br><span style='font-size:1rem;font-weight:400;'>Student ID: ${studentId}</span></h3>`;
                                    html += `<div class='question-cards-row'>`;
                                    Object.keys(students[studentId]).forEach(function(category) {
                                        html += `<div class='student-category-group'><h4 class='student-category-title'>${category}</h4>`;
                                        students[studentId][category].forEach(function(ans) {
                                            let actualRating = ratingLookup[ans.student_evaluation_id] || '';
                                            html += `<div class='student-eval-block' data-evalid='${ans.student_evaluation_id}'>
                                                <div class='eval-question-box'>${ans.question_text}</div>
                                                <table class='eval-table'>
                                                    <tr>
                                                        <td class='eval-answer-cell' rowspan='2'>${ans.answer}</td>
                                                        <td class='eval-rating-header' colspan='5' style='text-align:center;'>Table Rating</td>
                                                    </tr>
                                                    <tr>`;
                                            for (let i = 5; i >= 1; i--) {
                                                html += `<td class='eval-rating-cell'><span>${i}<br><span class='reviewed-rating' style='color:${actualRating == i ? "#3182ce" : "#a0aec0"};font-size:1.3em;'>${actualRating == i ? '&#9733;' : '&#9734;'}</span></span></td>`;
                                            }
                                            html += `</tr>
                                                </table>
                                                <span class='rate-status' id='rateStatus_${ans.student_evaluation_id}'></span>
                                            </div>`;
                                        });
                                        html += `</div>`;
                                    });
                                    html += `</div>`;
                                    html += `</div>`;
                                });
                            }
                            $('#reviewEvalList').html(html);
                        } else {
                            $('#reviewEvalList').html('<div class="no-match-message" style="text-align:center; color:#a0aec0; font-size:1.2em; margin-top:2em;">No record for this student</div>');
                        }
                    },
                    error: function() {
                        $('#reviewEvalList').html('<p>Error loading student answers for review.</p>');
                    }
                });
            }
        });
    }

    // Load on tab show (or page load)
    loadReviewEvalList();

    // Handle rating submission
    $(document).on('click', '.btn-save-all-ratings', function() {
    const $btn = $(this);
    const studentId = $btn.data('studentid');
    // Use #rateEvalList as parent for pre-assessment tab, fallback to .student-review-group for review tab
    let $parentGroup = $btn.closest('#rateEvalList');
    if ($parentGroup.length === 0) {
        $parentGroup = $btn.closest('.student-review-group');
    }
    if ($parentGroup.length === 0) {
        // Fallback: find by studentId in data attribute
        $parentGroup = $(`#rateEvalList:has(.btn-save-all-ratings[data-studentid='${studentId}'])`);
        if ($parentGroup.length === 0) {
            $parentGroup = $(`.student-review-group:has(.btn-save-all-ratings[data-studentid='${studentId}'])`);
        }
    }
    console.log('[DEBUG] Parent container HTML:', $parentGroup.html());
    // Find all .student-eval-block elements in this group
    const $evalBlocks = $parentGroup.find('.student-eval-block');
    console.log(`[DEBUG] Found ${$evalBlocks.length} .student-eval-block elements for studentId ${studentId}`);
    $evalBlocks.each(function(idx) {
        console.log(`[DEBUG] .student-eval-block[${idx}] data-evalid:`, $(this).data('evalid'));
    });
    // Prevent multiple clicks
    if ($btn.prop('disabled')) return;
    console.log('[DEBUG] Save All Ratings button pressed for studentId:', studentId);
    // Collect ratings for debug
    let debugRatings = [];
    $evalBlocks.each(function() {
        const student_evaluation_id = $(this).data('evalid');
        const rating = $(`input[name='likert_${studentId}_${student_evaluation_id}']:checked`).val();
        debugRatings.push({ student_evaluation_id, rating, STUDENT_ID: studentId });
    });
    console.log('[DEBUG] Ratings to be submitted:', debugRatings);
    const coordinator_id = $('#hiddencdrid').val();
    let ratings = [];
    $evalBlocks.each(function() {
        const student_evaluation_id = $(this).data('evalid');
        const rating = $(`input[name='likert_${studentId}_${student_evaluation_id}']:checked`).val();
        ratings.push({ student_evaluation_id, rating, STUDENT_ID: studentId });
    });
    let missing = ratings.filter(r => !r.rating);
    if (missing.length > 0) {
        // Show modal for missing ratings (reuse modal)
        showStatusModal('Please Rate All.');
        missing.forEach(r => {
            $(`#rateStatus_${r.student_evaluation_id}`).text('Please select a rating.').css('color', 'red');
        });
        return;
    }
    // Only disable button after AJAX success
    $.ajax({
        url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ coordinator_id, ratings }),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                ratings.forEach(r => {
                    $(`#rateStatus_${r.student_evaluation_id}`).text('Rating saved!').css('color', 'green');
                });
                $btn.prop('disabled', true);
                showStatusModal('Ratings have been saved!');
            } else {
                ratings.forEach(r => {
                    $(`#rateStatus_${r.student_evaluation_id}`).text('Failed to save rating.').css('color', 'red');
                });
            }
        },
        error: function() {
            ratings.forEach(r => {
                $(`#rateStatus_${r.student_evaluation_id}`).text('Error saving rating.').css('color', 'red');
            });
        }
    });
// Reusable modal function for status messages
function showStatusModal(message) {
    if ($('#statusModal').length === 0) {
        $('body').append(`
            <div id="statusModal" class="custom-modal-bg" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);z-index:9999;display:flex;align-items:center;justify-content:center;">
                <div class="custom-modal-content" style="background:#fff;padding:32px 24px;border-radius:10px;box-shadow:0 2px 16px rgba(0,0,0,0.18);max-width:350px;text-align:center;">
                    <h3 class="status-modal-message" style="margin-bottom:18px;font-size:1.3rem;color:#3182ce;">${message}</h3>
                    <button id="closeStatusModal" style="margin-top:12px;padding:8px 24px;background:#3182ce;color:#fff;border:none;border-radius:6px;font-size:1rem;cursor:pointer;">OK</button>
                </div>
            </div>
        `);
        $(document).on('click', '#closeStatusModal', function() {
            $('#statusModal').remove();
        });
    } else {
        $('.status-modal-message').text(message);
        $('#statusModal').show();
    }
}
    });
});
});
});
// Evaluation Inner Tab Switching Logic
$(document).ready(function() {
    // Only show All Evaluation Questions tab content by default
    $('#evalQuestionsTabBtn').addClass('active');
    $('#evalQuestionsTabContent').addClass('active');
    $('#rateTabBtn').removeClass('active');
    $('#rateTabContent').removeClass('active');
    $('#reviewTabBtn').removeClass('active');
    $('#reviewTabContent').removeClass('active');

    // Tab click event for evaluation inner tabs
    $('#evalQuestionsTabBtn').click(function() {
        $(this).addClass('active');
        $('#rateTabBtn').removeClass('active');
        $('#reviewTabBtn').removeClass('active');
        $('#evalQuestionsTabContent').addClass('active');
        $('#rateTabContent').removeClass('active');
        $('#reviewTabContent').removeClass('active');
    });
    $('#rateTabBtn').click(function() {
        $(this).addClass('active');
        $('#evalQuestionsTabBtn').removeClass('active');
        $('#reviewTabBtn').removeClass('active');
        $('#rateTabContent').addClass('active');
        $('#evalQuestionsTabContent').removeClass('active');
        $('#reviewTabContent').removeClass('active');
        // Load pre-assessment students when tab is clicked
        console.log('=== PRE-ASSESSMENT TAB CLICKED ===');
        loadPreassessmentStudentList();
    });
    $('#reviewTabBtn').click(function() {
        console.log('[DEBUG] === REVIEW TAB CLICKED ===');
        $(this).addClass('active');
        $('#evalQuestionsTabBtn').removeClass('active');
        $('#rateTabBtn').removeClass('active');
        $('#reviewTabContent').addClass('active');
        $('#evalQuestionsTabContent').removeClass('active');
        $('#rateTabContent').removeClass('active');
        
        console.log('[DEBUG] Review tab activated, allStudents length:', allStudents.length);
        
        // Check if Pre-Assessment data exists, if so reuse it
        if (allStudents.length > 0) {
            console.log('[DEBUG] Reusing existing Pre-Assessment data for Review tab');
            initializeReviewTab();
        } else {
            console.log('[DEBUG] Loading review students (fresh load)');
            loadReviewStudentList();
        }
    });

    // Load and separate students for Rate and Review tabs
    function loadRateAndReviewLists() {
        $.ajax({
            url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.answers) {
                    let students = {};
                    let reviewedStudents = {};
                    // Group answers by student and category
                    response.answers.forEach(function(ans) {
                        // Always use STUDENT_ID for grouping
                        const sid = ans.STUDENT_ID || ans.student_id;
                        if (!students[sid]) students[sid] = {};
                        if (!students[sid][ans.category]) students[sid][ans.category] = [];
                        students[sid][ans.category].push(ans);
                    });
                    // Get reviewed student IDs from coordinator_evaluation table
                    $.ajax({
                        url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
                        type: 'POST',
                        dataType: 'json',
                        data: JSON.stringify({ action: 'getReviewedStudents' }),
                        contentType: 'application/json',
                        success: function(reviewedResp) {
                            let reviewedIds = reviewedResp.reviewedIds || [];
                            let rateHtml = '';
                            let reviewHtml = '';
                            // Build a lookup for ratings: { student_evaluation_id: rating }
                            let ratingLookup = {};
                            if (Array.isArray(reviewedResp.ratings)) {
                                reviewedResp.ratings.forEach(function(r) {
                                    ratingLookup[r.student_evaluation_id] = r.rating;
                                });
                            }
                            Object.keys(students).forEach(function(studentId) {
                                // Normalize both IDs to string for comparison
                                const studentIdStr = String(studentId);
                                let firstAns = null;
                                Object.keys(students[studentId]).forEach(function(category) {
                                    if (!firstAns) firstAns = students[studentId][category][0];
                                });
                                let fullName = firstAns ? `${firstAns.SURNAME}, ${firstAns.NAME}` : '';
                                let studentGroupHtml = `<div class='student-review-group'><h3 class='student-review-title'>${fullName}</h3><div class='question-cards-row'>`;
                                // Combine Soft Skill and Comm Skill in one card
                                // Find all categories that match 'soft' or 'comm' (case-insensitive, partial match)
                                let combinedCategories = [];
                                Object.keys(students[studentId]).forEach(function(category) {
                                    let catLower = category.toLowerCase();
                                    if (catLower.includes('soft') || catLower.includes('comm')) {
                                        combinedCategories.push(category);
                                    }
                                });
                                // Determine if student is reviewed
                                const reviewedIdsStr = reviewedIds.map(String);
                                const isReviewed = reviewedIdsStr.includes(studentIdStr);
                                if (combinedCategories.length > 0) {
                                    studentGroupHtml += `<div class='student-category-group'>`;
                                    combinedCategories.forEach(function(category) {
                                        studentGroupHtml += `<h4 class='student-category-title'>${category}</h4>`;
                                        students[studentId][category].forEach(function(ans) {
                                            let actualRating = ratingLookup[ans.student_evaluation_id] || '';
                                            if (isReviewed) {
                                                // REVIEW TAB: show only rating stars
                                                studentGroupHtml += `<div class='student-eval-block' data-evalid='${ans.student_evaluation_id}'>
                                                    <div class='eval-question-box'>${ans.question_text}</div>
                                                    <table class='eval-table'>
                                                        <tr>
                                                            <td class='eval-answer-cell' rowspan='2'>${ans.answer}</td>
                                                            <td class='eval-rating-header' colspan='5' style='text-align:center;'>Table Rating</td>
                                                        </tr>
                                                        <tr>`;
                                                for (let i = 5; i >= 1; i--) {
                                                    studentGroupHtml += `<td class='eval-rating-cell'><span>${i}<br><span class='reviewed-rating' style='color:${actualRating == i ? "#3182ce" : "#a0aec0"};font-size:1.3em;'>${actualRating == i ? '&#9733;' : '&#9734;'}</span></span></td>`;
                                                }
                                                studentGroupHtml += `</tr>
                                                    </table>
                                                </div>`;
                                            } else {
                                                // RATE TAB: show radio buttons and Save button
                                                studentGroupHtml += `<div class='student-eval-block' data-evalid='${ans.student_evaluation_id}'>
                                                    <div class='eval-question-box'>${ans.question_text}</div>
                                                    <table class='eval-table'>
                                                        <tr>
                                                            <td class='eval-answer-cell' rowspan='2'>${ans.answer}</td>
                                                            <td class='eval-rating-header' colspan='5' style='text-align:center;'>Table Rating</td>
                                                        </tr>
                                                        <tr>
                                                            <td class='eval-rating-cell'><label>5<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='5'></label></td>
                                                            <td class='eval-rating-cell'><label>4<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='4'></label></td>
                                                            <td class='eval-rating-cell'><label>3<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='3'></label></td>
                                                            <td class='eval-rating-cell'><label>2<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='2'></label></td>
                                                            <td class='eval-rating-cell'><label>1<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='1'></label></td>
                                                        </tr>
                                                    </table>
                                                    <div style='text-align:right;'><button type='button' class='btn-clear-table' data-evalid='${ans.student_evaluation_id}' data-studentid='${studentId}' style='background:none;border:none;color:#2d3748;text-decoration:underline;cursor:pointer;padding:0;margin-top:8px;'>Clear Table</button></div>
                                                    <span class='rate-status' id='rateStatus_${ans.student_evaluation_id}'></span>
                                                </div>`;
                                            }
                                        });
                                    });
                                    studentGroupHtml += `</div>`;
                                }
                                // Render other categories as separate cards
                                Object.keys(students[studentId]).forEach(function(category) {
                                    if (combinedCategories.includes(category)) return;
                                    studentGroupHtml += `<div class='student-category-group'><h4 class='student-category-title'>${category}</h4>`;
                                    students[studentId][category].forEach(function(ans) {
                                        let actualRating = ratingLookup[ans.student_evaluation_id] || '';
                                        if (isReviewed) {
                                            // REVIEW TAB: show only rating stars
                                            studentGroupHtml += `<div class='student-eval-block' data-evalid='${ans.student_evaluation_id}'>
                                                <div class='eval-question-box'>${ans.question_text}</div>
                                                <table class='eval-table'>
                                                    <tr>
                                                        <td class='eval-answer-cell' rowspan='2'>${ans.answer}</td>
                                                        <td class='eval-rating-header' colspan='5' style='text-align:center;'>Table Rating</td>
                                                    </tr>
                                                    <tr>`;
                                            for (let i = 5; i >= 1; i--) {
                                                studentGroupHtml += `<td class='eval-rating-cell'><span>${i}<br><span class='reviewed-rating' style='color:${actualRating == i ? "#3182ce" : "#a0aec0"};font-size:1.3em;'>${actualRating == i ? '&#9733;' : '&#9734;'}</span></span></td>`;
                                            }
                                            studentGroupHtml += `</tr>
                                                </table>
                                            </div>`;
                                        } else {
                                            // RATE TAB: show radio buttons and Save button
                                            studentGroupHtml += `<div class='student-eval-block' data-evalid='${ans.student_evaluation_id}'>
                                                <div class='eval-question-box'>${ans.question_text}</div>
                                                <table class='eval-table'>
                                                    <tr>
                                                        <td class='eval-answer-cell' rowspan='2'>${ans.answer}</td>
                                                        <td class='eval-rating-header' colspan='5' style='text-align:center;'>Table Rating</td>
                                                    </tr>
                                                    <tr>
                                                        <td class='eval-rating-cell'><label>5<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='5'></label></td>
                                                        <td class='eval-rating-cell'><label>4<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='4'></label></td>
                                                        <td class='eval-rating-cell'><label>3<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='3'></label></td>
                                                        <td class='eval-rating-cell'><label>2<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='2'></label></td>
                                                        <td class='eval-rating-cell'><label>1<br><input type='radio' name='likert_${studentId}_${ans.student_evaluation_id}' value='1'></label></td>
                                                    </tr>
                                                </table>
                                                <div style='text-align:right;'><button type='button' class='btn-clear-table' data-evalid='${ans.student_evaluation_id}' data-studentid='${studentId}' style='background:none;border:none;color:#2d3748;text-decoration:underline;cursor:pointer;padding:0;margin-top:8px;'>Clear Table</button></div>
                                                <span class='rate-status' id='rateStatus_${ans.student_evaluation_id}'></span>
                                            </div>`;
                                        }
                                    });
                                    studentGroupHtml += `</div>`;
                                });
                                studentGroupHtml += `</div>`; // close question-cards-row
                                // Only show Save button for unrated students (use STUDENT_ID)
                                if (!isReviewed) {
                                    studentGroupHtml += `<div style='text-align:right; margin-top:18px;'><button class='btn-save-all-ratings' data-studentid='${studentId}' ${reviewedIds.includes(String(studentId)) ? 'disabled' : ''}>Save All Ratings</button></div>`;
                                }
                                studentGroupHtml += `</div>`; // close student-review-group
                                if (isReviewed) {
                                    reviewHtml += studentGroupHtml;
                                } else {
                                    rateHtml += studentGroupHtml;
                                }
                            });
                            $('#rateEvalList').html(rateHtml);
                            // Always append the no-match-message after rendering
                            if ($('#rateEvalList .no-match-message').length === 0) {
                                $('#rateEvalList').append('<div class="no-match-message" style="display:none; text-align:center; color:#a0aec0; font-size:1.2em; margin-top:2em;">No record for this student</div>');
                            }
                            $('#reviewedEvalList').html(reviewHtml || '<p>No reviewed student evaluations found.</p>');
                        }
                    });
                } else {
                    $('#rateEvalList').html('<p>No student answers found for rating.</p>');
                    $('#reviewedEvalList').html('<p>No reviewed student evaluations found.</p>');
                }
            },
            error: function() {
                $('#rateEvalList').html('<p>Error loading student answers for rating.</p>');
                $('#reviewedEvalList').html('<p>Error loading reviewed student evaluations.</p>');
            }
        });
    }

    // Initial load for Rate and Review tabs
    // --- Pre-Assessment Tab: Left-Right UI Logic ---
    let allStudents = [];
    let allReviewStudents = [];
    let selectedStudentId = null;
    let selectedReviewStudentId = null;
    let currentPage = 1;
    let currentReviewPage = 1;
    let studentsPerPage = 5;
    let reviewStudentsPerPage = 5;
    let totalStudents = 0;
    let totalReviewStudents = 0;

    function loadPreassessmentStudentList() {
        console.log('=== LOADING PRE-ASSESSMENT STUDENTS ===');
        
        // Get coordinator ID (same as Control tab)
        let cdrid = $("#hiddencdrid").val();
        
        if (!cdrid) {
            console.error('No coordinator ID found');
            renderEmptyPreAssessmentState('No coordinator ID found.');
            return;
        }
        
        console.log('Making AJAX request with cdrid:', cdrid);
        // Ensure loading state visible (in case of manual refresh)
        if (typeof showPreAssessmentLoading === 'function') {
            showPreAssessmentLoading();
        }
        // Fallback timeout to avoid endless spinner
        const preAssessTimeout = setTimeout(function() {
            if ($('#preAssessmentLoadingState').is(':visible')) {
                console.warn('[Pre-Assessment] Timeout exceeded, showing error state');
                if (typeof showPreAssessmentError === 'function') {
                    showPreAssessmentError();
                }
            }
        }, 10000); // 10 seconds
        // Fetch all students eligible for rating (AJAX or from global)
        $.ajax({
            url: 'ajaxhandler/studentDashboardAjax.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'getStudentsForPreassessment', cdrid: cdrid },
            success: function(response) {
                clearTimeout(preAssessTimeout);
                if (response.success && Array.isArray(response.students)) {
                    allStudents = response.students;
                    console.log('=== STUDENT DATA DEBUG ===');
                    console.log('Total students:', allStudents.length);
                    console.log('First student structure:', allStudents[0]);
                    console.log('Sample student data:');
                    allStudents.slice(0, 3).forEach((student, index) => {
                        console.log(`Student ${index + 1}:`, {
                            id: student.id,
                            STUDENT_ID: student.STUDENT_ID,
                            name: student.name
                        });
                    });
                    
                    // Apply session filtering logic (same as View All Students)
                    // Hide loading, show content area before rendering list
                    if (typeof showPreAssessmentContent === 'function') {
                        showPreAssessmentContent();
                    } else {
                        console.warn('[Pre-Assessment] showPreAssessmentContent undefined at success (will attempt global).');
                        if (typeof window.showPreAssessmentContent === 'function') {
                            window.showPreAssessmentContent();
                        }
                    }
                    displayPreAssessmentStudents(allStudents);
                } else {
                    if (typeof showPreAssessmentContent === 'function') {
                        showPreAssessmentContent();
                    } else if (typeof window.showPreAssessmentContent === 'function') {
                        window.showPreAssessmentContent();
                    }
                    renderEmptyPreAssessmentState('No students found.');
                }
            },
            error: function() {
                clearTimeout(preAssessTimeout);
                if (typeof showPreAssessmentError === 'function') {
                    showPreAssessmentError();
                } else {
                    if (typeof showPreAssessmentContent === 'function') {
                        showPreAssessmentContent();
                    } else if (typeof window.showPreAssessmentContent === 'function') {
                        window.showPreAssessmentContent();
                    }
                }
                renderEmptyPreAssessmentState('Error loading students.');
            }
        });
    }

    // Function to display pre-assessment students with session filtering (same logic as View All Students)
    function displayPreAssessmentStudents(students) {
        console.log('displayPreAssessmentStudents called with data:', students);
        
        // Debug: Check what session data we have
        console.log('Sample student data:', students.slice(0, 3));
        console.log('All SESSION_NAME values:', students.map(s => s.SESSION_NAME));
        console.log('Students with SESSION_NAME:', students.filter(s => s.SESSION_NAME));
        console.log('Students without SESSION_NAME:', students.filter(s => !s.SESSION_NAME));
        
        // Get unique sessions and sort (most recent first) - same logic as View All Students  
        const sessions = [...new Set((students || []).map(s => s.SESSION_NAME).filter(Boolean))];
        sessions.sort((a, b) => b.localeCompare(a));
        
        console.log('Extracted sessions for Pre-Assessment:', sessions);
        console.log('Number of unique sessions:', sessions.length);
        console.log('Sessions array details:', sessions);
        
        // Filter students by latest session first, then render
        let initialStudents = students;
        if (sessions && sessions.length > 0) {
            const latestSession = sessions[0];
            initialStudents = students.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === latestSession;
            });
            console.log('Filtered students for latest session:', latestSession, '- Count:', initialStudents.length);
        }
        
        // Initial render with filtered students and populate dropdown
        renderStudentList(initialStudents, sessions);
        
        // Session filter change handler (same logic as View All Students)
        $('#preAssessmentSessionFilter').off('change').on('change', function() {
            console.log('[DEBUG] Session filter changed to:', $(this).val());
            applyPreAssessmentFilters(allStudents);
        });
        
        // Also update search handler to work with session filter
        $('#rateStudentSearch').off('input').on('input', function() {
            console.log('[DEBUG] Search input changed to:', $(this).val());
            applyPreAssessmentFilters(allStudents);
        });
        
        // Pagination click handler using event delegation
        $(document).off('click', '.preassessment-page-btn').on('click', '.preassessment-page-btn', function(e) {
            e.preventDefault();
            console.log('Pagination button clicked!');
            const page = parseInt($(this).data('page'));
            console.log('Target page:', page);
            console.log('Button disabled:', $(this).prop('disabled'));
            if (!$(this).prop('disabled') && page > 0) {
                console.log('Calling changePreAssessmentPage with page:', page);
                changePreAssessmentPage(page);
            } else {
                console.log('Button is disabled or invalid page');
            }
        });
    }
    
    // Apply both session and search filters for Pre-Assessment 
    function applyPreAssessmentFilters(allStudentsData) {
        const selectedSession = $('#preAssessmentSessionFilter').val();
        const searchQuery = $('#rateStudentSearch').val().trim();
        
        let filtered = allStudentsData;
        
        // Apply session filter first
        if (selectedSession !== 'all') {
            filtered = filtered.filter(s => s.SESSION_NAME === selectedSession);
        }
        
        // Apply search filter
        if (searchQuery.length > 0) {
            // Remove any previous invalid message
            $('#studentSearchInvalidMsg').remove();
            if (/[^0-9]/.test(searchQuery)) {
                // Show invalid input message below the search bar
                $('#rateStudentSearch').after('<div id="studentSearchInvalidMsg" class="text-red-600 text-sm mt-1">Invalid input: Only numbers allowed</div>');
                return;
            }
            filtered = filtered.filter(s => {
                let idStr = (s.STUDENT_ID || s.student_id || '').toString();
                return idStr.includes(searchQuery);
            });
        }
        
        // Update student list without rebuilding dropdown
        updateStudentListDisplay(filtered);
        
        // Restore search input focus if it was focused
        const $input = $('#rateStudentSearch');
        $input.val(searchQuery);
        if (document.activeElement === $input[0]) {
            $input.focus();
        }
    }

    function renderStudentList(students, sessions = []) {
        let sorted = students.slice().sort((a, b) => {
            let aId = (a.STUDENT_ID || a.student_id || '').toString();
            let bId = (b.STUDENT_ID || b.student_id || '').toString();
            return aId.localeCompare(bId);
        });
        
        totalStudents = sorted.length;
        const totalPages = Math.ceil(totalStudents / studentsPerPage);
        
        // Get students for current page
        const startIndex = (currentPage - 1) * studentsPerPage;
        const endIndex = startIndex + studentsPerPage;
        const paginatedStudents = sorted.slice(startIndex, endIndex);
        
        let studentListHtml = '';
        paginatedStudents.forEach(function(student) {
            let displayId = student.STUDENT_ID || student.student_id || 'Unknown ID';
            console.log('[DEBUG] Rendering student:', { 
                id: student.id, 
                STUDENT_ID: student.STUDENT_ID, 
                displayId: displayId,
                dataStudentId: student.id // This will be in the HTML
            });
            studentListHtml += `
                <div class="preassessment-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${student.id === selectedStudentId ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${student.id}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                    </span>
                    <span class="truncate">${displayId}</span>
                </div>
            `;
        });
        
        // Generate pagination controls
        let paginationHtml = '';
        if (totalPages > 1) {
            paginationHtml += `<div class='flex flex-col items-center mt-4 px-2'>`;
            paginationHtml += `<div class='text-sm text-gray-600 mb-2'>Page ${currentPage} of ${totalPages}</div>`;
            paginationHtml += `<div class='flex gap-1'>`;
            
            // First page button
            const firstDisabled = currentPage <= 1;
            paginationHtml += `<button class='preassessment-page-btn px-3 py-2 text-sm border rounded ${firstDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='1' ${firstDisabled ? 'disabled' : ''}>&lt;&lt;</button>`;
            
            // Previous button
            const prevDisabled = currentPage <= 1;
            paginationHtml += `<button class='preassessment-page-btn px-3 py-2 text-sm border rounded ${prevDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentPage - 1}' ${prevDisabled ? 'disabled' : ''}>&lt;</button>`;
            
            // Next button
            const nextDisabled = currentPage >= totalPages;
            paginationHtml += `<button class='preassessment-page-btn px-3 py-2 text-sm border rounded ${nextDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentPage + 1}' ${nextDisabled ? 'disabled' : ''}>&gt;</button>`;
            
            // Last page button
            const lastDisabled = currentPage >= totalPages;
            paginationHtml += `<button class='preassessment-page-btn px-3 py-2 text-sm border rounded ${lastDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${totalPages}' ${lastDisabled ? 'disabled' : ''}>&gt;&gt;</button>`;
            
            paginationHtml += `</div>`;
            paginationHtml += `</div>`;
        }
        // Build the 20/80 column layout
        // On mobile, this will stack vertically with student list on top
        let html = `<div class='preassessment-main-wrapper flex flex-col md:flex-row w-full'>`;
        html += `<div class='preassessment-student-list-section left-col w-full md:w-1/5 max-w-xs md:pr-4 order-1 mb-4 md:mb-0'>`;
    html += `<div class='mb-4'><input type='text' id='rateStudentSearch' placeholder='Search student' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'></div>`;
    html += `<div class='mb-4'><select id='preAssessmentSessionFilter' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'><option value='all'>All Terms</option></select></div>`;
    html += `<div id='studentListPanel' class='overflow-y-auto min-h-[400px] max-h-[500px] flex flex-col gap-1'>${studentListHtml}</div>`;
    html += `<div id='paginationContainer'>${paginationHtml}</div>`;
        html += `</div>`;
        html += `<div class='preassessment-content-section right-col w-full md:w-4/5 md:pl-4 order-2'>`;
        if (!selectedStudentId) {
            html += `
            <div class="flex flex-col items-center justify-center h-full">
                <div class="bg-blue-50 rounded-full p-6 mb-4">
                    <svg xmlns='http://www.w3.org/2000/svg' class='h-12 w-12 text-blue-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                </div>
                <div class="text-xl font-semibold text-blue-700 mb-2">No student selected</div>
                <div class="text-gray-500 text-base">Select a student from the list to view their pre-assessment details.</div>
            </div>
            `;
        } else {
            html += `<div id='rateEvalList' class='space-y-4'></div>`;
        }
        html += `</div>`;
        html += `</div>`;
        $('#rateTabContent').html(html);
        
        // Populate session dropdown immediately after HTML insertion
        if (sessions && sessions.length > 0) {
            let sessionOptions = '<option value="all">All Terms</option>';
            sessions.forEach(session => { 
                sessionOptions += `<option value='${session}'>${session}</option>`; 
            });
            
            console.log('Session options HTML:', sessionOptions);
            console.log('Dropdown element exists immediately:', $('#preAssessmentSessionFilter').length > 0);
            
            // Populate dropdown
            $('#preAssessmentSessionFilter').html(sessionOptions);
            
            // Auto-select most recent session (same logic as View All Students)
            console.log('Auto-selecting session:', sessions[0]);
            $('#preAssessmentSessionFilter').val(sessions[0]).trigger('change');
        }
        
        // Directly bind click events to pagination buttons after HTML is inserted
        $('.preassessment-page-btn').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('Direct click handler from renderStudentList triggered!');
            const page = parseInt($(this).data('page'));
            console.log('Page from renderStudentList direct handler:', page);
            if (!$(this).prop('disabled') && page > 0) {
                changePreAssessmentPage(page);
            }
        });
    }
    
    // Update only the student list display without rebuilding dropdown or right panel
    function updateStudentListDisplay(students, resetPage = true) {
        let sorted = students.slice().sort((a, b) => {
            let aId = (a.STUDENT_ID || a.student_id || '').toString();
            let bId = (b.STUDENT_ID || b.student_id || '').toString();
            return aId.localeCompare(bId);
        });
        
        totalStudents = sorted.length;
        const totalPages = Math.ceil(totalStudents / studentsPerPage);
        
        // Reset to page 1 only when filtering, not when changing pages
        if (resetPage) {
            currentPage = 1;
        }
        
        // Get students for current page
        const startIndex = (currentPage - 1) * studentsPerPage;
        const endIndex = startIndex + studentsPerPage;
        const paginatedStudents = sorted.slice(startIndex, endIndex);
        
        let studentListHtml = '';
        paginatedStudents.forEach(function(student) {
            let displayId = student.STUDENT_ID || student.student_id || 'Unknown ID';
            studentListHtml += `
                <div class="preassessment-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${student.id === selectedStudentId ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${student.id}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 616 0z' /></svg>
                    </span>
                    <span class="truncate">${displayId}</span>
                </div>
            `;
        });
        
        // Generate pagination controls
        let paginationHtml = '';
        if (totalPages > 1) {
            paginationHtml += `<div class='flex flex-col items-center mt-4 px-2'>`;
            paginationHtml += `<div class='text-sm text-gray-600 mb-2'>Page ${currentPage} of ${totalPages}</div>`;
            paginationHtml += `<div class='flex gap-1'>`;
            
            // First page button
            const firstDisabled = currentPage <= 1;
            paginationHtml += `<button class='preassessment-page-btn px-3 py-2 text-sm border rounded ${firstDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='1' ${firstDisabled ? 'disabled' : ''}>&lt;&lt;</button>`;
            
            // Previous button
            const prevDisabled = currentPage <= 1;
            paginationHtml += `<button class='preassessment-page-btn px-3 py-2 text-sm border rounded ${prevDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentPage - 1}' ${prevDisabled ? 'disabled' : ''}>&lt;</button>`;
            
            // Next button
            const nextDisabled = currentPage >= totalPages;
            paginationHtml += `<button class='preassessment-page-btn px-3 py-2 text-sm border rounded ${nextDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentPage + 1}' ${nextDisabled ? 'disabled' : ''}>&gt;</button>`;
            
            // Last page button
            const lastDisabled = currentPage >= totalPages;
            paginationHtml += `<button class='preassessment-page-btn px-3 py-2 text-sm border rounded ${lastDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${totalPages}' ${lastDisabled ? 'disabled' : ''}>&gt;&gt;</button>`;
            
            paginationHtml += `</div>`;
            paginationHtml += `</div>`;
        }
        
        // Update student list and pagination containers
        $('#studentListPanel').html(studentListHtml);
        $('#paginationContainer').html(paginationHtml);
        
        // Debug: Check if pagination buttons were created
        console.log('Pagination HTML generated:', paginationHtml);
        console.log('Pagination buttons found after update:', $('.preassessment-page-btn').length);
        
        // Directly bind click events to pagination buttons after they're created
        $('.preassessment-page-btn').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('Direct click handler triggered!');
            const page = parseInt($(this).data('page'));
            console.log('Page from direct handler:', page);
            if (!$(this).prop('disabled') && page > 0) {
                changePreAssessmentPage(page);
            }
        });
    }
    
    // Function to change page in Pre-Assessment student list
    function changePreAssessmentPage(page) {
        console.log('changePreAssessmentPage called with page:', page);
        console.log('Current page before change:', currentPage);
        currentPage = page;
        console.log('Current page after change:', currentPage);
        
        // Get current filtered students
        const selectedSession = $('#preAssessmentSessionFilter').val();
        const searchQuery = $('#rateStudentSearch').val().trim();
        console.log('Current session filter:', selectedSession);
        console.log('Current search query:', searchQuery);
        
        let filteredStudents = allStudents.slice();
        
        // Apply session filter
        if (selectedSession && selectedSession !== 'all') {
            filteredStudents = filteredStudents.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === selectedSession;
            });
        }
        
        // Apply search filter
        if (searchQuery.length > 0) {
            filteredStudents = filteredStudents.filter(s => {
                let idStr = (s.STUDENT_ID || s.student_id || '').toString();
                return idStr.includes(searchQuery);
            });
        }
        
        // Update display with current page (don't reset page number)
        updateStudentListDisplay(filteredStudents, false);
    }
    
    // Make the function globally accessible
    window.changePreAssessmentPage = changePreAssessmentPage;

    // Unified search handler: use session-aware filtering
    // Remove any previous handlers to avoid duplicates
    $(document).off('input', '#rateStudentSearch').on('input', '#rateStudentSearch', function() {
        // Delegate to the centralized filter function which applies session + search
        applyPreAssessmentFilters(allStudents);
    });

    // Handle student selection - Load both grades and evaluation data
    $(document).on('click', '.preassessment-student-item', function() {
    console.log('[DEBUG] Pre-assessment student clicked');
    selectedStudentId = $(this).data('studentid');
    
    // Map INTERNS_ID to STUDENT_ID with debug
    let selectedStudent = allStudents.find(s => s.id == selectedStudentId);
    let studentDbId = selectedStudent && selectedStudent.STUDENT_ID ? selectedStudent.STUDENT_ID : selectedStudentId;
    console.log('[Pre-Assessment] Selected INTERNS_ID:', selectedStudentId, '| Mapped STUDENT_ID:', studentDbId, '| Student object:', selectedStudent);
    
    // Just update visual selection without rebuilding HTML
    $('.preassessment-student-item').removeClass('bg-blue-100 border-blue-400 font-semibold text-blue-700').addClass('text-gray-800');
    $(this).removeClass('text-gray-800').addClass('bg-blue-100 border-blue-400 font-semibold text-blue-700');
    
    // Make sure right panel exists, if not create it
    if ($('#rateEvalList').length === 0) {
        // Only rebuild HTML if the right panel doesn't exist
        console.log('[DEBUG] Right panel missing, rebuilding HTML structure');
        
        // Get current state before rebuilding
        let currentSession = $('#preAssessmentSessionFilter').val();
        let currentSearch = $('#rateStudentSearch').val();
        let currentPageNum = currentPage;
        
        // Get currently filtered students (same logic as applyPreAssessmentFilters)
        let currentStudents = allStudents.slice();
        if (currentSession && currentSession !== 'all') {
            currentStudents = currentStudents.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === currentSession;
            });
        }
        if (currentSearch && currentSearch.trim().length > 0) {
            currentStudents = currentStudents.filter(s => {
                let idStr = (s.STUDENT_ID || s.student_id || '').toString();
                return idStr.includes(currentSearch.trim());
            });
        }
        
        // Get unique sessions for dropdown
        const sessions = [...new Set(allStudents.map(s => s.SESSION_NAME).filter(Boolean))];
        sessions.sort((a, b) => b.localeCompare(a));
        
        // Rebuild with current filtered students and proper sessions
        renderStudentList(currentStudents, sessions);
        
        // Restore all state
        $('#preAssessmentSessionFilter').val(currentSession);
        $('#rateStudentSearch').val(currentSearch);
        currentPage = currentPageNum; // Restore page number
        
        // Rebind event handlers after HTML rebuild
        $('#preAssessmentSessionFilter').off('change').on('change', function() {
            console.log('[DEBUG] Session filter changed (rebound):', $(this).val());
            applyPreAssessmentFilters(allStudents);
        });
        
        $('#rateStudentSearch').off('input').on('input', function() {
            console.log('[DEBUG] Search input changed (rebound):', $(this).val());
            applyPreAssessmentFilters(allStudents);
        });
        
        console.log('[DEBUG] Restored state - Session:', currentSession, 'Search:', currentSearch, 'Page:', currentPageNum);
    }
    
    // Show loading in right panel
    $('#rateEvalList').html('<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div><div class="mt-2 text-gray-600">Loading student data...</div></div>');
    
    // Load both grades and evaluation data simultaneously
    loadStudentGradesAndEvaluation(studentDbId, selectedStudentId);
    });

    // Function to update student selection highlighting without rebuilding layout
    function updateStudentSelectionHighlight() {
        console.log('[DEBUG] Updating student selection highlight for:', selectedStudentId);
        // Remove highlight from all students
        $('.preassessment-student-item').removeClass('bg-blue-100 border-blue-400 font-semibold text-blue-700').addClass('text-gray-800');
        
        // Add highlight to selected student
        if (selectedStudentId) {
            let targetElement = $(`.preassessment-student-item[data-studentid="${selectedStudentId}"]`);
            console.log('[DEBUG] Target element for highlighting:', targetElement.length);
            targetElement.removeClass('text-gray-800').addClass('bg-blue-100 border-blue-400 font-semibold text-blue-700');
        }
    }



    // Combined function to load both grades and evaluation data
    function loadStudentGradesAndEvaluation(studentDbId, internsId) {
        // Check cache using your existing system
        const cacheKey = `evaluation_${studentDbId}_${internsId}`;
        
        if (isCacheValid('evaluation', cacheKey) && dataCache.evaluationData[cacheKey]) {
            console.log('[DEBUG] ⚡ Using cached data for student:', studentDbId);
            const cached = dataCache.evaluationData[cacheKey];
            
            // Render cached data instantly
            renderEvaluationData(cached.gradesData, cached.evaluationData, cached.studentDbId);
            return;
        }
        
        console.log('[DEBUG] 🔄 Fetching fresh data for student:', studentDbId);
        let gradesData = null;
        let evaluationData = null;
        let completedRequests = 0;
        
        // Function to render combined data when both requests complete
        function renderCombinedData() {
            if (completedRequests < 2) return; // Wait for both requests
            
            console.log('[DEBUG] All data loaded for student:', studentDbId);
            console.log('[DEBUG] Grades data:', gradesData);
            console.log('[DEBUG] Evaluation data:', evaluationData);
            
            // Cache the data using your existing system
            const cacheKey = `evaluation_${studentDbId}_${internsId}`;
            dataCache.evaluationData[cacheKey] = {
                gradesData: gradesData,
                evaluationData: evaluationData,
                studentDbId: studentDbId,
                internsId: internsId
            };
            dataCache.lastUpdated.evaluationData[cacheKey] = Date.now();
            console.log('[DEBUG] 💾 Cached evaluation data for:', cacheKey);
            
            renderEvaluationData(gradesData, evaluationData, studentDbId);
        }
        
        // Common render function for both fresh and cached data
        function renderEvaluationData(gradesData, evaluationData, studentDbId) {
            // Create horizontal layout: Grades (20%) | Evaluation (80%)
            // On mobile, this will stack vertically with CSS
            let html = `<div class="preassessment-content-wrapper flex flex-col md:flex-row gap-4 h-full">`;
            
            // Left Column - Academic Grades (20% of right column on desktop, full width on mobile)
            html += `<div class="preassessment-academic-section w-full md:w-1/5 min-w-0 order-2 md:order-1">`;
            
            // Student ID Header
            html += `<div class="bg-blue-50 rounded-lg p-3 border border-blue-200 mb-4">
                <h2 class="text-lg font-bold text-blue-800 flex items-center">
                    <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 mr-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' /></svg>
                    ID: ${studentDbId}
                </h2>
            </div>`;
            
            // Academic Grades Section
            if (gradesData && gradesData.success && gradesData.grades) {
                const grades = gradesData.grades;
                const subjectKeys = [
                    'CC 102','CC 103','PF 101','CC 104','IPT 101','IPT 102','CC 106','CC 105',
                    'IM 101','IM 102','HCI 101','HCI 102','WS 101','NET 101','NET 102',
                    'IAS 101','IAS 102','CAP 101','CAP 102','SP 101'
                ];
                
                html += `<div class="bg-white rounded-xl shadow p-4 border border-gray-200">
                    <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1 text-green-600' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' /></svg>
                        Academic Grades
                    </h3>
                    <div class="max-h-96 overflow-y-auto space-y-2">`;
                
                subjectKeys.forEach(function(key) {
                    if (grades.hasOwnProperty(key)) {
                        html += `<div class="bg-gray-50 rounded p-2 text-center">
                            <div class="text-xs font-semibold text-gray-700">${key}</div>
                            <div class="text-sm font-bold text-blue-700">${grades[key]}</div>
                        </div>`;
                    }
                });
                
                html += `</div></div>`;
            } else {
                html += `<div class="bg-yellow-50 rounded-lg p-3 border border-yellow-200">
                    <p class="text-xs text-yellow-800">No academic grades found.</p>
                </div>`;
            }
            
            html += `</div>`; // Close left column
            
            // Right Column - Evaluation Questions (80% of right column on desktop, full width on mobile)
            html += `<div class="preassessment-evaluation-section w-full md:w-4/5 min-w-0 order-3 md:order-2">`;
            
            if (evaluationData && evaluationData.success) {
                if (evaluationData.isRated) {
                    html += `<div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <p class="text-green-800 font-semibold">✓ This student has been rated. Check the Review tab for details.</p>
                    </div>`;
                } else if (evaluationData.evaluations && evaluationData.evaluations.length > 0) {
                    // Group evaluations by category with improved filtering
                    const evaluationsByCategory = {
                        'soft': evaluationData.evaluations.filter(ev => ev.category && 
                            (ev.category.toLowerCase().includes('soft') || 
                             ev.category.toLowerCase().includes('personal') || 
                             ev.category.toLowerCase().includes('interpersonal'))),
                        'comm': evaluationData.evaluations.filter(ev => ev.category && ev.category.toLowerCase().includes('comm')),
                        'tech': evaluationData.evaluations.filter(ev => ev.category && ev.category.toLowerCase().includes('technical'))
                    };
                    
                    html += `<div class="bg-white rounded-xl shadow p-6 border border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 mr-2 text-purple-600' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' /></svg>
                            Pre-Assessment Evaluation (Rate Student Answers)
                        </h3>
                        <!-- Category Dropdown -->
                        <div class="category-filter-section mb-4 flex justify-center">
                            <div class="flex items-center gap-3 bg-gray-50 rounded-lg p-3 border">
                                <label for="mainDashboardCategoryDropdown" class="text-sm font-semibold text-gray-700 whitespace-nowrap">Filter by Category:</label>
                                <select id="mainDashboardCategoryDropdown" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                                    <option value="soft" selected>Soft Skills (${evaluationsByCategory.soft.length})</option>
                                    <option value="comm">Communication Skills (${evaluationsByCategory.comm.length})</option>
                                    <option value="tech">Technical Skills (${evaluationsByCategory.tech.length})</option>
                                </select>
                            </div>
                        </div>
                        <!-- Category Content Containers -->
                        <div id="softSkillsEvalContainer" class="category-eval-container max-h-96 overflow-y-auto space-y-4">`;
                    
                    // Render Soft Skills by default
                    evaluationsByCategory.soft.forEach(function(ev) {
                        html += `
                        <div class="student-eval-block bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-purple-700 uppercase tracking-wide mb-1">Question</div>
                                <div class="text-sm font-medium text-gray-900 bg-white rounded p-2 border">${ev.question_text}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Student Answer</div>
                                <div class="text-sm text-gray-800 bg-blue-50 rounded p-2 border border-blue-200">${ev.answer}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">Likert Scale Rating (5=Excellent, 1=Poor)</div>
                                <div class="flex items-center justify-center gap-2 bg-white rounded p-3 border">
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-green-600 mb-1">5</span>
                                        <span class="text-xs text-gray-600 mb-1">Excellent</span>
                                        <input type="radio" name="likert_${ev.id}" value="5" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-blue-600 mb-1">4</span>
                                        <span class="text-xs text-gray-600 mb-1">Good</span>
                                        <input type="radio" name="likert_${ev.id}" value="4" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-yellow-600 mb-1">3</span>
                                        <span class="text-xs text-gray-600 mb-1">Average</span>
                                        <input type="radio" name="likert_${ev.id}" value="3" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-orange-600 mb-1">2</span>
                                        <span class="text-xs text-gray-600 mb-1">Poor</span>
                                        <input type="radio" name="likert_${ev.id}" value="2" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-red-600 mb-1">1</span>
                                        <span class="text-xs text-gray-600 mb-1">Very Poor</span>
                                        <input type="radio" name="likert_${ev.id}" value="1" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    html += `</div>
                        <!-- Communication Skills Container (hidden initially) -->
                        <div id="commSkillsEvalContainer" class="category-eval-container max-h-96 overflow-y-auto space-y-4" style="display:none;">`;
                    
                    evaluationsByCategory.comm.forEach(function(ev) {
                        html += `
                        <div class="student-eval-block bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-purple-700 uppercase tracking-wide mb-1">Question</div>
                                <div class="text-sm font-medium text-gray-900 bg-white rounded p-2 border">${ev.question_text}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Student Answer</div>
                                <div class="text-sm text-gray-800 bg-blue-50 rounded p-2 border border-blue-200">${ev.answer}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">Likert Scale Rating (5=Excellent, 1=Poor)</div>
                                <div class="flex items-center justify-center gap-2 bg-white rounded p-3 border">
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-green-600 mb-1">5</span>
                                        <span class="text-xs text-gray-600 mb-1">Excellent</span>
                                        <input type="radio" name="likert_${ev.id}" value="5" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-blue-600 mb-1">4</span>
                                        <span class="text-xs text-gray-600 mb-1">Good</span>
                                        <input type="radio" name="likert_${ev.id}" value="4" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-yellow-600 mb-1">3</span>
                                        <span class="text-xs text-gray-600 mb-1">Average</span>
                                        <input type="radio" name="likert_${ev.id}" value="3" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-orange-600 mb-1">2</span>
                                        <span class="text-xs text-gray-600 mb-1">Poor</span>
                                        <input type="radio" name="likert_${ev.id}" value="2" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-red-600 mb-1">1</span>
                                        <span class="text-xs text-gray-600 mb-1">Very Poor</span>
                                        <input type="radio" name="likert_${ev.id}" value="1" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    html += `</div>
                        <!-- Technical Skills Container (hidden initially) -->
                        <div id="techSkillsEvalContainer" class="category-eval-container max-h-96 overflow-y-auto space-y-4" style="display:none;">`;
                    
                    evaluationsByCategory.tech.forEach(function(ev) {
                        html += `
                        <div class="student-eval-block bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-purple-700 uppercase tracking-wide mb-1">Question</div>
                                <div class="text-sm font-medium text-gray-900 bg-white rounded p-2 border">${ev.question_text}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Student Answer</div>
                                <div class="text-sm text-gray-800 bg-blue-50 rounded p-2 border border-blue-200">${ev.answer}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">Likert Scale Rating (5=Excellent, 1=Poor)</div>
                                <div class="flex items-center justify-center gap-2 bg-white rounded p-3 border">
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-green-600 mb-1">5</span>
                                        <span class="text-xs text-gray-600 mb-1">Excellent</span>
                                        <input type="radio" name="likert_${ev.id}" value="5" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-blue-600 mb-1">4</span>
                                        <span class="text-xs text-gray-600 mb-1">Good</span>
                                        <input type="radio" name="likert_${ev.id}" value="4" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-yellow-600 mb-1">3</span>
                                        <span class="text-xs text-gray-600 mb-1">Average</span>
                                        <input type="radio" name="likert_${ev.id}" value="3" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-orange-600 mb-1">2</span>
                                        <span class="text-xs text-gray-600 mb-1">Poor</span>
                                        <input type="radio" name="likert_${ev.id}" value="2" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <span class="text-xs font-semibold text-red-600 mb-1">1</span>
                                        <span class="text-xs text-gray-600 mb-1">Very Poor</span>
                                        <input type="radio" name="likert_${ev.id}" value="1" class="likert-radio" data-eval-id="${ev.id}">
                                    </label>
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    html += `</div>
                        <!-- Action Buttons -->
                        <div class="mt-6 flex justify-between items-center">
                            <button id="autoRateBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded text-sm font-medium flex items-center gap-2" data-student-id="${studentDbId}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                                Auto-Rate (AI + Grades)
                            </button>
                            <button id="saveAllRatingsBtn" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded text-sm font-medium" data-student-id="${studentDbId}">
                                Save All Ratings
                            </button>
                        </div>
                    </div>`;
                } else {
                    html += `<div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-gray-600">This student has not completed their pre-assessment yet.</p>
                    </div>`;
                }
            } else {
                html += `<div class="bg-red-50 rounded-lg p-4 border border-red-200">
                    <p class="text-red-800">Error loading evaluation data for this student.</p>
                </div>`;
            }
            
            html += `</div>`; // Close right column
            html += `</div>`; // Close main flex container
            $('#rateEvalList').html(html);
        }
        
        // Fetch grades data
        $.ajax({
            url: 'ajaxhandler/preAssessmentGradesAjax.php',
            type: 'POST',
            dataType: 'json',
            data: { student_id: studentDbId },
            success: function(response) {
                gradesData = response;
                completedRequests++;
                renderCombinedData();
            },
            error: function() {
                gradesData = { success: false };
                completedRequests++;
                renderCombinedData();
            }
        });
        
        // Fetch evaluation data
        $.ajax({
            url: 'ajaxhandler/studentDashboardAjax.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'getPreassessmentEvaluation', studentId: internsId },
            success: function(response) {
                evaluationData = response;
                completedRequests++;
                renderCombinedData();
            },
            error: function() {
                evaluationData = { success: false };
                completedRequests++;
                renderCombinedData();
            }
        });
    }

    // Auto-Rate button handler
    $(document).on('click', '#autoRateBtn', function() {
        const studentId = $(this).data('student-id');
        const $button = $(this);
        
        // Confirm with user
        if (!confirm('This will automatically rate all questions for this student based on their academic grades and AI analysis. This action cannot be undone. Continue?')) {
            return;
        }
        
        $button.prop('disabled', true).html('<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></span>AI Processing...');
        
        // Call the automated rating endpoint
        $.ajax({
            url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'autoRateStudent',
                studentId: studentId,
                coordinatorId: COORDINATOR_ID
            },
            success: function(response) {
                if (response.success) {
                    // Apply AI suggestions to the rating inputs
                    if (response.suggestions && response.suggestions.length > 0) {
                        console.log(`Applying ${response.suggestions.length} AI suggestions...`);
                        
                        // Apply suggestions immediately
                        response.suggestions.forEach(function(suggestion) {
                            const studentEvalId = suggestion.student_evaluation_id;
                            const suggestedRating = Math.round(suggestion.suggested_rating); // Round to nearest integer
                            
                            // Try multiple radio button selector patterns
                            let $radioButton = $();
                            
                            // Pattern 1: newer format with just evaluation ID
                            let radioSelector1 = `input[name='likert_${studentEvalId}'][value='${suggestedRating}']`;
                            $radioButton = $(radioSelector1);
                            
                            // Pattern 2: older format with student ID
                            if ($radioButton.length === 0) {
                                let radioSelector2 = `input[name='likert_${studentId}_${studentEvalId}'][value='${suggestedRating}']`;
                                $radioButton = $(radioSelector2);
                            }
                            
                            // Pattern 3: try with data attribute
                            if ($radioButton.length === 0) {
                                let radioSelector3 = `input[data-eval-id='${studentEvalId}'][value='${suggestedRating}']`;
                                $radioButton = $(radioSelector3);
                            }
                            
                            if ($radioButton.length > 0) {
                                $radioButton.prop('checked', true);
                                
                                // Add visual indicator that this is an AI suggestion
                                const $parentCell = $radioButton.closest('.eval-rating-cell');
                                $parentCell.addClass('ai-suggested').attr('title', 'AI Suggested Rating');
                                
                                console.log(`Applied AI suggestion: Question ${studentEvalId} = ${suggestedRating}/5 (original: ${suggestion.suggested_rating})`);
                            } else {
                                console.warn(`Could not find radio button for evaluation ID ${studentEvalId}, tried selectors:`, {
                                    pattern1: radioSelector1,
                                    pattern2: `input[name='likert_${studentId}_${studentEvalId}'][value='${suggestedRating}']`,
                                    pattern3: `input[data-eval-id='${studentEvalId}'][value='${suggestedRating}']`
                                });
                            }
                        });
                        
                        // Update button to show suggestions are loaded
                        $button.removeClass('bg-blue-600 hover:bg-blue-700')
                               .addClass('bg-green-600 hover:bg-green-700')
                               .html(`
                                   <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                   </svg>
                                   AI Suggestions Applied
                               `)
                               .prop('disabled', false);
                        
                        // Show analysis report
                        alert('🤖 AI Rating Suggestions Applied!\n\n' + response.analysisReport + '\n\n✏️ You can now edit any ratings and click "Save All Ratings" when ready.');
                        
                        // Enable and highlight the save button
                        const $saveButton = $('#saveAllRatingsBtn');
                        if ($saveButton.length > 0) {
                            $saveButton.removeClass('bg-gray-400')
                                      .addClass('bg-green-600 hover:bg-green-700')
                                      .prop('disabled', false)
                                      .html('💾 Save AI Suggestions');
                        }
                    }
                    
                } else {
                    alert('Error during AI rating: ' + (response.message || 'Unknown error occurred'));
                    $button.prop('disabled', false).html(`
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        Auto-Rate (AI + Grades)
                    `);
                }
            },
            error: function(xhr, status, error) {
                alert('Network error during automated rating: ' + error);
                $button.prop('disabled', false).html(`
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    Auto-Rate (AI + Grades)
                `);
            }
        });
    });

    // Detect manual changes to AI suggestions
    $(document).on('change', '.eval-rating-cell.ai-suggested input[type="radio"]', function() {
        const $cell = $(this).closest('.eval-rating-cell');
        $cell.addClass('manually-changed').attr('title', 'AI Suggestion Modified');
        console.log('AI suggestion manually modified');
    });

    // Debug helper function - can be called from console
    window.debugRadioButtons = function(studentId, evalId) {
        console.log('Available radio buttons for evaluation ID:', evalId);
        $(`input[name*="${evalId}"]`).each(function() {
            console.log('Found radio:', $(this).attr('name'), 'value:', $(this).val());
        });
        $(`input[data-eval-id="${evalId}"]`).each(function() {
            console.log('Found radio by data-attr:', $(this).attr('name'), 'value:', $(this).val());
        });
    };

    // Universal Save All Ratings button handler
    $(document).on('click', '#saveAllRatingsBtn', function() {
        const studentId = $(this).data('student-id');
        const $button = $(this);
        
        // Get all selected ratings from ALL categories, not just visible one
        const selectedRatings = [];
        let totalQuestions = 0;
        let hasUnrated = false;
        
        // Check all category containers (soft, comm, tech)
        $('.category-eval-container').each(function() {
            const $container = $(this);
            
            // Count total questions in this category
            const categoryQuestions = $container.find('input[type="radio"][value="5"]').length;
            totalQuestions += categoryQuestions;
            
            // Get selected ratings in this category
            let categoryRatings = 0;
            $container.find('.likert-radio:checked').each(function() {
                const evalId = $(this).data('eval-id');
                const rating = $(this).val();
                selectedRatings.push({
                    evaluationId: evalId,
                    rating: rating
                });
                categoryRatings++;
            });
            
            // Check if this category is complete
            if (categoryRatings < categoryQuestions && categoryQuestions > 0) {
                hasUnrated = true;
                console.log(`Category ${$container.attr('id')} incomplete: ${categoryRatings}/${categoryQuestions} rated`);
            }
        });
        
        // Ensure all categories are completed before saving
        if (hasUnrated || selectedRatings.length < totalQuestions) {
            alert(`Please rate all questions in ALL categories before saving. Currently rated: ${selectedRatings.length}/${totalQuestions} questions.\n\nMake sure to complete Soft Skills, Communication Skills, AND Technical Skills.`);
            return;
        }
        
        $button.prop('disabled', true).html('<span class="spinner">⏳</span> Saving All Categories...');
        
        // Save all ratings with multiple AJAX calls
        let completedSaves = 0;
        let totalSaves = selectedRatings.length;
        let hasError = false;
        
        // Prepare data for batch save
        const ratingsData = {
            coordinator_id: COORDINATOR_ID, // Get from global variable set by PHP session
            ratings: selectedRatings.map(function(rating) {
                return {
                    student_evaluation_id: rating.evaluationId,
                    student_id: studentId,
                    rating: rating.rating
                };
            })
        };
        
        // Single AJAX call to save all ratings
        $.ajax({
            url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
            type: 'POST',
            dataType: 'json',
            data: ratingsData,
            success: function(response) {
                if (response.success) {
                    $button.removeClass('bg-green-600 hover:bg-green-700 bg-yellow-600 hover:bg-yellow-700')
                           .addClass('bg-green-800 hover:bg-green-900')
                           .text('✓ All Categories Saved')
                           .prop('disabled', true);
                    
                    // Disable all radio buttons in ALL categories (not just visible one)
                    $('.category-eval-container').find('.likert-radio').prop('disabled', true);
                    
                    // Reset the AI suggestions button for potential future use
                    const $aiButton = $(`button[onclick*="autoRateStudent('${studentId}')"]`);
                    if ($aiButton.length > 0) {
                        $aiButton.removeClass('bg-green-600 hover:bg-green-700')
                               .addClass('bg-gray-400')
                               .html(`
                                   <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                   </svg>
                                   Ratings Saved
                               `)
                               .prop('disabled', true);
                    }
                    
                    // Refresh the reviewed students list and evaluation data so future selections show the correct message
                    $.ajax({
                        url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ action: 'getReviewedStudents' }),
                        success: function(reviewedResp) {
                            if (reviewedResp.success) {
                                // Update the global reviewed students cache if it exists
                                if (window.reviewedStudentIds) {
                                    window.reviewedStudentIds = reviewedResp.reviewedIds || [];
                                }
                                console.log('[REFRESH] Updated reviewed students after save:', reviewedResp.reviewedIds);
                                
                                // Also refresh evaluation data for current student to show "already rated" message
                                const currentStudentId = studentId; // studentId from the save operation
                                if (currentStudentId) {
                                    $.ajax({
                                        url: 'ajaxhandler/studentDashboardAjax.php',
                                        type: 'POST',
                                        dataType: 'json',
                                        data: { action: 'getPreassessmentEvaluation', studentId: currentStudentId },
                                        success: function(evalResp) {
                                            if (evalResp.success && evalResp.isRated) {
                                                // Trigger a refresh of the student view to show "already rated" message
                                                console.log('[REFRESH] Student is now marked as rated, triggering UI refresh');
                                                // You could trigger a student selection refresh here if needed
                                            }
                                        }
                                    });
                                }
                            }
                        }
                    });
                    
                    alert(`✅ SUCCESS: All ${selectedRatings.length} ratings saved successfully across ALL categories!\n\nPre-assessment averages updated for:\n• Soft Skills\n• Communication Skills\n• Technical Skills\n\n🎉 Evaluation complete!`);
                } else {
                    alert('❌ Error saving ratings: ' + (response.message || 'Unknown error'));
                    $button.removeClass('bg-green-800 hover:bg-green-900 bg-yellow-600 hover:bg-yellow-700')
                           .addClass('bg-green-600 hover:bg-green-700')
                           .prop('disabled', false).text('Save All Ratings');
                }
            },
            error: function() {
                alert('❌ Network error saving ratings. Please try again.');
                $button.removeClass('bg-green-800 hover:bg-green-900 bg-yellow-600 hover:bg-yellow-700')
                       .addClass('bg-green-600 hover:bg-green-700')
                       .prop('disabled', false).text('Save All Ratings');
            }
        });
    });

    // Category dropdown handler for main dashboard evaluation
    $(document).on('change', '#mainDashboardCategoryDropdown', function() {
        const selectedCategory = $(this).val();
        
        // Hide all category containers
        $('.category-eval-container').hide();
        
        // Show the selected category container
        const containerMap = {
            'soft': '#softSkillsEvalContainer',
            'comm': '#commSkillsEvalContainer', 
            'tech': '#techSkillsEvalContainer'
        };
        
        const targetContainer = containerMap[selectedCategory];
        if (targetContainer) {
            $(targetContainer).show();
            
            // Update the Save All Ratings button based on OVERALL progress (all categories)
            const $saveButton = $('#saveAllRatingsBtn');
            const visibleContainer = $(targetContainer);
            
            // Check if ALL categories have been saved (not just the current one)
            let allCategoriesSaved = true;
            let totalQuestions = 0;
            let totalRated = 0;
            let totalSaved = 0;
            
            $('.category-eval-container').each(function() {
                const $container = $(this);
                const categoryQuestions = $container.find('input[type="radio"][value="5"]').length;
                const categoryRated = $container.find('.likert-radio:checked').length;
                const categorySaved = $container.find('.likert-radio:disabled').length;
                
                totalQuestions += categoryQuestions;
                totalRated += categoryRated;
                totalSaved += categorySaved;
                
                if (categorySaved < categoryQuestions && categoryQuestions > 0) {
                    allCategoriesSaved = false;
                }
            });
            
            if (allCategoriesSaved && totalQuestions > 0) {
                // ALL categories have been saved
                $saveButton.removeClass('bg-green-600 hover:bg-green-700 bg-yellow-600 hover:bg-yellow-700')
                          .addClass('bg-green-800 hover:bg-green-900')
                          .text('✓ All Categories Saved')
                          .prop('disabled', true);
            } else if (totalRated === totalQuestions && totalQuestions > 0) {
                // All questions rated but not yet saved
                $saveButton.removeClass('bg-green-800 hover:bg-green-900 bg-yellow-600 hover:bg-yellow-700')
                          .addClass('bg-green-600 hover:bg-green-700')
                          .text('Save All Categories')
                          .prop('disabled', false);
            } else {
                // Still need to rate more questions
                $saveButton.removeClass('bg-green-600 hover:bg-green-700 bg-green-800 hover:bg-green-900')
                          .addClass('bg-yellow-600 hover:bg-yellow-700')
                          .text(`Rate All Categories (${totalRated}/${totalQuestions})`)
                          .prop('disabled', true);
            }
        } else {
            console.warn('[WARNING] No container found for category:', selectedCategory);
        }
    });

    function loadStudentEvaluation(studentId) {
        // This function is now replaced by loadStudentGradesAndEvaluation
        // Keeping it for compatibility but it won't be used
        console.log('[DEBUG] loadStudentEvaluation called but replaced by combined function');
    }

    // Initial load for Pre-Assessment tab is now handled by unified evaluation loading

    // --- Review Tab: Share data with Pre-Assessment tab ---
    // allReviewStudents and selectedReviewStudentId are already declared above

    function loadReviewStudentList() {
        console.log('[DEBUG] Loading Review tab with shared Pre-Assessment data');
        console.log('[DEBUG] allStudents available:', allStudents.length > 0);
        
        if (allStudents.length === 0) {
            // If Pre-Assessment data not loaded yet, load it first
            console.log('[DEBUG] Pre-Assessment data not available, loading it first...');
            
            // Get coordinator ID (same as Pre-Assessment)
            let cdrid = $("#hiddencdrid").val();
            
            if (!cdrid) {
                console.error('No coordinator ID found for Review tab');
                renderEmptyReviewState('No coordinator ID found.');
                return;
            }
            
            // Load Pre-Assessment data first
            $.ajax({
                url: 'ajaxhandler/studentDashboardAjax.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'getStudentsForPreassessment', cdrid: cdrid },
                success: function(response) {
                    if (response.success && Array.isArray(response.students)) {
                        allStudents = response.students;
                        console.log('[DEBUG] Pre-Assessment data loaded for Review:', allStudents.length, 'students');
                        initializeReviewTab();
                    } else {
                        renderEmptyReviewState('No students found.');
                    }
                },
                error: function() {
                    renderEmptyReviewState('Error loading students.');
                }
            });
        } else {
            // Pre-Assessment data already available, use it directly
            initializeReviewTab();
        }
    }
    
    function initializeReviewTab() {
        console.log('[DEBUG] Initializing Review tab...');
        
        // Use the same student data as Pre-Assessment but for review purposes
        if (typeof allStudents !== 'undefined' && allStudents.length > 0) {
            allReviewStudents = allStudents.slice(); // Copy the Pre-Assessment data
            console.log('[DEBUG] Review tab initialized with', allReviewStudents.length, 'students');
            console.log('[DEBUG] Sample review students with proper structure:', allReviewStudents.slice(0, 2));
            
            displayReviewStudents(allReviewStudents);
        } else {
            console.log('[DEBUG] No Pre-Assessment data available yet for Review tab');
            // Show empty state or load data first
            allReviewStudents = [];
            displayReviewStudents(allReviewStudents);
        }
    }
    
    // Display Review students with session filtering (same logic as Pre-Assessment)
    function displayReviewStudents(students) {
        console.log('[DEBUG] displayReviewStudents called with data:', students);
        
        // Debug: Check what session data we have
        console.log('[DEBUG] Sample student data:', students.slice(0, 3));
        console.log('[DEBUG] All SESSION_NAME values:', students.map(s => s.SESSION_NAME));
        
        // Get unique sessions and sort (most recent first) - same logic as Pre-Assessment  
        const sessions = [...new Set((students || []).map(s => s.SESSION_NAME).filter(Boolean))];
        sessions.sort((a, b) => b.localeCompare(a));
        
        console.log('[DEBUG] Extracted sessions for Review:', sessions);
        console.log('[DEBUG] Number of unique sessions:', sessions.length);
        
        // Filter students by latest session first, then render
        let initialStudents = students;
        if (sessions && sessions.length > 0) {
            const latestSession = sessions[0];
            initialStudents = students.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === latestSession;
            });
            console.log('[DEBUG] Filtered students for latest session in Review:', latestSession, '- Count:', initialStudents.length);
        }
        
        // Initial render with filtered students and populate dropdown
        console.log('[DEBUG] Initial render with', initialStudents.length, 'students');
        console.log('[DEBUG] Sample initial student:', initialStudents[0]);
        renderReviewStudentList(initialStudents, sessions);
        
        // Session filter change handler
        $('#reviewSessionFilter').off('change').on('change', function() {
            console.log('[DEBUG] Review session filter changed to:', $(this).val());
            applyReviewFilters(allReviewStudents);
        });
        
        // Search handler
        $('#reviewStudentSearch').off('input').on('input', function() {
            console.log('[DEBUG] Review search input changed to:', $(this).val());
            applyReviewFilters(allReviewStudents);
        });
        
        // Pagination click handler using event delegation
        $(document).off('click', '.review-page-btn').on('click', '.review-page-btn', function(e) {
            e.preventDefault();
            console.log('[DEBUG] Review pagination button clicked!');
            const page = parseInt($(this).data('page'));
            if (!$(this).prop('disabled') && page > 0) {
                changeReviewPage(page);
            }
        });
    }
    
    // Apply filters for Review tab (session filter + search)
    function applyReviewFilters(allStudents) {
        console.log('[DEBUG] applyReviewFilters called with', allStudents.length, 'students');
        
        let filtered = allStudents;
        
        // Session filter
        const selectedSession = $('#reviewSessionFilter').val();
        console.log('[DEBUG] Selected session:', selectedSession);
        
        if (selectedSession && selectedSession !== '' && selectedSession !== 'All Sessions') {
            filtered = filtered.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === selectedSession;
            });
            console.log('[DEBUG] After session filter:', filtered.length, 'students');
        }
        
        // Search filter
        const searchTerm = $('#reviewStudentSearch').val().toLowerCase().trim();
        console.log('[DEBUG] Search term:', searchTerm);
        
        if (searchTerm) {
            filtered = filtered.filter(student => {
                return (student.FULL_NAME || '').toLowerCase().includes(searchTerm) ||
                       (student.INTERN_ID || '').toLowerCase().includes(searchTerm);
            });
            console.log('[DEBUG] After search filter:', filtered.length, 'students');
        }
        
        // Reset to page 1 and render results
        currentReviewPage = 1;
        console.log('[DEBUG] Rendering', filtered.length, 'filtered students');
        renderReviewStudentList(filtered, null, true);
    }
    
    // Change page for Review tab pagination
    function changeReviewPage(page) {
        console.log('[DEBUG] changeReviewPage called with page:', page);
        currentReviewPage = page;
        
        // Reapply filters to get current filtered data and render with new page
        applyReviewFilters(allReviewStudents);
    }

    function renderReviewStudentList(students, sessions = []) {
        console.log('[DEBUG] renderReviewStudentList called with:', students.length, 'students');
        console.log('[DEBUG] selectedReviewStudentId:', selectedReviewStudentId);
        console.log('[DEBUG] Sample student structure:', students[0]);
        
        let sorted = students.slice().sort((a, b) => {
            let aId = (a.STUDENT_ID || a.student_id || '').toString();
            let bId = (b.STUDENT_ID || b.student_id || '').toString();
            return aId.localeCompare(bId);
        });
        
        totalReviewStudents = sorted.length;
        const totalPages = Math.ceil(totalReviewStudents / reviewStudentsPerPage);
        
        // Get students for current page
        const startIndex = (currentReviewPage - 1) * reviewStudentsPerPage;
        const endIndex = startIndex + reviewStudentsPerPage;
        const paginatedStudents = sorted.slice(startIndex, endIndex);
        
        let studentListHtml = '';
        paginatedStudents.forEach(function(student) {
            let displayId = student.STUDENT_ID || student.student_id || 'Unknown ID';
            let isSelected = student.id === selectedReviewStudentId;
            console.log('[DEBUG] Rendering student:', { 
                id: student.id, 
                STUDENT_ID: student.STUDENT_ID,
                displayId: displayId,
                selectedReviewStudentId: selectedReviewStudentId,
                isSelected: isSelected
            });
            const studentId = student.id || student.STUDENT_ID || 'unknown';
            studentListHtml += `
                <div class="review-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${isSelected ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${studentId}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                    </span>
                    <span class="truncate">${displayId}</span>
                </div>
            `;
        });

        // Generate pagination controls
        let paginationHtml = '';
        if (totalPages > 1) {
            paginationHtml += `<div class='flex flex-col items-center mt-4 px-2'>`;
            paginationHtml += `<div class='text-sm text-gray-600 mb-2'>Page ${currentReviewPage} of ${totalPages}</div>`;
            paginationHtml += `<div class='flex gap-1'>`;
            
            // First page button
            const firstDisabled = currentReviewPage <= 1;
            paginationHtml += `<button class='review-page-btn px-3 py-2 text-sm border rounded ${firstDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='1' ${firstDisabled ? 'disabled' : ''}>&lt;&lt;</button>`;
            
            // Previous button
            const prevDisabled = currentReviewPage <= 1;
            paginationHtml += `<button class='review-page-btn px-3 py-2 text-sm border rounded ${prevDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentReviewPage - 1}' ${prevDisabled ? 'disabled' : ''}>&lt;</button>`;
            
            // Next button
            const nextDisabled = currentReviewPage >= totalPages;
            paginationHtml += `<button class='review-page-btn px-3 py-2 text-sm border rounded ${nextDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentReviewPage + 1}' ${nextDisabled ? 'disabled' : ''}>&gt;</button>`;
            
            // Last page button
            const lastDisabled = currentReviewPage >= totalPages;
            paginationHtml += `<button class='review-page-btn px-3 py-2 text-sm border rounded ${lastDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${totalPages}' ${lastDisabled ? 'disabled' : ''}>&gt;&gt;</button>`;
            
            paginationHtml += `</div>`;
            paginationHtml += `</div>`;
        }

        // Build the 20/80 column layout for Review tab
        let html = `<div class='review-main-wrapper flex flex-col md:flex-row w-full'>`;
        html += `<div class='review-student-list-section left-col w-full md:w-1/5 max-w-xs md:pr-4 order-1 mb-4 md:mb-0'>`;
        html += `<div class='mb-4'><input type='text' id='reviewStudentSearch' placeholder='Search student' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'></div>`;
        html += `<div class='mb-4'><select id='reviewSessionFilter' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'><option value='all'>All Terms</option></select></div>`;
        html += `<div id='reviewStudentListPanel' class='overflow-y-auto min-h-[400px] max-h-[500px] flex flex-col gap-1'>${studentListHtml}</div>`;
        html += `<div id='reviewPaginationContainer'>${paginationHtml}</div>`;
        html += `</div>`;
        html += `<div class='review-content-section right-col w-full md:w-4/5 md:pl-4 order-2'>`;
        if (!selectedReviewStudentId) {
            html += `
            <div class="flex flex-col items-center justify-center h-full">
                <div class="bg-blue-50 rounded-full p-6 mb-4">
                    <svg xmlns='http://www.w3.org/2000/svg' class='h-12 w-12 text-blue-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                </div>
                <div class="text-xl font-semibold text-blue-700 mb-2">No student selected</div>
                <div class="text-gray-500 text-base">Select a student from the list to view their reviewed assessment details.</div>
            </div>
            `;
        } else {
            // Add category dropdown for review tab
            html += `<div class="mb-4">
                <label for="reviewCategoryDropdown" class="block text-sm font-medium text-gray-700 mb-2">Filter by Category:</label>
                <select id="reviewCategoryDropdown" class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="soft" selected>Soft Skills</option>
                    <option value="comm">Communication Skills</option>
                    <option value="tech">Technical Skills</option>
                </select>
            </div>`;
            html += `<div id='reviewedEvalList' class='space-y-4 max-h-[700px] overflow-y-auto pr-2'></div>`;
        }
        html += `</div>`;
        html += `</div>`;
        $('#reviewTabContent').html(html);
        
        // Populate session dropdown with available sessions
        if (sessions && sessions.length > 0) {
            let sessionOptions = '<option value="all">All Terms</option>';
            sessions.forEach(session => {
                sessionOptions += `<option value="${session}">${session}</option>`;
            });
            $('#reviewSessionFilter').html(sessionOptions);
            
            // Auto-select the latest session (first in sorted array)
            if (sessions.length > 0) {
                $('#reviewSessionFilter').val(sessions[0]);
            }
        }
        
        console.log('[DEBUG] Review tab HTML replaced. New content loaded.');
    }

    // Apply both session and search filters for Review tab
    function applyReviewFilters(allStudentsData) {
        const selectedSession = $('#reviewSessionFilter').val();
        const searchQuery = $('#reviewStudentSearch').val().trim();
        
        console.log('[DEBUG] Applying Review filters - Session:', selectedSession, 'Search:', searchQuery);
        
        let filtered = allStudentsData.slice();
        
        // Filter by session first
        if (selectedSession && selectedSession !== 'all') {
            filtered = filtered.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === selectedSession;
            });
            console.log('[DEBUG] After session filter:', filtered.length, 'students');
        }
        
        // Then filter by search query
        if (searchQuery.length > 0) {
            // Remove any previous invalid message
            $('#reviewStudentSearchInvalidMsg').remove();
            
            filtered = filtered.filter(s => {
                let idStr = (s.STUDENT_ID || s.student_id || '').toString().toLowerCase();
                let searchLower = searchQuery.toLowerCase();
                
                // Search by STUDENT_ID only
                return idStr.includes(searchLower);
            });
            console.log('[DEBUG] After search filter:', filtered.length, 'students');
        }
        
        // Update display without resetting page number when filtering
        updateReviewStudentListDisplay(filtered);
        
        // Restore search input focus if it was focused
        const $input = $('#reviewStudentSearch');
        $input.val(searchQuery);
        if (document.activeElement === $input[0]) {
            $input.focus();
        }
    }
    
    // Update only the Review student list display without rebuilding dropdown or right panel
    function updateReviewStudentListDisplay(students, resetPage = true) {
        let sorted = students.slice().sort((a, b) => {
            let aId = (a.STUDENT_ID || a.student_id || '').toString();
            let bId = (b.STUDENT_ID || b.student_id || '').toString();
            return aId.localeCompare(bId);
        });
        
        totalReviewStudents = sorted.length;
        const totalPages = Math.ceil(totalReviewStudents / reviewStudentsPerPage);
        
        // Reset to page 1 only when filtering, not when changing pages
        if (resetPage) {
            currentReviewPage = 1;
        }
        
        // Get students for current page
        const startIndex = (currentReviewPage - 1) * reviewStudentsPerPage;
        const endIndex = startIndex + reviewStudentsPerPage;
        const paginatedStudents = sorted.slice(startIndex, endIndex);
        
        let studentListHtml = '';
        paginatedStudents.forEach(function(student) {
            let displayId = student.STUDENT_ID || student.student_id || 'Unknown ID';
            const studentId = student.id || student.STUDENT_ID || 'unknown';
            studentListHtml += `
                <div class="review-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${studentId === selectedReviewStudentId ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${studentId}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 616 0z' /></svg>
                    </span>
                    <span class="truncate">${displayId}</span>
                </div>
            `;
        });
        
        // Generate pagination controls
        let paginationHtml = '';
        if (totalPages > 1) {
            paginationHtml += `<div class='flex flex-col items-center mt-4 px-2'>`;
            paginationHtml += `<div class='text-sm text-gray-600 mb-2'>Page ${currentReviewPage} of ${totalPages}</div>`;
            paginationHtml += `<div class='flex gap-1'>`;
            
            // First page button
            const firstDisabled = currentReviewPage <= 1;
            paginationHtml += `<button class='review-page-btn px-3 py-2 text-sm border rounded ${firstDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='1' ${firstDisabled ? 'disabled' : ''}>&lt;&lt;</button>`;
            
            // Previous button
            const prevDisabled = currentReviewPage <= 1;
            paginationHtml += `<button class='review-page-btn px-3 py-2 text-sm border rounded ${prevDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentReviewPage - 1}' ${prevDisabled ? 'disabled' : ''}>&lt;</button>`;
            
            // Next button
            const nextDisabled = currentReviewPage >= totalPages;
            paginationHtml += `<button class='review-page-btn px-3 py-2 text-sm border rounded ${nextDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${currentReviewPage + 1}' ${nextDisabled ? 'disabled' : ''}>&gt;</button>`;
            
            // Last page button
            const lastDisabled = currentReviewPage >= totalPages;
            paginationHtml += `<button class='review-page-btn px-3 py-2 text-sm border rounded ${lastDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}' data-page='${totalPages}' ${lastDisabled ? 'disabled' : ''}>&gt;&gt;</button>`;
            
            paginationHtml += `</div>`;
            paginationHtml += `</div>`;
        }
        
        // Update student list and pagination containers
        $('#reviewStudentListPanel').html(studentListHtml);
        $('#reviewPaginationContainer').html(paginationHtml);
        
        // Directly bind click events to pagination buttons after they're created
        $('.review-page-btn').off('click').on('click', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (!$(this).prop('disabled') && page > 0) {
                changeReviewPage(page);
            }
        });
    }
    
    // Function to change page in Review student list
    function changeReviewPage(page) {
        console.log('[DEBUG] changeReviewPage called with page:', page);
        currentReviewPage = page;
        
        // Get current filtered students
        const selectedSession = $('#reviewSessionFilter').val();
        const searchQuery = $('#reviewStudentSearch').val().trim();
        
        let filteredStudents = allReviewStudents.slice();
        
        // Apply session filter
        if (selectedSession && selectedSession !== 'all') {
            filteredStudents = filteredStudents.filter(student => {
                const studentSession = student.SESSION_NAME || '';
                return studentSession === selectedSession;
            });
        }
        
        // Apply search filter
        if (searchQuery.length > 0) {
            filteredStudents = filteredStudents.filter(s => {
                let idStr = (s.STUDENT_ID || s.student_id || '').toString();
                return idStr.includes(searchQuery);
            });
        }
        
        // Update display with current page (don't reset page number)
        updateReviewStudentListDisplay(filteredStudents, false);
    }

    // Handle student selection in Review tab
    $(document).on('click', '.review-student-item', function() {
        console.log('[DEBUG] Review student clicked');
        selectedReviewStudentId = $(this).data('studentid');
        console.log('[DEBUG] Raw selectedReviewStudentId:', selectedReviewStudentId);
        console.log('[DEBUG] allReviewStudents sample:', allReviewStudents.slice(0, 2));
        
        // Map INTERNS_ID to STUDENT_ID with debug
        let selectedStudent = allReviewStudents.find(s => s.id == selectedReviewStudentId);
        let studentDbId = selectedStudent && selectedStudent.STUDENT_ID ? selectedStudent.STUDENT_ID : selectedReviewStudentId;
        console.log('[Review] Selected INTERNS_ID:', selectedReviewStudentId, '| Mapped STUDENT_ID:', studentDbId, '| Student object:', selectedStudent);
        
        // Just update visual selection without rebuilding HTML
        $('.review-student-item').removeClass('bg-blue-100 border-blue-400 font-semibold text-blue-700').addClass('text-gray-800');
        $(this).removeClass('text-gray-800').addClass('bg-blue-100 border-blue-400 font-semibold text-blue-700');
        
        // Make sure right panel exists, if not create it
        if ($('#reviewedEvalList').length === 0) {
            console.log('[DEBUG] Right panel missing in Review tab, rebuilding HTML structure');
            
            // Get current state before rebuilding
            let currentSession = $('#reviewSessionFilter').val();
            let currentSearch = $('#reviewStudentSearch').val();
            let currentPageNum = currentReviewPage;
            
            // Get currently filtered students
            let currentStudents = allReviewStudents.slice();
            if (currentSession && currentSession !== 'all') {
                currentStudents = currentStudents.filter(student => {
                    const studentSession = student.SESSION_NAME || '';
                    return studentSession === currentSession;
                });
            }
            if (currentSearch && currentSearch.trim().length > 0) {
                currentStudents = currentStudents.filter(s => {
                    let idStr = (s.STUDENT_ID || s.student_id || '').toString();
                    return idStr.includes(currentSearch.trim());
                });
            }
            
            // Get unique sessions for dropdown
            const sessions = [...new Set(allReviewStudents.map(s => s.SESSION_NAME).filter(Boolean))];
            sessions.sort((a, b) => b.localeCompare(a));
            
                // Rebuild with current filtered students and proper sessions
                console.log('[DEBUG] Rebuilding Review HTML with:', currentStudents.length, 'students');
                console.log('[DEBUG] Sample rebuild student:', currentStudents[0]);
                console.log('[DEBUG] Available sessions for rebuild:', sessions);
                renderReviewStudentList(currentStudents, sessions);            // Restore all state
            $('#reviewSessionFilter').val(currentSession);
            $('#reviewStudentSearch').val(currentSearch);
            currentReviewPage = currentPageNum;
            
            // Rebind event handlers after HTML rebuild
            $('#reviewSessionFilter').off('change').on('change', function() {
                console.log('[DEBUG] Review session filter changed (rebound):', $(this).val());
                applyReviewFilters(allReviewStudents);
            });
            
            $('#reviewStudentSearch').off('input').on('input', function() {
                console.log('[DEBUG] Review search input changed (rebound):', $(this).val());
                applyReviewFilters(allReviewStudents);
            });
        }
        
        // Show loading in right panel
        $('#reviewedEvalList').html('<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div><div class="mt-2 text-gray-600">Loading reviewed evaluation...</div></div>');
        
        // Load the Pre-Assessment data in read-only mode for this student
        loadStudentReviewData(studentDbId, selectedReviewStudentId);
    });
    
    // Load student review data (read-only Pre-Assessment view)
    function loadStudentReviewData(studentDbId, selectedStudentId) {
        console.log('[DEBUG] Loading Review data for student:', studentDbId);
        
        // Check if data is already cached using existing cache system
        const cacheKey = `review_${studentDbId}_${selectedStudentId}`;
        if (isCacheValid('evaluation', cacheKey) && dataCache.evaluationData[cacheKey]) {
            console.log('[DEBUG] ⚡ Using cached review data for student:', studentDbId);
            // Render cached data instantly
            displayReviewEvaluations(dataCache.evaluationData[cacheKey]);
            return;
        }
        
        console.log('[DEBUG] Fetching fresh review data for student:', cacheKey);
        
        // Use the same endpoint as Pre-Assessment but for review purposes
        $.ajax({
            url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
            type: 'POST',
            dataType: 'json',
            data: JSON.stringify({ action: 'getReviewedEvaluation', studentId: studentDbId }),
            contentType: 'application/json',
            success: function(response) {
                console.log('[DEBUG] Review evaluation response:', response);
                
                if (response.success && response.evaluations && response.evaluations.length > 0) {
                    // Cache the evaluation data using existing cache system
                    const cacheKey = `review_${studentDbId}_${selectedStudentId}`;
                    dataCache.evaluationData[cacheKey] = response.evaluations;
                    dataCache.lastUpdated.evaluationData[cacheKey] = Date.now();
                    console.log('[DEBUG] 💾 Cached review data for:', cacheKey);
                    
                    displayReviewEvaluations(response.evaluations);
                } else {
                    // If no reviewed evaluation found, show message to check Pre-Assessment
                    $('#reviewedEvalList').html(`
                        <div class="flex flex-col items-center justify-center py-12">
                            <div class="bg-yellow-50 rounded-full p-6 mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                            <div class="text-xl font-semibold text-yellow-700 mb-2">No Evaluation Found</div>
                            <div class="text-gray-600 text-center">
                                This student has not been evaluated yet.<br>
                                <span class="text-blue-600 font-medium">Check the Pre-Assessment tab to rate this student first.</span>
                            </div>
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('[DEBUG] Review data loading error:', error);
                $('#reviewedEvalList').html(`
                    <div class="flex flex-col items-center justify-center py-12">
                        <div class="bg-red-50 rounded-full p-6 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="text-xl font-semibold text-red-700 mb-2">Error Loading Data</div>
                        <div class="text-gray-600">Unable to load evaluation data for this student.</div>
                    </div>
                `);
            }
        });
    }
    
    // Display review evaluations in read-only format
    function displayReviewEvaluations(evaluations) {
        console.log('[DEBUG] Displaying review evaluations:', evaluations);
        
        // Group evaluations by category
        const evalsByCategory = {
            'Soft Skills': evaluations.filter(ev => ev.category && 
                (ev.category.toLowerCase().includes('soft') || 
                 ev.category.toLowerCase().includes('personal') || 
                 ev.category.toLowerCase().includes('interpersonal'))),
            'Communication Skills': evaluations.filter(ev => ev.category && ev.category.toLowerCase().includes('comm')),
            'Technical Skills': evaluations.filter(ev => ev.category && ev.category.toLowerCase().includes('technical'))
        };
        
        let evalHtml = `
            <div class="mb-6 p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h2 class="text-lg font-semibold text-blue-800">Evaluation Results - Read Only</h2>
                </div>
                <p class="text-blue-700 text-sm mt-1">This is a read-only view of the student's evaluated responses.</p>
            </div>
        `;
        
        // Generate HTML for all categories with filterable containers
        Object.keys(evalsByCategory).forEach(function(categoryName) {
            if (evalsByCategory[categoryName].length > 0) {
                const categoryId = categoryName.toLowerCase().includes('soft') ? 'soft' :
                                   categoryName.toLowerCase().includes('comm') ? 'comm' : 'tech';

                evalHtml += `<div class="review-category-container" data-category="${categoryId}">`;
                evalHtml += `<div class="bg-gray-100 rounded-lg p-4 mb-4 border-l-4 border-gray-500">`;
                evalHtml += `<h3 class="text-lg font-semibold text-gray-800">${categoryName}</h3>`;
                evalHtml += `</div>`;

                evalsByCategory[categoryName].forEach(function(ev) {
                    // Generate star display for rating
                    let starsHtml = '';
                    for (let i = 1; i <= 5; i++) {
                        const isActive = ev.rating >= i;
                        starsHtml += `<span class="text-2xl ${isActive ? 'text-yellow-400' : 'text-gray-300'}">${isActive ? '★' : '☆'}</span>`;
                    }

                    evalHtml += `
                    <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
                        <div class="font-semibold text-gray-800 text-lg mb-3">${ev.question_text}</div>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-md p-4">
                                <div class="text-gray-700 text-sm font-medium mb-2">Student's Answer:</div>
                                <div class="text-gray-900 text-base">${ev.answer}</div>
                            </div>
                            <div class="flex flex-col items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-50 rounded-md p-4">
                                <div class="text-gray-700 text-sm font-medium mb-2">Rating Given:</div>
                                <div class="flex items-center space-x-1 mb-2">
                                    ${starsHtml}
                                </div>
                                <div class="text-2xl font-bold text-blue-600">${ev.rating}/5</div>
                            </div>
                        </div>
                    </div>
                    `;
                });
                evalHtml += `</div>`; // Close category container
            }
        });
        
        const hasAny = Object.values(evalsByCategory).some(arr => Array.isArray(arr) && arr.length > 0);
        if (hasAny) {
            $('#reviewedEvalList').html(evalHtml);
            // Apply current dropdown filter if not 'all'
            const sel = $('#reviewCategoryDropdown').val();
            if (sel && sel !== 'all') {
                $('.review-category-container').hide();
                $(`.review-category-container[data-category="${sel}"]`).show();
            }
        } else {
            $('#reviewedEvalList').html(`
                <div class="flex flex-col items-center justify-center py-12">
                    <div class="bg-gray-50 rounded-full p-6 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="text-xl font-semibold text-gray-700 mb-2">No Evaluations Available</div>
                    <div class="text-gray-500 text-center">No evaluation categories found for this student.</div>
                </div>
            `);
        }
    }

    // Function to update review student selection highlighting without rebuilding layout
    function updateReviewStudentSelectionHighlight() {
        console.log('[DEBUG] Updating review student selection highlight for:', selectedReviewStudentId);
        // Remove highlight from all students
        $('.review-student-item').removeClass('bg-blue-100 border-blue-400 font-semibold text-blue-700').addClass('text-gray-800');
        
        // Add highlight to selected student
        if (selectedReviewStudentId) {
            let targetElement = $(`.review-student-item[data-studentid="${selectedReviewStudentId}"]`);
            console.log('[DEBUG] Target element for highlighting:', targetElement.length);
            targetElement.removeClass('text-gray-800').addClass('bg-blue-100 border-blue-400 font-semibold text-blue-700');
        }
    }

    function loadReviewedEvaluation(studentId) {
        // Fetch reviewed evaluation for selected student
        $.ajax({
            url: 'ajaxhandler/coordinatorRateStudentAnswersAjax.php',
            type: 'POST',
            dataType: 'json',
            data: JSON.stringify({ action: 'getReviewedEvaluation', studentId: studentId }),
            contentType: 'application/json',
            success: function(response) {
                if (response.success && response.evaluations && response.evaluations.length > 0) {
                    // Group evaluations by category with improved filtering
                    const evalsByCategory = {
                        'Soft Skills': response.evaluations.filter(ev => ev.category && 
                            (ev.category.toLowerCase().includes('soft') || 
                             ev.category.toLowerCase().includes('personal') || 
                             ev.category.toLowerCase().includes('interpersonal'))),
                        'Communication Skills': response.evaluations.filter(ev => ev.category && ev.category.toLowerCase().includes('comm')),
                        'Technical Skills': response.evaluations.filter(ev => ev.category && ev.category.toLowerCase().includes('technical'))
                    };
                    
                    // Store evaluations globally for filtering
                    window.reviewEvaluationsByCategory = evalsByCategory;
                    
                    // Generate HTML for all categories
                    let evalHtml = '';
                    Object.keys(evalsByCategory).forEach(function(categoryName) {
                        if (evalsByCategory[categoryName].length > 0) {
                            const categoryClass = categoryName.toLowerCase().replace(/\s+/g, '-');
                            const categoryId = categoryName.toLowerCase().includes('soft') ? 'soft' : 
                                             categoryName.toLowerCase().includes('comm') ? 'comm' : 'tech';
                            
                            evalHtml += `<div class="review-category-container" data-category="${categoryId}">`;
                            evalHtml += `<div class="bg-blue-50 rounded-lg p-3 mb-4 border-l-4 border-blue-500">`;
                            evalHtml += `<h3 class="text-lg font-semibold text-blue-700">${categoryName}</h3>`;
                            evalHtml += `</div>`;
                            
                            evalsByCategory[categoryName].forEach(function(ev) {
                                evalHtml += `
                                <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
                                    <div class="font-semibold text-blue-700 text-lg mb-2">${ev.question_text}</div>
                                    <div class="flex flex-col md:flex-row gap-4">
                                        <div class="bg-blue-50 rounded-md p-4 flex-1 mb-2 md:mb-0">
                                            <div class="text-gray-700 text-base font-medium mb-1">Answer:</div>
                                            <div class="text-gray-900 text-base">${ev.answer}</div>
                                        </div>
                                        <div class="flex-1">
                                            <table class="min-w-full bg-white rounded-lg border border-gray-200">
                                                <thead>
                                                    <tr>
                                                        <th colspan="5" class="bg-blue-50 text-blue-700 text-sm font-bold px-4 py-2 text-center rounded-t">Rating Given</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        ${[5,4,3,2,1].map(i => `
                                                            <td class="px-4 py-2 text-center">
                                                                <span class="block text-sm font-semibold text-gray-700">${i}</span>
                                                                <span class="reviewed-rating text-2xl" style="color:${ev.rating == i ? '#3182ce' : '#a0aec0'};">${ev.rating == i ? '&#9733;' : '&#9734;'}</span>
                                                            </td>
                                                        `).join('')}
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                `;
                            });
                            evalHtml += `</div>`; // Close category container
                        }
                    });
                    $('#reviewedEvalList').html(evalHtml);
                } else {
                    $('#reviewedEvalList').html('<div class="not-reviewed-message" style="text-align:center; color:#a0aec0; font-size:1.2em; margin-top:2em;">This student has not been rated. Check the Pre-Assessment.</div>');
                }
            },
            error: function() {
                $('#reviewedEvalList').html('<div class="review-message">Error loading reviewed evaluation.</div>');
            }
        });
    }

    // Initial load for Review tab student list is now handled by unified evaluation loading

    // Category dropdown handler for Review tab
    $(document).on('change', '#reviewCategoryDropdown', function() {
        const selectedCategory = $(this).val();
        
        if (selectedCategory === 'all') {
            // Show all categories
            $('.review-category-container').show();
        } else {
            // Hide all categories first
            $('.review-category-container').hide();
            // Show only selected category
            $(`.review-category-container[data-category="${selectedCategory}"]`).show();
        }
    });

    // Handle click on autocomplete suggestion in Review tab
    $(document).on('mousedown', '.autocomplete-item-review', function(e) {
        e.preventDefault();
        let selectedName = $(this).text();
        $('#reviewStudentSearch').val(selectedName);
        $('#reviewStudentSearchAutocomplete').remove();
        // Only show the card for the selected student
        let normalizedSelected = selectedName.toLowerCase().replace(/,/g, '').replace(/\s+/g, ' ').trim();
        let foundCard = false;
        $('.reviewed-eval-container .student-review-group').each(function() {
            let name = $(this).find('.student-review-title').text().toLowerCase().replace(/,/g, '').replace(/\s+/g, ' ').trim();
            if (name === normalizedSelected) {
                $(this).show();
                foundCard = true;
            } else {
                $(this).hide();
            }
        });
        // Show message if not reviewed
        if (!foundCard) {
            if ($('.reviewed-eval-container .not-reviewed-message').length === 0) {
                $('.reviewed-eval-container').append('<div class="not-reviewed-message" style="text-align:center; color:#a0aec0; font-size:1.2em; margin-top:2em;">This student has not been rated. Check the Rate tab.</div>');
            }
            $('.reviewed-eval-container .not-reviewed-message').show();
        } else {
            $('.reviewed-eval-container .not-reviewed-message').hide();
        }
    });

    // Hide autocomplete dropdown when clicking outside (Review tab)
    $(document).on('mousedown', function(e) {
        const $dropdown = $('#reviewStudentSearchAutocomplete');
        const $input = $('#reviewStudentSearch');
        if ($dropdown.length && !$dropdown.is(e.target) && $dropdown.has(e.target).length === 0 && !$input.is(e.target)) {
            $dropdown.remove();
        }
    });
    // lastValidSearch already declared above, do not redeclare
    // Removed legacy name-based search filter for student list. Only numeric STUDENT_ID filtering remains.

    // Handle click on autocomplete suggestion
    $(document).on('mousedown', '.autocomplete-item', function(e) {
        e.preventDefault();
        let selectedName = $(this).text();
        $('#rateStudentSearch').val(selectedName);
        $('#rateStudentSearchAutocomplete').remove();

        // Only show the card for the selected student, override input filter
        let normalizedSelected = selectedName.toLowerCase().replace(/,/g, '').replace(/\s+/g, ' ').trim();
        let foundCard = false;
        $('.student-review-group').each(function() {
            let name = $(this).find('.student-review-title').text().toLowerCase().replace(/,/g, '').replace(/\s+/g, ' ').trim();
            if (name === normalizedSelected) {
                $(this).show();
                foundCard = true;
            } else {
                $(this).hide();
            }
        });
        // Do NOT trigger input event, just update messages below

        // Check if selected student is already rated and show message if so
        let alreadyRated = false;
        $('#reviewedEvalList .student-review-group').each(function() {
            let name = $(this).find('.student-review-title').text().toLowerCase().replace(/,/g, '').replace(/\s+/g, ' ').trim();
            if (name === normalizedSelected) {
                alreadyRated = true;
            }
        });
        if (alreadyRated) {
            if ($('#rateEvalList .already-rated-message').length === 0) {
                $('#rateEvalList').append('<div class="already-rated-message" style="text-align:center; color:#a0aec0; font-size:1.2em; margin-top:2em;">This student has already been rated. Check the Review tab.</div>');
            }
            $('#rateEvalList .no-match-message').hide();
            $('#rateEvalList .already-rated-message').show();
        } else if (!foundCard) {
            $('#rateEvalList .already-rated-message').hide();
            $('#rateEvalList .no-match-message').show();
        } else {
            $('#rateEvalList .already-rated-message').hide();
            $('#rateEvalList .no-match-message').hide();
        }
    });

    // Hide autocomplete dropdown when clicking outside
    $(document).on('mousedown', function(e) {
        const $dropdown = $('#rateStudentSearchAutocomplete');
        const $input = $('#rateStudentSearch');
        if ($dropdown.length && !$dropdown.is(e.target) && $dropdown.has(e.target).length === 0 && !$input.is(e.target)) {
            $dropdown.remove();
        }
    });

    // Sticky search for student names in rateEvalList
    let lastValidSearch = '';
    $(document).on('input', '#rateStudentSearch', function() {
        const query = $(this).val().trim().toLowerCase();
        let foundMatch = false;
        $('.student-review-group').each(function() {
            const name = $(this).find('.student-review-title').text().toLowerCase();
            if (name.includes(query) && query.length > 0) {
                $(this).show();
                foundMatch = true;
            } else {
                $(this).hide();
            }
        });
        // If no match, revert to last valid search
        if (!foundMatch && lastValidSearch.length > 0) {
            $('.student-review-group').each(function() {
                const name = $(this).find('.student-review-title').text().toLowerCase();
                if (name.includes(lastValidSearch)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else if (foundMatch) {
            lastValidSearch = query;
        }
        // If search is cleared, show all
        if (query.length === 0) {
            $('.student-review-group').show();
            lastValidSearch = '';
        }
    });

    // Show reviewed evaluations button
    $(document).on('click', '#btnShowReviewedEvaluations', function() {
        $('#reviewTabBtn').click();
    });
});