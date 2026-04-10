<?php
include '../config.php';

// Kick them out if they aren't an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

$username = $_SESSION['username'];

// --- APPROVAL LOGIC ---
// If the admin clicks the "Approve" button, it runs this code to update the database
if (isset($_GET['approve_id'])) {
    $approve_id = intval($_GET['approve_id']);
    
    $update_sql = "UPDATE achievements SET status = 'Completed' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "i", $approve_id);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: verify_achievements.php?success=1");
        exit();
    }
}

// Fetch all achievements that are marked as "Pending Verification"
// We JOIN with the users table so we can see which student submitted it
$sql = "SELECT a.*, u.username 
        FROM achievements a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.status = 'Pending Verification' 
        ORDER BY a.achievement_date ASC";
$result = mysqli_query($conn, $sql);
$pending_count = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Achievements | CCMS Admin</title>
    <link rel="stylesheet" href="../style.css">
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
                <?php if($pending_count > 0): ?>
                    <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; margin-left: 5px;"><?php echo $pending_count; ?></span>
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
                <p style="opacity: 0.9; margin-top: 0.5rem;">Review student evidence and approve their co-curricular achievements.</p>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">✅ Record has been successfully verified and updated to 'Completed'.</div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <h2 style="color: var(--dark);">Pending Submissions (<?php echo $pending_count; ?>)</h2>
            </div>

            <?php if ($pending_count > 0): ?>
                <div class="table-wrapper" style="overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead style="background: var(--bg-light); border-bottom: 2px solid var(--border);">
                            <tr>
                                <th style="padding: 1rem;">Student</th>
                                <th style="padding: 1rem;">Achievement Title</th>
                                <th style="padding: 1rem;">Level</th>
                                <th style="padding: 1rem;">Evidence File</th>
                                <th style="padding: 1rem; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem;"><strong>@<?php echo htmlspecialchars($row['username']); ?></strong></td>
                                    <td style="padding: 1rem;"><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['level']); ?></td>
                                    
                                    <td style="padding: 1rem;">
                                        <?php if (!empty($row['evidence_file'])): ?>
                                            <a href="../achievement_tracker/uploads/<?php echo htmlspecialchars($row['evidence_file']); ?>" target="_blank" style="background: #e0e7ff; color: #4338ca; padding: 0.4rem 0.8rem; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: bold; display: inline-block;">
                                                🔍 View Certificate
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #ef4444; font-size: 0.85rem; font-weight: bold;">No file uploaded</span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding: 1rem; text-align: center;">
                                        <a href="?approve_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to verify this achievement?');" style="background: #10b981; color: white; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block; transition: 0.2s;">
                                            ✅ Approve
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem 0;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🎉</div>
                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">All Caught Up!</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">There are no pending achievements waiting for verification.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>