<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// $response = ['success' => true, 'message' => 'Student added successfully!'];

// echo json_encode($response);
// exit;


class attendanceDetails
{
    // Update HTE logo by HTE_ID
    public function updateHTELogo($dbo, $hteId, $logo_filename) {
        try {
            $stmt = $dbo->conn->prepare("UPDATE host_training_establishment SET LOGO = :logo WHERE HTE_ID = :hteId");
            $stmt->execute([':logo' => $logo_filename, ':hteId' => $hteId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error updating HTE logo: ' . $e->getMessage());
            return false;
        }
    }

    public function getStudentsByHteId($dbo, $hteId) {
        try {
            $stmt = $dbo->conn->prepare("SELECT id.INTERNS_ID, id.STUDENT_ID, id.NAME, id.GENDER, id.EMAIL, id.CONTACT_NUMBER 
                                          FROM interns_details AS id 
                                          JOIN intern_details AS itd ON id.INTERNS_ID = itd.INTERNS_ID 
                                          WHERE itd.HTE_ID = :hteId");
            $stmt->execute([':hteId' => $hteId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Error: " . $e->getMessage()];
        }
    }
    
    


    public function getStudentById($dbo, $studentId) {
        try {
            // Debugging: check if studentId is passed correctly
            echo "Student ID received: " . $studentId . "<br>";
    
            $stmt = $dbo->prepare("SELECT * FROM interns_details WHERE STUDENT_ID = :studentId");
            $stmt->execute(['studentId' => $studentId]);
    
            // Debugging: check if the query was executed and if any result is fetched
            if ($stmt->rowCount() > 0) {
                $student = $stmt->fetch(); // Fetch the student data
                // Debugging: log or print the student data
                echo "<pre>";
                print_r($student);
                echo "</pre>";
                return $student;
            } else {
                // Debugging: check when no result is found
                echo "No student found with ID: " . $studentId . "<br>";
                return false;
            }
        } catch (PDOException $e) {
            // Log the exception message for debugging
            echo "Error: " . $e->getMessage();
            return false;
        }
    }
    
       



    public function deleteStudent($dbo, $studentId) {
        try {
            // Start transaction
            $dbo->conn->beginTransaction();
            
            // Get INTERNS_ID first as we'll need it for related deletions
            $stmt = $dbo->conn->prepare("SELECT INTERNS_ID FROM interns_details WHERE STUDENT_ID = :studentId");
            $stmt->execute([":studentId" => $studentId]);
            $internId = $stmt->fetchColumn();
            
            if (!$internId) {
                throw new PDOException("Student not found");
            }

            // Delete from notifications where student is receiver
            $stmt = $dbo->conn->prepare("DELETE FROM notifications WHERE receiver_id = :internId");
            $stmt->execute([":internId" => $internId]);
            
            // Delete from notifications where student is sender (if exists)
            $stmt = $dbo->conn->prepare("DELETE FROM notifications WHERE sender_id = :internId");
            $stmt->execute([":internId" => $internId]);

            // Delete from student_evaluation
            $stmt = $dbo->conn->prepare("DELETE FROM student_evaluation WHERE STUDENT_ID = :studentId");
            $stmt->execute([":studentId" => $studentId]);
            
            // Delete from interns_attendance
            $stmt = $dbo->conn->prepare("DELETE FROM interns_attendance WHERE INTERNS_ID = :internId");
            $stmt->execute([":internId" => $internId]);
            
            // Delete from pending_attendance
            $stmt = $dbo->conn->prepare("DELETE FROM pending_attendance WHERE INTERNS_ID = :internId");
            $stmt->execute([":internId" => $internId]);
            
            // Delete from student_questions
            $stmt = $dbo->conn->prepare("DELETE FROM student_questions WHERE student_id = :internId");
            $stmt->execute([":internId" => $internId]);
            
            // Delete from report_images (weekly report images)
            $stmt = $dbo->conn->prepare("DELETE FROM report_images WHERE report_id IN (SELECT report_id FROM weekly_reports WHERE interns_id = :internId)");
            $stmt->execute([":internId" => $internId]);
            
            // Delete from weekly_reports (student's weekly reports)
            $stmt = $dbo->conn->prepare("DELETE FROM weekly_reports WHERE interns_id = :internId");
            $stmt->execute([":internId" => $internId]);
            
            // Delete from intern_details
            $stmt = $dbo->conn->prepare("DELETE FROM intern_details WHERE INTERNS_ID = :internId");
            $stmt->execute([":internId" => $internId]);
            
            // Finally, delete from interns_details
            $stmt = $dbo->conn->prepare("DELETE FROM interns_details WHERE STUDENT_ID = :studentId");
            $stmt->execute([":studentId" => $studentId]);
            
            // Commit transaction
            $dbo->conn->commit();
            
            return ["success" => true, "message" => "Student deleted successfully."];
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($dbo->conn->inTransaction()) {
                $dbo->conn->rollBack();
            }
            return ["success" => false, "message" => "Error deleting student: " . $e->getMessage()];
        }
    }



   
    public function getAttendanceReport($dbo,$sessionid,$hteid,$coordinatorid,$ondate)
    {
        $report=[];
        $sessionName='';
        $coordinatorName='';
        $hteName='';
        $c="SELECT * FROM session_details WHERE  ID=:ID";
        $s=$dbo->conn->prepare($c);
        try{
            $s->execute([":ID"=>$sessionid]);
            $sd=$s->fetchAll(PDO::FETCH_ASSOC)[0];
            $sessionName=$sd['YEAR']." ".$sd['TERM'];
        }
        catch (Exception $e)
        {

        }
        
        $c="SELECT * FROM host_training_establishment WHERE  HTE_ID=:ID";
        $s=$dbo->conn->prepare($c);
        try{
            $s->execute([":ID"=>$hteid]);
            $sd=$s->fetchAll(PDO::FETCH_ASSOC);
            if(count($sd) > 0) {
                $hteName=$sd[0]['INDUSTRY']."-".$sd[0]['NAME'];
            } else {
                $hteName = "No HTE found with ID $hteid";
            }
        }
        catch (PDOException $e)
        {
            
        }

        $c="SELECT * FROM coordinator WHERE  COORDINATOR_ID=:ID";
        $s=$dbo->conn->prepare($c);
        try{
            $s->execute([":ID"=>$coordinatorid]);
            $sd=$s->fetchAll(PDO::FETCH_ASSOC)[0];
            $coordinatorName=$sd['NAME'];
        }
        catch (Exception $e)
        {

        }
        

        array_push($report,["Session:",$sessionName]);
        array_push($report,["HTE:",$hteName]);
        array_push($report,["Coordinator:",$coordinatorName]);

        // una kay kuhaon sa kung pila ka hte sa current nga coordinator
        $c="SELECT DISTINCT ON_DATE FROM interns_attendance WHERE
            ID = :sessionid AND HTE_ID=:hteid AND COORDINATOR_ID=:coordinatorid
            ORDER BY ON_DATE";
        $s=$dbo->conn->prepare($c);
        try{
            $s->execute([":sessionid"=>$sessionid,":hteid"=>$hteid,":coordinatorid"=>$coordinatorid]);
            $rv=$s->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e)
        {
    
        }

        $rv = [];
        $c = "SELECT
        rsd.INTERNS_ID,
        rsd.STUDENT_ID,
        rsd.SURNAME,
        rsd.NAME,
        ita.ON_DATE,
        ita.TIMEIN,
        ita.TIMEOUT
        FROM (
            SELECT id.INTERNS_ID, id.STUDENT_ID, id.SURNAME, id.NAME, itd.SESSION_ID, itd.HTE_ID
            FROM interns_details AS id
            JOIN intern_details AS itd ON itd.INTERNS_ID = id.INTERNS_ID
            WHERE itd.SESSION_ID = :sessionid
            AND itd.HTE_ID = :hteid
        ) AS rsd
        JOIN interns_attendance AS ita ON rsd.INTERNS_ID = ita.INTERNS_ID
                                        AND rsd.HTE_ID = ita.HTE_ID
                                        AND ita.COORDINATOR_ID = :coordinatorid
        WHERE ita.ON_DATE = :ondate  -- Filter by selected ondate
        AND ita.TIMEIN IS NOT NULL
        AND ita.TIMEOUT IS NOT NULL";
        $s = $dbo->conn->prepare($c);

        try {
            $s->execute([
                ":sessionid" => $sessionid, 
                ":hteid" => $hteid, 
                ":coordinatorid" => $coordinatorid, 
                ":ondate" => $ondate  // Selected ondate
            ]);
            $rv = $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Handle the exception, if needed
        }

        array_push($report,["Intern ID","Student ID","Name","Time In","Time Out"]);
        $report = array_merge($report, $rv);
        return $report;

    }


    public function saveAttendance($dbo, $sessionid, $hteid, $coordinatorid, $studentid, $ondate, $timein, $timeout)
    {
        $rv = [-1];
        $c = "SELECT * FROM interns_attendance
        WHERE COORDINATOR_ID = :COORDINATOR_ID AND HTE_ID = :HTE_ID AND ID = :ID AND INTERNS_ID = :INTERNS_ID AND ON_DATE = :ON_DATE";
        $s = $dbo->conn->prepare($c);
        try {
            $s->execute([
                ":COORDINATOR_ID" => $coordinatorid,
                ":HTE_ID" => $hteid,
                ":ID" => $sessionid,
                ":INTERNS_ID" => $studentid,
                ":ON_DATE" => $ondate
            ]);
            $result = $s->fetch();
            if ($result) {
                $c = "UPDATE interns_attendance
                SET TIMEIN = :TIMEIN, TIMEOUT = :TIMEOUT
                WHERE COORDINATOR_ID = :COORDINATOR_ID AND HTE_ID = :HTE_ID AND ID = :ID AND INTERNS_ID = :INTERNS_ID AND ON_DATE = :ON_DATE";
                $s = $dbo->conn->prepare($c);
                try {
                    $s->execute([
                        ":COORDINATOR_ID" => $coordinatorid,
                        ":HTE_ID" => $hteid,
                        ":ID" => $sessionid,
                        ":INTERNS_ID" => $studentid,
                        ":ON_DATE" => $ondate,
                        ":TIMEIN" => $timein,
                        ":TIMEOUT" => $timeout
                    ]);
                    // echo "Updated existing record";
                    $rv = [1];
                } catch (PDOException $e) {
                    echo "Error updating existing record: " . $e->getMessage();
                    $rv = [$e->getMessage()];
                }
            } else {
                $c = "INSERT INTO interns_attendance 
                (COORDINATOR_ID, HTE_ID, ID, INTERNS_ID, ON_DATE, TIMEIN, TIMEOUT)
                VALUES (:COORDINATOR_ID, :HTE_ID, :ID, :INTERNS_ID, :ON_DATE, :TIMEIN, :TIMEOUT)";
                $s = $dbo->conn->prepare($c);
                try {
                    $s->execute([
                        ":COORDINATOR_ID" => $coordinatorid,
                        ":HTE_ID" => $hteid,
                        ":ID" => $sessionid,
                        ":INTERNS_ID" => $studentid,
                        ":ON_DATE" => $ondate,
                        ":TIMEIN" => $timein,
                        ":TIMEOUT" => $timeout
                    ]);
                    // echo "Inserted new record";
                    $rv = [1];
                } catch (PDOException $e) {
                    // echo "Error inserting new record: " . $e->getMessage();
                    $rv = [$e->getMessage()];
                }
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            $rv = [$e->getMessage()];
        }
        return $rv;
    }



    public function getPresentListOfAClassByACDROnDate($dbo,$sessionid,$classid,$coordinatorid,$ondate)
    {
        $rv = [];
        $c="select INTERNS_ID, TIMEIN, TIMEOUT from interns_attendance
            WHERE COORDINATOR_ID = :COORDINATOR_ID AND HTE_ID = :HTE_ID AND ID = :ID
                AND ON_DATE = :ON_DATE
                AND  TIMEIN IS NOT NULL AND TIMEOUT IS NOT NULL";
        $s=$dbo->conn->prepare($c);
        try{
            $s->execute([":COORDINATOR_ID"=>$coordinatorid,":HTE_ID"=>$classid,":ID"=>$sessionid,":ON_DATE"=>$ondate]);
            $rv=$s->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e)
        {

        }
        return $rv;
    }

    public function addStudent($dbo, $student_id, $name, $surname, $age, $gender, $email, $contact_number, $coordinator_id, $hte_id, $session_id, $grades = []) {
        try {
            // Start transaction
            $dbo->conn->beginTransaction();
    
            // If HTE_ID is provided, validate if HTE exists
            if (!empty($hte_id)) {
                $stmt = $dbo->conn->prepare("SELECT COUNT(*) FROM host_training_establishment WHERE HTE_ID = :hte_id");
                $stmt->bindParam(':hte_id', $hte_id, PDO::PARAM_INT);  // Binding the parameter
                $stmt->execute();
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception("HTE_ID $hte_id does not exist.");
                }
            }
    
            // Check if the student is already assigned to any HTE in any session
            $stmt = $dbo->conn->prepare("SELECT COUNT(*) 
                                         FROM intern_details 
                                         INNER JOIN interns_details ON intern_details.INTERNS_ID = interns_details.INTERNS_ID
                                         WHERE interns_details.STUDENT_ID = :student_id");
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);  // Binding the parameter
            $stmt->execute();
    
            // If the student already has an assignment, throw an exception
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("This student is already assigned to an HTE in another session.");
            }
    
            // If both session_id and hte_id are provided, check if the student is already assigned to the same HTE in the selected session
            if (!empty($session_id) && !empty($hte_id)) {
                $stmt = $dbo->conn->prepare("SELECT COUNT(*) 
                                             FROM intern_details 
                                             WHERE INTERNS_ID IN (SELECT INTERNS_ID FROM interns_details WHERE STUDENT_ID = :student_id) 
                                             AND SESSION_ID = :session_id 
                                             AND HTE_ID = :hte_id");
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);  // Binding the parameter
                $stmt->bindParam(':session_id', $session_id, PDO::PARAM_INT);  // Binding the parameter
                $stmt->bindParam(':hte_id', $hte_id, PDO::PARAM_INT);  // Binding the parameter
                $stmt->execute();
        
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("This student is already assigned to the same session and HTE.");
                }
            }
    
            // Check if student already exists in the interns_details table
            $stmt = $dbo->conn->prepare("SELECT INTERNS_ID FROM interns_details WHERE STUDENT_ID = :student_id");
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);  // Binding the parameter
            $stmt->execute();
    
            if ($stmt->rowCount() > 0) {
                // Student already exists, get the existing INTERNS_ID
                $intern_id = $stmt->fetchColumn();
    
                // If both session_id and hte_id are provided, check if the student is already assigned to the same session and HTE
                if (!empty($session_id) && !empty($hte_id)) {
                    $stmt = $dbo->conn->prepare("SELECT COUNT(*) FROM intern_details WHERE INTERNS_ID = :intern_id AND SESSION_ID = :session_id AND HTE_ID = :hte_id");
                    $stmt->bindParam(':intern_id', $intern_id, PDO::PARAM_INT);  // Binding the parameter
                    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_INT);  // Binding the parameter
                    $stmt->bindParam(':hte_id', $hte_id, PDO::PARAM_INT);  // Binding the parameter
                    $stmt->execute();
            
                    if ($stmt->fetchColumn() > 0) {
                        // Student already assigned to the same session and HTE
                        throw new Exception("This student ID is already assigned to the same session and HTE.");
                    }
                }
    
                // Insert into intern_details if student is not already assigned and session and hte are provided
                if (!empty($session_id) && !empty($hte_id)) {
                    $stmt = $dbo->conn->prepare("INSERT INTO intern_details (INTERNS_ID, SESSION_ID, HTE_ID) VALUES (:intern_id, :session_id, :hte_id)");
                    $stmt->bindParam(':intern_id', $intern_id, PDO::PARAM_INT);  // Binding the parameter
                    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_INT);  // Binding the parameter
                    $stmt->bindParam(':hte_id', $hte_id, PDO::PARAM_INT);  // Binding the parameter
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to assign student to HTE and session.");
                    }
                }
            } else {
                // Insert new student into interns_details
                $stmt = $dbo->conn->prepare("INSERT INTO interns_details (STUDENT_ID, NAME, SURNAME, AGE, GENDER, EMAIL, CONTACT_NUMBER) VALUES (:student_id, :name, :surname, :age, :gender, :email, :contact_number)");
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);  // Binding the parameter
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);  // Binding the parameter
                $stmt->bindParam(':surname', $surname, PDO::PARAM_STR);  // Binding the parameter
                $stmt->bindParam(':age', $age, PDO::PARAM_INT);  // Binding the parameter
                $stmt->bindParam(':gender', $gender, PDO::PARAM_STR);  // Binding the parameter
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);  // Binding the parameter
                $stmt->bindParam(':contact_number', $contact_number, PDO::PARAM_STR);  // Binding the parameter
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add new student.");
                }
                // Get the newly inserted INTERN_ID
                $intern_id = $dbo->conn->lastInsertId();
    
                // Insert into intern_details for new student if session and hte are provided
                if (!empty($session_id) && !empty($hte_id)) {
                    $stmt = $dbo->conn->prepare("INSERT INTO intern_details (INTERNS_ID, SESSION_ID, HTE_ID) VALUES (:intern_id, :session_id, :hte_id)");
                    $stmt->bindParam(':intern_id', $intern_id, PDO::PARAM_INT);  // Binding the parameter
                    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_INT);  // Binding the parameter
                    $stmt->bindParam(':hte_id', $hte_id, PDO::PARAM_INT);  // Binding the parameter
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to assign new student to HTE and session.");
                    }
                }
            }
    
            // Insert grades into pre_assessment if provided
            if (!empty($grades)) {
                $stmt = $dbo->conn->prepare("INSERT INTO pre_assessment (STUDENT_ID, `CC 102`, `CC 103`, `PF 101`, `CC 104`, `IPT 101`, `IPT 102`, `CC 106`, `CC 105`, `IM 101`, `IM 102`, `HCI 101`, `HCI 102`, `WS 101`, `NET 101`, `NET 102`, `IAS 101`, `IAS 102`, `CAP 101`, `CAP 102`, `SP 101`) VALUES (:student_id, :CC_102, :CC_103, :PF_101, :CC_104, :IPT_101, :IPT_102, :CC_106, :CC_105, :IM_101, :IM_102, :HCI_101, :HCI_102, :WS_101, :NET_101, :NET_102, :IAS_101, :IAS_102, :CAP_101, :CAP_102, :SP_101)");
                $params = [
                    ':student_id' => $student_id,
                    ':CC_102' => $grades['CC 102'] ?? null,
                    ':CC_103' => $grades['CC 103'] ?? null,
                    ':PF_101' => $grades['PF 101'] ?? null,
                    ':CC_104' => $grades['CC 104'] ?? null,
                    ':IPT_101' => $grades['IPT 101'] ?? null,
                    ':IPT_102' => $grades['IPT 102'] ?? null,
                    ':CC_106' => $grades['CC 106'] ?? null,
                    ':CC_105' => $grades['CC 105'] ?? null,
                    ':IM_101' => $grades['IM 101'] ?? null,
                    ':IM_102' => $grades['IM 102'] ?? null,
                    ':HCI_101' => $grades['HCI 101'] ?? null,
                    ':HCI_102' => $grades['HCI 102'] ?? null,
                    ':WS_101' => $grades['WS 101'] ?? null,
                    ':NET_101' => $grades['NET 101'] ?? null,
                    ':NET_102' => $grades['NET 102'] ?? null,
                    ':IAS_101' => $grades['IAS 101'] ?? null,
                    ':IAS_102' => $grades['IAS 102'] ?? null,
                    ':CAP_101' => $grades['CAP 101'] ?? null,
                    ':CAP_102' => $grades['CAP 102'] ?? null,
                    ':SP_101' => $grades['SP 101'] ?? null
                ];
                if (!$stmt->execute($params)) {
                    throw new Exception("Failed to insert grades into pre_assessment.");
                }
            }

            // Commit transaction if all operations succeed
            $dbo->conn->commit();

            // Return the intern_id of the assigned student
            return $intern_id;

        } catch (Exception $e) {
            // Rollback the transaction on any exception
            $dbo->conn->rollBack();

            // Return a JSON response with the error message
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    

    public function addHTE($dbo, $name, $industry, $address, $contact_email, $contact_person, $contact_number, $coordinator_id, $session_id, $logo_filename = null) {
        try {
            $dbo->conn->beginTransaction();
            // Check if the HTE already exists
            $stmt = $dbo->conn->prepare("SELECT HTE_ID FROM host_training_establishment WHERE NAME = ? AND CONTACT_EMAIL = ?");
            $stmt->execute([$name, $contact_email]);
            $existing_hte = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_hte) {
                $hte_id = $existing_hte['HTE_ID'];
                // Update existing HTE, including logo if provided
                if ($logo_filename) {
                    $stmt = $dbo->conn->prepare("UPDATE host_training_establishment SET INDUSTRY = ?, ADDRESS = ?, CONTACT_PERSON = ?, CONTACT_NUMBER = ?, LOGO = ? WHERE HTE_ID = ?");
                    $stmt->execute([$industry, $address, $contact_person, $contact_number, $logo_filename, $hte_id]);
                } else {
                    $stmt = $dbo->conn->prepare("UPDATE host_training_establishment SET INDUSTRY = ?, ADDRESS = ?, CONTACT_PERSON = ?, CONTACT_NUMBER = ? WHERE HTE_ID = ?");
                    $stmt->execute([$industry, $address, $contact_person, $contact_number, $hte_id]);
                }
            } else {
                // Insert new HTE, including logo
                $stmt = $dbo->conn->prepare("INSERT INTO host_training_establishment (NAME, INDUSTRY, ADDRESS, CONTACT_EMAIL, CONTACT_PERSON, CONTACT_NUMBER, LOGO) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $industry, $address, $contact_email, $contact_person, $contact_number, $logo_filename]);
                $hte_id = $dbo->conn->lastInsertId();
            }

            // Check if the HTE is already associated with this coordinator and session
            $stmt = $dbo->conn->prepare("SELECT COUNT(*) FROM internship_needs WHERE HTE_ID = ? AND COORDINATOR_ID = ? AND SESSION_ID = ?");
            $stmt->execute([$hte_id, $coordinator_id, $session_id]);
            $exists = $stmt->fetchColumn();

            if (!$exists) {
                // Insert into internship_needs only if the association doesn't exist
                $stmt = $dbo->conn->prepare("INSERT INTO internship_needs (HTE_ID, COORDINATOR_ID, SESSION_ID) VALUES (?, ?, ?)");
                $stmt->execute([$hte_id, $coordinator_id, $session_id]);
            }

            $dbo->conn->commit();
            return $hte_id;
        } catch (Exception $e) {
            $dbo->conn->rollBack();
            throw $e;
        }
    }

    public function addHTEWithMOA($dbo, $name, $industry, $address, $contact_email, $contact_person, $contact_number, $coordinator_id, $session_id, $logo_filename = null, $moa_file_url = null, $moa_public_id = null, $moa_start_date = null, $moa_end_date = null) {
        try {
            $dbo->conn->beginTransaction();
            
            // Check if the HTE already exists
            $stmt = $dbo->conn->prepare("SELECT HTE_ID FROM host_training_establishment WHERE NAME = ? AND CONTACT_EMAIL = ?");
            $stmt->execute([$name, $contact_email]);
            $existing_hte = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_hte) {
                $hte_id = $existing_hte['HTE_ID'];
                // Update existing HTE with MOA data
                $sql = "UPDATE host_training_establishment SET 
                        INDUSTRY = ?, ADDRESS = ?, CONTACT_PERSON = ?, CONTACT_NUMBER = ?, 
                        moa_file_url = ?, moa_public_id = ?, moa_start_date = ?, moa_end_date = ?, 
                        moa_upload_date = NOW()";
                $params = [$industry, $address, $contact_person, $contact_number, 
                          $moa_file_url, $moa_public_id, $moa_start_date, $moa_end_date];
                          
                if ($logo_filename) {
                    $sql .= ", LOGO = ?";
                    $params[] = $logo_filename;
                }
                $sql .= " WHERE HTE_ID = ?";
                $params[] = $hte_id;
                
                $stmt = $dbo->conn->prepare($sql);
                $stmt->execute($params);
            } else {
                // Insert new HTE with MOA data
                $sql = "INSERT INTO host_training_establishment 
                        (NAME, INDUSTRY, ADDRESS, CONTACT_EMAIL, CONTACT_PERSON, CONTACT_NUMBER, LOGO,
                         moa_file_url, moa_public_id, moa_start_date, moa_end_date, moa_upload_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $dbo->conn->prepare($sql);
                $stmt->execute([$name, $industry, $address, $contact_email, $contact_person, $contact_number, 
                               $logo_filename, $moa_file_url, $moa_public_id, $moa_start_date, $moa_end_date]);
                $hte_id = $dbo->conn->lastInsertId();
            }

            // Check if the association already exists
            $stmt = $dbo->conn->prepare("SELECT COUNT(*) FROM internship_needs WHERE HTE_ID = ? AND COORDINATOR_ID = ? AND SESSION_ID = ?");
            $stmt->execute([$hte_id, $coordinator_id, $session_id]);
            $exists = $stmt->fetchColumn();

            if (!$exists) {
                // Insert into internship_needs only if the association doesn't exist
                $stmt = $dbo->conn->prepare("INSERT INTO internship_needs (HTE_ID, COORDINATOR_ID, SESSION_ID) VALUES (?, ?, ?)");
                $stmt->execute([$hte_id, $coordinator_id, $session_id]);
            }

            $dbo->conn->commit();
            return $hte_id;
        } catch (Exception $e) {
            $dbo->conn->rollBack();
            throw $e;
        }
    }

    public function getHTEById($dbo, $hte_id) {
        try {
            $stmt = $dbo->conn->prepare("SELECT * FROM host_training_establishment WHERE HTE_ID = ?");
            $stmt->execute([$hte_id]);
            $hte = $stmt->fetch(PDO::FETCH_ASSOC);
            return $hte;
        } catch (Exception $e) {
            error_log("Error fetching HTE details: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateHTEDetails($dbo, $hte_id, $name, $industry, $address, $contact_email, $contact_person, $contact_number, $moa_start_date, $moa_end_date, $moa_file_url = null, $moa_public_id = null) {
        try {
            $dbo->conn->beginTransaction();
            
            // Base SQL and parameters
            $sql = "UPDATE host_training_establishment SET 
                    NAME = ?, INDUSTRY = ?, ADDRESS = ?, CONTACT_EMAIL = ?, 
                    CONTACT_PERSON = ?, CONTACT_NUMBER = ?, 
                    moa_start_date = ?, moa_end_date = ?";
            $params = [$name, $industry, $address, $contact_email, $contact_person, $contact_number, 
                      $moa_start_date, $moa_end_date];
            
            // Add MOA file update if provided
            if ($moa_file_url && $moa_public_id) {
                $sql .= ", moa_file_url = ?, moa_public_id = ?, moa_upload_date = NOW()";
                $params[] = $moa_file_url;
                $params[] = $moa_public_id;
            }
            
            $sql .= " WHERE HTE_ID = ?";
            $params[] = $hte_id;
            
            $stmt = $dbo->conn->prepare($sql);
            $result = $stmt->execute($params);
            
            $dbo->conn->commit();
            return $result;
        } catch (Exception $e) {
            $dbo->conn->rollBack();
            error_log("Error updating HTE details: " . $e->getMessage());
            throw $e;
        }
    }

    public function getCoordinatorDetails($dbo, $coordinator_id) {
        try {
            $stmt = $dbo->conn->prepare("SELECT COORDINATOR_ID, NAME, EMAIL, CONTACT_NUMBER, DEPARTMENT, PROFILE 
                                         FROM coordinator 
                                         WHERE COORDINATOR_ID = ?");
            $stmt->execute([$coordinator_id]);
            $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$coordinator) {
                throw new Exception("Coordinator not found.");
            }
    
            return $coordinator;
        } catch (Exception $e) {
            // Log the error
            error_log("Error fetching coordinator details: " . $e->getMessage());
            throw $e;
        }
    }

    public function isStudentAlreadyAssigned($dbo, $student_id, $session_id, $coordinator_id, $hte_id) {
        $query = "SELECT * FROM interns
                  WHERE STUDENT_ID = ? AND
                  (SESSION_ID != ? OR COORDINATOR_ID != ? OR HTE_ID != ?)";
        $stmt = $dbo->prepare($query);
        $stmt->execute([$student_id, $session_id, $coordinator_id, $hte_id]);
        return $stmt->rowCount() > 0;
    }

    public function assignStudents($dbo, $studentIds, $sessionId, $hteId, $coordinatorId) {
        try {
            $dbo->conn->beginTransaction();

            $assignedCount = 0;
            $errors = [];

            foreach ($studentIds as $studentId) {
                try {
                    // Check if student exists in interns_details
                    $stmt = $dbo->conn->prepare("SELECT INTERNS_ID FROM interns_details WHERE INTERNS_ID = :studentId");
                    $stmt->execute([':studentId' => $studentId]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$student) {
                        $errors[] = "Student with ID $studentId not found.";
                        continue;
                    }

                    $internId = $student['INTERNS_ID'];

                    // Check if student is already assigned to this session and HTE
                    $stmt = $dbo->conn->prepare("SELECT COUNT(*) FROM intern_details
                                                WHERE INTERNS_ID = :internId AND SESSION_ID = :sessionId AND HTE_ID = :hteId");
                    $stmt->execute([
                        ':internId' => $internId,
                        ':sessionId' => $sessionId,
                        ':hteId' => $hteId
                    ]);

                    if ($stmt->fetchColumn() > 0) {
                        // Student already assigned to this session and HTE
                        continue;
                    }

                    // Remove existing assignment for this student in this session if any
                    $stmt = $dbo->conn->prepare("DELETE FROM intern_details
                                                WHERE INTERNS_ID = :internId AND SESSION_ID = :sessionId");
                    $stmt->execute([':internId' => $internId, ':sessionId' => $sessionId]);

                    // Assign student to new HTE in this session
                    $stmt = $dbo->conn->prepare("INSERT INTO intern_details (INTERNS_ID, SESSION_ID, HTE_ID)
                                                VALUES (:internId, :sessionId, :hteId)");
                    $stmt->execute([
                        ':internId' => $internId,
                        ':sessionId' => $sessionId,
                        ':hteId' => $hteId
                    ]);

                    $assignedCount++;

                } catch (Exception $e) {
                    $errors[] = "Error assigning student $studentId: " . $e->getMessage();
                }
            }

            $dbo->conn->commit();

            $message = "$assignedCount student(s) assigned successfully.";
            if (!empty($errors)) {
                $message .= " Errors: " . implode(", ", $errors);
            }

            return ["success" => true, "message" => $message, "assignedCount" => $assignedCount];

        } catch (Exception $e) {
            $dbo->conn->rollBack();
            return ["success" => false, "message" => "Error assigning students: " . $e->getMessage()];
        }
    }

    public function updateCoordinatorDetails($dbo, $coordinator_id, $name, $email, $contact_number, $department) {
        try {
            $stmt = $dbo->conn->prepare("UPDATE coordinator SET NAME = ?, EMAIL = ?, CONTACT_NUMBER = ?, DEPARTMENT = ? WHERE COORDINATOR_ID = ?");
            $stmt->execute([$name, $email, $contact_number, $department, $coordinator_id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error updating coordinator details: " . $e->getMessage());
            return false;
        }
    }

    public function verifyCoordinatorPassword($dbo, $coordinator_id, $password) {
        try {
            if (!$dbo || !$dbo->conn) {
                throw new Exception("Database connection not initialized");
            }
            
            error_log("\n=== Password Verification Debug ===");
            error_log("Coordinator ID: " . $coordinator_id);
            error_log("Provided password length: " . strlen($password));
            
            // First, verify the coordinator exists and get their current password
            $stmt = $dbo->conn->prepare("SELECT PASSWORD FROM coordinator WHERE COORDINATOR_ID = ? LIMIT 1");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement");
            }
            
            $stmt->execute([$coordinator_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log("No coordinator found with ID: " . $coordinator_id);
                return false;
            }
            
            if (!isset($result['PASSWORD'])) {
                error_log("Password field not found in result");
                return false;
            }
            
            $storedPassword = $result['PASSWORD'];
            error_log("Found stored password. Length: " . strlen($storedPassword));
            
            // Simple string comparison for plain text passwords
            $matches = ($password === $storedPassword);
            error_log("Password comparison result: " . ($matches ? "MATCH" : "NO MATCH"));
            
            return $matches;
            
        } catch (PDOException $e) {
            error_log("Database error in verifyCoordinatorPassword: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        } catch (Exception $e) {
            error_log("Error in verifyCoordinatorPassword: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function updateCoordinatorProfilePicture($dbo, $coordinator_id, $filename) {
        try {
            if (!$dbo || !$dbo->conn) {
                throw new Exception("Database connection not initialized");
            }

            error_log("Updating profile picture for coordinator: " . $coordinator_id);
            error_log("New filename: " . $filename);

            // First verify the coordinator exists
            $checkStmt = $dbo->conn->prepare("SELECT COORDINATOR_ID FROM coordinator WHERE COORDINATOR_ID = ?");
            $checkStmt->execute([$coordinator_id]);
            
            if (!$checkStmt->fetch()) {
                error_log("Coordinator not found: " . $coordinator_id);
                throw new Exception("Coordinator not found");
            }

            $stmt = $dbo->conn->prepare("UPDATE coordinator SET PROFILE = ? WHERE COORDINATOR_ID = ?");
            if (!$stmt) {
                error_log("Failed to prepare profile update statement");
                throw new Exception("Failed to prepare profile update statement");
            }

            $stmt->execute([$filename, $coordinator_id]);
            
            if ($stmt->rowCount() > 0) {
                error_log("Profile picture updated successfully in database");
                return true;
            } else {
                error_log("No rows updated for coordinator profile picture");
                return false;
            }
        } catch (PDOException $e) {
            error_log("Database error updating profile picture: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        } catch (Exception $e) {
            error_log("Error updating profile picture: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function updateCoordinatorPassword($dbo, $coordinator_id, $new_password) {
        try {
            if (!$dbo || !$dbo->conn) {
                throw new Exception("Database connection not initialized");
            }

            // Log the update attempt
            error_log("Attempting to update password for coordinator: " . $coordinator_id);
            error_log("New password length: " . strlen($new_password));

            // First verify the coordinator exists
            $checkStmt = $dbo->conn->prepare("SELECT COORDINATOR_ID FROM coordinator WHERE COORDINATOR_ID = ?");
            $checkStmt->execute([$coordinator_id]);
            
            if (!$checkStmt->fetch()) {
                error_log("Coordinator not found: " . $coordinator_id);
                throw new Exception("Coordinator not found");
            }

            // For development, store as plain text
            $stmt = $dbo->conn->prepare("UPDATE coordinator SET PASSWORD = ? WHERE COORDINATOR_ID = ?");
            
            if (!$stmt) {
                error_log("Failed to prepare update statement");
                throw new Exception("Failed to prepare update statement");
            }

            $stmt->execute([$new_password, $coordinator_id]);
            
            if ($stmt->rowCount() > 0) {
                error_log("Password successfully updated in database for coordinator: " . $coordinator_id);
                return true;
            } else {
                error_log("No rows were updated for coordinator: " . $coordinator_id);
                return false;
            }
        } catch (PDOException $e) {
            error_log("Database error updating password: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw new Exception("Database error: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error updating password: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function getAllStudentsUnderCoordinator($dbo, $coordinator_id) {
        try {
            $stmt = $dbo->conn->prepare("
                SELECT
                    id.STUDENT_ID,
                    id.NAME,
                    id.SURNAME,
                    id.AGE,
                    id.GENDER,
                    id.EMAIL,
                    id.CONTACT_NUMBER,
                    hte.NAME AS HTE_NAME,
                    CONCAT(s.YEAR, ' ', s.TERM) AS SESSION_NAME
                FROM interns_details id
                JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
                JOIN internship_needs ins ON itd.HTE_ID = ins.HTE_ID AND ins.COORDINATOR_ID = :coordinator_id
                JOIN host_training_establishment hte ON itd.HTE_ID = hte.HTE_ID
                JOIN session_details s ON itd.SESSION_ID = s.ID
                    ORDER BY id.NAME ASC
            ");
            $stmt->execute([':coordinator_id' => $coordinator_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching students under coordinator: " . $e->getMessage());
            return [];
        }
    }

    public function getStudentsBySessionAndHTE($dbo, $sessionId, $hteId, $coordinatorId) {
        try {
            // Debug logging
            error_log("getStudentsBySessionAndHTE called with sessionId: $sessionId, hteId: $hteId, coordinatorId: $coordinatorId");

            $stmt = $dbo->conn->prepare("
                SELECT
                    id.INTERNS_ID,
                    id.STUDENT_ID,
                    id.NAME,
                    id.SURNAME,
                    id.AGE,
                    id.GENDER,
                    id.EMAIL,
                    id.CONTACT_NUMBER
                FROM interns_details id
                JOIN intern_details itd ON id.INTERNS_ID = itd.INTERNS_ID
                WHERE itd.SESSION_ID = :sessionId
                AND itd.HTE_ID = :hteId
                ORDER BY id.NAME ASC
            ");
            $stmt->execute([
                ':sessionId' => $sessionId,
                ':hteId' => $hteId
            ]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Query returned " . count($results) . " students");
            return $results;
        } catch (Exception $e) {
            error_log("Error fetching students by session and HTE: " . $e->getMessage());
            return [];
        }
    }

    public function deleteStudents($dbo, $studentIds) {
        try {
            $dbo->conn->beginTransaction();

            $deletedCount = 0;
            $errors = [];

            foreach ($studentIds as $studentId) {
                try {
                    // Get INTERNS_ID before deletion
                    $stmt = $dbo->conn->prepare("SELECT INTERNS_ID FROM interns_details WHERE STUDENT_ID = :studentId");
                    $stmt->execute([":studentId" => $studentId]);
                    $internResult = $stmt->fetch(PDO::FETCH_ASSOC);
                    $internsId = $internResult ? $internResult['INTERNS_ID'] : null;

                    $totalDeleted = 0;

                    // Delete from all related tables in correct order (child tables first)
                    $deleteTables = [
                        // Tables using STUDENT_ID (uppercase)
                        ['table' => 'coordinator_evaluation', 'column' => 'STUDENT_ID', 'value' => $studentId],
                        ['table' => 'student_evaluation', 'column' => 'STUDENT_ID', 'value' => $studentId],
                        ['table' => 'pre_assessment', 'column' => 'STUDENT_ID', 'value' => $studentId],
                        ['table' => 'student_deletion_log', 'column' => 'STUDENT_ID', 'value' => $studentId],
                        
                        // Tables using student_id (lowercase)
                        ['table' => 'post_assessment', 'column' => 'student_id', 'value' => $studentId],
                        ['table' => 'post_analysis_summary', 'column' => 'student_id', 'value' => $studentId],
                        ['table' => 'student_questions', 'column' => 'student_id', 'value' => $studentId]
                    ];

                    // Add tables using INTERNS_ID if we found it
                    if ($internsId) {
                        // Note: report_images will be cascade deleted when weekly_reports are deleted due to foreign key
                        $deleteTables[] = ['table' => 'notifications', 'column' => 'receiver_id', 'value' => $internsId];
                        $deleteTables[] = ['table' => 'weekly_reports', 'column' => 'interns_id', 'value' => $internsId];
                        $deleteTables[] = ['table' => 'pending_attendance', 'column' => 'INTERNS_ID', 'value' => $internsId];
                        $deleteTables[] = ['table' => 'interns_attendance', 'column' => 'INTERNS_ID', 'value' => $internsId];
                        $deleteTables[] = ['table' => 'intern_details', 'column' => 'INTERNS_ID', 'value' => $internsId];
                    }

                    // Finally delete from main table
                    $deleteTables[] = ['table' => 'interns_details', 'column' => 'STUDENT_ID', 'value' => $studentId];

                    foreach ($deleteTables as $deleteInfo) {
                        try {
                            $stmt = $dbo->conn->prepare("DELETE FROM {$deleteInfo['table']} WHERE {$deleteInfo['column']} = :value");
                            $stmt->execute([":value" => $deleteInfo['value']]);
                            $rowsDeleted = $stmt->rowCount();
                            $totalDeleted += $rowsDeleted;
                            
                            if ($rowsDeleted > 0) {
                                error_log("Deleted $rowsDeleted records from {$deleteInfo['table']} for student $studentId");
                            }
                        } catch (Exception $e) {
                            // Some tables might not exist or have different structure, continue with others
                            error_log("Note: Could not delete from {$deleteInfo['table']}: " . $e->getMessage());
                        }
                    }

                    if ($totalDeleted === 0) {
                        $errors[] = "Student $studentId not found or already deleted";
                    } else {
                        $deletedCount++;
                        error_log("Successfully deleted student $studentId from all tables (total $totalDeleted records)");
                    }

                } catch (Exception $e) {
                    $errors[] = "Error deleting student $studentId: " . $e->getMessage();
                    error_log("Student deletion error for $studentId: " . $e->getMessage());
                }
            }

            if (empty($errors)) {
                $dbo->conn->commit();
                error_log("Successfully deleted $deletedCount student(s) with CASCADE");
                return true;
            } else {
                $dbo->conn->rollBack();
                error_log("Student deletion failed with errors: " . implode(", ", $errors));
                return false;
            }

        } catch (Exception $e) {
            $dbo->conn->rollBack();
            error_log("Error deleting students: " . $e->getMessage());
            return false;
        }
    }

    // Get HTE assigned to admin with MOA information for admin dashboard
    public function getAdminHTEWithMOA($dbo, $adminId) {
        try {
            $sql = "SELECT 
                        hte.HTE_ID,
                        hte.NAME,
                        hte.INDUSTRY,
                        hte.ADDRESS,
                        hte.CONTACT_EMAIL,
                        hte.CONTACT_PERSON,
                        hte.CONTACT_NUMBER,
                        hte.LOGO,
                        hte.MOA_FILE_URL,
                        hte.MOA_PUBLIC_ID,
                        hte.MOA_START_DATE,
                        hte.MOA_END_DATE,
                        hte.MOA_UPLOAD_DATE,
                        c.NAME as COORDINATOR_NAME
                    FROM coordinator c
                    JOIN host_training_establishment hte ON c.HTE_ID = hte.HTE_ID
                    WHERE c.COORDINATOR_ID = :adminId AND c.ROLE = 'ADMIN'
                    ORDER BY hte.NAME ASC";
            
            $stmt = $dbo->conn->prepare($sql);
            $stmt->execute([':adminId' => $adminId]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("getAdminHTEWithMOA: Retrieved " . count($result) . " HTEs for admin ID: " . $adminId);
            return $result;
            
        } catch (PDOException $e) {
            error_log("Error in getAllHTEsWithMOA: " . $e->getMessage());
            throw new Exception("Database error: " . $e->getMessage());
        }
    }


}

?>
