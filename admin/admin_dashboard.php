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

function fetch_single_count_admin($conn, $sql, $types = '', ...$params)
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

$total_students = fetch_single_count_admin($conn, "SELECT COUNT(*) AS total FROM users WHERE role = ?", "s", 'student');
$total_events = safe_total_count_admin($conn, $events_table);
$total_clubs = safe_total_count_admin($conn, $clubs_table);
$total_merits = safe_total_count_admin($conn, $merits_table);
$total_achievements = safe_total_count_admin($conn, $achievements_table);

$upcoming_status = 'Upcoming';
$pending_status = 'Pending';
$pending_verification = 'Pending Verification';
$pending_events = fetch_single_count_admin($conn, "SELECT COUNT(*) AS total FROM events WHERE event_status = ?", "s", $upcoming_status);
$pending_clubs = fetch_single_count_admin($conn, "SELECT COUNT(*) AS total FROM clubs WHERE review_status = ?", "s", $pending_status);
$pending_achievements = fetch_single_count_admin($conn, "SELECT COUNT(*) AS total FROM achievements WHERE status = ?", "s", $pending_verification);
$pending_merits = fetch_single_count_admin($conn, "SELECT COUNT(*) AS total FROM merits WHERE status = ?", "s", $pending_status);

$total_pending = $pending_events + $pending_clubs + $pending_achievements + $pending_merits;

$events_expr = table_exists_admin($conn, $events_table)
    ? "(SELECT COUNT(*) FROM `$events_table` WHERE user_id = u.user_id)"
    : "0";

$clubs_expr = table_exists_admin($conn, $clubs_table)
    ? "(SELECT COUNT(*) FROM `$clubs_table` WHERE user_id = u.user_id)"
    : "0";

$merits_expr = table_exists_admin($conn, $merits_table)
    ? "(SELECT COUNT(*) FROM `$merits_table` WHERE user_id = u.user_id)"
    : "0";

$achievements_expr = table_exists_admin($conn, $achievements_table)
    ? "(SELECT COUNT(*) FROM `$achievements_table` WHERE user_id = u.user_id)"
    : "0";

$sql = "
    SELECT
        u.user_id,
        u.username,
        u.student_id,
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
    $sql .= " AND (u.student_id LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_like = "%" . $search . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "sss";
}

$sql .= " ORDER BY u.user_id DESC";

$stmt = mysqli_prepare($conn, $sql);
$result = false;
$filtered_students = 0;

if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $filtered_students = $result ? mysqli_num_rows($result) : 0;
}
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
        .mini-badge {
            background: #ef4444;
            color: white;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.8rem;
            margin-left: 6px;
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
            <a href="verify_achievements.php">
                📥 Verification Inbox
                <?php if ($total_pending > 0): ?>
                    <span class="mini-badge"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="view_history.php">🕘 View History</a>
        </div>

        <a href="../auth/logout.php" class="logout-link" style="margin-top: auto;">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-glass" style="background: linear-gradient(120deg, #1e293b, #4338ca);">
            <p class="hero-label" style="color: #c7d2fe; margin-bottom: 0.5rem; display: block;">System Administrator</p>
            <h1>Welcome, <?php echo htmlspecialchars($username); ?> 🛡️</h1>
            <p>Monitor registered students and quickly access pending verification tasks.</p>
        </div>

        <div class="stats-container">
            <div class="stat-box blue">
                <span class="stat-label">Registered Students</span>
                <div class="stat-number"><?php echo $total_students; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">Student accounts in system</span>
            </div>
            <div class="stat-box teal">
                <span class="stat-label">Total Clubs</span>
                <div class="stat-number"><?php echo $total_clubs; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">All club records</span>
            </div>
            <div class="stat-box green">
                <span class="stat-label">Total Events</span>
                <div class="stat-number"><?php echo $total_events; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">All event records</span>
            </div>
            <div class="stat-box orange">
                <span class="stat-label">Pending Verification</span>
                <div class="stat-number"><?php echo $total_pending; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">
                    <a href="verify_achievements.php" style="color:#4338ca;text-decoration:none;font-weight:700;">Open inbox</a>
                </span>
            </div>
            <div class="stat-box purple">
                <span class="stat-label">Total Achievements</span>
                <div class="stat-number"><?php echo $total_achievements; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">All achievement records</span>
            </div>
            <div class="stat-box blue">
                <span class="stat-label">Total Merits</span>
                <div class="stat-number"><?php echo $total_merits; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">All merit records</span>
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
                    <input type="text" name="search" placeholder="Search by student ID or email" value="<?php echo htmlspecialchars($search); ?>" style="min-width: 260px; padding: 0.8rem 1rem; border: 2px solid var(--border); border-radius: 12px; outline: none;">
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
                            <th style="padding: 1.2rem 1rem;">Student</th>
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
                                $grand_total = (int) $row['total_events'] + (int) $row['total_clubs'] + (int) $row['total_merits'] + (int) $row['total_achievements'];
                                ?>
                                <tr class="table-row-hover" style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1.2rem 1rem; color: var(--text-muted); font-weight: 500;">#<?php echo $row['user_id']; ?></td>
                                    <td style="padding: 1.2rem 1rem;"><strong style="color: var(--dark); font-size: 1.05rem;"><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
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
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
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
                <h2 style="color: var(--dark);">Pending Breakdown</h2>
            </div>

            <div class="module-grid" style="margin-bottom: 0;">
                <div class="module-card-v2">
                    <div class="module-icon-v2">📅</div>
                    <h3>📅 Pending Events</h3>
                    <p><?php echo $pending_events; ?> event record(s) awaiting admin decision.</p>
                    <a href="verify_achievements.php?tab=events" class="btn-open">Open Events</a>
                </div>
                <div class="module-card-v2">
                    <div class="module-icon-v2">👥</div>
                    <h3>👥 Pending Clubs</h3>
                    <p><?php echo $pending_clubs; ?> club record(s) awaiting admin decision.</p>
                    <a href="verify_achievements.php?tab=clubs" class="btn-open">Open Clubs</a>
                </div>
                <div class="module-card-v2">
                    <div class="module-icon-v2">🏆</div>
                    <h3>🏆 Pending Achievements</h3>
                    <p><?php echo $pending_achievements; ?> achievement submission(s) awaiting verification.</p>
                    <a href="verify_achievements.php?tab=achievements" class="btn-open">Open Achievements</a>
                </div>
                <div class="module-card-v2">
                    <div class="module-icon-v2">⏱️</div>
                    <h3>⏱️ Pending Merits</h3>
                    <p><?php echo $pending_merits; ?> merit record(s) awaiting approval.</p>
                    <a href="verify_achievements.php?tab=merits" class="btn-open">Open Merits</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
