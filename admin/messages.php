<?php
session_start();
include_once "../DBConn.php";

// Check admin access
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// DEBUG: Check if there are messages for admin
$debug_messages = mysqli_query($conn, "SELECT * FROM tblMessages WHERE receiver_id = $user_id OR sender_id = $user_id");
$debug_count = mysqli_num_rows($debug_messages);

// Get all conversations for admin
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
    u2.email as other_user_email,
    u2.role as other_user_role,
    cl.title as product_title,
    (SELECT COUNT(*) FROM tblMessages WHERE receiver_id = $user_id AND is_read = 0 AND sender_id = other_user_id) as unread_count
FROM tblConversations c
JOIN tblUser u1 ON c.user1_id = u1.user_id
JOIN tblUser u2 ON c.user2_id = u2.user_id
LEFT JOIN tblClothes cl ON c.product_id = cl.clothing_id
WHERE c.user1_id = $user_id OR c.user2_id = $user_id
ORDER BY c.last_message_time DESC");

// Get unread counts
$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tblMessages WHERE receiver_id = $user_id AND is_read = 0"));
$unread_notifications = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tblNotifications WHERE user_id = $user_id AND is_read = 0"));
$total_unread = ($unread_messages['count'] ?? 0) + ($unread_notifications['count'] ?? 0);

// Get all users for debugging
$all_users = mysqli_query($conn, "SELECT user_id, full_name, role FROM tblUser");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages - Pastimes</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #121212;
            --border: #e8e4db;
            --text-muted: #888;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { background-color: #f8f7f2; color: var(--dark); margin: 0; font-family: 'Inter', sans-serif; }

        /* Admin Navbar */
        .admin-nav { background: var(--dark); color: white; padding: 1rem 0; border-bottom: 2px solid var(--red); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; }
        .logo a { text-decoration: none; color: white; }
        .logo h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; letter-spacing: -0.5px; }
        .logo span { color: var(--red); font-size: 0.6rem; letter-spacing: 3px; }
        .nav-links { display: flex; align-items: center; gap: 1.5rem; }
        .nav-links a { color: white; text-decoration: none; font-size: 0.8rem; transition: color 0.3s; }
        .nav-links a:hover { color: var(--red); }
        .nav-links a.active { color: var(--red); }

        .btn-logout { background: var(--red); color: white !important; padding: 0.5rem 1.2rem; border-radius: 4px; text-decoration: none; transition: all 0.3s; }
        .btn-logout:hover { background: var(--red-dark); transform: translateY(-1px); }

        /* Notification Badge */
        .badge {
            background: var(--red);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.65rem;
            font-weight: bold;
            margin-left: 5px;
        }

        .admin-container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }

        .page-header { margin-bottom: 2rem; border-left: 4px solid var(--red); padding-left: 1.5rem; }
        .page-header h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; font-weight: 600; }
        .page-header p { color: var(--text-muted); font-size: 0.9rem; }

        /* Stats Bar */
        .stats-bar { display: flex; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .stat-box { background: white; padding: 1rem 1.5rem; border-radius: 0.5rem; border: 1px solid var(--border); flex: 1; min-width: 150px; text-align: center; }
        .stat-box .number { font-size: 1.5rem; font-weight: 700; color: var(--red); }
        .stat-box .label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }

        .conversation-list { background: white; border: 1px solid var(--border); border-radius: 0.75rem; overflow: hidden; }
        .conversation-item { display: flex; padding: 1.25rem; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.3s; text-decoration: none; color: inherit; }
        .conversation-item:hover { background: #fafafa; }
        .conversation-item.unread { background: #fffbf0; }
        .conversation-avatar { width: 50px; height: 50px; background: var(--red); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.2rem; flex-shrink: 0; }
        .conversation-details { flex: 1; padding-left: 1rem; }
        .conversation-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.25rem; }
        .sender-name { font-weight: 600; font-size: 1rem; }
        .user-role-badge { font-size: 0.6rem; background: #eee; padding: 0.2rem 0.5rem; border-radius: 2rem; color: #666; margin-left: 0.5rem; }
        .message-time { font-size: 0.7rem; color: var(--text-muted); }
        .product-ref { font-size: 0.7rem; color: var(--red); margin-bottom: 0.25rem; }
        .last-message { font-size: 0.85rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .unread-badge { background: var(--red); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: bold; }

        .empty-state { text-align: center; padding: 4rem; background: white; border: 1px solid var(--border); border-radius: 0.75rem; }
        .empty-state p { color: var(--text-muted); }

        .footer { background: var(--dark); color: white; padding: 2rem; margin-top: 4rem; text-align: center; }
        .footer-links { display: flex; justify-content: center; gap: 2rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .footer-links a { color: white; text-decoration: none; font-size: 0.7rem; letter-spacing: 1px; }
        .footer-links a:hover { color: var(--red); }

        /* Debug box */
        .debug-box { background: #fff3cd; border: 1px solid #ffc107; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.8rem; }
        .debug-box strong { color: #856404; }

        @media (max-width: 768px) {
            .nav-container { flex-direction: column; gap: 1rem; }
            .conversation-header { flex-direction: column; }
            .message-time { margin-top: 0.25rem; }
            .stats-bar { flex-direction: column; }
        }
    </style>
</head>
<body>

    <nav class="admin-nav">
        <div class="nav-container">
            <div class="logo">
                <a href="dashboard.php">
                    <h1>PASTIMES</h1>
                    <span>ADMIN CONSOLE</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_users.php">Users</a>
                <a href="approve_items.php">Listings</a>
                <a href="seller_verification.php">Verifications</a>
                <a href="messages.php" class="active">Messages
                    <?php if($unread_messages['count'] > 0): ?>
                        <span class="badge"><?php echo $unread_messages['count']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="../logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="page-header">
            <h1>Admin Messages</h1>
            <p>Communicate with buyers, sellers, and all platform users</p>
        </div>

        <!-- DEBUG INFO 
        <div class="debug-box">
            <strong>🔍 Debug Information:</strong><br>
            Your User ID: <?php echo $user_id; ?><br>
            Total Messages in DB for you: <?php echo $debug_count; ?><br>
            Conversations found: <?php echo mysqli_num_rows($conversations); ?>
        </div>-->

        <!-- Stats -->
        <div class="stats-bar">
            <div class="stat-box">
                <div class="number"><?php echo mysqli_num_rows($conversations); ?></div>
                <div class="label">Total Conversations</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $unread_messages['count'] ?? 0; ?></div>
                <div class="label">Unread Messages</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php 
                    $total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tblUser"));
                    echo $total_users['count'];
                ?></div>
                <div class="label">Total Users</div>
            </div>
        </div>

        <?php if(mysqli_num_rows($conversations) == 0): ?>
            <div class="empty-state">
                <p>No conversations yet.</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem;">Users can contact you through the "Contact Admin" page or by messaging you directly.</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem; color: #856404;">
                    💡 If you've sent messages but don't see them here, try sending a new message through "Contact Admin" page.
                </p>
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
                            <span>
                                <span class="sender-name"><?php echo htmlspecialchars($conv['other_user_name']); ?></span>
                                <span class="user-role-badge"><?php echo strtoupper($conv['other_user_role']); ?></span>
                            </span>
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

</body>
</html>