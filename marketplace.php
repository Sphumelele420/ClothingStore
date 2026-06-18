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

// Get filters
$where = "status='approved'";
if(isset($_GET['brand']) && $_GET['brand']) $where .= " AND brand='".mysqli_real_escape_string($conn, $_GET['brand'])."'";
if(isset($_GET['sustainability']) && $_GET['sustainability']) $where .= " AND sustainability_score='".mysqli_real_escape_string($conn, $_GET['sustainability'])."'";
if(isset($_GET['min_price']) && $_GET['min_price']) $where .= " AND price >= ". (int)$_GET['min_price'];
if(isset($_GET['max_price']) && $_GET['max_price']) $where .= " AND price <= ". (int)$_GET['max_price'];

$result = mysqli_query($conn, "SELECT * FROM tblClothes WHERE $where ORDER BY created_at DESC");

// Check for submission success
$submitted = isset($_GET['submitted']) && $_GET['submitted'] == 'success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - Pastimes Digital Atelier</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --bg: #f5f0e8;
            --border: #e8e4db;
            --text-muted: #888;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg);
            font-family: 'Inter', sans-serif;
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

        /* Contact Admin Link Style */
        .nav-contact {
            color: var(--red) !important;
            font-weight: 600;
            position: relative;
        }

        .nav-contact:hover {
            color: var(--red-dark) !important;
        }

        .nav-contact::after {
            content: '●';
            font-size: 0.4rem;
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
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

        /* Marketplace Container */
        .marketplace-container {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            gap: 40px;
        }

        /* Sidebar */
        .filters-sidebar {
            width: 280px;
            flex-shrink: 0;
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .filters-sidebar h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.2rem;
            letter-spacing: 2px;
            border-bottom: 1px solid var(--dark);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .filter-group {
            margin-bottom: 25px;
        }

        .filter-group h4 {
            font-size: 0.7rem;
            color: var(--red);
            letter-spacing: 2px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .filter-group label {
            display: block;
            font-size: 0.8rem;
            margin-bottom: 8px;
            cursor: pointer;
            color: #555;
        }

        .filter-group label:hover {
            color: var(--red);
        }

        .price-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-inputs input {
            width: 80px;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
        }

        /* Gallery */
        .gallery-main {
            flex-grow: 1;
        }

        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }

        .gallery-header h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem;
            font-weight: 500;
        }

        .sort-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 30px;
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
            background: white;
            cursor: pointer;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        /* Heritage Card */
        .heritage-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
            overflow: hidden;
        }

        .heritage-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .card-image {
            height: 320px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #E8E4DC 0%, #D4CEC4 100%);
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }

        .sustainability-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(255,255,255,0.95);
            padding: 4px 10px;
            font-size: 0.6rem;
            letter-spacing: 1px;
            font-weight: 600;
            border: 1px solid var(--red);
            z-index: 1;
            border-radius: 20px;
            color: var(--dark);
        }

        .card-details {
            padding: 20px;
            text-align: center;
        }

        .card-details h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .brand {
            color: var(--text-muted);
            font-size: 0.7rem;
            margin-bottom: 12px;
        }

        .price {
            font-weight: 700;
            color: var(--red);
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        .btn-message {
            display: inline-block;
            text-decoration: none;
            color: var(--dark);
            border: 1px solid var(--border);
            padding: 8px 20px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
            background: white;
            cursor: pointer;
            border-radius: 30px;
        }

        .btn-message:hover {
            background: var(--red);
            color: white;
            border-color: var(--red);
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d64;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #2e7d64;
            font-size: 0.85rem;
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

        @media (max-width: 900px) {
            .marketplace-container {
                flex-direction: column;
            }
            .filters-sidebar {
                width: 100%;
                position: static;
            }
            .card-image {
                height: 280px;
            }
            .notification-dropdown {
                width: 300px;
                right: -50px;
            }
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
                <a href="marketplace.php" class="active">Gallery</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'seller'): ?>
                        <a href="seller/my_atelier.php">My Atelier</a>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <a href="admin/dashboard.php">Dashboard</a>
                    <?php else: ?>
                        <a href="cart.php">Cart</a>
                    <?php endif; ?>
                    
                    <?php 
                    // Get unread counts
                    $unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
                    $unread_notifications = getUnreadNotificationCount($conn, $_SESSION['user_id']);
                    $total_unread = $unread_messages + $unread_notifications;
                    ?>
                    
                    <a href="messages.php">Messages</a>
                    
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
                    <span style="color: var(--red); font-size: 0.8rem;">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <?php else: ?>
                    <a href="login.php">Sign In</a>
                    <a href="register.php" class="nav-cta">Join Atelier</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="marketplace-container">
        <aside class="filters-sidebar">
            <h3>Refine Selection</h3>
            
            <div class="filter-group">
                <h4>DESIGNER & BRAND</h4>
                <label><input type="radio" name="brand" value="Hermès"> Hermès Heritage</label>
                <label><input type="radio" name="brand" value="Old Cellini"> Old Cellini</label>
                <label><input type="radio" name="brand" value="Maison Margiela"> Maison Margiela</label>
                <label><input type="radio" name="brand" value="Vintage Chanel"> Vintage Chanel</label>
                <label><input type="radio" name="brand" value="Burberry"> Burberry</label>
            </div>
            
            <div class="filter-group">
                <h4>SUSTAINABILITY SCORE</h4>
                <label><input type="radio" name="sustainability" value="A+"> A+ (Highest)</label>
                <label><input type="radio" name="sustainability" value="A"> A (Good)</label>
                <label><input type="radio" name="sustainability" value="U"> U (Upcycled)</label>
            </div>
            
            <div class="filter-group">
                <h4>PRICE RANGE</h4>
                <div class="price-inputs">
                    <input type="number" id="min_price" placeholder="Min">
                    <span>-</span>
                    <input type="number" id="max_price" placeholder="Max">
                </div>
            </div>
            
            <div class="filter-group">
                <h4>HERITAGE CONDITION</h4>
                <label><input type="checkbox" value="Mint"> MINT</label>
                <label><input type="checkbox" value="Excellent"> EXCELLENT</label>
                <label><input type="checkbox" value="Very Good"> VERY GOOD</label>
                <label><input type="checkbox" value="Loved"> LOVED</label>
            </div>
            
            <button class="btn-message" style="width: 100%; background: var(--dark); color: white; border: none;" onclick="applyFilters()">APPLY FILTERS</button>
            <button class="btn-message" style="width: 100%; margin-top: 10px;" onclick="window.location.href='marketplace.php'">CLEAR ALL</button>
        </aside>
        
        <main class="gallery-main">
            <div class="gallery-header">
                <h2>Found <?php echo mysqli_num_rows($result); ?> Heritage Pieces</h2>
                <select class="sort-select" onchange="sortProducts(this.value)">
                    <option value="newest">Sort by: Newest First</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                </select>
            </div>
            
            <?php if($submitted): ?>
                <div class="alert-success">
                    ✓ Your item has been submitted for curator review! An admin will review it shortly.
                </div>
            <?php endif; ?>
            
            <div class="gallery-grid">
                <?php if(mysqli_num_rows($result) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <a href="product_details.php?id=<?php echo $row['clothing_id']; ?>" class="heritage-card">
                        <div class="card-image">
                            <?php if(!empty($row['image_url']) && file_exists($row['image_url'])): ?>
                                <img src="<?php echo $row['image_url']; ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
                            <?php else: ?>
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%; width: 100%;">
                                    <span style="font-size: 3rem;">📷</span>
                                </div>
                            <?php endif; ?>
                            <span class="sustainability-badge">🌿 SCORE <?php echo $row['sustainability_score']; ?></span>
                        </div>
                        <div class="card-details">
                            <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                            <p class="brand"><?php echo htmlspecialchars($row['brand']); ?> • <?php echo $row['condition_grade']; ?></p>
                            <p class="price">R <?php echo number_format($row['price'], 0); ?></p>
                            <span class="btn-message">VIEW DETAILS →</span>
                        </div>
                    </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 100px;">
                        <p>No heritage pieces found matching your criteria.</p>
                        <a href="marketplace.php" class="btn-message" style="margin-top: 20px; display: inline-block;">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
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
    function applyFilters() {
        let brand = document.querySelector('input[name="brand"]:checked');
        let sustainability = document.querySelector('input[name="sustainability"]:checked');
        let minPrice = document.getElementById('min_price').value;
        let maxPrice = document.getElementById('max_price').value;
        
        let params = new URLSearchParams();
        if(brand) params.append('brand', brand.value);
        if(sustainability) params.append('sustainability', sustainability.value);
        if(minPrice) params.append('min_price', minPrice);
        if(maxPrice) params.append('max_price', maxPrice);
        
        window.location.href = 'marketplace.php?' + params.toString();
    }
    
    function sortProducts(value) {
        let currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('sort', value);
        window.location.href = currentUrl.toString();
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