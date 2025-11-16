<?php
require_once 'database/database.php';
$db = new Database();
$result = $db->conn->query("
    SELECT DISTINCT wr.interns_id, id.STUDENT_ID, wr.updated_at
    FROM weekly_reports wr 
    JOIN interns_details id ON wr.interns_id = id.INTERNS_ID 
    WHERE wr.status = 'draft' 
    ORDER BY wr.updated_at DESC
    LIMIT 3
");

echo "Students with draft reports:\n";
while($row = $result->fetch()) { 
    echo "Interns_ID: {$row['interns_id']}, Student_ID: {$row['STUDENT_ID']}, Updated: {$row['updated_at']}\n"; 
}
?>