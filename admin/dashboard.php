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

// Check if user is logged in AND is admin
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}

if($_SESSION['role'] !== 'admin'){
    if($_SESSION['role'] == 'seller'){
        header("Location: ../seller/my_atelier.php");
    } else {
        header("Location: ../marketplace.php");
    }
    exit();
}

// Get statistics
$sales_result = mysqli_query($conn, "SELECT COALESCE(SUM(total_price), 0) as total FROM tblAorder");
$sales = mysqli_fetch_assoc($sales_result);

$pending_users = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblUser WHERE verification_status='pending'");
$pending_count = mysqli_fetch_assoc($pending_users);

$pending_items = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblClothes WHERE status='pending'");
$pending_items_count = mysqli_fetch_assoc($pending_items);

$total_listings = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblClothes WHERE status='approved'");
$listings = mysqli_fetch_assoc($total_listings);

$total_users = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblUser");
$users = mysqli_fetch_assoc($total_users);

// Get pending seller count for the badge
$pending_sellers = mysqli_query($conn, "SELECT COUNT(*) as count FROM tblUser WHERE role='seller' AND verification_status='pending'");
$pending_sellers_count = mysqli_fetch_assoc($pending_sellers);

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
    <title>Admin Dashboard - Pastimes Digital Atelier</title>
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

        /* Admin Navbar Overrides */
        .admin-nav { background: var(--dark); color: white; padding: 1rem 0; border-bottom: 2px solid var(--red); }
        .admin-welcome { font-size: 0.8rem; letter-spacing: 1px; color: var(--red); margin-right: 20px; }

        .admin-container { max-width: 1300px; margin: 40px auto; padding: 0 30px; }
        
        .dashboard-header { margin-bottom: 40px; border-left: 4px solid var(--red); padding-left: 20px; }
        .dashboard-header h1 { font-family: 'Cormorant Garamond', serif; font-size: 2.5rem; text-transform: uppercase; letter-spacing: -1px; margin: 0; }
        .dashboard-subtitle { color: var(--text-muted); font-size: 0.95rem; max-width: 600px; margin-top: 10px; }

        /* Stats Grid */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px; 
        }
        
        .stat-card { 
            background: white; 
            padding: 25px; 
            border: 1px solid var(--border);
            transition: transform 0.2s;
        }
        
        .stat-card:first-child { background: var(--dark); color: white; border: none; }
        .stat-card:first-child .stat-value { color: var(--red); }

        .stat-card h3 { font-size: 0.65rem; letter-spacing: 2px; color: var(--text-muted); margin-bottom: 15px; }
        .stat-card .stat-value { font-size: 1.8rem; font-weight: 300; margin-bottom: 10px; }
        .stat-change { font-size: 0.75rem; font-weight: bold; }
        .positive { color: #27ae60; }

        /* Main Dashboard Content */
        .admin-grid { 
            display: grid; 
            grid-template-columns: 1.5fr 1fr; 
            gap: 30px; 
        }

        .admin-card { background: white; padding: 30px; border: 1px solid var(--border); }
        .admin-card h2 { font-size: 1.2rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }

        /* Activity List */
        .activity-item { padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .activity-item:last-child { border-bottom: none; }
        .activity-title { font-weight: bold; font-size: 0.9rem; margin-bottom: 5px; }
        .activity-desc { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; line-height: 1.4; }
        .activity-time { font-size: 0.65rem; color: var(--red); letter-spacing: 1px; }

        /* Verification Items */
        .verification-item { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            padding: 15px; 
            background: #fafafa; 
            margin-bottom: 10px;
            border-left: 2px solid transparent;
            transition: 0.3s;
        }
        .verification-item:hover { background: #f0f0f0; border-left-color: var(--red); }
        .verification-icon { font-size: 1.2rem; background: white; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); }
        .verification-name { font-weight: bold; font-size: 0.9rem; margin: 0; }
        .verification-tier { font-size: 0.75rem; color: var(--text-muted); }
        
        .btn-small { 
            margin-left: auto; 
            font-size: 0.7rem; 
            padding: 8px 15px; 
            border: 1px solid var(--dark); 
            text-decoration: none; 
            color: var(--dark);
            text-transform: uppercase;
            font-weight: bold;
            background: white;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .btn-small:hover {
            background: var(--red);
            border-color: var(--red);
            color: white;
        }

        .admin-actions .btn-primary {
            background: var(--dark);
            color: white;
            padding: 15px 25px;
            text-decoration: none;
            font-size: 0.8rem;
            letter-spacing: 1px;
            font-weight: bold;
            display: inline-block;
            text-align: center;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .admin-actions .btn-primary:hover {
            background: var(--red);
        }
        
        .btn-logout {
            background: #c44536;
            color: white !important;
            padding: 0.5rem 1.2rem;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: 15px;
        }

        .btn-logout:hover {
            background: #a33a2c;
            transform: translateY(-1px);
        }
        
        /* Notification badge */
        .badge {
            background: var(--red);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.65rem;
            font-weight: bold;
            margin-left: 5px;
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

        @media (max-width: 900px) {
            .admin-grid { grid-template-columns: 1fr; }
            .notification-dropdown { width: 300px; right: -50px; }
        }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="nav-container" style="display: flex; justify-content: space-between; align-items: center; max-width: 1300px; margin: 0 auto; padding: 0 30px;">
            <div class="logo">
                <a href="dashboard.php" style="text-decoration: none; color: white;">
                    <h1 style="margin:0; font-size: 1.5rem;">PASTIMES</h1>
                    <span style="font-size: 0.6rem; letter-spacing: 3px; color: var(--red);">ADMIN CONSOLE</span>
                </a>
            </div>
            <div class="nav-links" style="display: flex; align-items: center; gap: 1.5rem;">
                <a href="dashboard.php" style="color: white; text-decoration: none; font-size: 0.8rem;">Dashboard</a>
                <a href="manage_users.php" style="color: white; text-decoration: none; font-size: 0.8rem;">Users</a>
                <a href="approve_items.php" style="color: white; text-decoration: none; font-size: 0.8rem;">Listings</a>
                <a href="seller_verification.php" style="color: var(--red); text-decoration: none; font-size: 0.8rem; font-weight: bold;">
                    Seller Verifications
                    <?php if($pending_sellers_count['count'] > 0): ?>
                        <span class="badge"><?php echo $pending_sellers_count['count']; ?></span>
                    <?php endif; ?>
                </a>
                
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
                
                <span class="admin-welcome"><?php echo strtoupper(htmlspecialchars($_SESSION['full_name'])); ?></span>
                <a href="../logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <header class="dashboard-header">
            <h1>Heritage Dashboard</h1>
            <p class="dashboard-subtitle">A curated overview of the Digital Atelier's performance, integrity metrics, and pending artisanal verifications.</p>
        </header>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>CONSOLIDATED SALES VOLUME</h3>
                <div class="stat-value">R <?php echo number_format($sales['total'] ?? 0, 2); ?></div>
                <span class="stat-change positive">↑ 12.4%</span>
                <p style="font-size: 0.65rem; margin-top: 10px; opacity: 0.6;">Current Quarterly Cycle Performance</p>
            </div>
            
            <div class="stat-card">
                <h3>TOTAL USERS</h3>
                <div class="stat-value"><?php echo $users['count']; ?></div>
                <span class="stat-change" style="color: var(--red);">+<?php echo $pending_count['count']; ?> PENDING REVIEW</span>
            </div>
            
            <div class="stat-card">
                <h3>ACTIVE LISTINGS</h3>
                <div class="stat-value"><?php echo $listings['count']; ?></div>
                <span class="stat-change" style="color: #666;">LIVE IN GALLERY</span>
            </div>
            
            <div class="stat-card">
                <h3>SYSTEM STATUS</h3>
                <div class="stat-value" style="font-size: 1.2rem; color: #27ae60;">● OPERATIONAL</div>
                <span class="stat-change" style="color: #666;">LATENCY: 124ms</span>
            </div>
        </div>
        
        <div class="admin-grid">
            <div class="admin-card">
                <h2>Recent Activity Ledger</h2>
                <div class="activity-list">
                    <div class="activity-item">
                        <p class="activity-title">Seller Approved: Vintage Vault ZA</p>
                        <p class="activity-desc">Verification processed. Identity confirmed via government portal API.</p>
                        <span class="activity-time">24 MINUTES AGO</span>
                    </div>
                    <div class="activity-item">
                        <p class="activity-title">Listing Flagged: Hermès Birkin 35</p>
                        <p class="activity-desc">Suspected authenticity discrepancy. Manual review required by Senior Appraiser.</p>
                        <span class="activity-time">1 HOUR AGO</span>
                    </div>
                    <div class="activity-item">
                        <p class="activity-title">Payout Batch Executed</p>
                        <p class="activity-desc">R 124,500 successfully transferred to 42 seller accounts.</p>
                        <span class="activity-time">3 HOURS AGO</span>
                    </div>
                </div>
            </div>
            
            <div class="admin-card">
                <h2>Verification Queue</h2>
                <div class="verification-list">
                    <div class="verification-item">
                        <div class="verification-icon">👥</div>
                        <div>
                            <p class="verification-name">User Accounts</p>
                            <span class="verification-tier"><?php echo $pending_count['count']; ?> need verification</span>
                        </div>
                        <a href="manage_users.php" class="btn-small">Review</a>
                    </div>
                    
                    <div class="verification-item">
                        <div class="verification-icon">👨‍🎨</div>
                        <div>
                            <p class="verification-name">Seller Applications</p>
                            <span class="verification-tier"><?php echo $pending_sellers_count['count']; ?> artisans pending</span>
                        </div>
                        <a href="seller_verification.php" class="btn-small">Review</a>
                    </div>
                    
                    <div class="verification-item">
                        <div class="verification-icon">📦</div>
                        <div>
                            <p class="verification-name">Product Submissions</p>
                            <span class="verification-tier"><?php echo $pending_items_count['count']; ?> items pending approval</span>
                        </div>
                        <a href="approve_items.php" class="btn-small">Inspect</a>
                    </div>
                </div>
                
                <div class="admin-actions" style="margin-top: 30px; display: flex; flex-direction: column; gap: 10px;">
                    <a href="manage_users.php" class="btn-primary" style="text-align: center;">MANAGE ALL USERS</a>
                    <a href="seller_verification.php" class="btn-primary" style="text-align: center; background: var(--red); color: white;">SELLER VERIFICATION QUEUE</a>
                    <a href="approve_items.php" class="btn-primary" style="text-align: center; background: white; color: black; border: 1px solid black;">LISTING MANAGEMENT</a>
                </div>
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