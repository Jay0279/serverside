<?php
include '../config.php';

echo "<h2>System Diagnostic...</h2>";

// 1. Automatically check and add the 'role' column if you missed it
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
if(mysqli_num_rows($check_col) == 0) {
    $alter_sql = "ALTER TABLE users ADD role VARCHAR(20) NOT NULL DEFAULT 'student'";
    if (mysqli_query($conn, $alter_sql)) {
        echo "<p style='color: green;'>✅ 'role' column added to database successfully.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add 'role' column: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color: blue;'>✅ 'role' column already exists.</p>";
}

// 2. Create the Admin account
$username = "ADMIN";
$password = "ADMIN";
$email = "admin@ccms.edu";
$role = "admin";

// Encrypt password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if ADMIN already exists to prevent duplicates
$check_admin = mysqli_query($conn, "SELECT * FROM users WHERE username = 'ADMIN'");
if(mysqli_num_rows($check_admin) > 0) {
    echo "<h1 style='color: orange;'>⚠️ ADMIN account already exists in the database!</h1>";
} else {
    // Insert the new admin
    $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password, $role);
        if (mysqli_stmt_execute($stmt)) {
            echo "<h1 style='color: green;'>🎉 SUCCESS! Admin account created!</h1>";
            echo "<p>You can now go to the login page and use ADMIN / ADMIN.</p>";
        } else {
            echo "<h1 style='color: red;'>❌ Error inserting admin: " . mysqli_error($conn) . "</h1>";
        }
    } else {
        echo "<h1 style='color: red;'>❌ SQL Error: " . mysqli_error($conn) . "</h1>";
    }
}
?>