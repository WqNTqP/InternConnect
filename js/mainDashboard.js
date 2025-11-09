
// Fetch and render all companies (HTEs) in the companies table
function loadAllCompaniesData() {
    $.ajax({
        url: 'ajaxhandler/attendanceAJAX.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'getHTEList' },
        success: function(response) {
            if (response.success && response.htes && Array.isArray(response.htes)) {
                renderCompaniesList(response.htes);
            } else {
                $('#allCompaniesTableBody').html('<tr><td colspan="6" class="text-center text-gray-500 py-6">No companies found.</td></tr>');
            }
        },
        error: function() {
            $('#allCompaniesTableBody').html('<tr><td colspan="6" class="text-center text-red-500 py-6">Error loading companies.</td></tr>');
        }
    });
}

// Render companies in the companies table
function renderCompaniesList(companies) {
    let html = '';
    companies.forEach(function(company) {
        let logoHtml = company.LOGO ? `<img src='uploads/hte_logos/${company.LOGO}' alt='Logo' class='h-8 w-8 rounded-full object-cover border' />` : '-';
        logoHtml += ` <button class='update-logo-btn bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs ml-2' data-hteid='${company.HTE_ID}'>Update Logo</button>`;
        html += `<tr>
            <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-900\">${company.NAME || '-'}<\/td>
            <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${company.INDUSTRY || '-'}<\/td>
            <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${company.ADDRESS || '-'}<\/td>
            <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${company.CONTACT_PERSON || '-'}<\/td>
            <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${company.CONTACT_NUMBER || '-'}<\/td>
            <td class=\"px-6 py-3 whitespace-nowrap text-sm text-gray-700\">${logoHtml}<\/td>
        <\/tr>`;
    });
    $('#allCompaniesTableBody').html(html);
}

// Handle MOA view button click (show modal or download)
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
// Show Add Session form and hide other forms
$(document).on('click', '#btnAddSession', function(e) {
    e.preventDefault();
    $('#studentFormContainer').hide();
    $('#addHTEFormContainer').hide();
    $('#allStudentsContainer').hide();
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
            } else {
                alert(response.message || 'Error adding student.');
            }
            $form.data('submitted', false);
        },
        error: function(xhr, status, error) {
            alert('An error occurred while adding the student.');
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
                let x = '<option value="">Select Session</option>';
                for(let i=0; i<rv.length; i++) {
                    let cs = rv[i];
                    x += `<option value="${cs.ID}">${cs.YEAR} ${cs.TERM}</option>`;
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
                    const imgPath = 'uploads/' + student.profile_picture;
                    $('#profilePicture img').attr('src', imgPath).show();
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
                        <table class="w-full text-left rounded-lg overflow-hidden">
                            <thead><tr class="bg-purple-100 text-purple-800"><th class="px-4 py-2">Category</th><th class="px-4 py-2">Supervisor Avg</th><th class="px-4 py-2">Self Avg</th></tr></thead>
                            <tbody>
                            ${summary.averages.map(row => `<tr><td class="px-4 py-2">${row.category}</td><td class="px-4 py-2">${row.supervisor_avg !== null ? row.supervisor_avg : '-'}</td><td class="px-4 py-2">${row.self_avg !== null ? row.self_avg : '-'}</td></tr>`).join('')}
                            <tr class="bg-purple-200 font-bold"><td class="px-4 py-2">Total Average</td><td class="px-4 py-2">${supervisorAvg}</td><td class="px-4 py-2">${selfAvg}</td></tr>
                            </tbody>
                        </table>
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
                $('#postAnalysisContentArea').html('<div class="error">No post-analysis data found for this student.</div>');
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
                renderPostAnalysisStudentList(allPostAnalysisStudents);
            } else {
                allPostAnalysisStudents = [];
                $('#postAnalysisStudentListPanel').html('<div class="no-students">No students found for post-analysis.</div>');
            }
        },
        error: function() {
            allPostAnalysisStudents = [];
            $('#postAnalysisStudentListPanel').html('<div class="no-students">Error loading students.</div>');
        }
    });
}

function renderPostAnalysisStudentList(students) {
    let html = '';
    students.forEach(function(student) {
        const displayName = student.NAME + ' ' + student.SURNAME;
        html += `<div class="postanalysis-student-item" data-studentid="${student.INTERNS_ID}">${displayName}</div>`;
    });
    $('#postAnalysisStudentListPanel').html(html);
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
    loadPostAnalysisStudents();
});
$(document).ready(function() {
    if ($('#postAnalysisContent').is(':visible')) {
        loadPostAnalysisStudents();
    }
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
                loadAllCompaniesData();
            } else {
                alert(response.message || 'Failed to update company logo.');
            }
        },
        error: function() {
            alert('An error occurred while uploading the logo.');
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
        if (!studentId) {
            alert("Student ID not found.");
            return;
        }
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
        // Show/hide no match message
        if (!foundMatch && query.length > 0) {
            $('#rateEvalList .no-match-message').show();
        } else {
            $('#rateEvalList .no-match-message').hide();
        }
                }
            },
            error: function(xhr, status, error) {
                alert("Error fetching student dashboard info: " + error);
            }
        });
    });

    // Modal for displaying student dashboard info (all stats)
    function showStudentDashboardModal(stats) {
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
    // Toggle user dropdown on profile click
    $(document).on('click', '#userProfile', function(e) {
        $('#userDropdown').toggle();
        e.stopPropagation();
    });
    // Hide dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#userProfile').length) {
            $('#userDropdown').hide();
        }
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

    function renderPostStudentList(students) {
        let sorted = students.slice().sort((a, b) => (a.NAME + ' ' + a.SURNAME).localeCompare(b.NAME + ' ' + b.SURNAME));
        let studentListHtml = '';
        sorted.forEach(function(student) {
            const displayName = student.NAME + ' ' + student.SURNAME;
            studentListHtml += `
                <div class="postassessment-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${student.INTERNS_ID === selectedPostStudentId ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${student.INTERNS_ID}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                    </span>
                    <span class="truncate">${displayName}</span>
                </div>
            `;
        });
        // 20/80 layout for Post-Assessment tab
    let html = `<div class='flex w-full'>`;
    html += `<div class='left-col w-1/5 max-w-xs pr-4'>`;
    const searchValue = window.postStudentSearchValue || '';
    html += `<div class='mb-4'><input type='text' id='postStudentSearch' value='${searchValue.replace(/'/g, "&#39;").replace(/"/g, '&quot;')}' placeholder='Search student name' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'></div>`;
    html += `<div id='postStudentListPanel' class='overflow-y-auto max-h-[420px] flex flex-col gap-1'>${studentListHtml}</div>`;
        html += `</div>`;
        html += `<div class='right-col w-4/5 pl-4'>`;
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
        } else {
            html += `<div id='postEvalList' class='space-y-4 max-h-[700px] overflow-y-auto pr-2'></div>`;
        }
        html += `</div>`;
        html += `</div>`;
        $('#postAssessmentTabContent').html(html);
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

    // Populate post-assessment student list when tab is activated
    // Load post-assessment students for coordinator
    function loadPostAssessmentStudents() {
        $.ajax({
            url: 'ajaxhandler/coordinatorPostAssessmentAjax.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.students) {
                    allPostStudents = response.students;
                    renderPostStudentList(allPostStudents);
                } else {
                    allPostStudents = [];
                    $('#postStudentListPanel').html('<div class="no-students">No students found for post-assessment.</div>');
                }
            },
            error: function(xhr, status, error) {
                allPostStudents = [];
                $('#postStudentListPanel').html('<div class="no-students">Error loading students.</div>');
            }
        });
    }

    $(document).on('click', '#postAssessmentTabBtn', function() {
    selectedPostStudentId = null;
    loadPostAssessmentStudents();
    });

    // Also populate on page load if tab is visible
    $(document).ready(function() {
        if ($('#postAssessmentTabContent').is(':visible')) {
            loadPostAssessmentStudents();
        }
    });

    // Search filter for post-assessment student list
    $(document).on('input', '#postStudentSearch', function() {
        const query = $(this).val();
        window.postStudentSearchValue = query;
        window.shouldRefocusPostSearch = true;
        let filtered = allPostStudents.filter(s => {
            const displayName = (s.NAME + ' ' + s.SURNAME).toLowerCase();
            return displayName.includes(query.trim().toLowerCase());
        });
        renderPostStudentList(filtered);
    });

    // Handle student selection in Post-Assessment tab
    $(document).on('click', '.postassessment-student-item', function() {
    selectedPostStudentId = $(this).data('studentid');
    // Re-render student list to update highlight, preserving search value
    renderPostStudentList(allPostStudents);
    loadPostAssessmentEvaluation(selectedPostStudentId);
// Load and display post-assessment evaluation for selected student
function loadPostAssessmentEvaluation(studentId) {
    // Show loading indicator (optional)
    $('#postEvalList').html('<div class="loading">Loading evaluation...</div>');
    $.ajax({
        url: 'ajaxhandler/coordinatorPostAssessmentAjax.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'getStudentPostAssessment',
            interns_id: studentId
        },
        success: function(response) {
            if (response.success) {
                if (response.hasSupervisorRating && response.records.length > 0) {
                    // Group records by category
                    const grouped = {};
                    response.records.forEach(function(rec) {
                        if (!grouped[rec.category]) grouped[rec.category] = [];
                        grouped[rec.category].push(rec);
                    });
                    let html = `<div class="post-eval-container max-h-[700px] overflow-y-auto pr-2">
                        <h3 class="text-2xl font-bold text-blue-700 mb-6">Post-Evaluation</h3>`;
                    Object.keys(grouped).forEach(function(category) {
                        html += `<div class="mb-8">
                            <h4 class="post-eval-category-header text-lg font-semibold text-gray-800 mb-3 border-b border-gray-200 pb-1">${category}</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white rounded-lg shadow border border-gray-200">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-sm font-bold text-blue-700">Disciplines/Task</th>
                                            <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-32">Self Rating</th>
                                            <th class="px-4 py-2 text-center text-sm font-bold text-blue-700 w-32">Supervisor Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${grouped[category].map(rec => `
                                            <tr class="hover:bg-blue-50">
                                                <td class="px-4 py-2 text-gray-700">${rec.question_text ?? rec.question_id}</td>
                                                <td class="px-4 py-2 text-center text-blue-600 font-semibold w-32">${rec.self_rating ?? ''}</td>
                                                <td class="px-4 py-2 text-center text-green-600 font-semibold w-32">${rec.supervisor_rating ?? ''}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>`;
                    });
                    // Show the comment only once below all tables
                    const firstComment = response.records.find(rec => rec.comment && rec.comment.trim() !== '');
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
                } else {
                    $('#postEvalList').html('<div class="no-eval">Not rated yet by supervisor.</div>');
                }
            } else {
                $('#postEvalList').html('<div class="no-eval">No evaluation data found.</div>');
            }
        },
        error: function() {
            $('#postEvalList').html('<div class="no-eval">Error loading evaluation.</div>');
        }
    });
}
    });
    // Evaluation tab bar navigation
    $('#evalQuestionsTabBtn, #rateTabBtn, #postAssessmentTabBtn, #reviewTabBtn').click(function() {
        // Update button styles
        $(this).removeClass('text-gray-500 hover:text-gray-700 hover:bg-gray-50')
               .addClass('text-gray-900 bg-gray-100');
        
        // Update other buttons
        $(this).siblings('button').removeClass('text-gray-900 bg-gray-100')
               .addClass('text-gray-500 hover:text-gray-700 hover:bg-gray-50');

        // Hide all tab contents first
    $('#evalQuestionsTabContent, #rateTabContent, #postAssessmentTabContent, #reviewTabContent').hide();

        // Show the selected tab content
        if (this.id === 'evalQuestionsTabBtn') {
            $('#evalQuestionsTabContent').show();
        } else if (this.id === 'rateTabBtn') {
            $('#rateTabContent').show();
        } else if (this.id === 'postAssessmentTabBtn') {
            $('#postAssessmentTabContent').show();
        } else if (this.id === 'reviewTabBtn') {
            $('#reviewTabContent').show();
        // Removed stats tab logic
        }
    });

    // Show default tab on page load
    $('#evalQuestionsTabContent').show();
    $('#rateTabContent, #postAssessmentTabContent, #reviewTabContent').hide();
    // --- Prediction Tab Logic ---
    // Load students for prediction tab on tab switch or page load
    function loadPredictionStudents() {
        $('#predictionSpinner').show();
        $.ajax({
            url: 'ajaxhandler/predictionAjax.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                $('#predictionSpinner').hide();
                if (!response.success) {
                    alert('Error: ' + response.error);
                    return;
                }
                let students = response.students;
                let tbody = '';
                students.forEach(function(student, idx) {
                        let statusIcon = student.valid ? '<span class="ml-1">✔️</span>' : '<span class="ml-1" style="color:red;" title="Missing: ' + student.missing.join(', ') + '">❌</span>';
                            let statusText = student.STATUS;
                        let hasPrediction = student.pre_assessment && student.pre_assessment.ojt_placement;
                        let placementText = hasPrediction
                            ? `<span class="inline-block bg-green-100 text-green-700 font-bold px-3 py-1 rounded-full">${student.pre_assessment.ojt_placement}</span>`
                            : (student.valid
                                ? '<span class="inline-block bg-gray-100 text-gray-500 font-bold px-3 py-1 rounded-full">Ready</span>'
                                : '<span class="inline-block bg-gray-100 text-gray-500 font-bold px-3 py-1 rounded-full">Incomplete Data</span>');
                        let analysisData = {};
                        if (hasPrediction) {
                            analysisData = {
                                placement: student.pre_assessment.ojt_placement,
                                reasoning: student.pre_assessment.prediction_reasoning,
                                probabilities: student.pre_assessment.prediction_probabilities ? JSON.parse(student.pre_assessment.prediction_probabilities) : {},
                                confidence: student.pre_assessment.prediction_confidence
                            };
                        }
                        let statusClass = student.valid ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold';
                        let analysisBtnClass = hasPrediction ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 hover:bg-gray-500';
                        tbody += `<tr data-row="${idx}" class="hover:bg-blue-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${student.NAME}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${student.HTE_ASSIGNED}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm ${statusClass}">${statusText}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">${placementText}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button class="analysis-btn ${analysisBtnClass} text-white px-4 py-2 rounded-lg shadow transition" data-analysis="${encodeURIComponent(JSON.stringify(analysisData))}">Analysis</button>
                            </td>
                        </tr>`;
                });
                $('#predictionTable tbody').html(tbody);
                // Store students for later prediction
                window.predictionStudents = students;
            },
            error: function() {
                $('#predictionSpinner').hide();
                alert('Failed to fetch students for prediction.');
            }
        });
    }

    // Load students when prediction tab is shown
    $(document).on('click', '#predictionTab', function() {
        loadPredictionStudents();
    });
    // Also load on page ready if prediction tab is default
    $(document).ready(function() {
        if ($('#predictionContent').hasClass('active')) {
            loadPredictionStudents();
        }
    });

    // Run prediction and update database
    $(document).on('click', '#runPredictionBtn', function() {
        if (!window.predictionStudents) return;
        $('#predictionSpinner').show();
        let totalStudents = window.predictionStudents.length;
        let processedCount = 0;

        window.predictionStudents.forEach(function(student, idx) {
            if (student.valid) {
                // Get grades and skills from pre_assessment
                const grades = student.pre_assessment;
                
                // Use PHP proxy for Flask API (works in both local and production)
                const apiUrl = window.location.hostname === 'localhost' 
                    ? 'http://localhost:5000/predict'  // Direct to Flask in development
                    : 'api/predict.php';               // Use PHP proxy in production
                
                $.ajax({
                    url: apiUrl,
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(grades),
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
                                // Hide spinner when all students are processed
                                if (processedCount === totalStudents) {
                                    $('#predictionSpinner').hide();
                                    // Reload the student list to refresh the data
                                    loadPredictionStudents();
                                }
                            },
                            error: function(xhr, status, error) {
                                processedCount++;
                                console.error("Error saving prediction for student", student.STUDENT_ID, ":", error);
                                // Hide spinner when all students are processed
                                if (processedCount === totalStudents) {
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
            html = `<div class="text-center py-6 text-red-500 text-lg font-semibold">${(analysis && analysis.error ? analysis.error : 'No analysis available.')}</div>`;
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
                reasoningHtml = `<div class="mb-4 p-4 rounded-xl border-2 border-blue-200 bg-blue-50 shadow-sm"><div class="font-bold text-blue-700 mb-2 text-lg">Reasoning</div><div class="text-gray-800 text-base">${highlightGrades(analysis.reasoning)}</div></div>`;
            }
            // Probability section - smaller badges, more spacing
            let probabilityHtml = '';
            if (analysis.probabilities && Object.keys(analysis.probabilities).length > 0) {
                probabilityHtml = `<div class="mb-4"><div class="font-bold text-gray-700 mb-1">Probability Breakdown</div><div class="grid grid-cols-2 gap-3">${Object.entries(analysis.probabilities).map(([k, v]) => {
                    let color = 'bg-gray-200 text-gray-800';
                    if (k === 'Business Operations') color = 'bg-yellow-100 text-yellow-800';
                    else if (k === 'Technical Support') color = 'bg-green-100 text-green-800';
                    else if (k === 'Systems Development') color = 'bg-blue-100 text-blue-800';
                    else if (k === 'Research') color = 'bg-purple-100 text-purple-800';
                    return `<div class="flex items-center justify-between px-2 py-1 rounded text-sm font-semibold ${color}"><span>${k}</span><span>${v}%</span></div>`;
                }).join('')}</div></div>`;
            }
            // Probability explanation
            let probExpHtml = '';
            if (analysis.prob_explanation && analysis.prob_explanation !== 'undefined') {
                probExpHtml = `<div class="mb-2"><div class="font-bold text-gray-700 mb-1">Probability Explanation</div><div class="text-gray-800 text-base">${analysis.prob_explanation}</div></div>`;
            }
            html = `
                <div class="space-y-8">
                    <div class="flex flex-col items-center mb-4">
                        <div class="text-2xl font-bold text-gray-700 mb-2">Predicted OJT Placement</div>
                        ${analysis.placement ? `<span class="inline-block px-6 py-3 rounded-full font-extrabold text-2xl ${placementColors[analysis.placement] || 'bg-gray-100 text-gray-800'}">${analysis.placement}</span>` : ''}
                    </div>
                    ${reasoningHtml}
                    ${probExpHtml}
                    ${probabilityHtml}
                </div>
            `;
        }
    if ($('#analysisModal').length === 0) {
        $('body').append(`
            <div id="analysisModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50" style="display:none;">
                <div class="bg-white rounded-3xl shadow-2xl p-16 max-w-6xl w-full relative" style="min-width:900px; overflow-x:auto;">
                    <button class="absolute top-6 right-6 text-gray-500 hover:text-gray-700 text-2xl font-bold" id="closeAnalysisModal">&times;</button>
                    <h2 class="text-4xl font-extrabold text-blue-700 mb-8 text-center">Prediction Analysis</h2>
                    <div class="main-dashboard-modal-body" id="analysisModalContent"></div>
                </div>
            </div>
        `);
    }
    $('#analysisModalContent').html(html);
    $('#analysisModal').fadeIn();
});

$(document).on('click', '#closeAnalysisModal', function() {
    $('#analysisModal').fadeOut();
});
});
// --- End Prediction Tab Logic ---

    // --- Report Tab Filters ---
    function loadStudentFilterDropdown() {
        var coordinatorId = $("#hiddencdrid").val();
        $.ajax({
            url: "ajaxhandler/adminDashboardAjax.php",
            type: "POST",
            dataType: "json",
            data: { action: "getAllStudents", coordinatorId: coordinatorId },
            success: function(rv) {
                let options = `<option value=\"all\">All Students</option>`;
                if (rv && rv.data && Array.isArray(rv.data)) {
                    rv.data.forEach(function(stu) {
                        // Use INTERNS_ID for filtering
                        options += `<option value=\"${stu.INTERNS_ID}\">${stu.SURNAME}, ${stu.NAME}</option>`;
                    });
                }
                $("#filterStudent").html(options);
            },
            error: function() {
                $("#filterStudent").html('<option value=\"all\">All Students</option>');
            }
        });
    }

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
                // Use backend filename for image src
                const imgPath = 'uploads/' + response.data.filename + '?t=' + new Date().getTime();
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
        let studentId = $("#filterStudent").val() || "all";
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
                $("#approvedReportsList").html(`
                    <div class="flex justify-center items-center py-12">
                        <div class="animate-spin rounded-full h-12 w-12 border-t-4 border-blue-500 border-b-4 border-gray-200"></div>
                        <span class="ml-4 text-blue-600 text-lg font-semibold">Loading reports...</span>
                    </div>
                `);
            },
            success: function(rv) {
                // Render reports from rv.reports if status is success
                if (rv && rv.status === "success" && Array.isArray(rv.reports) && rv.reports.length > 0) {
                    let html = "";
                    rv.reports.forEach(function(report, reportIdx) {
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
                                            imagesHtml += `<img src='http://localhost/InternConnect/uploads/reports/${img.filename}' alt='${capitalize(day)} activity' class='rounded-lg border border-gray-200 shadow-sm w-full h-24 object-cover mb-2 hover:scale-105 transition'>`;
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
                    $("#approvedReportsList").html(html);
                    $("#approvedReportsList").removeClass("hidden");
                    // Modal JS
                    window.showZoomModal = function(reportIdx, day) {
                        const report = rv.reports[reportIdx];
                        let imagesHtml = "";
                        if (report.imagesPerDay && report.imagesPerDay[day] && report.imagesPerDay[day].length > 0) {
                            report.imagesPerDay[day].forEach(function(img) {
                                imagesHtml += `<img src='http://localhost/InternConnect/uploads/reports/${img.filename}' alt='${capitalize(day)} activity' class='rounded-lg border border-gray-200 shadow w-full h-56 object-cover mb-4'>`;
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
                    $("#approvedReportsList").html("<p>No approved reports found.</p>");
                    $("#approvedReportsList").removeClass("hidden");
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
                $("#approvedReportsList").html("<p>Error loading reports.</p>");
            }
        });
    }


    // Track if report tab has loaded
    let reportTabLoaded = false;
    $(document).on('click', '#reportTab', function() {
        if (!reportTabLoaded) {
            loadStudentFilterDropdown();
            setTimeout(loadApprovedReportsWithFilters, 300); // slight delay to ensure dropdown is populated
            reportTabLoaded = true;
        }
    });

    // Reset flag if user changes filter (forces reload)
    $(document).on('change', '#filterStudent, #filterDate', function() {
        reportTabLoaded = false;
    });

    // Apply filters button
    $(document).on('click', '#applyReportFilters', function() {
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
                        const imgPath = 'uploads/' + coordinator.PROFILE;
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
                            ? `<img src="uploads/${coordinatorData.PROFILE}" alt="Profile" class="profile-image">` 
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
                                        `<img src="uploads/${coordinatorData.PROFILE_PICTURE}" alt="Current Profile" class="current-image">` :
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
                        $('.profile-image, .profile-image-preview').attr('src', 'uploads/' + jsonResponse.filename);
                        
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

    // Function to show change password modal
    function showChangePasswordModal() {
        let html = `
            <div id="changePasswordModal" class="modal" style="display: flex;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Change Password</h2>
                        <button type="button" class="modal-close" id="closeChangePasswordModal">&times;</button>
                    </div>
                        <div class="modal-body">
                            <form id="changePasswordForm">
                                <div class="form-group">
                                    <label for="currentPassword">Current Password:</label>
                                    <input type="password" id="currentPassword" name="current_password" required style="position: relative; z-index: 1501;">
                                </div>
                                <div class="form-group">
                                    <label for="newPassword">New Password:</label>
                                    <input type="password" id="newPassword" name="new_password" required style="position: relative; z-index: 1501;">
                                </div>
                                <div class="form-group">
                                    <label for="confirmPassword">Confirm New Password:</label>
                                    <input type="password" id="confirmPassword" name="confirm_password" required style="position: relative; z-index: 1501;">
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-submit">Change Password</button>
                                    <button type="button" id="cancelPasswordChange" class="btn-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(html);
        $("#changePasswordModal").hide().fadeIn();
    }

    // Modal event handlers
    $(document).on('click', '#closeChangePasswordModal', function() {
        $('#changePasswordModal').fadeOut(function() {
            $(this).remove();
        });
    });

    $(document).on('click', '#cancelPasswordChange', function() {
        $('#changePasswordModal').fadeOut(function() {
            $(this).remove();
        });
    });

    $(document).on('click', '#changePasswordModal', function(e) {
        if (e.target === this) {
            $(this).fadeOut(function() {
                $(this).remove();
            });
        }
    });

    $(document).on('click', '#changePasswordModal .modal-content', function(e) {
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
        $('.btn-submit').prop('disabled', true).text('Verifying...');
        
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
                $('.btn-submit').prop('disabled', false).text('Change Password');
                
                if (response.success) {
                    console.log("Password verified, proceeding with update");
                    // Password verified, now update it
                    $('.btn-submit').text('Updating...');
                    
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
                                $('#changePasswordModal').fadeOut(function() {
                                    $(this).remove();
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
                                });
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
                $('.btn-submit').prop('disabled', false).text('Change Password');
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
    function loadAllStudentsData() {
        // Close other forms
        $('#studentFormContainer').fadeOut(function() {
            $('#studentForm')[0].reset();
            $('#studentForm input, #studentForm select').prop('disabled', false);
        });
        $('#addHTEFormContainer').hide();
    $('#sessionFormContainer').fadeOut();
        $('#deleteHTEFormContainer').hide();
        $('#deleteSessionFormContainer').hide();
        $('#deleteStudentFormContainer').hide();

        let cdrid = $("#hiddencdrid").val();

        if (!cdrid) {
            alert("Coordinator ID not found.");
            return;
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
                    displayAllStudents(response.data);
                } else {
                    console.error('Error in response:', response.message);
                    alert("Error: " + (response.message || "Unknown error occurred."));
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", status, error);
                alert("Error fetching students. Please check the console for more information.");
            }
        });
    }

    // View All Students button click handler
    $(document).on('click', '#btnViewAllStudents', function() {
        loadAllStudentsData();
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
        x=x+ `<option value=${cs['ID']}>${cs['YEAR']+" "+cs['TERM']}</option>`;
    }

    return x;
}

function loadSeassions()
{
    $.ajax({
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: {action: "getSession" },
        beforeSend: function() {
            // para mo show ni siya loading

        },
        success: function(rv) {
            {
                //alert(JSON.stringify(rv));
                let x=getSessionHTML(rv);
                $("#ddlclass").html(x);

                // Auto-select the first session if available
                if (rv && rv.length > 0) {
                    $("#ddlclass").val(rv[0].ID);
                    // Trigger the change event to load HTEs for the selected session
                    $("#ddlclass").trigger("change");
                }

            }
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
    return x;
}

function fetchTHE(cdrid,sessionid)
{   console.log("Fetching THE for session:", sessionid);
    //kuhaon tanan mga H.T.E nga gi handle sa current login coordinator
    //didto sa database gamit ang ajax call
    $.ajax({
        
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: {cdrid:cdrid,sessionid:sessionid,action:"getHTE"},
        beforeSend: function(e) {
            // para mo show ni siya loading

        },
        success: function(rv) {
            {
                // console.log(rv);
                // alert(JSON.stringify(rv));
                let  x=getHTEHTML(rv);
                $("#classlistarea").html(x);
            }
        },
        error: function(e) {
            console.error("Error fetching THE:", e);
        }
    });

}

 function getClassdetailsAreaHTML(building)
{
    let dobj=new Date();
    let ondate=`2024-09-11`;
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
    ondate=year+"-"+month+"-"+day;
    // alert(ondate);
    let logoHtml = '';
    if (building['LOGO']) {
        logoHtml = `<img src='uploads/hte_logos/${building['LOGO']}' alt='Company Logo' class='w-20 h-20 object-cover rounded-full border-2 border-blue-300 shadow mb-2 bg-white' />`;
    } else {
        logoHtml = `<div class='w-20 h-20 flex items-center justify-center bg-gray-100 rounded-full border-2 border-gray-300 text-gray-400 mb-2'>No Logo</div>`;
    }
    let x = `<div class="company-card flex flex-col items-center p-4 bg-white rounded-xl shadow-md border border-gray-200">
                ${logoHtml}
                <div class="font-semibold text-lg text-gray-800 mb-1">${building['NAME']}</div>
                <div class="text-sm text-gray-500 mb-2">${building['INDUSTRY']}</div>
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                    <input type="date" value='${ondate}' id='dtpondate' class='border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300' />
                    <span class="icon-calendar"></span>
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
        return `<div class="text-gray-500 text-center py-8">No students found.</div>`;
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
    $.ajax({
        url: "ajaxhandler/attendanceAJAX.php",
        type: "POST",
        dataType: "json",
        data: {cdrid:cdrid,ondate:ondate,sessionid:sessionid,classid:classid, action: "getStudentList" },
        beforeSend: function() {
            // para mo show ni siya loading

        },
        success: function(rv) {
            // console.log("fetchStudentList success: " + JSON.stringify(rv));
            {
                // alert(JSON.stringify(rv))
                console.log('Student data:', rv);
                let x = getStudentListHTML(rv);
                $("#studentlistarea").html(x);
            }
        },
        error: function(xhr, status, error) {
            console.log("fetchStudentList error: " + error);
        }
    });
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
                    document.location.replace("index.php");
                },
                error: function(xhr, status, error) {
                    alert("Something went wrong!")
                }
            }
        );

    });



    loadSeassions();

    $(document).on("change", "#ddlclass", function(e) {
        currentSessionId = $(this).val();
        $("#hiddenSelectedSessionId").val(currentSessionId);
        $("#classlistarea").html(``);
        $("#classdetailsarea").html(``);
        if (currentSessionId != -1) {
            let cdrid = $("#hiddencdrid").val();
            fetchTHE(cdrid, currentSessionId); // Fetch HTEs for the selected session
            // Display student list area with only Add HTE button enabled
            $("#studentlistarea").html(getStudentListHTML([]));
        } else {
            $("#studentlistarea").html(``);
        }
    });

    $(document).on("change", "#company-select", function(e) {
        // Hide control panel forms when showing student list
        $('.form-container').slideUp();
        
        let selectedOption = $(this).find("option:selected");
        let building = selectedOption.data("building");
        if (!building) return;

        currentHteId = building.HTE_ID;
        $("#hiddenSelectedHteID").val(currentHteId);
        let x = getClassdetailsAreaHTML(building);
        $("#classdetailsarea").html(x);
        let cdrid = $("#hiddencdrid").val();
        let ondate = $("#dtpondate").val();
        if (currentSessionId != -1) {
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
        if(sessionid!=-1)
        {
        
            fetchStudentList(sessionid,classid,cdrid,ondate);
        }
    });
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
                        } else {
                            // Only show error if no students were inserted
                            if (!response.inserted || response.inserted === 0) {
                                alert(response.message); // Show error message only if nothing was inserted
                            }
                        }
                        $("#studentForm").data('submitted', false);
                    },
                    error: function(xhr, status, error) {
                        console.error("Error uploading CSV:", error);
                        if (xhr && xhr.responseText) {
                            console.log("CSV upload error response:", xhr.responseText);
                        }
                        alert("An error occurred while uploading the CSV file. Please check the console for details.");
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
    

        $(document).on("submit", "#hteForm", function(e) {
            e.preventDefault();
            var form = this;
            var formData = new FormData(form);
            formData.append('action', 'addHTE');
            formData.append('sessionId', currentSessionId);

            // Debug: log FormData contents
            if (formData.has('LOGO')) {
                var file = formData.get('LOGO');
                if (file && file.name) {
                    console.log('LOGO file selected:', file.name, 'size:', file.size);
                } else {
                    console.warn('LOGO file not selected or empty');
                }
            } else {
                console.warn('LOGO field not found in FormData');
            }
            // Log all FormData key/value pairs
            for (var pair of formData.entries()) {
                console.log(pair[0]+ ':', pair[1]);
            }

            $.ajax({
                url: "ajaxhandler/attendanceAJAX.php",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(response) {
                    console.log("Server Response:", response); // Log the raw response
                    if (response.success) {
                        alert("HTE added successfully! ");
                        $("#addHTEForm").hide();
                    } else {
                        alert("Error adding HTE: " + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", status, error);
                    console.log("Response text:", xhr.responseText); // Log the response text for debugging
                    alert("Error adding HTE: " + error + "\nCheck console for more details.");
                }
            });
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

// Fetch and display all evaluation questions
function loadEvaluationQuestions() {
    $.ajax({
        url: 'ajaxhandler/coordinatorEvaluationQuestionsAjax.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.questions) {
                let html = '<table class="table"><thead><tr><th>Category</th><th>Question</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                response.questions.forEach(function(q) {
                    html += `<tr>
                        <td>${q.category}</td>
                        <td>${q.question_text}</td>
                        <td>${q.status}</td>
                        <td>
                            <button class="btn-edit-question" data-id="${q.question_id}">Edit</button>
                            <button class="btn-deactivate-question" data-id="${q.question_id}">Deactivate</button>
                        </td>
                    </tr>`;
                });
                html += '</tbody></table>';
                $('#evaluationQuestionsList').html(html);
            } else {
                $('#evaluationQuestionsList').html('<p>No questions found.</p>');
            }
        },
        error: function() {
            $('#evaluationQuestionsList').html('<p>Error loading questions.</p>');
        }
    });
}

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
                loadEvaluationQuestions();
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
                loadEvaluationQuestions();
            } else {
                alert('Failed to update question.');
            }
        },
        error: function() {
            alert('Error updating question.');
        }
    });
});

// Initial load
$(document).ready(function() {
    loadEvaluationQuestions();
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
                    const activeQuestions = response.questions.filter(q => q.status === 'active');
                    // Collect unique categories
                    const categories = [...new Set(activeQuestions.map(q => q.category ? q.category.trim() : 'Other'))];
                    let html = `<div class='flex w-full'>`;
                    // Left column (category selector)
                    html += `<div class='left-col w-1/5 max-w-xs pr-4'>`;
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
                    html += `<div class='right-col w-4/5 pl-4'>`;
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
                                } else {
                                    $status.text('Failed to save changes.').removeClass('bg-blue-100 text-blue-700 bg-green-100 text-green-700').addClass('bg-red-100 text-red-700');
                                }
                            },
                            error: function() {
                                $status.text('Error saving changes.').removeClass('bg-blue-100 text-blue-700 bg-green-100 text-green-700').addClass('bg-red-100 text-red-700');
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
    });
    $('#reviewTabBtn').click(function() {
        $(this).addClass('active');
        $('#evalQuestionsTabBtn').removeClass('active');
        $('#rateTabBtn').removeClass('active');
        $('#reviewTabContent').addClass('active');
        $('#evalQuestionsTabContent').removeClass('active');
        $('#rateTabContent').removeClass('active');
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
    let selectedStudentId = null;

    function loadPreassessmentStudentList() {
        // Fetch all students eligible for rating (AJAX or from global)
        $.ajax({
            url: 'ajaxhandler/studentDashboardAjax.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'getStudentsForPreassessment' },
            success: function(response) {
                if (response.success && Array.isArray(response.students)) {
                    allStudents = response.students;
                    renderStudentList(allStudents);
                } else {
                    $('#studentListPanel').html('<div class="preassessment-message">No students found.</div>');
                }
            },
            error: function() {
                $('#studentListPanel').html('<div class="preassessment-message">Error loading students.</div>');
            }
        });
    }

    function renderStudentList(students) {
        let sorted = students.slice().sort((a, b) => {
            let aId = (a.STUDENT_ID || a.student_id || '').toString();
            let bId = (b.STUDENT_ID || b.student_id || '').toString();
            return aId.localeCompare(bId);
        });
        let studentListHtml = '';
        sorted.forEach(function(student) {
            let displayId = student.STUDENT_ID || student.student_id || 'Unknown ID';
            studentListHtml += `
                <div class="preassessment-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${student.id === selectedStudentId ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${student.id}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                    </span>
                    <span class="truncate">${displayId}</span>
                </div>
            `;
        });
        // Build the 20/80 column layout
        let html = `<div class='flex w-full'>`;
        html += `<div class='left-col w-1/5 max-w-xs pr-4'>`;
    html += `<div class='mb-4'><input type='text' id='rateStudentSearch' placeholder='Search student' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'></div>`;
    html += `<div id='studentListPanel' class='overflow-y-auto max-h-[420px] flex flex-col gap-1'>${studentListHtml}</div>`;
        html += `</div>`;
        html += `<div class='right-col w-4/5 pl-4'>`;
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
    }

    // Search filter for student list
    $(document).on('input', '#rateStudentSearch', function() {
        const query = $(this).val().trim();
        // Remove any previous invalid message
        $('#studentSearchInvalidMsg').remove();
        if (query.length > 0 && /[^0-9]/.test(query)) {
            // Show invalid input message below the search bar
            $(this).after('<div id="studentSearchInvalidMsg" class="text-red-600 text-sm mt-1">Invalid input: Only numbers allowed</div>');
            return;
        }
        let filtered = allStudents.filter(s => {
            let idStr = (s.STUDENT_ID || s.student_id || '').toString();
            return idStr.includes(query);
        });
    renderStudentList(filtered);
    // Restore the input value and focus after re-render
    const $input = $('#rateStudentSearch');
    $input.val(query);
    $input.focus();
    });

    // Handle student selection
    $(document).on('click', '.preassessment-student-item', function() {
    selectedStudentId = $(this).data('studentid');
    // Map INTERNS_ID to STUDENT_ID with debug
    let selectedStudent = allStudents.find(s => s.id == selectedStudentId);
    let studentDbId = selectedStudent && selectedStudent.STUDENT_ID ? selectedStudent.STUDENT_ID : selectedStudentId;
    console.log('[Pre-Assessment] Selected INTERNS_ID:', selectedStudentId, '| Mapped STUDENT_ID:', studentDbId, '| Student object:', selectedStudent);
    console.log('[Pre-Assessment] allStudents array:', allStudents);
    // Fetch and show grades modal using STUDENT_ID
    $.ajax({
        url: 'ajaxhandler/preAssessmentGradesAjax.php',
        type: 'POST',
        dataType: 'json',
        data: { student_id: studentDbId },
        success: function(response) {
            if (response.success && response.grades) {
                let grades = response.grades;
                let gradeRows = '';
                // Only show subject grades
                const subjectKeys = [
                    'CC 102','CC 103','PF 101','CC 104','IPT 101','IPT 102','CC 106','CC 105',
                    'IM 101','IM 102','HCI 101','HCI 102','WS 101','NET 101','NET 102',
                    'IAS 101','IAS 102','CAP 101','CAP 102','SP 101'
                ];
                subjectKeys.forEach(function(key) {
                    if (grades.hasOwnProperty(key)) {
                        gradeRows += `<tr><td class='px-3 py-2 font-semibold text-gray-700'>${key}</td><td class='px-3 py-2 text-blue-700'>${grades[key]}</td></tr>`;
                    }
                });
                // Find the top and left position of the student list
                let $studentList = $('.preassessment-student-list');
                let offset = $studentList.length ? $studentList.offset() : { top: 95, left: 32 };
                // Shift modal right by 32px to fully cover scrollbar
                let modalLeft = offset.left + 55;
                let modalHtml = `
                <div id='gradesModal' style='position:absolute;top:${offset.top}px;left:${modalLeft}px;max-width:330px;max-height:80vh;z-index:10;' class='bg-gradient-to-br from-white via-blue-50 to-blue-100 shadow-lg flex flex-col rounded-2xl border border-blue-100 animate-slidein pointer-events-auto'>
                    <div class='flex justify-between items-center px-6 py-4 border-b border-blue-100 rounded-t-2xl'>
                        <div class='font-extrabold text-lg text-blue-700 tracking-wide'>Grades for Student ID: <span class='text-blue-900'>${grades.STUDENT_ID}</span></div>
                        <button id='closeGradesModal' class='text-gray-400 hover:text-blue-700 text-2xl font-bold transition'>&times;</button>
                    </div>
                    <div class='overflow-y-auto p-6'>
                        <table class='w-full text-left rounded-xl border border-blue-100 bg-white shadow-md'>
                            <thead><tr class='bg-blue-50 text-blue-700'><th class='px-3 py-2 text-base font-semibold'>Subject</th><th class='px-3 py-2 text-base font-semibold'>Grade</th></tr></thead>
                            <tbody>${gradeRows}</tbody>
                        </table>
                    </div>
                </div>
                <style>
                @keyframes slidein { from { transform: translateX(-40px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
                .animate-slidein { animation: slidein 0.2s cubic-bezier(.4,0,.2,1); }
                </style>`;
                // Remove any existing modal, then show new
                $('#gradesModal').remove();
                $('body').append(modalHtml);
                // Remove any existing modal, then show new
                $('#gradesModal').remove();
                $('body').append(modalHtml);
                // Remove any existing modal and show new
                $('#gradesModal').remove();
                $('body').append(modalHtml);
            }
        }
    });
    // Re-render student list to update highlight
    renderStudentList(allStudents);
    loadStudentEvaluation(selectedStudentId);
    // Close grades modal handler
    $(document).on('click', '#closeGradesModal', function() {
    $('#gradesModal').remove();
    });
    });

    function loadStudentEvaluation(studentId) {
        // Fetch evaluation for selected student
        $.ajax({
            url: 'ajaxhandler/studentDashboardAjax.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'getPreassessmentEvaluation', studentId: studentId },
            success: function(response) {
                if (response.success) {
                    if (response.isRated) {
                        $('#rateEvalList').html('<div class="preassessment-message">This student has been rated. Check the Review tab.</div>');
                    } else if (response.evaluations && response.evaluations.length > 0) {
                        // Modern card layout for rating UI
                        let evalHtml = '';
                        response.evaluations.forEach(function(ev) {
                            evalHtml += `
                            <div class="student-eval-block bg-white rounded-xl shadow p-6 mb-8 border border-gray-200">
                                <div class="flex flex-col md:flex-row gap-8 items-stretch mb-4">
                                    <div class="w-full md:w-2/5 flex flex-col justify-center mb-4 md:mb-0">
                                        <div class="eval-question-label text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Question</div>
                                        <div class="eval-question-box text-base font-bold text-gray-900 bg-blue-50 rounded-lg p-3 border border-blue-200" style="max-height:120px;overflow-y:auto;">${ev.question_text}</div>
                                    </div>
                                    <div class="w-full md:w-3/5 flex flex-col justify-center">
                                        <div class="eval-answer-label text-xs font-semibold text-green-700 uppercase tracking-wide mb-1">Student Answer</div>
                                        <div class="eval-answer-box text-base text-gray-800 bg-green-50 rounded-lg p-3 border border-green-200">${ev.answer}</div>
                                    </div>
                                </div>
                                <div class="eval-rating-section flex flex-col items-start gap-2 mt-6">
                                    <label class="font-medium text-gray-700 mb-2">Rate this answer:</label>
                                    <div class="flex gap-6">
                                        ${[5,4,3,2,1].map(rating => `
                                            <label class="flex flex-col items-center cursor-pointer">
                                                <input type="radio" name="likert_${studentId}_${ev.id}" value="${rating}" class="hidden peer">
                                                <span class="w-9 h-9 flex items-center justify-center rounded-full border-2 border-blue-400 peer-checked:bg-blue-600 peer-checked:text-white text-blue-600 font-bold text-lg mb-1 transition">${rating}</span>
                                                <span class="text-xs text-gray-500">${rating}</span>
                                            </label>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                            `;
                        });
                        evalHtml += `<div class="flex justify-center mt-10">
                            <button class="btn-save-all-ratings px-8 py-2 text-lg font-semibold bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition-all duration-150" data-studentid="${studentId}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Save All Ratings
                            </button>
                        </div>`;
                        $('#rateEvalList').html(evalHtml);
                        // Make the right column scrollable
                        $('#rateEvalList').css({
                            'max-height': 'calc(100vh - 260px)', // leave more space for button
                            'overflow-y': 'auto',
                            'padding-right': '8px'
                        });
                    } else {
                        $('#rateEvalList').html('<div class="preassessment-message">Student has not submitted an evaluation yet.</div>');
                    }
                } else {
                    $('#rateEvalList').html('<div class="preassessment-message">Error loading evaluation.</div>');
                }
            },
            error: function() {
                $('#rateEvalList').html('<div class="preassessment-message">Error loading evaluation.</div>');
            }
        });
    }

    // Initial load for Pre-Assessment tab
    $(document).ready(function() {
        if ($('#rateTabContent').length) {
            loadPreassessmentStudentList();
        }
    });

    // --- Review Tab: Populate student list and handle selection ---
    let allReviewStudents = [];
    let selectedReviewStudentId = null;

    function loadReviewStudentList() {
        // Fetch all students eligible for review (same as pre-assessment)
        $.ajax({
            url: 'ajaxhandler/studentDashboardAjax.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'getStudentsForPreassessment' },
            success: function(response) {
                if (response.success && Array.isArray(response.students)) {
                    // Map students to use STUDENT_ID for review selection
                    allReviewStudents = response.students.map(function(student) {
                        return {
                            id: student.STUDENT_ID || student.id, // Use STUDENT_ID if available
                            name: student.name || (student.SURNAME ? student.SURNAME + ', ' + student.NAME : student.NAME)
                        };
                    });
                    renderReviewStudentList(allReviewStudents);
                } else {
                    $('#reviewStudentListPanel').html('<div class="review-message">No students found.</div>');
                }
            },
            error: function() {
                $('#reviewStudentListPanel').html('<div class="review-message">Error loading students.</div>');
            }
        });
    }

    function renderReviewStudentList(students) {
        let sorted = students.slice().sort((a, b) => {
            let aId = (a.STUDENT_ID || a.student_id || a.id || '').toString();
            let bId = (b.STUDENT_ID || b.student_id || b.id || '').toString();
            return aId.localeCompare(bId);
        });
        let studentListHtml = '';
        sorted.forEach(function(student) {
            let displayName = student.name || student.NAME || student.full_name || 'Unknown Name';
            studentListHtml += `
                <div class="review-student-item flex items-center gap-3 px-4 py-3 mb-2 rounded-lg cursor-pointer transition-all duration-150 bg-white shadow-sm hover:bg-blue-50 border border-transparent ${student.id === selectedReviewStudentId ? 'bg-blue-100 border-blue-400 font-semibold text-blue-700' : 'text-gray-800'}" data-studentid="${student.id}">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 text-blue-700 font-bold text-lg mr-2">
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.657 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z' /></svg>
                    </span>
                    <span class="truncate">${displayName}</span>
                </div>
            `;
        });
        // 20/80 layout for Review tab
        let html = `<div class='flex w-full'>`;
        html += `<div class='left-col w-1/5 max-w-xs pr-4'>`;
        html += `<div class='mb-4'><input type='text' id='reviewStudentSearch' placeholder='Search student' class='w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200'></div>`;
        html += `<div id='reviewStudentListPanel' class='overflow-y-auto max-h-[420px] flex flex-col gap-1'>${studentListHtml}</div>`;
        html += `</div>`;
        html += `<div class='right-col w-4/5 pl-4'>`;
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
            html += `<div id='reviewedEvalList' class='space-y-4 max-h-[700px] overflow-y-auto pr-2'></div>`;
        }
        html += `</div>`;
        html += `</div>`;
        $('#reviewTabContent').html(html);
    }

    // Search filter for review student list
    $(document).on('input', '#reviewStudentSearch', function() {
        const query = $(this).val().trim().toLowerCase();
        let filtered = allReviewStudents.filter(s => {
            let displayId = (s.STUDENT_ID || s.student_id || s.id || '').toString().toLowerCase();
            return displayId.includes(query);
        });
        renderReviewStudentList(filtered);
    });

    // Handle student selection in Review tab
    $(document).on('click', '.review-student-item', function() {
    selectedReviewStudentId = $(this).data('studentid');
    renderReviewStudentList(allReviewStudents);
    loadReviewedEvaluation(selectedReviewStudentId);
    });

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
                    // Render reviewed evaluation cards
                    let evalHtml = '';
                    response.evaluations.forEach(function(ev) {
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
                                                <th colspan="5" class="bg-blue-50 text-blue-700 text-sm font-bold px-4 py-2 text-center rounded-t">Table Rating</th>
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

    // Initial load for Review tab student list
    $(document).ready(function() {
        if ($('#reviewTabContent').length) {
            loadReviewStudentList();
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