<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Placeholders until the other modules are fully wired into this root dashboard.
$total_events = 0;
$total_clubs = 0;
$total_merits = 0;

$total_sql = "SELECT COUNT(*) AS total FROM achievements WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $total_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$total_result = mysqli_stmt_get_result($stmt);
$total_row = mysqli_fetch_assoc($total_result);
$total_achievements = $total_row['total'];

$recent_sql = "SELECT * FROM achievements WHERE user_id = ? ORDER BY achievement_date DESC LIMIT 4";
$stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_result = mysqli_stmt_get_result($stmt);
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
                <p class="hero-text" style="color: var(--text-muted); margin-top: 0.5rem;">Manage your student involvement, track hours, and organize your achievements across all modules.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-title">Events Attended</span>
                <h3><?php echo $total_events; ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Programmes and talks</p>
            </div>

            <div class="stat-card green">
                <span class="stat-title">Active Clubs</span>
                <h3><?php echo $total_clubs; ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Current memberships</p>
            </div>

            <div class="stat-card orange">
                <span class="stat-title">Merit Hours</span>
                <h3><?php echo $total_merits; ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Total contribution</p>
            </div>

            <div class="stat-card purple">
                <span class="stat-title">Total Achievements</span>
                <h3><?php echo $total_achievements; ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Recorded recognitions</p>
            </div>
        </div>

        <div class="card-grid">
            <div class="module-card">
                <div class="module-icon">Event</div>
                <h3>Event Tracker</h3>
                <p>Track formal programmes, workshops, competitions, and talks.</p>
                <a href="cocurricular_system/event_tracker/events.php" class="btn-primary" style="margin-top: auto; text-align: center;">Open Module</a>
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
                <a href="cocurricular_system/achievement_tracker/achievements.php" style="color: var(--primary); text-decoration: none; font-weight: bold;">View all</a>
            </div>

            <?php if ($recent_result && mysqli_num_rows($recent_result) > 0): ?>
                <div class="recent-list">
                    <?php while ($row = mysqli_fetch_assoc($recent_result)): ?>
                        <div class="recent-item">
                            <div class="recent-badge">Award</div>
                            <div>
                                <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                                <p>Achievement Module • <?php echo htmlspecialchars($row['level'] ?? 'N/A'); ?></p>
                            </div>
                            <span class="recent-date"><?php echo htmlspecialchars($row['achievement_date'] ?? 'No Date'); ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem 0;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">CCMS</div>
                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No recent activity</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Your recent events, clubs, and achievements will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
