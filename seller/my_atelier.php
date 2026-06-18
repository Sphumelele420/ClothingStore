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

// Ensure security and role access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller'){
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';

// Create uploads directory if it doesn't exist
$upload_dir = "../uploads/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if(isset($_POST['publish'])){
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = (float)$_POST['price'];
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $size = mysqli_real_escape_string($conn, $_POST['size']);
    $condition = mysqli_real_escape_string($conn, $_POST['condition']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $year = (int)$_POST['year'];
    $seller_id = $_SESSION['user_id'];
    
    // Handle image upload
    $image_path = '';
    if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0){
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['product_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        $filesize = $_FILES['product_image']['size'];
        
        // Validate file type
        if(in_array(strtolower($filetype), $allowed)){
            // Validate file size (max 5MB)
            if($filesize <= 5000000){
                $new_filename = time() . '_' . uniqid() . '.' . $filetype;
                $destination = $upload_dir . $new_filename;
                
                if(move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)){
                    $image_path = "uploads/" . $new_filename;
                } else {
                    $error = "Failed to upload image. Please try again.";
                }
            } else {
                $error = "Image is too large. Maximum size is 5MB.";
            }
        } else {
            $error = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP";
        }
    } else {
        $error = "Please select an image for your listing.";
    }
    
    if($title && $description && $price && $image_path && !$error){
        $sql = "INSERT INTO tblClothes (seller_id, title, description, price, brand, size, condition_grade, category, year, status, image_url) 
                VALUES ('$seller_id', '$title', '$description', '$price', '$brand', '$size', '$condition', '$category', '$year', 'pending', '$image_path')";
        
        if(mysqli_query($conn, $sql)){
            $message = "Your masterpiece has been submitted for review by our curators.";
            header("Location: ../marketplace.php?msg=submitted");
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    } elseif(!$error) {
        $error = "Please fill in all required fields and upload an image.";
    }
}

// Get unread counts for seller
$unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
$unread_notifications = getUnreadNotificationCount($conn, $_SESSION['user_id']);
$total_unread = $unread_messages + $unread_notifications;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Listing - Pastimes Atelier</title>
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

        /* Main Container */
        .create-listing-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Banner */
        .zero-fees-banner {
            background: linear-gradient(135deg, var(--dark) 0%, #2a2a2a 100%);
            color: white;
            text-align: center;
            padding: 1rem;
            letter-spacing: 3px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 2rem;
            border-radius: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .alert-success {
            background: #e8f5e9;
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #ffebee;
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        /* Form Sections Grid */
        .form-sections {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 2rem;
        }

        /* Left Sidebar */
        .visual-sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .image-upload-area {
            border: 2px dashed var(--border);
            background: white;
            border-radius: 1rem;
            min-height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
            overflow: hidden;
            position: relative;
        }

        .image-upload-area:hover {
            border-color: var(--red);
            background: #fafafa;
        }

        .image-upload-area.dragover {
            border-color: var(--red);
            background: #f5f2eb;
        }

        .image-placeholder {
            padding: 2rem;
        }

        .image-placeholder span {
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
        }

        .image-placeholder p {
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .image-placeholder small {
            color: var(--text-muted);
            font-size: 0.7rem;
        }

        .image-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .remove-image {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            cursor: pointer;
            font-size: 0.7rem;
            z-index: 10;
            transition: background 0.3s;
        }

        .remove-image:hover {
            background: var(--error);
        }

        /* AI Suggestion Card */
        .ai-suggestion {
            background: linear-gradient(135deg, #faf8f4 0%, #f5f2eb 100%);
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border);
        }

        .suggestion-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--success);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .ai-suggestion h4 {
            font-size: 1rem;
            margin: 0.75rem 0;
            font-weight: 600;
        }

        .suggestion-content p {
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }

        .suggestion-content strong {
            color: var(--red);
        }

        .suggestion-tip {
            background: rgba(196, 69, 54, 0.1);
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-style: italic;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }

        .form-group label .required {
            color: var(--error);
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background: white;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(196, 69, 54, 0.1);
        }

        .form-row {
            display: flex;
            gap: 1.25rem;
        }

        .half {
            flex: 1;
        }

        /* Earnings Preview */
        .earnings-preview {
            background: linear-gradient(135deg, #f8f7f2 0%, #f0ede6 100%);
            padding: 1.25rem;
            border-radius: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0;
        }

        .earnings-info p {
            margin: 0;
            font-weight: 700;
            font-size: 0.8rem;
        }

        .earnings-info small {
            color: var(--success);
            font-size: 0.7rem;
        }

        .earnings-amount span {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--red);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-primary {
            background: var(--dark);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 2rem;
            font-weight: 700;
            letter-spacing: 1px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
        }

        .btn-primary:hover {
            background: var(--red);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(196, 69, 54, 0.3);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            padding: 1rem 2rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
        }

        .btn-secondary:hover {
            border-color: var(--red);
            color: var(--red);
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

        /* Responsive */
        @media (max-width: 900px) {
            .form-sections {
                grid-template-columns: 1fr;
            }
            
            .visual-sidebar {
                position: static;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .create-listing-container {
                padding: 0 1rem;
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
                <a href="../marketplace.php" style="text-decoration: none; color: inherit;">
                    <h1>Pastimes</h1>
                    <span>SELLER ATELIER</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="../marketplace.php">Gallery</a>
                <a href="my_atelier.php">Sell Items</a>
                
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
                
                <a href="../messages.php">Messages</a>
                 <!-- Contact Admin Link -->
                    <a href="contact_admin.php" class="nav-contact">📧 Contact Admin</a>
                <a href="../logout.php">Logout</a>
                <span style="color: var(--red); font-size: 0.8rem;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="create-listing-container">
        <div class="zero-fees-banner">
            ✨ ZERO SELLER FEES • KEEP 100% OF YOUR SALE ✨
        </div>
        
        <?php if($message): ?>
            <div class="alert alert-success">
                ✓ <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                ⚠️ <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="listing-form" id="listingForm" enctype="multipart/form-data">
            <div class="form-sections">
                <!-- Left Sidebar: Visuals & Intelligence -->
                <div class="visual-sidebar">
                    <div class="image-upload-area" id="imageUploadArea">
                        <input type="file" name="product_image" id="product_image" accept="image/*" style="display: none;" required>
                        <div class="image-placeholder" id="imagePlaceholder">
                            <span>📷</span>
                            <p>Upload Primary Image</p>
                            <small>Click or drag & drop (Max 5MB)</small>
                            <small style="display: block; margin-top: 5px;">JPG, PNG, GIF, WEBP</small>
                        </div>
                        <img id="imagePreview" class="image-preview" style="display: none;">
                        <button type="button" class="remove-image" id="removeImageBtn" style="display: none;">✗ Remove</button>
                    </div>
                    
                    <div class="ai-suggestion">
                        <div class="suggestion-label">● CURATOR AI ANALYSIS</div>
                        <h4>Suggested Details</h4>
                        <div class="suggestion-content">
                            <p><strong>Brand Match:</strong> Burberry Heritage Line (94% confidence)</p>
                            <p><strong>Estimated Value:</strong> R12,500 — R14,200</p>
                            <div class="suggestion-tip">
                                💡 "Based on current gallery trends, items with 'Heritage' in the title sell 30% faster."
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Main: Data Entry -->
                <div class="data-entry">
                    <div class="form-group">
                        <label for="title">Masterpiece Title <span class="required">*</span></label>
                        <input type="text" name="title" id="title" required placeholder="e.g., 1994 Archive Burberry Trench Coat">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="brand">Brand <span class="required">*</span></label>
                            <input type="text" name="brand" id="brand" required placeholder="Burberry">
                        </div>
                        <div class="form-group half">
                            <label for="size">Size <span class="required">*</span></label>
                            <input type="text" name="size" id="size" required placeholder="UK 40 / US 38">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="condition">Condition Grade <span class="required">*</span></label>
                            <select name="condition" id="condition" required>
                                <option value="Mint">Mint (Unworn / Pristine)</option>
                                <option value="Excellent" selected>Excellent (Minimal wear)</option>
                                <option value="Very Good">Very Good (Minor flaws)</option>
                                <option value="Good">Good (Visible wear)</option>
                                <option value="Loved">Loved (Character & history)</option>
                            </select>
                        </div>
                        <div class="form-group half">
                            <label for="category">Category <span class="required">*</span></label>
                            <input type="text" name="category" id="category" required placeholder="Outerwear / Dresses / Accessories">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="year">Circa (Year)</label>
                            <input type="number" name="year" id="year" placeholder="1994">
                        </div>
                        <div class="form-group half">
                            <label for="price">Listing Price (ZAR) <span class="required">*</span></label>
                            <input type="number" step="0.01" name="price" id="price" required placeholder="12950">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">The Piece's Story <span class="required">*</span></label>
                        <textarea name="description" id="description" rows="6" required placeholder="Describe the heritage, fabric, condition details, and any unique character..."></textarea>
                    </div>
                    
                    <div class="earnings-preview">
                        <div class="earnings-info">
                            <p>ESTIMATED EARNINGS</p>
                            <small>✓ 0% Commission Fee • Keep 100%</small>
                        </div>
                        <div class="earnings-amount">
                            <span id="earnings_display">R0.00</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="publish" class="btn-primary">Publish to Gallery</button>
                        <button type="button" class="btn-secondary">Save as Draft</button>
                    </div>
                </div>
            </div>
        </form>
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
        // Image upload functionality
        const imageUploadArea = document.getElementById('imageUploadArea');
        const imageInput = document.getElementById('product_image');
        const imagePlaceholder = document.getElementById('imagePlaceholder');
        const imagePreview = document.getElementById('imagePreview');
        const removeImageBtn = document.getElementById('removeImageBtn');
        
        // Click to upload
        imageUploadArea.addEventListener('click', () => {
            imageInput.click();
        });
        
        // Handle file selection
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                validateAndPreview(file);
            }
        });
        
        // Drag and drop functionality
        imageUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            imageUploadArea.classList.add('dragover');
        });
        
        imageUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            imageUploadArea.classList.remove('dragover');
        });
        
        imageUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            imageUploadArea.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (file) {
                validateAndPreview(file);
                // Set the file input's files
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                imageInput.files = dataTransfer.files;
            }
        });
        
        function validateAndPreview(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload JPG, PNG, GIF, or WEBP images.');
                return;
            }
            
            // Validate file size (5MB max)
            if (file.size > 5000000) {
                alert('File is too large. Maximum size is 5MB.');
                return;
            }
            
            // Preview image
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                imagePlaceholder.style.display = 'none';
                removeImageBtn.style.display = 'block';
                imageUploadArea.style.border = '2px solid var(--success)';
            };
            reader.readAsDataURL(file);
        }
        
        // Remove image
        removeImageBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            imageInput.value = '';
            imagePreview.style.display = 'none';
            imagePlaceholder.style.display = 'block';
            removeImageBtn.style.display = 'none';
            imageUploadArea.style.border = '2px dashed var(--border)';
        });
        
        // Price calculation
        const priceInput = document.getElementById('price');
        const earningsDisplay = document.getElementById('earnings_display');
        
        priceInput.addEventListener('input', function() {
            let value = parseFloat(this.value) || 0;
            earningsDisplay.textContent = 'R' + value.toLocaleString(undefined, {minimumFractionDigits: 2});
        });
        
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