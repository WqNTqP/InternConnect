<?php

class BuildingRegistrationDetails
{
    // public function getRegisteredStudents($dbo, $sessionid, $courseid)
    // {
    //     $rv = [];
    //     $c = "SELECT id.INTERNS_ID, id.STUDENT_ID, id.NAME, id.GENDER 
    //         FROM interns_details AS id, intern_details AS itd 
    //         WHERE itd.INTERNS_ID = id.INTERNS_ID 
    //         AND itd.SESSION_ID = :sessionid 
    //         AND itd.HTE_ID = :courseid";

    //     $s = $dbo->conn->prepare($c);
    //     $s->execute([':sessionid' => $sessionid, ':courseid' => $courseid]);
    //     $rv = $s->fetchAll(PDO::FETCH_ASSOC);
    //     return $rv;
    // }


    public function getRegisteredStudents($dbo, $sessionid, $courseid)
    {
        $rv = [];
        $c = "SELECT id.INTERNS_ID, id.STUDENT_ID,id.NAME, id.SURNAME, id.GENDER 
            FROM interns_details AS id, intern_details AS itd 
            WHERE itd.INTERNS_ID = id.INTERNS_ID 
            AND itd.SESSION_ID = :sessionid 
            AND itd.HTE_ID = :courseid";

        $s = $dbo->conn->prepare($c);
        $s->execute([':sessionid' => $sessionid, ':courseid' => $courseid]);
        $rv = $s->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug output
        error_log("Session ID: " . $sessionid);
        error_log("Course ID: " . $courseid);
        error_log("Number of students found: " . count($rv));
        error_log("SQL Query: " . $c);
        
        return $rv;
    }
}

?>
