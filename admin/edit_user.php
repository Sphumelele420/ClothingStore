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

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($user_id == 0){
    header("Location: manage_users.php");
    exit();
}

// Get user details
$user_result = mysqli_query($conn, "SELECT * FROM tblUser WHERE user_id = $user_id");
$user = mysqli_fetch_assoc($user_result);

if(!$user){
    header("Location: manage_users.php?msg=User not found");
    exit();
}

$success = '';
$error = '';

// Handle form submission
if(isset($_POST['update_user'])){
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $verification_status = mysqli_real_escape_string($conn, $_POST['verification_status']);
    
    // Check if email already exists for another user
    $check_email = mysqli_query($conn, "SELECT user_id FROM tblUser WHERE email='$email' AND user_id != $user_id");
    if(mysqli_num_rows($check_email) > 0){
        $error = "Email already exists for another user.";
    } else {
        $update_sql = "UPDATE tblUser SET 
                       full_name = '$full_name',
                       username = '$username',
                       email = '$email',
                       role = '$role',
                       verification_status = '$verification_status'
                       WHERE user_id = $user_id";
        
        if(mysqli_query($conn, $update_sql)){
            $success = "User details updated successfully!";
            // Refresh user data
            $user_result = mysqli_query($conn, "SELECT * FROM tblUser WHERE user_id = $user_id");
            $user = mysqli_fetch_assoc($user_result);
        } else {
            $error = "Error updating user: " . mysqli_error($conn);
        }
    }
}

// Handle password reset
if(isset($_POST['reset_password'])){
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if(strlen($new_password) < 6){
        $error = "Password must be at least 6 characters.";
    } elseif($new_password != $confirm_password){
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_pass = mysqli_query($conn, "UPDATE tblUser SET password = '$hashed_password' WHERE user_id = $user_id");
        
        if($update_pass){
            $success = "Password reset successfully!";
        } else {
            $error = "Error resetting password.";
        }
    }
}

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
    <title>Edit User - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --border: #e8e4db;
            --text-muted: #888;
            --success: #2e7d64;
            --pending: #e8a735;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f8f7f2; font-family: 'Inter', sans-serif; }
        
        /* Admin Navbar */
        .admin-nav { background: var(--dark); padding: 1rem 2rem; color: white; border-bottom: 2px solid var(--red); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo a { text-decoration: none; color: white; }
        .logo h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; margin: 0; letter-spacing: -0.5px; }
        .logo span { color: var(--red); font-size: 0.7rem; letter-spacing: 3px; }
        .nav-links a { color: white; text-decoration: none; margin-left: 2rem; font-size: 0.8rem; transition: color 0.3s; }
        .nav-links a:hover { color: var(--red); }
        
        /* Notification Icon Styles */
        .notification-icon {
            position: relative;
            display: inline-block;
            cursor: pointer;
            margin-left: 2rem;
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
        
        /* Logout Button */
        .btn-logout {
            background: var(--red);
            color: white !important;
            padding: 0.5rem 1.2rem;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: 15px;
        }

        .btn-logout:hover {
            background: var(--red-dark);
            transform: translateY(-1px);
        }
        
        /* Admin Container */
        .admin-container { max-width: 1000px; margin: 2rem auto; padding: 0 2rem; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; font-weight: 600; }
        .page-header p { color: var(--text-muted); margin-top: 0.25rem; }
        .back-btn { color: var(--red); text-decoration: none; }
        .back-btn:hover { color: var(--red-dark); }
        
        .card { background: white; border-radius: 0.75rem; padding: 2rem; margin-bottom: 2rem; border: 1px solid var(--border); }
        .card h2 { font-family: 'Cormorant Garamond', serif; font-size: 1.2rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--red); display: inline-block; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--text-muted); letter-spacing: 1px; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 0.5rem; font-size: 0.9rem; font-family: 'Inter', sans-serif; transition: all 0.3s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--red); box-shadow: 0 0 0 3px rgba(196, 69, 54, 0.1); }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        
        .btn-primary { background: var(--dark); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 2rem; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary:hover { background: var(--red); transform: translateY(-1px); }
        .btn-secondary { background: #e0e0e0; color: #333; padding: 0.75rem 1.5rem; border: none; border-radius: 2rem; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-secondary:hover { background: #ccc; }
        .btn-danger { background: var(--red); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 2rem; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-danger:hover { background: var(--red-dark); transform: translateY(-1px); }
        
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.85rem; }
        .alert-success { background: #e8f5e9; color: var(--success); border-left: 4px solid var(--success); }
        .alert-error { background: #ffebee; color: var(--red); border-left: 4px solid var(--red); }
        
        .info-box { background: #f5f5f5; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .info-box p { margin: 0.25rem 0; font-size: 0.85rem; }
        
        .button-group { display: flex; gap: 1rem; margin-top: 1rem; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .button-group { flex-direction: column; }
            .nav-container { flex-direction: column; gap: 1rem; }
            .nav-links { display: flex; flex-wrap: wrap; justify-content: center; margin-top: 0.5rem; }
            .notification-dropdown { width: 300px; right: -50px; }
        }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="nav-container">
            <div class="logo">
                <a href="dashboard.php" style="text-decoration: none; color: white;">
                    <h1>Pastimes</h1>
                    <span>Admin Console</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_users.php">Users</a>
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
        <div class="page-header">
            <div>
                <h1>Edit User</h1>
                <p>Editing user: <?php echo htmlspecialchars($user['full_name']); ?></p>
            </div>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Edit User Form -->
        <div class="card">
            <h2>User Information</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="buyer" <?php echo $user['role'] == 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                            <option value="seller" <?php echo $user['role'] == 'seller' ? 'selected' : ''; ?>>Seller</option>
                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Verification Status</label>
                    <select name="verification_status">
                        <option value="pending" <?php echo $user['verification_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="verified" <?php echo $user['verification_status'] == 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo $user['verification_status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="update_user" class="btn-primary">Save Changes</button>
                    <a href="manage_users.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        
        <!-- Reset Password Section -->
        <div class="card">
            <h2>Reset Password</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" name="reset_password" class="btn-danger">Reset Password</button>
                </div>
            </form>
        </div>
        
        <!-- User Information Card -->
        <div class="card">
            <h2>Account Information</h2>
            <div class="info-box">
                <p><strong>User ID:</strong> <?php echo $user['user_id']; ?></p>
                <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                <p><strong>Account Status:</strong> 
                    <span style="color: <?php 
                        echo $user['verification_status'] == 'verified' ? '#2e7d64' : 
                            ($user['verification_status'] == 'pending' ? '#e8a735' : '#c44536'); 
                    ?>;">
                        <?php echo strtoupper($user['verification_status']); ?>
                    </span>
                </p>
            </div>
            
            <div class="button-group">
                <a href="manage_users.php?action=delete&id=<?php echo $user['user_id']; ?>" class="btn-danger" style="text-decoration: none; display: inline-block;" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">Delete User Account</a>
            </div>
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
    fetch('get_notification.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('notificationList');
            if (!data.notifications || data.notifications.length === 0) {
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