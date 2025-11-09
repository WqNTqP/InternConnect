<?php
session_start();
if (!isset($_SESSION["coordinator_user"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$coordinator_id = $_SESSION["coordinator_user"];

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . "/InternConnect/database/database.php";

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
    // Fetch all students from interns_details (no attendance or post_assessment join)
    $stmt = $conn->prepare("SELECT INTERNS_ID, STUDENT_ID, SURNAME, NAME FROM interns_details");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "students" => $students]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>

