<?php
session_start();
include_once "DBConn.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer'){
    header("Location: login.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$buyer_id = $_SESSION['user_id'];

if($product_id > 0){
    // Check if already in cart
    $check = mysqli_query($conn, "SELECT * FROM tblCart WHERE buyer_id=$buyer_id AND clothing_id=$product_id");
    
    if(mysqli_num_rows($check) == 0){
        mysqli_query($conn, "INSERT INTO tblCart (buyer_id, clothing_id) VALUES ($buyer_id, $product_id)");
    }
    
    header("Location: cart.php");
    exit();
}

header("Location: marketplace.php");
exit();
?>