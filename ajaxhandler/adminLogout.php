<?php 
session_start();
unset($_SESSION["admin_user"]);
header("location:../admindashboard.php");
exit();
?>

