<?php
include '../config.php';

$error = "";
$success = "";

$username = trim($_POST['username'] ?? '');
$student_id = trim($_POST['student_id'] ?? '');
$email = trim($_POST['email'] ?? '');

if (isset($_POST['register'])) {
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($username === '' || $student_id === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $check_sql = "
            SELECT username, email, student_id
            FROM users
            WHERE username = ? OR email = ? OR student_id = ?
            LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $check_sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $username, $email, $student_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $existing_user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($existing_user) {
                if ($existing_user['student_id'] === $student_id) {
                    $error = "Student ID already exists.";
                } elseif ($existing_user['email'] === $email) {
                    $error = "Email already exists.";
                } else {
                    $error = "Username already exists.";
                }
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'student';
                $insert_sql = "INSERT INTO users (username, student_id, email, password, role) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_sql);

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssss", $username, $student_id, $email, $hashed_password, $role);

                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Registration successful! You can now login.";
                        $username = '';
                        $student_id = '';
                        $email = '';
                    } else {
                        $error = "Registration failed. Please try again.";
                    }

                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | CCMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <div class="auth-left">
            <div class="brand-badge">CCMS Portal</div>
            <h1 style="font-size: 2.2rem;">Join the System</h1>
            <p>Create your centralized student account to manage all your co-curricular records in one place.</p>
            <ul>
                <li>📅 Event & Programme tracking</li>
                <li>👥 Club membership management</li>
                <li>⏱️ Merit hours logging</li>
                <li>🏆 Achievement & Award records</li>
            </ul>
        </div>

        <div class="auth-right">
            <div class="auth-card">
                <h2>Register</h2>
                <p class="auth-subtitle">Create your student account</p>

                <?php if (!empty($error)): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" placeholder="Fill your username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Student ID</label>
                        <input type="text" name="student_id" placeholder="Enter your student ID" value="<?php echo htmlspecialchars($student_id); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Minimum 6 characters" required>
                    </div>

                    <div class="input-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                    </div>

                    <button type="submit" name="register" class="btn-primary full-btn">Register Account</button>
                </form>

                <div class="switch-auth">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
