<?php
// loadClothingStore.php - Script to create all tables and load data
include_once 'DBConn.php';

// Read the SQL file
$sql_file = file_get_contents('myClothingStore.sql');

// Split into individual queries
$queries = explode(';', $sql_file);

$success_count = 0;
$error_count = 0;

foreach($queries as $query) {
    $query = trim($query);
    if(!empty($query)) {
        if(mysqli_query($conn, $query)) {
            $success_count++;
            echo "✓ Query executed successfully<br>";
        } else {
            $error_count++;
            echo "✗ Error: " . mysqli_error($conn) . "<br>";
        }
    }
}

echo "<hr>";
echo "<h3>Database Setup Complete!</h3>";
echo "<p>Successful queries: $success_count</p>";
echo "<p>Failed queries: $error_count</p>";

mysqli_close($conn);
?>