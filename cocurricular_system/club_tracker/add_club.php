<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

$club_options = [
    "Academic" => [
        "Computer Science Club",
        "Mathematics Society",
        "Robotics Club",
        "Engineering Society"
    ],
    "Sports" => [
        "Badminton Club",
        "Basketball Club",
        "Volleyball Club",
        "Football Club"
    ],
    "Cultural" => [
        "Chinese Cultural Society",
        "English Cultural Society",
        "Malay Cultural Society",
        "Music Club",
        "Dance Club"
    ],
    "Volunteer" => [
        "Leo Club",
        "Red Crescent Society",
        "Community Service Club"
    ],
    "Leadership" => [
        "Student Representative Council",
        "Peer Support Club",
        "Committee Board"
    ],
    "Others" => [
        "Photography Club",
        "Debate Club",
        "E-Sports Club"
    ]
];

$role_options = [
    "President/Chairperson",
    "Vice President/Vice Chair",
    "Secretary",
    "Treasurer",
    "Event Director",
    "Committee Member",
    "Member"
];

$selected_category = "";
$selected_club_name = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $club_category = trim($_POST['club_category']);
    $club_name = trim($_POST['club_name']);
    $role_position = trim($_POST['role_position']);
    $join_date = $_POST['join_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $membership_status = trim($_POST['membership_status']);
    $remarks = trim($_POST['remarks']);

    $selected_category = $club_category;
    $selected_club_name = $club_name;

    if (empty($club_category) || empty($club_name) || empty($role_position) || empty($join_date) || empty($membership_status)) {
        $error = "Please fill in all required fields.";
    } elseif (!isset($club_options[$club_category])) {
        $error = "Invalid club category selected.";
    } elseif (!in_array($club_name, $club_options[$club_category], true)) {
        $error = "Invalid club name selected for the chosen category.";
    } elseif (!in_array($role_position, $role_options, true)) {
        $error = "Invalid role/position selected.";
    } elseif (!empty($end_date) && $end_date < $join_date) {
        $error = "End date cannot be earlier than join date.";
    } else {
        $check_sql = "SELECT club_id FROM clubs WHERE user_id = ? AND club_category = ? AND club_name = ?";
        $stmt_check = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt_check, "iss", $user_id, $club_category, $club_name);
        mysqli_stmt_execute($stmt_check);
        $check_result = mysqli_stmt_get_result($stmt_check);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "You have already added this club.";
        } else {
            $sql = "INSERT INTO clubs (user_id, club_name, club_category, role_position, join_date, end_date, membership_status, remarks, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "isssssss",
                $user_id,
                $club_name,
                $club_category,
                $role_position,
                $join_date,
                $end_date,
                $membership_status,
                $remarks
            );

            if (mysqli_stmt_execute($stmt)) {
                header("Location: clubs.php?success=added");
                exit();
            } else {
                $error = "Failed to add club record.";
            }
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
        <div class="hero-banner merit-hero-banner">
            <div>
                <p class="hero-label">CLUB MODULE</p>
                <h1>Add Club Record 👥</h1>
                <p class="hero-text merit-hero-text">Create a new club membership record with organized dropdown selections.</p>
            </div>
        </div>

        <div class="panel merit-main-panel" style="max-width: 980px;">
            <div class="panel-header merit-panel-header-better">
                <div>
                    <h2 class="merit-panel-title">New Club Record</h2>
                    <p class="merit-panel-subtitle">Fill in your membership details and submit for record keeping.</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid-2">
                    <div class="input-group">
                        <label>Club Category *</label>
                        <select class="module-select" name="club_category" id="club_category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($club_options as $category => $clubs): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php if ($selected_category === $category) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Club Name *</label>
                        <select class="module-select" name="club_name" id="club_name" required>
                            <option value="">Select Club Name</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Role / Position *</label>
                        <select class="module-select" name="role_position" required>
                            <option value="">Select Role / Position</option>
                            <?php foreach ($role_options as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" <?php if (isset($_POST['role_position']) && $_POST['role_position'] === $role) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($role); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Membership Status *</label>
                        <select class="module-select" name="membership_status" required>
                            <option value="">Select Status</option>
                            <option value="Active" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] === "Active") echo "selected"; ?>>Active</option>
                            <option value="Inactive" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] === "Inactive") echo "selected"; ?>>Inactive</option>
                            <option value="Completed" <?php if (isset($_POST['membership_status']) && $_POST['membership_status'] === "Completed") echo "selected"; ?>>Completed</option>
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

    <script>
        const clubOptions = <?php echo json_encode($club_options, JSON_UNESCAPED_UNICODE); ?>;
        const selectedCategory = <?php echo json_encode($selected_category, JSON_UNESCAPED_UNICODE); ?>;
        const selectedClubName = <?php echo json_encode($selected_club_name, JSON_UNESCAPED_UNICODE); ?>;

        const categorySelect = document.getElementById('club_category');
        const clubNameSelect = document.getElementById('club_name');

        function populateClubNames() {
            const category = categorySelect.value;
            clubNameSelect.innerHTML = '<option value="">Select Club Name</option>';

            if (!category || !clubOptions[category]) {
                return;
            }

            clubOptions[category].forEach(function (clubName) {
                const option = document.createElement('option');
                option.value = clubName;
                option.textContent = clubName;

                if (clubName === selectedClubName) {
                    option.selected = true;
                }

                clubNameSelect.appendChild(option);
            });
        }

        categorySelect.addEventListener('change', populateClubNames);

        window.addEventListener('DOMContentLoaded', function () {
            if (selectedCategory) {
                categorySelect.value = selectedCategory;
            }
            populateClubNames();
        });
    </script>
</body>
</html>