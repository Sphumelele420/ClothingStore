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

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($product_id == 0){
    header("Location: marketplace.php");
    exit();
}

// Get product details with seller info
$sql = "SELECT c.*, u.full_name as seller_name, u.username as seller_username, u.email as seller_email
        FROM tblClothes c
        JOIN tblUser u ON c.seller_id = u.user_id
        WHERE c.clothing_id = $product_id AND c.status = 'approved'";

$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) == 0){
    header("Location: marketplace.php");
    exit();
}

$product = mysqli_fetch_assoc($result);

// Get related products (same brand or category)
$related_sql = "SELECT * FROM tblClothes 
                WHERE status='approved' 
                AND clothing_id != $product_id 
                AND (brand = '{$product['brand']}' OR category = '{$product['category']}')
                LIMIT 4";
$related_result = mysqli_query($conn, $related_sql);

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
    <title><?php echo htmlspecialchars($product['title']); ?> - Pastimes Atelier</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --cream: #f5f0e8;
            --border: #e8e4db;
            --text-gray: #888;
            --success: #2e7d64;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            background: var(--cream); 
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

        /* Product Container */
        .product-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .product-main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 3rem;
            border: 1px solid var(--border);
        }
        
        .product-gallery {
            position: sticky;
            top: 100px;
        }
        
        .main-image {
            width: 100%;
            height: 500px;
            background: linear-gradient(135deg, #E8E4DC 0%, #D4CEC4 100%);
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .main-image .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            font-size: 4rem;
        }
        
        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        
        .thumbnail {
            height: 80px;
            background: linear-gradient(135deg, #E8E4DC 0%, #D4CEC4 100%);
            border-radius: 0.5rem;
            cursor: pointer;
            border: 2px solid transparent;
            overflow: hidden;
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .thumbnail.active {
            border-color: var(--red);
        }
        
        .thumbnail:hover {
            border-color: var(--red);
        }
        
        .product-info h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .product-brand {
            font-size: 0.9rem;
            color: var(--red);
            margin-bottom: 1rem;
            letter-spacing: 1px;
        }
        
        .product-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--red);
            margin: 1rem 0;
        }
        
        .product-meta {
            display: flex;
            gap: 2rem;
            padding: 1rem 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin: 1rem 0;
        }
        
        .meta-item {
            text-align: center;
        }
        
        .meta-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-gray);
            letter-spacing: 1px;
        }
        
        .meta-value {
            font-weight: 600;
            font-size: 1rem;
            margin-top: 0.25rem;
        }
        
        .sustainability-score {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--success);
            color: white;
            border-radius: 2rem;
            font-size: 0.75rem;
            margin: 1rem 0;
        }
        
        .product-description h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .seller-card {
            background: #faf8f4;
            padding: 1.5rem;
            border-radius: 1rem;
            margin: 1.5rem 0;
            border: 1px solid var(--border);
        }
        
        .seller-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .message-seller {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
        }
        
        .btn-message-seller {
            background: var(--dark);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s;
        }

        .btn-message-seller:hover {
            background: var(--red);
        }
        
        .btn-buy-now {
            background: var(--red);
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-buy-now:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }
        
        .related-section {
            margin-top: 3rem;
        }
        
        .related-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        
        .related-card {
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s;
            border: 1px solid var(--border);
        }
        
        .related-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .related-image {
            height: 200px;
            background: linear-gradient(135deg, #E8E4DC 0%, #D4CEC4 100%);
            overflow: hidden;
        }

        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-info {
            padding: 1rem;
        }
        
        .related-info h4 {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .related-price {
            font-weight: 700;
            color: var(--red);
            margin-top: 0.5rem;
        }

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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            margin: 1rem 0;
            font-family: inherit;
            resize: vertical;
        }

        .btn-primary {
            background: var(--red);
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: var(--red-dark);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            padding: 0.75rem;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            border-color: var(--red);
            color: var(--red);
        }
        
        @media (max-width: 900px) {
            .product-main {
                grid-template-columns: 1fr;
            }
            
            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .related-grid {
                grid-template-columns: 1fr;
            }
            
            .product-meta {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .message-seller {
                flex-direction: column;
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
                    <span>Digital Atelier</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="marketplace.php">Gallery</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'seller'): ?>
                        <a href="seller/my_atelier.php">My Atelier</a>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <a href="admin/dashboard.php">Dashboard</a>
                    <?php else: ?>
                        <a href="cart.php">Cart</a>
                    <?php endif; ?>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
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
                    <?php endif; ?>
                    
                    <a href="messages.php">Messages</a>
                    <a href="logout.php">Logout</a>
                    <span style="color: var(--red); font-size: 0.8rem;">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <?php else: ?>
                    <a href="login.php">Sign In</a>
                    <a href="register.php" class="nav-cta">Join Atelier</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="product-container">
        <div class="product-main">
            <!-- Left: Image Gallery -->
            <div class="product-gallery">
                <div class="main-image">
                    <?php if(!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                        <img src="<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" id="mainProductImage">
                    <?php else: ?>
                        <div class="no-image">📷</div>
                    <?php endif; ?>
                </div>
                <div class="thumbnail-grid">
                    <?php if(!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                        <div class="thumbnail active" onclick="changeImage('<?php echo $product['image_url']; ?>', this)">
                            <img src="<?php echo $product['image_url']; ?>" alt="Thumbnail 1">
                        </div>
                    <?php else: ?>
                        <div class="thumbnail"></div>
                        <div class="thumbnail"></div>
                        <div class="thumbnail"></div>
                        <div class="thumbnail"></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right: Product Information -->
            <div class="product-info">
                <span class="sustainability-score">🌿 SUSTAINABILITY SCORE <?php echo $product['sustainability_score']; ?></span>
                
                <h1><?php echo htmlspecialchars($product['title']); ?></h1>
                <p class="product-brand"><?php echo htmlspecialchars($product['brand']); ?> • Heritage Archive</p>
                
                <div class="product-price">R <?php echo number_format($product['price'], 2); ?></div>
                
                <div class="product-meta">
                    <div class="meta-item">
                        <div class="meta-label">CONDITION</div>
                        <div class="meta-value"><?php echo $product['condition_grade']; ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">SIZE</div>
                        <div class="meta-value"><?php echo $product['size']; ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">YEAR</div>
                        <div class="meta-value"><?php echo $product['year'] ?: 'Vintage'; ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">REFERENCE</div>
                        <div class="meta-value">#<?php echo $product['clothing_id']; ?></div>
                    </div>
                </div>
                
                <div class="product-description">
                    <h3>The Story</h3>
                    <p style="line-height: 1.6; color: var(--text-gray);">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </p>
                </div>
                
                <!-- Seller Information -->
                <div class="seller-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: var(--red); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.2rem;">
                            <?php echo strtoupper(substr($product['seller_name'], 0, 2)); ?>
                        </div>
                        <div>
                            <div class="seller-name"><?php echo htmlspecialchars($product['seller_name']); ?></div>
                            <div style="font-size: 0.7rem; color: var(--red); letter-spacing: 1px;">VERIFIED CURATOR</div>
                        </div>
                    </div>
                    
                    <div class="message-seller">
                        <button class="btn-buy-now" onclick="addToCart(<?php echo $product['clothing_id']; ?>)">
                            🛒 ADD TO CART
                        </button>
                        <button class="btn-message-seller" onclick="openMessageModal()">
                            💬 MESSAGE SELLER
                        </button>
                    </div>
                </div>
                
                <!-- Authentication Guarantee -->
                <div style="display: flex; gap: 2rem; justify-content: center; padding: 1rem; background: #faf8f4; border-radius: 0.5rem; margin-top: 1rem;">
                    <span style="font-size: 0.7rem; letter-spacing: 1px;">🔒 AUTHENTICATED</span>
                    <span style="font-size: 0.7rem; letter-spacing: 1px;">🚚 INSURED SHIPPING</span>
                    <span style="font-size: 0.7rem; letter-spacing: 1px;">🔄 14-DAY RETURNS</span>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if(mysqli_num_rows($related_result) > 0): ?>
        <div class="related-section">
            <h3 class="related-title">You May Also Like</h3>
            <div class="related-grid">
                <?php while($related = mysqli_fetch_assoc($related_result)): ?>
                <a href="product_details.php?id=<?php echo $related['clothing_id']; ?>" class="related-card">
                    <div class="related-image">
                        <?php if(!empty($related['image_url']) && file_exists($related['image_url'])): ?>
                            <img src="<?php echo $related['image_url']; ?>" alt="<?php echo htmlspecialchars($related['title']); ?>">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%;">📷</div>
                        <?php endif; ?>
                    </div>
                    <div class="related-info">
                        <h4><?php echo htmlspecialchars($related['title']); ?></h4>
                        <p style="font-size: 0.7rem; color: var(--text-gray);"><?php echo $related['brand']; ?></p>
                        <div class="related-price">R <?php echo number_format($related['price'], 2); ?></div>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <h3>Message Seller</h3>
            <form method="POST" action="send_message.php">
                <input type="hidden" name="receiver_id" value="<?php echo $product['seller_id']; ?>">
                <input type="hidden" name="product_id" value="<?php echo $product['clothing_id']; ?>">
                <textarea name="message" rows="5" placeholder="Ask about condition, sizing, or make an offer..." required></textarea>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn-primary">Send Message</button>
                    <button type="button" class="btn-secondary" onclick="closeMessageModal()">Cancel</button>
                </div>
            </form>
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
        function changeImage(imageUrl, thumbnailElement) {
            const mainImage = document.getElementById('mainProductImage');
            if(mainImage) {
                mainImage.src = imageUrl;
            }
            
            // Remove active class from all thumbnails
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            
            // Add active class to clicked thumbnail
            thumbnailElement.classList.add('active');
        }
        
        function addToCart(productId) {
            <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'buyer'): ?>
                window.location.href = 'add_to_cart.php?id=' + productId;
            <?php else: ?>
                if(confirm('Please login to add items to cart. Go to login page?')) {
                    window.location.href = 'login.php';
                }
            <?php endif; ?>
        }
        
        function openMessageModal() {
            <?php if(isset($_SESSION['user_id'])): ?>
                document.getElementById('messageModal').style.display = 'flex';
            <?php else: ?>
                if(confirm('Please login to message the seller. Go to login page?')) {
                    window.location.href = 'login.php';
                }
            <?php endif; ?>
        }
        
        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            let modal = document.getElementById('messageModal');
            if(event.target == modal) {
                modal.style.display = 'none';
            }
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
            fetch('get_notifications.php')
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