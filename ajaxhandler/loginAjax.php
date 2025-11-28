<?php
// Start session at the very beginning to avoid header issues
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/path_config.php';
require_once PathConfig::getDatabasePath();
require_once PathConfig::getProjectPath('/database/coordinator.php');

$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : "";
if(!empty($action))
{
    if($action=="verifyUser")
    {
        // Get the username and password entered by the user
        $un = $_POST["user_name"];
        $pw = $_POST["password"];

        // Initialize the database and coordinator objects
        $dbo = new Database();
        $fdo = new coordinator();

        // Call the verifyUser method
        $rv = $fdo->verifyUser($dbo, $un, $pw);

        if ($rv['status'] == "ALL OK") {
            // Session already started at top of file
            $_SESSION['current_user'] = $rv['id'];
            $_SESSION['current_user_role'] = $rv['role'];
            
            // Include the role in the response
            $rv['data'] = [
                "id" => $rv['id'],
                "role" => $rv['role'] // Role will be either COORDINATOR or ADMIN
            ];
        }

        // Return the response as JSON
        header('Content-Type: application/json');
        echo json_encode($rv);
        exit(); // Prevent any additional output
    }
}


?>
