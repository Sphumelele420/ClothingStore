<?php
session_start();
include_once "../DBConn.php";

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

// Check admin access
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

// Handle user actions
if(isset($_GET['action']) && isset($_GET['id'])){
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if($action == 'verify'){
        mysqli_query($conn, "UPDATE tblUser SET verification_status='verified' WHERE user_id=$id");
        header("Location: manage_users.php?msg=User verified");
        exit();
    } elseif($action == 'delete'){
        mysqli_query($conn, "DELETE FROM tblUser WHERE user_id=$id");
        header("Location: manage_users.php?msg=User deleted");
        exit();
    } elseif($action == 'reject'){
        mysqli_query($conn, "UPDATE tblUser SET verification_status='rejected' WHERE user_id=$id");
        header("Location: manage_users.php?msg=User rejected");
        exit();
    }
}

// Get all users
$users_result = mysqli_query($conn, "SELECT * FROM tblUser ORDER BY created_at DESC");

// Get unread counts for admin
$unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
$unread_notifications = getUnreadNotificationCount($conn, $_SESSION['user_id']);
$total_unread = $unread_messages + $unread_notifications;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #121212;
            --border: #e8e4db;
            --text-muted: #888;
            --success: #2e7d64;
            --warning: #e8a735;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            background: #f8f7f2; 
            font-family: 'Inter', sans-serif;
            color: var(--dark);
        }
        
        /* Admin Navbar */
        .admin-nav { 
            background: var(--dark); 
            color: white; 
            padding: 1rem 2rem; 
            border-bottom: 2px solid var(--red);
        }
        
        .nav-container { 
            max-width: 1400px; 
            margin: 0 auto; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .logo a { 
            text-decoration: none; 
            color: white; 
        }
        
        .logo h1 { 
            font-family: 'Cormorant Garamond', serif;
            margin: 0; 
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .logo span { 
            color: var(--red); 
            font-size: 0.7rem; 
            letter-spacing: 3px;
        }
        
        .nav-links { 
            display: flex; 
            align-items: center; 
            gap: 2rem;
        }
        
        .nav-links a { 
            color: white; 
            text-decoration: none; 
            font-size: 0.8rem;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover { 
            color: var(--red); 
        }
        
        .nav-links a.active { 
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
        
        /* RED LOGOUT BUTTON */
        .btn-logout {
            background: var(--red);
            color: white !important;
            font-size: 0.7rem;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 0.5rem 1.2rem;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: 15px;
        }
        
        .btn-logout:hover {
            background: var(--red-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(196, 69, 54, 0.3);
            color: white !important;
        }
        
        /* Admin Container */
        .admin-container { 
            max-width: 1400px; 
            margin: 2rem auto; 
            padding: 0 2rem; 
        }
        
        .admin-container h1 { 
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        /* Alert Messages */
        .alert { 
            padding: 1rem; 
            border-radius: 0.5rem; 
            margin-bottom: 1.5rem; 
        }
        
        .alert-success { 
            background: #e8f5e9; 
            color: var(--success); 
            border-left: 4px solid var(--success); 
        }
        
        /* Data Table */
        .users-table-container {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        .data-table th, 
        .data-table td { 
            padding: 1rem; 
            text-align: left; 
            border-bottom: 1px solid var(--border); 
        }
        
        .data-table th { 
            background: #fafafa; 
            font-size: 0.7rem; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            font-weight: 600;
            color: var(--text-muted);
        }
        
        .data-table tr:hover {
            background: #f9f9f9;
        }
        
        /* Badges */
        .role-badge, .status-badge { 
            display: inline-block; 
            padding: 0.25rem 0.75rem; 
            border-radius: 2rem; 
            font-size: 0.7rem; 
            font-weight: 600;
        }
        
        .role-badge.admin { background: var(--dark); color: white; }
        .role-badge.seller { background: var(--red); color: white; }
        .role-badge.buyer { background: #e0e0e0; color: #333; }
        
        .status-badge.verified { background: #e8f5e9; color: var(--success); }
        .status-badge.pending { background: #fff3e0; color: var(--warning); }
        .status-badge.rejected { background: #ffebee; color: var(--red); }
        
        /* Action Buttons */
        .action-buttons { 
            display: flex; 
            gap: 0.5rem; 
            flex-wrap: wrap;
        }
        
        .btn-verify, .btn-reject, .btn-edit, .btn-delete { 
            padding: 0.25rem 0.75rem; 
            border-radius: 0.25rem; 
            text-decoration: none; 
            font-size: 0.7rem; 
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-verify { background: var(--success); color: white; }
        .btn-verify:hover { background: #1a5d4a; }
        
        .btn-reject { background: var(--warning); color: white; }
        .btn-reject:hover { background: #d4951e; }
        
        .btn-edit { background: var(--red); color: white; }
        .btn-edit:hover { background: var(--red-dark); }
        
        .btn-delete { background: #c0392b; color: white; }
        .btn-delete:hover { background: #a33a2c; }
        
        @media (max-width: 900px) {
            .nav-container { flex-direction: column; gap: 1rem; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .data-table { font-size: 0.8rem; }
            .data-table th, .data-table td { padding: 0.5rem; }
            .action-buttons { flex-direction: column; }
            .notification-dropdown { width: 300px; right: -50px; }
        }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="nav-container">
            <div class="logo">
                <a href="dashboard.php" style="text-decoration: none; color: white;">
                    <h1>PASTIMES</h1>
                    <span>ADMIN CONSOLE</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_users.php" class="active">Users</a>
                <a href="seller_verification.php">Verification</a>
                <a href="approve_items.php">Listings</a>
                
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
                            <a href="../messages.php">View all messages →</a>
                        </div>
                    </div>
                </div>
                
                <a href="../logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <h1>User Management</h1>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Review, verify, and manage all platform users</p>
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        
        <div class="users-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                    <tr style="<?php echo $user['verification_status'] == 'pending' ? 'background: #fffbf0;' : ''; ?>">
                        <td><?php echo $user['user_id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="role-badge <?php echo $user['role']; ?>">
                                <?php echo strtoupper($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $user['verification_status']; ?>">
                                <?php echo strtoupper($user['verification_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td class="action-buttons">
                            <?php if($user['verification_status'] == 'pending'): ?>
                                <a href="?action=verify&id=<?php echo $user['user_id']; ?>" class="btn-verify" onclick="return confirm('Verify this user?')">Verify</a>
                                <a href="?action=reject&id=<?php echo $user['user_id']; ?>" class="btn-reject" onclick="return confirm('Reject this user?')">Reject</a>
                            <?php endif; ?>
                            <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn-edit">Edit</a>
                            <a href="?action=delete&id=<?php echo $user['user_id']; ?>" class="btn-delete" onclick="return confirm('Delete this user permanently?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if(mysqli_num_rows($users_result) == 0): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No users found in the system
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

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
        fetch('../get_notifications.php')
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
        fetch('../get_notification_count.php')
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