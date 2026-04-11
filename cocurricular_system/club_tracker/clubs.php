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
    $club_id = (int) $_GET['delete'];

    $delete_sql = "DELETE FROM clubs WHERE club_id = ? AND user_id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($stmt_delete, "ii", $club_id, $user_id);

    if (mysqli_stmt_execute($stmt_delete)) {
        header("Location: clubs.php?success=deleted");
        exit();
    }
}

// FILTER & SEARCH INPUT
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$membership_status = isset($_GET['membership_status']) ? trim($_GET['membership_status']) : "";

// SORTING FUNCTION
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : "created_at";
$order = isset($_GET['order']) ? $_GET['order'] : "DESC";

$allowed_sort = ['club_name', 'role_position', 'join_date', 'membership_status', 'created_at'];
$allowed_order = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'created_at';
}

if (!in_array($order, $allowed_order)) {
    $order = 'DESC';
}

// PAGINATION
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// BASE QUERY
$base_sql = "FROM clubs WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $base_sql .= " AND (club_name LIKE ? OR role_position LIKE ? OR club_category LIKE ?)";
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

// SUMMARY
$summary_sql = "SELECT 
                COUNT(*) AS total_records,
                SUM(CASE WHEN membership_status = 'Active' THEN 1 ELSE 0 END) AS active_records
                FROM clubs
                WHERE user_id = ?";
$stmt_summary = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($stmt_summary, "i", $user_id);
mysqli_stmt_execute($stmt_summary);
$summary_result = mysqli_stmt_get_result($stmt_summary);
$summary_row = mysqli_fetch_assoc($summary_result);

$total_records = $summary_row['total_records'] ?? 0;
$active_records = $summary_row['active_records'] ?? 0;

// FILTERED COUNT
$count_sql = "SELECT COUNT(*) AS total " . $base_sql;
$stmt_count = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$count_row = mysqli_fetch_assoc($count_result);

$filtered_records = $count_row['total'];
$total_pages = ceil($filtered_records / $limit);

// FETCH DATA
$sql = "SELECT * " . $base_sql . " 
        ORDER BY $sort_by $order 
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
    <title>Club Tracker | CCMS</title>
    <link rel="stylesheet" href="../../style.css">
    <style>
        .pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: flex-end; }
        .page-link { padding: 8px 12px; border-radius: 8px; background: #e5e7eb; color: #374151; text-decoration: none; font-weight: bold; transition: 0.2s; }
        .page-link:hover { background: #d1d5db; }
        .page-link.active { background: var(--primary); color: white; }
        .action-bar { display: flex; gap: 10px; align-items: center; }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        .summary-cards {
            display: flex;
            gap: 16px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .summary-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1rem 1.2rem;
            min-width: 220px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.04);
        }
        .summary-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 6px;
        }
        .summary-value {
            color: var(--dark);
            font-size: 1.6rem;
            font-weight: bold;
        }
        .badge-active {
            background: #dcfce7;
            color: #166534;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .badge-past {
            background: #e5e7eb;
            color: #374151;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
    </style>
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
                <h1>My Club Records 👥</h1>
                <p class="hero-text" style="color: var(--text-muted);">Manage your club memberships, leadership roles, and society involvement.</p>
            </div>
            <div class="action-bar">
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
                <h2 style="color: var(--dark);">Club Dashboard</h2>
                <span class="badge" style="background: var(--bg-light); color: var(--text-muted);">
                    Showing <?php echo mysqli_num_rows($result); ?> record(s)
                </span>
            </div>

            <form method="GET" style="display: flex; gap: 10px; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <input type="text" name="search" placeholder="Search club, role, or category..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       style="flex:2; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">

                <select name="membership_status" style="flex:1; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                    <option value="">All Status</option>
                    <option value="Active" <?php if ($membership_status == "Active") echo "selected"; ?>>Active</option>
                    <option value="Inactive" <?php if ($membership_status == "Inactive") echo "selected"; ?>>Inactive</option>
                    <option value="Completed" <?php if ($membership_status == "Completed") echo "selected"; ?>>Completed</option>
                </select>

                <select name="sort_by" style="flex:1; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                    <option value="club_name" <?php if ($sort_by == "club_name") echo "selected"; ?>>Sort by Club</option>
                    <option value="role_position" <?php if ($sort_by == "role_position") echo "selected"; ?>>Sort by Role</option>
                    <option value="join_date" <?php if ($sort_by == "join_date") echo "selected"; ?>>Sort by Join Date</option>
                    <option value="membership_status" <?php if ($sort_by == "membership_status") echo "selected"; ?>>Sort by Status</option>
                </select>

                <select name="order" style="flex:1; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                    <option value="ASC" <?php if ($order == "ASC") echo "selected"; ?>>Ascending</option>
                    <option value="DESC" <?php if ($order == "DESC") echo "selected"; ?>>Descending</option>
                </select>

                <button type="submit" class="btn-primary" style="padding: 0.8rem 1.5rem;">Filter</button>
                <a href="clubs.php" class="btn-primary" style="text-decoration: none; padding: 0.8rem 1.5rem;">Reset</a>
            </form>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-wrapper" style="overflow-x:auto; background:white; border-radius:12px; border:1px solid var(--border);">
                    <table style="width:100%; border-collapse:collapse; text-align:left;">
                        <thead style="background: var(--bg-light); border-bottom: 2px solid var(--border);">
                            <tr>
                                <th style="padding: 1rem;">Club Name</th>
                                <th style="padding: 1rem;">Category</th>
                                <th style="padding: 1rem;">Role</th>
                                <th style="padding: 1rem;">Join Date</th>
                                <th style="padding: 1rem;">End Date</th>
                                <th style="padding: 1rem;">Status</th>
                                <th style="padding: 1rem; text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding:1rem;"><strong><?php echo htmlspecialchars($row['club_name']); ?></strong></td>
                                    <td style="padding:1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['club_category']); ?></td>
                                    <td style="padding:1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['role_position']); ?></td>
                                    <td style="padding:1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['join_date']); ?></td>
                                    <td style="padding:1rem; color: var(--text-muted);"><?php echo !empty($row['end_date']) ? htmlspecialchars($row['end_date']) : '-'; ?></td>
                                    <td style="padding:1rem;">
                                        <?php if ($row['membership_status'] == 'Active'): ?>
                                            <span class="badge-active">Active</span>
                                        <?php elseif ($row['membership_status'] == 'Inactive'): ?>
                                            <span class="badge-inactive">Inactive</span>
                                        <?php else: ?>
                                            <span class="badge-past"><?php echo htmlspecialchars($row['membership_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:1rem; text-align:center;">
                                        <a href="view_club.php?id=<?php echo $row['club_id']; ?>" style="color:#2563eb; text-decoration:none; font-weight:bold; margin-right:10px;">👁 View</a>
                                        <a href="edit_club.php?id=<?php echo $row['club_id']; ?>" style="color: var(--secondary); text-decoration:none; font-weight:bold; margin-right:10px;">✎ Edit</a>
                                        <a href="clubs.php?delete=<?php echo $row['club_id']; ?>" onclick="return confirm('Delete this club record?');" style="color: var(--danger-text); text-decoration:none; font-weight:bold;">🗑 Delete</a>
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
                <div style="text-align:center; padding:3rem 0;">
                    <div style="font-size:3rem; margin-bottom:1rem;">📭</div>
                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No club records found</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Adjust your filters or add a new club record.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>