<?php 
session_start();
unset($_SESSION["student_user"]);
header("location:../student_login.php");
exit();
?>

