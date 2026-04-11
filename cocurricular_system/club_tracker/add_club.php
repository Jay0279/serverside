<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
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
                <h1>Add Club Record 👥</h1>
                <p class="hero-text">Record your club or society membership details.</p>
            </div>
        </div>

        <div class="panel" style="max-width: 900px;">
            <div class="panel-header">
                <h2>New Club Record</h2>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid-2">
                    <div class="input-group">
                        <label>Club Name *</label>
                        <input class="module-input" type="text" name="club_name" required
                               value="<?php echo isset($_POST['club_name']) ? htmlspecialchars($_POST['club_name']) : ''; ?>">
                    </div>

                    <div class="input-group">
                        <label>Club Category *</label>
                        <select class="module-select" name="club_category" required>
                            <option value="">Select Category</option>
                            <option value="Academic" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Academic") echo "selected"; ?>>Academic</option>
                            <option value="Sports" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Sports") echo "selected"; ?>>Sports</option>
                            <option value="Cultural" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Cultural") echo "selected"; ?>>Cultural</option>
                            <option value="Volunteer" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Volunteer") echo "selected"; ?>>Volunteer</option>
                            <option value="Leadership" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Leadership") echo "selected"; ?>>Leadership</option>
                            <option value="Others" <?php if (isset($_POST['club_category']) && $_POST['club_category'] == "Others") echo "selected"; ?>>Others</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Role / Position *</label>
                        <input class="module-input" type="text" name="role_position" required
                               value="<?php echo isset($_POST['role_position']) ? htmlspecialchars($_POST['role_position']) : ''; ?>">
                    </div>

                    <div class="input-group">
                        <label>Membership Status *</label>
                        <select class="module-select" name="membership_status" required>
                            <option value="">Select Status</option>
                            <option value="Active" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] == "Active") echo "selected"; ?>>Active</option>
                            <option value="Inactive" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] == "Inactive") echo "selected"; ?>>Inactive</option>
                            <option value="Completed" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] == "Completed") echo "selected"; ?>>Completed</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Join Date *</label>
                        <input class="module-input" type="date" name="join_date" required
                               value="<?php echo isset($_POST['join_date']) ? htmlspecialchars($_POST['join_date']) : ''; ?>">
                    </div>

                    <div class="input-group">
                        <label>End Date</label>
                        <input class="module-input" type="date" name="end_date"
                               value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                    </div>

                    <div class="input-group full-span">
                        <label>Remarks</label>
                        <textarea class="module-textarea" name="remarks"><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
                    </div>
                </div>

                <div class="module-actions">
                    <button type="submit" class="btn-primary">Save Record</button>
                    <a href="clubs.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>