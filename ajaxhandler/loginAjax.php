<?php
// Start session at the very beginning to avoid header issues
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/path_config.php';
require_once PathConfig::getDatabasePath();
require_once PathConfig::getProjectPath('/database/coordinator.php');
$action=$_REQUEST["action"];
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
        echo json_encode($rv);
    }
}
















// $path=$_SERVER['DOCUMENT_ROOT'];
// require_once $path."/database/database.php";
// require_once $path."/database/coordinator.php";
// $action=$_REQUEST["action"];
// if(!empty($action))
// {
//     if($action=="verifyUser")
//     {
//         //kuhaon kung unsa tong gi type
//         $un=$_POST["user_name"];
//         $pw=$_POST["password"];
//         //$rv=["un"=>$un,"pw"=>$pw];
//         //echo json_encode($rv);
//         //awon ug naa ba sa database
//         $dbo=new Database();
//         $fdo=new coordinator();
//         $rv=$fdo->verifyUser($dbo, $un, $pw);
//         if($rv['status']=="ALL OK")
//         {
//             session_start();
//             $_SESSION['current_user']=$rv['id'];
            
//         }

        // for($i=0;$i<100000;$i++)
        //   {
        //     for($j=0;$j<2000;$j++)
        //     {
              
        //     }
        //   }
//         // kani mao ni ang response kung unsa ang status
//         echo json_encode($rv);

//     }
// }
?>
