<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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
        <div class="hero-banner">
            <div>
                <p class="hero-label">Club Module</p>
                <h1>Club Record Details 👁</h1>
                <p class="hero-text">View your club membership information.</p>
            </div>
        </div>

        <div class="panel" style="max-width: 900px;">
            <div class="panel-header">
                <h2><?php echo htmlspecialchars($row['club_name']); ?></h2>
            </div>

            <div class="details-grid">
                <div class="details-box"><strong>Club Name:</strong><br><?php echo htmlspecialchars($row['club_name']); ?></div>
                <div class="details-box"><strong>Category:</strong><br><?php echo htmlspecialchars($row['club_category']); ?></div>
                <div class="details-box"><strong>Role / Position:</strong><br><?php echo htmlspecialchars($row['role_position']); ?></div>
                <div class="details-box"><strong>Status:</strong><br><?php echo htmlspecialchars($row['membership_status']); ?></div>
                <div class="details-box"><strong>Join Date:</strong><br><?php echo htmlspecialchars($row['join_date']); ?></div>
                <div class="details-box"><strong>End Date:</strong><br><?php echo !empty($row['end_date']) ? htmlspecialchars($row['end_date']) : '-'; ?></div>
                <div class="details-box full-span"><strong>Remarks:</strong><br><?php echo !empty($row['remarks']) ? nl2br(htmlspecialchars($row['remarks'])) : '-'; ?></div>
            </div>

            <div class="module-actions">
                <a href="edit_club.php?id=<?php echo $row['club_id']; ?>" class="btn-primary">Edit Record</a>
                <a href="clubs.php" class="btn-secondary">Back</a>
            </div>
        </div>
    </div>
</body>
</html>