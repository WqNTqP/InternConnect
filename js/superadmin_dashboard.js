$(document).ready(function() {
    // Add ready class to html to show content only when everything is initialized
    document.documentElement.classList.add('ready');
    
    // User dropdown functionality
    const userProfile = $('.user-profile');
    const userDropdown = $('.user-dropdown');
    
    // Toggle dropdown when clicking the user profile
    userProfile.on('click', function(e) {
        e.stopPropagation();
        userDropdown.toggleClass('show');
    });
    
    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.user-dropdown').length) {
            userDropdown.removeClass('show');
        }
    });
    
    // Prevent dropdown from closing when clicking inside it
    userDropdown.on('click', function(e) {
        e.stopPropagation();
    });

    // Handle logout button click
    $('#logoutBtn').on('click', function(e) {
        e.preventDefault();
        window.location.href = 'ajaxhandler/superadminLogout.php';
    });
    
    // Sidebar functionality
    const sidebarToggle = $('.sidebar-toggle');
    const sidebar = $('.sidebar');
    const contentArea = $('.content-area');
    
    // Function to close sidebar
    function closeSidebar() {
        sidebar.addClass('closed');
        contentArea.addClass('full-width');
        localStorage.setItem('sidebarClosed', 'true');
    }
    
    // Function to open sidebar
    function openSidebar() {
        sidebar.removeClass('closed');
        contentArea.removeClass('full-width');
        localStorage.setItem('sidebarClosed', 'false');
    }
    
    // Toggle sidebar when button is clicked
    sidebarToggle.on('click', function(e) {
        e.stopPropagation(); // Prevent click from bubbling to document
        if (sidebar.hasClass('closed')) {
            openSidebar();
        } else {
            closeSidebar();
        }
    });
    
    // Close sidebar when clicking outside
    $(document).on('click', function(e) {
        // If sidebar is open and click is not on sidebar or toggle button
        if (!sidebar.hasClass('closed') && 
            !$(e.target).closest('.sidebar').length && 
            !$(e.target).closest('.sidebar-toggle').length) {
            closeSidebar();
        }
    });
    
    // Prevent clicks inside sidebar from closing it
    sidebar.on('click', function(e) {
        e.stopPropagation();
    });
    
    // Check localStorage for saved state and initialize sidebar
    const sidebarClosed = localStorage.getItem('sidebarClosed');
    // If there's no stored state, close the sidebar by default
    if (sidebarClosed === null) {
        sidebar.addClass('closed');
        contentArea.addClass('full-width');
        localStorage.setItem('sidebarClosed', 'true');
    } else if (sidebarClosed === 'true') {
        // If it was previously closed, keep it closed
        sidebar.addClass('closed');
        contentArea.addClass('full-width');
    }

    // Show the modal when the "Add New Coordinator/Admin" button is clicked
    $('#btnAddCoordinator').on('click', function() {
        $('#addCoordinatorModal').addClass('show').css('display', 'flex');
        $('body').css('overflow', 'hidden'); // Prevent body scroll when modal is open
        fetchHTEOptions(); // Fetch HTE options when opening the modal
    });

    // Handle role selection change
    $('#role').on('change', function() {
        const selectedRole = $(this).val();
        
        // Hide both containers initially
        $('#hteDropdownContainer').hide();
        $('#superadminWarning').hide();
        
        if (selectedRole === 'ADMIN') {
            $('#hteDropdownContainer').show();
        } else if (selectedRole === 'SUPERADMIN') {
            // Show warning for superadmin creation
            if (!confirm('Creating a new Superadmin will grant them full system access. Are you sure you want to proceed?')) {
                $(this).val(''); // Reset selection if not confirmed
                return;
            }
        }
    });

    // Fetch HTE options for the dropdown
    function fetchHTEOptions() {
        $.ajax({
            url: "ajaxhandler/addCoordinatorAjax.php",
            type: "POST",
            dataType: "json",
            data: { action: "getHTEs" },
            success: function(response) {
                if (response.success) {
                    const hteSelect = $("#hteSelect");
                    hteSelect.empty();
                    hteSelect.append('<option value="">Select HTE</option>');
                    response.htes.forEach(hte => {
                        hteSelect.append(`<option value="${hte.HTE_ID}">${hte.NAME}</option>`);
                    });
                } else {
                    console.error("Error fetching HTEs:", response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching HTEs:", error);
            }
        });
    }

    // Handle form submission for adding a coordinator/admin
    $('#addCoordinatorForm').on('submit', function(event) {
        event.preventDefault();

        // Validate the form
        const role = $('#role').val();
        if (role === 'ADMIN' && !$('#hteSelect').val()) {
            alert('Please select an HTE for the admin.');
            return;
        }

        const formData = $(this).serialize();

        $.ajax({
            type: 'POST',
            url: 'ajaxhandler/addCoordinatorAjax.php',
            data: formData,
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    $('#addCoordinatorModal').removeClass('show').css('display', 'none');
                    $('body').css('overflow', '');
                    $('#addCoordinatorForm')[0].reset();
                    location.reload();
                } else {
                    alert("Error: " + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error details:", xhr.responseText);
                alert('Error adding coordinator. Please try again.');
            }
        });
    });

    // Close modal functionality
    $('#closeModal').on('click', function() {
        $('#addCoordinatorModal').removeClass('show').css('display', 'none');
        $('body').css('overflow', '');
        $('#addCoordinatorForm')[0].reset();
        $('#hteDropdownContainer').hide();
    });

    // Close modal when clicking outside
    $('#addCoordinatorModal').on('click', function(event) {
        if (event.target === this) {
            $(this).removeClass('show').css('display', 'none');
            $('body').css('overflow', '');
            $('#addCoordinatorForm')[0].reset();
            $('#hteDropdownContainer').hide();
        }
    });
    
    // Close modal with Escape key
    $(document).on('keydown', function(event) {
        if (event.key === 'Escape' && $('#addCoordinatorModal').hasClass('show')) {
            $('#addCoordinatorModal').removeClass('show').css('display', 'none');
            $('body').css('overflow', '');
            $('#addCoordinatorForm')[0].reset();
            $('#hteDropdownContainer').hide();
        }
    });

    // Handle deletion of a coordinator
    window.deleteCoordinator = function(coordinatorId) {
        if (confirm('Are you sure you want to delete this coordinator?')) {
            $.ajax({
                url: "ajaxhandler/delete_coordinatorAjax.php",
                type: "POST",
                data: { id: coordinatorId },
                success: function(response) {
                    console.log("Response:", JSON.stringify(response, null, 2)); // Log the response object
                    if (response.success) {
                        alert("Coordinator deleted successfully!");
                        location.reload(); // Reload the page to see the updated list
                    } else {
                        alert("Error: " + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error:", error);
                    alert("An error occurred while deleting the coordinator.");
                }
            });
        }
    };

    // Close modal functionality
    $('#closeModal').on('click', function() {
        $('#addCoordinatorModal').hide(); // Hide the modal
        $('#addCoordinatorForm')[0].reset(); // Reset the form
        $("#hteDropdownContainer").hide(); // Hide HTE dropdown on modal close
    });

    // Event listener for role change to show/hide HTE dropdown
    $(document).on("change", "#role", function() {
        const selectedRole = $(this).val();
        if (selectedRole === "ADMIN") {
            $("#hteDropdownContainer").show(); // Show HTE dropdown
            fetchHTEOptions(); // Fetch and populate HTE options
        } else {
            $("#hteDropdownContainer").hide(); // Hide HTE dropdown
        }
    });

    // Function to fetch HTE options
    function fetchHTEOptions() {
        $.ajax({
            url: "ajaxhandler/addCoordinatorAjax.php", // Adjust the URL as needed
            type: "POST",
            dataType: "json",
            data: { action: "getHTEs" }, // Specify the action to fetch HTEs
            success: function(response) {
                if (response.success) {
                    const hteSelect = $("#hteSelect");
                    hteSelect.empty(); // Clear existing options
                    response.htes.forEach(hte => {
                        hteSelect.append(`<option value="${hte.HTE_ID}">${hte.NAME}</option>`); // Populate HTE options
                    });
                } else {
                    alert("Error fetching HTEs: " + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching HTEs:", error);
                alert("An error occurred while fetching HTEs. Please check the console for more details.");
            }
        });
    }

    $(document).on("click","#btnLogout",function(ee)
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
});