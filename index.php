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

// Get 4 most recent approved items for display
$recent_items = mysqli_query($conn, "SELECT * FROM tblClothes WHERE status='approved' ORDER BY created_at DESC LIMIT 4");

// Get unread counts for logged in users
$unread_messages = 0;
$unread_notifications = 0;
$total_unread = 0;
if(isset($_SESSION['user_id'])){
    $unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
    $unread_notifications = getUnreadNotificationCount($conn, $_SESSION['user_id']);
    $total_unread = $unread_messages + $unread_notifications;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pastimes - Digital Atelier</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --bg: #f5f0e8;
            --border: #e8e4db;
            --text-muted: #888;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--dark);
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
            font-size: 0.85rem;
            font-weight: 500;
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

        /* Hero Section */
        .hero {
            min-height: 80vh;
            background: linear-gradient(135deg, #2C2C2C 0%, #1A1A1A 100%);
            color: white;
            display: flex;
            align-items: center;
            position: relative;
        }

        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        .hero-badge {
            font-size: 0.7rem;
            letter-spacing: 0.2em;
            color: var(--red);
            text-transform: uppercase;
        }

        .hero h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3.5rem;
            margin: 1.5rem 0 1rem;
            line-height: 1.2;
            max-width: 600px;
        }

        .hero p {
            font-size: 1.1rem;
            color: #B0B0B0;
            max-width: 500px;
            margin-bottom: 2rem;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn-primary {
            background: var(--red);
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid white;
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
        }

        /* New Arrivals Section */
        .arrivals-section {
            max-width: 1400px;
            margin: 4rem auto;
            padding: 0 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }

        .section-header h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .section-header a {
            color: var(--red);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.3s;
        }

        .section-header a:hover {
            color: var(--red-dark);
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }

        .heritage-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s;
        }

        .heritage-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }

        .card-image {
            height: 280px;
            background: linear-gradient(135deg, #E8E4DC 0%, #D4CEC4 100%);
            position: relative;
            overflow: hidden;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .sustainability-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.9);
            padding: 0.25rem 0.5rem;
            border-radius: 2rem;
            font-size: 0.6rem;
            font-weight: bold;
            border: 1px solid var(--red);
        }

        .card-details {
            padding: 1rem;
            text-align: center;
        }

        .card-details h4 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .card-details .brand {
            font-size: 0.7rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .card-details .price {
            font-size: 1rem;
            font-weight: 700;
            color: var(--red);
            margin-bottom: 0.75rem;
        }

        .btn-message {
            display: inline-block;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.7rem;
            color: var(--dark);
            transition: all 0.2s;
        }

        .btn-message:hover {
            border-color: var(--red);
            color: var(--red);
        }

        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 3rem 2rem;
            margin-top: 4rem;
            text-align: center;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
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
            .hero h1 { font-size: 2rem; }
            .hero-buttons { flex-direction: column; align-items: center; }
            .gallery-grid { grid-template-columns: 1fr; }
            .nav-container { flex-direction: column; gap: 1rem; }
            .notification-dropdown { width: 300px; right: -50px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <h1>Pastimes</h1>
            </div>
            <div class="nav-links">
                <a href="index.php" class="active">Home</a>
                <a href="marketplace.php">Gallery</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'seller'): ?>
                        <a href="seller/my_atelier.php">My Atelier</a>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <a href="admin/dashboard.php">Dashboard</a>
                    <?php else: ?>
                        <a href="cart.php">Cart</a>
                    <?php endif; ?>
                     <!-- Contact Admin Link -->
                    <a href="contact_admin.php" class="nav-contact">📧 Contact Admin</a>
                    
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
                    
                    <a href="logout.php">Logout</a>
                    <span style="color: var(--red);">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <?php else: ?>
                    <a href="login.php">Sign In</a>
                    <a href="register.php" class="nav-cta">Join Pastimes</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <span class="hero-badge">THE DIGITAL ATELIER</span>
            <h1>Second-hand items with a first-class soul.</h1>
            <p>Discover rare vintage treasures authenticated by heritage experts. Every piece has a story — start writing yours today.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn-primary">Create an account</a>
                <a href="marketplace.php" class="btn-secondary">Explore Gallery</a>
            </div>
        </div>
    </section>

    <div class="arrivals-section">
        <div class="section-header">
            <h2>New Arrivals</h2>
            <a href="marketplace.php">VIEW ALL →</a>
        </div>
        <div class="gallery-grid">
            <?php if(mysqli_num_rows($recent_items) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($recent_items)): ?>
                <a href="product_details.php?id=<?php echo $row['clothing_id']; ?>" class="heritage-card">
                    <div class="card-image">
                        <?php if(!empty($row['image_url']) && file_exists($row['image_url'])): ?>
                            <img src="<?php echo $row['image_url']; ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%;">📷</div>
                        <?php endif; ?>
                        <span class="sustainability-badge">SCORE <?php echo $row['sustainability_score']; ?></span>
                    </div>
                    <div class="card-details">
                        <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                        <p class="brand"><?php echo htmlspecialchars($row['brand']); ?> • ARCHIVE</p>
                        <p class="price">R <?php echo number_format($row['price'], 0); ?></p>
                        <span class="btn-message">VIEW DETAILS →</span>
                    </div>
                </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                    <p>No heritage pieces available yet.</p>
                </div>
            <?php endif; ?>
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
    if(<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
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
    }
    </script>

</body>
</html>