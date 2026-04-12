<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$roles = ['Participant', 'Volunteer', 'Committee', 'Facilitator', 'Representative', 'Speaker', 'Organizer'];
$roleMeritPoints = [
    'Participant' => 5,
    'Volunteer' => 8,
    'Committee' => 10,
    'Facilitator' => 12,
    'Representative' => 12,
    'Speaker' => 15,
    'Organizer' => 20,
];

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $activity_title = trim($_POST['activity_title']);
    $activity_type = trim($_POST['activity_type']);
    $participation_role = trim($_POST['participation_role'] ?? 'Participant');
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $hours_contributed = trim($_POST['hours_contributed']);
    $submitted_merit = trim($_POST['merit_points'] ?? '');
    $description = trim($_POST['description']);
    $auto_merit_points = $roleMeritPoints[$participation_role] ?? 5;
    $merit_points = is_numeric($submitted_merit) ? (int) $submitted_merit : $auto_merit_points;

    if (empty($activity_title) || empty($activity_type) || empty($start_date) || empty($end_date) || empty($hours_contributed)) {
        $error = "Please fill in all required fields.";
    } elseif (!preg_match('/^\d+(\.\d{1})?$/', $hours_contributed)) {
        $error = "Hours must be a non-negative number with at most 1 decimal place.";
    } elseif (!in_array($participation_role, $roles, true)) {
        $error = "Please choose a valid participation role.";
    } elseif ($merit_points < 0) {
        $error = "Merit points must be a non-negative number.";
    } elseif ($end_date < $start_date) {
        $error = "End date cannot be earlier than start date.";
    } else {
        $sql = "INSERT INTO merits (user_id, activity_title, activity_type, start_date, end_date, hours_contributed, merit_points, description, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issssdis", $user_id, $activity_title, $activity_type, $start_date, $end_date, $hours_contributed, $merit_points, $description);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: merit.php?success=added");
            exit();
        } else {
            $error = "Failed to add merit record.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Merit | CCMS</title>
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
            <a href="../club_tracker/clubs.php">👥 Club Tracker</a>
            <a href="merit.php" class="active">⏱️ Merit Tracker</a>
            <a href="../achievement_tracker/achievements.php">🏆 Achievements</a>
        </div>

        <a href="../../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="margin-bottom: 2rem;">
            <div>
                <p class="hero-label">Merit Module</p>
                <h1>Add Merit Record ⏱️</h1>
                <p class="hero-text" style="color: var(--text-muted);">Record your co-curricular contribution hours.</p>
            </div>
        </div>

        <div class="panel" style="max-width: 850px;">
            <div class="panel-header">
                <h2 style="color: var(--dark);">New Merit Record</h2>
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
                        <input type="text" name="activity_title" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['activity_title']) ? htmlspecialchars($_POST['activity_title']) : ''; ?>">
                    </div>

                    <div>
                        <label>Activity Type *</label>
                        <select name="activity_type" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                            <option value="">Select Type</option>
                            <option value="Volunteering" <?php if (isset($_POST['activity_type']) && $_POST['activity_type'] == "Volunteering") echo "selected"; ?>>Volunteering</option>
                            <option value="Community Service" <?php if (isset($_POST['activity_type']) && $_POST['activity_type'] == "Community Service") echo "selected"; ?>>Community Service</option>
                            <option value="Committee Work" <?php if (isset($_POST['activity_type']) && $_POST['activity_type'] == "Committee Work") echo "selected"; ?>>Committee Work</option>
                            <option value="Charity Program" <?php if (isset($_POST['activity_type']) && $_POST['activity_type'] == "Charity Program") echo "selected"; ?>>Charity Program</option>
                            <option value="Others" <?php if (isset($_POST['activity_type']) && $_POST['activity_type'] == "Others") echo "selected"; ?>>Others</option>
                        </select>
                    </div>

                    <div>
                        <label>Participation Role *</label>
                        <select name="participation_role" id="participation_role" required onchange="autoFillMeritPoints(this.value)" style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" <?php echo (isset($_POST['participation_role']) ? $_POST['participation_role'] : 'Participant') === $role ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role); ?> (<?php echo $roleMeritPoints[$role]; ?> pts)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Start Date *</label>
                        <input type="date" name="start_date" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
                    </div>

                    <div>
                        <label>End Date *</label>
                        <input type="date" name="end_date" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                    </div>

                    <div style="grid-column: span 2;">
                        <label>Hours Contributed *</label>
                        <input type="number" step="0.1" min="0" name="hours_contributed" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['hours_contributed']) ? htmlspecialchars($_POST['hours_contributed']) : ''; ?>">
                    </div>

                    <div style="grid-column: span 2;">
                        <label>Merit Points *</label>
                        <input type="number" id="merit_points" name="merit_points" min="0" step="1" required style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"
                               value="<?php echo isset($_POST['merit_points']) ? htmlspecialchars($_POST['merit_points']) : $roleMeritPoints['Participant']; ?>">
                        <p style="font-size:0.8rem; color: var(--text-muted); margin-top:6px;">Uses the same merit point mapping as Event Tracker. You can still adjust it manually.</p>
                    </div>

                    <div style="grid-column: span 2;">
                        <label>Description</label>
                        <textarea name="description" rows="5" style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid var(--border);"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display:flex; gap:10px;">
                    <button type="submit" class="btn-primary">Save Record</button>
                    <a href="merit.php" class="btn-disabled" style="text-decoration:none;">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const roleMeritMap = {
            'Participant': <?php echo $roleMeritPoints['Participant']; ?>,
            'Volunteer': <?php echo $roleMeritPoints['Volunteer']; ?>,
            'Committee': <?php echo $roleMeritPoints['Committee']; ?>,
            'Facilitator': <?php echo $roleMeritPoints['Facilitator']; ?>,
            'Representative': <?php echo $roleMeritPoints['Representative']; ?>,
            'Speaker': <?php echo $roleMeritPoints['Speaker']; ?>,
            'Organizer': <?php echo $roleMeritPoints['Organizer']; ?>
        };

        function autoFillMeritPoints(role) {
            const meritInput = document.getElementById('merit_points');
            if (roleMeritMap[role] !== undefined) {
                meritInput.value = roleMeritMap[role];
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const roleSelect = document.getElementById('participation_role');
            if (roleSelect) {
                autoFillMeritPoints(roleSelect.value);
            }
        });
    </script>
</body>
</html>
