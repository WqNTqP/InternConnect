<?php





$path=$_SERVER['DOCUMENT_ROOT'];
// Check if we're in a subdirectory (local development) or root (production)
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
require_once $basePath."/database/database.php";
require_once $basePath."/database/admin.php";
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
        $fdo = new admin();

        // Call the verifyUser method
        $rv = $fdo->verifyUser($dbo, $un, $pw);

        if ($rv['status'] == "ALL OK") {
            // Start session and store the user id
            session_start();
            $_SESSION['admin_user'] = $rv['id']; // Use admin_user for Admin
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




// require_once '../database/database.php';

// function sendResponse($status, $data = null, $message = '') {
//     echo json_encode(["status" => $status, "data" => $data, "message" => $message]);
//     exit;
// }

// try {
//     $dbo = new Database();
// } catch (Exception $e) {
//     sendResponse('error', null, 'Database connection failed');
// }

// $action = $_POST['action'] ?? '';

// if ($action === "verifyAdmin") {
//     $email = $_POST['email'] ?? '';
//     $password = $_POST['password'] ?? '';

//     if (empty($email) || empty($password)) {
//         sendResponse('error', null, 'Email and password are required');
//     }

//     try {
//         $stmt = $dbo->conn->prepare("SELECT ADMIN_ID, NAME FROM admin_table WHERE EMAIL = :email AND PASSWORD = :password");
//         $stmt->execute([':email' => $email, ':password' => $password]);

//         if ($stmt->rowCount() > 0) {
//             $admin = $stmt->fetch(PDO::FETCH_ASSOC);
//             sendResponse('ALL OK', ["id" => $admin['ADMIN_ID'], "name" => $admin['NAME']], 'Login successful');
//         } else {
//             sendResponse('error', null, 'Invalid email or password');
//         }
//     } catch (PDOException $e) {
//         sendResponse('error', null, 'Database error: ' . $e->getMessage());
//     }
// } else {
//     sendResponse('error', null, 'Invalid action');
// }
?>

