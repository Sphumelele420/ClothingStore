<?php
session_start();
include_once "../DBConn.php";

// If already logged in as admin, redirect to dashboard
if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'){
    header("Location: dashboard.php");
    exit();
}

$error = '';

if(isset($_POST['admin_login'])){
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    $result = mysqli_query($conn, "SELECT * FROM tblUser WHERE email='$email' AND role='admin'");
    
    if(mysqli_num_rows($result) == 1){
        $row = mysqli_fetch_assoc($result);
        
        if(password_verify($password, $row['password'])){
            if($row['verification_status'] == 'verified'){
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = 'admin';
                $_SESSION['verification_status'] = $row['verification_status'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Your admin account is pending verification.";
            }
        } else {
            $error = "Invalid admin credentials.";
        }
    } else {
        $error = "Admin account not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Pastimes</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #c44536;
            --red-dark: #a33a2c;
            --dark: #1a1a1a;
            --cream: #f5f0e8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--cream);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-login-container {
            max-width: 450px;
            width: 100%;
            margin: 2rem;
        }

        .admin-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1);
            border: 1px solid #e8e4db;
        }

        .admin-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .admin-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--dark);
        }

        .admin-badge {
            display: inline-block;
            background: var(--red);
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 20px;
            font-size: 0.7rem;
            letter-spacing: 2px;
            margin-bottom: 1rem;
        }

        .admin-header h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .admin-header p {
            color: #888;
            font-size: 0.8rem;
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
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(196, 69, 54, 0.1);
        }

        .btn-admin-login {
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

        .btn-admin-login:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }

        .error-message {
            background: #fee;
            color: #d9534f;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            text-align: center;
            font-size: 0.8rem;
            border-left: 3px solid #d9534f;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link a {
            color: var(--red);
            text-decoration: none;
            font-size: 0.8rem;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-card">
            <div class="admin-header">
                <span class="admin-badge">ADMINISTRATOR ACCESS</span>
                <h1>Pastimes</h1>
                <h2>Admin Portal</h2>
                <p>Enter your administrator credentials</p>
            </div>

            <?php if($error): ?>
                <div class="error-message">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>ADMIN EMAIL</label>
                    <input type="email" name="email" placeholder="admin@pastimes.com" required>
                </div>
                <div class="form-group">
                    <label>ADMIN PASSWORD</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" name="admin_login" class="btn-admin-login">Access Dashboard</button>
            </form>

            <div class="back-link">
                <a href="../login.php">← Back to User Login</a>
            </div>
        </div>
    </div>
</body>
</html>