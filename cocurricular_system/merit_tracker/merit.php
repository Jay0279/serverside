<?php
include '../../config.php';

// SESSION VALIDATION
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// DELETE FUNCTION
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $merit_id = (int) $_GET['delete'];

    $delete_sql = "DELETE FROM merits WHERE merit_id = ? AND user_id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($stmt_delete, "ii", $merit_id, $user_id);

    if (mysqli_stmt_execute($stmt_delete)) {
        header("Location: merit.php?success=deleted");
        exit();
    }
}

// FILTER & SEARCH INPUT
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$activity_type = isset($_GET['activity_type']) ? trim($_GET['activity_type']) : "";
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : "";

// SORTING FUNCTION
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : "created_at";
$order = isset($_GET['order']) ? $_GET['order'] : "DESC";

$allowed_sort = ['activity_title', 'start_date', 'hours_contributed', 'merit_points', 'created_at'];
$allowed_order = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sort, true)) {
    $sort_by = 'created_at';
}

if (!in_array($order, $allowed_order, true)) {
    $order = 'DESC';
}

// PAGINATION SETUP
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// BASE QUERY
$base_sql = "FROM merits m
             LEFT JOIN events e ON m.event_id = e.id
             WHERE m.user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $base_sql .= " AND (m.activity_title LIKE ? OR m.description LIKE ?)";
    $search_like = "%" . $search . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss";
}

if (!empty($activity_type)) {
    $base_sql .= " AND m.activity_type = ?";
    $params[] = $activity_type;
    $types .= "s";
}

if (!empty($status_filter)) {
    $base_sql .= " AND m.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// SORT COLUMN MAP
switch ($sort_by) {
    case 'activity_title':
        $sort_expression = 'm.activity_title';
        break;
    case 'start_date':
        $sort_expression = 'm.start_date';
        break;
    case 'hours_contributed':
        $sort_expression = 'm.hours_contributed';
        break;
    case 'merit_points':
        $sort_expression = "CASE
                                WHEN m.event_id IS NOT NULL AND e.merit_points IS NOT NULL THEN e.merit_points
                                ELSE COALESCE(m.merit_points, 0)
                            END";
        break;
    default:
        $sort_expression = 'm.created_at';
        break;
}

// OVERALL SUMMARY
$summary_sql = "SELECT 
                COUNT(*) AS total_records,
                COALESCE(SUM(
                    CASE 
                        WHEN m.status = 'Completed' THEN m.hours_contributed
                        ELSE 0
                    END
                ), 0) AS total_hours,
                COALESCE(SUM(
                    CASE 
                        WHEN m.status = 'Completed' THEN
                            CASE
                                WHEN m.event_id IS NOT NULL AND e.merit_points IS NOT NULL THEN e.merit_points
                                ELSE COALESCE(m.merit_points, 0)
                            END
                        ELSE 0
                    END
                ), 0) AS total_merit_points,
                COALESCE(SUM(
                    CASE 
                        WHEN m.status = 'Completed' THEN 1
                        ELSE 0
                    END
                ), 0) AS approved_records
                FROM merits m
                LEFT JOIN events e ON m.event_id = e.id
                WHERE m.user_id = ?";

$stmt_summary = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($stmt_summary, "i", $user_id);
mysqli_stmt_execute($stmt_summary);

$summary_result = mysqli_stmt_get_result($stmt_summary);
$summary_row = mysqli_fetch_assoc($summary_result);

$total_records = (int) ($summary_row['total_records'] ?? 0);
$total_hours = (float) ($summary_row['total_hours'] ?? 0);
$total_merit_points = (int) ($summary_row['total_merit_points'] ?? 0);
$approved_records = (int) ($summary_row['approved_records'] ?? 0);

// FILTERED COUNT
$count_sql = "SELECT COUNT(*) AS total " . $base_sql;
$stmt_count = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);

$count_result = mysqli_stmt_get_result($stmt_count);
$count_row = mysqli_fetch_assoc($count_result);

$filtered_records = (int) ($count_row['total'] ?? 0);
$total_pages = max(1, (int) ceil($filtered_records / $limit));

// FETCH DATA FOR TABLE
$sql = "SELECT 
            m.*,
            CASE
                WHEN m.event_id IS NOT NULL AND e.merit_points IS NOT NULL THEN e.merit_points
                ELSE COALESCE(m.merit_points, 0)
            END AS display_merit_points
        " . $base_sql . "
        ORDER BY $sort_expression $order
        LIMIT ? OFFSET ?";

$params_with_pagination = $params;
$params_with_pagination[] = $limit;
$params_with_pagination[] = $offset;

$types_with_pagination = $types . "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types_with_pagination, ...$params_with_pagination);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

// PRESERVE QUERY PARAMETERS
$query_string = $_GET;
unset($query_string['page']);
$base_url = '?' . http_build_query($query_string) . '&page=';

$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merit Tracker | CCMS</title>
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
            <a href="merit.php" class="active">⏱️ Merit Tracker</a>
            <a href="../achievement_tracker/achievements.php">🏆 Achievements</a>
        </div>

        <a href="../../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner merit-hero-banner">
            <div>
                <p class="hero-label">MERIT MODULE</p>
                <h1>My Merit Records ⏱️</h1>
                <p class="hero-text merit-hero-text">Manage your contribution hours and merit marks in one organized dashboard.</p>
            </div>
            <div class="action-bar merit-action-bar">
                <a href="add_merit.php" class="btn-primary">+ Add New</a>
            </div>
        </div>

        <?php if ($success == 'added'): ?>
            <div class="alert-success">Merit record added successfully.</div>
        <?php elseif ($success == 'updated'): ?>
            <div class="alert-success">Merit record updated successfully.</div>
        <?php elseif ($success == 'deleted'): ?>
            <div class="alert-success">Merit record deleted successfully.</div>
        <?php endif; ?>

        <div class="stats-container merit-stats-container">
            <div class="stat-box blue">
                <span class="stat-label">Total Records</span>
                <div class="stat-number"><?php echo $total_records; ?></div>
                <span class="stat-label merit-stat-subtext">All merit submissions</span>
            </div>

            <div class="stat-box green">
                <span class="stat-label">Total Hours</span>
                <div class="stat-number"><?php echo number_format($total_hours, 1); ?></div>
                <span class="stat-label merit-stat-subtext">Approved contribution</span>
            </div>

            <div class="stat-box purple">
                <span class="stat-label">Merit Marks</span>
                <div class="stat-number"><?php echo $total_merit_points; ?></div>
                <span class="stat-label merit-stat-subtext">Approved merit points</span>
            </div>

            <div class="stat-box orange">
                <span class="stat-label">Approved Records</span>
                <div class="stat-number"><?php echo $approved_records; ?></div>
                <span class="stat-label merit-stat-subtext">Verified by admin</span>
            </div>
        </div>

        <div class="panel merit-main-panel">
            <div class="panel-header merit-panel-header-better">
                <div>
                    <h2 class="merit-panel-title">Records Dashboard</h2>
                    <p class="merit-panel-subtitle">Browse your merit records with search, filters, and sorting.</p>
                </div>
                <span class="merit-total-pill">Showing <?php echo mysqli_num_rows($result); ?> record(s)</span>
            </div>

            <form method="GET" class="filter-form merit-filter-form better-merit-filter-form">
                <input
                    type="text"
                    name="search"
                    placeholder="Search activity title or description..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    class="merit-filter-search">

                <select name="activity_type" class="merit-filter-select">
                    <option value="">All Types</option>
                    <option value="Volunteering" <?php if ($activity_type == "Volunteering") echo "selected"; ?>>Volunteering</option>
                    <option value="Community Service" <?php if ($activity_type == "Community Service") echo "selected"; ?>>Community Service</option>
                    <option value="Committee Work" <?php if ($activity_type == "Committee Work") echo "selected"; ?>>Committee Work</option>
                    <option value="Charity Program" <?php if ($activity_type == "Charity Program") echo "selected"; ?>>Charity Program</option>
                    <option value="Others" <?php if ($activity_type == "Others") echo "selected"; ?>>Others</option>
                </select>

                <select name="status" class="merit-filter-select">
                    <option value="">All Statuses</option>
                    <option value="Completed" <?php if ($status_filter == "Completed") echo "selected"; ?>>Approved</option>
                    <option value="Pending" <?php if ($status_filter == "Pending") echo "selected"; ?>>Pending</option>
                    <option value="Rejected" <?php if ($status_filter == "Rejected") echo "selected"; ?>>Rejected</option>
                </select>

                <select name="sort_by" class="merit-filter-select">
                    <option value="activity_title" <?php if ($sort_by == "activity_title") echo "selected"; ?>>Sort by Title</option>
                    <option value="start_date" <?php if ($sort_by == "start_date") echo "selected"; ?>>Sort by Start Date</option>
                    <option value="hours_contributed" <?php if ($sort_by == "hours_contributed") echo "selected"; ?>>Sort by Hours</option>
                    <option value="merit_points" <?php if ($sort_by == "merit_points") echo "selected"; ?>>Sort by Merit Marks</option>
                    <option value="created_at" <?php if ($sort_by == "created_at") echo "selected"; ?>>Sort by Created Date</option>
                </select>

                <select name="order" class="merit-filter-select">
                    <option value="ASC" <?php if ($order == "ASC") echo "selected"; ?>>Ascending</option>
                    <option value="DESC" <?php if ($order == "DESC") echo "selected"; ?>>Descending</option>
                </select>

                <button type="submit" class="btn-primary">Filter</button>
                <a href="merit.php" class="btn-disabled merit-reset-link">Reset</a>
            </form>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-wrapper merit-table-wrapper">
                    <table class="record-table merit-record-table better-merit-table">
                        <thead>
                            <tr>
                                <th>Activity Title</th>
                                <th>Type</th>
                                <th>Start Date</th>
                                <th>Hours</th>
                                <th>Merit Marks</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['activity_title']); ?></strong>
                                    </td>

                                    <td>
                                        <span class="merit-soft-tag">
                                            <?php echo htmlspecialchars($row['activity_type']); ?>
                                        </span>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['start_date']); ?></td>

                                    <td>
                                        <span class="badge badge-hours merit-hours-badge">
                                            <?php echo htmlspecialchars($row['hours_contributed']); ?> hrs
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge merit-points-badge">
                                            <?php echo (int) $row['display_merit_points']; ?> pts
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($row['status'] === 'Completed'): ?>
                                            <span class="merit-status-badge badge-success">Approved</span>
                                        <?php elseif ($row['status'] === 'Rejected'): ?>
                                            <span class="merit-status-badge badge-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="merit-status-badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="merit-description-cell">
                                        <?php echo !empty($row['description']) ? nl2br(htmlspecialchars($row['description'])) : '-'; ?>
                                    </td>

                                    <td>
                                        <div class="merit-action-links">
                                            <a href="edit_merit.php?id=<?php echo $row['merit_id']; ?>" class="text-link edit">✎ Edit</a>
                                            <a href="merit.php?delete=<?php echo $row['merit_id']; ?>" onclick="return confirm('Delete this merit record?');" class="text-link delete">🗑 Delete</a>
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
                    <p class="merit-empty-text">Adjust your filters or add a new merit record.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>