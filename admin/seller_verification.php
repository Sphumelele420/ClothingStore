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

// Handle verification actions
if(isset($_GET['verify'])){
    $id = (int)$_GET['verify'];
    mysqli_query($conn, "UPDATE tblUser SET verification_status='verified' WHERE user_id=$id");
    header("Location: seller_verification.php?msg=Seller approved successfully");
    exit();
}

if(isset($_GET['reject'])){
    $id = (int)$_GET['reject'];
    mysqli_query($conn, "UPDATE tblUser SET verification_status='rejected' WHERE user_id=$id");
    header("Location: seller_verification.php?msg=Seller application rejected");
    exit();
}

// Get pending seller applications (only sellers pending verification)
$pending = mysqli_query($conn, "SELECT * FROM tblUser WHERE role='seller' AND verification_status='pending' ORDER BY created_at DESC");

// Get statistics
$total_pending = mysqli_num_rows($pending);
$total_verified = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM tblUser WHERE role='seller' AND verification_status='verified'"));

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
    <title>Seller Verification - Pastimes Admin</title>
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
            display: block;
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
        
        .page-header {
            margin-bottom: 2rem;
            border-left: 4px solid var(--red);
            padding-left: 20px;
        }
        
        .page-header h1 { 
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .page-header p {
            color: var(--text-muted);
        }
        
        /* Stats Banner */
        .stats-banner {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            text-align: center;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--red);
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-top: 0.5rem;
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
        
        /* Queue Header */
        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }
        
        .queue-header h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        
        /* Seller Cards Grid */
        .queue-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        
        .queue-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .queue-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .queue-card-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid var(--border);
            background: #fafafa;
        }
        
        .queue-initials {
            width: 50px;
            height: 50px;
            background: var(--red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
        }
        
        .queue-name h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .identity-status {
            font-size: 0.7rem;
            color: var(--warning);
            font-weight: 500;
        }
        
        .queue-details {
            display: none;
            padding: 1.5rem;
            background: white;
        }
        
        .queue-card.active .queue-details {
            display: block;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
        }
        
        .info-label {
            width: 130px;
            font-weight: 600;
            color: var(--text-muted);
        }
        
        .info-value {
            flex: 1;
            color: var(--dark);
        }
        
        .store-description {
            background: #f8f7f2;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            font-style: italic;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .verification-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: #fff3e0;
            color: var(--warning);
            border-radius: 0.25rem;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .card-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        
        .btn-verify {
            background: var(--success);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
        
        .btn-verify:hover {
            background: #1a5d4a;
            transform: translateY(-1px);
        }
        
        .btn-reject {
            background: var(--red);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
        
        .btn-reject:hover {
            background: var(--red-dark);
            transform: translateY(-1px);
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
        
        .empty-state .success-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 900px) {
            .queue-grid {
                grid-template-columns: 1fr;
            }
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            .notification-dropdown {
                width: 300px;
                right: -50px;
            }
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
                <a href="seller_verification.php" class="active">Verifications</a>
                
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
            <h1>Seller Verification Queue</h1>
            <p>Review and authorize new artisans entering the Digital Atelier ecosystem.</p>
        </div>
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        
        <!-- Statistics Banner -->
        <div class="stats-banner">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_pending; ?></div>
                <div class="stat-label">Pending Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_verified; ?></div>
                <div class="stat-label">Verified Sellers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_pending + $total_verified; ?></div>
                <div class="stat-label">Total Sellers</div>
            </div>
        </div>
        
        <div class="queue-header">
            <h3>AWAITING REVIEW (<?php echo $total_pending; ?>)</h3>
        </div>
        
        <?php if($total_pending > 0): ?>
            <div class="queue-grid">
                <?php while($user = mysqli_fetch_assoc($pending)): ?>
                <div class="queue-card" onclick="toggleDetails(this)">
                    <div class="queue-card-header">
                        <div class="queue-initials">
                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                        </div>
                        <div class="queue-name">
                            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                            <span class="identity-status">IDENTITY STATUS: PENDING</span>
                        </div>
                    </div>
                    
                    <div class="queue-details">
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Username:</div>
                            <div class="info-value">@<?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Member Since:</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Application Type:</div>
                            <div class="info-value">Artisan Seller</div>
                        </div>
                        
                        <div class="store-description">
                            "Specializing in curated vintage pieces with heritage value. Each item is authenticated before listing. Committed to sustainable fashion and preserving craftsmanship."
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Identity Verification:</div>
                            <div class="info-value">
                                <span class="verification-badge">✓ Clear Scan</span>
                                <span class="verification-badge">✓ MRZ Valid</span>
                                <span style="color: var(--warning);">⚠️ Manual Review Required</span>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <a href="?verify=<?php echo $user['user_id']; ?>" class="btn-verify" onclick="return confirm('Approve this seller? They will be able to list items.')">✓ Approve & Verify</a>
                            <a href="?reject=<?php echo $user['user_id']; ?>" class="btn-reject" onclick="return confirm('Reject this seller application?')">✗ Reject Application</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="success-icon">✨</div>
                <p>All caught up! No pending seller applications.</p>
                <p style="font-size: 0.8rem;">New artisan applications will appear here when sellers register.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function toggleDetails(card) {
        // Close other open cards
        document.querySelectorAll('.queue-card.active').forEach(openCard => {
            if (openCard !== card) {
                openCard.classList.remove('active');
            }
        });
        // Toggle current card
        card.classList.toggle('active');
    }
    
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