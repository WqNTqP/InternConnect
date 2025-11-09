<?php

$path=$_SERVER['DOCUMENT_ROOT'];
require_once $path."/InternConnect/database/database.php";
 $dbo=new Database();



/////////////////////////////////////////////
//DUMMY TABLE
// $c = "CREATE TABLE interns_attendance (
//     COORDINATOR_ID int,
//     HTE_ID int,
//     ID int,
//     STUDENT_ID INT,
//     ON_DATE DATE,
//     STATUS VARCHAR(10),

//     PRIMARY KEY (ON_DATE,COORDINATOR_ID,HTE_ID,ID,STUDENT_ID),
//     FOREIGN KEY (COORDINATOR_ID) REFERENCES coordinator (COORDINATOR_ID),
//     FOREIGN KEY (HTE_ID) REFERENCES host_training_establishment (HTE_ID),
//     FOREIGN KEY (ID) REFERENCES session_details (ID),
//     FOREIGN KEY (STUDENT_ID) REFERENCES intern_details (INTERN_ID)
//     )";
// $s = $dbo->conn->prepare($c);
// try {
//     // Prepare and execute the query
//     $s->execute();
    
//     // Log the success message
//     echo "<br>Table has been created!";
// } catch (PDOException $e) {
//     // Log the error message
//     error_log("Error creating table: " . $e->getMessage());
//     echo "<br>Error creating table: " . $e->getMessage();
// } catch (Exception $e) {
//     // Log the error message
//     error_log("Error creating table: " . $e->getMessage());
//     echo "<br>Error creating table: " . $e->getMessage();
// }

// $c="create table coordinator
// (
    // COORDINATOR_ID int auto_increment primary key,
    // NAME varchar(30) not null,
    // EMAIL varchar(30) not null,
    // CONTACT_NUMBER int not null,
    // DEPARTMENT varchar(30) not null
// )";
// $s=$dbo->conn->prepare($c);
// try{
//     $s->execute();
//     echo("<br>COORDINATOR Table has been created!");
// }
// catch(PDOException $o)
// {
//     echo("<br>COORDINATOR Table is already existing!");
// }

////////////////////////////////////////////////////////////////

// $c = "CREATE TABLE host_training_establishment (
//     HTE_ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
//     NAME varchar(30) NOT NULL,
//     INDUSTRY varchar(30) NOT NULL,
//     ADDRESS varchar(100) NOT NULL,
//     CONTACT_EMAIL varchar(50) NOT NULL,
//     CONTACT_PERSON varchar(50) NOT NULL,
//     CONTACT_NUMBER varchar(20) NOT NULL
// )";
// $s2 = $dbo->conn->prepare($c);
// try {
//     $s2->execute();
//     echo("<br>host_training_establishment Table has been created!");
// } catch (PDOException $o) {
//     echo("<br>host_training_establishment Table is already existing!");
// }

////////////////////////////////////////////////////////////////

// $c = "CREATE TABLE interns_details (
//     INTERNS_ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
//     NAME varchar(30) NOT NULL,
//     AGE int NOT NULL,
//     GENDER  varchar(10) NOT NULL,
//     EMAIL varchar(30) NOT NULL,
//     CONTACT_NUMBER int NOT NULL

// )";
// $s2 = $dbo->conn->prepare($c);
// try {
//     $s2->execute();
//     echo("<br> Table has been created!");
// } catch (PDOException $o) {
//     echo("<br> Table is already existing!");
// }


////////////////////////////////////////////////////////////////

// $c = "CREATE TABLE task (
//     TASK_ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
//     INTERN_ID int NOT NULL,
//     DESCRIPTION varchar(50) NOT NULL,
//     DEADLINE varchar(50) NOT NULL,

//     FOREIGN KEY (INTERN_ID) REFERENCES interns(INTERN_ID)

// )";
// $s2 = $dbo->conn->prepare($c);
// try {
//     $s2->execute();
//     echo("<br>task Table has been created!");
// } catch (PDOException $o) {
//     echo("<br>task Table is already existing!");
// }



////////////////////////////////////////////////////////////////

// $c = "CREATE TABLE attendance (
//     INTERNSHIP_ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
//     DATE date NOT NULL,
//     PRESENT varchar(50) NOT NULL,
//     ABSENT varchar(50) NOT NULL,
//     TIME_REMAINING varchar(50) NOT NULL,
//     TIME_TOTAL varchar(50) NOT NULL,
//     LATE varchar(30) NOT NULL,
//     EXCUSE_ABSENT varchar(30) NOT NULL
// )";
// $s2 = $dbo->conn->prepare($c);
// try {
//     $s2->execute();
//     echo("<br>attendance Table has been created!");
// } catch (PDOException $o) {
//     echo("<br>attendance Table is already existing!");
// }

////////////////////////////////////////////////////////////////

// $c = "CREATE TABLE internship_needs (
//         INTERNSHIP_ID int NOT NULL,

//         FOREIGN KEY (INTERNSHIP_ID) REFERENCES attendance(INTERNSHIP_ID)
// )";
// $s2 = $dbo->conn->prepare($c);
// try {
//     $s2->execute();
//     echo("<br>internship_needs Table has been created!");
// } catch (PDOException $o) {
//     echo("<br>internship_needs Table is already existing!");
// }
///////////////////////////////////////////////////////////
//JOINT TABLE HERE

// $c = "CREATE TABLE internships_needs (
//     INTERN_ID INT,
//     HTE_ID INT,
//     COORDINATOR_ID INT,
//     PRIMARY KEY (INTERN_ID, H.T.E_ID,COORDINATOR_ID),
//     FOREIGN KEY (INTERN_ID) REFERENCES interns(INTERN_ID),
//     FOREIGN KEY (COORDINATOR_ID) REFERENCES coordinator(COORDINATOR_ID),
//     FOREIGN KEY (HTE_ID) REFERENCES host_training_establishment(HTE_ID)
// )";
// $s2 = $dbo->conn->prepare($c);
// try {
//     $s2->execute();
//     echo("<br>joint Table has been created!");
// } catch (PDOException $o) {
//     echo("<br>joint Table is already existing!");
// }

///////////////////////////////////////////////////////////
// TABLE DROPING HERE

// try {

//     // SQL command to drop the table
//     $sql = "DROP TABLE IF EXISTS attendance_details"; // Replace 'test' with your table name

//     // Prepare and execute the statement
//     $s2 = $dbo->conn->prepare($sql);
//     $s2->execute();

//     echo "Table dropped successfully!";
// } catch (PDOException $o) {
//     echo "Error: " . $o->getMessage();
// }



//////////////////////////////////////////////
//ALTER TABLES HERE

// // DROPING CONSTRAINTS
//         $alter_sql = "ALTER TABLE internship_needs
//         DROP FOREIGN KEY internship_needs_ibfk_1;

//         ALTER TABLE internship_needs
//         ADD CONSTRAINT internship_needs_ibfk_1
//         FOREIGN KEY (INTERNS_ID) REFERENCES interns_details (INTERNS_ID)";
// // DROPING COLUMNS
// // $alter_sql = "ALTER TABLE internship_needs DROP COLUMN INTERNSHIP_ID ";
// // ADDING COLUMNS
// $alter_sql = "ALTER TABLE intern_details
// DROP FOREIGN KEY intern_details_ibfk_1;

// ALTER TABLE intern_details
// CHANGE INTERN_ID INTERNS_ID INT;

// ALTER TABLE intern_details
// ADD CONSTRAINT intern_details_ibfk_1
// FOREIGN KEY (INTERNS_ID) REFERENCES interns_details (INTERNS_ID);";

// // Prepare and execute the statement
// $alter_stmt = $dbo->conn->prepare($alter_sql);
// try {
//     $alter_stmt->execute();
//     echo("<br>TABLE COLUMN HAS BEEN ALTERED!");
// } catch (PDOException $e) {
//     echo("<br>Error altering table: " . $e->getMessage());
// }

////////////////////////////////////////////////
//SHOW CREATE TABLE

// try {
//     $query = "SHOW CREATE TABLE intern_task";
//     $stmt = $dbo->conn->query($query);
//     $result = $stmt->fetch(PDO::FETCH_ASSOC);

//     // Display the create statement
//     echo "<pre>" . $result['Create Table'] . "</pre>";
// } catch (PDOException $e) {
//     echo "Error showing table creation: " . $e->getMessage();
// }

////////////////////////////////////////////////
//ALTER TABLE FOREIGN KEY HERE

// $fk_sql = "drop TABLE intern_task";

// // Prepare and execute the statement
// $fk_stmt = $dbo->conn->prepare($fk_sql);
// try {
//     $fk_stmt->execute();
//     echo "<br>Foreign key constraint has been dropped!";
// } catch (PDOException $e) {
//     echo "<br>Error dropping foreign key constraint: " . $e->getMessage();
// }

//////////////////////////////////////////////////////
//MODIFYING HERE

// $alterQuery = "ALTER TABLE interns_details 
//                 MODIFY COLUMN CONTACT_NUMBER VARCHAR(15) NOT NULL";


// $s2 = $dbo->conn->prepare($alterQuery);
// try {
//     $s2->execute();
//     echo "<br>Table columns have been updated!";
// } catch (PDOException $e) {
//     echo "<br>Error updating table: " . $e->getMessage();
// }


//////////////////////////////////////////////////////
// //INSERT COORDINATOR DATA HERE


$c = "INSERT INTO internship_needs (INTERNS_ID, HTE_ID, COORDINATOR_ID, SESSION_ID)
        VALUES 
        (1, 1, 59828996, 2),
        (3, 2, 59828996, 2)";

$s = $dbo->conn->prepare($c);

try {
    $s->execute();
    echo "<br>DATA HAS BEEN ADDED!";
} catch (PDOException $e) {
    echo "<br>Error adding data: " . $e->getMessage();
}

//////////////////////////////////////////////////////
// INSERT TASK DATA HERE


// $c = "INSERT INTO task (TASK_ID, DESCRIPTION, DEADLINE)
//       VALUES 
//       (1, 'testingtask', 'endofthemonth')";

// $s = $dbo->conn->prepare($c);

// try {
//     $s->execute();
//     echo "<br>TASK DATA HAS BEEN ADDED!";
// } catch (PDOException $e) {
//     echo "<br>Error adding data: " . $e->getMessage();
// }

//////////////////////////////////////////////////////
// INSERT INTERNS DATA HERE


// $c = "INSERT INTO interns (INTERN_ID, COORDINATOR_ID, DESCRIPTION, DEADLINE, STATUS,TASK_ID)
//       VALUES 
//       (1, 59828996, 'testing_task1', 'end_of_the_month','ONGOING',1),
//       (2, 59828996, 'testing_task2', 'end_of_the_month','ONGOING',1),
//       (3, 59828996, 'testing_task3', 'end_of_the_month','ONGOING',1),
//       (4, 59828996, 'testing_task4', 'end_of_the_month','ONGOING',1)";

// $s = $dbo->conn->prepare($c);

// try {
//     $s->execute();
//     echo "<br>INTERNS DATA HAS BEEN ADDED!";
// } catch (PDOException $e) {
//     echo "<br>Error adding data: " . $e->getMessage();
// }

//////////////////////////////////////////////////////
// INSERT host_training_establishment DATA HERE


// $c = "INSERT INTO host_training_establishment (HTE_ID, NAME, INDUSTRY, ADDRESS, CONTACT_EMAIL,CONTACT_PERSON,CONTACT_NUMBER)
//       VALUES 
//       (5, 'TESTING_CORP', 'IT1', 'UNAHAN SA AGDAO','RASTAMAN@GMAIL.COM','RASTAMAN','09513762404'),
//       (2, 'TESTING_CORP', 'IT2', 'UNAHAN SA AGDAO','RASTAMAN@GMAIL.COM','RASTAMAN','09513762404'),
//       (3, 'TESTING_CORP', 'IT3', 'UNAHAN SA AGDAO','RASTAMAN@GMAIL.COM','RASTAMAN','09513762404'),
//       (4, 'TESTING_CORP', 'IT4', 'UNAHAN SA AGDAO','RASTAMAN@GMAIL.COM','RASTAMAN','09513762404')";

// $s = $dbo->conn->prepare($c);

// try {
//     $s->execute();
//     echo "<br>HTE DATA HAS BEEN ADDED!";
// } catch (PDOException $e) {
//     echo "<br>Error adding data: " . $e->getMessage();
// }

////////////////////////////////////////////////////////
//INSERT SESSION DETAILS DATA HERE

// $c = "INSERT INTO session_details (ID, YEAR, TERM)
//       VALUES 
//       (3, 2024, 'THIRD SEMESTER')";

// $s = $dbo->conn->prepare($c);

// try {
//     $s->execute();
//     echo "<br>DATA HAS BEEN ADDED!";
// } catch (PDOException $e) {
//     echo "<br>Error adding data: " . $e->getMessage();
// }


/////////////////////////////////////////////////////
//UPDATE HERE

// $updateIntern = "UPDATE interns_details
// SET CONTACT_NUMBER = '09513762405'
// WHERE NAME = 'Jane Doe'";
// $s3 = $dbo->conn->prepare($updateIntern);
// try {
//     $s3->execute();
//     echo "<br>INTERN DATA HAS BEEN UPDATED!";
// } catch (PDOException $e) {
//     echo "<br>Error updating intern: " . $e->getMessage();
// }

/////////////////////////////////////////////////
//SHOW TABLE

// $show_constraint_sql = "SELECT CONSTRAINT_NAME 
//                         FROM information_schema.KEY_COLUMN_USAGE 
//                         WHERE TABLE_NAME = 'internship_needs' 
//                         AND COLUMN_NAME = 'INTERNSHIP_ID' 
//                         AND REFERENCED_TABLE_NAME IS NOT NULL";

// $show_constraint_stmt = $dbo->conn->prepare($show_constraint_sql);
// $show_constraint_stmt->execute();
// $result = $show_constraint_stmt->fetchAll();

// if ($result) {
//     foreach ($result as $row) {
//         echo "<br>Constraint key for INTERNSHIP_ID: " . $row['CONSTRAINT_NAME'];
//     }
// } else {
//     echo "<br>No constraint key found for INTERNSHIP_ID";
// }


////////////////////////////////////////////////////
//POPULATE HTE

// $c = "INSERT INTO intern_details (INTERNS_ID, SESSION_ID, HTE_ID) VALUES (?, ?, ?)";
// $s = $dbo->conn->prepare($c);

// $data = [
//     [6, 1, 1],
//     [7, 1, 1],
//     [8, 1, 1],
//     [6, 1, 2],
//     [7, 1, 2],
//     [8, 1, 2],
// ];

// foreach ($data as $row) {
//     $s->bindParam(1, $row[0]);
//     $s->bindParam(2, $row[1]);
//     $s->bindParam(3, $row[2]);
//     try {
//         $s->execute();
//     } catch (PDOException $o) {
//         echo("<br>Error inserting data: " . $o->getMessage());
//     }
// }
// echo("<br>Data has been inserted!");

///////////////////////////////////////////////

// $c = "ALTER TABLE intern_details CHANGE ID SESSION_ID int";
// $s = $dbo->conn->prepare($c);
// try {
//     $s->execute();
//     echo("<br>Column has been altered!");
// } catch (PDOException $o) {
//     echo("<br>Error altering column: " . $o->getMessage());
// }

//////////////////////////////////////////////////////

// try {


//     // Insert sample data
//     $insert_query = "INSERT INTO interns_details (NAME, AGE, GENDER, EMAIL, CONTACT_NUMBER) VALUES 
//                      (:name, :age, :gender, :email, :contact_number)";
//     $stmt = $dbo->conn->prepare($insert_query);

//     // Your data
//     $data = array(
//         array('name' => 'KIM CHARLES', 'age' => 23, 'gender' => 'MALE', 'email' => 'kimcharles.emping@hcdc.edu.ph', 'contact_number' => 9513762404),
//         // Add more sample data here
//         array('name' => 'John Doe', 'age' => 25, 'gender' => 'MALE', 'email' => 'johndoe@example.com', 'contact_number' => 1234567890),
//         array('name' => 'Jane Doe', 'age' => 22, 'gender' => 'FEMALE', 'email' => 'janedoe@example.com', 'contact_number' => 9876543210)
//     );

//     foreach ($data as $row) {
//         $stmt->bindParam(':name', $row['name']);
//         $stmt->bindParam(':age', $row['age']);
//         $stmt->bindParam(':gender', $row['gender']);
//         $stmt->bindParam(':email', $row['email']);
//         $stmt->bindParam(':contact_number', $row['contact_number']);
//         $stmt->execute();
//     }

//     echo("<br> Sample data inserted!");
// } catch (PDOException $o) {
//     echo("<br> Table is already existing!");
// }

////////////////////////////////////////////////////

// $update_query = "UPDATE interns_details SET STUDENT_ID = :student_id WHERE INTERNS_ID = :interns_id";
// $stmt = $dbo->conn->prepare($update_query);

// $data = array(
//     array('student_id' => 59828996, 'interns_id' => 1),
//     array('student_id' => 59828997, 'interns_id' => 2),
//     array('student_id' => 59828998, 'interns_id' => 3)
// );

// foreach ($data as $row) {
//     $stmt->bindParam(':student_id', $row['student_id']);
//     $stmt->bindParam(':interns_id', $row['interns_id']);
//     $stmt->execute();
// }

// echo("<br> STUDENT_ID updated!");

//////////////////////////////////////////////
// $c="ALTER TABLE interns_attendance
// DROP FOREIGN KEY interns_attendance_ibfk_4";
// $s=$dbo->conn->prepare($c);
// try{
//     $s->execute();
// }
// catch(Exception $e)
// {
//     // ...
// }

// $c="ALTER TABLE interns_attendance
// ADD CONSTRAINT interns_attendance_ibfk_4
// FOREIGN KEY (INTERN_ID) REFERENCES intern_details (INTERN_ID)";
// $s=$dbo->conn->prepare($c);
// try{
//     $s->execute();
// }
// catch(Exception $e)
// {
//     // ...
// }

///////////////////////////////////////////
// $constraint_sql = "
//     SELECT CONSTRAINT_NAME 
//     FROM information_schema.KEY_COLUMN_USAGE 
//     WHERE TABLE_NAME = 'internship_needs' 
//     AND REFERENCED_TABLE_NAME IS NOT NULL;
// ";

// $constraint_stmt = $dbo->conn->prepare($constraint_sql);
// $constraint_stmt->execute();
// $constraint_name = $constraint_stmt->fetchColumn();

// $alter_sql = "
//     ALTER TABLE internship_needs
//     DROP FOREIGN KEY $constraint_name;
//     ALTER TABLE internship_needs
//     ADD CONSTRAINT internship_needs_ibfk_1
//     FOREIGN KEY (INTERNS_ID) REFERENCES interns_details (INTERNS_ID);
// ";

// $alter_stmt = $dbo->conn->prepare($alter_sql);
// try {
//     $alter_stmt->execute();
//     echo("<br>TABLE COLUMN HAS BEEN ALTERED!");
// } catch (PDOException $e) {
//     echo("<br>Error altering table: " . $e->getMessage());
// }
?>
