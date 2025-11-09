<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . "/InternConnect/database/database.php";

$response = [];  // Initialize response array

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // If the action is 'getHTEs', fetch the list of HTEs
    if (isset($_POST['action']) && $_POST['action'] === 'getHTEs') {
        try {
            $db = new Database();
            $conn = $db->conn; // Access the PDO connection

            $stmt = $conn->prepare("SELECT HTE_ID, NAME FROM host_training_establishment");
            $stmt->execute();
            $htes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($htes) {
                $response['success'] = true;
                $response['htes'] = $htes;
            } else {
                $response['success'] = false;
                $response['message'] = 'No HTEs found.';
            }
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = 'Database Error: ' . $e->getMessage();
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = 'General Error: ' . $e->getMessage();
        }

        // Output the response as JSON and exit
        echo json_encode($response);
        exit;
    }

    // Existing logic to add a new coordinator
    if (isset($_POST['coordinatorId'])) {
        // Sanitize and validate input data
        $coordinatorId = trim($_POST['coordinatorId']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contactNumber = trim($_POST['contactNumber']);
        $department = trim($_POST['department']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role']);
        $hteId = isset($_POST['hteId']) ? trim($_POST['hteId']) : null; // Get HTE_ID if available

        // Check if required fields are empty
        if (empty($coordinatorId) || empty($name) || empty($email) || empty($contactNumber) || empty($department) || empty($username) || empty($password) || empty($role)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        try {
            $db = new Database();
            $conn = $db->conn;

            // Check for duplication of COORDINATOR_ID
            $stmt = $conn->prepare("SELECT COUNT(*) FROM coordinator WHERE COORDINATOR_ID = ?");
            $stmt->execute([$coordinatorId]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'ID already exists.']);
                exit;
            }

            // If role is ADMIN, set the HTE_ID
            // Check if user is authorized to create SUPERADMIN
            if ($role === 'SUPERADMIN') {
                if (!isset($_SESSION['current_user_role']) || $_SESSION['current_user_role'] !== 'SUPERADMIN') {
                    echo json_encode(['success' => false, 'message' => 'Only existing Superadmins can create new Superadmins.']);
                    exit;
                }
            }

            if ($role === 'ADMIN' && $hteId) {
                // Insert new admin with HTE_ID
                $stmt = $conn->prepare("INSERT INTO coordinator (COORDINATOR_ID, NAME, EMAIL, CONTACT_NUMBER, DEPARTMENT, USERNAME, PASSWORD, ROLE, HTE_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$coordinatorId, $name, $email, $contactNumber, $department, $username, $password, $role, $hteId]);
            } else {
                // Insert new coordinator/superadmin without HTE_ID
                $stmt = $conn->prepare("INSERT INTO coordinator (COORDINATOR_ID, NAME, EMAIL, CONTACT_NUMBER, DEPARTMENT, USERNAME, PASSWORD, ROLE) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$coordinatorId, $name, $email, $contactNumber, $department, $username, $password, $role]);
            }

            echo json_encode(['success' => true, 'message' => 'Coordinator added successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to add coordinator/admin: ' . $e->getMessage()]);
        }
    }

} else {
    // Handle invalid request method
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>

