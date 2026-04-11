<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $club_name = trim($_POST['club_name']);
    $club_category = trim($_POST['club_category']);
    $role_position = trim($_POST['role_position']);
    $join_date = $_POST['join_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $membership_status = trim($_POST['membership_status']);
    $remarks = trim($_POST['remarks']);

    if (empty($club_name) || empty($club_category) || empty($role_position) || empty($join_date) || empty($membership_status)) {
        $error = "Please fill in all required fields.";
    } elseif (!empty($end_date) && $end_date < $join_date) {
        $error = "End date cannot be earlier than join date.";
    } else {
        $sql = "INSERT INTO clubs (user_id, club_name, club_category, role_position, join_date, end_date, membership_status, remarks, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isssssss", $user_id, $club_name, $club_category, $role_position, $join_date, $end_date, $membership_status, $remarks);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: clubs.php?success=added");
            exit();
        } else {
            $error = "Failed to add club record.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Club | CCMS</title>
    <link rel="stylesheet" href="../../style.css">
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
        <div class="hero-banner" style="margin-bottom: 2rem;">
            <div>
                <p class="hero-label">Club Module</p>
                <h1>Add Club Record 👥</h1>
                <p class="hero-text" style="color: var(--text-muted);">Record your club or society membership details.</p>
            </div>
        </div>

        <div class="panel" style="max-width: 850px;">
            <div class="panel-header">
                <h2 style="color: var(--dark);">New Club Record</h2>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background:#fee2e2; color:#991b1b; padding:12px 16px; border-radius:10px; margin-bottom:1rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label>Club Name *</label>
                        <input type="text" name="club_name" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['club_name']) ? htmlspecialchars($_POST['club_name']) : ''; ?>">
                    </div>

                    <div>
                        <label>Club Category *</label>
                        <select name="club_category" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                            <option value="">Select Category</option>
                            <option value="Academic" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Academic") echo "selected"; ?>>Academic</option>
                            <option value="Sports" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Sports") echo "selected"; ?>>Sports</option>
                            <option value="Cultural" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Cultural") echo "selected"; ?>>Cultural</option>
                            <option value="Volunteer" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Volunteer") echo "selected"; ?>>Volunteer</option>
                            <option value="Leadership" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Leadership") echo "selected"; ?>>Leadership</option>
                            <option value="Others" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Others") echo "selected"; ?>>Others</option>
                        </select>
                    </div>

                    <div>
                        <label>Role / Position *</label>
                        <input type="text" name="role_position" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['role_position']) ? htmlspecialchars($_POST['role_position']) : ''; ?>">
                    </div>

                    <div>
                        <label>Membership Status *</label>
                        <select name="membership_status" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                            <option value="">Select Status</option>
                            <option value="Active" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] == "Active") echo "selected"; ?>>Active</option>
                            <option value="Inactive" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] == "Inactive") echo "selected"; ?>>Inactive</option>
                            <option value="Completed" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] == "Completed") echo "selected"; ?>>Completed</option>
                        </select>
                    </div>

                    <div>
                        <label>Join Date *</label>
                        <input type="date" name="join_date" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['join_date']) ? htmlspecialchars($_POST['join_date']) : ''; ?>">
                    </div>

                    <div>
                        <label>End Date</label>
                        <input type="date" name="end_date" style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                    </div>

                    <div style="grid-column: span 2;">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="5" style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display:flex; gap:10px;">
                    <button type="submit" class="btn-primary">Save Record</button>
                    <a href="clubs.php" class="btn-disabled" style="text-decoration:none;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>