<?php
session_start();
include_once "../DBConn.php";

// Check admin access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($conversation_id == 0){
    header("Location: messages.php");
    exit();
}

// Get conversation details
$conv_query = mysqli_query($conn, "SELECT * FROM tblConversations WHERE conversation_id = $conversation_id");
if(!$conv_query || mysqli_num_rows($conv_query) == 0){
    header("Location: messages.php");
    exit();
}

$conversation = mysqli_fetch_assoc($conv_query);

// Determine the other user (not admin)
$other_user_id = ($conversation['user1_id'] == $user_id) ? $conversation['user2_id'] : $conversation['user1_id'];

// Get other user info
$other_user_query = mysqli_query($conn, "SELECT * FROM tblUser WHERE user_id = $other_user_id");
$other_user = mysqli_fetch_assoc($other_user_query);

// Get product info if exists
$product = null;
if($conversation['product_id'] && $conversation['product_id'] > 0){
    $product_query = mysqli_query($conn, "SELECT * FROM tblClothes WHERE clothing_id = {$conversation['product_id']}");
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

// Get unread counts for admin
$unread_messages_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblMessages WHERE receiver_id = $user_id AND is_read = 0");
$unread_messages = mysqli_fetch_assoc($unread_messages_query);
$unread_count = $unread_messages['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation - Admin Panel</title>
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

        body { background-color: #f8f7f2; color: var(--dark); font-family: 'Inter', sans-serif; }

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

        .badge { background: var(--red); color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.65rem; font-weight: bold; margin-left: 5px; }

        .admin-container { max-width: 900px; margin: 2rem auto; padding: 0 2rem; }

        .chat-header { background: white; border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .chat-user h2 { font-family: 'Cormorant Garamond', serif; font-size: 1.2rem; margin-bottom: 0.25rem; }
        .chat-user p { font-size: 0.8rem; color: var(--text-muted); }
        .chat-user .user-role { font-size: 0.7rem; color: var(--red); font-weight: 600; }
        .back-btn { color: var(--red); text-decoration: none; font-size: 0.8rem; transition: color 0.3s; }
        .back-btn:hover { color: var(--red-dark); }

        .messages-area { background: white; border: 1px solid var(--border); border-radius: 0.75rem; height: 450px; overflow-y: auto; padding: 1.5rem; margin-bottom: 1.5rem; }
        .message { margin-bottom: 1.5rem; display: flex; }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        .message-bubble { max-width: 70%; padding: 0.75rem 1rem; border-radius: 1rem; position: relative; }
        .message.sent .message-bubble { background: var(--red); color: white; border-bottom-right-radius: 0.25rem; }
        .message.received .message-bubble { background: #f0f0f0; color: var(--dark); border-bottom-left-radius: 0.25rem; }
        .message-time { font-size: 0.65rem; margin-top: 0.25rem; opacity: 0.7; }
        .message.sent .message-time { text-align: right; }

        .message-input-area { background: white; border: 1px solid var(--border); border-radius: 0.75rem; padding: 1rem; display: flex; gap: 1rem; }
        .message-input-area textarea { flex: 1; padding: 0.75rem; border: 1px solid var(--border); border-radius: 0.5rem; font-family: inherit; resize: none; font-size: 0.85rem; transition: border-color 0.3s; }
        .message-input-area textarea:focus { outline: none; border-color: var(--red); }
        .send-btn { background: var(--dark); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 2rem; cursor: pointer; font-weight: 600; transition: background 0.3s; }
        .send-btn:hover { background: var(--red); }

        .footer { background: var(--dark); color: white; padding: 2rem; margin-top: 4rem; text-align: center; }
        .footer-links { display: flex; justify-content: center; gap: 2rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .footer-links a { color: white; text-decoration: none; font-size: 0.7rem; letter-spacing: 1px; }
        .footer-links a:hover { color: var(--red); }
        .footer p { font-size: 0.7rem; color: #888; }

        @media (max-width: 768px) {
            .nav-container { flex-direction: column; gap: 1rem; }
            .message-bubble { max-width: 85%; }
            .chat-header { flex-direction: column; text-align: center; gap: 1rem; }
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
                    <?php if($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="../logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="chat-header">
            <div class="chat-user">
                <h2>Conversation with <?php echo htmlspecialchars($other_user['full_name']); ?></h2>
                <p>
                    <span class="user-role"><?php echo strtoupper($other_user['role']); ?></span>
                    <?php if($product): ?>
                        • Regarding: <?php echo htmlspecialchars($product['title']); ?>
                    <?php endif; ?>
                </p>
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
    </script>
</body>
</html>