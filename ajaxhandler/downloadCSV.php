<?php
session_start();
$path = $_SERVER['DOCUMENT_ROOT'];
require_once $path . "/database/database.php";
require_once $path . "/database/attendanceDetails.php";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students_with_grades.csv"');

$dbo = new Database();
$ado = new attendanceDetails();

// Query all students and their grades
$query = "SELECT d.STUDENT_ID, d.NAME, d.AGE, d.GENDER, d.EMAIL, d.CONTACT_NUMBER,
    p.`CC 102`, p.`CC 103`, p.`PF 101`, p.`CC 104`, p.`IPT 101`, p.`IPT 102`, p.`CC 106`, p.`CC 105`,
    p.`IM 101`, p.`IM 102`, p.`HCI 101`, p.`HCI 102`, p.`WS 101`, p.`NET 101`, p.`NET 102`,
    p.`IAS 101`, p.`IAS 102`, p.`CAP 101`, p.`CAP 102`, p.`SP 101`
FROM interns_details d
LEFT JOIN pre_assessment p ON d.STUDENT_ID = p.STUDENT_ID";
$stmt = $dbo->conn->prepare($query);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output CSV header
$header = [
    'student_id','name','age','gender','email','contact_number',
    'CC 102','CC 103','PF 101','CC 104','IPT 101','IPT 102','CC 106','CC 105',
    'IM 101','IM 102','HCI 101','HCI 102','WS 101','NET 101','NET 102',
    'IAS 101','IAS 102','CAP 101','CAP 102','SP 101'
];
$output = fopen('php://output', 'w');
fputcsv($output, $header);
foreach ($rows as $row) {
    $csvRow = [];
    foreach ($header as $col) {
        $csvRow[] = $row[$col] ?? '';
    }
    fputcsv($output, $csvRow);
}
fclose($output);
exit;

