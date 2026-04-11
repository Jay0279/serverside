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

$tab = $_GET['tab'] ?? 'events';

// pending count for sidebar
$pending_events = 0;
$pending_achievements = 0;
$pending_merits = 0;

$r1 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM events WHERE event_status = 'Upcoming'");
if ($r1) $pending_events = (int) (mysqli_fetch_assoc($r1)['total'] ?? 0);

$r2 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM achievements WHERE status = 'Pending Verification'");
if ($r2) $pending_achievements = (int) (mysqli_fetch_assoc($r2)['total'] ?? 0);

$r3 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM merits WHERE status = 'Pending'");
if ($r3) $pending_merits = (int) (mysqli_fetch_assoc($r3)['total'] ?? 0);

$total_pending = $pending_events + $pending_achievements + $pending_merits;

// Event history
$eventHistorySql = "
    SELECT e.*, u.username, c.club_name
    FROM events e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN clubs c ON e.club_id = c.club_id
    WHERE e.event_status IN ('Completed', 'Cancelled')
      AND e.reviewed_at IS NOT NULL
    ORDER BY e.reviewed_at DESC
";
$eventHistoryResult = mysqli_query($conn, $eventHistorySql);

// Achievement history
$achievementHistorySql = "
    SELECT a.*, u.username, e.event_title
    FROM achievements a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN events e ON a.event_id = e.id
    WHERE a.status IN ('Completed', 'Rejected')
      AND a.reviewed_at IS NOT NULL
    ORDER BY a.reviewed_at DESC
";
$achievementHistoryResult = mysqli_query($conn, $achievementHistorySql);

// Merit history
$meritHistorySql = "
    SELECT m.*, u.username, e.event_title
    FROM merits m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN events e ON m.event_id = e.id
    WHERE m.status IN ('Completed', 'Rejected')
      AND m.reviewed_at IS NOT NULL
    ORDER BY m.reviewed_at DESC
";
$meritHistoryResult = mysqli_query($conn, $meritHistorySql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View History | CCMS Admin</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <style>
        .tab-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border, #e2e8f0);
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
        .mini-badge {
            background:#ef4444;
            color:white;
            padding:2px 8px;
            border-radius:999px;
            font-size:0.8rem;
            margin-left:6px;
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .history-item {
            padding: 16px 18px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: white;
        }
        .history-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .history-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 6px;
        }
        .history-meta {
            color: var(--text-muted);
            font-size: 0.92rem;
            line-height: 1.7;
        }
        .history-remark {
            margin-top: 10px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            color: #475569;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .history-link {
            display: inline-block;
            margin-top: 10px;
            color: #4338ca;
            font-weight: 700;
            text-decoration: none;
        }
        .status-completed {
            color: #166534;
            background: #dcfce7;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .status-rejected {
            color: #991b1b;
            background: #fee2e2;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
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
                ✅ Verification Inbox
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
            <p>See approved and rejected event, achievement, and merit records in one dedicated page.</p>
        </div>

        <div class="tab-bar">
            <a href="?tab=events" class="tab-btn <?php echo $tab === 'events' ? 'active' : ''; ?>">📅 Event History</a>
            <a href="?tab=achievements" class="tab-btn <?php echo $tab === 'achievements' ? 'active' : ''; ?>">🏆 Achievement History</a>
            <a href="?tab=merits" class="tab-btn <?php echo $tab === 'merits' ? 'active' : ''; ?>">⏱️ Merit History</a>
        </div>

        <div class="panel">
            <?php if ($tab === 'events'): ?>
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <h2 style="color: var(--dark);">Event Review History</h2>
                </div>

                <?php if ($eventHistoryResult && mysqli_num_rows($eventHistoryResult) > 0): ?>
                    <div class="history-list">
                        <?php while ($item = mysqli_fetch_assoc($eventHistoryResult)): ?>
                            <div class="history-item">
                                <div class="history-top">
                                    <div>
                                        <div class="history-title"><?php echo htmlspecialchars($item['event_title']); ?></div>
                                        <div class="history-meta">
                                            <strong>Student:</strong> @<?php echo htmlspecialchars($item['username']); ?><br>
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

            <?php elseif ($tab === 'achievements'): ?>
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <h2 style="color: var(--dark);">Achievement Review History</h2>
                </div>

                <?php if ($achievementHistoryResult && mysqli_num_rows($achievementHistoryResult) > 0): ?>
                    <div class="history-list">
                        <?php while ($item = mysqli_fetch_assoc($achievementHistoryResult)): ?>
                            <div class="history-item">
                                <div class="history-top">
                                    <div>
                                        <div class="history-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                        <div class="history-meta">
                                            <strong>Student:</strong> @<?php echo htmlspecialchars($item['username']); ?><br>
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
                                    <a href="<?php echo htmlspecialchars(history_file_url($item['evidence_file'])); ?>" target="_blank" class="history-link">📎 View Evidence File</a>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No achievement history yet.</p>
                <?php endif; ?>

            <?php elseif ($tab === 'merits'): ?>
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <h2 style="color: var(--dark);">Merit Review History</h2>
                </div>

                <?php if ($meritHistoryResult && mysqli_num_rows($meritHistoryResult) > 0): ?>
                    <div class="history-list">
                        <?php while ($item = mysqli_fetch_assoc($meritHistoryResult)): ?>
                            <div class="history-item">
                                <div class="history-top">
                                    <div>
                                        <div class="history-title"><?php echo htmlspecialchars($item['activity_title']); ?></div>
                                        <div class="history-meta">
                                            <strong>Student:</strong> @<?php echo htmlspecialchars($item['username']); ?><br>
                                            <strong>Related Event:</strong> <?php echo !empty($item['event_title']) ? htmlspecialchars($item['event_title']) : '-'; ?><br>
                                            <strong>Type:</strong> <?php echo htmlspecialchars($item['activity_type']); ?><br>
                                            <strong>Start Date:</strong> <?php echo htmlspecialchars($item['start_date']); ?><br>
                                            <strong>Hours:</strong> <?php echo htmlspecialchars($item['hours_contributed']); ?> hrs<br>
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