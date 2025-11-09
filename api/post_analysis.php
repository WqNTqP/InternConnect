<?php
// Flask API proxy for post_analysis endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get query parameters
    $queryString = $_SERVER['QUERY_STRING'];
    
    // Flask API is running on localhost:5000 internally
    $flask_url = 'http://localhost:5000/post_analysis';
    if ($queryString) {
        $flask_url .= '?' . $queryString;
    }
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $flask_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute and get response
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        // Fallback: Return mock data for local development when Flask isn't running
        $mockData = [
            'post_assessment_averages' => [
                'technical_skills' => 4.2,
                'problem_solving' => 4.0,
                'communication' => 4.5,
                'teamwork' => 4.3,
                'adaptability' => 4.1
            ],
            'placement' => 'Technical Support',
            'reasoning' => 'Mock analysis: Student shows strong technical and communication skills suitable for technical support role.',
            'supervisor_comment' => 'Mock comment: Excellent performance in technical assessments.',
            'comparative_analysis' => 'Mock analysis: Performance improved significantly from pre-assessment.',
            'strengths_post_assessment' => 'Strong technical skills, good communication',
            'correlation_analysis' => 'High correlation between technical skills and job performance',
            'conclusion' => 'Mock conclusion: Ready for technical support placement.',
            'mock_data' => true
        ];
        echo json_encode($mockData);
    } else {
        http_response_code($httpCode);
        echo $response;
    }
    
    curl_close($ch);
} else {
    echo json_encode(['error' => 'Only GET method allowed']);
}
?>