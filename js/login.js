function tryLogin() {
    let un = $("#txtUsername").val(); // Get username input
    let pw = $("#txtPassword").val(); // Get password input
    
    // Check if username and password are not empty
    if (un.trim() !== "" && pw.trim() !== "") {
        $.ajax({
            url: "ajaxhandler/loginAjax.php", // Adjust path if needed
            type: "POST",
            dataType: "json",
            data: { user_name: un, password: pw, action: "verifyUser" }, // Send username, password, and action
            beforeSend: function() {
                $("#diverror").removeClass("applyerrordiv");
                $("#lockscreen").addClass("applylockscreen");
            },
            success: function(rv) {
                $("#lockscreen").removeClass("applylockscreen");
                console.log(rv);
                // Check response status for login validation
                if (rv.status === "ALL OK") {
                    // Show success message briefly before redirect
                    showLoginMessage("Login successful! Redirecting...", "success");
                    
                    // Redirect based on user role
                    setTimeout(() => {
                        if (rv.data.role === 'SUPERADMIN') {
                            document.location.replace("superadmin/dashboard");
                        } else if (rv.data.role === 'COORDINATOR') {
                            document.location.replace("dashboard");
                        }
                    }, 1000);
                } else {
                    // Enhanced error messages based on server response
                    let errorMessage = "";
                    switch(rv.status) {
                        case "USER NAME DOES NOT EXIST":
                            errorMessage = "No account found with this username. Please check your username and try again.";
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
                    showLoginMessage(errorMessage, "error");
                }
            },
            error: function(xhr, status, error) {
                $("#lockscreen").removeClass("applylockscreen");
                
                let errorMessage = "Connection error. Please check your internet connection and try again.";
                if (xhr.status === 404) {
                    errorMessage = "Login service not found. Please contact support.";
                } else if (xhr.status === 500) {
                    errorMessage = "Server error occurred. Please try again later.";
                }
                showLoginMessage(errorMessage, "error");
            }
        });
    } else {
        // Show error if either username or password is empty
        showLoginMessage("Please enter both username and password.", "error");
    }
}

function showLoginMessage(message, type = "error") {
    console.log('showLoginMessage called with:', message, type);
    const errorDiv = $("#diverror");
    const messageLabel = $("#errormessage");
    
    console.log('Error div found:', errorDiv.length);
    console.log('Message label found:', messageLabel.length);
    
    // Set the message text
    if (messageLabel.length > 0) {
        messageLabel.text(message);
    } else {
        // Fallback: set text directly on error div
        errorDiv.find('label').text(message);
    }
    
    // Always show the error div
    errorDiv.show().removeClass("hidden").addClass("block").css('display', 'block');
    
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
    
    console.log('Error div classes after:', errorDiv.attr('class'));
}

$(document).ready(function() {
    // Trigger login on button click
    $("#btnLogin").on("click", function(e) {
        e.preventDefault(); // Prevent the default form submission behavior
        tryLogin(); // Call the login function
    });

    // Trigger login on Enter key press in input fields
    $("input").keypress(function(e) {
        if (e.which === 13) { // If Enter key is pressed
            tryLogin(); // Call the login function
        }
    });
});
