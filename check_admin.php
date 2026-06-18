<?php
include_once "DBConn.php";

echo "<h2>Checking Admin User</h2>";

$result = mysqli_query($conn, "SELECT user_id, full_name, email, role, verification_status FROM tblUser WHERE email = 'admin@pastimes.com'");

if(mysqli_num_rows($result) > 0){
    $admin = mysqli_fetch_assoc($result);
    echo "<p style='color: green;'>✅ Admin user FOUND!</p>";
    echo "<pre>";
    print_r($admin);
    echo "</pre>";
    
    // Check if role is correct
    if($admin['role'] == 'admin'){
        echo "<p style='color: green;'>✅ Role is correctly set to 'admin'</p>";
    } else {
        echo "<p style='color: red;'>❌ Role is set to: " . $admin['role'] . ". It should be 'admin'</p>";
        echo "<p>Run this SQL to fix: UPDATE tblUser SET role = 'admin' WHERE email = 'admin@pastimes.com';</p>";
    }
    
    // Check verification status
    if($admin['verification_status'] == 'verified'){
        echo "<p style='color: green;'>✅ Verification status is 'verified'</p>";
    } else {
        echo "<p style='color: red;'>❌ Verification status is: " . $admin['verification_status'] . ". It should be 'verified'</p>";
        echo "<p>Run this SQL to fix: UPDATE tblUser SET verification_status = 'verified' WHERE email = 'admin@pastimes.com';</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Admin user NOT found!</p>";
    echo "<p>Run this SQL to create admin:</p>";
    echo "<pre>
INSERT INTO tblUser (full_name, username, email, password, role, verification_status) 
VALUES (
    'System Administrator', 
    'admin', 
    'admin@pastimes.com', 
    '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'admin', 
    'verified'
);
    </pre>";
}

// Show all users
echo "<h3>All Users in Database:</h3>";
$all_users = mysqli_query($conn, "SELECT user_id, full_name, email, role, verification_status FROM tblUser");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";
while($user = mysqli_fetch_assoc($all_users)){
    echo "<tr>";
    echo "<td>" . $user['user_id'] . "</td>";
    echo "<td>" . $user['full_name'] . "</td>";
    echo "<td>" . $user['email'] . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "<td>" . $user['verification_status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

mysqli_close($conn);
?>