<?php
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$flash = '';
$flashClass = 'success';

function safe_evidence_url($filename)
{
    return "../cocurricular_system/achievement_tracker/uploads/" . rawurlencode($filename);
}

function fetch_rows_verify($conn, $sql, $types = '', ...$params)
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

function student_identity_verify($student_id, $email)
{
    return trim(($student_id ?? '') . ' | ' . ($email ?? ''));
}

/**
 * Auto-create milestone achievements based on:
 * 1) total approved merit hours
 * 2) total approved merit points from completed events
 */
function award_auto_achievements($conn, $user_id)
{
    $hours_rules = [
        10 => ['title' => 'Active Contributor Award', 'category' => 'Community Service', 'level' => 'University', 'source' => 'auto_merit_hours'],
        25 => ['title' => 'Dedicated Service Award', 'category' => 'Community Service', 'level' => 'University', 'source' => 'auto_merit_hours'],
        50 => ['title' => 'Excellence in Service Award', 'category' => 'Community Service', 'level' => 'University', 'source' => 'auto_merit_hours'],
        80 => ['title' => 'Outstanding Volunteer Award', 'category' => 'Community Service', 'level' => 'University', 'source' => 'auto_merit_hours'],
    ];

    $points_rules = [
        20  => ['title' => 'Bronze Engagement Award', 'category' => 'Participation', 'level' => 'University', 'source' => 'auto_merit_points'],
        50  => ['title' => 'Silver Engagement Award', 'category' => 'Participation', 'level' => 'University', 'source' => 'auto_merit_points'],
        80  => ['title' => 'Gold Engagement Award', 'category' => 'Participation', 'level' => 'University', 'source' => 'auto_merit_points'],
        120 => ['title' => 'Outstanding Student Involvement Award', 'category' => 'Leadership', 'level' => 'University', 'source' => 'auto_merit_points'],
    ];

    $hours_total = 0;
    $hours_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(hours_contributed), 0) AS total_hours FROM merits WHERE user_id = ? AND status = 'Completed'");
    mysqli_stmt_bind_param($hours_stmt, 'i', $user_id);
    mysqli_stmt_execute($hours_stmt);
    $hours_result = mysqli_stmt_get_result($hours_stmt);
    if ($row = mysqli_fetch_assoc($hours_result)) {
        $hours_total = (float) $row['total_hours'];
    }
    mysqli_stmt_close($hours_stmt);

    foreach ($hours_rules as $threshold => $rule) {
        if ($hours_total >= $threshold) {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM achievements WHERE user_id = ? AND title = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 'is', $user_id, $rule['title']);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            $exists = mysqli_stmt_num_rows($check_stmt) > 0;
            mysqli_stmt_close($check_stmt);

            if (!$exists) {
                $description = "Automatically awarded after reaching {$threshold} approved merit hours.";
                $status = 'Completed';
                $source = $rule['source'];

                $insert_stmt = mysqli_prepare($conn, "
                    INSERT INTO achievements
                    (user_id, event_id, title, category, achievement_date, level, description, status, evidence_file, achievement_source, reviewed_at, reviewed_by, admin_remark, created_at)
                    VALUES (?, NULL, ?, ?, CURDATE(), ?, ?, ?, NULL, ?, NOW(), NULL, 'System auto-generated achievement.', NOW())
                ");
                mysqli_stmt_bind_param(
                    $insert_stmt,
                    'issssss',
                    $user_id,
                    $rule['title'],
                    $rule['category'],
                    $rule['level'],
                    $description,
                    $status,
                    $source
                );
                mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);
            }
        }
    }

    $points_total = 0;
    $points_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(merit_points), 0) AS total_points FROM events WHERE user_id = ? AND event_status = 'Completed'");
    mysqli_stmt_bind_param($points_stmt, 'i', $user_id);
    mysqli_stmt_execute($points_stmt);
    $points_result = mysqli_stmt_get_result($points_stmt);
    if ($row = mysqli_fetch_assoc($points_result)) {
        $points_total = (int) $row['total_points'];
    }
    mysqli_stmt_close($points_stmt);

    foreach ($points_rules as $threshold => $rule) {
        if ($points_total >= $threshold) {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM achievements WHERE user_id = ? AND title = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 'is', $user_id, $rule['title']);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            $exists = mysqli_stmt_num_rows($check_stmt) > 0;
            mysqli_stmt_close($check_stmt);

            if (!$exists) {
                $description = "Automatically awarded after reaching {$threshold} total merit points from approved events.";
                $status = 'Completed';
                $source = $rule['source'];

                $insert_stmt = mysqli_prepare($conn, "
                    INSERT INTO achievements
                    (user_id, event_id, title, category, achievement_date, level, description, status, evidence_file, achievement_source, reviewed_at, reviewed_by, admin_remark, created_at)
                    VALUES (?, NULL, ?, ?, CURDATE(), ?, ?, ?, NULL, ?, NOW(), NULL, 'System auto-generated achievement.', NOW())
                ");
                mysqli_stmt_bind_param(
                    $insert_stmt,
                    'issssss',
                    $user_id,
                    $rule['title'],
                    $rule['category'],
                    $rule['level'],
                    $description,
                    $status,
                    $source
                );
                mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type   = $_POST['type'] ?? '';
    $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $admin_remark = trim($_POST['admin_remark'] ?? '');

    if ($id > 0 && in_array($action, ['approve', 'reject'], true) && in_array($type, ['event', 'achievement', 'merit', 'club'], true)) {

        if ($type === 'event') {
            if ($action === 'approve') {
                $stmt = mysqli_prepare($conn, "UPDATE events 
                    SET event_status = 'Completed', reviewed_at = NOW(), reviewed_by = ?, admin_remark = ?
                    WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $admin_remark, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $eventStmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ? LIMIT 1");
                mysqli_stmt_bind_param($eventStmt, 'i', $id);
                mysqli_stmt_execute($eventStmt);
                $eventRow = mysqli_fetch_assoc(mysqli_stmt_get_result($eventStmt));
                mysqli_stmt_close($eventStmt);

                if ($eventRow && (float)$eventRow['event_hours'] > 0) {
                    $checkStmt = mysqli_prepare($conn, "SELECT merit_id FROM merits WHERE event_id = ? AND user_id = ? LIMIT 1");
                    mysqli_stmt_bind_param($checkStmt, 'ii', $id, $eventRow['user_id']);
                    mysqli_stmt_execute($checkStmt);
                    mysqli_stmt_store_result($checkStmt);
                    $meritExists = mysqli_stmt_num_rows($checkStmt) > 0;
                    mysqli_stmt_close($checkStmt);

                    if (!$meritExists) {
                        $meritStmt = mysqli_prepare($conn, "INSERT INTO merits 
                            (user_id, event_id, activity_title, activity_type, start_date, end_date, hours_contributed, merit_points, description, status, reviewed_at, reviewed_by, admin_remark) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', NOW(), ?, ?)");
                        $meritDesc = 'Auto-generated from approved event (Role: ' . $eventRow['participation_role'] . ')';
                        $autoMeritPoints = (int)$eventRow['merit_points'];

                        mysqli_stmt_bind_param(
                            $meritStmt,
                            'iissssdisis',
                            $eventRow['user_id'],
                            $id,
                            $eventRow['event_title'],
                            $eventRow['event_category'],
                            $eventRow['event_date'],
                            $eventRow['event_date'],
                            $eventRow['event_hours'],
                            $autoMeritPoints,
                            $meritDesc,
                            $admin_id,
                            $admin_remark
                        );
                        mysqli_stmt_execute($meritStmt);
                        mysqli_stmt_close($meritStmt);
                    }
                }

                if ($eventRow) {
                    award_auto_achievements($conn, $eventRow['user_id']);
                }

                $flash = 'âœ… Event approved and marked as Completed. Merit record created if applicable.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE events 
                    SET event_status = 'Cancelled', reviewed_at = NOW(), reviewed_by = ?, admin_remark = ?
                    WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $admin_remark, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $flash = 'âŒ Event has been rejected and marked as Cancelled.';
                $flashClass = 'error';
            }
        } elseif ($type === 'achievement') {
            if ($action === 'approve') {
                $stmt = mysqli_prepare($conn, "UPDATE achievements 
                    SET status = 'Completed', reviewed_at = NOW(), reviewed_by = ?, admin_remark = ?
                    WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $admin_remark, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $flash = 'âœ… Achievement approved successfully.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE achievements 
                    SET status = 'Rejected', reviewed_at = NOW(), reviewed_by = ?, admin_remark = ?
                    WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $admin_remark, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $flash = 'âŒ Achievement has been rejected.';
                $flashClass = 'error';
            }
        } elseif ($type === 'merit') {
            if ($action === 'approve') {
                $get_stmt = mysqli_prepare($conn, "SELECT user_id FROM merits WHERE merit_id = ? LIMIT 1");
                mysqli_stmt_bind_param($get_stmt, 'i', $id);
                mysqli_stmt_execute($get_stmt);
                $get_result = mysqli_stmt_get_result($get_stmt);
                $merit_row = mysqli_fetch_assoc($get_result);
                mysqli_stmt_close($get_stmt);

                $stmt = mysqli_prepare($conn, "UPDATE merits 
                    SET status = 'Completed', reviewed_at = NOW(), reviewed_by = ?, admin_remark = ?
                    WHERE merit_id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $admin_remark, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if ($merit_row) {
                    award_auto_achievements($conn, $merit_row['user_id']);
                }

                $flash = 'âœ… Merit record approved successfully. Auto achievement checked.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE merits 
                    SET status = 'Rejected', reviewed_at = NOW(), reviewed_by = ?, admin_remark = ?
                    WHERE merit_id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $admin_remark, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $flash = 'âŒ Merit record has been rejected.';
                $flashClass = 'error';
            }
        } elseif ($type === 'club') {
            if ($action === 'approve') {
                $stmt = mysqli_prepare($conn, "UPDATE clubs 
                    SET review_status = 'Approved', reviewed_at = NOW(), reviewed_by = ?, admin_remark = ?
                    WHERE club_id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $admin_remark, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $flash = 'âœ… Club record approved successfully.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE clubs 
                    SET review_status = 'Rejected', reviewed_at = NOW(), reviewed_by = ?, admin_remark = ?
                    WHERE club_id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $admin_remark, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $flash = 'âŒ Club record has been rejected.';
                $flashClass = 'error';
            }
        }
    }
}

$eventSql = "
    SELECT e.*, u.student_id, u.email, c.club_name
    FROM events e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN clubs c ON e.club_id = c.club_id
    WHERE e.event_status = ?
    ORDER BY e.event_date ASC
";
$eventResult = fetch_rows_verify($conn, $eventSql, "s", 'Upcoming');
$pending_events = $eventResult ? mysqli_num_rows($eventResult) : 0;

$clubSql = "
    SELECT c.*, u.student_id, u.email
    FROM clubs c
    JOIN users u ON c.user_id = u.id
    WHERE c.review_status = ?
    ORDER BY c.join_date ASC, c.club_id DESC
";
$clubResult = fetch_rows_verify($conn, $clubSql, "s", 'Pending');
$pending_clubs = $clubResult ? mysqli_num_rows($clubResult) : 0;

$achieveSql = "
    SELECT a.*, u.student_id, u.email, e.event_title
    FROM achievements a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN events e ON a.event_id = e.id
    WHERE a.status = ?
    ORDER BY a.achievement_date ASC
";
$achieveResult = fetch_rows_verify($conn, $achieveSql, "s", 'Pending Verification');
$pending_achievements = $achieveResult ? mysqli_num_rows($achieveResult) : 0;

$meritSql = "
    SELECT m.*, u.student_id, u.email, e.event_title
    FROM merits m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN events e ON m.event_id = e.id
    WHERE m.status = ?
    ORDER BY m.start_date ASC, m.merit_id DESC
";
$meritResult = fetch_rows_verify($conn, $meritSql, "s", 'Pending');
$pending_merits = $meritResult ? mysqli_num_rows($meritResult) : 0;

$total_pending = $pending_events + $pending_clubs + $pending_achievements + $pending_merits;
$tab = $_GET['tab'] ?? 'events';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Inbox | CCMS Admin</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <style>
        .tab-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border, #e2e8f0);
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 0.7rem 1.4rem;
            border: none;
            background: none;
            font-size: 0.95rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            text-decoration: none;
        }
        .tab-btn.active {
            color: #4338ca;
            border-bottom-color: #4338ca;
        }
        .badge {
            display: inline-block;
            background: #ef4444;
            color: white;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 0.75rem;
            margin-left: 5px;
            vertical-align: middle;
        }
        .action-btns {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-approve {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.45rem 1rem;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn-approve:hover {
            background: #059669;
        }
        .btn-reject {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.45rem 1rem;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn-reject:hover {
            background: #dc2626;
        }
        .info-row {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 3px;
        }
        .remark-box {
            width: 100%;
            min-height: 70px;
            resize: vertical;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.7rem;
            margin-bottom: 0.6rem;
        }
        .evidence-link {
            background: #e0e7ff;
            color: #4338ca;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            display: inline-block;
        }
    </style>
</head>
<body class="main-body">
    <div class="sidebar" style="background: linear-gradient(180deg, #1e1b4b, #312e81);">
        <div>
            <h2 style="color: #818cf8;">CCMS Admin</h2>
            <p class="sidebar-subtitle">Staff Portal</p>
        </div>

        <div class="nav-links">
            <a href="admin_dashboard.php">ðŸ‘¥ User Management</a>
            <a href="verify_achievements.php" class="active">
                ðŸ“¥ Verification Inbox
                <?php if ($total_pending > 0): ?>
                    <span class="badge"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="view_history.php">ðŸ•˜ View History</a>
        </div>

        <a href="../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="background: linear-gradient(135deg, #1e1b4b, #4338ca); color: white; margin-bottom: 2rem;">
            <div>
                <p class="hero-label" style="color: #c7d2fe;">Action Required</p>
                <h1 style="color: white;">Verification Inbox ðŸ“¥</h1>
                <p style="opacity: 0.9; margin-top: 0.5rem;">
                    Review and approve student club, event, achievement, and merit records.
                    <strong style="color: #fbbf24;"><?php echo $total_pending; ?> item(s)</strong> awaiting your action.
                </p>
            </div>
        </div>

        <?php if ($flash !== ''): ?>
            <div class="alert <?php echo $flashClass; ?>"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>

        <div class="tab-bar">
            <a href="?tab=events" class="tab-btn <?php echo $tab === 'events' ? 'active' : ''; ?>">
                ðŸ“… Pending Events
                <?php if ($pending_events > 0): ?><span class="badge"><?php echo $pending_events; ?></span><?php endif; ?>
            </a>
            <a href="?tab=clubs" class="tab-btn <?php echo $tab === 'clubs' ? 'active' : ''; ?>">
                ðŸ‘¥ Pending Clubs
                <?php if ($pending_clubs > 0): ?><span class="badge"><?php echo $pending_clubs; ?></span><?php endif; ?>
            </a>
            <a href="?tab=achievements" class="tab-btn <?php echo $tab === 'achievements' ? 'active' : ''; ?>">
                ðŸ† 🏆 Pending Achievements
                <?php if ($pending_achievements > 0): ?><span class="badge"><?php echo $pending_achievements; ?></span><?php endif; ?>
            </a>
            <a href="?tab=merits" class="tab-btn <?php echo $tab === 'merits' ? 'active' : ''; ?>">
                â±ï¸ ⏱️ Pending Merits
                <?php if ($pending_merits > 0): ?><span class="badge"><?php echo $pending_merits; ?></span><?php endif; ?>
            </a>
        </div>

        <?php if ($tab === 'events'): ?>
            <div class="panel">
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="color: var(--dark);">ðŸ“… Pending Events (<?php echo $pending_events; ?>)</h2>
                    </div>
                </div>

                <?php if ($pending_events > 0): ?>
                    <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 1050px;">
                            <thead style="background: #f8fafc; border-bottom: 2px solid var(--border);">
                                <tr>
                                    <th style="padding: 1rem;">Student</th>
                                    <th style="padding: 1rem;">Event</th>
                                    <th style="padding: 1rem;">Date</th>
                                    <th style="padding: 1rem;">Role</th>
                                    <th style="padding: 1rem;">Hours</th>
                                    <th style="padding: 1rem;">Merit Pts</th>
                                    <th style="padding: 1rem;">Remark</th>
                                    <th style="padding: 1rem; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($eventResult)): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1rem;"><strong><?php echo htmlspecialchars(student_identity_verify($row['student_id'], $row['email'])); ?></strong></td>
                                        <td style="padding: 1rem;">
                                            <strong><?php echo htmlspecialchars($row['event_title']); ?></strong>
                                            <div class="info-row">
                                                <?php echo htmlspecialchars($row['event_category']); ?>
                                                <?php if (!empty($row['club_name'])): ?>
                                                    Â· <?php echo htmlspecialchars($row['club_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="info-row"><?php echo htmlspecialchars($row['organizer']); ?></div>
                                        </td>
                                        <td style="padding: 1rem;"><?php echo date('d M Y', strtotime($row['event_date'])); ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($row['participation_role']); ?></td>
                                        <td style="padding: 1rem;"><?php echo rtrim(rtrim(number_format((float)$row['event_hours'], 1), '0'), '.'); ?> hrs</td>
                                        <td style="padding: 1rem;"><?php echo (int)$row['merit_points']; ?> pts</td>
                                        <td style="padding: 1rem; min-width: 220px;">
                                            <form method="POST">
                                                <textarea name="admin_remark" class="remark-box" placeholder="Optional admin remark..."></textarea>
                                                <input type="hidden" name="type" value="event">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <div class="action-btns">
                                                    <button type="submit" name="action" value="approve" class="btn-approve" onclick="return confirm('Approve this event?');">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Reject this event?');">Reject</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td style="padding: 1rem; text-align: center; color:#64748b;">Use remark column</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem 0;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">ðŸ“…</div>
                        <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No Pending Events!</h3>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'clubs'): ?>
            <div class="panel">
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="color: var(--dark);">ðŸ‘¥ Pending Clubs (<?php echo $pending_clubs; ?>)</h2>
                    </div>
                </div>

                <?php if ($pending_clubs > 0): ?>
                    <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 1150px;">
                            <thead style="background: #f8fafc; border-bottom: 2px solid var(--border);">
                                <tr>
                                    <th style="padding: 1rem;">Student</th>
                                    <th style="padding: 1rem;">Club</th>
                                    <th style="padding: 1rem;">Category</th>
                                    <th style="padding: 1rem;">Role</th>
                                    <th style="padding: 1rem;">Membership</th>
                                    <th style="padding: 1rem;">Join Date</th>
                                    <th style="padding: 1rem;">End Date</th>
                                    <th style="padding: 1rem;">Student Remarks</th>
                                    <th style="padding: 1rem;">Remark</th>
                                    <th style="padding: 1rem; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($clubResult)): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1rem;"><strong><?php echo htmlspecialchars(student_identity_verify($row['student_id'], $row['email'])); ?></strong></td>
                                        <td style="padding: 1rem;"><strong><?php echo htmlspecialchars($row['club_name']); ?></strong></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($row['club_category']); ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($row['role_position']); ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($row['membership_status']); ?></td>
                                        <td style="padding: 1rem;"><?php echo date('d M Y', strtotime($row['join_date'])); ?></td>
                                        <td style="padding: 1rem;"><?php echo !empty($row['end_date']) ? date('d M Y', strtotime($row['end_date'])) : '-'; ?></td>
                                        <td style="padding: 1rem; color:#64748b;">
                                            <?php echo !empty($row['remarks']) ? nl2br(htmlspecialchars(mb_strimwidth($row['remarks'], 0, 70, '...'))) : '-'; ?>
                                        </td>
                                        <td style="padding: 1rem; min-width: 220px;">
                                            <form method="POST">
                                                <textarea name="admin_remark" class="remark-box" placeholder="Optional admin remark..."></textarea>
                                                <input type="hidden" name="type" value="club">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['club_id']; ?>">
                                                <div class="action-btns">
                                                    <button type="submit" name="action" value="approve" class="btn-approve" onclick="return confirm('Approve this club record?');">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Reject this club record?');">Reject</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td style="padding: 1rem; text-align:center; color:#64748b;">Use remark column</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem 0;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">ðŸ‘¥</div>
                        <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No Pending Clubs!</h3>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'achievements'): ?>
            <div class="panel">
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="color: var(--dark);">ðŸ† 🏆 Pending Achievements (<?php echo $pending_achievements; ?>)</h2>
                    </div>
                </div>

                <?php if ($pending_achievements > 0): ?>
                    <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 1100px;">
                            <thead style="background: #f8fafc; border-bottom: 2px solid var(--border);">
                                <tr>
                                    <th style="padding: 1rem;">Student</th>
                                    <th style="padding: 1rem;">Achievement</th>
                                    <th style="padding: 1rem;">Related Event</th>
                                    <th style="padding: 1rem;">Category</th>
                                    <th style="padding: 1rem;">Level</th>
                                    <th style="padding: 1rem;">Date</th>
                                    <th style="padding: 1rem;">Evidence</th>
                                    <th style="padding: 1rem;">Remark</th>
                                    <th style="padding: 1rem; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($achieveResult)): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1rem;"><strong><?php echo htmlspecialchars(student_identity_verify($row['student_id'], $row['email'])); ?></strong></td>
                                        <td style="padding: 1rem;">
                                            <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                            <?php if (!empty($row['description'])): ?>
                                                <div class="info-row"><?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 60, '...')); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem;"><?php echo !empty($row['event_title']) ? htmlspecialchars($row['event_title']) : '-'; ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($row['level']); ?></td>
                                        <td style="padding: 1rem;"><?php echo date('d M Y', strtotime($row['achievement_date'])); ?></td>
                                        <td style="padding: 1rem;">
                                            <?php if (!empty($row['evidence_file'])): ?>
                                                <a href="<?php echo htmlspecialchars(safe_evidence_url($row['evidence_file'])); ?>" target="_blank" class="evidence-link">View</a>
                                            <?php else: ?>
                                                <span style="color: #ef4444; font-size: 0.82rem; font-weight: 700;">No file</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; min-width: 220px;">
                                            <form method="POST">
                                                <textarea name="admin_remark" class="remark-box" placeholder="Optional admin remark..."></textarea>
                                                <input type="hidden" name="type" value="achievement">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <div class="action-btns">
                                                    <button type="submit" name="action" value="approve" class="btn-approve" onclick="return confirm('Approve this achievement?');">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Reject this achievement?');">Reject</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td style="padding: 1rem; text-align:center; color:#64748b;">Use remark column</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem 0;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">ðŸ†</div>
                        <h3 style="color: var(--dark); margin-bottom: 0.5rem;">All Caught Up!</h3>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'merits'): ?>
            <div class="panel">
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="color: var(--dark);">â±ï¸ ⏱️ Pending Merits (<?php echo $pending_merits; ?>)</h2>
                    </div>
                </div>

                <?php if ($pending_merits > 0): ?>
                    <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 1150px;">
                            <thead style="background: #f8fafc; border-bottom: 2px solid var(--border);">
                                <tr>
                                    <th style="padding: 1rem;">Student</th>
                                    <th style="padding: 1rem;">Activity Title</th>
                                    <th style="padding: 1rem;">Type</th>
                                    <th style="padding: 1rem;">Related Event</th>
                                    <th style="padding: 1rem;">Start Date</th>
                                    <th style="padding: 1rem;">Hours</th>
                                    <th style="padding: 1rem;">Description</th>
                                    <th style="padding: 1rem;">Remark</th>
                                    <th style="padding: 1rem; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($meritResult)): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1rem;"><strong><?php echo htmlspecialchars(student_identity_verify($row['student_id'], $row['email'])); ?></strong></td>
                                        <td style="padding: 1rem;"><strong><?php echo htmlspecialchars($row['activity_title']); ?></strong></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($row['activity_type']); ?></td>
                                        <td style="padding: 1rem;"><?php echo !empty($row['event_title']) ? htmlspecialchars($row['event_title']) : '-'; ?></td>
                                        <td style="padding: 1rem;"><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($row['hours_contributed']); ?> hrs</td>
                                        <td style="padding: 1rem; color:#64748b;">
                                            <?php echo !empty($row['description']) ? nl2br(htmlspecialchars(mb_strimwidth($row['description'], 0, 70, '...'))) : '-'; ?>
                                        </td>
                                        <td style="padding: 1rem; min-width: 220px;">
                                            <form method="POST">
                                                <textarea name="admin_remark" class="remark-box" placeholder="Optional admin remark..."></textarea>
                                                <input type="hidden" name="type" value="merit">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['merit_id']; ?>">
                                                <div class="action-btns">
                                                    <button type="submit" name="action" value="approve" class="btn-approve" onclick="return confirm('Approve this merit record?');">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Reject this merit record?');">Reject</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td style="padding: 1rem; text-align:center; color:#64748b;">Use remark column</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem 0;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">â±ï¸</div>
                        <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No ⏱️ Pending Merits!</h3>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

