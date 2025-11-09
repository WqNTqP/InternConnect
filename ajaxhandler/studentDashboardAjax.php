<?php
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$path = dirname(__FILE__) . '/../';
require_once $path . "database/database.php";

error_log("Full path to database.php: " . $path . "database/database.php");

function sendResponse($status, $data, $message = '') {
    echo json_encode(array("status" => $status, "data" => $data, "message" => $message));
    exit;
}

function logError($message) {
    error_log(date('[Y-m-d H:i] ') . "ERROR: " . $message . "\n", 3, 'error.log');
}

try {
    $dbo = new Database();
} catch (Exception $e) {
    logError("Database connection failed: " . $e->getMessage());
    sendResponse('error', null, 'Database connection failed');
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if (empty($action)) {
    sendResponse('error', null, 'No action specified');
}

switch ($action) {
    case "getStudentsForReview":
        try {
            // Get all students who have at least one rated evaluation
            $stmt = $dbo->conn->prepare("
                SELECT DISTINCT i.INTERNS_ID as id, i.NAME, i.SURNAME
                FROM interns_details i
                JOIN student_evaluation se ON se.STUDENT_ID = i.STUDENT_ID
                JOIN coordinator_evaluation ce ON ce.student_evaluation_id = se.id AND ce.STUDENT_ID = se.STUDENT_ID
            ");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = array();
            foreach ($students as $student) {
                $result[] = array(
                    'id' => $student['id'],
                    'name' => $student['NAME'] . ' ' . $student['SURNAME']
                );
            }
            echo json_encode(array('success' => true, 'students' => $result));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'students' => [], 'message' => 'Database error: ' . $e->getMessage()));
        }
        exit;
    case "getPreassessmentEvaluation":
        $internsId = isset($_POST['studentId']) ? $_POST['studentId'] : null;
        if (!$internsId) {
            echo json_encode(['success' => false, 'message' => 'No studentId provided']);
            exit;
        }
        try {
            // Map INTERNS_ID to STUDENT_ID
            $stmtMap = $dbo->conn->prepare("SELECT STUDENT_ID FROM interns_details WHERE INTERNS_ID = ? LIMIT 1");
            $stmtMap->execute([$internsId]);
            $row = $stmtMap->fetch(PDO::FETCH_ASSOC);
            if (!$row || !$row['STUDENT_ID']) {
                echo json_encode(['success' => true, 'evaluations' => [], 'isRated' => false]);
                exit;
            }
            $studentId = $row['STUDENT_ID'];

            // Get all evaluation answers for this student
            $stmt = $dbo->conn->prepare("SELECT se.id as id, se.id as student_evaluation_id, eq.question_text, se.answer FROM student_evaluation se JOIN evaluation_questions eq ON se.question_id = eq.question_id WHERE se.STUDENT_ID = ?");
            $stmt->execute([$studentId]);
            $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check if all answers have been rated by coordinator
            $isRated = false;
            if (count($evaluations) > 0) {
                $evalIds = array_column($evaluations, 'id');
                $placeholders = implode(',', array_fill(0, count($evalIds), '?'));
                $stmt2 = $dbo->conn->prepare("SELECT COUNT(*) as rated_count FROM coordinator_evaluation WHERE student_evaluation_id IN ($placeholders) AND STUDENT_ID = ?");
                $stmt2->execute(array_merge($evalIds, [$studentId]));
                $ratedCount = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($ratedCount && intval($ratedCount['rated_count']) === count($evalIds)) {
                    $isRated = true;
                }
            }

            echo json_encode([
                'success' => true,
                'evaluations' => $evaluations,
                'isRated' => $isRated
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    case "getStudentsForPreassessment":
        try {
            $stmt = $dbo->conn->prepare("SELECT INTERNS_ID as id, STUDENT_ID, NAME, SURNAME FROM interns_details");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = array();
            foreach ($students as $student) {
                $result[] = array(
                    'id' => $student['id'],
                    'STUDENT_ID' => $student['STUDENT_ID'],
                    'name' => $student['NAME'] . ' ' . $student['SURNAME']
                );
            }
            echo json_encode(array('success' => true, 'students' => $result));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'students' => [], 'message' => 'Database error: ' . $e->getMessage()));
        }
        exit;
    case "getStudentProfile":
        $studentId = $_POST['studentId'] ?? null;
        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
        }
        try {
            // Fetch main student info
            $stmt = $dbo->conn->prepare("SELECT INTERNS_ID, STUDENT_ID, NAME, SURNAME, AGE, GENDER, EMAIL, CONTACT_NUMBER, profile_picture FROM interns_details WHERE INTERNS_ID = ?");
            $stmt->execute([$studentId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($profile) {
                // Fetch HTE_ID from intern_details
                $stmt2 = $dbo->conn->prepare("SELECT HTE_ID FROM intern_details WHERE INTERNS_ID = ? LIMIT 1");
                $stmt2->execute([$studentId]);
                $internDetail = $stmt2->fetch(PDO::FETCH_ASSOC);
                $hteName = 'N/A';
                if ($internDetail && !empty($internDetail['HTE_ID'])) {
                    $hteId = $internDetail['HTE_ID'];
                    // Try to fetch HTE name from hte/building table
                    $stmt3 = $dbo->conn->prepare("SELECT NAME FROM host_training_establishment WHERE HTE_ID = ? LIMIT 1");
                    $stmt3->execute([$hteId]);
                    $hteRow = $stmt3->fetch(PDO::FETCH_ASSOC);
                    if ($hteRow && !empty($hteRow['NAME'])) {
                        $hteName = $hteRow['NAME'];
                    }
                }
                $profile['HTE_NAME'] = $hteName;
                sendResponse('success', $profile, 'Student profile retrieved successfully');
            } else {
                sendResponse('error', null, 'Student profile not found');
            }
        } catch (Exception $e) {
            logError("Error retrieving student profile: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving student profile');
        }
        break;
    case "getStudentQuestions":
        $studentId = $_POST['studentId'] ?? null;
        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
        }
        try {
            $stmt = $dbo->conn->prepare("SELECT id AS question_id, category, question_text, question_number FROM student_questions WHERE student_id = ? ORDER BY category, question_number");
            $stmt->execute([$studentId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse('success', $questions, 'Questions loaded');
        } catch (Exception $e) {
            logError("Error loading student questions: " . $e->getMessage());
            sendResponse('error', null, 'Error loading questions');
        }
        break;
    case "getDashboardStats":
        $studentId = $_POST['studentId'] ?? null;
        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
        }
        try {
            // Get present days this week
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            
            $stmt = $dbo->conn->prepare("
                SELECT COUNT(*) as presentDays 
                FROM interns_attendance 
                WHERE INTERNS_ID = ? 
                AND ON_DATE BETWEEN ? AND ? 
                AND TIMEIN IS NOT NULL 
                AND TIMEOUT IS NOT NULL
            ");
            $stmt->execute([$studentId, $weekStart, $weekEnd]);
            $presentDays = $stmt->fetch(PDO::FETCH_ASSOC)['presentDays'] ?? 0;

            // Get total hours
            $stmt = $dbo->conn->prepare("
                SELECT SUM(ABS(TIMESTAMPDIFF(HOUR, TIMEIN, TIMEOUT))) as totalHours 
                FROM interns_attendance 
                WHERE INTERNS_ID = ? 
                AND TIMEIN IS NOT NULL 
                AND TIMEOUT IS NOT NULL
            ");
            $stmt->execute([$studentId]);
            $totalHours = $stmt->fetch(PDO::FETCH_ASSOC)['totalHours'] ?? 0;

            // Get overall attendance rate
            $stmt = $stmt = $dbo->conn->prepare("
                SELECT COUNT(*) as totalDays 
                FROM interns_attendance 
                WHERE INTERNS_ID = ? 
                AND TIMEIN IS NOT NULL 
                AND TIMEOUT IS NOT NULL
            ");
            $stmt->execute([$studentId]);
            $totalPresent = $stmt->fetch(PDO::FETCH_ASSOC)['totalDays'] ?? 0;

            // Calculate total possible days (assuming 5 days per week)
            $startDate = $dbo->conn->prepare("
                SELECT MIN(ON_DATE) as startDate 
                FROM interns_attendance 
                WHERE INTERNS_ID = ?
            ");
            $startDate->execute([$studentId]);
            $startDateResult = $startDate->fetch(PDO::FETCH_ASSOC);
            $startDate = $startDateResult['startDate'] ?? date('Y-m-d');
            
            $totalWeeks = ceil((time() - strtotime($startDate)) / (7 * 24 * 3600));
            $totalPossibleDays = $totalWeeks * 5; // Assuming 5 working days per week
            
            $attendanceRate = $totalPossibleDays > 0 ? round(($totalPresent / $totalPossibleDays) * 100, 2) : 0;

            sendResponse('success', [
                'presentDays' => $presentDays,
                'totalHours' => round($totalHours, 1),
                'attendanceRate' => $attendanceRate
            ], 'Dashboard stats retrieved successfully');
        } catch (Exception $e) {
            logError("Error retrieving dashboard stats: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving dashboard stats');
        }
        break;

    case "getRecentActivity":
        $studentId = $_POST['studentId'] ?? null;
        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
        }
        try {
            // Fetch recent activities for the student
            // For demonstration, fetch last 5 attendance records ordered by date descending
            $stmt = $dbo->conn->prepare("
                SELECT ON_DATE, TIMEIN, TIMEOUT 
                FROM interns_attendance 
                WHERE INTERNS_ID = ? 
                ORDER BY ON_DATE DESC 
                LIMIT 5
            ");
            $stmt->execute([$studentId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $activities = [];
            foreach ($records as $record) {
                $date = date('F j, Y', strtotime($record['ON_DATE']));
                if ($record['TIMEIN'] && $record['TIMEOUT']) {
                    $description = "Present on {$date} from {$record['TIMEIN']} to {$record['TIMEOUT']}";
                    $icon = "check-circle";
                } elseif ($record['TIMEIN']) {
                    $description = "Checked in on {$date} at {$record['TIMEIN']}";
                    $icon = "clock";
                } else {
                    $description = "No attendance record on {$date}";
                    $icon = "times-circle";
                }
                $activities[] = [
                    "icon" => $icon,
                    "description" => $description,
                    "time" => $date
                ];
            }

            sendResponse('success', $activities, 'Recent activity retrieved successfully');
        } catch (Exception $e) {
            logError("Error retrieving recent activity: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving recent activity');
        }
        break;

    case "updateStudentProfile":
        $studentId = $_POST['studentId'] ?? null;
        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
        }
        
        // Prepare to handle file upload
        $profilePicturePath = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
            $fileName = $_FILES['profile_picture']['name'];
            $fileSize = $_FILES['profile_picture']['size'];
            $fileType = $_FILES['profile_picture']['type'];
            $allowedFileTypes = ['image/jpeg', 'image/png', 'image/gif'];

            // Validate file type
            if (in_array($fileType, $allowedFileTypes)) {
                // Set the upload directory
                $uploadFileDir = '../uploads/';
                
                // Create uploads directory if it doesn't exist
                if (!file_exists($uploadFileDir)) {
                    mkdir($uploadFileDir, 0777, true);
                }
                
                // Generate unique filename to prevent overwrites
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueFileName = uniqid() . '_' . $studentId . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $uniqueFileName;

                // Move the file to the upload directory
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Store only the filename in the database
                    $profilePicturePath = $uniqueFileName;
                } else {
                    sendResponse('error', null, 'Error moving the uploaded file');
                }
            } else {
                sendResponse('error', null, 'Invalid file type. Only JPEG, PNG, and GIF files are allowed.');
            }
        } else {
            sendResponse('error', null, 'No profile picture file uploaded or file upload error');
        }

        try {
            $stmt = $dbo->conn->prepare("UPDATE interns_details SET profile_picture = ? WHERE INTERNS_ID = ?");
            $stmt->execute([$profilePicturePath, $studentId]);
            sendResponse('success', null, 'Profile picture updated successfully');
        } catch (Exception $e) {
            logError("Error updating student profile picture: " . $e->getMessage());
            sendResponse('error', null, 'Error updating profile picture');
        }
        break;

    case "getStudentDetails":
        $studentId = $_POST['studentId'] ?? null;
        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
        }
        try {
            $stmt = $dbo->conn->prepare("
                SELECT id.*, hte.NAME as HTE_NAME 
                FROM interns_details id 
                LEFT JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID 
                LEFT JOIN host_training_establishment hte ON itd.HTE_ID = hte.HTE_ID 
                WHERE id.INTERNS_ID = ?
            ");
            $stmt->execute([$studentId]);
            $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($studentDetails) {
                sendResponse('success', $studentDetails, 'Student details retrieved successfully');
            } else {
                sendResponse('error', null, 'Student not found');
            }
        } catch (Exception $e) {
            logError("Error retrieving student details: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving student details');
        }
        break;

        case "getAttendanceStatus":
            $studentId = $_POST['studentId'] ?? null;
            $date = $_POST['date'] ?? date('Y-m-d');
            if (!$studentId) {
                sendResponse('error', null, 'Student ID is required');
            }
            try {
                // First check for approved attendance in interns_attendance table
                $stmt = $dbo->conn->prepare("
                    SELECT ON_DATE, TIMEIN, TIMEOUT
                    FROM interns_attendance
                    WHERE INTERNS_ID = ? AND ON_DATE = ?
                ");
                $stmt->execute([$studentId, $date]);
                $attendanceData = $stmt->fetch(PDO::FETCH_ASSOC);

                // Also check for any pending attendance for today (regardless of status)
                $stmtPending = $dbo->conn->prepare("
                    SELECT ON_DATE, TIMEIN, TIMEOUT, STATUS
                    FROM pending_attendance
                    WHERE INTERNS_ID = ? AND ON_DATE = ?
                ");
                $stmtPending->execute([$studentId, $date]);
                $pendingData = $stmtPending->fetch(PDO::FETCH_ASSOC);

                // Initialize with today's date
                $finalData = ['ON_DATE' => $date, 'TIMEIN' => null, 'TIMEOUT' => null];

                // Prioritize approved attendance, but show any existing attendance data
                if ($attendanceData && ($attendanceData['TIMEIN'] || $attendanceData['TIMEOUT'])) {
                    // Use approved attendance data if it exists
                    $finalData['TIMEIN'] = $attendanceData['TIMEIN'];
                    $finalData['TIMEOUT'] = $attendanceData['TIMEOUT'];
                } elseif ($pendingData && ($pendingData['TIMEIN'] || $pendingData['TIMEOUT'])) {
                    // Use pending attendance data if no approved attendance exists
                    $finalData['TIMEIN'] = $pendingData['TIMEIN'];
                    $finalData['TIMEOUT'] = $pendingData['TIMEOUT'];
                }

                sendResponse('success', $finalData, 'Attendance status retrieved successfully');
            } catch (Exception $e) {
                logError("Error retrieving attendance status: " . $e->getMessage());
                sendResponse('error', null, 'Error retrieving attendance status');
            }
        break;

            
    case "getAttendanceHistory":
        $studentId = $_POST['studentId'] ?? null;
        $startDate = $_POST['startDate'] ?? null;
        $endDate = $_POST['endDate'] ?? null;

        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
            break;
        }

        try {
            // Base query to fetch attendance for the student (one record per date with latest times)
            $query = "SELECT ON_DATE, MAX(TIMEIN) as TIMEIN, MAX(TIMEOUT) as TIMEOUT FROM interns_attendance WHERE INTERNS_ID = ?";
            $params = [$studentId];

            // Check if startDate and endDate are provided and add to query
            if ($startDate && $endDate) {
                $query .= " AND ON_DATE BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $query .= " GROUP BY ON_DATE ORDER BY ON_DATE DESC";

            // Execute the query
            $stmt = $dbo->conn->prepare($query);
            $stmt->execute($params);

            // Fetch results
            $attendanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Also check pending attendance for the date range (one record per date with latest times)
            $stmtPending = $dbo->conn->prepare("
                SELECT ON_DATE, MAX(TIMEIN) as TIMEIN, MAX(TIMEOUT) as TIMEOUT
                FROM pending_attendance
                WHERE INTERNS_ID = ? AND STATUS = 'approved'
                " . ($startDate && $endDate ? "AND ON_DATE BETWEEN ? AND ?" : "") . "
                GROUP BY ON_DATE ORDER BY ON_DATE DESC
            ");
            $paramsPending = [$studentId];
            if ($startDate && $endDate) {
                $paramsPending[] = $startDate;
                $paramsPending[] = $endDate;
            }
            $stmtPending->execute($paramsPending);
            $pendingRecords = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

            // Merge pending records with main attendance history, avoiding duplicates by date
            if (!empty($pendingRecords)) {
                $existingDates = [];
                foreach ($attendanceHistory as $record) {
                    $existingDates[$record['ON_DATE']] = true;
                }

                foreach ($pendingRecords as $pending) {
                    if (!isset($existingDates[$pending['ON_DATE']])) {
                        $attendanceHistory[] = $pending;
                        $existingDates[$pending['ON_DATE']] = true;
                    }
                }
            }

            // Sort the entire attendanceHistory descending by ON_DATE and TIMEIN
            usort($attendanceHistory, function($a, $b) {
                // Handle null ON_DATE (month headers) by placing them at the start
                if ($a['ON_DATE'] === null) return -1;
                if ($b['ON_DATE'] === null) return 1;

                // Compare dates first (descending)
                $dateCompare = strcmp($b['ON_DATE'], $a['ON_DATE']);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                // If dates are same, compare TIMEIN (descending), handle nulls
                $timeA = $a['TIMEIN'] ?? '00:00:00';
                $timeB = $b['TIMEIN'] ?? '00:00:00';
                return strcmp($timeB, $timeA);
            });

            // Records are already sorted by date descending, no need for month grouping

            // Return results
            sendResponse('success', $attendanceHistory, 'Attendance history retrieved successfully');
        } catch (Exception $e) {
            // Handle errors gracefully
            logError("Error retrieving attendance history: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving attendance history');
        }
    break;

            case "recordPendingAttendance":
                $studentId = $_POST['studentId'] ?? null; // INTERNS_ID
                $type = $_POST['type'] ?? null; // This will be 'timein' or 'timeout'
                $hteId = $_POST['hteId'] ?? null; // HTE_ID
                $currentDate = date('Y-m-d'); // Current date
                $currentTime = date('H:i'); // Current time
            
                // Validate required parameters
                if (!$studentId || !$type || !$hteId) {
                    sendResponse('error', null, 'Missing required parameters');
                }
            
                try {
                    // Check for existing record for today
                    $stmt = $dbo->conn->prepare("SELECT * FROM pending_attendance WHERE INTERNS_ID = ? AND ON_DATE = ?");
                    $stmt->execute([$studentId, $currentDate]);
                    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
                    if ($type == 'timein') {
                        if (!$existingRecord) {
                            // No existing record, insert new timein
                            $stmt = $dbo->conn->prepare("INSERT INTO pending_attendance (INTERNS_ID, HTE_ID, ON_DATE, TIMEIN, TIMEOUT, STATUS) VALUES (?, ?, ?, ?, ?, 'pending')");
                            $stmt->execute([$studentId, $hteId, $currentDate, $currentTime, null]);
                            error_log("Inserted new timein record");
                        } else {
                            // If timein already exists, return an error
                            sendResponse('error', null, 'Time In already recorded for today');
                        }
                    } elseif ($type == 'timeout') {
                        if ($existingRecord && $existingRecord['TIMEIN']) {
                            // Update existing record with timeout
                            $stmt = $dbo->conn->prepare("UPDATE pending_attendance SET TIMEOUT = ?, updated_at = NOW() WHERE INTERNS_ID = ? AND ON_DATE = ?");
                            $stmt->execute([$currentTime, $studentId, $currentDate]);
                            error_log("Updated timeout for existing record");
                        } else {
                            // If no timein exists, return an error
                            sendResponse('error', null, 'No Time In record found for today. Please record Time In first.');
                        }
                    }
            
                    sendResponse('success', null, 'Attendance recorded successfully');
                } catch (Exception $e) {
                    sendResponse('error', null, 'Database error: ' . $e->getMessage());
                }
            break;

    case "getLatestReportWeek":
        $studentId = $_POST['studentId'] ?? null;
        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
        }
        try {
            // Calculate the current week's Monday and Sunday dates
            $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
            $currentWeekEnd = date('Y-m-d', strtotime('sunday this week'));

            // Check if student has any submitted reports to determine if they should submit for current week
            $stmt = $dbo->conn->prepare("
                SELECT COUNT(*) as reportCount
                FROM weekly_reports
                WHERE interns_id = ? AND status = 'submitted'
                AND week_start = ? AND week_end = ?
            ");
            $stmt->execute([$studentId, $currentWeekStart, $currentWeekEnd]);
            $currentWeekReport = $stmt->fetch(PDO::FETCH_ASSOC);

            $responseData = [
                'week_start' => $currentWeekStart,
                'week_end' => $currentWeekEnd,
                'has_current_week_report' => ($currentWeekReport['reportCount'] > 0)
            ];

            sendResponse('success', $responseData, 'Current week dates retrieved successfully');
        } catch (Exception $e) {
            logError("Error retrieving current week dates: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving current week dates');
        }
        break;

    case "getEarliestAttendanceYear":
        try {
            // Get the earliest year from attendance records
            $stmt = $dbo->conn->prepare("
                SELECT MIN(YEAR(ON_DATE)) as earliest_year
                FROM interns_attendance
                WHERE ON_DATE IS NOT NULL
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $earliestYear = $result['earliest_year'] ?? date('Y');

            sendResponse('success', ['earliest_year' => $earliestYear], 'Earliest attendance year retrieved successfully');
        } catch (Exception $e) {
            logError("Error retrieving earliest attendance year: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving earliest attendance year');
        }
        break;

    case "updatePassword":
        $studentId = $_POST['studentId'] ?? null;
        $currentPassword = $_POST['currentPassword'] ?? null;
        $newPassword = $_POST['newPassword'] ?? null;
        $confirmPassword = $_POST['confirmPassword'] ?? null;

        if (!$studentId || !$currentPassword || !$newPassword || !$confirmPassword) {
            sendResponse('error', null, 'All password fields are required');
        }

        if ($newPassword !== $confirmPassword) {
            sendResponse('error', null, 'New password and confirmation do not match');
        }

        // Verify current password first
        try {
            require_once $path . "database/student.php";
            $sdo = new Student();

            // Get student details including STUDENT_ID for temporary password verification
            $stmt = $dbo->conn->prepare("SELECT EMAIL, STUDENT_ID, PASSWORD FROM interns_details WHERE INTERNS_ID = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                sendResponse('error', null, 'Student not found');
            }

            // Verify current password - check both actual password and temporary STUDENT_ID password
            $isValid = false;

            // Debug logging
            error_log("Current password verification:");
            error_log("PASSWORD field: " . json_encode($student['PASSWORD']));
            error_log("STUDENT_ID: " . json_encode($student['STUDENT_ID']));
            error_log("Input currentPassword: " . json_encode($currentPassword));

            if ($student['PASSWORD'] === null || $student['PASSWORD'] === '') {
                // If no password is set, use student ID as temporary password
                error_log("No password set, checking against STUDENT_ID");
                if ($currentPassword === $student['STUDENT_ID']) {
                    $isValid = true;
                    error_log("Password matches STUDENT_ID");
                } else {
                    error_log("Password does not match STUDENT_ID");
                    error_log("Comparison: '" . $currentPassword . "' vs '" . $student['STUDENT_ID'] . "'");
                }
            } else {
                // If password is set, use the actual password
                error_log("Password set, checking against stored password");
                if ($currentPassword === $student['PASSWORD']) {
                    $isValid = true;
                    error_log("Password matches stored password");
                } else {
                    error_log("Password does not match stored password");
                    error_log("Comparison: '" . $currentPassword . "' vs '" . $student['PASSWORD'] . "'");
                }
            }



            // Update password
            $result = $sdo->updatePassword($dbo, $studentId, $newPassword);

            if ($result['success']) {
                sendResponse('success', null, $result['message']);
            } else {
                sendResponse('error', null, $result['message']);
            }
        } catch (Exception $e) {
            logError("Error updating password: " . $e->getMessage());
            sendResponse('error', null, 'Error updating password');
        }
        break;
            
                case "getRecentReportStatus":
        $studentId = $_POST['studentId'] ?? null;
        if (!$studentId) {
            sendResponse('error', null, 'Student ID is required');
        }
        try {
            // Get recent weekly reports for this student, this month
            $currentMonth = date('m');
            $currentYear = date('Y');
            $stmt = $dbo->conn->prepare("SELECT report_id, week_start, week_end, status, created_at, updated_at, approval_status, approved_at FROM weekly_reports WHERE interns_id = ? AND ((MONTH(created_at) = ? AND YEAR(created_at) = ?) OR (MONTH(approved_at) = ? AND YEAR(approved_at) = ?)) ORDER BY created_at DESC, approved_at DESC LIMIT 5");
            $stmt->execute([$studentId, $currentMonth, $currentYear, $currentMonth, $currentYear]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($reports as $report) {
                $result[] = [
                    'week_start' => $report['week_start'],
                    'week_end' => $report['week_end'],
                    'status' => $report['status'],
                    'approval_status' => $report['approval_status'],
                    'created_at' => $report['created_at'],
                    'updated_at' => $report['updated_at'],
                    'approved_at' => $report['approved_at']
                ];
            }
            sendResponse('success', $result, 'Recent report status retrieved successfully');
        } catch (Exception $e) {
            logError("Error retrieving recent report status: " . $e->getMessage());
            sendResponse('error', null, 'Error retrieving recent report status');
        }
        break;

    case "getAllStudents":
        try {
            $stmt = $dbo->conn->prepare("SELECT INTERNS_ID as student_id, NAME, SURNAME FROM interns_details");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = array();
            foreach ($students as $student) {
                $result[] = array(
                    'student_id' => $student['student_id'],
                    'name' => $student['NAME'] . ' ' . $student['SURNAME']
                );
            }
            sendResponse('success', $result);
        } catch (Exception $e) {
            sendResponse('error', null, 'Database error: ' . $e->getMessage());
        }
        break;
}
?>

