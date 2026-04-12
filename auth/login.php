<?php
include '../config.php';

$error = "";
$saved_identifier = isset($_COOKIE['remember_username']) ? $_COOKIE['remember_username'] : '';

if (isset($_POST['login'])) {
    $login_identifier = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($login_identifier === '' || $password === '') {
        $error = "Please fill in all fields.";
    } else {
        $sql = "
            SELECT *
            FROM users
            WHERE (role = 'admin' AND username = ?)
               OR (role = 'student' AND (email = ? OR student_id = ?))
            LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $login_identifier, $login_identifier, $login_identifier);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    if ($remember) {
                        setcookie("remember_username", $login_identifier, time() + (86400 * 30), "/");
                    } else {
                        setcookie("remember_username", "", time() - 3600, "/");
                    }

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    if ($user['role'] === 'admin') {
                        header("Location: ../admin/admin_dashboard.php");
                    } else {
                        header("Location: ../dashboard.php");
                    }
                    exit();
                }

                $error = "Incorrect password.";
            } else {
                $error = "Account not found.";
            }
        } else {
            $error = "Login failed. Please try again.";
        }
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

                <?php if ($error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group">
                        <label>Email / Student ID</label>
                        <input
                            type="text"
                            name="username"
                            placeholder="Admin username or student email/student ID"
                            value="<?php echo htmlspecialchars($saved_identifier); ?>"
                            required>
                    </div>

                    <div class="input-group" style="margin-bottom: 1rem;">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember" <?php if ($saved_identifier) echo 'checked'; ?>>
                        <label for="remember" style="margin: 0; font-weight: normal; cursor: pointer;">Remember my login</label>
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
