<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json'); // Ensure the response is in JSON format

$path = $_SERVER['DOCUMENT_ROOT'];
require_once $path . "/InternConnect/database/database.php"; // Include your database connection
require_once $path . "/InternConnect/database/attendanceDetails.php"; // Include attendanceDetails.php

// Log the request method and request URI
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);

// Log the entire $_FILES array
error_log("FILES Array: " . print_r($_FILES, true)); // Log the $_FILES array to the error log

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the 'csvFile' key exists in the $_FILES array
    if (!isset($_FILES['csvFile'])) {
        error_log("Error: No file key 'csvFile' was found in the request.");
        echo json_encode(['success' => false, 'message' => 'No file key "csvFile" was found in the request. Please ensure your form is set up correctly.']);
        exit;
    }

    // Check if a file was uploaded
    if ($_FILES['csvFile']['error'] === UPLOAD_ERR_NO_FILE) {
        error_log("Error: No file was uploaded. Check if a file is selected before submitting.");
        echo json_encode(['success' => false, 'message' => 'No file was uploaded. Please select a file to upload.']);
        exit;
    } elseif ($_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
        error_log("File upload error: " . $_FILES['csvFile']['error']);
        echo json_encode(['success' => false, 'message' => 'File upload error: ' . $_FILES['csvFile']['error']]);
        exit;
    }

    $fileTmpName = $_FILES['csvFile']['tmp_name'];
    $fileName = $_FILES['csvFile']['name'];
    $fileSize = $_FILES['csvFile']['size'];
    $fileType = $_FILES['csvFile']['type'];

    // Log file details
    error_log("Uploaded File Details: Name: $fileName, Size: $fileSize, Type: $fileType");

    // Check if the uploaded file is a CSV
    if ($fileType !== 'text/csv' && $fileType !== 'application/csv') {
        error_log("Error: Only CSV files are allowed.");
        echo json_encode(['success' => false, 'message' => 'Only CSV files are allowed.']);
        exit;
    }

    // Retrieve additional parameters from POST
    $coordinator_id = $_POST['coordinator_id'] ?? null;
    $hte_id = $_POST['hte_id'] ?? null;
    $session_id = $_POST['session_id'] ?? null;

    // Validate session_id
    if (empty($session_id) || !is_numeric($session_id)) {
        error_log("Error: Invalid session_id provided.");
        echo json_encode(['success' => false, 'message' => 'Invalid session ID. Please ensure it is selected correctly.']);
        exit;
    }

    // Attempt to open the file
    if (($handle = fopen($fileTmpName, "r")) !== FALSE) {
        $inserted = 0;
        $skipped = 0;

        // Skip the header row
        fgetcsv($handle); // No need to specify delimiter as it is a comma

        // Create Database instance
        $dbo = new Database();
        
        // Create an instance of attendanceDetails
        $attendanceDetails = new attendanceDetails();

        // Process the file data and insert students
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 26) { // 6 personal + 20 course grades
                error_log("Skipping row: insufficient columns " . print_r($data, true));
                $skipped++;
                continue;
            }

            $student_id = $data[0];
            $name = $data[1];
            $surname = $data[2];
            $age = $data[3];
            $gender = $data[4];
            // Convert gender to 'Male'/'Female' if needed
            if (strtoupper($gender) === 'M') {
                $gender = 'Male';
            } elseif (strtoupper($gender) === 'F') {
                $gender = 'Female';
            }
            $email = $data[5];
            $contact_number = $data[6];

            // Parse course grades
            $course_columns = [
                'CC 102', 'CC 103', 'PF 101', 'CC 104', 'IPT 101', 'IPT 102', 'CC 106', 'CC 105',
                'IM 101', 'IM 102', 'HCI 101', 'HCI 102', 'WS 101', 'NET 101', 'NET 102',
                'IAS 101', 'IAS 102', 'CAP 101', 'CAP 102', 'SP 101'
            ];
            $grades = [];
            for ($i = 0; $i < count($course_columns); $i++) {
                $grades[$course_columns[$i]] = $data[7 + $i] ?? null;
            }

            // Add student and track the result
            $result = $attendanceDetails->addStudent($dbo, $student_id, $name, $surname, $age, $gender, $email, $contact_number, $coordinator_id, $hte_id, $session_id, $grades);
            if ($result) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
        

        fclose($handle);

        // Return the result
        echo json_encode(['success' => true, 'inserted' => $inserted, 'skipped' => $skipped]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to open the uploaded file.']);
    }
}
