<?php
include '../config.php'; // Updated Path!

// Kick them out if they aren't logged in OR if they aren't an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php"); // Updated Path!
    exit();
}

$username = $_SESSION['username'];

// Fetch all students and count their achievements
$sql = "SELECT u.id, u.username, u.email, 
        (SELECT COUNT(*) FROM achievements WHERE user_id = u.id) as total_achievements
        FROM users u 
        WHERE u.role = 'student'
        ORDER BY u.id DESC";
$result = mysqli_query($conn, $sql);

// Count total students
$total_students = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CCMS</title>
    <link rel="stylesheet" href="../style.css"> </head>
<body class="main-body">
    <div class="sidebar" style="background: linear-gradient(180deg, #1e1b4b, #312e81);">
        <div>
            <h2>CCMS Admin</h2>
            <p class="sidebar-subtitle">Staff Portal</p>
        </div>

        <div class="nav-links">
            <a href="admin_dashboard.php" class="active">👥 User Management</a>
            <a href="verify_achievements.php">✅ Verification Inbox</a>
        </div>

        <a href="../auth/logout.php" class="logout-link">Log Out</a> </div>

    <div class="content">
        <div class="hero-banner" style="background: linear-gradient(135deg, #1e1b4b, #4338ca); color: white;">
            <div>
                <p class="hero-label" style="color: #c7d2fe;">System Administrator</p>
                <h1 style="color: white;">Welcome, <?php echo htmlspecialchars($username); ?> 🛡️</h1>
                <p style="opacity: 0.9; margin-top: 0.5rem;">Oversee student accounts and monitor system-wide co-curricular records.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-title">Registered Students</span>
                <h3><?php echo $total_students; ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Total active accounts</p>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Student Usage Summary</h2>
            </div>

            <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead style="background: var(--bg-light); border-bottom: 2px solid var(--border);">
                        <tr>
                            <th style="padding: 1rem;">ID</th>
                            <th style="padding: 1rem;">Student Name</th>
                            <th style="padding: 1rem;">Email</th>
                            <th style="padding: 1rem;">Total Achievements</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_students > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem; color: var(--text-muted);">#<?php echo $row['id']; ?></td>
                                    <td style="padding: 1rem;"><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td style="padding: 1rem;">
                                        <span class="badge badge-success" style="padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: bold;">
                                            <?php echo $row['total_achievements']; ?> Records
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">No students registered yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>