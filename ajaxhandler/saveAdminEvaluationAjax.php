<?php
// ajaxhandler/saveAdminEvaluationAjax.php
header('Content-Type: application/json');

require_once '../database/database.php';
session_start();

$student_id = isset($_POST['student_id']) ? $_POST['student_id'] : null;
$ratings = isset($_POST['ratings']) ? json_decode($_POST['ratings'], true) : null;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$supervisor_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;

$db = new Database();
$conn = $db->conn;

// Always translate STUDENT_ID to INTERN_ID before proceeding
$intern_id = null;
if ($student_id) {
    $stmt_id = $conn->prepare('SELECT INTERNS_ID FROM interns_details WHERE STUDENT_ID = ? LIMIT 1');
    $stmt_id->execute([$student_id]);
    $row_id = $stmt_id->fetch(PDO::FETCH_ASSOC);
    if ($row_id && !empty($row_id['INTERNS_ID'])) {
        $intern_id = $row_id['INTERNS_ID'];
    }
}

if (!$student_id || !$ratings || !is_array($ratings) || !$intern_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid student_id, intern_id, or ratings']);
    exit;
}
$success = true;
foreach ($ratings as $r) {
    // Fix category casing to match database
    $category_map = [
        'SYSTEM DEVELOPMENT' => 'System Development',
        'TECHNICAL SUPPORT' => 'Technical Support',
        'BUSINESS OPERATION' => 'Business Operation',
        'RESEARCH' => 'Research'
    ];
    $category_raw = $r['category'];
    $category = isset($category_map[$category_raw]) ? $category_map[$category_raw] : $category_raw;
    $question_id = isset($r['question_id']) ? $r['question_id'] : null;
    $question_text = $r['question_text'];
    $self_rating = $r['self_rating'];
    $admin_rating = $r['admin_rating'];

    // Only proceed if we have a valid question_id
    if ($question_id !== null) {
        $select_stmt = $conn->prepare('SELECT id FROM post_assessment WHERE student_id=? AND category=? AND question_id=?');
        $select_stmt->execute([$intern_id, $category, $question_id]);
        $row_exists = $select_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $row_exists = false;
    }
    if (!isset($debug_select)) $debug_select = [];
    $debug_select[] = [
        'student_id' => $intern_id,
        'category' => $category,
        'question_id' => $question_id,
        'row_exists' => $row_exists ? true : false
    ];
    // Collect debug info for web response
    if (!isset($debug)) $debug = [];
    $debug[] = [
        'student_id' => $intern_id,
        'category' => $category,
        'question_id' => $question_id,
        'admin_rating' => $admin_rating,
        'supervisor_id' => $supervisor_id,
        'comment' => $comment
    ];
    $stmt = $conn->prepare('UPDATE post_assessment SET supervisor_id=?, supervisor_rating=?, comment=? WHERE student_id=? AND category=? AND question_id=?');
    $stmt->execute([
        $supervisor_id,
        $admin_rating,
        $comment,
        $intern_id,
        $category,
        $question_id
    ]);
    // Only treat as error if the row does not exist at all
    if ($stmt->rowCount() === 0 && !$row_exists) {
        if (!isset($debug_errors)) $debug_errors = [];
        $debug_errors[] = "No row updated for: student_id=$intern_id, category=$category, question_id=$question_id (row does not exist)";
        $success = false;
    }
}

// After saving all ratings, calculate and update category averages in pre_assessment
if ($success) {
    // Define the categories to process (match exactly with post_assessment table)
    $categories = [
        'System Development' => 'post_systems_development_avg',
        'Research' => 'post_research_avg',
        'Business Operation' => 'post_business_operations_avg',
        'Technical Support' => 'post_technical_support_avg'
    ];
    // Supervisor averages (existing logic)
    $averages = [];
    foreach ($categories as $cat => $col) {
        $stmt_avg = $conn->prepare("SELECT AVG(COALESCE(supervisor_rating, self_rating)) AS avg_rating FROM post_assessment WHERE student_id = ? AND category = ?");
        $stmt_avg->execute([$intern_id, $cat]);
        $avg = $stmt_avg->fetchColumn();
        $averages[$col] = $avg !== false ? $avg : null;
    }
    // Self-assessment averages
    $self_avg_cols = [
        'System Development' => 'self_systems_development_avg',
        'Research' => 'self_research_avg',
        'Business Operation' => 'self_business_operations_avg',
        'Technical Support' => 'self_technical_support_avg'
    ];
    $self_averages = [];
    foreach ($self_avg_cols as $cat => $col) {
        $stmt_self_avg = $conn->prepare("SELECT AVG(self_rating) AS avg_rating FROM post_assessment WHERE student_id = ? AND category = ?");
        $stmt_self_avg->execute([$intern_id, $cat]);
        $avg_self = $stmt_self_avg->fetchColumn();
        $self_averages[$col] = $avg_self !== false ? $avg_self : null;
    }
    // Build dynamic SQL for update (supervisor + self + supervisor_comment)
    $update_sql = "UPDATE pre_assessment SET ";
    $update_fields = [];
    $update_params = [];
    foreach ($categories as $cat => $col) {
        $update_fields[] = "$col = ?";
        $update_params[] = $averages[$col];
    }
    foreach ($self_avg_cols as $cat => $col) {
        $update_fields[] = "$col = ?";
        $update_params[] = $self_averages[$col];
    }
    // Add supervisor_comment update
    $update_fields[] = "supervisor_comment = ?";
    $update_params[] = $comment;
    $update_sql .= implode(", ", $update_fields) . " WHERE STUDENT_ID = ?";
    $update_params[] = $student_id;
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->execute($update_params);
    $rows_updated = $stmt_update->rowCount();
    echo json_encode([
        'status' => 'success',
        'debug' => $debug,
        'debug_select' => $debug_select,
        'averages' => $averages,
        'self_averages' => $self_averages,
        'rows_updated' => $rows_updated,
        'student_id_used' => $student_id
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save some ratings',
        'debug' => isset($debug) ? $debug : [],
        'debug_errors' => isset($debug_errors) ? $debug_errors : [],
        'debug_select' => isset($debug_select) ? $debug_select : []
    ]);
}

