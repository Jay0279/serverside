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



// SORTING FUNCTION
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : "created_at";
$order = isset($_GET['order']) ? $_GET['order'] : "DESC";

$allowed_sort = ['activity_title', 'start_date', 'hours_contributed', 'created_at'];
$allowed_order = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'created_at';
}

if (!in_array($order, $allowed_order)) {
    $order = 'DESC';
}



//PAGINATION SETUP
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;


// BASE QUERY
$base_sql = "FROM merits WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $base_sql .= " AND activity_title LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

if (!empty($activity_type)) {
    $base_sql .= " AND activity_type = ?";
    $params[] = $activity_type;
    $types .= "s";
}



// OVERALL SUMMARY - Total reords and total hours
$summary_sql = "SELECT 
                COUNT(*) AS total_records,
                COALESCE(SUM(CASE WHEN status = 'Completed' THEN hours_contributed ELSE 0 END), 0) AS total_hours
                FROM merits
                WHERE user_id = ?";
$stmt_summary = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($stmt_summary, "i", $user_id);
mysqli_stmt_execute($stmt_summary);

$summary_result = mysqli_stmt_get_result($stmt_summary);
$summary_row = mysqli_fetch_assoc($summary_result);

$total_records = $summary_row['total_records'];
$total_hours = $summary_row['total_hours'];



// FILTERED COUNT
$count_sql = "SELECT COUNT(*) AS total " . $base_sql;
$stmt_count = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);

$count_result = mysqli_stmt_get_result($stmt_count);
$count_row = mysqli_fetch_assoc($count_result);

$filtered_records = $count_row['total'];
$total_pages = ceil($filtered_records / $limit);


// FETCH DATA FOR TABLE
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



//PRESERVE QUERY PARAMETERS - Ensures pagination keeps filter & sorting values
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
    <link rel="stylesheet" href="../../style.css">
    <style>
        .pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: flex-end; }
        .page-link { padding: 8px 12px; border-radius: 8px; background: #e5e7eb; color: #374151; text-decoration: none; font-weight: bold; transition: 0.2s; }
        .page-link:hover { background: #d1d5db; }
        .page-link.active { background: var(--primary); color: white; }
        .action-bar { display: flex; gap: 10px; align-items: center; }
        .badge-hours { background: #dbeafe; color: #1d4ed8; }
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
                <h1>My Merit Records ⏱️</h1>
                <p class="hero-text" style="color: var(--text-muted);">Manage and organize your co-curricular contribution hours.</p>
            </div>
            <div class="action-bar">
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

        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-label">Total Records</div>
                <div class="summary-value"><?php echo $total_records; ?></div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Total Hours Contributed</div>
                <div class="summary-value"><?php echo number_format($total_hours, 2); ?> hrs</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Records Dashboard</h2>
                <span class="badge" style="background: var(--bg-light); color: var(--text-muted);">
                    Showing <?php echo mysqli_num_rows($result); ?> record(s)
                </span>
            </div>

            <form method="GET" class="filter-form" style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
                <input type="text" name="search" placeholder="Search activity title..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 2; padding: 0.8rem; border-radius: 10px; border: 1px solid var(--border);">

                <select name="activity_type" style="flex: 1; padding: 0.8rem; border-radius: 10px; border: 1px solid var(--border);">
                    <option value="">All Types</option>
                    <option value="Volunteering" <?php if ($activity_type == "Volunteering") echo "selected"; ?>>Volunteering</option>
                    <option value="Community Service" <?php if ($activity_type == "Community Service") echo "selected"; ?>>Community Service</option>
                    <option value="Committee Work" <?php if ($activity_type == "Committee Work") echo "selected"; ?>>Committee Work</option>
                    <option value="Charity Program" <?php if ($activity_type == "Charity Program") echo "selected"; ?>>Charity Program</option>
                    <option value="Others" <?php if ($activity_type == "Others") echo "selected"; ?>>Others</option>
                </select>

            
                <select name="sort_by" style="flex:1; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                    <option value="activity_title" <?php if ($sort_by == "activity_title") echo "selected"; ?>>Sort by Title</option>
                    <option value="start_date" <?php if ($sort_by == "start_date") echo "selected"; ?>>Sort by Start Date</option>
                    <option value="hours_contributed" <?php if ($sort_by == "hours_contributed") echo "selected"; ?>>Sort by Hours</option>
                </select>

                <select name="order" style="flex:1; padding:0.8rem; border-radius:10px; border:1px solid var(--border);">
                    <option value="ASC" <?php if ($order == "ASC") echo "selected"; ?>>Ascending</option>
                    <option value="DESC" <?php if ($order == "DESC") echo "selected"; ?>>Descending</option>
                </select>


                <button type="submit" class="btn-primary" style="padding: 0.8rem 1.5rem;">Filter</button>
                <a href="merit.php" class="btn-primary" style="text-decoration: none; padding: 0.8rem 1.5rem;">Reset</a>
            </form>


            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead style="background: var(--bg-light); border-bottom: 2px solid var(--border);">
                            <tr>
                                <th style="padding: 1rem;">Activity Title</th>
                                <th style="padding: 1rem;">Type</th>
                                <th style="padding: 1rem;">Start Date</th>
                                <th style="padding: 1rem;">Hours</th>
                                <th style="padding: 1rem;">Status</th>
                                <th style="padding: 1rem;">Description</th>
                                <th style="padding: 1rem; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem;"><strong><?php echo htmlspecialchars($row['activity_title']); ?></strong></td>
                                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['activity_type']); ?></td>
                                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['start_date']); ?></td>
                                    <td style="padding: 1rem;">
                                        <span class="badge badge-hours" style="padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: bold;">
                                            <?php echo htmlspecialchars($row['hours_contributed']); ?> hrs
                                        </span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </td>
                                    <td style="padding: 1rem; color: var(--text-muted); max-width: 250px;">
                                        <?php echo !empty($row['description']) ? nl2br(htmlspecialchars($row['description'])) : '-'; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <a href="edit_merit.php?id=<?php echo $row['merit_id']; ?>" style="color: var(--secondary); text-decoration: none; font-weight: bold; margin-right: 10px;">✎ Edit</a>
                                        <a href="merit.php?delete=<?php echo $row['merit_id']; ?>" onclick="return confirm('Delete this merit record?');" style="color: var(--danger-text); text-decoration: none; font-weight: bold;">🗑 Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?php echo $base_url . $i; ?>" class="page-link <?php if($i == $page) echo 'active'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align: center; padding: 3rem 0;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No records found</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Adjust your filters or add a new merit record.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

