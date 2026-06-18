<?php
include_once "DBConn.php";

$message = '';
$error = '';
$form_data = ['full_name' => '', 'email' => '', 'role' => 'buyer'];

if(isset($_POST['register'])){
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    $form_data = ['full_name' => $full_name, 'email' => $email, 'role' => $role];
    
    if(empty($full_name) || empty($email) || empty($password)){
        $error = "All fields are required.";
    } elseif(strlen($password) < 8){
        $error = "Password must be at least 8 characters.";
    } else {
        $username = strtolower(str_replace(' ', '_', $full_name));
        $check = mysqli_query($conn, "SELECT * FROM tblUser WHERE email='$email'");
        
        if(mysqli_num_rows($check) > 0){
            $error = "Email already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO tblUser (full_name, username, email, password, role, verification_status) 
                    VALUES ('$full_name', '$username', '$email', '$hashed', '$role', 'pending')";
            
            if(mysqli_query($conn, $sql)){
                $message = "Registration successful! Please wait for admin verification.";
                $form_data = ['full_name' => '', 'email' => '', 'role' => 'buyer'];
            } else {
                $error = "Registration failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Pastimes Digital Atelier</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --cream: #f5f0e8;
            --error: #d9534f;
            --success: #2e7d64;
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
        .register-wrapper {
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
        .register-image {
            flex: 1;
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            min-height: 700px;
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

        /* Decorative pattern */
        .register-image::before {
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
        .register-form-container {
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

        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: white;
        }

        .form-group input:focus, 
        .form-group select:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(196, 69, 54, 0.1);
        }

        /* Password wrapper with show/hide button */
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-wrapper input {
            width: 100%;
            padding-right: 75px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            font-size: 0.75rem;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
        }
        
        .toggle-password:hover {
            color: var(--red);
        }

        small {
            display: block;
            margin-top: 5px;
            font-size: 0.65rem;
            color: #888;
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

        .alert {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            text-align: center;
            font-size: 0.8rem;
        }

        .alert-success {
            background: #e8f5e9;
            color: var(--success);
            border-left: 3px solid var(--success);
        }

        .alert-error {
            background: #fee;
            color: var(--error);
            border-left: 3px solid var(--error);
        }

        @media (max-width: 900px) {
            .register-wrapper {
                flex-direction: column;
                margin: 1rem;
            }
            .register-image {
                min-height: 300px;
                padding: 2rem;
            }
            .register-form-container {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>

    <div class="register-wrapper">
        <!-- Left Side - Image/Quote Section -->
        <div class="register-image">
            <div class="image-content">
                <div class="logo-icon">✍️</div>
                <h2>Join the Atelier</h2>
                <p>Become part of a curated community of heritage collectors and artisans.</p>
                <div class="quote">
                    "Every piece has a story.<br>Start writing yours today."
                </div>
            </div>
        </div>

        <!-- Right Side - Registration Form -->
        <div class="register-form-container">
            <div class="auth-header">
                <h1>Pastimes</h1>
                <span class="auth-subtitle">THE DIGITAL ATELIER</span>
                <h2>Create an account</h2>
                <p>Join our curated community of heritage fashion enthusiasts.</p>
            </div>
            
            <?php if($message): ?>
                <div class="alert alert-success">
                    ✅ <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>FULL NAME</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name']); ?>" placeholder="Alex Heritage" required>
                </div>

                <div class="form-group">
                    <label>EMAIL ADDRESS</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" placeholder="alex@atelier.com" required>
                </div>

                <div class="form-group">
                    <label>PASSWORD</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Create a secure password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">Show</button>
                    </div>
                    <small>Minimum 8 characters required</small>
                </div>

                <div class="form-group">
                    <label>SELECT YOUR PATH</label>
                    <select name="role">
                        <option value="buyer" <?php echo $form_data['role'] == 'buyer' ? 'selected' : ''; ?>>Buyer - Discover treasures</option>
                        <option value="seller" <?php echo $form_data['role'] == 'seller' ? 'selected' : ''; ?>>Seller - Archive collections</option>
                    </select>
                </div>

                <button type="submit" name="register" class="btn-primary">Complete Registration</button>
            </form>
            
            <div class="auth-footer">
                Already a member? <a href="login.php">Log in</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = 'Hide';
                toggleBtn.style.color = 'var(--red)';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = 'Show';
                toggleBtn.style.color = '#888';
            }
        }
    </script>

</body>
</html>