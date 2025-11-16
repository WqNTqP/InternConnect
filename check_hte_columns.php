<?php
require_once 'database/database.php';
$db = new Database();
$result = $db->conn->query('DESCRIBE host_training_establishment');
echo "HOST_TRAINING_ESTABLISHMENT columns:\n";
while($row = $result->fetch()) {
    echo "- " . $row['Field'] . "\n";
}
?>