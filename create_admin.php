<?php
// create_admin.php - Run this file once to create admin user
include_once "DBConn.php";

// Delete existing admin
mysqli_query($conn, "DELETE FROM tblUser WHERE email = 'admin@pastimes.com'");

// Create admin user
$full_name = "System Administrator";
$username = "admin";
$email = "admin@pastimes.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "admin";
$verification_status = "verified";

$sql = "INSERT INTO tblUser (full_name, username, email, password, role, verification_status) 
        VALUES ('$full_name', '$username', '$email', '$password', '$role', '$verification_status')";

if(mysqli_query($conn, $sql)){
    echo "✅ Admin user created successfully!<br>";
    echo "Email: admin@pastimes.com<br>";
    echo "Password: admin123<br>";
} else {
    echo "❌ Error: " . mysqli_error($conn);
}

// Show all users
$result = mysqli_query($conn, "SELECT user_id, full_name, email, role, verification_status FROM tblUser");
echo "<h3>All Users in Database:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";
while($row = mysqli_fetch_assoc($result)){
    echo "<tr>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . $row['full_name'] . "</td>";
    echo "<td>" . $row['email'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td>" . $row['verification_status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

mysqli_close($conn);
?>