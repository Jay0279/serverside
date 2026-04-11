<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

function club_column_exists_view($conn, $column_name)
{
    $safe_column = mysqli_real_escape_string($conn, $column_name);
    $sql = "SHOW COLUMNS FROM clubs LIKE '$safe_column'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

$has_review_status = club_column_exists_view($conn, 'review_status');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: clubs.php");
    exit();
}

$club_id = (int) $_GET['id'];

$sql = "SELECT * FROM clubs WHERE club_id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $club_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: clubs.php");
    exit();
}

$row = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Club | CCMS</title>
    <link rel="stylesheet" href="../../style.css?v=<?php echo time(); ?>">
</head>
<body class="main-body">
    <div class="sidebar">
        <div>
            <h2>CCMS</h2>
            <p class="sidebar-subtitle">Student Portal</p>
        </div>

        <div class="nav-links">
            <a href="../../dashboard.php">📊 Dashboard</a>
            <a href="../event_tracker/events.php">📅 Event Tracker</a>
            <a href="clubs.php" class="active">👥 Club Tracker</a>
            <a href="../merit_tracker/merit.php">⏱️ Merit Tracker</a>
            <a href="../achievement_tracker/achievements.php">🏆 Achievements</a>
        </div>

        <a href="../../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner merit-hero-banner">
            <div>
                <p class="hero-label">CLUB MODULE</p>
                <h1>Club Record Details 👁</h1>
                <p class="hero-text merit-hero-text">Review your saved membership information in a clean detail view.</p>
            </div>
        </div>

        <div class="panel merit-main-panel" style="max-width: 980px;">
            <div class="panel-header merit-panel-header-better">
                <div>
                    <h2 class="merit-panel-title"><?php echo htmlspecialchars($row['club_name']); ?></h2>
                    <p class="merit-panel-subtitle">Detailed information for this club membership record.</p>
                </div>
            </div>

            <div class="stats-container merit-stats-container" style="margin-bottom: 1.5rem;">
                <div class="stat-box blue">
                    <span class="stat-label">Club Category</span>
                    <div class="stat-number" style="font-size: 1.4rem;"><?php echo htmlspecialchars($row['club_category']); ?></div>
                    <span class="stat-label merit-stat-subtext">Selected category</span>
                </div>

                <div class="stat-box green">
                    <span class="stat-label">Role</span>
                    <div class="stat-number" style="font-size: 1.4rem;"><?php echo htmlspecialchars($row['role_position']); ?></div>
                    <span class="stat-label merit-stat-subtext">Membership position</span>
                </div>

                <div class="stat-box orange">
                    <span class="stat-label">Membership</span>
                    <div class="stat-number" style="font-size: 1.4rem;"><?php echo htmlspecialchars($row['membership_status']); ?></div>
                    <span class="stat-label merit-stat-subtext">Current membership state</span>
                </div>

                <?php if ($has_review_status): ?>
                    <div class="stat-box purple">
                        <span class="stat-label">Review Status</span>
                        <div class="stat-number" style="font-size: 1.4rem;"><?php echo htmlspecialchars($row['review_status']); ?></div>
                        <span class="stat-label merit-stat-subtext">Admin verification state</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-wrapper merit-table-wrapper">
                <table class="record-table merit-record-table better-merit-table">
                    <tbody>
                        <tr>
                            <th style="width: 220px;">Club Name</th>
                            <td><?php echo htmlspecialchars($row['club_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Club Category</th>
                            <td><?php echo htmlspecialchars($row['club_category']); ?></td>
                        </tr>
                        <tr>
                            <th>Role / Position</th>
                            <td><?php echo htmlspecialchars($row['role_position']); ?></td>
                        </tr>
                        <tr>
                            <th>Membership Status</th>
                            <td><?php echo htmlspecialchars($row['membership_status']); ?></td>
                        </tr>
                        <tr>
                            <th>Join Date</th>
                            <td><?php echo htmlspecialchars($row['join_date']); ?></td>
                        </tr>
                        <tr>
                            <th>End Date</th>
                            <td><?php echo !empty($row['end_date']) ? htmlspecialchars($row['end_date']) : '-'; ?></td>
                        </tr>
                        <?php if ($has_review_status): ?>
                            <tr>
                                <th>Review Status</th>
                                <td><?php echo htmlspecialchars($row['review_status']); ?></td>
                            </tr>
                            <tr>
                                <th>Admin Remark</th>
                                <td><?php echo !empty($row['admin_remark']) ? nl2br(htmlspecialchars($row['admin_remark'])) : '-'; ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Remarks</th>
                            <td><?php echo !empty($row['remarks']) ? nl2br(htmlspecialchars($row['remarks'])) : '-'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="module-actions">
                <a href="edit_club.php?id=<?php echo $row['club_id']; ?>" class="btn-primary">Edit Record</a>
                <a href="clubs.php" class="btn-secondary">Back</a>
            </div>
        </div>
    </div>
</body>
</html>