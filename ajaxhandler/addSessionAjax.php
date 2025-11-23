<?php
session_start();
// Check if we're in a subdirectory (local development) or root (production)
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
require_once $basePath . "/database/database.php";
require_once $basePath . "/database/sessionDetails.php";

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $dbo = new Database();
    $sessionDetails = new SessionDetails();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $year = isset($_POST['year']) ? trim($_POST['year']) : '';
        
        // Validate inputs
        if (empty($year)) {
            $response['message'] = 'Year is required';
            echo json_encode($response);
            exit;
        }
        
        if (!is_numeric($year) || $year < 2000 || $year > 2050) {
            $response['message'] = 'Please enter a valid year (2000-2050)';
            echo json_encode($response);
            exit;
        }
        
        // Check if session already exists
        $checkQuery = "SELECT COUNT(*) FROM session_details WHERE YEAR = ?";
        $stmt = $dbo->conn->prepare($checkQuery);
        $stmt->execute([$year]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $response['message'] = 'Session already exists for this year';
            echo json_encode($response);
            exit;
        }
        
        // Insert new session
        $insertQuery = "INSERT INTO session_details (YEAR) VALUES (?)";
        $stmt = $dbo->conn->prepare($insertQuery);
        $stmt->execute([$year]);
        
        $response['success'] = true;
        $response['message'] = 'Session added successfully';
        $response['session'] = [
            'id' => $dbo->conn->lastInsertId(),
            'year' => $year,
            'display_name' => 'S.Y. ' . $year . '-' . ($year + 1)
        ];
        
    } else {
        $response['message'] = 'Invalid request method';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>

