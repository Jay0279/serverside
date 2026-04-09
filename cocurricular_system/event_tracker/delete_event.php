<?php
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_id']) || !ctype_digit($_POST['event_id'])) {
    header('Location: events.php?msg=error');
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = (int) $_POST['event_id'];

$sql = 'DELETE FROM events WHERE id = ? AND user_id = ?';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $event_id, $user_id);
$success = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: events.php?msg=' . ($success ? 'deleted' : 'error'));
exit();
