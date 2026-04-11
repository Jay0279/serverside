<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: achievements.php");
    exit();
}

$id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];
$error = "";

// Fetch existing achievement
$sql = "SELECT * FROM achievements WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    header("Location: achievements.php");
    exit();
}

// Fetch completed events
$events = [];
$event_sql = "SELECT id, event_title, event_category, event_date, participation_role, organizer, merit_points
              FROM events
              WHERE user_id = ? AND event_status = 'Completed'
              ORDER BY event_date DESC, id DESC";
$event_stmt = mysqli_prepare($conn, $event_sql);
mysqli_stmt_bind_param($event_stmt, "i", $user_id);
mysqli_stmt_execute($event_stmt);
$event_result = mysqli_stmt_get_result($event_stmt);

while ($event_row = mysqli_fetch_assoc($event_result)) {
    $events[] = $event_row;
}
mysqli_stmt_close($event_stmt);

if (isset($_POST['update'])) {
    $event_id = isset($_POST['event_id']) && ctype_digit($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $achievement_date = trim($_POST['achievement_date']);
    $level = trim($_POST['level']);
    $description = trim($_POST['description']);
    $status = "Pending Verification";
    $evidence_file = $row['evidence_file'];

    // Validate event
    if ($event_id > 0) {
        $check_event_sql = "SELECT id FROM events WHERE id = ? AND user_id = ? AND event_status = 'Completed' LIMIT 1";
        $check_event_stmt = mysqli_prepare($conn, $check_event_sql);
        mysqli_stmt_bind_param($check_event_stmt, "ii", $event_id, $user_id);
        mysqli_stmt_execute($check_event_stmt);
        $check_event_result = mysqli_stmt_get_result($check_event_stmt);
        $valid_event = mysqli_num_rows($check_event_result) > 0;
        mysqli_stmt_close($check_event_stmt);

        if (!$valid_event) {
            $error = "Selected event is invalid.";
        }
    } else {
        $error = "Please select a related completed event.";
    }

    // Upload new evidence if provided
    if (empty($error) && isset($_FILES['evidence']) && $_FILES['evidence']['error'] == UPLOAD_ERR_OK) {
        if ($_FILES['evidence']['size'] > 2 * 1024 * 1024) {
            $error = "File size must not exceed 2MB.";
        } else {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            $file_extension = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));

            if (in_array($file_extension, $allowed_extensions)) {
                if (!is_dir("uploads")) {
                    mkdir("uploads", 0777, true);
                }

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
                $error = "Invalid file type. Only JPG, JPEG, PNG, and PDF are allowed.";
            }
        }
    }

    if (empty($title) || empty($category) || empty($achievement_date) || empty($level)) {
        $error = "Please fill in all required fields.";
    } elseif (empty($error)) {
        $update_sql = "UPDATE achievements
                       SET event_id = ?, title = ?, category = ?, achievement_date = ?, level = ?, status = ?, description = ?, evidence_file = ?
                       WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param(
            $stmt,
            "isssssssii",
            $event_id,
            $title,
            $category,
            $achievement_date,
            $level,
            $status,
            $description,
            $evidence_file,
            $id,
            $user_id
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: achievements.php?success=updated");
            exit();
        } else {
            $error = "Failed to update achievement.";
        }
    }

    // keep entered values if validation fails
    $row['event_id'] = $event_id;
    $row['title'] = $title;
    $row['category'] = $category;
    $row['achievement_date'] = $achievement_date;
    $row['level'] = $level;
    $row['description'] = $description;
    $row['evidence_file'] = $evidence_file;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Achievement | CCMS</title>
    <link rel="stylesheet" href="../../style.css?v=<?php echo time(); ?>">
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

        .current-file {
            background: var(--bg-light);
            padding: 10px 15px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 15px;
            border: 1px solid var(--border);
            font-size: 0.9rem;
        }

        .hint-box {
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #3730a3;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-top: 0.6rem;
            line-height: 1.5;
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
                <p class="hero-label">Update Record</p>
                <h1>Edit Achievement ✎</h1>
                <p class="hero-text" style="color: var(--text-muted);">
                    Modify your details. Editing a record will send it back for verification.
                </p>
            </div>
            <a href="achievements.php" class="cancel-btn">← Back to List</a>
        </div>

        <div class="panel" style="max-width: 900px;">
            <?php if (!empty($error)): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="input-group full-width">
                        <label>Related Completed Event *</label>
                        <select name="event_id" id="event_id" required onchange="fillAchievementSuggestion()">
                            <option value="">Select Completed Event</option>
                            <?php foreach ($events as $event): ?>
                                <option
                                    value="<?php echo (int) $event['id']; ?>"
                                    data-title="<?php echo htmlspecialchars($event['event_title'], ENT_QUOTES); ?>"
                                    data-category="<?php echo htmlspecialchars($event['event_category'], ENT_QUOTES); ?>"
                                    data-date="<?php echo htmlspecialchars($event['event_date'], ENT_QUOTES); ?>"
                                    data-role="<?php echo htmlspecialchars($event['participation_role'], ENT_QUOTES); ?>"
                                    data-organizer="<?php echo htmlspecialchars($event['organizer'], ENT_QUOTES); ?>"
                                    data-merit="<?php echo (int) $event['merit_points']; ?>"
                                    <?php echo ((int) $row['event_id'] === (int) $event['id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($event['event_title'] . " - " . $event['event_date'] . " (" . $event['event_category'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint-box" id="event_hint">
                            Select a completed event to view related details.
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Achievement Title *</label>
                        <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($row['title']); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Category *</label>
                        <select name="category" id="category" required>
                            <?php
                            $categories = [
                                "Academic",
                                "Sports",
                                "Leadership",
                                "Competition",
                                "Arts & Culture",
                                "Community Service",
                                "Innovation & Entrepreneurship",
                                "Professional Certification",
                                "Clubs & Societies",
                                "Others"
                            ];
                            foreach ($categories as $cat):
                            ?>
                                <option value="<?php echo $cat; ?>" <?php echo ($row['category'] === $cat) ? 'selected' : ''; ?>>
                                    <?php echo $cat; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Achievement Date *</label>
                        <input type="date" name="achievement_date" id="achievement_date" value="<?php echo htmlspecialchars($row['achievement_date']); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Level *</label>
                        <select name="level" id="level" required>
                            <option value="University" <?php if ($row['level'] == "University") echo "selected"; ?>>University</option>
                            <option value="State" <?php if ($row['level'] == "State") echo "selected"; ?>>State</option>
                            <option value="National" <?php if ($row['level'] == "National") echo "selected"; ?>>National</option>
                            <option value="International" <?php if ($row['level'] == "International") echo "selected"; ?>>International</option>
                        </select>
                    </div>

                    <div class="input-group full-width">
                        <label>Description (Optional)</label>
                        <textarea name="description" rows="4" id="description"><?php echo htmlspecialchars($row['description']); ?></textarea>
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
                            <input type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf">
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Leave empty to keep current evidence. Max size: 2MB.</p>
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

    <script>
        function fillAchievementSuggestion() {
            const select = document.getElementById('event_id');
            const option = select.options[select.selectedIndex];
            const hint = document.getElementById('event_hint');

            if (!option || !option.value) {
                hint.innerHTML = 'Select a completed event to view related details.';
                return;
            }

            const eventTitle = option.getAttribute('data-title') || '';
            const eventCategory = option.getAttribute('data-category') || '';
            const eventDate = option.getAttribute('data-date') || '';
            const role = option.getAttribute('data-role') || '';
            const merit = option.getAttribute('data-merit') || '0';

            hint.innerHTML =
                '<strong>Selected Event:</strong> ' + eventTitle +
                '<br><strong>Category:</strong> ' + eventCategory +
                ' | <strong>Role:</strong> ' + role +
                ' | <strong>Date:</strong> ' + eventDate +
                ' | <strong>Merit:</strong> ' + merit + ' pts';
        }

        document.addEventListener('DOMContentLoaded', function () {
            fillAchievementSuggestion();
        });
    </script>
</body>
</html>