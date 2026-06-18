<?php
session_start();
include_once "../DBConn.php";

// Check admin access
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

// Get item ID from URL
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($item_id == 0){
    header("Location: approve_items.php");
    exit();
}

// Get item details
$item_result = mysqli_query($conn, "SELECT * FROM tblClothes WHERE clothing_id = $item_id");
$item = mysqli_fetch_assoc($item_result);

if(!$item){
    header("Location: approve_items.php?msg=Item not found");
    exit();
}

$success = '';
$error = '';

// Handle form submission
if(isset($_POST['update_item'])){
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = (float)$_POST['price'];
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $size = mysqli_real_escape_string($conn, $_POST['size']);
    $condition = mysqli_real_escape_string($conn, $_POST['condition']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $year = (int)$_POST['year'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Handle image upload
    $image_path = $item['image_url'];
    if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0){
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['product_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        $filesize = $_FILES['product_image']['size'];
        
        if(in_array(strtolower($filetype), $allowed)){
            if($filesize <= 5000000){
                $upload_dir = "../uploads/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_filename = time() . '_' . uniqid() . '.' . $filetype;
                $destination = $upload_dir . $new_filename;
                
                if(move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)){
                    // Delete old image if exists
                    if($item['image_url'] && file_exists("../" . $item['image_url'])){
                        unlink("../" . $item['image_url']);
                    }
                    $image_path = "uploads/" . $new_filename;
                }
            }
        }
    }
    
    $update_sql = "UPDATE tblClothes SET 
                   title = '$title',
                   description = '$description',
                   price = $price,
                   brand = '$brand',
                   size = '$size',
                   condition_grade = '$condition',
                   category = '$category',
                   year = $year,
                   status = '$status',
                   image_url = '$image_path'
                   WHERE clothing_id = $item_id";
    
    if(mysqli_query($conn, $update_sql)){
        $success = "Item updated successfully!";
        // Refresh item data
        $item_result = mysqli_query($conn, "SELECT * FROM tblClothes WHERE clothing_id = $item_id");
        $item = mysqli_fetch_assoc($item_result);
    } else {
        $error = "Error updating item: " . mysqli_error($conn);
    }
}

// Get unread counts for admin
$unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
$unread_notifications = getUnreadNotificationCount($conn, $_SESSION['user_id']);
$total_unread = $unread_messages + $unread_notifications;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #121212;
            --border: #e8e4db;
            --text-muted: #888;
            --success: #2e7d64;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f8f7f2; font-family: 'Inter', sans-serif; }
        
        .admin-nav { background: var(--dark); padding: 1rem 2rem; color: white; border-bottom: 2px solid var(--red); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo a { text-decoration: none; color: white; }
        .logo h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; letter-spacing: -0.5px; }
        .logo span { color: var(--red); font-size: 0.7rem; letter-spacing: 3px; }
        .nav-links a { color: white; text-decoration: none; margin-left: 2rem; font-size: 0.8rem; transition: color 0.3s; }
        .nav-links a:hover { color: var(--red); }
        
        .btn-logout { background: var(--red); color: white !important; padding: 0.5rem 1.2rem; border-radius: 4px; text-decoration: none; transition: all 0.3s; margin-left: 15px; }
        .btn-logout:hover { background: var(--red-dark); transform: translateY(-1px); }
        
        .admin-container { max-width: 1000px; margin: 2rem auto; padding: 0 2rem; }
        .page-header h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; }
        .page-header p { color: var(--text-muted); margin-top: 0.25rem; }
        
        .card { background: white; border-radius: 0.75rem; padding: 2rem; margin-bottom: 2rem; border: 1px solid var(--border); }
        .card h2 { font-family: 'Cormorant Garamond', serif; font-size: 1.2rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--red); display: inline-block; margin-bottom: 1.5rem; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--text-muted); letter-spacing: 1px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 0.5rem; font-size: 0.9rem; font-family: 'Inter', sans-serif; transition: all 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--red); box-shadow: 0 0 0 3px rgba(196, 69, 54, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .btn-primary { background: var(--dark); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 2rem; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary:hover { background: var(--red); transform: translateY(-1px); }
        .btn-secondary { background: #e0e0e0; color: #333; padding: 0.75rem 1.5rem; border: none; border-radius: 2rem; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-secondary:hover { background: #ccc; }
        .button-group { display: flex; gap: 1rem; margin-top: 1rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.85rem; }
        .alert-success { background: #e8f5e9; color: var(--success); border-left: 4px solid var(--success); }
        .alert-error { background: #ffebee; color: var(--red); border-left: 4px solid var(--red); }
        .image-preview { max-width: 200px; margin-top: 0.5rem; border-radius: 0.5rem; border: 1px solid var(--border); }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .button-group { flex-direction: column; }
            .nav-container { flex-direction: column; gap: 1rem; }
        }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="nav-container">
            <div class="logo">
                <a href="dashboard.php"><h1>PASTIMES</h1><span>ADMIN CONSOLE</span></a>
            </div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_users.php">Users</a>
                <a href="approve_items.php">Listings</a>
                <a href="../logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="page-header">
            <h1>Edit Listing</h1>
            <p>Editing: <?php echo htmlspecialchars($item['title']); ?></p>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Item Information</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" value="<?php echo htmlspecialchars($item['brand']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Size</label>
                        <input type="text" name="size" value="<?php echo htmlspecialchars($item['size']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition">
                            <option value="Mint" <?php echo $item['condition_grade'] == 'Mint' ? 'selected' : ''; ?>>Mint</option>
                            <option value="Excellent" <?php echo $item['condition_grade'] == 'Excellent' ? 'selected' : ''; ?>>Excellent</option>
                            <option value="Very Good" <?php echo $item['condition_grade'] == 'Very Good' ? 'selected' : ''; ?>>Very Good</option>
                            <option value="Good" <?php echo $item['condition_grade'] == 'Good' ? 'selected' : ''; ?>>Good</option>
                            <option value="Loved" <?php echo $item['condition_grade'] == 'Loved' ? 'selected' : ''; ?>>Loved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" value="<?php echo htmlspecialchars($item['category']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" value="<?php echo $item['year']; ?>" placeholder="1994">
                    </div>
                    <div class="form-group">
                        <label>Price (ZAR)</label>
                        <input type="number" step="0.01" name="price" value="<?php echo $item['price']; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="pending" <?php echo $item['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $item['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $item['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="sold" <?php echo $item['status'] == 'sold' ? 'selected' : ''; ?>>Sold</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="6" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Change Image</label>
                    <input type="file" name="product_image" accept="image/*">
                    <?php if(!empty($item['image_url']) && file_exists("../" . $item['image_url'])): ?>
                        <div>
                            <img src="../<?php echo $item['image_url']; ?>" alt="Current image" class="image-preview">
                            <p style="font-size: 0.7rem; color: var(--text-muted);">Current image</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="update_item" class="btn-primary">Update Item</button>
                    <a href="approve_items.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>