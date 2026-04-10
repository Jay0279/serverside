<?php
include '../config.php';
$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; // Save their role

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../admin/admin_dashboard.php"); // Updated Path!
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
    <link rel="stylesheet" href="../style.css">
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
                        <input type="text" name="username" placeholder="Enter your username" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
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