<?php
session_start();
include_once "DBConn.php";

// Notification helper functions (embedded directly)
function createNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $link = $link ? "'$link'" : "NULL";
    $query = mysqli_query($conn, "INSERT INTO tblNotifications (user_id, type, title, message, link) 
                                   VALUES ($user_id, '$type', '$title', '$message', $link)");
    return $query;
}

function getConversationId($conn, $user_id, $other_id) {
    $query = mysqli_query($conn, "SELECT conversation_id FROM tblConversations 
                                  WHERE (user1_id = $user_id AND user2_id = $other_id) 
                                  OR (user1_id = $other_id AND user2_id = $user_id) LIMIT 1");
    if($query && mysqli_num_rows($query) > 0){
        $row = mysqli_fetch_assoc($query);
        return $row['conversation_id'];
    }
    return 0;
}

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$message = mysqli_real_escape_string($conn, $_POST['message']);

if($receiver_id > 0 && !empty($message)){
    // Insert message
    $insert_query = mysqli_query($conn, "INSERT INTO tblMessages (sender_id, receiver_id, product_id, message) 
                                         VALUES ($sender_id, $receiver_id, $product_id, '$message')");
    
    if($insert_query){
        // Create notification for receiver
        $sender_name = $_SESSION['full_name'];
        $conversation_id = getConversationId($conn, $sender_id, $receiver_id);
        createNotification($conn, $receiver_id, 'message', 
                          "New message from $sender_name", 
                          substr($message, 0, 100), 
                          "conversation.php?id=$conversation_id");
    }
    
    // Update or create conversation
    $user1 = min($sender_id, $receiver_id);
    $user2 = max($sender_id, $receiver_id);
    
    $check_conv = mysqli_query($conn, "SELECT conversation_id FROM tblConversations 
                                       WHERE ((user1_id=$user1 AND user2_id=$user2) OR (user1_id=$user2 AND user2_id=$user1))
                                       AND product_id = $product_id");
    
    if($check_conv && mysqli_num_rows($check_conv) > 0){
        $conv = mysqli_fetch_assoc($check_conv);
        mysqli_query($conn, "UPDATE tblConversations SET last_message='$message', last_message_time=NOW() 
                             WHERE conversation_id = {$conv['conversation_id']}");
    } else {
        mysqli_query($conn, "INSERT INTO tblConversations (user1_id, user2_id, product_id, last_message, last_message_time) 
                             VALUES ($user1, $user2, $product_id, '$message', NOW())");
    }
}

// Redirect back to product page or messages page
if($product_id > 0){
    header("Location: product_details.php?id=$product_id&msg=sent");
} else {
    header("Location: messages.php");
}
exit();
?>