<?php
require_once '../database/database.php';
header('Content-Type: application/json');

$db = new Database();

$questions = [
    ['category' => 'Technical Skills', 'question_text' => 'Explain to a non-tech person what a table in a database is.'],
    ['category' => 'Problem Solving', 'question_text' => 'Describe a time you solved a technical problem.'],
    ['category' => 'Communication', 'question_text' => 'How do you communicate complex ideas to others?'],
    ['category' => 'Teamwork', 'question_text' => 'Share an experience working in a team.'],
    ['category' => 'Adaptability', 'question_text' => 'How do you handle changes in project requirements?']
];

$success = true;
foreach ($questions as $q) {
            $sql = "INSERT INTO evaluation_questions (category, question_text, status) VALUES (:category, :question_text, 1)";
    $stmt = $db->conn->prepare($sql);
    if (!$stmt->execute([':category' => $q['category'], ':question_text' => $q['question_text']])) {
        $success = false;
        break;
    }
}

echo json_encode(['success' => $success]);

