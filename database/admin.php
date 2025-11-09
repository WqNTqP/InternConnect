<?php
$path=$_SERVER['DOCUMENT_ROOT'];
require_once $path."/database/database.php";

class admin
{
    public function verifyUser  ($dbo, $un, $pw) {
        $rv = ["id" => -1, "status" => "ERROR", "role" => ""]; // Include role in the response
        $c = "SELECT COORDINATOR_ID, NAME, username, password, ROLE FROM coordinator WHERE username = :un";
        $s = $dbo->conn->prepare($c);
        try {
            $s->execute([":un" => $un]);
            if ($s->rowCount() > 0) {
                $result = $s->fetchAll(PDO::FETCH_ASSOC)[0];
                if ($result['password'] == $pw) {
                    // Return the id, status, and role (either COORDINATOR or ADMIN)
                    $rv = ["id" => $result['COORDINATOR_ID'], "status" => "ALL OK", "role" => $result['ROLE']];
                } else {
                    $rv = ["id" => $result['COORDINATOR_ID'], "status" => "Wrong Password", "role" => ""];
                }
            } else {
                $rv = ["id" => -1, "status" => "USER NAME DOES NOT EXIST", "role" => ""];
            }
        } catch (PDOException $e) {
            // Handle exceptions if needed
            $rv = ["id" => -1, "status" => "Database Error", "role" => ""];
        }
        return $rv;
    }
}
?>

