<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = isset($_GET['success']) ? $_GET['success'] : '';

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

$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : "created_at";
$order = isset($_GET['order']) ? $_GET['order'] : "DESC";

$allowed_sort = ['club_name', 'role_position', 'join_date', 'membership_status', 'created_at'];
$allowed_order = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = "created_at";
}
if (!in_array($order, $allowed_order)) {
    $order = "DESC";
}

// Pagination
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
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

// Summary
$summary_sql = "SELECT COUNT(*) AS total_records,
                SUM(CASE WHEN membership_status = 'Active' THEN 1 ELSE 0 END) AS active_records
                FROM clubs WHERE user_id = ?";
$stmt_summary = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($stmt_summary, "i", $user_id);
mysqli_stmt_execute($stmt_summary);
$summary_result = mysqli_stmt_get_result($stmt_summary);
$summary_row = mysqli_fetch_assoc($summary_result);

$total_records = $summary_row['total_records'] ?? 0;
$active_records = $summary_row['active_records'] ?? 0;

// Count
$count_sql = "SELECT COUNT(*) AS total" . $base_sql;
$stmt_count = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$count_row = mysqli_fetch_assoc($count_result);

$filtered_records = $count_row['total'] ?? 0;
$total_pages = ceil($filtered_records / $limit);

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
        <div class="hero-banner">
            <div>
                <p class="hero-label">Club Module</p>
                <h1>My Club Records 👥</h1>
                <p class="hero-text">Manage your club memberships, society involvement, and committee roles.</p>
            </div>
            <div class="action-bar">
                <a href="add_club.php" class="btn-primary">+ Add New</a>
            </div>
        </div>

        <?php if ($success == 'added'): ?>
            <div class="alert-success-box">Club record added successfully.</div>
        <?php elseif ($success == 'updated'): ?>
            <div class="alert-success-box">Club record updated successfully.</div>
        <?php elseif ($success == 'deleted'): ?>
            <div class="alert-success-box">Club record deleted successfully.</div>
        <?php endif; ?>

        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-label">Total Club Records</div>
                <div class="summary-value"><?php echo $total_records; ?></div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Active Memberships</div>
                <div class="summary-value"><?php echo $active_records; ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Records Dashboard</h2>
                <span class="stat-note">Showing <?php echo mysqli_num_rows($result); ?> record(s)</span>
            </div>

            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search club, category, or role..."
                       value="<?php echo htmlspecialchars($search); ?>" style="flex:2;">

                <select name="membership_status" style="flex:1;">
                    <option value="">All Status</option>
                    <option value="Active" <?php if ($membership_status == "Active") echo "selected"; ?>>Active</option>
                    <option value="Inactive" <?php if ($membership_status == "Inactive") echo "selected"; ?>>Inactive</option>
                    <option value="Completed" <?php if ($membership_status == "Completed") echo "selected"; ?>>Completed</option>
                </select>

                <select name="sort_by" style="flex:1;">
                    <option value="club_name" <?php if ($sort_by == "club_name") echo "selected"; ?>>Sort by Club</option>
                    <option value="role_position" <?php if ($sort_by == "role_position") echo "selected"; ?>>Sort by Role</option>
                    <option value="join_date" <?php if ($sort_by == "join_date") echo "selected"; ?>>Sort by Join Date</option>
                    <option value="membership_status" <?php if ($sort_by == "membership_status") echo "selected"; ?>>Sort by Status</option>
                </select>

                <select name="order" style="flex:1;">
                    <option value="ASC" <?php if ($order == "ASC") echo "selected"; ?>>Ascending</option>
                    <option value="DESC" <?php if ($order == "DESC") echo "selected"; ?>>Descending</option>
                </select>

                <button type="submit" class="btn-primary">Filter</button>
                <a href="clubs.php" class="btn-secondary">Reset</a>
            </form>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-wrapper">
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Club Name</th>
                                <th>Category</th>
                                <th>Role</th>
                                <th>Join Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['club_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['club_category']); ?></td>
                                    <td><?php echo htmlspecialchars($row['role_position']); ?></td>
                                    <td><?php echo htmlspecialchars($row['join_date']); ?></td>
                                    <td><?php echo !empty($row['end_date']) ? htmlspecialchars($row['end_date']) : '-'; ?></td>
                                    <td>
                                        <?php if ($row['membership_status'] == 'Active'): ?>
                                            <span class="badge-active">Active</span>
                                        <?php elseif ($row['membership_status'] == 'Inactive'): ?>
                                            <span class="badge-inactive">Inactive</span>
                                        <?php else: ?>
                                            <span class="badge-completed"><?php echo htmlspecialchars($row['membership_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_club.php?id=<?php echo $row['club_id']; ?>" class="text-link view">👁 View</a>
                                        <a href="edit_club.php?id=<?php echo $row['club_id']; ?>" class="text-link edit">✎ Edit</a>
                                        <a href="clubs.php?delete=<?php echo $row['club_id']; ?>" class="text-link delete" onclick="return confirm('Delete this club record?');">🗑 Delete</a>
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
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3>No club records found</h3>
                    <p>Try adjusting your filter or add a new club record.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>