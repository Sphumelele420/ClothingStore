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

// Get all conversations for this user
$conversations = mysqli_query($conn, "SELECT DISTINCT 
    c.conversation_id,
    c.product_id,
    c.last_message,
    c.last_message_time,
    CASE 
        WHEN c.user1_id = $user_id THEN u2.full_name
        ELSE u1.full_name
    END as other_user_name,
    CASE 
        WHEN c.user1_id = $user_id THEN u2.user_id
        ELSE u1.user_id
    END as other_user_id,
    cl.title as product_title,
    (SELECT COUNT(*) FROM tblMessages WHERE receiver_id = $user_id AND is_read = 0 AND sender_id = other_user_id) as unread_count
FROM tblConversations c
JOIN tblUser u1 ON c.user1_id = u1.user_id
JOIN tblUser u2 ON c.user2_id = u2.user_id
LEFT JOIN tblClothes cl ON c.product_id = cl.clothing_id
WHERE c.user1_id = $user_id OR c.user2_id = $user_id
ORDER BY c.last_message_time DESC");

// Mark messages as read when viewing a conversation
if(isset($_GET['conv_id'])){
    $conv_id = (int)$_GET['conv_id'];
    // Get the other user ID from conversation
    $conv_info = mysqli_query($conn, "SELECT * FROM tblConversations WHERE conversation_id = $conv_id");
    $conv = mysqli_fetch_assoc($conv_info);
    $other_id = ($conv['user1_id'] == $user_id) ? $conv['user2_id'] : $conv['user1_id'];
    
    mysqli_query($conn, "UPDATE tblMessages SET is_read = 1 WHERE receiver_id = $user_id AND sender_id = $other_id");
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
    <title>Messages - Pastimes Atelier</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --cream: #f5f0e8;
            --border: #e8e4db;
            --text-muted: #888;
            --success: #2e7d64;
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

        .nav-cta {
            background: var(--red);
            color: white !important;
            padding: 0.5rem 1.25rem;
            border-radius: 2rem;
        }

        .nav-cta:hover {
            background: var(--red-dark);
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

        /* Messages Container */
        .messages-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .messages-header {
            margin-bottom: 2rem;
            border-left: 4px solid var(--red);
            padding-left: 1.5rem;
        }

        .messages-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .messages-header p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Conversation List */
        .conversation-list {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .conversation-item {
            display: flex;
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .conversation-item:hover {
            background: #fafafa;
        }

        .conversation-item.unread {
            background: #fffbf0;
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            background: var(--red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .conversation-details {
            flex: 1;
            padding-left: 1rem;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.25rem;
        }

        .sender-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .message-time {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .product-ref {
            font-size: 0.7rem;
            color: var(--red);
            margin-bottom: 0.25rem;
        }

        .last-message {
            font-size: 0.85rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            background: var(--red);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 4rem;
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
        }

        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .btn-message {
            display: inline-block;
            text-decoration: none;
            color: var(--dark);
            border: 1px solid var(--border);
            padding: 8px 20px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
            background: white;
            cursor: pointer;
            border-radius: 30px;
        }

        .btn-message:hover {
            background: var(--red);
            color: white;
            border-color: var(--red);
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
            .conversation-header {
                flex-direction: column;
            }
            .message-time {
                margin-top: 0.25rem;
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
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'seller'): ?>
                        <a href="seller/my_atelier.php">My Atelier</a>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <a href="admin/dashboard.php">Dashboard</a>
                    <?php else: ?>
                        <a href="cart.php">Cart</a>
                    <?php endif; ?>
                    
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
                <?php else: ?>
                    <a href="login.php">Sign In</a>
                    <a href="register.php" class="nav-cta">Join Atelier</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="messages-container">
        <div class="messages-header">
            <h1>Your Messages</h1>
            <p>Communicate with curators and collectors about heritage pieces</p>
        </div>

        <?php if(mysqli_num_rows($conversations) == 0): ?>
            <div class="empty-state">
                <p>No messages yet.</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem;">Start a conversation by messaging a seller from any product page.</p>
                <a href="marketplace.php" class="btn-message" style="margin-top: 1rem; display: inline-block;">Browse Gallery</a>
            </div>
        <?php else: ?>
            <div class="conversation-list">
                <?php while($conv = mysqli_fetch_assoc($conversations)): ?>
                <a href="conversation.php?id=<?php echo $conv['conversation_id']; ?>" class="conversation-item <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?>">
                    <div class="conversation-avatar">
                        <?php echo strtoupper(substr($conv['other_user_name'], 0, 2)); ?>
                    </div>
                    <div class="conversation-details">
                        <div class="conversation-header">
                            <span class="sender-name"><?php echo htmlspecialchars($conv['other_user_name']); ?></span>
                            <span class="message-time"><?php echo date('M d, g:i A', strtotime($conv['last_message_time'])); ?></span>
                        </div>
                        <?php if($conv['product_title']): ?>
                        <div class="product-ref">Re: <?php echo htmlspecialchars($conv['product_title']); ?></div>
                        <?php endif; ?>
                        <div class="last-message"><?php echo htmlspecialchars(substr($conv['last_message'], 0, 100)); ?></div>
                    </div>
                    <?php if($conv['unread_count'] > 0): ?>
                    <div class="unread-badge"><?php echo $conv['unread_count']; ?></div>
                    <?php endif; ?>
                </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
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