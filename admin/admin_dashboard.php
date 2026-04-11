<?php
include '../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

$username = $_SESSION['username'];
$search = trim($_GET['search'] ?? '');

$events_table = 'events';
$clubs_table = 'clubs';
$merits_table = 'merits';
$achievements_table = 'achievements';

function table_exists_admin($conn, $table_name)
{
    $safe_table_name = mysqli_real_escape_string($conn, $table_name);
    $sql = "SHOW TABLES LIKE '$safe_table_name'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function safe_total_count_admin($conn, $table_name)
{
    if (!table_exists_admin($conn, $table_name)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS total FROM `$table_name`";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return (int) $row['total'];
    }

    return 0;
}

function admin_history_file_url($filename)
{
    return "../cocurricular_system/achievement_tracker/uploads/" . rawurlencode($filename);
}

$total_students = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM users WHERE role = ?");
$role = 'student';
mysqli_stmt_bind_param($stmt, "s", $role);
mysqli_stmt_execute($stmt);
$result_students = mysqli_stmt_get_result($stmt);
if ($result_students) {
    $row_students = mysqli_fetch_assoc($result_students);
    $total_students = (int) $row_students['total'];
}
mysqli_stmt_close($stmt);

$total_events = safe_total_count_admin($conn, $events_table);
$total_clubs = safe_total_count_admin($conn, $clubs_table);
$total_merits = safe_total_count_admin($conn, $merits_table);
$total_achievements = safe_total_count_admin($conn, $achievements_table);

// Pending verification count
$pending_count = 0;
$pending_sql = "SELECT COUNT(*) AS total FROM achievements WHERE status = 'Pending Verification'";
$pending_result = mysqli_query($conn, $pending_sql);
if ($pending_result) {
    $pending_row = mysqli_fetch_assoc($pending_result);
    $pending_count = (int) ($pending_row['total'] ?? 0);
}

// Detailed recent verification history
$history_sql = "SELECT 
                    a.id,
                    a.title,
                    a.category,
                    a.level,
                    a.status,
                    a.achievement_date,
                    a.reviewed_at,
                    a.admin_remark,
                    a.evidence_file,
                    u.username,
                    e.event_title
                FROM achievements a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN events e ON a.event_id = e.id
                WHERE a.status IN ('Completed', 'Rejected')
                  AND a.reviewed_at IS NOT NULL
                ORDER BY a.reviewed_at DESC
                LIMIT 8";
$history_result = mysqli_query($conn, $history_sql);

$events_expr = table_exists_admin($conn, $events_table)
    ? "(SELECT COUNT(*) FROM `$events_table` WHERE user_id = u.id)"
    : "0";

$clubs_expr = table_exists_admin($conn, $clubs_table)
    ? "(SELECT COUNT(*) FROM `$clubs_table` WHERE user_id = u.id)"
    : "0";

$merits_expr = table_exists_admin($conn, $merits_table)
    ? "(SELECT COUNT(*) FROM `$merits_table` WHERE user_id = u.id)"
    : "0";

$achievements_expr = table_exists_admin($conn, $achievements_table)
    ? "(SELECT COUNT(*) FROM `$achievements_table` WHERE user_id = u.id)"
    : "0";

$sql = "
    SELECT
        u.id,
        u.username,
        u.email,
        $events_expr AS total_events,
        $clubs_expr AS total_clubs,
        $merits_expr AS total_merits,
        $achievements_expr AS total_achievements
    FROM users u
    WHERE u.role = ?
";

$params = ['student'];
$types = "s";

if ($search !== '') {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $search_like = "%" . $search . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss";
}

$sql .= " ORDER BY u.id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$filtered_students = $result ? mysqli_num_rows($result) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CCMS</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <style>
        .table-row-hover:hover {
            background-color: #f8fafc;
            transition: background-color 0.2s ease;
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
            font-size: 0.9rem;
        }

        .history-link:hover {
            text-decoration: underline;
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

        .history-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
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
            <a href="admin_dashboard.php" class="active">👥 User Management</a>
            <a href="verify_achievements.php">✅ Verification Inbox
                <?php if ($pending_count > 0): ?>
                    <span style="background:#ef4444;color:white;padding:2px 8px;border-radius:999px;font-size:0.8rem;margin-left:6px;"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <a href="../auth/logout.php" class="logout-link" style="margin-top: auto;">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-glass" style="background: linear-gradient(120deg, #1e293b, #4338ca);">
            <p class="hero-label" style="color: #c7d2fe; margin-bottom: 0.5rem; display: block;">System Administrator</p>
            <h1>Welcome, <?php echo htmlspecialchars($username); ?> 🛡️</h1>
            <p>Monitor all registered students, pending verification requests, and recent review activity from one place.</p>
        </div>

        <div class="stats-container">
            <div class="stat-box blue">
                <span class="stat-label">Registered Students</span>
                <div class="stat-number"><?php echo $total_students; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">Student accounts in system</span>
            </div>

            <div class="stat-box green">
                <span class="stat-label">Total Events</span>
                <div class="stat-number"><?php echo $total_events; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">All event records</span>
            </div>

            <div class="stat-box orange">
                <span class="stat-label">Total Merit Records</span>
                <div class="stat-number"><?php echo $total_merits; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">All merit submissions</span>
            </div>

            <div class="stat-box purple">
                <span class="stat-label">Pending Verification</span>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">
                    <a href="verify_achievements.php" style="color:#4338ca;text-decoration:none;font-weight:700;">Open inbox</a>
                </span>
            </div>
        </div>

        <div class="panel" style="margin-bottom: 2rem;">
            <div class="panel-header" style="gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;">
                <div>
                    <h2 style="color: var(--dark); margin-bottom: 0.35rem;">Student Usage Summary</h2>
                    <p style="color: var(--text-muted); font-size: 0.95rem;">
                        <?php echo $filtered_students; ?> result(s)
                        <?php if ($search !== ''): ?>
                            for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                    </p>
                </div>

                <form method="GET" action="" style="display: flex; gap: 0.8rem; flex-wrap: wrap; align-items: center;">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search by username or email"
                        value="<?php echo htmlspecialchars($search); ?>"
                        style="min-width: 260px; padding: 0.8rem 1rem; border: 2px solid var(--border); border-radius: 12px; outline: none; transition: 0.3s;">
                    <button type="submit" class="btn-primary">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="admin_dashboard.php" class="btn-disabled" style="text-decoration: none; display: inline-block;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrapper" style="overflow-x: auto; background: white; border-top-left-radius: 16px; border-top-right-radius: 16px; border: 1px solid var(--border); border-bottom: none;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 980px;">
                    <thead style="background: #f8fafc; border-bottom: 2px solid var(--border);">
                        <tr>
                            <th style="padding: 1.2rem 1rem;">ID</th>
                            <th style="padding: 1.2rem 1rem;">Student Name</th>
                            <th style="padding: 1.2rem 1rem;">Email</th>
                            <th style="padding: 1.2rem 1rem; text-align: center;">Events</th>
                            <th style="padding: 1.2rem 1rem; text-align: center;">Clubs</th>
                            <th style="padding: 1.2rem 1rem; text-align: center;">Merits</th>
                            <th style="padding: 1.2rem 1rem; text-align: center;">Achievements</th>
                            <th style="padding: 1.2rem 1rem; text-align: center;">Total Records</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                $grand_total =
                                    (int) $row['total_events'] +
                                    (int) $row['total_clubs'] +
                                    (int) $row['total_merits'] +
                                    (int) $row['total_achievements'];
                                ?>
                                <tr class="table-row-hover" style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1.2rem 1rem; color: var(--text-muted); font-weight: 500;">#<?php echo $row['id']; ?></td>
                                    <td style="padding: 1.2rem 1rem;"><strong style="color: var(--dark); font-size: 1.05rem;"><?php echo htmlspecialchars($row['username']); ?></strong></td>
                                    <td style="padding: 1.2rem 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td style="padding: 1.2rem 1rem; text-align: center;"><?php echo $row['total_events']; ?></td>
                                    <td style="padding: 1.2rem 1rem; text-align: center;"><?php echo $row['total_clubs']; ?></td>
                                    <td style="padding: 1.2rem 1rem; text-align: center;"><?php echo $row['total_merits']; ?></td>
                                    <td style="padding: 1.2rem 1rem; text-align: center;"><?php echo $row['total_achievements']; ?></td>
                                    <td style="padding: 1.2rem 1rem; text-align: center;"><strong><?php echo $grand_total; ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="padding: 4rem 2rem; text-align: center;">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No students found</h3>
                                    <p style="color: var(--text-muted);">Try another search keyword or wait until students register in the system.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header" style="margin-bottom: 1.5rem;">
                <h2 style="color: var(--dark);">Recent Verification History</h2>
            </div>

            <?php if ($history_result && mysqli_num_rows($history_result) > 0): ?>
                <div class="history-list">
                    <?php while ($item = mysqli_fetch_assoc($history_result)): ?>
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
                                <a href="<?php echo htmlspecialchars(admin_history_file_url($item['evidence_file'])); ?>" target="_blank" class="history-link">
                                    📎 View Evidence File
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted);">No review history yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>