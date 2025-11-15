
<?php
// predictionAjax.php - Backend for Prediction Tab (Student OJT Placement Prediction)
header('Content-Type: application/json');
session_start();
require_once '../database/database.php';

// Debug logging
error_log("Raw POST data: " . file_get_contents('php://input'));
error_log("POST array: " . print_r($_POST, true));

$response = ["success" => false, "students" => [], "error" => ""];

if (!isset($_SESSION["current_user"])) {
    $response["error"] = "Not logged in.";
    echo json_encode($response);
    exit;
}

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'savePrediction':
            // Save prediction and analysis to pre_assessment table
            error_log("POST data received: " . print_r($_POST, true));
            
            $student_id = $_POST['student_id'] ?? null;
            $ojt_placement = $_POST['ojt_placement'] ?? null;
            $reasoning = $_POST['prediction_reasoning'] ?? null;
            $probabilities = $_POST['prediction_probabilities'] ?? null;
            // Debug log incoming data
            error_log("Received POST data: " . print_r($_POST, true));
            
            // Validate each field individually
            $errors = [];
            if (!isset($_POST['student_id']) || $_POST['student_id'] === '') $errors[] = "student_id is required";
            if (!isset($_POST['ojt_placement']) || $_POST['ojt_placement'] === '') $errors[] = "ojt_placement is required";
            if (!isset($_POST['prediction_reasoning']) || $_POST['prediction_reasoning'] === '') $errors[] = "prediction_reasoning is required";
            if (!isset($_POST['prediction_probabilities']) || $_POST['prediction_probabilities'] === '') $errors[] = "prediction_probabilities is required";
            
            if (!empty($errors)) {
                $response = [
                    "success" => false,
                    "error" => "Missing required prediction data: " . implode(", ", $errors),
                    "received" => [
                        "student_id" => $student_id,
                        "ojt_placement" => $ojt_placement,
                        "reasoning" => $reasoning,
                        "probabilities" => $probabilities
                    ]
                ];
                error_log("Validation errors: " . print_r($response, true));
                echo json_encode($response);
                exit;
            }
            try {
                // First check if record exists
                $checkSql = "SELECT COUNT(*) FROM pre_assessment WHERE STUDENT_ID = ?";
                $checkStmt = $db->conn->prepare($checkSql);
                $checkStmt->execute([$student_id]);
                $exists = $checkStmt->fetchColumn() > 0;

                // Only update prediction fields since grades already exist
                $fields = [
                    'ojt_placement' => $ojt_placement,
                    'prediction_reasoning' => $reasoning,
                    'prediction_probabilities' => $probabilities
                ];

                if ($exists) {
                    // Build UPDATE query
                    $updatePairs = [];
                    $params = [':student_id' => $student_id];
                    
                    foreach ($fields as $field => $value) {
                        if ($value !== null) {
                            $paramName = 'param_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
                            $updatePairs[] = "`$field` = :$paramName";
                            $params[":$paramName"] = $value;
                        }
                    }
                    
                    $sql = "UPDATE pre_assessment SET " . implode(', ', $updatePairs) . " WHERE STUDENT_ID = :student_id";
                } else {
                    // Build INSERT query
                    $columns = ['STUDENT_ID'];
                    $values = [':student_id'];
                    $params = [':student_id' => $student_id];
                    
                    foreach ($fields as $field => $value) {
                        if ($value !== null) {
                            $paramName = 'param_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
                            $columns[] = "`$field`";
                            $values[] = ":$paramName";
                            $params[":$paramName"] = $value;
                        }
                    }
                    
                    $sql = "INSERT INTO pre_assessment (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
                }
                
                $stmt = $db->conn->prepare($sql);
                $stmt->execute($params);
                echo json_encode(["success" => true]);
            } catch (Exception $e) {
                echo json_encode(["error" => $e->getMessage()]);
            }
            exit;
            
        case 'getPreAssessment':
            // Get the latest prediction for a student
            if (!isset($_POST['student_id'])) {
                echo json_encode(["error" => "Student ID is required"]);
                exit;
            }
            
            $student_id = $_POST['student_id'];
            
            try {
                $sql = "SELECT * FROM pre_assessment WHERE STUDENT_ID = ? AND ojt_placement IS NOT NULL LIMIT 1";
                $stmt = $db->conn->prepare($sql);
                $stmt->execute([$student_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($row) {
                    
                    // Return exactly what's stored in the database without modifications
                    $response = [
                        'placement' => $row['ojt_placement'],
                        'reasoning' => $row['prediction_reasoning'],
                        'probabilities' => json_decode($row['prediction_probabilities'], true) ?? [],
                        'prob_explanation' => "The model predicts {$row['ojt_placement']} based on academic performance and skills assessment"
                    ];
                    
                    echo json_encode($response);
                } else {
                    echo json_encode(["error" => "No prediction found"]);
                }
            } catch (Exception $e) {
                echo json_encode(["error" => $e->getMessage()]);
            }
            exit;
            
        case 'getPredictions':
            // Get all predictions for coordinator's students
            $cdrid = $_POST['cdrid'] ?? $_SESSION["current_user"];
            
            try {
                $sql = "SELECT DISTINCT
                            id.STUDENT_ID,
                            CONCAT(id.SURNAME, ', ', id.NAME) as name,
                            hte.NAME as hte_name,
                            pa.ojt_placement as placement,
                            pa.prediction_reasoning as reasoning,
                            pa.prediction_probabilities as probabilities,
                            CASE 
                                WHEN pa.soft_skill IS NOT NULL AND pa.communication_skill IS NOT NULL THEN 'Rated'
                                ELSE 'Not Rated'
                            END as status
                        FROM internship_needs ineed
                        JOIN intern_details idet ON ineed.HTE_ID = idet.HTE_ID
                        JOIN interns_details id ON idet.INTERNS_ID = id.INTERNS_ID
                        JOIN host_training_establishment hte ON idet.HTE_ID = hte.HTE_ID
                        LEFT JOIN pre_assessment pa ON id.STUDENT_ID = pa.STUDENT_ID
                        WHERE ineed.COORDINATOR_ID = ?
                        ORDER BY id.SURNAME, id.NAME";
                        
                $stmt = $db->conn->prepare($sql);
                $stmt->execute([$cdrid]);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $data = [];
                foreach ($students as $student) {
                    $analysis = [];
                    if ($student['placement'] && $student['reasoning'] && $student['probabilities']) {
                        $analysis = [
                            'placement' => $student['placement'],
                            'reasoning' => $student['reasoning'],
                            'probabilities' => json_decode($student['probabilities'], true) ?? []
                        ];
                    }
                    
                    $data[] = [
                        'student_id' => $student['STUDENT_ID'],
                        'name' => $student['name'],
                        'hte_name' => $student['hte_name'] ?? 'Not Assigned',
                        'placement' => $student['placement'],
                        'status' => $student['status'],
                        'analysis' => $analysis
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $data
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
            
        default:
            echo json_encode(["error" => "Invalid action"]);
            exit;
    }
}

// Default: fetch students and their predictions
$coordinator_id = $_SESSION["current_user"];
try {
    $sql = "SELECT id.INTERNS_ID, id.STUDENT_ID, id.NAME, id.SURNAME, hte.NAME AS HTE_NAME
            FROM internship_needs ineed
            JOIN intern_details idet ON ineed.HTE_ID = idet.HTE_ID
            JOIN interns_details id ON idet.INTERNS_ID = id.INTERNS_ID
            JOIN host_training_establishment hte ON idet.HTE_ID = hte.HTE_ID
            WHERE ineed.COORDINATOR_ID = ?
            ORDER BY id.SURNAME, id.NAME";
    $stmt = $db->conn->prepare($sql);
    $stmt->execute([$coordinator_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $student) {
        $student_id = $student['STUDENT_ID'];
        // Get pre-assessment data
        $pre_sql = "SELECT * FROM pre_assessment WHERE STUDENT_ID = ?";
        $pre_stmt = $db->conn->prepare($pre_sql);
        $pre_stmt->execute([$student_id]);
        $pre = $pre_stmt->fetch(PDO::FETCH_ASSOC);

        $required = [
            'CC 102','CC 103','PF 101','CC 104','IPT 101','IPT 102','CC 106','CC 105',
            'IM 101','IM 102','HCI 101','HCI 102','WS 101','NET 101','NET 102',
            'IAS 101','IAS 102','CAP 101','CAP 102','SP 101','soft_skill','communication_skill'
        ];
        $missing = [];
        $valid = true;
        if ($pre) {
            foreach ($required as $col) {
                if (!isset($pre[$col]) || $pre[$col] === null || $pre[$col] === "") {
                    $missing[] = $col;
                    $valid = false;
                }
            }
        } else {
            $valid = false;
            $missing = $required;
        }
        // Status
        $status = ($pre && $pre['soft_skill'] !== null && $pre['communication_skill'] !== null) ? "Rated" : "Not Rated";
        // Prepare student data
        $student_data = [
            "INTERNS_ID" => $student['INTERNS_ID'],
            "STUDENT_ID" => $student['STUDENT_ID'],
            "NAME" => $student['SURNAME'] . ", " . $student['NAME'],
            "HTE_ASSIGNED" => $student['HTE_NAME'],
            "STATUS" => $status,
            "valid" => $valid,
            "missing" => $missing,
            "pre_assessment" => $pre
        ];
        $response["students"][] = $student_data;
    }
    $response["success"] = true;
} catch (Exception $e) {
    $response["error"] = $e->getMessage();
}

echo json_encode($response);
exit;
?>

