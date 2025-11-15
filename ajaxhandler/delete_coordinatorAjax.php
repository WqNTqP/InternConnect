<?php
session_start(); // Start the session

// Include database connection
require_once __DIR__ . '/../config/path_config.php';
require_once PathConfig::getDatabasePath();

header('Content-Type: application/json'); // Set content type to JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the ID is provided
    if (isset($_POST['id'])) {
        $coordinatorId = $_POST['id'];

        // Validate the ID (ensure it's a number)
        if (!is_numeric($coordinatorId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Coordinator ID.']);
            exit;
        }

        // Create a new instance of the Database class
        $dbo = new Database();

        try {
            // Prepare the SQL statement to delete the coordinator
            $stmt = $dbo->conn->prepare("DELETE FROM coordinator WHERE COORDINATOR_ID = :coordinatorId");
            $stmt->bindParam(':coordinatorId', $coordinatorId, PDO::PARAM_INT);

            // Execute the statement
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Coordinator deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete coordinator.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Coordinator ID not provided.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
