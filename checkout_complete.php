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

// Check if user is logged in and is a buyer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer'){
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

// Process checkout
if(isset($_POST['place_order'])){
    $shipping_address = mysqli_real_escape_string($conn, $_POST['shipping_address']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    
    // Get cart items to calculate total
    $cart_total_query = mysqli_query($conn, "SELECT SUM(cl.price) as total FROM tblCart c JOIN tblClothes cl ON c.clothing_id = cl.clothing_id WHERE c.buyer_id = $buyer_id");
    $cart_total = mysqli_fetch_assoc($cart_total_query);
    $order_total = $cart_total['total'];
    
    // Get cart data for order creation
    $cart_data = mysqli_query($conn, "SELECT c.*, cl.seller_id, cl.price, cl.clothing_id FROM tblCart c JOIN tblClothes cl ON c.clothing_id = cl.clothing_id WHERE c.buyer_id = $buyer_id");
    
    if(mysqli_num_rows($cart_data) > 0){
        $order_sql = "INSERT INTO tblAorder (buyer_id, seller_id, clothing_id, quantity, total_price, order_status, shipping_address) VALUES ";
        $items = [];
        
        while($item = mysqli_fetch_assoc($cart_data)){
            $items[] = "($buyer_id, {$item['seller_id']}, {$item['clothing_id']}, 1, {$item['price']}, 'pending', '$shipping_address')";
            // Update clothing status to sold
            mysqli_query($conn, "UPDATE tblClothes SET status='sold' WHERE clothing_id = {$item['clothing_id']}");
        }
        
        $order_sql .= implode(", ", $items);
        
        if(mysqli_query($conn, $order_sql)){
            // Get the last inserted order ID
            $order_id = mysqli_insert_id($conn);
            
            // Insert transaction record
            mysqli_query($conn, "INSERT INTO tblTransactions (order_id, amount, payment_method, transaction_status) 
                                 VALUES ($order_id, $order_total, '$payment_method', 'completed')");
            
            // Clear cart
            mysqli_query($conn, "DELETE FROM tblCart WHERE buyer_id = $buyer_id");
            
            // Redirect to checkout_complete page
            header("Location: checkout_complete.php");
            exit();
        } else {
            $error = "Order failed: " . mysqli_error($conn);
        }
    } else {
        $error = "Your cart is empty.";
    }
}

// Get cart items for display
$cart_items = mysqli_query($conn, "SELECT c.*, cl.clothing_id, cl.title, cl.price, cl.brand, cl.size, cl.condition_grade, u.full_name as seller_name, u.user_id as seller_id
                                   FROM tblCart c
                                   JOIN tblClothes cl ON c.clothing_id = cl.clothing_id
                                   JOIN tblUser u ON cl.seller_id = u.user_id
                                   WHERE c.buyer_id = $buyer_id");

$total = 0;
while($item = mysqli_fetch_assoc($cart_items)){
    $total += $item['price'];
}

// Redirect if cart is empty
if(mysqli_num_rows($cart_items) == 0){
    header("Location: cart.php");
    exit();
}

// Reset pointer for display
mysqli_data_seek($cart_items, 0);

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
    <title>Checkout - Pastimes Digital Atelier</title>
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
            --error: #c0392b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            background-color: var(--cream); 
            color: var(--dark); 
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

        /* Checkout Container */
        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .checkout-header {
            margin-bottom: 2rem;
            border-left: 4px solid var(--red);
            padding-left: 1.5rem;
        }

        .checkout-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .checkout-header p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Two Column Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        /* Left Column - Forms */
        .checkout-form {
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.5rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-section h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--red);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-family: inherit;
            font-size: 0.85rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(196, 69, 54, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .summary-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-item .item-name {
            flex: 1;
        }

        .summary-item .item-price {
            font-weight: 600;
            color: var(--red);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            font-size: 0.85rem;
        }

        .summary-row.total {
            border-top: 1px solid var(--border);
            margin-top: 0.5rem;
            padding-top: 1rem;
            font-size: 1rem;
            font-weight: 700;
        }

        .summary-row.total span:last-child {
            color: var(--red);
            font-size: 1.2rem;
        }

        .btn-place-order {
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

        .btn-place-order:hover {
            background: var(--red);
        }

        .secure-badge {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.65rem;
            color: var(--text-muted);
            letter-spacing: 1px;
        }

        .error-message {
            background: #ffebee;
            color: #c0392b;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            border-left: 4px solid #c0392b;
        }

        .footer {
            background: var(--dark);
            color: white;
            padding: 2rem;
            margin-top: 3rem;
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
            .checkout-layout {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 1rem;
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
                <a href="cart.php">Cart</a>
                <a href="checkout.php" style="color: var(--red);">Checkout</a>
                
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
                <a href="logout.php">Logout</a>
                <span style="color: var(--red); font-size: 0.8rem;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="checkout-container">
        <div class="checkout-header">
            <h1>Complete Your Acquisition</h1>
            <p>CURATED HERITAGE • FINAL STEP</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="error-message">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="checkout-layout">
            <!-- Left Column - Forms -->
            <form method="POST" class="checkout-form">
                <div class="form-section">
                    <h3>Shipping Destination</h3>
                    <div class="form-group">
                        <label>FULL NAME</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>SHIPPING ADDRESS</label>
                        <textarea name="shipping_address" rows="3" placeholder="Street address, city, postal code, country" required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>CITY</label>
                            <input type="text" name="city" placeholder="City" required>
                        </div>
                        <div class="form-group">
                            <label>POSTAL CODE</label>
                            <input type="text" name="postal_code" placeholder="Postal code" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>PHONE NUMBER</label>
                        <input type="tel" name="phone" placeholder="Contact number for delivery" required>
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.7rem;">
                        Your heritage piece is covered by our carbon-neutral, fully insured white-glove delivery service.
                    </small>
                </div>

                <div class="form-section">
                    <h3>Payment Method</h3>
                    <div class="form-group">
                        <label>SELECT PAYMENT METHOD</label>
                        <select name="payment_method" required>
                            <option value="">Select a payment method</option>
                            <option value="Visa">Visa •••• 8812</option>
                            <option value="Mastercard">Mastercard •••• 4456</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Bank Transfer">Bank Transfer (EFT)</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>CARD NUMBER (if applicable)</label>
                            <input type="text" placeholder="XXXX XXXX XXXX XXXX">
                        </div>
                        <div class="form-group">
                            <label>EXPIRY DATE</label>
                            <input type="text" placeholder="MM/YY">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>SECURITY CODE (CVV)</label>
                        <input type="text" placeholder="123">
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.7rem;">
                        Your payment information is encrypted and secure.
                    </small>
                </div>

                <div class="form-section">
                    <h3>Order Notes (Optional)</h3>
                    <div class="form-group">
                        <textarea name="order_notes" rows="2" placeholder="Special delivery instructions or gift message..."></textarea>
                    </div>
                </div>

                <button type="submit" name="place_order" class="btn-place-order">Confirm Acquisition →</button>
                <div class="secure-badge">
                    🔒 SECURE SSL | ✓ AUTHENTICATED | ↻ CIRCULAR
                </div>
            </form>

            <!-- Right Column - Order Summary -->
            <div class="order-summary">
                <h3>Order Summary</h3>
                
                <div class="summary-items">
                    <?php 
                    // Reset pointer and display items
                    mysqli_data_seek($cart_items, 0);
                    while($item = mysqli_fetch_assoc($cart_items)): 
                    ?>
                    <div class="summary-item">
                        <span class="item-name"><?php echo htmlspecialchars($item['title']); ?></span>
                        <span class="item-price">R <?php echo number_format($item['price'], 2); ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>R <?php echo number_format($total, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>Complimentary</span>
                </div>
                <div class="summary-row">
                    <span>Authentication Fee</span>
                    <span>Included</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span>R <?php echo number_format($total, 2); ?></span>
                </div>

                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                    <p style="font-size: 0.7rem; color: var(--text-muted); text-align: center;">
                        By clicking the payment button, you agree to the Pastimes Terms of Authentication and Secure Escrow Service.
                    </p>
                </div>
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