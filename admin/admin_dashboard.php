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
    <link rel="stylesheet" href="../style.css">
</head>
<body class="main-body">
    <div class="sidebar" style="background: linear-gradient(180deg, #1e1b4b, #312e81);">
        <div>
            <h2>CCMS Admin</h2>
            <p class="sidebar-subtitle">Staff Portal</p>
        </div>

        <div class="nav-links">
            <a href="admin_dashboard.php" class="active">User Management</a>
            <a href="verify_achievements.php">Verification Inbox</a>
        </div>

        <a href="../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="background: linear-gradient(135deg, #1e1b4b, #4338ca); color: white;">
            <div>
                <p class="hero-label" style="color: #c7d2fe;">System Administrator</p>
                <h1 style="color: white;">Welcome, <?php echo htmlspecialchars($username); ?></h1>
                <p style="opacity: 0.9; margin-top: 0.5rem;">Monitor all registered students and review overall system usage across events, clubs, merits, and achievements.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-title">Registered Students</span>
                <h3><?php echo $total_students; ?></h3>
                <p class="stat-note">Student accounts in system</p>
            </div>

            <div class="stat-card green">
                <span class="stat-title">Total Events</span>
                <h3><?php echo $total_events; ?></h3>
                <p class="stat-note">All event records</p>
            </div>

            <div class="stat-card orange">
                <span class="stat-title">Total Merit Records</span>
                <h3><?php echo $total_merits; ?></h3>
                <p class="stat-note">All merit submissions</p>
            </div>

            <div class="stat-card purple">
                <span class="stat-title">Total Achievements</span>
                <h3><?php echo $total_achievements; ?></h3>
                <p class="stat-note">Recognition records</p>
            </div>

            <div class="stat-card" style="border-left-color: #14b8a6;">
                <span class="stat-title">Total Clubs</span>
                <h3><?php echo $total_clubs; ?></h3>
                <p class="stat-note">Club membership records</p>
            </div>
        </div>

        <div class="panel" style="margin-bottom: 2rem;">
            <div class="panel-header" style="gap: 1rem; flex-wrap: wrap;">
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
                        style="min-width: 260px; padding: 0.8rem 1rem; border: 2px solid var(--border); border-radius: 12px; outline: none;">
                    <button type="submit" class="btn-primary">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="admin_dashboard.php" class="btn-disabled" style="text-decoration: none; display: inline-block; cursor: pointer;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 16px; border: 1px solid var(--border);">
                <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 980px;">
                    <thead style="background: var(--bg-light); border-bottom: 2px solid var(--border);">
                        <tr>
                            <th style="padding: 1rem;">ID</th>
                            <th style="padding: 1rem;">Student Name</th>
                            <th style="padding: 1rem;">Email</th>
                            <th style="padding: 1rem; text-align: center;">Events</th>
                            <th style="padding: 1rem; text-align: center;">Clubs</th>
                            <th style="padding: 1rem; text-align: center;">Merits</th>
                            <th style="padding: 1rem; text-align: center;">Achievements</th>
                            <th style="padding: 1rem; text-align: center;">Total Records</th>
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
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem; color: var(--text-muted);">#<?php echo $row['id']; ?></td>
                                    <td style="padding: 1rem;"><strong style="color: var(--dark);"><?php echo htmlspecialchars($row['username']); ?></strong></td>
                                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="display: inline-block; min-width: 44px; padding: 0.35rem 0.75rem; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-weight: 700;"><?php echo $row['total_events']; ?></span>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="display: inline-block; min-width: 44px; padding: 0.35rem 0.75rem; border-radius: 999px; background: #dcfce7; color: #15803d; font-weight: 700;"><?php echo $row['total_clubs']; ?></span>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="display: inline-block; min-width: 44px; padding: 0.35rem 0.75rem; border-radius: 999px; background: #fef3c7; color: #b45309; font-weight: 700;"><?php echo $row['total_merits']; ?></span>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="display: inline-block; min-width: 44px; padding: 0.35rem 0.75rem; border-radius: 999px; background: #ede9fe; color: #6d28d9; font-weight: 700;"><?php echo $row['total_achievements']; ?></span>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="display: inline-block; min-width: 58px; padding: 0.45rem 0.8rem; border-radius: 999px; background: #111827; color: white; font-weight: 700;"><?php echo $grand_total; ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="padding: 2.5rem; text-align: center;">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.8rem;">Admin</div>
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
            <div class="panel-header">
                <h2 style="color: var(--dark);">Admin Notes</h2>
            </div>

            <div class="card-grid" style="margin-bottom: 0;">
                <div class="module-card">
                    <div class="module-icon">Search</div>
                    <h3>Search Support</h3>
                    <p>Find students quickly by username or email to review their system activity summary.</p>
                </div>

                <div class="module-card">
                    <div class="module-icon">Stats</div>
                    <h3>System Monitoring</h3>
                    <p>Review overall totals across event, club, merit, and achievement modules from one place.</p>
                </div>

                <div class="module-card">
                    <div class="module-icon">Links</div>
                    <h3>Integrated Overview</h3>
                    <p>See how centralized login and user-linked module records come together in one dashboard.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
