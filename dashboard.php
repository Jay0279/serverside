<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Dynamic Greeting based on time
date_default_timezone_set('Asia/Kuala_Lumpur');
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}

// Helper functions for DB stats
function tableExists($conn, $table_name) {
    $safe_table_name = mysqli_real_escape_string($conn, $table_name);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$safe_table_name}'");
    return $result && mysqli_num_rows($result) > 0;
}

function getSingleCount($conn, $sql, $user_id, $field = 'total') {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return 0;
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (float) ($row[$field] ?? 0);
}

// Check tables
$has_events_table = tableExists($conn, 'events');
$has_merits_table = tableExists($conn, 'merits');
$has_achievements_table = tableExists($conn, 'achievements');
$has_clubs_table = tableExists($conn, 'clubs');

// Fetch Stats
$total_events = $has_events_table ? getSingleCount($conn, 'SELECT COUNT(*) AS total FROM events WHERE user_id = ?', $user_id) : 0;
$total_merits = $has_merits_table ? getSingleCount($conn, "SELECT COALESCE(SUM(hours_contributed), 0) AS total_merits FROM merits WHERE user_id = ? AND status = 'Completed'", $user_id, 'total_merits') : 0;
$total_achievements = $has_achievements_table ? getSingleCount($conn, 'SELECT COUNT(*) AS total FROM achievements WHERE user_id = ?', $user_id) : 0;
$total_clubs = $has_clubs_table ? getSingleCount($conn, 'SELECT COUNT(*) AS total FROM clubs WHERE user_id = ?', $user_id) : 0;

// Securely Fetch Recent Activity using Prepared Statements
$recent_queries = [];
$params = [];
$types = "";

if ($has_events_table) {
    $recent_queries[] = "SELECT 'event' AS module, '📅' AS icon, event_title AS title, event_status AS status, event_date AS date FROM events WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}
if ($has_achievements_table) {
    $recent_queries[] = "SELECT 'achievement' AS module, '🏆' AS icon, title AS title, status AS status, achievement_date AS date FROM achievements WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}
if ($has_merits_table) {
    $recent_queries[] = "SELECT 'merit' AS module, '⏱️' AS icon, activity_title AS title, status AS status, start_date AS date FROM merits WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}
if ($has_clubs_table) {
    $recent_queries[] = "SELECT 'club' AS module, '👥' AS icon, club_name AS title, membership_status AS status, join_date AS date FROM clubs WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$recent_result = false;
if (!empty($recent_queries)) {
    $recent_sql = implode(' UNION ALL ', $recent_queries) . ' ORDER BY date DESC LIMIT 4';
    $stmt = mysqli_prepare($conn, $recent_sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $recent_result = mysqli_stmt_get_result($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CCMS</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="main-body">
    
    <div class="sidebar">
        <div>
            <h2>CCMS</h2>
            <p class="sidebar-subtitle">Student Portal</p>
        </div>

        <div class="nav-links">
            <a href="dashboard.php" class="active">📊 Dashboard</a>
            <a href="cocurricular_system/event_tracker/events.php">📅 Event Tracker</a>
            <a href="cocurricular_system/club_tracker/clubs.php">👥 Club Tracker</a>
            <a href="cocurricular_system/merit_tracker/merit.php">⏱️ Merit Tracker</a>
            <a href="cocurricular_system/achievement_tracker/achievements.php">🏆 Achievements</a>
        </div>

        <a href="auth/logout.php" class="logout-link" style="margin-top: auto;">Log Out</a>
    </div>

    <div class="content">
        
        <div class="hero-glass">
            <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($username); ?>! 👋</h1>
            <p>Welcome to your central hub. Track your involvement, log your hours, and organize your achievements seamlessly.</p>
        </div>

        <div class="stats-container">
            <div class="stat-box blue">
                <span class="stat-label">Events Attended</span>
                <div class="stat-number"><?php echo $total_events; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">Programmes & Talks</span>
            </div>

            <div class="stat-box teal">
                <span class="stat-label">Active Clubs</span>
                <div class="stat-number"><?php echo $total_clubs; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">Current Memberships</span>
            </div>

            <div class="stat-box green">
                <span class="stat-label">Merit Hours</span>
                <div class="stat-number"><?php echo number_format($total_merits, 1); ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">Approved Contribution</span>
            </div>

            <div class="stat-box purple">
                <span class="stat-label">Total Achievements</span>
                <div class="stat-number"><?php echo $total_achievements; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">Recorded Recognitions</span>
            </div>
        </div>

        <div class="module-grid">
            <div class="module-card-v2">
                <div class="module-icon-v2">📅</div>
                <h3>Event Tracker</h3>
                <p>Track formal programmes, workshops, competitions, talks, and volunteering records with powerful filters.</p>
                <a href="cocurricular_system/event_tracker/events.php" class="btn-open">Open Module</a>
            </div>

            <div class="module-card-v2">
                <div class="module-icon-v2">👥</div>
                <h3>Club Tracker</h3>
                <p>Manage club memberships, committee positions, and student roles.</p>
                <a href="cocurricular_system/club_tracker/clubs.php" class="btn-open">Open Module</a>
            </div>

            <div class="module-card-v2">
                <div class="module-icon-v2">⏱️</div>
                <h3>Merit Tracker</h3>
                <p>Record and monitor your community service, committee work, and contribution hours.</p>
                <a href="cocurricular_system/merit_tracker/merit.php" class="btn-open">Open Module</a>
            </div>

            <div class="module-card-v2">
                <div class="module-icon-v2">🏆</div>
                <h3>Achievement Tracker</h3>
                <p>Store your awards, certificates, and competition results securely for admin verification.</p>
                <a href="cocurricular_system/achievement_tracker/achievements.php" class="btn-open">Open Module</a>
            </div>
        </div>

        <div class="recent-section">
            <h2>Recent Activity</h2>
            <?php if ($recent_result && mysqli_num_rows($recent_result) > 0): ?>
                <div class="activity-list">
                    <?php while ($row = mysqli_fetch_assoc($recent_result)): 
                        // Determine badge color
                        $status_class = 'status-upcoming';
                        if (stripos($row['status'], 'completed') !== false) $status_class = 'status-completed';
                        if (stripos($row['status'], 'pending') !== false) $status_class = 'status-pending';
                    ?>
                        <div class="activity-item">
                            <div class="activity-left">
                                <div class="activity-icon"><?php echo $row['icon']; ?></div>
                                <div class="activity-details">
                                    <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($row['module']); ?> module • <?php echo date('d M Y', strtotime($row['date'])); ?></p>
                                </div>
                            </div>
                            <div class="activity-status <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                    <h3>No recent activity</h3>
                    <p>Your recent event, merit, and achievement submissions will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>