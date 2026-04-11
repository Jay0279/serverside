<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$success = isset($_GET['success']) ? $_GET['success'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$category = isset($_GET['category']) ? trim($_GET['category']) : "";
$level = isset($_GET['level']) ? trim($_GET['level']) : "";
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : "";
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$status_map = [
    'Approved' => 'Completed',
    'Pending' => 'Pending Verification',
    'Rejected' => 'Rejected',
];

// Tooltip message for auto achievements
function getAutoAchievementMessage($title)
{
    $messages = [
        'Active Contributor Award' => 'Awarded for reaching 10 approved contribution hours. Please go to the office to collect your certificate.',
        'Bronze Engagement Award' => 'Awarded for reaching 20 merit points. Please go to the office to collect your certificate.',
        'Silver Engagement Award' => 'Awarded for reaching 50 merit points. Please go to the office to collect your certificate.',
        'Gold Engagement Award' => 'Awarded for reaching 80 merit points. Please go to the office to collect your certificate.',
        'Outstanding Student Involvement Award' => 'Awarded for reaching 120 merit points. Please go to the office to collect your certificate.',
        'Dedicated Service Award' => 'Awarded for reaching 25 contribution hours. Please go to the office to collect your certificate.',
        'Excellence in Service Award' => 'Awarded for reaching 50 contribution hours. Please go to the office to collect your certificate.',
        'Outstanding Volunteer Award' => 'Awarded for reaching 80 contribution hours. Please go to the office to collect your certificate.',
    ];

    return $messages[$title] ?? 'Awarded based on your achievement milestone. Please go to the office to collect your certificate.';
}

// Summary cards
$summary_sql = "SELECT
    COUNT(*) AS total_achievements,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS approved_achievements,
    SUM(CASE WHEN status = 'Pending Verification' THEN 1 ELSE 0 END) AS pending_achievements,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_achievements
FROM achievements
WHERE user_id = ?";

$stmt_summary = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($stmt_summary, "i", $user_id);
mysqli_stmt_execute($stmt_summary);
$summary_result = mysqli_stmt_get_result($stmt_summary);
$summary_row = mysqli_fetch_assoc($summary_result);

$total_achievements = (int)($summary_row['total_achievements'] ?? 0);
$approved_achievements = (int)($summary_row['approved_achievements'] ?? 0);
$pending_achievements = (int)($summary_row['pending_achievements'] ?? 0);
$rejected_achievements = (int)($summary_row['rejected_achievements'] ?? 0);

// Filter base query
$base_sql = " FROM achievements a
              LEFT JOIN events e ON a.event_id = e.id
              WHERE a.user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $base_sql .= " AND (a.title LIKE ? OR e.event_title LIKE ?)";
    $search_like = "%" . $search . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss";
}

if (!empty($category)) {
    $base_sql .= " AND a.category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($level)) {
    $base_sql .= " AND a.level = ?";
    $params[] = $level;
    $types .= "s";
}

if ($status_filter !== '' && isset($status_map[$status_filter])) {
    $base_sql .= " AND a.status = ?";
    $params[] = $status_map[$status_filter];
    $types .= "s";
}

// Count
$count_sql = "SELECT COUNT(*) AS total " . $base_sql;
$stmt_total = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($stmt_total, $types, ...$params);
mysqli_stmt_execute($stmt_total);
$total_result = mysqli_stmt_get_result($stmt_total);
$total_row = mysqli_fetch_assoc($total_result);
$total_records = (int)$total_row['total'];
$total_pages = max(1, ceil($total_records / $limit));

// Data
$sql = "SELECT a.*, e.event_title " . $base_sql . " ORDER BY a.created_at DESC, a.id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Pagination URL
$query_string = $_GET;
unset($query_string['page']);
$base_url = '?' . http_build_query($query_string) . '&page=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievement Tracker | CCMS</title>
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
            <a href="../club_tracker/clubs.php">👥 Club Tracker</a>
            <a href="../merit_tracker/merit.php">⏱️ Merit Tracker</a>
            <a href="achievements.php" class="active">🏆 Achievements</a>
        </div>

        <a href="../../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner achievement-hero-banner better-achievement-hero">
            <div>
                <p class="hero-label">ACHIEVEMENT MODULE</p>
                <h1>My Achievements 🏆</h1>
                <p class="hero-text achievement-hero-text">
                    Manage your awards, recognitions, and certificates in one place.
                </p>
            </div>
            <a href="add_achievement.php" class="btn-primary">+ Add New</a>
        </div>

        <?php if ($success == 'added'): ?>
            <div class="alert success">Achievement record added successfully.</div>
        <?php elseif ($success == 'updated'): ?>
            <div class="alert success">Achievement record updated successfully.</div>
        <?php elseif ($success == 'deleted'): ?>
            <div class="alert success">Achievement record deleted successfully.</div>
        <?php endif; ?>

        <div class="stats-container achievement-stats-container">
            <div class="stat-box purple">
                <span class="stat-label">Total Achievements</span>
                <div class="stat-number"><?php echo $total_achievements; ?></div>
                <span class="stat-label achievement-stat-subtext">All recorded achievements</span>
            </div>

            <div class="stat-box green">
                <span class="stat-label">Approved</span>
                <div class="stat-number"><?php echo $approved_achievements; ?></div>
                <span class="stat-label achievement-stat-subtext">Verified by admin</span>
            </div>

            <div class="stat-box orange">
                <span class="stat-label">Pending</span>
                <div class="stat-number"><?php echo $pending_achievements; ?></div>
                <span class="stat-label achievement-stat-subtext">Waiting for review</span>
            </div>

            <div class="stat-box red achievement-red-card">
                <span class="stat-label">Rejected</span>
                <div class="stat-number"><?php echo $rejected_achievements; ?></div>
                <span class="stat-label achievement-stat-subtext">Need correction or update</span>
            </div>
        </div>

        <div class="panel achievement-main-panel">
            <div class="panel-header achievement-panel-header-better">
                <div>
                    <h2 class="achievement-panel-title">Records Dashboard</h2>
                    <p class="achievement-panel-subtitle">
                        Browse your achievement records with search and filters.
                    </p>
                </div>
                <span class="achievement-total-pill">Total: <?php echo $total_records; ?></span>
            </div>

            <form method="GET" class="filter-form achievement-filter-form better-filter-form">
                <input
                    type="text"
                    name="search"
                    placeholder="Search title or event..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    class="achievement-filter-search">

                <select name="category" class="achievement-filter-select">
                    <option value="">All Categories</option>
                    <option value="Academic" <?php if ($category == "Academic") echo "selected"; ?>>Academic</option>
                    <option value="Sports" <?php if ($category == "Sports") echo "selected"; ?>>Sports</option>
                    <option value="Leadership" <?php if ($category == "Leadership") echo "selected"; ?>>Leadership</option>
                    <option value="Competition" <?php if ($category == "Competition") echo "selected"; ?>>Competition</option>
                    <option value="Participation" <?php if ($category == "Participation") echo "selected"; ?>>Participation</option>
                    <option value="Community Service" <?php if ($category == "Community Service") echo "selected"; ?>>Community Service</option>
                    <option value="Others" <?php if ($category == "Others") echo "selected"; ?>>Others</option>
                </select>

                <select name="level" class="achievement-filter-select">
                    <option value="">All Levels</option>
                    <option value="University" <?php if ($level == "University") echo "selected"; ?>>University</option>
                    <option value="State" <?php if ($level == "State") echo "selected"; ?>>State</option>
                    <option value="National" <?php if ($level == "National") echo "selected"; ?>>National</option>
                    <option value="International" <?php if ($level == "International") echo "selected"; ?>>International</option>
                </select>

                <select name="status" class="achievement-filter-select">
                    <option value="">All Statuses</option>
                    <option value="Approved" <?php if ($status_filter == "Approved") echo "selected"; ?>>Approved</option>
                    <option value="Pending" <?php if ($status_filter == "Pending") echo "selected"; ?>>Pending</option>
                    <option value="Rejected" <?php if ($status_filter == "Rejected") echo "selected"; ?>>Rejected</option>
                </select>

                <button type="submit" class="btn-primary">Filter</button>
                <a href="achievements.php" class="btn-disabled achievement-reset-link">Reset</a>
            </form>

            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <div class="table-wrapper achievement-table-wrapper">
                    <table class="record-table achievement-record-table better-achievement-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Related Event</th>
                                <th>Category</th>
                                <th>Level</th>
                                <th>Status</th>
                                <th>History / Feedback</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                    $is_auto = isset($row['achievement_source']) && $row['achievement_source'] !== 'manual' && $row['achievement_source'] !== '';
                                    $tooltip_message = $is_auto ? getAutoAchievementMessage($row['title']) : '';
                                ?>
                                <tr>
                                    <td>
                                        <div class="title-meta">
                                            <div class="title-inline">
                                                <strong><?php echo htmlspecialchars($row['title']); ?></strong>

                                                <?php if ($is_auto): ?>
                                                    <div class="tooltip-wrap">
                                                        <span class="info-icon">(?)</span>
                                                        <div class="tooltip-text">
                                                            <?php echo htmlspecialchars($tooltip_message); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <span class="title-subdate"><?php echo htmlspecialchars($row['achievement_date']); ?></span>
                                        </div>
                                    </td>

                                    <td><?php echo !empty($row['event_title']) ? htmlspecialchars($row['event_title']) : '-'; ?></td>

                                    <td>
                                        <span class="achievement-soft-tag">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['level']); ?></td>

                                    <td>
                                        <?php if ($row['status'] === 'Completed'): ?>
                                            <span class="achievement-status-badge badge-success">Approved</span>
                                        <?php elseif ($row['status'] === 'Rejected'): ?>
                                            <span class="achievement-status-badge badge-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="achievement-status-badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="history-box better-history-box">
                                            <strong>Submitted:</strong> <?php echo !empty($row['created_at']) ? htmlspecialchars($row['created_at']) : '-'; ?><br>
                                            <strong>Reviewed:</strong> <?php echo !empty($row['reviewed_at']) ? htmlspecialchars($row['reviewed_at']) : '-'; ?><br>
                                            <strong>Admin Remark:</strong>
                                            <?php echo !empty($row['admin_remark']) ? nl2br(htmlspecialchars($row['admin_remark'])) : '-'; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="achievement-action-links">
                                            <a href="edit_achievement.php?id=<?php echo $row['id']; ?>" class="text-link edit">✎ Edit</a>
                                            <a href="delete_achievement.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this achievement?');" class="text-link delete">🗑 Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?php echo $base_url . $i; ?>" class="page-link <?php if ($i == $page) echo 'active'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="achievement-empty-state">
                    <div class="achievement-empty-icon">📭</div>
                    <h3 class="achievement-empty-title">No records found</h3>
                    <p class="achievement-empty-text">Add a new achievement to start tracking your history.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
