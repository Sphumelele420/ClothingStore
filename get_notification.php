<?php
session_start();
include_once "DBConn.php";

if(!isset($_SESSION['user_id'])){
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Function to get conversation ID
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

// Function to get time ago
function time_ago($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return floor($diff/60) . ' minutes ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return date('M d', $timestamp);
}

$notifications = array();

// Get unread messages from tblMessages
$messages_query = mysqli_query($conn, "SELECT m.*, u.full_name as sender_name 
                                       FROM tblMessages m
                                       JOIN tblUser u ON m.sender_id = u.user_id
                                       WHERE m.receiver_id = $user_id AND m.is_read = 0
                                       ORDER BY m.sent_at DESC LIMIT 10");

if($messages_query){
    while($msg = mysqli_fetch_assoc($messages_query)){
        $conv_id = getConversationId($conn, $user_id, $msg['sender_id']);
        $time_ago = time_ago(strtotime($msg['sent_at']));
        
        $notifications[] = array(
            'id' => $msg['message_id'],
            'type' => 'message',
            'title' => 'New message from ' . htmlspecialchars($msg['sender_name']),
            'message' => substr(htmlspecialchars($msg['message']), 0, 100),
            'link' => 'conversation.php?id=' . $conv_id,
            'is_read' => $msg['is_read'],
            'time_ago' => $time_ago
        );
    }
}

// Get unread notifications from tblNotifications
$notif_query = mysqli_query($conn, "SELECT * FROM tblNotifications 
                                    WHERE user_id = $user_id AND is_read = 0
                                    ORDER BY created_at DESC LIMIT 10");

if($notif_query){
    while($notif = mysqli_fetch_assoc($notif_query)){
        $time_ago = time_ago(strtotime($notif['created_at']));
        
        $notifications[] = array(
            'id' => $notif['notification_id'],
            'type' => $notif['type'],
            'title' => $notif['title'],
            'message' => $notif['message'],
            'link' => $notif['link'] ?? '#',
            'is_read' => $notif['is_read'],
            'time_ago' => $time_ago
        );
    }
}

// Sort notifications by time (newest first)
usort($notifications, function($a, $b) {
    // Compare time_ago strings by converting to timestamps
    return strtotime($b['time_ago']) - strtotime($a['time_ago']);
});

echo json_encode(['notifications' => $notifications]);
?>