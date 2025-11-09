<?php 
session_start();

// Only log out coordinator user
unset($_SESSION["coordinator_user"]);
unset($_SESSION["user_type"]);
unset($_SESSION["user_id"]);
// Return JSON response instead of redirect
echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
exit();
?>

