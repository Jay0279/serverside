<?php
include '../../config.php';

function remove_invalid_auto_achievements($conn, $user_id)
{
    $hours_rules = [
        10 => 'Active Contributor Award',
        25 => 'Dedicated Service Award',
        50 => 'Excellence in Service Award',
        80 => 'Outstanding Volunteer Award',
    ];

    $points_rules = [
        20 => 'Bronze Engagement Award',
        50 => 'Silver Engagement Award',
        80 => 'Gold Engagement Award',
        120 => 'Outstanding Student Involvement Award',
    ];

    $hours_total = 0;
    $hours_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(hours_contributed), 0) AS total_hours FROM merits WHERE user_id = ? AND status = 'Completed'");
    mysqli_stmt_bind_param($hours_stmt, 'i', $user_id);
    mysqli_stmt_execute($hours_stmt);
    $hours_result = mysqli_stmt_get_result($hours_stmt);
    if ($hours_row = mysqli_fetch_assoc($hours_result)) {
        $hours_total = (float) $hours_row['total_hours'];
    }
    mysqli_stmt_close($hours_stmt);

    foreach ($hours_rules as $threshold => $title) {
        if ($hours_total < $threshold) {
            $delete_stmt = mysqli_prepare($conn, "DELETE FROM achievements WHERE user_id = ? AND title = ? AND achievement_source = 'auto_merit_hours'");
            mysqli_stmt_bind_param($delete_stmt, 'is', $user_id, $title);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }
    }

    $points_total = 0;
    $points_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(merit_points), 0) AS total_points FROM events WHERE user_id = ? AND event_status = 'Completed'");
    mysqli_stmt_bind_param($points_stmt, 'i', $user_id);
    mysqli_stmt_execute($points_stmt);
    $points_result = mysqli_stmt_get_result($points_stmt);
    if ($points_row = mysqli_fetch_assoc($points_result)) {
        $points_total = (int) $points_row['total_points'];
    }
    mysqli_stmt_close($points_stmt);

    foreach ($points_rules as $threshold => $title) {
        if ($points_total < $threshold) {
            $delete_stmt = mysqli_prepare($conn, "DELETE FROM achievements WHERE user_id = ? AND title = ? AND achievement_source = 'auto_merit_points'");
            mysqli_stmt_bind_param($delete_stmt, 'is', $user_id, $title);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_id']) || !ctype_digit($_POST['event_id'])) {
    header('Location: events.php?msg=error');
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = (int) $_POST['event_id'];

$success = false;
$achievement_files = [];

mysqli_begin_transaction($conn);

try {
    $event_check_sql = 'SELECT id FROM events WHERE id = ? AND user_id = ? LIMIT 1';
    $event_check_stmt = mysqli_prepare($conn, $event_check_sql);
    mysqli_stmt_bind_param($event_check_stmt, 'ii', $event_id, $user_id);
    mysqli_stmt_execute($event_check_stmt);
    $event_exists = mysqli_fetch_assoc(mysqli_stmt_get_result($event_check_stmt));
    mysqli_stmt_close($event_check_stmt);

    if (!$event_exists) {
        throw new Exception('Event not found.');
    }

    $achievement_file_sql = 'SELECT evidence_file FROM achievements WHERE event_id = ? AND user_id = ?';
    $achievement_file_stmt = mysqli_prepare($conn, $achievement_file_sql);
    mysqli_stmt_bind_param($achievement_file_stmt, 'ii', $event_id, $user_id);
    mysqli_stmt_execute($achievement_file_stmt);
    $achievement_file_result = mysqli_stmt_get_result($achievement_file_stmt);

    while ($achievement_row = mysqli_fetch_assoc($achievement_file_result)) {
        if (!empty($achievement_row['evidence_file'])) {
            $achievement_files[] = $achievement_row['evidence_file'];
        }
    }
    mysqli_stmt_close($achievement_file_stmt);

    $delete_achievements_sql = 'DELETE FROM achievements WHERE event_id = ? AND user_id = ?';
    $delete_achievements_stmt = mysqli_prepare($conn, $delete_achievements_sql);
    mysqli_stmt_bind_param($delete_achievements_stmt, 'ii', $event_id, $user_id);
    mysqli_stmt_execute($delete_achievements_stmt);
    mysqli_stmt_close($delete_achievements_stmt);

    $delete_merits_sql = 'DELETE FROM merits WHERE event_id = ? AND user_id = ?';
    $delete_merits_stmt = mysqli_prepare($conn, $delete_merits_sql);
    mysqli_stmt_bind_param($delete_merits_stmt, 'ii', $event_id, $user_id);
    mysqli_stmt_execute($delete_merits_stmt);
    mysqli_stmt_close($delete_merits_stmt);

    $delete_event_sql = 'DELETE FROM events WHERE id = ? AND user_id = ?';
    $delete_event_stmt = mysqli_prepare($conn, $delete_event_sql);
    mysqli_stmt_bind_param($delete_event_stmt, 'ii', $event_id, $user_id);
    mysqli_stmt_execute($delete_event_stmt);
    $success = mysqli_stmt_affected_rows($delete_event_stmt) > 0;
    mysqli_stmt_close($delete_event_stmt);

    if (!$success) {
        throw new Exception('Failed to delete event.');
    }

    remove_invalid_auto_achievements($conn, $user_id);

    mysqli_commit($conn);

    foreach ($achievement_files as $achievement_file) {
        $file_path = '../achievement_tracker/uploads/' . $achievement_file;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $success = false;
}

header('Location: events.php?msg=' . ($success ? 'deleted' : 'error'));
exit();
