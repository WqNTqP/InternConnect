<?php
// Get student_id from request

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
if (!$student_id) {
    echo json_encode(['error' => 'Missing student_id']);
    exit;
}
require_once $_SERVER['DOCUMENT_ROOT'] . "/database/database.php";
$dbo = new Database();

// Translate STUDENT_ID to INTERN_ID
$stmt = $dbo->conn->prepare("SELECT INTERNS_ID FROM interns_details WHERE STUDENT_ID = ? LIMIT 1");
$stmt->execute([$student_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['INTERNS_ID'])) {
    echo json_encode([]);
    exit;
}
$intern_id = $row['INTERNS_ID'];

// Get all categories for this intern from student_questions
$stmt = $dbo->conn->prepare("SELECT DISTINCT category FROM student_questions WHERE student_id = ?");
$stmt->execute([$intern_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all questions for this intern grouped by category

$stmt = $dbo->conn->prepare("SELECT id AS question_id, category, question_text, question_number FROM student_questions WHERE student_id = ? ORDER BY category, question_number");
$stmt->execute([$intern_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get self ratings for this intern from post_assessment

// Get all ratings (self, supervisor, comment) for this intern from post_assessment
$stmt = $dbo->conn->prepare("SELECT category, question_id, self_rating, supervisor_rating, comment FROM post_assessment WHERE student_id = ?");
$stmt->execute([$intern_id]);
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize questions and ratings by category
$result = [];
foreach ($categories as $cat) {
    $result[$cat] = [
        'questions' => [],
        'ratings' => []
    ];
}
foreach ($questions as $q) {
    // Ensure each question object includes 'id' for frontend mapping
    $questionObj = $q;
    $questionObj['id'] = $q['question_id']; // 'question_id' is already the id from SELECT
    $result[$q['category']]['questions'][] = $questionObj;
}

foreach ($ratings as $r) {
    $result[$r['category']]['ratings'][$r['question_id']] = [
        'self_rating' => $r['self_rating'],
        'supervisor_rating' => $r['supervisor_rating'],
        'comment' => $r['comment']
    ];
}

echo json_encode($result);

