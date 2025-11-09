<?php
session_start();
header('Content-Type: application/json');
// Check if we're in a subdirectory (local development) or root (production)
$path = $_SERVER['DOCUMENT_ROOT'];
$basePath = file_exists($path."/database/database.php") ? $path : $path."/InternConnect";
require_once $basePath . "/database/database.php";

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
if (!$student_id) {
    echo json_encode(["success" => false, "error" => "Missing student_id"]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->conn;

    // Check if summary already exists for this student
    $stmt = $conn->prepare("SELECT * FROM post_analysis_summary WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Data exists, return it
        $existing['averages'] = json_decode($existing['averages_json'], true);
        unset($existing['averages_json']);
        echo json_encode(["success" => true, "source" => "db", "summary" => $existing]);
        exit;
    }

    // Call Flask API for full post-analysis
    // Use live HTTPS API to ensure reliable connection
    $flaskUrl = 'https://internconnect-kjzb.onrender.com/api/post_analysis.php';
    $isLocal = ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
    
    $flaskData = ["student_id" => $student_id];
    $fullUrl = $flaskUrl . '?' . http_build_query($flaskData);
    
    // Log the request details
    error_log("Flask API Debug - URL: " . $fullUrl);
    error_log("Flask API Debug - Is Local: " . ($isLocal ? 'true' : 'false'));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $flaskResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log the response details
    error_log("Flask API Debug - HTTP Code: " . $httpCode);
    error_log("Flask API Debug - cURL Error: " . $curlError);
    error_log("Flask API Debug - Response: " . substr($flaskResponse, 0, 500));
    
    $flaskJson = json_decode($flaskResponse, true);
    if (!$flaskJson || !isset($flaskJson['post_assessment_averages'])) {
        echo json_encode([
            "success" => false, 
            "error" => "Flask API error or missing data.",
            "debug" => [
                "url" => $fullUrl,
                "http_code" => $httpCode,
                "curl_error" => $curlError,
                "response" => substr($flaskResponse, 0, 200),
                "is_local" => $isLocal
            ]
        ]);
        exit;
    }

    // Save all fields to summary table
    $insert = $conn->prepare("INSERT INTO post_analysis_summary (student_id, placement, reasoning, supervisor_comment, comparative_analysis, strengths_post_assessment, correlation_analysis, conclusion_recommendation, averages_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $insert->execute([
        $student_id,
        $flaskJson['placement'] ?? null,
        $flaskJson['reasoning'] ?? null,
        $flaskJson['supervisor_comment'] ?? null,
        $flaskJson['comparative_analysis'] ?? null,
        $flaskJson['strengths_post_assessment'] ?? null,
        $flaskJson['correlation_analysis'] ?? null,
        $flaskJson['conclusion_recommendation'] ?? null,
        json_encode($flaskJson['post_assessment_averages'])
    ]);
    // Return the inserted data
    $stmt = $conn->prepare("SELECT * FROM post_analysis_summary WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
    $inserted['averages'] = json_decode($inserted['averages_json'], true);
    unset($inserted['averages_json']);
    echo json_encode(["success" => true, "source" => "generated", "summary" => $inserted]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

