<?php
session_start();
include_once "../config.php";

// Ensure security and role access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller'){
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';

if(isset($_POST['publish'])){
    // Using a sanitization function (assumed defined in config.php)
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = (float)$_POST['price'];
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $size = mysqli_real_escape_string($conn, $_POST['size']);
    $condition = mysqli_real_escape_string($conn, $_POST['condition']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $year = (int)$_POST['year'];
    $seller_id = $_SESSION['user_id'];
    
    if($title && $description && $price){
        // Prepared statements are recommended, but following your existing syntax:
        $sql = "INSERT INTO tblClothes (seller_id, title, description, price, brand, size, condition_grade, category, year, status) 
                VALUES ('$seller_id', '$title', '$description', '$price', '$brand', '$size', '$condition', '$category', '$year', 'pending')";
        
        if(mysqli_query($conn, $sql)){
            $message = "Your masterpiece has been submitted for review by our curators.";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Listing - Pastimes Atelier</title>
    <link rel="stylesheet" href="../styles/style.css">
    <style>
        :root {
            --gold: #c5a059;
            --dark: #1a1a1a;
            --cream: #f4f1ea;
            --border: #e8e4db;
        }

        body { background-color: #fdfcf9; color: var(--dark); }

        .create-listing-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .zero-fees-banner {
            background: var(--dark);
            color: white;
            text-align: center;
            padding: 12px;
            letter-spacing: 3px;
            font-size: 0.75rem;
            margin-bottom: 30px;
        }

        .form-sections {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 50px;
        }

        /* Image & AI Sidebar */
        .image-upload-area {
            border: 1px dashed var(--gold);
            background: white;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            margin-bottom: 25px;
        }

        .ai-suggestion {
            background: var(--cream);
            padding: 25px;
            border-left: 3px solid var(--gold);
        }

        .suggestion-label {
            font-size: 0.65rem;
            font-weight: bold;
            color: #27ae60;
            letter-spacing: 1px;
        }

        /* Form Styling */
        .listing-form label {
            display: block;
            text-transform: uppercase;
            font-size: 0.7rem;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .listing-form input, .listing-form select, .listing-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            background: white;
            margin-bottom: 20px;
            font-family: inherit;
        }

        .form-row { display: flex; gap: 20px; }
        .half { flex: 1; }

        .earnings-preview {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }

        .earnings-amount span {
            font-size: 1.5rem;
            font-weight: 300;
            color: var(--gold);
        }

        .btn-primary {
            background: var(--dark);
            color: white;
            border: none;
            padding: 15px 40px;
            letter-spacing: 2px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary:hover { background: var(--gold); }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--dark);
            padding: 15px 40px;
            margin-left: 10px;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            .form-sections { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                 <a href="marketplace.php" style="text-decoration: none; color: inherit;">
                <h1>Pastimes</h1>
                <span style="font-size: 0.7rem; color: var(--gold); letter-spacing: 2px;">THE SELLER ATELIER</span>
            </div>
            <div class="nav-links">
                <a href="../marketplace.php">Gallery</a>
                <a href="my_atelier.php">Dashboard</a>
                 <!-- Contact Admin Link -->
                    <a href="contact_admin.php" class="nav-contact">📧 Contact Admin</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="create-listing-container">
        <div class="zero-fees-banner">✨ ZERO SELLER FEES • KEEP 100% OF YOUR SALE ✨</div>
        
        <?php if($message): ?>
            <div class="alert alert-success" style="padding: 15px; background: #eafff2; border: 1px solid #27ae60; margin-bottom: 20px; color: #27ae60;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="listing-form">
            <div class="form-sections">
                <!-- Left Sidebar: Visuals & Intelligence -->
                <div class="visual-sidebar">
                    <div class="image-upload-area">
                        <div class="image-placeholder">
                            <span style="font-size: 2rem;">📷</span>
                            <p style="text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px;">Primary Image Set</p>
                            <small style="color: #888;">Tap to edit gallery angles</small>
                        </div>
                    </div>
                    
                    <div class="ai-suggestion">
                        <p class="suggestion-label">● CURATOR AI ANALYSIS COMPLETE</p>
                        <h4 style="margin: 10px 0; font-weight: 400;">Suggested Details</h4>
                        <div class="suggestion-content" style="font-size: 0.85rem; line-height: 1.6;">
                            <p><strong>MATCH:</strong> Burberry Heritage Line (94% confidence)</p>
                            <p><strong>VALUATION:</strong> R12,500 — R14,200</p>
                            <p style="color: #666; font-style: italic;">"Based on current gallery trends, items with 'Heritage' in the title sell 30% faster."</p>
                        </div>
                    </div>
                </div>
                
                <!-- Right Main: Data Entry -->
                <div class="data-entry">
                    <div class="form-group">
                        <label for="title">Masterpiece Title *</label>
                        <input type="text" name="title" id="title" required placeholder="e.g., 1994 Archive Burberry Trench Coat">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="brand">Brand *</label>
                            <input type="text" name="brand" id="brand" required placeholder="Burberry">
                        </div>
                        <div class="form-group half">
                            <label for="size">Size *</label>
                            <input type="text" name="size" id="size" required placeholder="UK 40 / US 38">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="condition">Condition Grade *</label>
                            <select name="condition" id="condition" required>
                                <option value="Mint">Mint (Unworn)</option>
                                <option value="Excellent" selected>Excellent (Minimal wear)</option>
                                <option value="Very Good">Very Good (Minor flaws)</option>
                                <option value="Loved">Loved (Visible heritage)</option>
                            </select>
                        </div>
                        <div class="form-group half">
                            <label for="category">Category *</label>
                            <input type="text" name="category" id="category" required placeholder="Outerwear">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="year">Circa (Year)</label>
                            <input type="number" name="year" id="year" placeholder="1994">
                        </div>
                        <div class="form-group half">
                            <label for="price">Listing Price (ZAR) *</label>
                            <input type="number" step="0.01" name="price" id="price" required placeholder="12950">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">The Piece's Story *</label>
                        <textarea name="description" id="description" rows="6" required placeholder="Describe the heritage, fabric, and any unique character..."></textarea>
                    </div>
                    
                    <div class="earnings-preview">
                        <div>
                            <p style="margin:0; font-weight: bold; font-size: 0.8rem;">ESTIMATED EARNINGS</p>
                            <small style="color: #27ae60;">PROMOTION: 0% COMMISSION FEE</small>
                        </div>
                        <div class="earnings-amount">
                            <span id="earnings_display">R0.00</span>
                        </div>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 30px;">
                        <button type="submit" name="publish" class="btn-primary">PUBLISH TO GALLERY</button>
                        <button type="button" class="btn-secondary">SAVE TO DRAFTS</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        const priceInput = document.getElementById('price');
        const earningsDisplay = document.getElementById('earnings_display');
        
        priceInput.addEventListener('input', function() {
            let value = parseFloat(this.value) || 0;
            earningsDisplay.textContent = 'R' + value.toLocaleString(undefined, {minimumFractionDigits: 2});
        });
    </script>
</body>
</html>