<?php
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

$username = $_SESSION['username'];
$flash = '';
$flashClass = 'success';

// =========================================
// POST ACTIONS — Approve or Reject
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type   = $_POST['type'] ?? '';   // 'event' or 'achievement'
    $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($id > 0 && in_array($action, ['approve', 'reject'], true) && in_array($type, ['event', 'achievement'], true)) {

        if ($type === 'event') {
            if ($action === 'approve') {
                // Mark event as Completed
                $stmt = mysqli_prepare($conn, "UPDATE events SET event_status = 'Completed' WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // Auto-create merit record if not already exists
                $eventStmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ? LIMIT 1");
                mysqli_stmt_bind_param($eventStmt, 'i', $id);
                mysqli_stmt_execute($eventStmt);
                $eventRow = mysqli_fetch_assoc(mysqli_stmt_get_result($eventStmt));
                mysqli_stmt_close($eventStmt);

                if ($eventRow && $eventRow['event_hours'] > 0) {
                    $checkStmt = mysqli_prepare($conn, "SELECT merit_id FROM merits WHERE event_id = ? AND user_id = ? LIMIT 1");
                    mysqli_stmt_bind_param($checkStmt, 'ii', $id, $eventRow['user_id']);
                    mysqli_stmt_execute($checkStmt);
                    mysqli_stmt_store_result($checkStmt);
                    $meritExists = mysqli_stmt_num_rows($checkStmt) > 0;
                    mysqli_stmt_close($checkStmt);

                    if (!$meritExists) {
                        $meritStmt = mysqli_prepare($conn, "INSERT INTO merits (user_id, event_id, activity_title, activity_type, start_date, end_date, hours_contributed, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $meritDesc   = 'Auto-approved by Admin (Role: ' . $eventRow['participation_role'] . ')';
                        $meritStatus = 'Approved';
                        mysqli_stmt_bind_param(
                            $meritStmt,
                            'iissssdss',
                            $eventRow['user_id'],
                            $id,
                            $eventRow['event_title'],
                            $eventRow['event_category'],
                            $eventRow['event_date'],
                            $eventRow['event_date'],
                            $eventRow['event_hours'],
                            $meritDesc,
                            $meritStatus
                        );
                        mysqli_stmt_execute($meritStmt);
                        mysqli_stmt_close($meritStmt);
                    }
                }

                $flash = '✅ Event approved and marked as Completed. Merit record created if applicable.';
            } else {
                // Reject — mark as Cancelled
                $stmt = mysqli_prepare($conn, "UPDATE events SET event_status = 'Cancelled' WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $flash = '❌ Event has been rejected and marked as Cancelled.';
                $flashClass = 'error';
            }
        } elseif ($type === 'achievement') {
            if ($action === 'approve') {
                $stmt = mysqli_prepare($conn, "UPDATE achievements SET status = 'Verified' WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $flash = '✅ Achievement verified and approved successfully.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE achievements SET status = 'Rejected' WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $flash = '❌ Achievement has been rejected.';
                $flashClass = 'error';
            }
        }
    }
}

// =========================================
// FETCH PENDING EVENTS
// Events that are NOT yet Completed, Missed, or Cancelled
// i.e. status = 'Upcoming' — waiting for admin to confirm completed
// =========================================
$eventSql = "
    SELECT e.*, u.username, c.club_name
    FROM events e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN clubs c ON e.club_id = c.club_id
    WHERE e.event_status = 'Upcoming'
    ORDER BY e.event_date ASC
";
$eventResult     = mysqli_query($conn, $eventSql);
$pending_events  = $eventResult ? mysqli_num_rows($eventResult) : 0;

// =========================================
// FETCH PENDING ACHIEVEMENTS
// =========================================
$achieveSql = "
    SELECT a.*, u.username
    FROM achievements a
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'Pending Verification'
    ORDER BY a.achievement_date ASC
";
$achieveResult       = mysqli_query($conn, $achieveSql);
$pending_achievements = $achieveResult ? mysqli_num_rows($achieveResult) : 0;

$total_pending = $pending_events + $pending_achievements;

// Active tab
$tab = $_GET['tab'] ?? 'events';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Inbox | CCMS Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .tab-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border, #e2e8f0);
            padding-bottom: 0;
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
            transition: 0.2s;
        }

        .tab-btn.active {
            color: #4338ca;
            border-bottom-color: #4338ca;
        }

        .tab-btn:hover:not(.active) {
            color: #1e293b;
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
    </style>
</head>

<body class="main-body">
    <div class="sidebar" style="background: linear-gradient(180deg, #1e1b4b, #312e81);">
        <div>
            <h2 style="color: #818cf8;">CCMS Admin</h2>
            <p class="sidebar-subtitle">Staff Portal</p>
        </div>

        <div class="nav-links">
            <a href="admin_dashboard.php">👥 User Management</a>
            <a href="verify_achievements.php" class="active">
                ✅ Verification Inbox
                <?php if ($total_pending > 0): ?>
                    <span class="badge"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <a href="../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="background: linear-gradient(135deg, #1e1b4b, #4338ca); color: white; margin-bottom: 2rem;">
            <div>
                <p class="hero-label" style="color: #c7d2fe;">Action Required</p>
                <h1 style="color: white;">Verification Inbox 📥</h1>
                <p style="opacity: 0.9; margin-top: 0.5rem;">
                    Review and approve student event completions and achievement submissions.
                    <strong style="color: #fbbf24;"><?php echo $total_pending; ?> item(s)</strong> awaiting your action.
                </p>
            </div>
        </div>

        <?php if ($flash !== ''): ?>
            <div class="alert <?php echo $flashClass; ?>"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>

        <!-- TAB BAR -->
        <div class="tab-bar">
            <a href="?tab=events" class="tab-btn <?php echo $tab === 'events' ? 'active' : ''; ?>">
                📅 Pending Events
                <?php if ($pending_events > 0): ?>
                    <span class="badge"><?php echo $pending_events; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=achievements" class="tab-btn <?php echo $tab === 'achievements' ? 'active' : ''; ?>">
                🏆 Pending Achievements
                <?php if ($pending_achievements > 0): ?>
                    <span class="badge"><?php echo $pending_achievements; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- ===================== EVENTS TAB ===================== -->
        <?php if ($tab === 'events'): ?>
            <div class="panel">
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="color: var(--dark);">Upcoming Events Pending Approval (<?php echo $pending_events; ?>)</h2>
                        <p style="color: #64748b; font-size: 0.9rem; margin-top: 4px;">
                            These events are submitted by students as "Upcoming". Approve to mark them as <strong>Completed</strong> (and auto-create merit), or reject to mark as <strong>Cancelled</strong>.
                        </p>
                    </div>
                </div>

                <?php if ($pending_events > 0): ?>
                    <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 900px;">
                            <thead style="background: #f8fafc; border-bottom: 2px solid var(--border);">
                                <tr>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Student</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Event</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Date</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Role</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Hours</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Merit Pts</th>
                                    <th style="padding: 1rem; text-align: center; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($eventResult)): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1rem;">
                                            <strong>@<?php echo htmlspecialchars($row['username']); ?></strong>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <strong style="color: #1e293b;"><?php echo htmlspecialchars($row['event_title']); ?></strong>
                                            <div class="info-row">
                                                <?php echo htmlspecialchars($row['event_category']); ?>
                                                <?php if (!empty($row['club_name'])): ?>
                                                    · <?php echo htmlspecialchars($row['club_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="info-row"><?php echo htmlspecialchars($row['organizer']); ?></div>
                                        </td>
                                        <td style="padding: 1rem; color: #475569;">
                                            <?php echo date('d M Y', strtotime($row['event_date'])); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="background: #eff6ff; color: #2563eb; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.82rem; font-weight: 600;">
                                                <?php echo htmlspecialchars($row['participation_role']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; font-weight: 700; color: #0f172a;">
                                            <?php echo rtrim(rtrim(number_format((float)$row['event_hours'], 1), '0'), '.'); ?> hrs
                                        </td>
                                        <td style="padding: 1rem; font-weight: 700; color: #7c3aed;">
                                            <?php echo (int)$row['merit_points']; ?> pts
                                        </td>
                                        <td style="padding: 1rem;">
                                            <div class="action-btns">
                                                <form method="POST" onsubmit="return confirm('Approve this event as Completed?');">
                                                    <input type="hidden" name="type" value="event">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" class="btn-approve">✅ Approve</button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Reject this event?');">
                                                    <input type="hidden" name="type" value="event">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" class="btn-reject">❌ Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem 0;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🎉</div>
                        <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No Pending Events!</h3>
                        <p style="color: #64748b;">All student events have been reviewed. Check back later.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===================== ACHIEVEMENTS TAB ===================== -->
        <?php elseif ($tab === 'achievements'): ?>
            <div class="panel">
                <div class="panel-header" style="margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="color: var(--dark);">Achievement Submissions Pending Approval (<?php echo $pending_achievements; ?>)</h2>
                        <p style="color: #64748b; font-size: 0.9rem; margin-top: 4px;">
                            Review uploaded evidence before approving. Approved achievements will be marked as <strong>Verified</strong>. Rejected ones will be marked as <strong>Rejected</strong>.
                        </p>
                    </div>
                </div>

                <?php if ($pending_achievements > 0): ?>
                    <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 800px;">
                            <thead style="background: #f8fafc; border-bottom: 2px solid var(--border);">
                                <tr>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Student</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Achievement</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Category</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Level</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Date</th>
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Evidence</th>
                                    <th style="padding: 1rem; text-align: center; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($achieveResult)): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1rem;">
                                            <strong>@<?php echo htmlspecialchars($row['username']); ?></strong>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <strong style="color: #1e293b;"><?php echo htmlspecialchars($row['title']); ?></strong>
                                            <?php if (!empty($row['description'])): ?>
                                                <div class="info-row"><?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 60, '...')); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; color: #475569;"><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td style="padding: 1rem;">
                                            <span style="background: #f5f3ff; color: #7c3aed; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.82rem; font-weight: 600;">
                                                <?php echo htmlspecialchars($row['level']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; color: #475569;">
                                            <?php echo date('d M Y', strtotime($row['achievement_date'])); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php if (!empty($row['evidence_file'])): ?>
                                                <a href="../achievement_tracker/uploads/<?php echo htmlspecialchars($row['evidence_file']); ?>"
                                                    target="_blank"
                                                    style="background: #e0e7ff; color: #4338ca; padding: 0.4rem 0.8rem; border-radius: 8px; text-decoration: none; font-size: 0.82rem; font-weight: 700; display: inline-block;">
                                                    🔍 View
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #ef4444; font-size: 0.82rem; font-weight: 700;">No file</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <div class="action-btns">
                                                <form method="POST" onsubmit="return confirm('Approve this achievement?');">
                                                    <input type="hidden" name="type" value="achievement">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" class="btn-approve">✅ Approve</button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Reject this achievement?');">
                                                    <input type="hidden" name="type" value="achievement">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" class="btn-reject">❌ Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem 0;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🏆</div>
                        <h3 style="color: var(--dark); margin-bottom: 0.5rem;">All Caught Up!</h3>
                        <p style="color: #64748b;">There are no pending achievements waiting for verification.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>