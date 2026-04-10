<?php
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];

    // First, get the filename so we can delete the actual file
    $check_sql = "SELECT evidence_file FROM achievements WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Delete the physical file from the uploads folder
        if (!empty($row['evidence_file']) && file_exists("uploads/" . $row['evidence_file'])) {
            unlink("uploads/" . $row['evidence_file']);
        }
        
        // Then, delete the record from the database
        $delete_sql = "DELETE FROM achievements WHERE id = ? AND user_id = ?";
        $del_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($del_stmt, "ii", $id, $user_id);
        mysqli_stmt_execute($del_stmt);
    }
}

header("Location: achievements.php");
exit();
?>