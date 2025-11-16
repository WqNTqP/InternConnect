function tryStudentLogin()
{
    let email = $("#txtEmail").val();
    let password = $("#txtStudentID").val();
    if(email.trim() !== "" && password.trim() !== "")
    {
        $.ajax({
            url: "ajaxhandler/studentLoginAjax.php",
            type: "POST",
            dataType: "json",
            data: { email: email, password: password, action: "verifyStudent" },
            beforeSend: function() {
                $("#diverror").removeClass("applyerrordiv");
                $("#lockscreen").addClass("applylockscreen");
            },
            success: function(rv) {
                $("#lockscreen").removeClass("applylockscreen");
                
                if(rv.status == "ALL OK") {
                    // Show success message briefly before redirect
                    showStudentMessage("Login successful! Redirecting to your dashboard...", "success");
                    
                    setTimeout(() => {
                        document.location.replace("student_dashboard.php");
                    }, 1000);
                } else {
                    // Enhanced error messages based on server response
                    let errorMessage = "";
                    const message = rv.message || rv.status || "";
                    
                    if (message.toLowerCase().includes("not found") || message.toLowerCase().includes("does not exist")) {
                        errorMessage = "No student account found with this email address. Please check your email and try again.";
                    } else if (message.toLowerCase().includes("password") || message.toLowerCase().includes("incorrect")) {
                        errorMessage = "Incorrect password. Please use your Student ID as the password or contact your coordinator if you've changed it.";
                    } else if (message.toLowerCase().includes("database")) {
                        errorMessage = "Database connection issue. Please try again later or contact support.";
                    } else {
                        errorMessage = message || "Login failed. Please check your credentials and try again.";
                    }
                    
                    showStudentMessage(errorMessage, "error");
                }
            },
            error: function(xhr, status, error) {
                $("#lockscreen").removeClass("applylockscreen");
                
                let errorMessage = "Connection error. Please check your internet connection and try again.";
                if (xhr.status === 404) {
                    errorMessage = "Login service not found. Please contact your coordinator.";
                } else if (xhr.status === 500) {
                    errorMessage = "Server error occurred. Please try again later.";
                }
                showStudentMessage(errorMessage, "error");
            }
        });
    }
    else
    {
        showStudentMessage("Please enter both email and password.", "error");
    }
}

function showStudentMessage(message, type = "error") {
    console.log('showStudentMessage called with:', message, type);
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
    } else {
        // Reset to error styling (in case it was changed to success)
        errorDiv.find(".border-green-500").removeClass("border-green-500").addClass("border-red-500");
        errorDiv.find(".bg-green-50").removeClass("bg-green-50").addClass("bg-red-50");
        errorDiv.find(".text-green-500").removeClass("text-green-500").addClass("text-red-500");
        errorDiv.find(".text-green-700").removeClass("text-green-700").addClass("text-red-700");
    }
    
    console.log('Error div classes after:', errorDiv.attr('class'));
}


$(document).ready(function() {
    $("#btnLogin").on("click", function(e) {
        e.preventDefault();
        tryStudentLogin();
    });

    // Also add this for enter key press in input fields
    $("input").keypress(function(e) {
        if(e.which == 13) { // Enter key
            tryStudentLogin();
        }
    });

    // Password visibility toggle removed
});
