<?php
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: achievements.php");
    exit();
}

$id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$error = "";

$sql = "SELECT * FROM achievements WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    header("Location: achievements.php");
    exit();
}

if (isset($_POST['update'])) {
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $achievement_date = trim($_POST['achievement_date']);
    $level = trim($_POST['level']);
    $description = trim($_POST['description']);
    
    // SECURITY UPGRADE: Automatically force status back to Pending Verification upon edit [cite: 17, 18]
    $status = "Pending Verification"; 
    
    $evidence_file = $row['evidence_file']; 

    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] == UPLOAD_ERR_OK) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $file_extension = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
            $upload_path = "uploads/" . $new_filename;
            
            if (move_uploaded_file($_FILES['evidence']['tmp_name'], $upload_path)) {
                if (!empty($row['evidence_file']) && file_exists("uploads/" . $row['evidence_file'])) {
                    unlink("uploads/" . $row['evidence_file']);
                }
                $evidence_file = $new_filename;
            } else {
                $error = "Failed to upload the new file.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
        }
    }

    if (empty($title) || empty($category) || empty($achievement_date) || empty($level)) {
        $error = "Please fill in all required fields.";
    } elseif (empty($error)) {
        $update_sql = "UPDATE achievements 
                       SET title=?, category=?, achievement_date=?, level=?, status=?, description=?, evidence_file=? 
                       WHERE id=? AND user_id=?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "sssssssii", $title, $category, $achievement_date, $level, $status, $description, $evidence_file, $id, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: achievements.php");
            exit();
        } else {
            $error = "Failed to update achievement.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Achievement | CCMS</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        .full-width { grid-column: span 2; }
        textarea { width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border); border-radius: 12px; outline: none; font-size: 1rem; font-family: inherit; transition: 0.3s; resize: vertical; }
        textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1); }
        .cancel-btn { background: var(--border); color: var(--dark); text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: bold; display: inline-block; transition: 0.2s; }
        .cancel-btn:hover { background: #d1d5db; }
        
        .file-upload-box { border: 2px dashed var(--primary); padding: 1.5rem; text-align: center; border-radius: 12px; background: rgba(124, 58, 237, 0.05); transition: 0.3s; }
        .file-upload-box:hover { background: rgba(124, 58, 237, 0.1); }
        .file-upload-box input[type="file"] { display: block; margin: 10px auto; }
        
        .current-file { background: var(--bg-light); padding: 10px 15px; border-radius: 8px; display: inline-block; margin-bottom: 15px; border: 1px solid var(--border); font-size: 0.9rem;}
        
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .full-width { grid-column: span 1; } }
    </style>
</head>
<body class="main-body">
    <div class="sidebar">
        <div>
            <h2>CCMS</h2>
            <p class="sidebar-subtitle">Student Portal</p>
        </div>
        <div class="nav-links">
            <a href="../dashboard.php">📊 Dashboard</a>
            <a href="achievements.php" class="active">🏆 Achievements</a>
        </div>
        <a href="../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="margin-bottom: 2rem;">
            <div>
                <p class="hero-label">Update Record</p>
                <h1>Edit Achievement ✎</h1>
                <p class="hero-text" style="color: var(--text-muted);">Modify your details. Note: Editing a verified record will return it to pending status.</p>
            </div>
            <a href="achievements.php" class="cancel-btn">← Back to List</a>
        </div>

        <div class="panel" style="max-width: 800px;">
            <?php if (!empty($error)): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Achievement Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($row['title']); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Category *</label>
                        <select name="category" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border); border-radius: 12px; outline: none; font-size: 1rem;">
                            <option value="Academic" <?php if ($row['category'] == "Academic") echo "selected"; ?>>Academic</option>
                            <option value="Sports" <?php if ($row['category'] == "Sports") echo "selected"; ?>>Sports</option>
                            <option value="Leadership" <?php if ($row['category'] == "Leadership") echo "selected"; ?>>Leadership</option>
                            <option value="Competition" <?php if ($row['category'] == "Competition") echo "selected"; ?>>Competition</option>
                            <option value="Others" <?php if ($row['category'] == "Others") echo "selected"; ?>>Others</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Achievement Date *</label>
                        <input type="date" name="achievement_date" value="<?php echo htmlspecialchars($row['achievement_date']); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Level *</label>
                        <select name="level" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border); border-radius: 12px; outline: none; font-size: 1rem;">
                            <option value="University" <?php if ($row['level'] == "University") echo "selected"; ?>>University</option>
                            <option value="State" <?php if ($row['level'] == "State") echo "selected"; ?>>State</option>
                            <option value="National" <?php if ($row['level'] == "National") echo "selected"; ?>>National</option>
                            <option value="International" <?php if ($row['level'] == "International") echo "selected"; ?>>International</option>
                        </select>
                    </div>

                    <div class="input-group full-width">
                        <label>Description (Optional)</label>
                        <textarea name="description" rows="4"><?php echo htmlspecialchars($row['description']); ?></textarea>
                    </div>

                    <div class="input-group full-width">
                        <label>Update Certificate / Evidence (Optional)</label>
                        
                        <?php if (!empty($row['evidence_file'])): ?>
                            <div class="current-file">
                                <strong>Current File:</strong> 
                                <a href="uploads/<?php echo htmlspecialchars($row['evidence_file']); ?>" target="_blank" style="color: var(--primary); text-decoration: none;">View Attached File 📄</a>
                            </div>
                        <?php endif; ?>

                        <div class="file-upload-box">
                            <span style="font-size: 2rem;">🔄</span>
                            <p style="margin: 10px 0; font-weight: bold; color: var(--primary);">Upload New Image or PDF to replace current</p>
                            <input type="file" name="evidence" accept=".jpg, .jpeg, .png, .pdf" capture="environment">
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Leave empty to keep your current evidence. Max size: 2MB.</p>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" name="update" class="btn-primary" style="flex: 1;">Update Details</button>
                    <a href="achievements.php" class="cancel-btn" style="text-align: center;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>