<?php
session_start();
include_once "DBConn.php";

$error = '';
$sticky_email = '';

if(isset($_POST['login'])){
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $sticky_email = $email;
    
    $result = mysqli_query($conn, "SELECT * FROM tblUser WHERE email='$email'");
    
    if(mysqli_num_rows($result) == 1){
        $row = mysqli_fetch_assoc($result);
        
        if(password_verify($password, $row['password'])){
            if($row['verification_status'] == 'verified'){
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['verification_status'] = $row['verification_status'];
                
                if($row['role'] == 'admin'){
                    header("Location: admin/dashboard.php");
                    exit();
                } 
                elseif($row['role'] == 'seller'){
                    header("Location: seller/my_atelier.php");
                    exit();
                } 
                else {
                    header("Location: marketplace.php");
                    exit();
                }
            } else {
                $error = "Your account is pending verification. Please wait for admin approval.";
            }
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "User not found. Please register first.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pastimes Digital Atelier</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --cream: #f5f0e8;
            --error: #d9534f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--cream);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Two Column Layout */
        .login-wrapper {
            display: flex;
            max-width: 1200px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1);
            margin: 2rem;
        }

        /* Left Side - Image */
        .login-image {
            flex: 1;
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            min-height: 600px;
        }

        .image-content {
            text-align: center;
            color: white;
            position: relative;
            z-index: 2;
        }

        .image-content .logo-icon {
            font-size: 4rem;
            margin-bottom: 2rem;
        }

        .image-content h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            letter-spacing: -0.5px;
        }

        .image-content p {
            font-size: 0.9rem;
            color: #b0b0b0;
            line-height: 1.6;
            max-width: 280px;
            margin: 0 auto;
        }

        .image-content .quote {
            margin-top: 2rem;
            font-style: italic;
            font-size: 0.85rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1.5rem;
        }

        /* Decorative elements */
        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%23c44536" opacity="0.15" d="M50,0 L100,50 L50,100 L0,50 Z"/></svg>') repeat;
            background-size: 60px;
            opacity: 0.3;
        }

        /* Right Side - Form */
        .login-form-container {
            flex: 1;
            padding: 3rem;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: -0.5px;
            color: var(--dark);
            margin: 0;
        }

        .auth-subtitle {
            font-family: 'Inter', sans-serif;
            display: block;
            font-size: 0.65rem;
            letter-spacing: 4px;
            color: var(--red);
            margin-top: 0.25rem;
            margin-bottom: 1.5rem;
        }

        .auth-header h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .auth-header p {
            font-size: 0.8rem;
            color: #888;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            color: #555;
            text-transform: uppercase;
        }

        .form-group input {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(196, 69, 54, 0.1);
        }

        .btn-primary {
            background: var(--red);
            color: white;
            padding: 0.9rem;
            border: none;
            border-radius: 30px;
            width: 100%;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 1px;
            transition: all 0.3s;
            margin-top: 0.5rem;
        }

        .btn-primary:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }

        /* Admin Button Styles */
        .admin-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e8e4db;
            text-align: center;
        }

        .btn-admin {
            background: transparent;
            color: var(--dark);
            border: 1px solid var(--border);
            padding: 0.7rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s;
            width: 100%;
            text-decoration: none;
            display: inline-block;
        }

        .btn-admin:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(196, 69, 54, 0.05);
        }

        .admin-note {
            font-size: 0.65rem;
            color: #999;
            margin-top: 0.5rem;
            text-align: center;
        }

        .auth-divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }

        .auth-divider::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e8e4db;
            z-index: 1;
        }

        .auth-divider span {
            background: white;
            padding: 0 12px;
            position: relative;
            z-index: 2;
            font-size: 0.65rem;
            letter-spacing: 1px;
            color: #aaa;
        }

        .btn-google {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 30px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-google:hover {
            border-color: var(--red);
            background: #fafafa;
        }

        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: #666;
        }

        .auth-footer a {
            color: var(--red);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .error-message {
            background: #fee;
            color: var(--error);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            text-align: center;
            font-size: 0.8rem;
            border-left: 3px solid var(--error);
        }

        .demo-credentials {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #fcf9f2;
            border-radius: 8px;
            font-size: 0.7rem;
            border-top: 1px solid #e8e4db;
        }

        .demo-credentials h4 {
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--red);
            letter-spacing: 1px;
        }

        .demo-credentials p {
            margin: 4px 0;
            color: #666;
        }

        .demo-credentials strong {
            color: var(--dark);
        }

        @media (max-width: 900px) {
            .login-wrapper {
                flex-direction: column;
                margin: 1rem;
            }
            .login-image {
                min-height: 300px;
                padding: 2rem;
            }
            .login-form-container {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <!-- Left Side - Image/Quote Section -->
        <div class="login-image">
            <div class="image-content">
                <div class="logo-icon">🖋️</div>
                <h2>Every piece has a history.</h2>
                <p>Start writing yours today.</p>
                <div class="quote">
                    "Curating history,<br>one garment at a time."
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-form-container">
            <div class="auth-header">
                <h1>Pastimes</h1>
                <span class="auth-subtitle">THE DIGITAL ATELIER</span>
                <h2>Welcome Back</h2>
                <p>Enter your credentials to access your archive.</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>EMAIL ADDRESS</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($sticky_email); ?>" placeholder="collector@pastimes.com" required>
                </div>
                <div class="form-group">
                    <label>PASSWORD</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" name="login" class="btn-primary">Login</button>
            </form>
            
            <div class="auth-divider">
                <span>OR CONTINUE WITH</span>
            </div>
            
            <button class="btn-google">
                <span>G</span> Continue with Google
            </button>
            
            <div class="auth-footer">
                New to the collection? <a href="register.php">Register for an account</a>
            </div>
            
            <!-- Admin Access Section - Requirement #4 -->
            <div class="admin-section">
                <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                    <!-- If admin is already logged in -->
                    <a href="admin/dashboard.php" class="btn-admin">🔐 ADMIN DASHBOARD</a>
                    <div class="admin-note">You are logged in as an administrator.</div>
                <?php else: ?>
                    <!-- If no admin logged in, show admin login button -->
                    <a href="admin/login.php" class="btn-admin">🔑 ADMIN ACCESS</a>
                    <div class="admin-note">Administrators click here for secure access</div>
                <?php endif; ?>
            </div>
            
            <div class="demo-credentials">
                <h4>🎭 DEMO CREDENTIALS</h4>
                <p><strong>Admin:</strong> admin@pastimes.com / admin123</p>
                <p><strong>Seller:</strong> eliza@pastimes.com / admin123</p>
                <p><strong>Buyer:</strong> john@example.com / admin123</p>
            </div>
        </div>
    </div>

</body>
</html>