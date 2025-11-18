function tryLogin() {
    let un = $("#txtAdminEmail").val();
    let pw = $("#txtAdminPassword").val();
    
    if (un.trim() !== "" && pw.trim() !== "") {
        // Trigger loading state
        window.setAdminLoading(true);
        
        $.ajax({
            url: "ajaxhandler/adminLoginAjax.php",
            type: "POST",
            dataType: "json",
            data: { user_name: un, password: pw, action: "verifyUser" },
            beforeSend: function() {
                $("#diverror").hide();
                $("#diverror").removeClass("block").addClass("hidden");
            },
            success: function(rv) {
                // Hide loading state
                window.setAdminLoading(false);
                
                if (rv.status === "ALL OK") {
                    showMessage("Login successful! Redirecting...", "success");
                    
                    if (rv.data.role === 'ADMIN') {
                        setTimeout(() => {
                            document.location.replace("admindashboard.php");
                        }, 1000);
                    } 
                } else {
                    let errorMessage = "";
                    switch(rv.status) {
                        case "USER NAME DOES NOT EXIST":
                            errorMessage = "No account found with this email address. Please check your email and try again.";
                            break;
                        case "Wrong Password":
                            errorMessage = "Incorrect password. Please check your password and try again.";
                            break;
                        case "Database Connection Failed":
                            errorMessage = "Unable to connect to the database. Please try again later.";
                            break;
                        case "Database Error":
                            errorMessage = "A database error occurred. Please contact support if this continues.";
                            break;
                        default:
                            errorMessage = rv.status || "An unexpected error occurred. Please try again.";
                    }
                    showMessage(errorMessage, "error");
                }
            },
            error: function(xhr, status, error) {
                window.setAdminLoading(false);
                
                let errorMessage = "Connection error. Please check your internet connection and try again.";
                if (xhr.status === 404) {
                    errorMessage = "Login service not found. Please contact support.";
                } else if (xhr.status === 500) {
                    errorMessage = "Server error occurred. Please try again later.";
                }
                showMessage(errorMessage, "error");
            }
        });
    } else {
        showMessage("Please enter both email and password.", "error");
    }
}

function showMessage(message, type = "error") {
    console.log('showMessage called with:', message, type);
    const errorDiv = $("#diverror");
    const messageLabel = $("#errormessage");
    
    console.log('Error div found:', errorDiv.length);
    console.log('Message label found:', messageLabel.length);
    
    // Multiple approaches to set the message text
    if (messageLabel.length > 0) {
        messageLabel.text(message);
        console.log('Message set via messageLabel');
    } else {
        // Fallback 1: Find label by ID
        const labelById = errorDiv.find('#errormessage');
        if (labelById.length > 0) {
            labelById.text(message);
            console.log('Message set via labelById');
        } else {
            // Fallback 2: Find any label
            const anyLabel = errorDiv.find('label');
            if (anyLabel.length > 0) {
                anyLabel.text(message);
                console.log('Message set via anyLabel');
            } else {
                // Fallback 3: Create a simple error display
                errorDiv.html('<div class="p-4 text-red-700 font-medium">' + message + '</div>');
                console.log('Message set via HTML fallback');
            }
        }
    }
    
    // Multiple approaches to show the error div
    errorDiv.removeClass("hidden").addClass("block").show().css({
        'display': 'block',
        'visibility': 'visible',
        'opacity': '1'
    });
    
    if (type === "success") {
        errorDiv.find(".border-red-500").removeClass("border-red-500").addClass("border-green-500");
        errorDiv.find(".bg-red-50").removeClass("bg-red-50").addClass("bg-green-50");
        errorDiv.find(".text-red-500").removeClass("text-red-500").addClass("text-green-500");
        errorDiv.find(".text-red-700").removeClass("text-red-700").addClass("text-green-700");
        
        // Change icon to checkmark for success
        const successIcon = `<svg class="h-5 w-5 text-green-500 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>`;
        errorDiv.find('svg').replaceWith(successIcon);
    } else {
        // Reset to error styling (in case it was changed to success)
        errorDiv.find(".border-green-500").removeClass("border-green-500").addClass("border-red-500");
        errorDiv.find(".bg-green-50").removeClass("bg-green-50").addClass("bg-red-50");
        errorDiv.find(".text-green-500").removeClass("text-green-500").addClass("text-red-500");
        errorDiv.find(".text-green-700").removeClass("text-green-700").addClass("text-red-700");
        
        // Restore X icon for errors
        const errorIcon = `<svg class="h-5 w-5 text-red-500 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>`;
        errorDiv.find('svg').replaceWith(errorIcon);
    }
    
    console.log('Error div visible?', errorDiv.is(':visible'));
    console.log('Error div classes after:', errorDiv.attr('class'));
    console.log('Error div HTML after:', errorDiv.html());
}

$(document).ready(function() {
    // Trigger login on button click
    $("#btnAdminLogin").on("click", function(e) {  // Use correct button ID
        e.preventDefault(); // Prevent the default form submission behavior
        tryLogin(); // Call the login function
    });

    // Trigger login on Enter key press in input fields
    $("input").keypress(function(e) {
        if (e.which === 13) { // If Enter key is pressed
            tryLogin(); // Call the login function
        }
    });

    // Test function to ensure error display works
    window.testAdminError = function() {
        showMessage("Test error message for admin login", "error");
    };
});
