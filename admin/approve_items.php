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

// Notification helper functions (embedded if not already defined)
if(!function_exists('createNotification')){
    function createNotification($conn, $user_id, $type, $title, $message, $link = null) {
        $link = $link ? "'$link'" : "NULL";
        $query = mysqli_query($conn, "INSERT INTO tblNotifications (user_id, type, title, message, link) 
                                       VALUES ($user_id, '$type', '$title', '$message', $link)");
        return $query;
    }
}

// Check if user is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}

// Handle item approval
if(isset($_GET['approve'])){
    $item_id = (int)$_GET['approve'];
    
    // Get seller info
    $item_query = mysqli_query($conn, "SELECT seller_id, title FROM tblClothes WHERE clothing_id = $item_id");
    $item = mysqli_fetch_assoc($item_query);
    
    mysqli_query($conn, "UPDATE tblClothes SET status='approved' WHERE clothing_id=$item_id");
    
    // Create notification for seller
    createNotification($conn, $item['seller_id'], 'approval', 
                      "Your item has been approved!", 
                      "Your listing '{$item['title']}' is now live in the gallery.",
                      "../product_details.php?id=$item_id");
    
    header("Location: approve_items.php?msg=Item approved");
    exit();
}

// Handle item rejection
if(isset($_GET['reject'])){
    $item_id = (int)$_GET['reject'];
    mysqli_query($conn, "UPDATE tblClothes SET status='rejected' WHERE clothing_id=$item_id");
    header("Location: approve_items.php?msg=Item rejected");
    exit();
}

// Handle item deletion
if(isset($_GET['delete'])){
    $item_id = (int)$_GET['delete'];
    
    // Also delete the image file if it exists
    $img_query = mysqli_query($conn, "SELECT image_url FROM tblClothes WHERE clothing_id=$item_id");
    $img = mysqli_fetch_assoc($img_query);
    if($img && !empty($img['image_url']) && file_exists("../" . $img['image_url'])){
        unlink("../" . $img['image_url']);
    }
    
    mysqli_query($conn, "DELETE FROM tblClothes WHERE clothing_id=$item_id");
    header("Location: approve_items.php?msg=Item deleted");
    exit();
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$where = "";
if($filter == 'pending') $where = "WHERE status='pending'";
if($filter == 'approved') $where = "WHERE status='approved'";
if($filter == 'rejected') $where = "WHERE status='rejected'";

$items_result = mysqli_query($conn, "SELECT c.*, u.full_name as seller_name, u.username as seller_username 
                                     FROM tblClothes c 
                                     JOIN tblUser u ON c.seller_id = u.user_id 
                                     $where 
                                     ORDER BY c.created_at DESC");

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
    <title>Approve Listings - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #121212;
            --border: #e8e4db;
            --success: #27ae60;
            --warning: #e67e22;
            --error: #c0392b;
            --text-muted: #888;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { background: #f8f7f2; font-family: 'Inter', sans-serif; }

        /* Admin Navbar */
        .admin-nav { background: var(--dark); color: white; padding: 1rem 2rem; border-bottom: 2px solid var(--red); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo a { text-decoration: none; color: white; }
        .logo h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; margin: 0; font-weight: 600; letter-spacing: -0.5px; }
        .logo span { font-size: 0.6rem; letter-spacing: 3px; color: var(--red); }
        .nav-links a { color: white; text-decoration: none; margin-left: 2rem; font-size: 0.8rem; transition: color 0.3s; }
        .nav-links a:hover { color: var(--red); }
        .nav-links a.active { color: var(--red); }

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

        /* Admin Container */
        .admin-container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; font-weight: 600; }
        .page-header p { color: var(--text-muted); margin-top: 0.5rem; }

        .filter-tabs { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        .filter-tab { padding: 0.5rem 1.5rem; text-decoration: none; color: #666; border-radius: 2rem; transition: 0.3s; }
        .filter-tab.active { background: var(--dark); color: white; }
        .filter-tab:hover:not(.active) { background: #eee; }

        .items-grid { display: grid; gap: 1.5rem; }
        .item-card { background: white; border-radius: 0.75rem; overflow: hidden; border: 1px solid var(--border); display: flex; transition: 0.3s; }
        .item-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

        .item-image {
            width: 200px;
            height: 200px;
            overflow: hidden;
            background: linear-gradient(135deg, #E8E4DC 0%, #D4CEC4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        
        .no-image {
            font-size: 2rem;
            color: #999;
        }
        
        .item-details { flex: 1; padding: 1.5rem; }
        .item-title { font-size: 1.2rem; font-weight: bold; margin-bottom: 0.25rem; }
        .item-meta { color: #666; font-size: 0.8rem; margin-bottom: 0.5rem; }
        .item-price { font-size: 1.3rem; font-weight: bold; color: var(--red); margin: 0.5rem 0; }
        .item-description { color: #555; font-size: 0.85rem; line-height: 1.4; margin: 0.5rem 0; }
        .seller-info { font-size: 0.75rem; color: var(--dark); background: #f5f5f5; display: inline-block; padding: 0.25rem 0.75rem; border-radius: 2rem; }

        .item-actions { margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .btn-approve { background: var(--success); color: white; padding: 0.5rem 1.5rem; text-decoration: none; border-radius: 2rem; font-size: 0.8rem; transition: all 0.2s; }
        .btn-approve:hover { background: #1a5d4a; transform: translateY(-1px); }
        .btn-reject { background: var(--warning); color: white; padding: 0.5rem 1.5rem; text-decoration: none; border-radius: 2rem; font-size: 0.8rem; transition: all 0.2s; }
        .btn-reject:hover { background: #d4951e; transform: translateY(-1px); }
        .btn-delete { background: var(--error); color: white; padding: 0.5rem 1.5rem; text-decoration: none; border-radius: 2rem; font-size: 0.8rem; transition: all 0.2s; }
        .btn-delete:hover { background: #a33a2c; transform: translateY(-1px); }

        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 2rem; font-size: 0.7rem; font-weight: bold; margin-left: 1rem; }
        .status-pending { background: #fff3e0; color: var(--warning); }
        .status-approved { background: #e8f5e9; color: var(--success); }
        .status-rejected { background: #ffebee; color: var(--error); }

        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
        .alert-success { background: #e8f5e9; color: var(--success); border-left: 4px solid var(--success); }

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

        @media (max-width: 768px) {
            .item-card { flex-direction: column; }
            .item-image { width: 100%; height: 200px; }
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
                <a href="manage_users.php">Users</a>
                <a href="seller_verification.php">Verification</a>
                <a href="approve_items.php" class="active">Listings</a>
                
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
                <h1>Listing Approval Queue</h1>
                <p>Review and approve heritage pieces submitted by sellers</p>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>

        <div class="filter-tabs">
            <a href="?filter=pending" class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">Pending Approval</a>
            <a href="?filter=approved" class="filter-tab <?php echo $filter == 'approved' ? 'active' : ''; ?>">Approved</a>
            <a href="?filter=rejected" class="filter-tab <?php echo $filter == 'rejected' ? 'active' : ''; ?>">Rejected</a>
        </div>

        <div class="items-grid">
            <?php while($item = mysqli_fetch_assoc($items_result)): ?>
            <div class="item-card">
                <div class="item-image">
                    <?php if(!empty($item['image_url']) && file_exists("../" . $item['image_url'])): ?>
                        <img src="../<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    <?php else: ?>
                        <div class="no-image">📷</div>
                    <?php endif; ?>
                </div>
                <div class="item-details">
                    <div>
                        <span class="item-title"><?php echo htmlspecialchars($item['title']); ?></span>
                        <span class="status-badge status-<?php echo $item['status']; ?>">
                            <?php echo strtoupper($item['status']); ?>
                        </span>
                    </div>
                    <div class="item-meta">
                        <?php echo htmlspecialchars($item['brand']); ?> • <?php echo $item['size']; ?> • <?php echo $item['condition_grade']; ?>
                        <?php if($item['year']): ?> • <?php echo $item['year']; ?><?php endif; ?>
                    </div>
                    <div class="item-price">R <?php echo number_format($item['price'], 2); ?></div>
                    <div class="item-description">
                        <?php echo nl2br(htmlspecialchars(substr($item['description'], 0, 200))); ?>
                        <?php if(strlen($item['description']) > 200) echo '...'; ?>
                    </div>
                    <div class="seller-info">
                        👤 Seller: <?php echo htmlspecialchars($item['seller_name']); ?> (@<?php echo $item['seller_username']; ?>)
                    </div>
                    
                    <?php if($item['status'] == 'pending'): ?>
                    <div class="item-actions">
                        <a href="?approve=<?php echo $item['clothing_id']; ?>" class="btn-approve" onclick="return confirm('Approve this listing? It will appear in the gallery.')">✓ Approve & Publish</a>
                        <a href="?reject=<?php echo $item['clothing_id']; ?>" class="btn-reject" onclick="return confirm('Reject this listing?')">✗ Reject</a>
                        <a href="?delete=<?php echo $item['clothing_id']; ?>" class="btn-delete" onclick="return confirm('Delete this listing permanently?')">🗑 Delete</a>
                    </div>
                    <?php elseif($item['status'] == 'approved'): ?>
                    <div class="item-actions">
                        <a href="?delete=<?php echo $item['clothing_id']; ?>" class="btn-delete" onclick="return confirm('Remove this listing?')">Remove from Gallery</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
            
            <?php if(mysqli_num_rows($items_result) == 0): ?>
            <div style="text-align: center; padding: 4rem; background: white; border-radius: 1rem;">
                <p>No <?php echo $filter; ?> items found.</p>
                <?php if($filter == 'pending'): ?>
                    <p style="color: #666; margin-top: 0.5rem;">All caught up! No items need approval.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
    fetch('get_notifications.php')
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