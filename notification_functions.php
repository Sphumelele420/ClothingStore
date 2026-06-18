<?php
// includes/notification_functions.php - Helper functions for notifications

function getUnreadMessageCount($conn, $user_id) {
    $query = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblMessages 
                                  WHERE receiver_id = $user_id AND is_read = 0");
    if($query){
        $result = mysqli_fetch_assoc($query);
        return $result['count'];
    }
    return 0;
}

function getUnreadNotificationCount($conn, $user_id) {
    $query = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblNotifications 
                                  WHERE user_id = $user_id AND is_read = 0");
    if($query){
        $result = mysqli_fetch_assoc($query);
        return $result['count'];
    }
    return 0;
}

function getTotalUnreadCount($conn, $user_id) {
    return getUnreadMessageCount($conn, $user_id) + getUnreadNotificationCount($conn, $user_id);
}

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

function markAllAsRead($conn, $user_id) {
    mysqli_query($conn, "UPDATE tblMessages SET is_read = 1 WHERE receiver_id = $user_id");
    mysqli_query($conn, "UPDATE tblNotifications SET is_read = 1 WHERE user_id = $user_id");
}

function time_ago($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return floor($diff/60) . ' minutes ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return date('M d', $timestamp);
}
?>