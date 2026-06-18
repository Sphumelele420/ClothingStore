<?php
session_start();
include_once "DBConn.php";

// Notification helper functions
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

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($conversation_id == 0){
    header("Location: messages.php");
    exit();
}

// Get conversation details with error checking
$conv_query = mysqli_query($conn, "SELECT * FROM tblConversations WHERE conversation_id = $conversation_id");
if(!$conv_query){
    die("Error fetching conversation: " . mysqli_error($conn));
}

if(mysqli_num_rows($conv_query) == 0){
    header("Location: messages.php");
    exit();
}

$conversation = mysqli_fetch_assoc($conv_query);

// Determine the other user
$other_user_id = ($conversation['user1_id'] == $user_id) ? $conversation['user2_id'] : $conversation['user1_id'];

// Get other user info
$user_query = mysqli_query($conn, "SELECT * FROM tblUser WHERE user_id = $other_user_id");
if(!$user_query){
    die("Error fetching user: " . mysqli_error($conn));
}
$other_user = mysqli_fetch_assoc($user_query);

// Get product info if exists
$product = null;
$product_id_value = isset($conversation['product_id']) && $conversation['product_id'] > 0 ? $conversation['product_id'] : 0;

if($product_id_value > 0){
    $product_query = mysqli_query($conn, "SELECT * FROM tblClothes WHERE clothing_id = $product_id_value");
    if($product_query && mysqli_num_rows($product_query) > 0){
        $product = mysqli_fetch_assoc($product_query);
    }
}

// Get all messages in this conversation
$messages_query = mysqli_query($conn, "SELECT * FROM tblMessages 
                                 WHERE ((sender_id = $user_id AND receiver_id = $other_user_id) 
                                 OR (sender_id = $other_user_id AND receiver_id = $user_id))
                                 ORDER BY sent_at ASC");

if(!$messages_query){
    die("Error fetching messages: " . mysqli_error($conn));
}

// Mark messages as read
mysqli_query($conn, "UPDATE tblMessages SET is_read = 1 WHERE receiver_id = $user_id AND sender_id = $other_user_id");

// Handle new message
if(isset($_POST['send_message'])){
    $new_message = mysqli_real_escape_string($conn, $_POST['message']);
    if(!empty($new_message)){
        $insert_query = mysqli_query($conn, "INSERT INTO tblMessages (sender_id, receiver_id, message) 
                             VALUES ($user_id, $other_user_id, '$new_message')");
        
        if($insert_query){
            // Update conversation last message
            mysqli_query($conn, "UPDATE tblConversations SET last_message = '$new_message', last_message_time = NOW() 
                                 WHERE conversation_id = $conversation_id");
        }
        
        header("Location: conversation.php?id=$conversation_id");
        exit();
    }
}

// Get unread counts for notification bell
$unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
$unread_notifications = getUnreadNotificationCount($conn, $_SESSION['user_id']);
$total_unread = $unread_messages + $unread_notifications;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation - Pastimes Atelier</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --cream: #f5f0e8;
            --border: #e8e4db;
            --text-muted: #888;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            background-color: var(--cream); 
            color: var(--dark); 
            font-family: 'Inter', sans-serif;
        }

        /* Navigation */
        .navbar {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .logo span {
            font-size: 0.6rem;
            letter-spacing: 2px;
            color: var(--red);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 1px;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--red);
        }

        /* Notification Icon Styles */
        .notification-icon {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--red);
            color: white;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            top: 35px;
            right: 0;
            width: 350px;
            max-height: 400px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            overflow: hidden;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 14px;
            background: #fafafa;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .notification-item:hover {
            background: #f5f5f5;
        }

        .notification-item.unread {
            background: #fffbf0;
        }

        .notification-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 11px;
            color: #666;
            margin-bottom: 4px;
        }

        .notification-time {
            font-size: 10px;
            color: #999;
        }

        .notification-footer {
            padding: 10px 15px;
            border-top: 1px solid var(--border);
            text-align: center;
            background: #fafafa;
        }

        .notification-footer a {
            text-decoration: none;
            font-size: 12px;
            color: var(--red);
        }

        .empty-notifications {
            padding: 30px;
            text-align: center;
            color: #999;
            font-size: 13px;
        }

        /* Chat Container */
        .chat-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .chat-header {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-user h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.2rem;
            margin-bottom: 0.25rem;
        }

        .chat-user p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .back-btn {
            color: var(--red);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.3s;
        }

        .back-btn:hover {
            color: var(--red-dark);
        }

        .messages-area {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            height: 500px;
            overflow-y: auto;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .message {
            margin-bottom: 1.5rem;
            display: flex;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.received {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            position: relative;
        }

        .message.sent .message-bubble {
            background: var(--red);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }

        .message.received .message-bubble {
            background: #f0f0f0;
            color: var(--dark);
            border-bottom-left-radius: 0.25rem;
        }

        .message-time {
            font-size: 0.65rem;
            margin-top: 0.25rem;
            opacity: 0.7;
        }

        .message.sent .message-time {
            text-align: right;
        }

        .message-input-area {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1rem;
            display: flex;
            gap: 1rem;
        }

        .message-input-area textarea {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-family: inherit;
            resize: none;
            font-size: 0.85rem;
            transition: border-color 0.3s;
        }

        .message-input-area textarea:focus {
            outline: none;
            border-color: var(--red);
        }

        .send-btn {
            background: var(--dark);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .send-btn:hover {
            background: var(--red);
        }

        .footer {
            background: var(--dark);
            color: white;
            padding: 2rem;
            margin-top: 4rem;
            text-align: center;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            font-size: 0.7rem;
            letter-spacing: 1px;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--red);
        }

        .footer p {
            font-size: 0.7rem;
            color: #888;
        }

        @media (max-width: 768px) {
            .message-bubble {
                max-width: 85%;
            }
            .chat-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="marketplace.php" style="text-decoration: none; color: inherit;">
                    <h1>Pastimes</h1>
                    <span>DIGITAL ATELIER</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="marketplace.php">Gallery</a>
                
                <div class="notification-icon" onclick="toggleNotifications(event)">
                    <span>🔔</span>
                    <?php if($total_unread > 0): ?>
                        <span class="notification-badge"><?php echo $total_unread > 9 ? '9+' : $total_unread; ?></span>
                    <?php endif; ?>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            Notifications (<?php echo $total_unread; ?> unread)
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div style="text-align: center; padding: 20px;">Loading...</div>
                        </div>
                        <div class="notification-footer">
                            <a href="messages.php">View all messages →</a>
                        </div>
                    </div>
                </div>
                
                <a href="messages.php" style="color: var(--red);">Messages</a>
                <a href="logout.php">Logout</a>
                <span style="color: var(--red); font-size: 0.8rem;">
                    Welcome, <?php echo $_SESSION['full_name']; ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="chat-container">
        <div class="chat-header">
            <div class="chat-user">
                <h2>Conversation with <?php echo htmlspecialchars($other_user['full_name']); ?></h2>
                <?php if($product): ?>
                <p>Regarding: <?php echo htmlspecialchars($product['title']); ?></p>
                <?php endif; ?>
            </div>
            <a href="messages.php" class="back-btn">← Back to Messages</a>
        </div>

        <div class="messages-area" id="messagesArea">
            <?php if(mysqli_num_rows($messages_query) > 0): ?>
                <?php while($msg = mysqli_fetch_assoc($messages_query)): ?>
                <div class="message <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                    <div class="message-bubble">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                        <div class="message-time"><?php echo date('M d, g:i A', strtotime($msg['sent_at'])); ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    No messages yet. Start the conversation!
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" class="message-input-area">
            <textarea name="message" rows="3" placeholder="Type your message here..." required></textarea>
            <button type="submit" name="send_message" class="send-btn">Send →</button>
        </form>
    </div>

    <footer class="footer">
        <div class="footer-links">
            <a href="#">THE HERITAGE</a>
            <a href="#">SUSTAINABILITY</a>
            <a href="#">AUTHENTICATION</a>
            <a href="#">TERMS OF SERVICE</a>
        </div>
        <p>© 2024 PASTIMES DIGITAL ATELIER. ALL RIGHTS RESERVED.</p>
    </footer>

    <script>
        // Auto-scroll to bottom of messages
        const messagesArea = document.getElementById('messagesArea');
        messagesArea.scrollTop = messagesArea.scrollHeight;
        
        // Notification functions
        function toggleNotifications(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
            
            if (dropdown.classList.contains('show')) {
                loadNotifications();
            }
        }
        
        function loadNotifications() {
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('notificationList');
                    if (data.notifications && data.notifications.length === 0) {
                        container.innerHTML = '<div class="empty-notifications">No new notifications</div>';
                        return;
                    }
                    
                    let html = '';
                    data.notifications.forEach(notif => {
                        html += `
                            <a href="${notif.link}" class="notification-item ${notif.is_read == 0 ? 'unread' : ''}">
                                <div class="notification-title">${notif.title}</div>
                                <div class="notification-message">${notif.message}</div>
                                <div class="notification-time">${notif.time_ago}</div>
                            </a>
                        `;
                    });
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    document.getElementById('notificationList').innerHTML = '<div class="empty-notifications">Unable to load notifications</div>';
                });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
        });
        
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                loadNotifications();
            }
            // Update badge count
            fetch('get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.notification-badge');
                    if (data.total > 0) {
                        if (badge) {
                            badge.textContent = data.total > 9 ? '9+' : data.total;
                        } else {
                            const icon = document.querySelector('.notification-icon');
                            if (icon) {
                                icon.innerHTML += `<span class="notification-badge">${data.total > 9 ? '9+' : data.total}</span>`;
                            }
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(error => console.error('Error updating badge:', error));
        }, 30000);
    </script>
</body>
</html>