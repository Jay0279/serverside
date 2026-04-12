<?php
include '../config.php';

echo "<h2>System Diagnostic...</h2>";

$check_role_stmt = mysqli_prepare($conn, "SHOW COLUMNS FROM users LIKE 'role'");
mysqli_stmt_execute($check_role_stmt);
$check_role_result = mysqli_stmt_get_result($check_role_stmt);

if (mysqli_num_rows($check_role_result) === 0) {
    $alter_role_sql = "ALTER TABLE users ADD role VARCHAR(20) NOT NULL DEFAULT 'student'";
    if (mysqli_query($conn, $alter_role_sql)) {
        echo "<p style='color: green;'>role column added to database successfully.</p>";
    } else {
        echo "<p style='color: red;'>Failed to add role column: " . htmlspecialchars(mysqli_error($conn)) . "</p>";
    }
} else {
    echo "<p style='color: blue;'>role column already exists.</p>";
}
mysqli_stmt_close($check_role_stmt);

$username = "ADMIN";
$password = "ADMIN";
$email = "admin@ccms.edu";
$role = "admin";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$check_admin_sql = "SELECT id FROM users WHERE username = ? LIMIT 1";
$check_admin_stmt = mysqli_prepare($conn, $check_admin_sql);
mysqli_stmt_bind_param($check_admin_stmt, "s", $username);
mysqli_stmt_execute($check_admin_stmt);
$check_admin_result = mysqli_stmt_get_result($check_admin_stmt);
$admin_exists = mysqli_fetch_assoc($check_admin_result);
mysqli_stmt_close($check_admin_stmt);

if ($admin_exists) {
    echo "<h1 style='color: orange;'>ADMIN account already exists in the database.</h1>";
} else {
    $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password, $role);
        if (mysqli_stmt_execute($stmt)) {
            echo "<h1 style='color: green;'>SUCCESS! Admin account created.</h1>";
            echo "<p>You can now go to the login page and use ADMIN / ADMIN.</p>";
        } else {
            echo "<h1 style='color: red;'>Error inserting admin: " . htmlspecialchars(mysqli_error($conn)) . "</h1>";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<h1 style='color: red;'>SQL Error: " . htmlspecialchars(mysqli_error($conn)) . "</h1>";
    }
}
?>
