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
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

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

$count_sql = "SELECT COUNT(*) AS total " . $base_sql;
$stmt_total = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($stmt_total, $types, ...$params);
mysqli_stmt_execute($stmt_total);
$total_result = mysqli_stmt_get_result($stmt_total);
$total_row = mysqli_fetch_assoc($total_result);
$total_records = (int) $total_row['total'];
$total_pages = max(1, ceil($total_records / $limit));

$sql = "SELECT a.*, e.event_title " . $base_sql . " ORDER BY a.created_at DESC, a.id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

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
    <style>
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef08a; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .history-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            margin-top: 8px;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
        .pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: flex-end; flex-wrap: wrap; }
        .page-link { padding: 8px 12px; border-radius: 8px; background: #e5e7eb; color: #374151; text-decoration: none; font-weight: bold; }
        .page-link.active { background: var(--primary); color: white; }
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
            <a href="../merit_tracker/merit.php">⏱️ Merit Tracker</a>
            <a href="achievements.php" class="active">🏆 Achievements</a>
        </div>

        <a href="../../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="margin-bottom: 2rem;">
            <div>
                <p class="hero-label">Achievement Module</p>
                <h1>My Achievements 🏆</h1>
                <p class="hero-text" style="color: var(--text-muted);">Track your submission result, approval history, and admin feedback.</p>
            </div>
            <a href="add_achievement.php" class="btn-primary">+ Add New</a>
        </div>

        <?php if ($success == 'added'): ?>
            <div class="alert success">Achievement record added successfully.</div>
        <?php elseif ($success == 'updated'): ?>
            <div class="alert success">Achievement record updated successfully.</div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Records Dashboard</h2>
                <span style="color:var(--text-muted);">Total: <?php echo $total_records; ?></span>
            </div>

            <form method="GET" class="filter-form" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:1.5rem;">
                <input type="text" name="search" placeholder="Search title or event..." value="<?php echo htmlspecialchars($search); ?>" style="flex:2;">
                <select name="category" style="flex:1;">
                    <option value="">All Categories</option>
                    <option value="Academic" <?php if ($category == "Academic") echo "selected"; ?>>Academic</option>
                    <option value="Sports" <?php if ($category == "Sports") echo "selected"; ?>>Sports</option>
                    <option value="Leadership" <?php if ($category == "Leadership") echo "selected"; ?>>Leadership</option>
                    <option value="Competition" <?php if ($category == "Competition") echo "selected"; ?>>Competition</option>
                    <option value="Others" <?php if ($category == "Others") echo "selected"; ?>>Others</option>
                </select>
                <select name="level" style="flex:1;">
                    <option value="">All Levels</option>
                    <option value="University" <?php if ($level == "University") echo "selected"; ?>>University</option>
                    <option value="State" <?php if ($level == "State") echo "selected"; ?>>State</option>
                    <option value="National" <?php if ($level == "National") echo "selected"; ?>>National</option>
                    <option value="International" <?php if ($level == "International") echo "selected"; ?>>International</option>
                </select>
                <button type="submit" class="btn-primary">Filter</button>
                <a href="achievements.php" class="btn-disabled" style="text-decoration:none;">Reset</a>
            </form>

            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <div class="table-wrapper">
                    <table class="record-table">
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
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                        <span style="color:var(--text-muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['achievement_date']); ?></span>
                                    </td>
                                    <td><?php echo !empty($row['event_title']) ? htmlspecialchars($row['event_title']) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><?php echo htmlspecialchars($row['level']); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'Completed'): ?>
                                            <span class="badge-success" style="padding:0.4rem 0.8rem;border-radius:999px;font-weight:700;">Approved</span>
                                        <?php elseif ($row['status'] === 'Rejected'): ?>
                                            <span class="badge-danger" style="padding:0.4rem 0.8rem;border-radius:999px;font-weight:700;">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge-warning" style="padding:0.4rem 0.8rem;border-radius:999px;font-weight:700;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="history-box">
                                            <strong>Submitted:</strong> <?php echo htmlspecialchars($row['created_at']); ?><br>
                                            <strong>Reviewed:</strong> <?php echo !empty($row['reviewed_at']) ? htmlspecialchars($row['reviewed_at']) : '-'; ?><br>
                                            <strong>Admin Remark:</strong> <?php echo !empty($row['admin_remark']) ? nl2br(htmlspecialchars($row['admin_remark'])) : '-'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="edit_achievement.php?id=<?php echo $row['id']; ?>" class="text-link edit">✎ Edit</a>
                                        <a href="delete_achievement.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this achievement?');" class="text-link delete">🗑 Delete</a>
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
                <div style="text-align:center;padding:3rem 0;">
                    <div style="font-size:3rem;margin-bottom:1rem;">📭</div>
                    <h3 style="color:var(--dark);margin-bottom:0.5rem;">No records found</h3>
                    <p style="color:var(--text-muted);">Add a new achievement to start tracking your history.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>