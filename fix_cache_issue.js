// Add this to clear any browser cache issues
// This ensures that AJAX calls use the latest endpoints

$(document).ready(function() {
    // Force cache busting for AJAX calls
    $.ajaxSetup({
        cache: false,
        beforeSend: function(xhr, settings) {
            // Log all AJAX calls for debugging
            console.log('AJAX Call:', settings.url, settings.data);
        },
        error: function(xhr, status, error) {
            // Enhanced error logging
            console.error('AJAX Error:', {
                url: this.url,
                status: status,
                error: error,
                responseText: xhr.responseText
            });
        }
    });
    
    // Clear any cached external URLs
    if (typeof Storage !== "undefined") {
        // Clear localStorage and sessionStorage of any cached URLs
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            if (key && (key.includes('flask') || key.includes('render') || key.includes('api'))) {
                localStorage.removeItem(key);
            }
        }
        
        for (let i = sessionStorage.length - 1; i >= 0; i--) {
            const key = sessionStorage.key(i);
            if (key && (key.includes('flask') || key.includes('render') || key.includes('api'))) {
                sessionStorage.removeItem(key);
            }
        }
    }
});