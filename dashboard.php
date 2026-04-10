<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

function tableExists($conn, $table_name)
{
    $safe_table_name = mysqli_real_escape_string($conn, $table_name);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$safe_table_name}'");
    return $result && mysqli_num_rows($result) > 0;
}

function getSingleCount($conn, $sql, $user_id, $field = 'total')
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row[$field] ?? 0);
}

$has_events_table = tableExists($conn, 'events');
$has_merits_table = tableExists($conn, 'merits');
$has_achievements_table = tableExists($conn, 'achievements');
$has_clubs_table = tableExists($conn, 'clubs');

$total_events = $has_events_table
    ? getSingleCount($conn, 'SELECT COUNT(*) AS total FROM events WHERE user_id = ?', $user_id)
    : 0;

$total_clubs = $has_clubs_table
    ? getSingleCount($conn, 'SELECT COUNT(*) AS total FROM clubs WHERE user_id = ?', $user_id)
    : 0;

$total_merits = $has_merits_table
    ? getSingleCount(
        $conn,
        "SELECT COALESCE(SUM(hours_contributed), 0) AS total_merits FROM merits WHERE user_id = ? AND status = 'Completed'",
        $user_id,
        'total_merits'
    )
    : 0;

$total_achievements = $has_achievements_table
    ? getSingleCount($conn, 'SELECT COUNT(*) AS total FROM achievements WHERE user_id = ?', $user_id)
    : 0;

$recent_queries = [];
$recent_types = '';
$recent_params = [];

if ($has_events_table) {
    $recent_queries[] = "
        SELECT
            'event' AS module_name,
            'Event' AS module_icon,
            event_title AS title,
            participation_role AS detail,
            event_date AS activity_date
        FROM events
        WHERE user_id = ?
    ";
    $recent_types .= 'i';
    $recent_params[] = $user_id;
}

if ($has_achievements_table) {
    $recent_queries[] = "
        SELECT
            'achievement' AS module_name,
            'Award' AS module_icon,
            title AS title,
            COALESCE(level, 'N/A') AS detail,
            achievement_date AS activity_date
        FROM achievements
        WHERE user_id = ?
    ";
    $recent_types .= 'i';
    $recent_params[] = $user_id;
}

if ($has_merits_table) {
    $recent_queries[] = "
        SELECT
            'merit' AS module_name,
            'Merit' AS module_icon,
            activity_title AS title,
            activity_type AS detail,
            start_date AS activity_date
        FROM merits
        WHERE user_id = ?
    ";
    $recent_types .= 'i';
    $recent_params[] = $user_id;
}

$recent_result = false;
if (!empty($recent_queries)) {
    $recent_sql = implode(' UNION ALL ', $recent_queries) . ' ORDER BY activity_date DESC LIMIT 6';
    $stmt = mysqli_prepare($conn, $recent_sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $recent_types, ...$recent_params);
        mysqli_stmt_execute($stmt);
        $recent_result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CCMS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="main-body">
    <div class="sidebar">
        <div>
            <h2>CCMS</h2>
            <p class="sidebar-subtitle">Student Portal</p>
        </div>

        <div class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="cocurricular_system/event_tracker/events.php">Event Tracker</a>
            <a href="#">Club Tracker</a>
            <a href="cocurricular_system/merit_tracker/merit.php">Merit Tracker</a>
            <a href="cocurricular_system/achievement_tracker/achievements.php">Achievements</a>
        </div>

        <a href="auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner">
            <div>
                <p class="hero-label">Central Hub</p>
                <h1>Welcome back, <?php echo htmlspecialchars($username); ?></h1>
                <p class="hero-text" style="margin-top: 0.5rem;">Manage your student involvement, track hours, and organize your achievements across all modules.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-title">Events Attended</span>
                <h3><?php echo $total_events; ?></h3>
                <p class="stat-note">Programmes and talks</p>
            </div>

            <div class="stat-card green">
                <span class="stat-title">Active Clubs</span>
                <h3><?php echo $total_clubs; ?></h3>
                <p class="stat-note">Current memberships</p>
            </div>

            <div class="stat-card orange">
                <span class="stat-title">Merit Hours</span>
                <h3><?php echo $total_merits; ?></h3>
                <p class="stat-note">Total contribution</p>
            </div>

            <div class="stat-card purple">
                <span class="stat-title">Total Achievements</span>
                <h3><?php echo $total_achievements; ?></h3>
                <p class="stat-note">Recorded recognitions</p>
            </div>
        </div>

        <div class="card-grid">
            <div class="module-card highlight">
                <div class="module-icon">Event</div>
                <h3>Event Tracker</h3>
                <p>Track formal programmes, workshops, competitions, talks, and volunteering records with filters and summaries.</p>
                <a href="cocurricular_system/event_tracker/events.php" class="btn-primary" style="margin-top: auto;">Open Module</a>
            </div>

            <div class="module-card">
                <div class="module-icon">Club</div>
                <h3>Club Tracker</h3>
                <p>Manage club memberships, committee positions, and student roles.</p>
                <a href="#" class="btn-disabled" style="margin-top: auto; text-align: center;">In Progress</a>
            </div>

            <div class="module-card">
                <div class="module-icon">Merit</div>
                <h3>Merit Tracker</h3>
                <p>Record contribution hours, volunteering, and service participation.</p>
                <a href="cocurricular_system/merit_tracker/merit.php" class="btn-primary" style="margin-top: auto; text-align: center;">Open Module</a>
            </div>

            <div class="module-card">
                <div class="module-icon">Award</div>
                <h3>Achievement Tracker</h3>
                <p>Store awards, certificates, competition results, and recognitions.</p>
                <a href="cocurricular_system/achievement_tracker/achievements.php" class="btn-primary" style="margin-top: auto;">Open Module</a>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Recent System Activity</h2>
                <a href="cocurricular_system/event_tracker/events.php" style="color: var(--primary); text-decoration: none; font-weight: bold;">View events</a>
            </div>

            <?php if ($recent_result && mysqli_num_rows($recent_result) > 0): ?>
                <div class="recent-list">
                    <?php while ($row = mysqli_fetch_assoc($recent_result)): ?>
                        <div class="recent-item">
                            <div class="recent-badge"><?php echo htmlspecialchars($row['module_icon']); ?></div>
                            <div>
                                <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                                <p><?php echo ucfirst(htmlspecialchars($row['module_name'])); ?> Module • <?php echo htmlspecialchars($row['detail'] ?? 'N/A'); ?></p>
                            </div>
                            <span class="recent-date"><?php echo !empty($row['activity_date']) ? htmlspecialchars(date('d M Y', strtotime($row['activity_date']))) : 'No Date'; ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem 0;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">CCMS</div>
                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No recent activity</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Your recent events, clubs, merits, and achievements will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
