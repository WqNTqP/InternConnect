<?php
session_start();
if (!isset($_SESSION["coordinator_user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$coordinator_id = $_SESSION["coordinator_user"];

header('Content-Type: application/json');
// Check if we're in a subdirectory (local development) or root (production)
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
require_once $basePath . "/database/database.php";

if (isset($_POST['action']) && $_POST['action'] === 'getStudentPostAssessment' && isset($_POST['interns_id'])) {
    $interns_id = $_POST['interns_id'];
    try {
        $db = new Database();
        $conn = $db->conn;
        // Fetch all post-assessment records for this student, including question text from student_questions
        $stmt = $conn->prepare("
            SELECT pa.question_id, sq.question_text, pa.self_rating, pa.supervisor_rating, pa.category, pa.comment
            FROM post_assessment pa
            LEFT JOIN student_questions sq ON pa.question_id = sq.id
            WHERE pa.student_id = ?
        ");
        $stmt->execute([$interns_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasSupervisorRating = false;
        foreach ($records as $rec) {
            if (!is_null($rec['supervisor_rating'])) {
                $hasSupervisorRating = true;
                break;
            }
        }
        echo json_encode([
            'success' => true,
            'records' => $records,
            'hasSupervisorRating' => $hasSupervisorRating
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit;
}

try {
    $db = new Database();
    $conn = $db->conn;
    
    // Fetch students assigned to the current coordinator through internship_needs with session information
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            id.INTERNS_ID, 
            id.STUDENT_ID, 
            id.SURNAME, 
            id.NAME,
            s.YEAR,
            s.ID as SESSION_ID,
            CONCAT('S.Y. ', s.YEAR, '-', s.YEAR + 1) AS SESSION_NAME
        FROM interns_details id
        JOIN intern_details idet ON id.INTERNS_ID = idet.INTERNS_ID
        JOIN internship_needs itn ON idet.HTE_ID = itn.HTE_ID
        JOIN session_details s ON idet.SESSION_ID = s.ID
        WHERE itn.COORDINATOR_ID = ?
        ORDER BY s.YEAR DESC, id.SURNAME ASC, id.NAME ASC
    ");
    $stmt->execute([$coordinator_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(["success" => true, "students" => $students]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>

