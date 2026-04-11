<?php
include '../config.php';
$error = "";

// 1. Check if the cookie exists to pre-fill the username
$saved_username = isset($_COOKIE['remember_username']) ? $_COOKIE['remember_username'] : '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']); // Check if the checkbox was ticked

    if (!empty($username) && !empty($password)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                
                // --- COOKIE USAGE LOGIC ADDED HERE ---
                if ($remember) {
                    // Set cookie for 30 days (86400 seconds * 30)
                    setcookie("remember_username", $username, time() + (86400 * 30), "/");
                } else {
                    // If unchecked, delete the cookie by setting expiration to the past
                    setcookie("remember_username", "", time() - 3600, "/");
                }
                // -------------------------------------

                // Set session variables (Session-based access control)
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; 

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../admin/admin_dashboard.php");
                } else {
                    header("Location: ../dashboard.php");
                }
                exit();
            } else { 
                $error = "Incorrect password."; 
            }
        } else { 
            $error = "Username not found."; 
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CCMS</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <style>
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <div class="auth-left">
            <div class="brand-badge">CCMS Portal</div>
            <h1 style="font-size: 2.2rem;">Co-curricular Management System</h1>
            <p>Welcome to the centralized portal for all your student activities.</p>
            <ul>
                <li>📅 Track events and formal programmes</li>
                <li>👥 Manage club memberships and roles</li>
                <li>⏱️ Record merit contribution hours</li>
                <li>🏆 Store achievements and recognitions</li>
            </ul>
        </div>
        
        <div class="auth-right">
            <div class="auth-card">
                <h2>Welcome Back</h2>
                <p class="auth-subtitle">Log in to your account</p>
                
                <?php if($error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($saved_username); ?>" required>
                    </div>
                    
                    <div class="input-group" style="margin-bottom: 1rem;">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember" <?php if($saved_username) echo 'checked'; ?>>
                        <label for="remember" style="margin: 0; font-weight: normal; cursor: pointer;">Remember my username</label>
                    </div>
                    
                    <button type="submit" name="login" class="btn-primary full-btn">Login</button>
                </form>
                
                <div class="switch-auth">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>