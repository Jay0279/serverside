<?php
include '../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$search = trim($_GET['search'] ?? '');
$file_type = trim($_GET['file_type'] ?? '');

// ===== HELPER FUNCTIONS =====
function is_image_file($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

function is_pdf_file($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return $ext === 'pdf';
}

// IMPORTANT: fixed URL path for your actual folder structure
function safe_file_url($filename)
{
    return "../cocurricular_system/achievement_tracker/uploads/" . rawurlencode($filename);
}

// IMPORTANT: fixed physical path for file_exists()
function safe_file_path($filename)
{
    return __DIR__ . '/../cocurricular_system/achievement_tracker/uploads/' . $filename;
}

// ===== FORM ACTIONS: APPROVE / REJECT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_id']) && ctype_digit($_POST['approve_id'])) {
        $approve_id = (int) $_POST['approve_id'];
        $admin_remark = trim($_POST['admin_remark'] ?? '');

        $update_sql = "UPDATE achievements
                       SET status = 'Completed',
                           reviewed_at = NOW(),
                           reviewed_by = ?,
                           admin_remark = ?
                       WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "isi", $admin_id, $admin_remark, $approve_id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: verify_achievements.php?success=approved");
            exit();
        } else {
            header("Location: verify_achievements.php?success=error");
            exit();
        }
    }

    if (isset($_POST['reject_id']) && ctype_digit($_POST['reject_id'])) {
        $reject_id = (int) $_POST['reject_id'];
        $admin_remark = trim($_POST['admin_remark'] ?? '');

        $update_sql = "UPDATE achievements
                       SET status = 'Rejected',
                           reviewed_at = NOW(),
                           reviewed_by = ?,
                           admin_remark = ?
                       WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "isi", $admin_id, $admin_remark, $reject_id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: verify_achievements.php?success=rejected");
            exit();
        } else {
            header("Location: verify_achievements.php?success=error");
            exit();
        }
    }
}

// ===== BADGE COUNT =====
$pending_count = 0;
$count_sql = "SELECT COUNT(*) AS total FROM achievements WHERE status = 'Pending Verification'";
$count_result = mysqli_query($conn, $count_sql);
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $pending_count = (int) ($count_row['total'] ?? 0);
}

// ===== MAIN QUERY =====
$sql = "SELECT a.*, u.username, e.event_title
        FROM achievements a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN events e ON a.event_id = e.id
        WHERE a.status = 'Pending Verification'";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (u.username LIKE ? OR a.title LIKE ? OR a.category LIKE ? OR e.event_title LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if ($file_type === 'image') {
    $sql .= " AND (
        LOWER(a.evidence_file) LIKE '%.jpg' OR
        LOWER(a.evidence_file) LIKE '%.jpeg' OR
        LOWER(a.evidence_file) LIKE '%.png' OR
        LOWER(a.evidence_file) LIKE '%.gif' OR
        LOWER(a.evidence_file) LIKE '%.webp'
    )";
} elseif ($file_type === 'pdf') {
    $sql .= " AND LOWER(a.evidence_file) LIKE '%.pdf'";
} elseif ($file_type === 'none') {
    $sql .= " AND (a.evidence_file IS NULL OR a.evidence_file = '')";
}

$sql .= " ORDER BY a.achievement_date DESC, a.id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$filtered_count = $result ? mysqli_num_rows($result) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Achievements | CCMS Admin</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <style>
        .verification-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .submission-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }

        .submission-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .submission-top h3 {
            color: var(--dark);
            margin-bottom: 0.3rem;
        }

        .submission-meta {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .preview-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .preview-box img {
            max-width: 100%;
            max-height: 320px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: block;
            margin-top: 0.8rem;
        }

        .submission-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .btn-reject {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.8rem 1.2rem;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        .btn-view {
            background: #e0e7ff;
            color: #4338ca;
            padding: 0.8rem 1.2rem;
            border-radius: 10px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .search-row {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .search-row input,
        .search-row select,
        .search-row textarea {
            padding: 0.8rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            outline: none;
        }

        .status-pill {
            display: inline-block;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .remark-box {
            width: 100%;
            min-height: 90px;
            resize: vertical;
            margin-top: 1rem;
        }

        .mini-info {
            display: inline-block;
            margin-top: 0.6rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body class="main-body">
    <div class="sidebar" style="background: linear-gradient(180deg, #1e1b4b, #312e81);">
        <div>
            <h2>CCMS Admin</h2>
            <p class="sidebar-subtitle">Staff Portal</p>
        </div>

        <div class="nav-links">
            <a href="admin_dashboard.php">👥 User Management</a>
            <a href="verify_achievements.php" class="active">✅ Verification Inbox
                <?php if ($pending_count > 0): ?>
                    <span style="background:#ef4444;color:white;padding:2px 8px;border-radius:999px;font-size:0.8rem;margin-left:6px;"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <a href="../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="background: linear-gradient(135deg, #1e1b4b, #4338ca); color: white; margin-bottom: 2rem;">
            <div>
                <p class="hero-label" style="color: #c7d2fe;">Action Required</p>
                <h1 style="color: white;">Verification Inbox 📥</h1>
                <p style="opacity: 0.9; margin-top: 0.5rem;">Review student evidence and approve or reject achievements.</p>
            </div>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'approved'): ?>
            <div class="alert success">✅ Record approved successfully.</div>
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'rejected'): ?>
            <div class="alert error">❌ Record rejected successfully.</div>
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'error'): ?>
            <div class="alert error">❌ Action failed. Please try again.</div>
        <?php endif; ?>

        <div class="stats-container">
            <div class="stat-box purple">
                <span class="stat-label">Pending Verification</span>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">Needs admin review</span>
            </div>

            <div class="stat-box blue">
                <span class="stat-label">Filtered Results</span>
                <div class="stat-number"><?php echo $filtered_count; ?></div>
                <span class="stat-label" style="color: var(--text-muted); font-size: 0.8rem;">Based on current search</span>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Pending Submissions (<?php echo $filtered_count; ?>)</h2>
            </div>

            <form method="GET" class="search-row">
                <input
                    type="text"
                    name="search"
                    placeholder="Search by student, title, category, or event"
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="flex: 2; min-width: 260px;">

                <select name="file_type" style="flex: 1; min-width: 180px;">
                    <option value="">All Evidence Types</option>
                    <option value="image" <?php echo $file_type === 'image' ? 'selected' : ''; ?>>Image Only</option>
                    <option value="pdf" <?php echo $file_type === 'pdf' ? 'selected' : ''; ?>>PDF Only</option>
                    <option value="none" <?php echo $file_type === 'none' ? 'selected' : ''; ?>>No File</option>
                </select>

                <button type="submit" class="btn-primary">Search</button>
                <a href="verify_achievements.php" class="btn-secondary">Reset</a>
            </form>

            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <div class="verification-grid">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                        $has_file = !empty($row['evidence_file']);
                        $file_url = $has_file ? safe_file_url($row['evidence_file']) : '';
                        $file_exists = $has_file ? file_exists(safe_file_path($row['evidence_file'])) : false;
                        ?>
                        <div class="submission-card">
                            <div class="submission-top">
                                <div>
                                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                                    <div class="submission-meta">
                                        <strong>Student:</strong> @<?php echo htmlspecialchars($row['username']); ?><br>
                                        <strong>Category:</strong> <?php echo htmlspecialchars($row['category']); ?><br>
                                        <strong>Level:</strong> <?php echo htmlspecialchars($row['level']); ?><br>
                                        <strong>Date:</strong> <?php echo htmlspecialchars($row['achievement_date']); ?><br>
                                        <strong>Related Event:</strong> <?php echo !empty($row['event_title']) ? htmlspecialchars($row['event_title']) : '-'; ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-pill"><?php echo htmlspecialchars($row['status']); ?></span>
                                </div>
                            </div>

                            <?php if (!empty($row['description'])): ?>
                                <div class="preview-box">
                                    <strong>Description:</strong><br>
                                    <div style="margin-top: 0.5rem; color: var(--text-muted);">
                                        <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="preview-box">
                                <strong>Evidence Preview:</strong><br>

                                <?php if (!$has_file): ?>
                                    <p style="margin-top: 0.8rem; color: #ef4444; font-weight: bold;">No file uploaded</p>

                                <?php elseif (!$file_exists): ?>
                                    <p style="margin-top: 0.8rem; color: #ef4444; font-weight: bold;">
                                        File not found on server.
                                    </p>
                                    <div class="mini-info">
                                        Expected file name: <?php echo htmlspecialchars($row['evidence_file']); ?>
                                    </div>

                                <?php elseif (is_image_file($row['evidence_file'])): ?>
                                    <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" class="btn-view">🔍 Open Full Image</a>
                                    <img src="<?php echo htmlspecialchars($file_url); ?>" alt="Evidence Preview">

                                <?php elseif (is_pdf_file($row['evidence_file'])): ?>
                                    <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" class="btn-view">📄 Open PDF File</a>
                                    <p style="margin-top: 0.8rem; color: var(--text-muted);">PDF will open in a new tab.</p>

                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" class="btn-view">📎 Open Uploaded File</a>
                                <?php endif; ?>
                            </div>

                            <form method="POST">
                                <textarea name="admin_remark" class="remark-box" placeholder="Optional remark for approval or rejection..."></textarea>

                                <div class="submission-actions">
                                    <input type="hidden" name="approve_id" value="<?php echo (int) $row['id']; ?>">
                                    <button type="submit" class="btn-primary" onclick="return confirm('Approve this achievement?');">✅ Approve</button>
                                </div>
                            </form>

                            <form method="POST" style="margin-top: 8px;">
                                <textarea name="admin_remark" class="remark-box" placeholder="Reason for rejection..."></textarea>

                                <div class="submission-actions">
                                    <input type="hidden" name="reject_id" value="<?php echo (int) $row['id']; ?>">
                                    <button type="submit" class="btn-reject" onclick="return confirm('Reject this achievement?');">❌ Reject</button>
                                </div>
                            </form>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem 0;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🎉</div>
                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No pending submissions</h3>
                    <p style="color: var(--text-muted);">Everything has been reviewed.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>