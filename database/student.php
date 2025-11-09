<?php
class Student {
    public function verifyStudent($dbo, $email, $password) {
        $rv = ["id" => -1, "status" => "ERROR", "message" => "Invalid email or password"];

        // First, find the student by email
        $c = "SELECT INTERNS_ID, STUDENT_ID, NAME, PASSWORD FROM interns_details WHERE EMAIL = :email";
        $s = $dbo->conn->prepare($c);
        try {
            $s->execute([":email" => $email]);
            if ($s->rowCount() > 0) {
                $result = $s->fetch(PDO::FETCH_ASSOC);
                
                // Debug logging
                error_log("Student found: " . json_encode($result));
                error_log("Input password: $password");
                
                // Trim and handle potential whitespace issues
                $inputPassword = trim($password);
                $storedStudentId = trim($result['STUDENT_ID']);
                $storedPassword = $result['PASSWORD'] !== null ? trim($result['PASSWORD']) : null;

                // Debug logging
                error_log("Stored Password: " . json_encode($storedPassword));
                error_log("Stored Student ID: " . json_encode($storedStudentId));
                error_log("Input Password: " . json_encode($inputPassword));
                
                // Check if password is provided and matches either the stored password or student ID
                error_log("Checking password for email: $email");
                $isValid = false;
                
                if ($storedPassword === null || $storedPassword === '') {
                    // If no password is set, use student ID as temporary password
                    error_log("No password set, checking against STUDENT_ID: '" . $storedStudentId . "'");
                    error_log("Input password: '" . $inputPassword . "'");
                    if ($inputPassword === $storedStudentId) {
                        $isValid = true;
                        error_log("Password matches STUDENT_ID");
                    } else {
                        error_log("Password does not match STUDENT_ID");
                        error_log("Comparison: '" . $inputPassword . "' vs '" . $storedStudentId . "'");
                    }
                } else {
                    // If password is set, use the actual password
                    error_log("Password set, checking against stored password: '" . $storedPassword . "'");
                    error_log("Input password: '" . $inputPassword . "'");
                    if ($inputPassword === $storedPassword) {
                        $isValid = true;
                        error_log("Password matches stored password");
                    } else {
                        error_log("Password does not match stored password");
                        error_log("Comparison: '" . $inputPassword . "' vs '" . $storedPassword . "'");
                    }
                }
                
                if ($isValid) {
                    $rv = [
                        "id" => $result['INTERNS_ID'],
                        "name" => $result['NAME'],
                        "status" => "ALL OK"
                    ];
                } else {
                    $rv["message"] = "Invalid password";
                }
            } else {
                $rv["message"] = "No student found with this email";
            }
        } catch (PDOException $e) {
            $rv["message"] = "Database error: " . $e->getMessage();
        }
        return $rv;
    }
    
    public function updatePassword($dbo, $studentId, $newPassword) {
        try {
            $stmt = $dbo->conn->prepare("UPDATE interns_details SET PASSWORD = ? WHERE INTERNS_ID = ?");
            $stmt->execute([$newPassword, $studentId]);
            
            if ($stmt->rowCount() > 0) {
                return ["success" => true, "message" => "Password updated successfully"];
            } else {
                return ["success" => false, "message" => "Failed to update password or student not found"];
            }
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }
}
?>

