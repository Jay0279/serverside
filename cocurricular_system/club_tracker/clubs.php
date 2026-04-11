<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = isset($_GET['success']) ? $_GET['success'] : '';

function club_column_exists($conn, $column_name)
{
    $safe_column = mysqli_real_escape_string($conn, $column_name);
    $sql = "SHOW COLUMNS FROM clubs LIKE '$safe_column'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

$has_review_status = club_column_exists($conn, 'review_status');

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $club_id = (int) $_GET['delete'];

    $delete_sql = "DELETE FROM clubs WHERE club_id = ? AND user_id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($stmt_delete, "ii", $club_id, $user_id);

    if (mysqli_stmt_execute($stmt_delete)) {
        header("Location: clubs.php?success=deleted");
        exit();
    }
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$membership_status = isset($_GET['membership_status']) ? trim($_GET['membership_status']) : "";
$review_status = isset($_GET['review_status']) ? trim($_GET['review_status']) : "";

$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : "created_at";
$order = isset($_GET['order']) ? $_GET['order'] : "DESC";

$allowed_sort = ['club_name', 'role_position', 'join_date', 'membership_status', 'created_at'];
if ($has_review_status) {
    $allowed_sort[] = 'review_status';
}
$allowed_order = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sort, true)) {
    $sort_by = "created_at";
}
if (!in_array($order, $allowed_order, true)) {
    $order = "DESC";
}

// Pagination
$page = isset($_GET['page']) ? max((int) $_GET['page'], 1) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Base query
$base_sql = " FROM clubs WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $base_sql .= " AND (club_name LIKE ? OR club_category LIKE ? OR role_position LIKE ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($membership_status)) {
    $base_sql .= " AND membership_status = ?";
    $params[] = $membership_status;
    $types .= "s";
}

if ($has_review_status && !empty($review_status)) {
    $base_sql .= " AND review_status = ?";
    $params[] = $review_status;
    $types .= "s";
}

// Summary
if ($has_review_status) {
    $summary_sql = "SELECT 
                    COUNT(*) AS total_records,
                    SUM(CASE WHEN membership_status = 'Active' THEN 1 ELSE 0 END) AS active_records,
                    SUM(CASE WHEN review_status = 'Approved' THEN 1 ELSE 0 END) AS approved_records,
                    SUM(CASE WHEN review_status = 'Pending' THEN 1 ELSE 0 END) AS pending_records
                    FROM clubs 
                    WHERE user_id = ?";
} else {
    $summary_sql = "SELECT 
                    COUNT(*) AS total_records,
                    SUM(CASE WHEN membership_status = 'Active' THEN 1 ELSE 0 END) AS active_records
                    FROM clubs 
                    WHERE user_id = ?";
}

$stmt_summary = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($stmt_summary, "i", $user_id);
mysqli_stmt_execute($stmt_summary);
$summary_result = mysqli_stmt_get_result($stmt_summary);
$summary_row = mysqli_fetch_assoc($summary_result);

$total_records = (int) ($summary_row['total_records'] ?? 0);
$active_records = (int) ($summary_row['active_records'] ?? 0);
$approved_records = $has_review_status ? (int) ($summary_row['approved_records'] ?? 0) : 0;
$pending_records = $has_review_status ? (int) ($summary_row['pending_records'] ?? 0) : 0;

// Count
$count_sql = "SELECT COUNT(*) AS total" . $base_sql;
$stmt_count = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$count_row = mysqli_fetch_assoc($count_result);

$filtered_records = (int) ($count_row['total'] ?? 0);
$total_pages = max(1, (int) ceil($filtered_records / $limit));

// Data
$sql = "SELECT *" . $base_sql . " ORDER BY $sort_by $order LIMIT ? OFFSET ?";
$params_with_pagination = $params;
$params_with_pagination[] = $limit;
$params_with_pagination[] = $offset;
$types_with_pagination = $types . "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types_with_pagination, ...$params_with_pagination);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Preserve query string
$query_string = $_GET;
unset($query_string['page']);
$base_url = '?' . http_build_query($query_string) . '&page=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Tracker | CCMS</title>
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
                <h1>My Club Records 👥</h1>
                <p class="hero-text merit-hero-text">Manage your memberships, club roles, and society involvement in one organized dashboard.</p>
            </div>
            <div class="action-bar merit-action-bar">
                <a href="add_club.php" class="btn-primary">+ Add New</a>
            </div>
        </div>

        <?php if ($success == 'added'): ?>
            <div class="alert-success">Club record added successfully.</div>
        <?php elseif ($success == 'updated'): ?>
            <div class="alert-success">Club record updated successfully.</div>
        <?php elseif ($success == 'deleted'): ?>
            <div class="alert-success">Club record deleted successfully.</div>
        <?php endif; ?>

        <div class="stats-container merit-stats-container">
            <div class="stat-box blue">
                <span class="stat-label">Total Records</span>
                <div class="stat-number"><?php echo $total_records; ?></div>
                <span class="stat-label merit-stat-subtext">All club submissions</span>
            </div>

            <div class="stat-box green">
                <span class="stat-label">Active Memberships</span>
                <div class="stat-number"><?php echo $active_records; ?></div>
                <span class="stat-label merit-stat-subtext">Currently active</span>
            </div>

            <?php if ($has_review_status): ?>
                <div class="stat-box purple">
                    <span class="stat-label">Approved Records</span>
                    <div class="stat-number"><?php echo $approved_records; ?></div>
                    <span class="stat-label merit-stat-subtext">Verified by admin</span>
                </div>

                <div class="stat-box orange">
                    <span class="stat-label">Pending Review</span>
                    <div class="stat-number"><?php echo $pending_records; ?></div>
                    <span class="stat-label merit-stat-subtext">Awaiting admin decision</span>
                </div>
            <?php else: ?>
                <div class="stat-box purple">
                    <span class="stat-label">Club Categories</span>
                    <div class="stat-number">6</div>
                    <span class="stat-label merit-stat-subtext">Structured dropdown setup</span>
                </div>

                <div class="stat-box orange">
                    <span class="stat-label">Roles Supported</span>
                    <div class="stat-number">7</div>
                    <span class="stat-label merit-stat-subtext">Standardized role options</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel merit-main-panel">
            <div class="panel-header merit-panel-header-better">
                <div>
                    <h2 class="merit-panel-title">Records Dashboard</h2>
                    <p class="merit-panel-subtitle">Browse your club records with search, filters, and sorting.</p>
                </div>
                <span class="merit-total-pill">Showing <?php echo mysqli_num_rows($result); ?> record(s)</span>
            </div>

            <form method="GET" class="filter-form merit-filter-form better-merit-filter-form">
                <input
                    type="text"
                    name="search"
                    placeholder="Search club, category, or role..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    class="merit-filter-search">

                <select name="membership_status" class="merit-filter-select">
                    <option value="">All Membership</option>
                    <option value="Active" <?php if ($membership_status == "Active") echo "selected"; ?>>Active</option>
                    <option value="Inactive" <?php if ($membership_status == "Inactive") echo "selected"; ?>>Inactive</option>
                    <option value="Completed" <?php if ($membership_status == "Completed") echo "selected"; ?>>Completed</option>
                </select>

                <?php if ($has_review_status): ?>
                    <select name="review_status" class="merit-filter-select">
                        <option value="">All Review Status</option>
                        <option value="Approved" <?php if ($review_status == "Approved") echo "selected"; ?>>Approved</option>
                        <option value="Pending" <?php if ($review_status == "Pending") echo "selected"; ?>>Pending</option>
                        <option value="Rejected" <?php if ($review_status == "Rejected") echo "selected"; ?>>Rejected</option>
                    </select>
                <?php endif; ?>

                <select name="sort_by" class="merit-filter-select">
                    <option value="club_name" <?php if ($sort_by == "club_name") echo "selected"; ?>>Sort by Club</option>
                    <option value="role_position" <?php if ($sort_by == "role_position") echo "selected"; ?>>Sort by Role</option>
                    <option value="join_date" <?php if ($sort_by == "join_date") echo "selected"; ?>>Sort by Join Date</option>
                    <option value="membership_status" <?php if ($sort_by == "membership_status") echo "selected"; ?>>Sort by Membership</option>
                    <?php if ($has_review_status): ?>
                        <option value="review_status" <?php if ($sort_by == "review_status") echo "selected"; ?>>Sort by Review</option>
                    <?php endif; ?>
                    <option value="created_at" <?php if ($sort_by == "created_at") echo "selected"; ?>>Sort by Created Date</option>
                </select>

                <select name="order" class="merit-filter-select">
                    <option value="ASC" <?php if ($order == "ASC") echo "selected"; ?>>Ascending</option>
                    <option value="DESC" <?php if ($order == "DESC") echo "selected"; ?>>Descending</option>
                </select>

                <button type="submit" class="btn-primary">Filter</button>
                <a href="clubs.php" class="btn-disabled merit-reset-link">Reset</a>
            </form>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-wrapper merit-table-wrapper">
                    <table class="record-table merit-record-table better-merit-table">
                        <thead>
                            <tr>
                                <th>Club Name</th>
                                <th>Category</th>
                                <th>Role</th>
                                <th>Join Date</th>
                                <th>End Date</th>
                                <th>Membership</th>
                                <?php if ($has_review_status): ?>
                                    <th>Review</th>
                                <?php endif; ?>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['club_name']); ?></strong>
                                    </td>

                                    <td>
                                        <span class="merit-soft-tag">
                                            <?php echo htmlspecialchars($row['club_category']); ?>
                                        </span>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['role_position']); ?></td>
                                    <td><?php echo htmlspecialchars($row['join_date']); ?></td>
                                    <td><?php echo !empty($row['end_date']) ? htmlspecialchars($row['end_date']) : '-'; ?></td>

                                    <td>
                                        <?php if ($row['membership_status'] === 'Active'): ?>
                                            <span class="merit-status-badge badge-success">Active</span>
                                        <?php elseif ($row['membership_status'] === 'Inactive'): ?>
                                            <span class="merit-status-badge badge-danger">Inactive</span>
                                        <?php else: ?>
                                            <span class="merit-status-badge badge-warning"><?php echo htmlspecialchars($row['membership_status']); ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <?php if ($has_review_status): ?>
                                        <td>
                                            <?php if ($row['review_status'] === 'Approved'): ?>
                                                <span class="merit-status-badge badge-success">Approved</span>
                                            <?php elseif ($row['review_status'] === 'Rejected'): ?>
                                                <span class="merit-status-badge badge-danger">Rejected</span>
                                            <?php else: ?>
                                                <span class="merit-status-badge badge-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <td class="merit-description-cell">
                                        <?php echo !empty($row['remarks']) ? nl2br(htmlspecialchars($row['remarks'])) : '-'; ?>
                                    </td>

                                    <td>
                                        <div class="merit-action-links">
                                            <a href="view_club.php?id=<?php echo $row['club_id']; ?>" class="text-link view">👁 View</a>
                                            <a href="edit_club.php?id=<?php echo $row['club_id']; ?>" class="text-link edit">✎ Edit</a>
                                            <a href="clubs.php?delete=<?php echo $row['club_id']; ?>" onclick="return confirm('Delete this club record?');" class="text-link delete">🗑 Delete</a>
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
                <div class="merit-empty-state">
                    <div class="merit-empty-icon">📭</div>
                    <h3 class="merit-empty-title">No records found</h3>
                    <p class="merit-empty-text">Adjust your filters or add a new club record.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>