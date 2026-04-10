<?php
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Search and Filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$category = isset($_GET['category']) ? trim($_GET['category']) : "";
$level = isset($_GET['level']) ? trim($_GET['level']) : "";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Number of records per page
$offset = ($page - 1) * $limit;

// Base query
$base_sql = "FROM achievements WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $base_sql .= " AND title LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

if (!empty($category)) {
    $base_sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($level)) {
    $base_sql .= " AND level = ?";
    $params[] = $level;
    $types .= "s";
}

// --- EXPORT TO CSV FEATURE ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $export_sql = "SELECT title, category, achievement_date, level, status, description " . $base_sql . " ORDER BY achievement_date DESC";
    $stmt = mysqli_prepare($conn, $export_sql);
    if($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=My_Achievements_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Title', 'Category', 'Date', 'Level', 'Status', 'Description'));

    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}
// -----------------------------

// Pagination Total Count
$count_sql = "SELECT COUNT(*) AS total " . $base_sql;
$stmt_total = mysqli_prepare($conn, $count_sql);
if($types) mysqli_stmt_bind_param($stmt_total, $types, ...$params);
mysqli_stmt_execute($stmt_total);
$total_result = mysqli_stmt_get_result($stmt_total);
$total_row = mysqli_fetch_assoc($total_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Fetch Paginated Data
$sql = "SELECT * " . $base_sql . " ORDER BY achievement_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Build query string for pagination links to preserve filters
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
    <link rel="stylesheet" href="../../style.css">
    <style>
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef08a; color: #854d0e; }
        .pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: flex-end; }
        .page-link { padding: 8px 12px; border-radius: 8px; background: #e5e7eb; color: #374151; text-decoration: none; font-weight: bold; transition: 0.2s; }
        .page-link:hover { background: #d1d5db; }
        .page-link.active { background: var(--primary); color: white; }
        .action-bar { display: flex; gap: 10px; align-items: center; }
        .btn-outline { border: 2px solid var(--primary); color: var(--primary); padding: 0.7rem 1.2rem; border-radius: 12px; text-decoration: none; font-weight: bold; transition: 0.2s; }
        .btn-outline:hover { background: var(--primary); color: white; }
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
            <a href="#">📅 Event Tracker</a>
            <a href="#">👥 Club Tracker</a>
            <a href="#">⏱️ Merit Tracker</a>
            <a href="achievements.php" class="active">🏆 Achievements</a>
        </div>

        <a href="../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="margin-bottom: 2rem;">
            <div>
                <p class="hero-label">Achievement Module</p>
                <h1>My Achievements 🏆</h1>
                <p class="hero-text" style="color: var(--text-muted);">Manage and organize your awards, certificates, and recognitions.</p>
            </div>
            <div class="action-bar">
                <a href="?export=csv&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&level=<?php echo urlencode($level); ?>" class="btn-outline">📥 Export CSV</a>
                <a href="add_achievement.php" class="btn-primary">+ Add New</a>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Records Dashboard</h2>
                <span class="badge" style="background: var(--bg-light); color: var(--text-muted);">Total: <?php echo $total_records; ?> Found</span>
            </div>

            <form method="GET" class="filter-form" style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
                <input type="text" name="search" placeholder="Search title..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 2; padding: 0.8rem; border-radius: 10px; border: 1px solid var(--border);">
                
                <select name="category" style="flex: 1; padding: 0.8rem; border-radius: 10px; border: 1px solid var(--border);">
                    <option value="">All Categories</option>
                    <option value="Academic" <?php if ($category == "Academic") echo "selected"; ?>>Academic</option>
                    <option value="Sports" <?php if ($category == "Sports") echo "selected"; ?>>Sports</option>
                    <option value="Leadership" <?php if ($category == "Leadership") echo "selected"; ?>>Leadership</option>
                    <option value="Competition" <?php if ($category == "Competition") echo "selected"; ?>>Competition</option>
                    <option value="Others" <?php if ($category == "Others") echo "selected"; ?>>Others</option>
                </select>

                <select name="level" style="flex: 1; padding: 0.8rem; border-radius: 10px; border: 1px solid var(--border);">
                    <option value="">All Levels</option>
                    <option value="University" <?php if ($level == "University") echo "selected"; ?>>University</option>
                    <option value="State" <?php if ($level == "State") echo "selected"; ?>>State</option>
                    <option value="National" <?php if ($level == "National") echo "selected"; ?>>National</option>
                    <option value="International" <?php if ($level == "International") echo "selected"; ?>>International</option>
                </select>

                <button type="submit" class="btn-primary" style="padding: 0.8rem 1.5rem;">Filter</button>
                <a href="achievements.php" class="btn-disabled" style="text-decoration: none; padding: 0.8rem 1.5rem;">Reset</a>
            </form>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead style="background: var(--bg-light); border-bottom: 2px solid var(--border);">
                            <tr>
                                <th style="padding: 1rem;">Title</th>
                                <th style="padding: 1rem;">Category</th>
                                <th style="padding: 1rem;">Date</th>
                                <th style="padding: 1rem;">Level</th>
                                <th style="padding: 1rem;">Status</th>
                                <th style="padding: 1rem; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem;"><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['achievement_date']); ?></td>
                                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['level']); ?></td>
                                    <td style="padding: 1rem;">
                                        <?php 
                                            $badgeClass = ($row['status'] == 'Completed') ? 'badge-success' : 'badge-warning';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>" style="padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: bold;">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <a href="edit_achievement.php?id=<?php echo $row['id']; ?>" style="color: var(--secondary); text-decoration: none; font-weight: bold; margin-right: 10px;">✎ Edit</a>
                                        <a href="delete_achievement.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this achievement?');" style="color: var(--danger-text); text-decoration: none; font-weight: bold;">🗑 Delete</a>
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
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Adjust your filters or add a new achievement.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
