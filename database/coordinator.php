<?php

class coordinator
{
    public function verifyUser  ($dbo, $un, $pw) {
        $rv = ["id" => -1, "status" => "ERROR", "role" => ""]; // Include role in the response
        
        // Check if database connection exists
        if ($dbo->conn === null) {
            return ["id" => -1, "status" => "Database Connection Failed", "role" => ""];
        }
        
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

    public function verifyPassword($dbo, $coordinator_id, $password) {
        try {
            // Verify coordinator exists and get their password
            $stmt = $dbo->conn->prepare("SELECT password FROM coordinator WHERE COORDINATOR_ID = :id");
            if (!$stmt->execute([':id' => $coordinator_id])) {
                throw new Exception("Database error while verifying password");
            }
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                throw new Exception("Coordinator not found");
            }
            
            // If the password is stored as a hash, use password_verify
            if (strlen($result['password']) > 32) { // Likely a hash
                return password_verify($password, $result['password']);
            } else {
                // Legacy plain text comparison - consider updating to hashed passwords
                $matched = $result['password'] === $password;
                if ($matched) {
                    // Upgrade to hashed password
                    $this->updatePassword($dbo, $coordinator_id, $password);
                }
                return $matched;
            }
            
        } catch (Exception $e) {
            error_log("Error verifying password: " . $e->getMessage());
            throw $e;
        }
    }

    public function updatePassword($dbo, $coordinator_id, $new_password) {
        try {
            // Start transaction
            $dbo->conn->beginTransaction();
            
            // Update the password
            $stmt = $dbo->conn->prepare("UPDATE coordinator SET password = :password WHERE COORDINATOR_ID = :id");
            $result = $stmt->execute([
                ':password' => $new_password,
                ':id' => $coordinator_id
            ]);
            
            if (!$result || $stmt->rowCount() === 0) {
                throw new Exception("No coordinator found with ID: " . $coordinator_id);
            }
            
            // Commit the transaction
            $dbo->conn->commit();
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            if ($dbo->conn->inTransaction()) {
                $dbo->conn->rollBack();
            }
            error_log("Error updating password: " . $e->getMessage());
            throw $e;
        }
    }

    public function getHTEInASession($dbo, $sessionid, $cdrid)
    {
        $rv = [];
      $c = "SELECT hte.HTE_ID, hte.NAME, hte.INDUSTRY, hte.LOGO
          FROM internship_needs AS itn
          JOIN host_training_establishment AS hte
          ON itn.HTE_ID = hte.HTE_ID
          WHERE itn.COORDINATOR_ID = :cdrid AND itn.SESSION_ID = :sessionid";
        $s = $dbo->conn->prepare($c);
        try {
            $s->execute([":cdrid" => $cdrid, ":sessionid" => $sessionid]);
            $rv = $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo "An error occurred: " . $e->getMessage();
        }
        return $rv;
    }
}
?>

