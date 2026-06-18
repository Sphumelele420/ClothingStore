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

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer'){
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

// Get cart items with image_url
$cart_items = mysqli_query($conn, "SELECT c.*, cl.clothing_id, cl.title, cl.price, cl.brand, cl.size, cl.condition_grade, cl.image_url, u.full_name as seller_name
                                   FROM tblCart c
                                   JOIN tblClothes cl ON c.clothing_id = cl.clothing_id
                                   JOIN tblUser u ON cl.seller_id = u.user_id
                                   WHERE c.buyer_id = $buyer_id");

$total = 0;
$item_count = 0;

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
    <title>Your Collection - Pastimes</title>
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
            margin: 0; 
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

        /* Cart Container */
        .cart-container {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 2rem;
        }

        .cart-header {
            margin-bottom: 3rem;
            border-left: 4px solid var(--red);
            padding-left: 1.5rem;
        }

        .cart-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .cart-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        /* Cart Items */
        .cart-items {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .cart-item {
            display: flex;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            transition: background 0.3s;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item:hover {
            background: #fafafa;
        }

        .item-image {
            width: 100px;
            height: 120px;
            background: linear-gradient(135deg, #E8E4DC 0%, #D4CEC4 100%);
            border-radius: 0.5rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .item-image .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            font-size: 2rem;
            color: var(--text-muted);
        }

        .item-details {
            flex: 1;
            padding-left: 1.5rem;
        }

        .item-details h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-brand {
            font-size: 0.8rem;
            color: var(--red);
            margin-bottom: 0.5rem;
        }

        .item-meta {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .item-meta span {
            background: #f5f5f5;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }

        .seller-name {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }

        .item-actions {
            text-align: right;
            flex-shrink: 0;
        }

        .item-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--red);
            display: block;
            margin-bottom: 0.5rem;
        }

        .btn-remove {
            color: #c0392b;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            transition: opacity 0.3s;
            display: inline-block;
        }

        .btn-remove:hover {
            text-decoration: underline;
            opacity: 0.7;
        }

        /* Order Summary */
        .order-summary {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .order-summary h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .summary-row.total {
            border-top: 1px solid var(--border);
            margin-top: 1rem;
            padding-top: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .summary-row.total span:first-child {
            font-weight: 600;
        }

        .summary-row.total span:last-child {
            color: var(--red);
            font-size: 1.2rem;
        }

        .btn-checkout {
            display: block;
            width: 100%;
            background: var(--dark);
            color: white;
            text-align: center;
            padding: 1rem;
            text-decoration: none;
            font-weight: 600;
            letter-spacing: 1px;
            font-size: 0.85rem;
            margin-top: 1.5rem;
            border-radius: 2rem;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-checkout:hover {
            background: var(--red);
        }

        .secure-badge {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.65rem;
            color: var(--text-muted);
            letter-spacing: 1px;
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 4rem;
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
        }

        .empty-cart p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .empty-cart .btn-continue {
            display: inline-block;
            background: var(--dark);
            color: white;
            padding: 0.75rem 2rem;
            text-decoration: none;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background 0.3s;
        }

        .empty-cart .btn-continue:hover {
            background: var(--red);
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

        @media (max-width: 900px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
            }
            
            .cart-item {
                flex-direction: column;
                text-align: center;
            }
            
            .item-image {
                margin: 0 auto 1rem;
            }
            
            .item-details {
                padding-left: 0;
                margin-top: 0rem;
            }
            
            .item-actions {
                text-align: center;
                margin-top: 1rem;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .cart-header {
                text-align: center;
                border-left: none;
                padding-left: 0;
            }
        }

        @media (max-width: 480px) {
            .cart-container {
                padding: 0 1rem;
            }
            
            .cart-item {
                padding: 1rem;
            }
            
            .item-meta {
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
                    <span>DIGITAL ATELIER</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="marketplace.php">Gallery</a>
                <a href="cart.php" style="color: var(--red);">Cart</a>
                
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
                
                <a href="messages.php">Messages</a>
                 <!-- Contact Admin Link -->
                    <a href="contact_admin.php" class="nav-contact">📧 Contact Admin</a>
                <a href="logout.php">Logout</a>
                <span style="color: var(--red); font-size: 0.8rem;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="cart-container">
        <header class="cart-header">
            <h1>Your Curated Collection</h1>
            <p>Review your selected heritage pieces before acquisition.</p>
        </header>
        
        <?php if(mysqli_num_rows($cart_items) == 0): ?>
            <div class="empty-cart">
                <p>Your collection is currently empty.</p>
                <a href="marketplace.php" class="btn-continue">Explore the Gallery →</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items">
                    <?php while($item = mysqli_fetch_assoc($cart_items)): 
                        $total += $item['price'];
                        $item_count++;
                    ?>
                    <div class="cart-item">
                        <div class="item-image">
                            <?php if(!empty($item['image_url']) && file_exists($item['image_url'])): ?>
                                <img src="<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php else: ?>
                                <div class="no-image">📷</div>
                            <?php endif; ?>
                        </div>
                        <div class="item-details">
                            <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                            <div class="item-brand"><?php echo htmlspecialchars($item['brand']); ?></div>
                            <div class="item-meta">
                                <span>Size: <?php echo $item['size']; ?></span>
                                <span>Grade: <?php echo $item['condition_grade']; ?></span>
                            </div>
                            <div class="seller-name">
                                Curator: <?php echo htmlspecialchars($item['seller_name']); ?>
                            </div>
                        </div>
                        <div class="item-actions">
                            <span class="item-price">R <?php echo number_format($item['price'], 2); ?></span>
                            <a href="remove_from_cart.php?id=<?php echo $item['clothing_id']; ?>" class="btn-remove" onclick="return confirm('Remove this item from your collection?')">Remove</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="order-summary">
                    <h3>Order Summary</h3>
                    <div class="summary-row">
                        <span>Subtotal (<?php echo $item_count; ?> items)</span>
                        <span>R <?php echo number_format($total, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Authentication Fee</span>
                        <span>Included</span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>Calculated at checkout</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>R <?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <a href="checkout.php" class="btn-checkout">Proceed to Checkout →</a>
                    <div class="secure-badge">
                        🔒 Secure SSL Encryption
                    </div>
                </div>
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