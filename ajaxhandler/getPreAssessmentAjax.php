<?php
require_once('../database/database.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'getPreAssessment') {
        $student_id = $_POST['student_id'];
        
        // Get the latest pre-assessment for this student
        $query = "SELECT * FROM pre_assessment WHERE student_id = ? ORDER BY date_taken DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $assessment = $result->fetch_assoc();
            
            // Format the response to match the expected structure
            echo json_encode([
                'status' => 'success',
                'placement' => $assessment['predicted_placement'],
                'confidence' => $assessment['confidence_level'],
                'reasoning' => $assessment['reasoning'],
                'subjects' => $assessment['relevant_subjects'],
                'prob_explanation' => $assessment['probability_explanation'],
                'probabilities' => json_decode($assessment['probability_scores'], true)
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'error' => 'No pre-assessment found for this student'
            ]);
        }
    }
}
?>
