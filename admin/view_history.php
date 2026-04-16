<?php
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

$username = $_SESSION['username'];

function history_file_url($filename)
{
    return "../cocurricular_system/achievement_tracker/uploads/" . rawurlencode($filename);
}

function fetch_count_history($conn, $sql, $types = '', ...$params)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0);
}

function fetch_rows_history($conn, $sql, $types = '', ...$params)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

function student_identity_text($student_id, $email)
{
    return trim(($student_id ?? '') . ' | ' . ($email ?? ''));
}

$tab = $_GET['tab'] ?? 'events';

$upcoming_status = 'Upcoming';
$pending_status = 'Pending';
$pending_verification = 'Pending Verification';

$pending_events = fetch_count_history($conn, "SELECT COUNT(*) AS total FROM events WHERE event_status = ?", "s", $upcoming_status);
$pending_clubs = fetch_count_history($conn, "SELECT COUNT(*) AS total FROM clubs WHERE review_status = ?", "s", $pending_status);
$pending_achievements = fetch_count_history($conn, "SELECT COUNT(*) AS total FROM achievements WHERE status = ?", "s", $pending_verification);
$pending_merits = fetch_count_history($conn, "SELECT COUNT(*) AS total FROM merits WHERE status = ?", "s", $pending_status);

$total_pending = $pending_events + $pending_clubs + $pending_achievements + $pending_merits;

$eventHistoryResult = fetch_rows_history(
    $conn,
    "
        SELECT e.*, u.student_id, u.email, c.club_name
        FROM events e
        JOIN users u ON e.user_id = u.user_id
        LEFT JOIN clubs c ON e.club_id = c.club_id
        WHERE e.event_status IN (?, ?)
          AND e.reviewed_at IS NOT NULL
        ORDER BY e.reviewed_at DESC
    ",
    "ss",
    'Completed',
    'Cancelled'
);

$clubHistoryResult = fetch_rows_history(
    $conn,
    "
        SELECT c.*, u.student_id, u.email
        FROM clubs c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.review_status IN (?, ?)
          AND c.reviewed_at IS NOT NULL
        ORDER BY c.reviewed_at DESC
    ",
    "ss",
    'Approved',
    'Rejected'
);

$achievementHistoryResult = fetch_rows_history(
    $conn,
    "
        SELECT a.*, u.student_id, u.email, e.event_title
        FROM achievements a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN events e ON a.event_id = e.id
        WHERE a.status IN (?, ?)
          AND a.reviewed_at IS NOT NULL
        ORDER BY a.reviewed_at DESC
    ",
    "ss",
    'Completed',
    'Rejected'
);

$meritHistoryResult = fetch_rows_history(
    $conn,
    "
        SELECT m.*, u.student_id, u.email, e.event_title
        FROM merits m
        JOIN users u ON m.user_id = u.user_id
        LEFT JOIN events e ON m.event_id = e.id
        WHERE m.status IN (?, ?)
          AND m.reviewed_at IS NOT NULL
        ORDER BY m.reviewed_at DESC
    ",
    "ss",
    'Completed',
    'Rejected'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View History | CCMS Admin</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
</head>
<body class="main-body">
    <div class="sidebar" style="background: #0f172a;">
        <div>
            <h2 style="color: #818cf8;">CCMS Admin</h2>
            <p class="sidebar-subtitle">Staff Portal</p>
        </div>

        <div class="nav-links">
            <a href="admin_dashboard.php">👥 User Management</a>
            <a href="verify_achievements.php">
                📥 Verification Inbox
                <?php if ($total_pending > 0): ?>
                    <span class="mini-badge"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="view_history.php" class="active">🕘 View History</a>
        </div>

        <a href="../auth/logout.php" class="logout-link" style="margin-top:auto;">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-glass" style="background: linear-gradient(120deg, #1e293b, #4338ca);">
            <p class="hero-label" style="color: #c7d2fe; margin-bottom: 0.5rem; display: block;">Admin Review Records</p>
            <h1>View History 🕘</h1>
            <p>See approved and rejected club, event, achievement, and merit records in one dedicated page.</p>
        </div>

        <div class="tab-bar">
            <a href="?tab=events" class="tab-btn <?php echo $tab === 'events' ? 'active' : ''; ?>">📅 Event History</a>
            <a href="?tab=clubs" class="tab-btn <?php echo $tab === 'clubs' ? 'active' : ''; ?>">👥 Club History</a>
            <a href="?tab=achievements" class="tab-btn <?php echo $tab === 'achievements' ? 'active' : ''; ?>">🏆 Achievement History</a>
            <a href="?tab=merits" class="tab-btn <?php echo $tab === 'merits' ? 'active' : ''; ?>">⏱️ Merit History</a>
        </div>

        <div class="panel">
            <?php if ($tab === 'events'): ?>
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <h2 style="color: var(--dark);">📅 Event History</h2>
                </div>

                <?php if ($eventHistoryResult && mysqli_num_rows($eventHistoryResult) > 0): ?>
                    <div class="history-list">
                        <?php while ($item = mysqli_fetch_assoc($eventHistoryResult)): ?>
                            <div class="history-item">
                                <div class="history-top">
                                    <div>
                                        <div class="history-title"><?php echo htmlspecialchars($item['event_title']); ?></div>
                                        <div class="history-meta">
                                            <strong>Student:</strong> <?php echo htmlspecialchars(student_identity_text($item['student_id'], $item['email'])); ?><br>
                                            <strong>Category:</strong> <?php echo htmlspecialchars($item['event_category']); ?><br>
                                            <strong>Organizer:</strong> <?php echo htmlspecialchars($item['organizer']); ?><br>
                                            <strong>Related Club:</strong> <?php echo !empty($item['club_name']) ? htmlspecialchars($item['club_name']) : '-'; ?><br>
                                            <strong>Event Date:</strong> <?php echo htmlspecialchars($item['event_date']); ?><br>
                                            <strong>Role:</strong> <?php echo htmlspecialchars($item['participation_role']); ?><br>
                                            <strong>Hours:</strong> <?php echo htmlspecialchars($item['event_hours']); ?><br>
                                            <strong>Reviewed At:</strong> <?php echo htmlspecialchars($item['reviewed_at']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($item['event_status'] === 'Completed'): ?>
                                            <span class="status-completed">Approved</span>
                                        <?php else: ?>
                                            <span class="status-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="history-remark">
                                    <strong>Admin Remark:</strong><br>
                                    <?php echo !empty($item['admin_remark']) ? nl2br(htmlspecialchars($item['admin_remark'])) : '-'; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No event history yet.</p>
                <?php endif; ?>

            <?php elseif ($tab === 'clubs'): ?>
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <h2 style="color: var(--dark);">👥 Club History</h2>
                </div>

                <?php if ($clubHistoryResult && mysqli_num_rows($clubHistoryResult) > 0): ?>
                    <div class="history-list">
                        <?php while ($item = mysqli_fetch_assoc($clubHistoryResult)): ?>
                            <div class="history-item">
                                <div class="history-top">
                                    <div>
                                        <div class="history-title"><?php echo htmlspecialchars($item['club_name']); ?></div>
                                        <div class="history-meta">
                                            <strong>Student:</strong> <?php echo htmlspecialchars(student_identity_text($item['student_id'], $item['email'])); ?><br>
                                            <strong>Category:</strong> <?php echo htmlspecialchars($item['club_category']); ?><br>
                                            <strong>Role:</strong> <?php echo htmlspecialchars($item['role_position']); ?><br>
                                            <strong>Membership Status:</strong> <?php echo htmlspecialchars($item['membership_status']); ?><br>
                                            <strong>Join Date:</strong> <?php echo htmlspecialchars($item['join_date']); ?><br>
                                            <strong>End Date:</strong> <?php echo !empty($item['end_date']) ? htmlspecialchars($item['end_date']) : '-'; ?><br>
                                            <strong>Reviewed At:</strong> <?php echo htmlspecialchars($item['reviewed_at']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($item['review_status'] === 'Approved'): ?>
                                            <span class="status-completed">Approved</span>
                                        <?php else: ?>
                                            <span class="status-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="history-remark">
                                    <strong>Student Remarks:</strong><br>
                                    <?php echo !empty($item['remarks']) ? nl2br(htmlspecialchars($item['remarks'])) : '-'; ?>
                                </div>

                                <div class="history-remark">
                                    <strong>Admin Remark:</strong><br>
                                    <?php echo !empty($item['admin_remark']) ? nl2br(htmlspecialchars($item['admin_remark'])) : '-'; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No club history yet.</p>
                <?php endif; ?>

            <?php elseif ($tab === 'achievements'): ?>
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <h2 style="color: var(--dark);">🏆 Achievement History</h2>
                </div>

                <?php if ($achievementHistoryResult && mysqli_num_rows($achievementHistoryResult) > 0): ?>
                    <div class="history-list">
                        <?php while ($item = mysqli_fetch_assoc($achievementHistoryResult)): ?>
                            <div class="history-item">
                                <div class="history-top">
                                    <div>
                                        <div class="history-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                        <div class="history-meta">
                                            <strong>Student:</strong> <?php echo htmlspecialchars(student_identity_text($item['student_id'], $item['email'])); ?><br>
                                            <strong>Related Event:</strong> <?php echo !empty($item['event_title']) ? htmlspecialchars($item['event_title']) : '-'; ?><br>
                                            <strong>Category:</strong> <?php echo htmlspecialchars($item['category']); ?><br>
                                            <strong>Level:</strong> <?php echo htmlspecialchars($item['level']); ?><br>
                                            <strong>Achievement Date:</strong> <?php echo htmlspecialchars($item['achievement_date']); ?><br>
                                            <strong>Reviewed At:</strong> <?php echo htmlspecialchars($item['reviewed_at']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($item['status'] === 'Completed'): ?>
                                            <span class="status-completed">Approved</span>
                                        <?php else: ?>
                                            <span class="status-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="history-remark">
                                    <strong>Admin Remark:</strong><br>
                                    <?php echo !empty($item['admin_remark']) ? nl2br(htmlspecialchars($item['admin_remark'])) : '-'; ?>
                                </div>

                                <?php if (!empty($item['evidence_file'])): ?>
                                    <a href="<?php echo htmlspecialchars(history_file_url($item['evidence_file'])); ?>" target="_blank" class="history-link">View Evidence File</a>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No achievement history yet.</p>
                <?php endif; ?>

            <?php elseif ($tab === 'merits'): ?>
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <h2 style="color: var(--dark);">⏱️ Merit History</h2>
                </div>

                <?php if ($meritHistoryResult && mysqli_num_rows($meritHistoryResult) > 0): ?>
                    <div class="history-list">
                        <?php while ($item = mysqli_fetch_assoc($meritHistoryResult)): ?>
                            <div class="history-item">
                                <div class="history-top">
                                    <div>
                                        <div class="history-title"><?php echo htmlspecialchars($item['activity_title']); ?></div>
                                        <div class="history-meta">
                                            <strong>Student:</strong> <?php echo htmlspecialchars(student_identity_text($item['student_id'], $item['email'])); ?><br>
                                            <strong>Related Event:</strong> <?php echo !empty($item['event_title']) ? htmlspecialchars($item['event_title']) : '-'; ?><br>
                                            <strong>Type:</strong> <?php echo htmlspecialchars($item['activity_type']); ?><br>
                                            <strong>Start Date:</strong> <?php echo htmlspecialchars($item['start_date']); ?><br>
                                            <strong>Hours:</strong> <?php echo htmlspecialchars($item['hours_contributed']); ?> hrs<br>
                                            <strong>Merit Points:</strong> <?php echo htmlspecialchars($item['merit_points']); ?><br>
                                            <strong>Reviewed At:</strong> <?php echo htmlspecialchars($item['reviewed_at']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($item['status'] === 'Completed'): ?>
                                            <span class="status-completed">Approved</span>
                                        <?php else: ?>
                                            <span class="status-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="history-remark">
                                    <strong>Admin Remark:</strong><br>
                                    <?php echo !empty($item['admin_remark']) ? nl2br(htmlspecialchars($item['admin_remark'])) : '-'; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No merit history yet.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
