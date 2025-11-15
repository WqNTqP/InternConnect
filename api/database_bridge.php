<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../database/database.php';

try {
    $db = new Database();
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_past_data':
                // Get all data from past_data table
                $stmt = $db->conn->prepare("SELECT * FROM past_data");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'count' => count($data)
                ]);
                break;
                
            case 'get_feature_columns':
                // Get column names excluding non-feature columns
                $stmt = $db->conn->prepare("DESCRIBE past_data");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $exclude_cols = ['id_number', 'student_name', 'year_graduated', 'OJT Placement'];
                $feature_cols = [];
                
                foreach ($columns as $col) {
                    if (!in_array($col['Field'], $exclude_cols)) {
                        $feature_cols[] = $col['Field'];
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'feature_columns' => $feature_cols,
                    'count' => count($feature_cols)
                ]);
                break;
                
            case 'get_placements':
                // Get unique OJT Placement values
                $stmt = $db->conn->prepare("SELECT DISTINCT `OJT Placement` FROM past_data WHERE `OJT Placement` IS NOT NULL");
                $stmt->execute();
                $placements = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                echo json_encode([
                    'success' => true,
                    'placements' => $placements,
                    'count' => count($placements)
                ]);
                break;
                
            case 'get_pre_assessment':
                // Get student pre-assessment data by student_id
                if (!isset($_GET['student_id'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'student_id parameter is required'
                    ]);
                    break;
                }
                
                $student_id = $_GET['student_id'];
                
                // Try to find the student record
                $stmt = $db->conn->prepare("SELECT * FROM pre_assessment WHERE STUDENT_ID = ?");
                $stmt->execute([$student_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data) {
                    echo json_encode([
                        'success' => true,
                        'data' => $data
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Student not found'
                    ]);
                }
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid action'
                ]);
                break;
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No action specified'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>