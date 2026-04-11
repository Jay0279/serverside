<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$isEdit = isset($_GET['id']) && ctype_digit($_GET['id']);
$event_id = $isEdit ? (int) $_GET['id'] : 0;

$categories = ['Seminar', 'Workshop', 'Competition', 'Talk', 'Volunteer', 'Sports', 'Club Activity', 'Other'];
$roles = ['Participant', 'Committee', 'Facilitator', 'Volunteer', 'Representative', 'Speaker', 'Organizer'];
$statuses = ['Upcoming', 'Completed', 'Missed', 'Cancelled'];

// Fetch clubs belonging to this user for the dropdown
$clubsSql = 'SELECT club_id, club_name FROM clubs WHERE user_id = ? ORDER BY club_name ASC';
$clubsStmt = mysqli_prepare($conn, $clubsSql);
mysqli_stmt_bind_param($clubsStmt, 'i', $user_id);
mysqli_stmt_execute($clubsStmt);
$clubsResult = mysqli_stmt_get_result($clubsStmt);
$clubsList = mysqli_fetch_all($clubsResult, MYSQLI_ASSOC);
mysqli_stmt_close($clubsStmt);

$formData = [
    'club_id'            => '',
    'event_title'        => '',
    'organizer'          => '',
    'event_category'     => 'Workshop',
    'venue'              => '',
    'event_date'         => date('Y-m-d'),
    'start_time'         => '',
    'end_time'           => '',
    'participation_role' => 'Participant',
    'event_status'       => 'Upcoming',
    'event_hours'        => '0',
    'merit_points'       => '0',
    'remarks'            => ''
];

$error = '';

if ($isEdit) {
    $fetchSql = 'SELECT * FROM events WHERE id = ? AND user_id = ? LIMIT 1';
    $fetchStmt = mysqli_prepare($conn, $fetchSql);
    mysqli_stmt_bind_param($fetchStmt, 'ii', $event_id, $user_id);
    mysqli_stmt_execute($fetchStmt);
    $result = mysqli_stmt_get_result($fetchStmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($fetchStmt);

    if (!$existing) {
        header('Location: events.php?msg=error');
        exit();
    }

    $formData = [
        'club_id'            => $existing['club_id'] ?? '',
        'event_title'        => $existing['event_title'],
        'organizer'          => $existing['organizer'],
        'event_category'     => $existing['event_category'],
        'venue'              => $existing['venue'],
        'event_date'         => $existing['event_date'],
        'start_time'         => $existing['start_time'],
        'end_time'           => $existing['end_time'],
        'participation_role' => $existing['participation_role'],
        'event_status'       => $existing['event_status'],
        'event_hours'        => $existing['event_hours'],
        'merit_points'       => $existing['merit_points'],
        'remarks'            => $existing['remarks']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $value) {
        $formData[$key] = trim($_POST[$key] ?? '');
    }

    $club_id            = (isset($_POST['club_id']) && ctype_digit($_POST['club_id'])) ? (int) $_POST['club_id'] : null;
    $event_title        = $formData['event_title'];
    $organizer          = $formData['organizer'];
    $event_category     = $formData['event_category'];
    $venue              = $formData['venue'];
    $event_date         = $formData['event_date'];
    $start_time         = $formData['start_time'] !== '' ? $formData['start_time'] : null;
    $end_time           = $formData['end_time'] !== '' ? $formData['end_time'] : null;
    $participation_role = $formData['participation_role'];
    $event_status       = $formData['event_status'];
    $event_hours        = is_numeric($formData['event_hours']) ? (float) $formData['event_hours'] : -1;
    $merit_points       = is_numeric($formData['merit_points']) ? (int) $formData['merit_points'] : -1;
    $remarks            = $formData['remarks'];

    if ($event_title === '' || $organizer === '' || $event_date === '') {
        $error = 'Please fill in the event title, organizer, and event date.';
    } elseif (!in_array($event_category, $categories, true)) {
        $error = 'Please choose a valid category.';
    } elseif (!in_array($participation_role, $roles, true)) {
        $error = 'Please choose a valid participation role.';
    } elseif (!in_array($event_status, $statuses, true)) {
        $error = 'Please choose a valid status.';
    } elseif ($event_hours < 0) {
        $error = 'Event hours cannot be negative.';
    } elseif ($merit_points < 0) {
        $error = 'Merit points cannot be negative.';
    }

    if ($error === '') {
        if ($isEdit) {
            $sql = 'UPDATE events SET club_id = ?, event_title = ?, organizer = ?, event_category = ?, venue = ?, event_date = ?, start_time = ?, end_time = ?, participation_role = ?, event_status = ?, event_hours = ?, merit_points = ?, remarks = ? WHERE id = ? AND user_id = ?';
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                'isssssssssdisii',
                $club_id,
                $event_title,
                $organizer,
                $event_category,
                $venue,
                $event_date,
                $start_time,
                $end_time,
                $participation_role,
                $event_status,
                $event_hours,
                $merit_points,
                $remarks,
                $event_id,
                $user_id
            );
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($success) {
                // Auto-sync merit if status is Completed
                if ($event_status === 'Completed' && $event_hours > 0) {
                    $checkSql = 'SELECT merit_id FROM merits WHERE event_id = ? AND user_id = ? LIMIT 1';
                    $checkStmt = mysqli_prepare($conn, $checkSql);
                    mysqli_stmt_bind_param($checkStmt, 'ii', $event_id, $user_id);
                    mysqli_stmt_execute($checkStmt);
                    mysqli_stmt_store_result($checkStmt);
                    $alreadyExists = mysqli_stmt_num_rows($checkStmt) > 0;
                    mysqli_stmt_close($checkStmt);

                    if (!$alreadyExists) {
                        $meritSql = 'INSERT INTO merits (user_id, event_id, activity_title, activity_type, start_date, end_date, hours_contributed, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                        $meritStmt = mysqli_prepare($conn, $meritSql);
                        $meritDesc = 'Auto-generated from Event Tracker';
                        $meritStatus = 'Pending';
                        mysqli_stmt_bind_param(
                            $meritStmt,
                            'iissssdss',
                            $user_id,
                            $event_id,
                            $event_title,
                            $event_category,
                            $event_date,
                            $event_date,
                            $event_hours,
                            $meritDesc,
                            $meritStatus
                        );
                        mysqli_stmt_execute($meritStmt);
                        mysqli_stmt_close($meritStmt);
                    }
                }
                header('Location: events.php?msg=updated');
                exit();
            }
        } else {
            $sql = 'INSERT INTO events (user_id, club_id, event_title, organizer, event_category, venue, event_date, start_time, end_time, participation_role, event_status, event_hours, merit_points, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                'iisssssssssdis',
                $user_id,
                $club_id,
                $event_title,
                $organizer,
                $event_category,
                $venue,
                $event_date,
                $start_time,
                $end_time,
                $participation_role,
                $event_status,
                $event_hours,
                $merit_points,
                $remarks
            );
            $success = mysqli_stmt_execute($stmt);

            if ($success) {
                $new_event_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Auto-sync merit if status is Completed on new insert
                if ($event_status === 'Completed' && $event_hours > 0) {
                    $meritSql = 'INSERT INTO merits (user_id, event_id, activity_title, activity_type, start_date, end_date, hours_contributed, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    $meritStmt = mysqli_prepare($conn, $meritSql);
                    $meritDesc = 'Auto-generated from Event Tracker';
                    $meritStatus = 'Pending';
                    mysqli_stmt_bind_param(
                        $meritStmt,
                        'iissssdss',
                        $user_id,
                        $new_event_id,
                        $event_title,
                        $event_category,
                        $event_date,
                        $event_date,
                        $event_hours,
                        $meritDesc,
                        $meritStatus
                    );
                    mysqli_stmt_execute($meritStmt);
                    mysqli_stmt_close($meritStmt);
                }

                header('Location: events.php?msg=added');
                exit();
            }
            mysqli_stmt_close($stmt);
        }

        $error = 'Unable to save the event right now. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit Event' : 'Add Event'; ?> | CCMS</title>
    <link rel="stylesheet" href="../../style.css">
</head>

<body class="main-body">
    <div class="sidebar">
        <div>
            <h2>CCMS</h2>
            <p class="sidebar-subtitle">Student Portal</p>
        </div>

        <div class="nav-links">
            <a href="../../dashboard.php">📊 Dashboard</a>
            <a href="events.php" class="active">📅 Event Tracker</a>
            <a href="../club_tracker/clubs.php">👥 Club Tracker</a>
            <a href="../merit_tracker/merit.php">⏱️ Merit Tracker</a>
            <a href="../achievement_tracker/achievements.php">🏆 Achievements</a>
        </div>

        <a href="../../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner hero-banner-split">
            <div>
                <p class="hero-label">Event Tracker</p>
                <h1><?php echo $isEdit ? 'Edit Event Record' : 'Create New Event'; ?></h1>
                <p class="hero-text">Keep your student programme details neat, professional, and easy to review later.</p>
            </div>
            <div class="hero-actions">
                <a href="events.php" class="btn-secondary">← Back to Events</a>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="panel form-panel">
            <div class="panel-header panel-header-stack">
                <div>
                    <h2 style="color: var(--dark);">Event Details</h2>
                    <p class="muted-line">Logged in as <?php echo htmlspecialchars($username); ?>. Fill in the details below and save the record.</p>
                </div>
            </div>

            <form method="POST" class="event-form-grid">

                <div class="input-group">
                    <label for="event_title">Event Title</label>
                    <input type="text" id="event_title" name="event_title" maxlength="150" required
                        value="<?php echo htmlspecialchars($formData['event_title']); ?>"
                        placeholder="Example: UTAR Leadership Workshop">
                </div>

                <div class="input-group">
                    <label for="organizer">Organizer</label>
                    <input type="text" id="organizer" name="organizer" maxlength="150" required
                        value="<?php echo htmlspecialchars($formData['organizer']); ?>"
                        placeholder="Example: Faculty of ICT / Student Affairs">
                </div>

                <div class="input-group">
                    <label for="event_category">Category</label>
                    <select id="event_category" name="event_category" required>
                        <?php foreach ($categories as $item): ?>
                            <option value="<?php echo htmlspecialchars($item); ?>"
                                <?php echo $formData['event_category'] === $item ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label for="participation_role">Participation Role</label>
                    <select id="participation_role" name="participation_role" required>
                        <?php foreach ($roles as $item): ?>
                            <option value="<?php echo htmlspecialchars($item); ?>"
                                <?php echo $formData['participation_role'] === $item ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label for="club_id">Related Club <span class="muted-line">(Optional)</span></label>
                    <select id="club_id" name="club_id">
                        <option value="">-- None / Not club-related --</option>
                        <?php foreach ($clubsList as $club): ?>
                            <option value="<?php echo (int) $club['club_id']; ?>"
                                <?php echo (string) $formData['club_id'] === (string) $club['club_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($club['club_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($clubsList)): ?>
                        <p class="muted-line" style="margin-top: 4px; font-size: 0.8rem;">No clubs found. <a href="../club_tracker/clubs.php">Add a club first</a> to link it here.</p>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label for="event_date">Event Date</label>
                    <input type="date" id="event_date" name="event_date" required
                        value="<?php echo htmlspecialchars($formData['event_date']); ?>">
                </div>

                <div class="input-group">
                    <label for="venue">Venue</label>
                    <input type="text" id="venue" name="venue" maxlength="150"
                        value="<?php echo htmlspecialchars($formData['venue']); ?>"
                        placeholder="Example: UTAR Kampar / Online via Zoom">
                </div>

                <div class="input-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" id="start_time" name="start_time"
                        value="<?php echo htmlspecialchars($formData['start_time']); ?>">
                </div>

                <div class="input-group">
                    <label for="end_time">End Time</label>
                    <input type="time" id="end_time" name="end_time"
                        value="<?php echo htmlspecialchars($formData['end_time']); ?>">
                </div>

                <div class="input-group">
                    <label for="event_status">Status</label>
                    <select id="event_status" name="event_status" required>
                        <?php foreach ($statuses as $item): ?>
                            <option value="<?php echo htmlspecialchars($item); ?>"
                                <?php echo $formData['event_status'] === $item ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label for="event_hours">Event Hours</label>
                    <input type="number" id="event_hours" name="event_hours" min="0" step="0.5"
                        value="<?php echo htmlspecialchars($formData['event_hours']); ?>" placeholder="0">
                </div>

                <div class="input-group">
                    <label for="merit_points">Merit Points</label>
                    <input type="number" id="merit_points" name="merit_points" min="0" step="1"
                        value="<?php echo htmlspecialchars($formData['merit_points']); ?>" placeholder="0">
                </div>

                <div class="input-group full-span">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="5"
                        placeholder="Optional notes: certificate received, key tasks, competition result, or lecturer remarks."><?php echo htmlspecialchars($formData['remarks']); ?></textarea>
                </div>

                <div class="input-group full-span" style="background: var(--light, #f8f9fa); border-radius: 8px; padding: 12px 16px; font-size: 0.85rem; color: #666;">
                    💡 <strong>Note:</strong> If you set the status to <strong>Completed</strong> and enter event hours, a merit record will be <strong>automatically created</strong> in the Merit Tracker. You do not need to add it manually.
                </div>

                <div class="form-actions full-span">
                    <a href="events.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary"><?php echo $isEdit ? 'Save Changes' : 'Save Event'; ?></button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>