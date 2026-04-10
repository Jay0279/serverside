<?php
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: merit.php");
    exit();
}

$merit_id = (int) $_GET['id'];
$error = "";


$sql = "SELECT * FROM merits WHERE merit_id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $merit_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: merit.php");
    exit();
}

$row = mysqli_fetch_assoc($result);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $activity_title = trim($_POST['activity_title']);
    $activity_type = trim($_POST['activity_type']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $hours_contributed = trim($_POST['hours_contributed']);
    $description = trim($_POST['description']);

    if (empty($activity_title) || empty($activity_type) || empty($start_date) || empty($end_date) || empty($hours_contributed)) {
        $error = "Please fill in all required fields.";
    } elseif (!preg_match('/^\d+(\.\d{1})?$/', $hours_contributed)) {
        $error = "Hours must be a non-negative number with at most 1 decimal place.";
    } elseif ($end_date < $start_date) {
        $error = "End date cannot be earlier than start date.";
    } else {
        $update_sql = "UPDATE merits 
                       SET activity_title = ?, activity_type = ?, start_date = ?, end_date = ?, hours_contributed = ?, description = ?, status = 'Pending'
                       WHERE merit_id = ? AND user_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param(
            $update_stmt,
            "ssssdsii",
            $activity_title,
            $activity_type,
            $start_date,
            $end_date,
            $hours_contributed,
            $description,
            $merit_id,
            $user_id
        );

        if (mysqli_stmt_execute($update_stmt)) {
            header("Location: merit.php?success=updated");
            exit();
        } else {
            $error = "Failed to update merit record.";
        }
    }

    // Keep entered values if validation fails
    $row['activity_title'] = $activity_title;
    $row['activity_type'] = $activity_type;
    $row['start_date'] = $start_date;
    $row['end_date'] = $end_date;
    $row['hours_contributed'] = $hours_contributed;
    $row['description'] = $description;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Merit | CCMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="main-body">
    <div class="sidebar">
        <div>
            <h2>CCMS</h2>
            <p class="sidebar-subtitle">Student Portal</p>
        </div>

        <div class="nav-links">
            <a href="../dashboard.php">📊 Dashboard</a>
            <a href="../event_tracker/events.php">📅 Event Tracker</a>
            <a href="../club_tracker/clubs.php">👥 Club Tracker</a>
            <a href="merit.php" class="active">⏱️ Merit Tracker</a>
            <a href="../achievement_tracker/achievements.php">🏆 Achievements</a>
        </div>

        <a href="../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="margin-bottom: 2rem;">
            <div>
                <p class="hero-label">Merit Module</p>
                <h1>Edit Merit Record ✎</h1>
                <p class="hero-text" style="color: var(--text-muted);">Update your contribution hours record.</p>
            </div>
        </div>

        <div class="panel" style="max-width: 850px;">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Edit Merit Record</h2>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background:#fee2e2; color:#991b1b; padding:12px 16px; border-radius:10px; margin-bottom:1rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label>Activity Title *</label>
                        <input type="text" name="activity_title" required
                               style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo htmlspecialchars($row['activity_title']); ?>">
                    </div>

                    <div>
                        <label>Activity Type *</label>
                        <select name="activity_type" required
                                style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                            <option value="">Select Type</option>
                            <option value="Volunteering" <?php if ($row['activity_type'] == "Volunteering") echo "selected"; ?>>Volunteering</option>
                            <option value="Community Service" <?php if ($row['activity_type'] == "Community Service") echo "selected"; ?>>Community Service</option>
                            <option value="Committee Work" <?php if ($row['activity_type'] == "Committee Work") echo "selected"; ?>>Committee Work</option>
                            <option value="Charity Program" <?php if ($row['activity_type'] == "Charity Program") echo "selected"; ?>>Charity Program</option>
                            <option value="Others" <?php if ($row['activity_type'] == "Others") echo "selected"; ?>>Others</option>
                        </select>
                    </div>

                    <div>
                        <label>Start Date *</label>
                        <input type="date" name="start_date" required
                               style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo htmlspecialchars($row['start_date']); ?>">
                    </div>

                    <div>
                        <label>End Date *</label>
                        <input type="date" name="end_date" required
                               style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo htmlspecialchars($row['end_date']); ?>">
                    </div>

                    <div style="grid-column: span 2;">
                        <label>Hours Contributed *</label>
                        <input type="number" step="0.1" min="0" name="hours_contributed" required
                               style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo htmlspecialchars($row['hours_contributed']); ?>">
                    </div>

                    <div style="grid-column: span 2;">
                        <label>Description</label>
                        <textarea name="description" rows="5"
                                  style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"><?php echo htmlspecialchars($row['description']); ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display:flex; gap:10px;">
                    <button type="submit" class="btn-primary">Update Record</button>
                    <a href="merit.php" class="btn-disabled" style="text-decoration:none;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
