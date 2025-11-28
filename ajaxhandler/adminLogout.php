<?php 
session_start();
unset($_SESSION["admin_user"]);
header("Location: ../supervisor");
exit();
?>

