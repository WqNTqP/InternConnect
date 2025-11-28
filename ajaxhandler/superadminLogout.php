<?php 
session_start();
// Destroy all session data
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}
session_destroy();

// Ensure proper redirection without relying on .htaccess
header("Location: ../");
exit();
?>

