// Report Management
let currentReport = null;

// Dynamic base URL function to handle different environments
function getBaseUrl() {
    const protocol = window.location.protocol;
    const host = window.location.host;
    const pathname = window.location.pathname;
    
    // Extract the base path (e.g., "/InternConnect/")
    const pathArray = pathname.split('/');
    const basePath = pathArray.length > 1 && pathArray[1] ? '/' + pathArray[1] + '/' : '/';
    
    return protocol + '//' + host + basePath;
}

$(document).ready(function() {
    // Initialize reports when the tab is clicked
    $('#reportsTab').click(function() {
        loadReports();
    });

    // Filter handling
    $('#studentFilter, #statusFilter').on('change', function() {
        filterReports();
    });

    // Report card action handlers
    $(document).on('click', '.btn-view', function() {
        const reportId = $(this).closest('.report-card').data('report-id');
        openReportPreview(reportId);
    });

    // Modal controls
    $('.modal .close, #cancelReturnBtn').click(function() {
        closeModals();
    });

    // Approve and return buttons
    $('#approveReportBtn').click(function() {
        if (currentReport) {
            approveReport(currentReport.reportId);
        }
    });

    $('#returnReportBtn').click(function() {
        if (currentReport) {
            $('#reportPreviewModal').hide();
            $('#returnReportModal').show();
        }
    });

    $('#confirmReturnBtn').click(function() {
        const returnReason = $('#returnReason').val().trim();
        if (!returnReason) {
            alert('Please provide a reason for returning the report.');
            return;
        }
        if (currentReport) {
            returnReport(currentReport.reportId, returnReason);
        }
    });
});

function loadReports() {
    $.ajax({
        url: 'ajaxhandler/adminDashboardAjax.php',
        type: 'POST',
        data: {
            action: 'getReports'
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayReports(response.reports);
            } else {
                console.error('Error loading reports:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
}

function displayReports(reports) {
    const pendingGrid = $('#pendingReportsGrid');
    const processedGrid = $('#processedReportsGrid');
    
    pendingGrid.empty();
    processedGrid.empty();

    reports.forEach(report => {
        const reportCard = createReportCard(report);
        if (report.approval_status === 'pending') {
            pendingGrid.append(reportCard);
        } else {
            processedGrid.append(reportCard);
        }
    });

    // Show/hide sections based on content
    $('#pendingReports').toggle(pendingGrid.children().length > 0);
    $('#processedReports').toggle(processedGrid.children().length > 0);
}

function createReportCard(report) {
    const statusClass = {
        'pending': 'pending',
        'approved': 'approved',
        'returned': 'returned'
    }[report.approval_status];

    const statusText = {
        'pending': 'Pending Review',
        'approved': 'Approved',
        'returned': 'Returned'
    }[report.approval_status];

    return $(`
        <div class="report-card" data-report-id="${report.report_id}">
            <div class="report-header">
                <div class="student-info">
                    <h4 class="student-name">${report.student_name}</h4>
                    <div class="report-date">Week ${report.week_number} (${report.week_start} - ${report.week_end})</div>
                </div>
                <span class="report-status ${statusClass}">${statusText}</span>
            </div>
            <div class="report-preview">
                ${report.monday_description || 'No description available.'}
            </div>
            <div class="report-actions">
                <button class="btn btn-view">
                    <i class="fas fa-eye"></i> View Report
                </button>
            </div>
        </div>
    `);
}

function openReportPreview(reportId) {
    $.ajax({
        url: 'ajaxhandler/adminDashboardAjax.php',
        type: 'POST',
        data: {
            action: 'getReportDetails',
            reportId: reportId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                currentReport = response.report;
                displayReportPreview(response.report);
                $('#reportPreviewModal').show();
            } else {
                console.error('Error loading report details:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
}

function displayReportPreview(report) {
    const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    let content = `
        <div class="report-content">
            <div class="student-info">
                <h3>${report.student_name}</h3>
                <p>Week ${report.week_number} (${report.week_start} - ${report.week_end})</p>
            </div>
    `;

    days.forEach(day => {
        const description = report[`${day}_description`];
        const images = report.imagesPerDay?.[day] || [];
        
        content += `
            <div class="day-section">
                <h4>${day.charAt(0).toUpperCase() + day.slice(1)}</h4>
                <p>${description || 'No description provided.'}</p>
                ${images.length > 0 ? `
                    <div class="day-images">
                        ${images.map(img => `
                            <img src="${getBaseUrl()}uploads/reports/${img.filename}" alt="Activity image" onclick="openImagePreview(this.src)">
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    });

    content += '</div>';
    
    // Show/hide approve/return buttons based on status
    if (report.approval_status === 'pending') {
        $('#approveReportBtn, #returnReportBtn').show();
    } else {
        $('#approveReportBtn, #returnReportBtn').hide();
    }

    $('#reportContent').html(content);
}

function approveReport(reportId) {
    $.ajax({
        url: 'ajaxhandler/approveReportAjax.php',
        type: 'POST',
        data: {
            action: 'approveReport',
            reportId: reportId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                closeModals();
                loadReports();
            } else {
                alert('Error approving report: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
            alert('Error approving report. Please try again.');
        }
    });
}

function returnReport(reportId, returnReason) {
    $.ajax({
        url: 'ajaxhandler/approveReportAjax.php',
        type: 'POST',
        data: {
            action: 'returnReport',
            reportId: reportId,
            returnReason: returnReason
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                closeModals();
                loadReports();
            } else {
                alert('Error returning report: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
            alert('Error returning report. Please try again.');
        }
    });
}

function filterReports() {
    const studentFilter = $('#studentFilter').val().toLowerCase();
    const statusFilter = $('#statusFilter').val();

    $('.report-card').each(function() {
        const card = $(this);
        const studentName = card.find('.student-name').text().toLowerCase();
        const status = card.find('.report-status').text().toLowerCase();

        const matchesStudent = !studentFilter || studentName.includes(studentFilter);
        const matchesStatus = statusFilter === 'all' || status === statusFilter;

        card.toggle(matchesStudent && matchesStatus);
    });

    // Update section visibility
    ['pending', 'processed'].forEach(section => {
        const hasVisibleCards = $(`#${section}ReportsGrid .report-card:visible`).length > 0;
        $(`#${section}Reports`).toggle(hasVisibleCards);
    });
}

function closeModals() {
    $('.modal').hide();
    $('#returnReason').val('');
    currentReport = null;
}

// Image preview functionality (optional)
function openImagePreview(src) {
    window.open(src, '_blank');
}