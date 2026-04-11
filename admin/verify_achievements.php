<?php
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

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
        }
    }
}

$pending_count = 0;
$count_sql = "SELECT COUNT(*) AS total FROM achievements WHERE status = 'Pending Verification'";
$count_result = mysqli_query($conn, $count_sql);
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $pending_count = (int) ($count_row['total'] ?? 0);
}

$sql = "SELECT a.*, u.username, e.event_title
        FROM achievements a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN events e ON a.event_id = e.id
        WHERE a.status = 'Pending Verification'
        ORDER BY a.achievement_date DESC";
$result = mysqli_query($conn, $sql);
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
        .verification-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .remark-box {
            width: 100%;
            margin-top: 0.8rem;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 10px;
        }
        .action-row {
            display: flex;
            gap: 10px;
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
        .preview-img {
            max-width: 260px;
            margin-top: 12px;
            border-radius: 12px;
            border: 1px solid var(--border);
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
                <p style="opacity: 0.9; margin-top: 0.5rem;">Pending: <?php echo $pending_count; ?> submission(s)</p>
            </div>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'approved'): ?>
            <div class="alert success">✅ Record approved successfully.</div>
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'rejected'): ?>
            <div class="alert error">❌ Record rejected successfully.</div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Pending Submissions (<?php echo $filtered_count; ?>)</h2>
            </div>

            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="verification-card">
                        <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                        <div style="color:var(--text-muted);margin-top:8px;line-height:1.6;">
                            Student: @<?php echo htmlspecialchars($row['username']); ?><br>
                            Category: <?php echo htmlspecialchars($row['category']); ?><br>
                            Level: <?php echo htmlspecialchars($row['level']); ?><br>
                            Related Event: <?php echo !empty($row['event_title']) ? htmlspecialchars($row['event_title']) : '-'; ?><br>
                            Date: <?php echo htmlspecialchars($row['achievement_date']); ?>
                        </div>

                        <?php if (!empty($row['description'])): ?>
                            <div style="margin-top:12px;color:var(--text-muted);">
                                <strong>Description:</strong><br>
                                <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($row['evidence_file'])): ?>
                            <?php
                                $file = $row['evidence_file'];
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $file_url = "../achievement_tracker/uploads/" . rawurlencode($file);
                            ?>
                            <div style="margin-top:12px;">
                                <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" class="btn-secondary">🔍 Open Evidence</a>
                                <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                    <div>
                                        <img src="<?php echo htmlspecialchars($file_url); ?>" alt="Evidence Preview" class="preview-img">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <textarea name="admin_remark" class="remark-box" placeholder="Optional remark for approval or rejection..."></textarea>

                            <div class="action-row">
                                <input type="hidden" name="approve_id" value="<?php echo (int) $row['id']; ?>">
                                <button type="submit" class="btn-primary" onclick="return confirm('Approve this record?');">✅ Approve</button>
                            </div>
                        </form>

                        <form method="POST" style="margin-top:8px;">
                            <textarea name="admin_remark" class="remark-box" placeholder="Reason for rejection..."></textarea>
                            <div class="action-row">
                                <input type="hidden" name="reject_id" value="<?php echo (int) $row['id']; ?>">
                                <button type="submit" class="btn-reject" onclick="return confirm('Reject this record?');">❌ Reject</button>
                            </div>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center;padding:3rem 0;">
                    <div style="font-size:3rem;margin-bottom:1rem;">🎉</div>
                    <h3 style="color:var(--dark);margin-bottom:0.5rem;">No pending submissions</h3>
                    <p style="color:var(--text-muted);">Everything has been reviewed.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>