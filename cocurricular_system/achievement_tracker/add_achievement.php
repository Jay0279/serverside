<?php
include '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";

if (isset($_POST['submit'])) {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $achievement_date = trim($_POST['achievement_date']);
    $level = trim($_POST['level']);
    $description = trim($_POST['description']);

    // SECURITY UPGRADE: Automatically force status to Pending Verification [cite: 17, 18]
    $status = "Pending Verification";

    // --- FILE UPLOAD LOGIC ---
    $evidence_file = NULL;

    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] == UPLOAD_ERR_OK) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $file_extension = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
            $upload_path = "uploads/" . $new_filename;

            if (move_uploaded_file($_FILES['evidence']['tmp_name'], $upload_path)) {
                $evidence_file = $new_filename;
            } else {
                $error = "Failed to upload the evidence file to the server.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
        }
    }

    // --- DATABASE INSERT LOGIC ---
    if (empty($title) || empty($category) || empty($achievement_date) || empty($level)) {
        $error = "Please fill in all required fields.";
    } elseif (empty($error)) {
        $sql = "INSERT INTO achievements (user_id, title, category, achievement_date, level, description, status, evidence_file)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isssssss", $user_id, $title, $category, $achievement_date, $level, $description, $status, $evidence_file);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: achievements.php");
            exit();
        } else {
            $error = "Failed to save achievement.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Achievement | CCMS</title>
    <link rel="stylesheet" href="../../style.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .full-width {
            grid-column: span 2;
        }

        textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            outline: none;
            font-size: 1rem;
            font-family: inherit;
            transition: 0.3s;
            resize: vertical;
        }

        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
        }

        .cancel-btn {
            background: var(--border);
            color: var(--dark);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            font-weight: bold;
            display: inline-block;
            transition: 0.2s;
        }

        .cancel-btn:hover {
            background: #d1d5db;
        }

        .file-upload-box {
            border: 2px dashed var(--primary);
            padding: 1.5rem;
            text-align: center;
            border-radius: 12px;
            background: rgba(124, 58, 237, 0.05);
            transition: 0.3s;
        }

        .file-upload-box:hover {
            background: rgba(124, 58, 237, 0.1);
        }

        .file-upload-box input[type="file"] {
            display: block;
            margin: 10px auto;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: span 1;
            }
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
            <a href="../dashboard.php">📊 Dashboard</a>
            <a href="achievements.php" class="active">🏆 Achievements</a>
        </div>
        <a href="../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner" style="margin-bottom: 2rem;">
            <div>
                <p class="hero-label">New Record</p>
                <h1>Add Achievement ✨</h1>
                <p class="hero-text" style="color: var(--text-muted);">Insert a new achievement and upload your evidence. It will be sent for administrative review.</p>
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
                        <input type="text" name="title" placeholder="e.g. Best Presenter Award" required>
                    </div>

                    <div class="input-group">
                        <label>Category *</label>
                        <select name="category" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border); border-radius: 12px; outline: none; font-size: 1rem;">
                            <option value="">Select Category</option>
                            <option value="Academic">Academic</option>
                            <option value="Sports">Sports</option>
                            <option value="Leadership">Leadership</option>
                            <option value="Competition">Competition</option>
                            <option value="Arts & Culture">Arts & Culture</option>
                            <option value="Community Service">Community Service</option>
                            <option value="Innovation & Entrepreneurship">Innovation & Entrepreneurship</option>
                            <option value="Professional Certification">Professional Certification</option>
                            <option value="Clubs & Societies">Clubs & Societies</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Achievement Date *</label>
                        <input type="date" name="achievement_date" required>
                    </div>

                    <div class="input-group">
                        <label>Level *</label>
                        <select name="level" required style="width: 100%; padding: 0.8rem 1rem; border: 2px solid var(--border); border-radius: 12px; outline: none; font-size: 1rem;">
                            <option value="">Select Level</option>
                            <option value="University">University</option>
                            <option value="State">State</option>
                            <option value="National">National</option>
                            <option value="International">International</option>
                        </select>
                    </div>

                    <div class="input-group full-width">
                        <label>Description (Optional)</label>
                        <textarea name="description" rows="4"></textarea>
                    </div>

                    <div class="input-group full-width">
                        <label>Certificate / Evidence (Optional)</label>
                        <div class="file-upload-box">
                            <span style="font-size: 2rem;">📷</span>
                            <p style="margin: 10px 0; font-weight: bold; color: var(--primary);">Upload Image or PDF</p>
                            <input type="file" name="evidence" accept=".jpg, .jpeg, .png, .pdf" capture="environment">
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Max file size: 2MB</p>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" name="submit" class="btn-primary" style="flex: 1;">Save Achievement</button>
                    <a href="achievements.php" class="cancel-btn" style="text-align: center;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>
