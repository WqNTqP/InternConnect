<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/database/database.php';
$db = new Database();
// Get total students under the current coordinator
// Try to get coordinator_id from session, GET, or current user context
$coordinatorId = null;
if (isset($_SESSION['coordinator_id'])) {
    $coordinatorId = $_SESSION['coordinator_id'];
} elseif (isset($_GET['coordinator_id'])) {
    $coordinatorId = $_GET['coordinator_id'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'coordinator') {
    $coordinatorId = $_SESSION['user_id'];
}
$totalStudents = null;
$answeredCount = null;
if ($coordinatorId !== null) {
    // Total students under coordinator
    $totalStudentsStmt = $db->conn->prepare("
        SELECT COUNT(DISTINCT id.INTERNS_ID) as total_students
        FROM interns_details id
        JOIN intern_details indet ON id.INTERNS_ID = indet.INTERNS_ID
        JOIN internship_needs ineed ON indet.HTE_ID = ineed.HTE_ID AND indet.SESSION_ID = ineed.SESSION_ID
        WHERE ineed.COORDINATOR_ID = ?
    ");
    $totalStudentsStmt->execute([$coordinatorId]);
    $totalStudents = $totalStudentsStmt->fetch(PDO::FETCH_ASSOC)['total_students'];

    // Students who answered evaluation under coordinator
    $answeredStmt = $db->conn->prepare("
        SELECT COUNT(DISTINCT se.student_id) as answered_count
        FROM student_evaluation se
        JOIN interns_details id ON se.student_id = id.INTERNS_ID
        JOIN intern_details indet ON id.INTERNS_ID = indet.INTERNS_ID
        JOIN internship_needs ineed ON indet.HTE_ID = ineed.HTE_ID AND indet.SESSION_ID = ineed.SESSION_ID
        WHERE ineed.COORDINATOR_ID = ?
    ");
    $answeredStmt->execute([$coordinatorId]);
    $answeredCount = $answeredStmt->fetch(PDO::FETCH_ASSOC)['answered_count'];
}


// Get total students who have been rated
$ratedStmt = $db->conn->prepare("SELECT COUNT(DISTINCT student_id) as rated_count FROM coordinator_evaluation WHERE rating IS NOT NULL");
$ratedStmt->execute();
$ratedCount = $ratedStmt->fetch(PDO::FETCH_ASSOC)['rated_count'];

// Get per-question rating stats
$questionStatsStmt = $db->conn->prepare("
    SELECT se.question_id, eq.question_text, r.rating, COUNT(r.rating) as rating_count
    FROM student_evaluation se
    JOIN evaluation_questions eq ON se.question_id = eq.question_id
    LEFT JOIN coordinator_evaluation r ON se.id = r.student_evaluation_id
    GROUP BY se.question_id, r.rating
    ORDER BY se.question_id, r.rating DESC
");
$questionStatsStmt->execute();
$questionStatsRaw = $questionStatsStmt->fetchAll(PDO::FETCH_ASSOC);

// Organize stats per question
$questionStats = [];
foreach ($questionStatsRaw as $row) {
    $qid = $row['question_id'];
    if (!isset($questionStats[$qid])) {
        $questionStats[$qid] = [
            'question_text' => $row['question_text'],
            'ratings' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]
        ];
    }
    if ($row['rating'] !== null) {
        $questionStats[$qid]['ratings'][$row['rating']] = (int)$row['rating_count'];
    }
}

// Output JSON
echo json_encode([
    'answeredCount' => $answeredCount,
    'ratedCount' => $ratedCount,
    'totalStudents' => $totalStudents,
    'questionStats' => $questionStats
]);

