<?php
include 'config.php';

$username = "ADMIN";
$password = "ADMIN";
$email = "admin@ccms.edu";
$role = "admin";

// Encrypt the password so it works with your login system
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password, $role);

if (mysqli_stmt_execute($stmt)) {
    echo "<h1>Success!</h1><p>Admin account created. You can now delete this file and try logging in.</p>";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>