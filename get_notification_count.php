<?php
session_start();
include_once "DBConn.php";

if(!isset($_SESSION['user_id'])){
    echo json_encode(['total' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get unread messages count
$msg_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblMessages 
                                  WHERE receiver_id = $user_id AND is_read = 0");
$msg_count = 0;
if($msg_query){
    $result = mysqli_fetch_assoc($msg_query);
    $msg_count = $result['count'];
}

// Get unread notifications count
$notif_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblNotifications 
                                    WHERE user_id = $user_id AND is_read = 0");
$notif_count = 0;
if($notif_query){
    $result = mysqli_fetch_assoc($notif_query);
    $notif_count = $result['count'];
}

$total = $msg_count + $notif_count;

echo json_encode(['total' => $total]);
?>