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

// Function to create or update conversation
function createConversation($conn, $user1_id, $user2_id, $message) {
    // Ensure user1 is always the smaller ID for consistency
    $user1 = min($user1_id, $user2_id);
    $user2 = max($user1_id, $user2_id);
    
    // Check if conversation exists
    $check = mysqli_query($conn, "SELECT conversation_id FROM tblConversations 
                                  WHERE (user1_id = $user1 AND user2_id = $user2) 
                                  OR (user1_id = $user2 AND user2_id = $user1)");
    
    if($check && mysqli_num_rows($check) > 0){
        $conv = mysqli_fetch_assoc($check);
        // Update existing conversation
        mysqli_query($conn, "UPDATE tblConversations SET 
                             last_message = '$message', 
                             last_message_time = NOW() 
                             WHERE conversation_id = {$conv['conversation_id']}");
        return $conv['conversation_id'];
    } else {
        // Create new conversation
        mysqli_query($conn, "INSERT INTO tblConversations (user1_id, user2_id, last_message, last_message_time) 
                             VALUES ($user1, $user2, '$message', NOW())");
        return mysqli_insert_id($conn);
    }
}

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sender_name = $_SESSION['full_name'];

// Get admin user
$admin_query = mysqli_query($conn, "SELECT user_id FROM tblUser WHERE role = 'admin' LIMIT 1");
$admin = mysqli_fetch_assoc($admin_query);
$admin_id = $admin['user_id'];

$message = '';
$error = '';

if(isset($_POST['send_message'])){
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message_text = mysqli_real_escape_string($conn, $_POST['message']);
    
    if(empty($subject) || empty($message_text)){
        $error = "Please fill in all fields.";
    } else {
        $full_message = "Subject: " . $subject . "\n\n" . $message_text;
        
        // Insert message into tblMessages
        $insert = mysqli_query($conn, "INSERT INTO tblMessages (sender_id, receiver_id, message) 
                                       VALUES ($user_id, $admin_id, '$full_message')");
        
        if($insert){
            // Create or update conversation - THIS IS THE CRITICAL PART
            createConversation($conn, $user_id, $admin_id, $full_message);
            
            // Create notification for admin
            mysqli_query($conn, "INSERT INTO tblNotifications (user_id, type, title, message, link) 
                                 VALUES ($admin_id, 'message', 'New message from $sender_name', 
                                         '$subject', 'admin/messages.php')");
            
            $message = "Your message has been sent to the administrator. They will respond shortly.";
        } else {
            $error = "Error sending message. Please try again.";
        }
    }
}

// Get unread counts
$unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
$unread_notifications = getUnreadNotificationCount($conn, $_SESSION['user_id']);
$total_unread = $unread_messages + $unread_notifications;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Admin - Pastimes Atelier</title>
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
            background: var(--cream); 
            font-family: 'Inter', sans-serif;
            color: var(--dark);
            min-height: 100vh;
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

        .nav-contact {
            color: var(--red) !important;
            font-weight: 600;
        }

        .nav-contact:hover {
            color: var(--red-dark) !important;
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

        /* Contact Container */
        .contact-container {
            max-width: 700px;
            margin: 3rem auto;
            padding: 0 2rem;
        }

        .contact-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .contact-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .contact-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .contact-header .badge {
            display: inline-block;
            background: var(--red);
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 20px;
            font-size: 0.7rem;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
        }

        .contact-card {
            background: white;
            border-radius: 1rem;
            padding: 2.5rem;
            border: 1px solid var(--border);
            box-shadow: 0 5px 25px rgba(0,0,0,0.05);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }

        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .form-group input:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(196, 69, 54, 0.1);
        }

        .btn-send {
            background: var(--red);
            color: white;
            padding: 0.9rem 2rem;
            border: none;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 1px;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-send:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(196, 69, 54, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d64;
            border-left: 4px solid #2e7d64;
        }

        .alert-error {
            background: #ffebee;
            color: var(--red);
            border-left: 4px solid var(--red);
        }

        .info-box {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .info-box p {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .info-box a {
            color: var(--red);
            text-decoration: none;
            font-weight: 600;
        }

        .info-box a:hover {
            text-decoration: underline;
        }

        /* Footer */
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
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            .contact-card {
                padding: 1.5rem;
            }
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
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
                    
                    <a href="messages.php">Messages</a>
                    <a href="contact_admin.php" class="nav-contact">📧 Contact Admin</a>
                    
                    <div class="notification-icon" onclick="toggleNotifications(event)">
                        <span>🔔</span>
                        <?php if($total_unread > 0): ?>
                            <span class="notification-badge"><?php echo $total_unread > 9 ? '9+' : $total_unread; ?></span>
                        <?php endif; ?>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">Notifications (<?php echo $total_unread; ?> unread)</div>
                            <div class="notification-list" id="notificationList"><div style="text-align:center;padding:20px;">Loading...</div></div>
                            <div class="notification-footer"><a href="messages.php">View all messages →</a></div>
                        </div>
                    </div>
                    
                    <a href="logout.php">Logout</a>
                    <span style="color: var(--red); font-size: 0.8rem;">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <?php else: ?>
                    <a href="login.php">Sign In</a>
                    <a href="register.php" class="nav-cta" style="background: var(--red); color: white; padding: 0.5rem 1.25rem; border-radius: 2rem;">Join Pastimes</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="contact-container">
        <div class="contact-header">
            <span class="badge">ADMIN SUPPORT</span>
            <h1>Contact Administrator</h1>
            <p>Have questions, concerns, or need assistance? Our administrators are here to help.</p>
        </div>

        <div class="contact-card">
            <?php if($message): ?>
                <div class="alert alert-success">✓ <?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="What is this about?" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="6" placeholder="Describe your question or concern in detail..." required></textarea>
                </div>
                <button type="submit" name="send_message" class="btn-send">Send Message →</button>
            </form>

            <div class="info-box">
                <p>💡 You can also message sellers directly from any product page.</p>
                <p style="margin-top: 0.5rem;">
                    <a href="messages.php">View all your messages →</a>
                </p>
            </div>
        </div>
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
    
    document.addEventListener('click', function() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) dropdown.classList.remove('show');
    });
    </script>

</body>
</html>