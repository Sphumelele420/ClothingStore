<?php
session_start();
include_once "DBConn.php";
include_once "includes/notification_functions.php";

if(!isset($_SESSION['user_id'])){
    echo json_encode(['total' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];
$total = getTotalUnreadCount($conn, $user_id);

echo json_encode(['total' => $total]);
?>