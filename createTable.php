<?php
// createTable.php - Creates and populates tblUser
include_once 'DBConn.php';

// Drop tblUser if it exists
$sql_drop = "DROP TABLE IF EXISTS tblUser";
if (mysqli_query($conn, $sql_drop)) {
    echo "Table tblUser dropped successfully.<br>";
} else {
    echo "Error dropping table: " . mysqli_error($conn) . "<br>";
}

// Create tblUser table
$sql_create = "CREATE TABLE tblUser (
    user_id INT(11) NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer','seller','admin') DEFAULT 'buyer',
    verification_status ENUM('pending','verified','rejected') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (user_id),
    UNIQUE KEY email (email),
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql_create)) {
    echo "Table tblUser created successfully.<br>";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "<br>";
}

// Load data from userData.txt
$filename = "userData.txt";
if (file_exists($filename)) {
    $file = fopen($filename, "r");
    $line_count = 0;
    
    while (($line = fgets($file)) !== false) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $data = explode("|", $line);
        if (count($data) == 5) {
            $full_name = mysqli_real_escape_string($conn, $data[0]);
            $username = strtolower(str_replace(' ', '_', $data[0]));
            $email = mysqli_real_escape_string($conn, $data[1]);
            $password = mysqli_real_escape_string($conn, $data[2]);
            $role = mysqli_real_escape_string($conn, $data[3]);
            $verification_status = mysqli_real_escape_string($conn, $data[4]);
            
            $sql_insert = "INSERT INTO tblUser (full_name, username, email, password, role, verification_status) 
                           VALUES ('$full_name', '$username', '$email', '$password', '$role', '$verification_status')";
            
            if (mysqli_query($conn, $sql_insert)) {
                $line_count++;
            } else {
                echo "Error inserting record: " . mysqli_error($conn) . "<br>";
            }
        }
    }
    fclose($file);
    echo "$line_count records inserted into tblUser successfully.<br>";
} else {
    echo "userData.txt file not found.<br>";
}

mysqli_close($conn);
?>