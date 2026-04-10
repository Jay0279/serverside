<?php
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

function bindQueryParams($stmt, $types, $params)
{
    if (!empty($types) && !empty($params)) {
        $bind_names[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind_names));
    }
}

function fetchSingleValue($conn, $sql, $types, $params, $field)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    bindQueryParams($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row[$field] ?? 0;
}

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

$categories = ['Seminar', 'Workshop', 'Competition', 'Talk', 'Volunteer', 'Sports', 'Club Activity', 'Other'];
$statuses = ['Upcoming', 'Completed', 'Missed', 'Cancelled'];

$total_events = (int) fetchSingleValue(
    $conn,
    'SELECT COUNT(*) AS total FROM events WHERE user_id = ?',
    'i',
    [$user_id],
    'total'
);

$completed_events = (int) fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total FROM events WHERE user_id = ? AND event_status = 'Completed'",
    'i',
    [$user_id],
    'total'
);

$upcoming_events = (int) fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total FROM events WHERE user_id = ? AND event_status = 'Upcoming'",
    'i',
    [$user_id],
    'total'
);

$total_hours = (float) fetchSingleValue(
    $conn,
    'SELECT COALESCE(SUM(event_hours), 0) AS total_hours FROM events WHERE user_id = ?',
    'i',
    [$user_id],
    'total_hours'
);

$where = ['user_id = ?'];
$params = [$user_id];
$types = 'i';

if ($q !== '') {
    $where[] = '(event_title LIKE ? OR organizer LIKE ? OR venue LIKE ? OR remarks LIKE ?)';
    $searchValue = '%' . $q . '%';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $types .= 'ssss';
}

if ($status !== '' && in_array($status, $statuses, true)) {
    $where[] = 'event_status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($category !== '' && in_array($category, $categories, true)) {
    $where[] = 'event_category = ?';
    $params[] = $category;
    $types .= 's';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countSql = "SELECT COUNT(*) AS total FROM events $whereSql";
$countStmt = mysqli_prepare($conn, $countSql);
bindQueryParams($countStmt, $types, $params);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
mysqli_stmt_close($countStmt);

$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "SELECT * FROM events $whereSql ORDER BY event_date DESC, start_time DESC LIMIT ? OFFSET ?";
$listStmt = mysqli_prepare($conn, $listSql);
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;
$listTypes = $types . 'ii';
bindQueryParams($listStmt, $listTypes, $listParams);
mysqli_stmt_execute($listStmt);
$eventsResult = mysqli_stmt_get_result($listStmt);

$flashMessage = '';
$flashClass = 'success';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $flashMessage = 'Event record added successfully.';
            break;
        case 'updated':
            $flashMessage = 'Event record updated successfully.';
            break;
        case 'deleted':
            $flashMessage = 'Event record deleted successfully.';
            break;
        case 'error':
            $flashMessage = 'Something went wrong. Please try again.';
            $flashClass = 'error';
            break;
    }
}

function buildPageLink($pageNumber)
{
    $params = $_GET;
    $params['page'] = $pageNumber;
    return 'events.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Tracker | CCMS</title>
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
            <a href="#">👥 Club Tracker</a>
            <a href="#">⏱️ Merit Tracker</a>
            <a href="../achievement_tracker/achievements.php">🏆 Achievements</a>
        </div>

        <a href="../../auth/logout.php" class="logout-link">Log Out</a>
    </div>

    <div class="content">
        <div class="hero-banner hero-banner-split">
            <div>
                <p class="hero-label">Event Tracker</p>
                <h1>Manage your student events, <?php echo htmlspecialchars($username); ?> ✨</h1>
                <p class="hero-text">Record programmes, workshops, talks, competitions, and volunteering activities in one clean place.</p>
            </div>
            <div class="hero-actions">
                <a href="event_form.php" class="btn-primary">+ Add New Event</a>
            </div>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div class="alert <?php echo $flashClass; ?>"><?php echo htmlspecialchars($flashMessage); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-title">Total Events</span>
                <h3><?php echo $total_events; ?></h3>
                <p class="stat-note">All recorded participations</p>
            </div>
            <div class="stat-card green">
                <span class="stat-title">Completed</span>
                <h3><?php echo $completed_events; ?></h3>
                <p class="stat-note">Finished activities</p>
            </div>
            <div class="stat-card orange">
                <span class="stat-title">Upcoming</span>
                <h3><?php echo $upcoming_events; ?></h3>
                <p class="stat-note">Planned participations</p>
            </div>
            <div class="stat-card purple">
                <span class="stat-title">Total Hours</span>
                <h3><?php echo rtrim(rtrim(number_format($total_hours, 1), '0'), '.'); ?></h3>
                <p class="stat-note">Contribution time logged</p>
            </div>
        </div>

        <div class="panel panel-tight">
            <div class="panel-header panel-header-stack">
                <div>
                    <h2 style="color: var(--dark);">Search & Filter</h2>
                    <p class="muted-line">Quickly find a programme by title, organizer, venue, category, or status.</p>
                </div>
            </div>

            <form method="GET" class="filter-grid">
                <div class="input-group compact-group">
                    <label for="q">Search</label>
                    <input type="text" id="q" name="q" placeholder="Search title, organizer, venue..." value="<?php echo htmlspecialchars($q); ?>">
                </div>

                <div class="input-group compact-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $item): ?>
                            <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $category === $item ? 'selected' : ''; ?>><?php echo htmlspecialchars($item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group compact-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $item): ?>
                            <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $status === $item ? 'selected' : ''; ?>><?php echo htmlspecialchars($item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-primary">Apply Filter</button>
                    <a href="events.php" class="btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 style="color: var(--dark);">Your Event Records</h2>
                    <p class="muted-line"><?php echo $totalRows; ?> result<?php echo $totalRows === 1 ? '' : 's'; ?> found</p>
                </div>
                <a href="event_form.php" class="btn-primary">+ Add Event</a>
            </div>

            <?php if ($eventsResult && mysqli_num_rows($eventsResult) > 0): ?>
                <div class="event-grid">
                    <?php while ($event = mysqli_fetch_assoc($eventsResult)): ?>
                        <div class="event-card">
                            <div class="event-card-top">
                                <span class="event-badge category-badge"><?php echo htmlspecialchars($event['event_category']); ?></span>
                                <span class="event-badge status-badge <?php echo strtolower(str_replace(' ', '-', $event['event_status'])); ?>"><?php echo htmlspecialchars($event['event_status']); ?></span>
                            </div>

                            <h3><?php echo htmlspecialchars($event['event_title']); ?></h3>
                            <p class="event-organizer"><?php echo htmlspecialchars($event['organizer']); ?></p>

                            <div class="event-meta-list">
                                <div><strong>Date:</strong> <?php echo date('d M Y', strtotime($event['event_date'])); ?></div>
                                <div><strong>Time:</strong>
                                    <?php
                                    $start = !empty($event['start_time']) ? date('g:i A', strtotime($event['start_time'])) : '-';
                                    $end = !empty($event['end_time']) ? date('g:i A', strtotime($event['end_time'])) : '-';
                                    echo htmlspecialchars($start . ($end !== '-' ? ' - ' . $end : ''));
                                    ?>
                                </div>
                                <div><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue'] ?: 'Not specified'); ?></div>
                                <div><strong>Role:</strong> <?php echo htmlspecialchars($event['participation_role']); ?></div>
                                <div><strong>Hours:</strong> <?php echo htmlspecialchars(rtrim(rtrim(number_format((float) $event['event_hours'], 1), '0'), '.')); ?></div>
                                <div><strong>Merit:</strong> <?php echo (int) $event['merit_points']; ?> pts</div>
                            </div>

                            <?php if (!empty($event['remarks'])): ?>
                                <div class="event-remarks">
                                    <?php echo nl2br(htmlspecialchars($event['remarks'])); ?>
                                </div>
                            <?php endif; ?>

                            <div class="event-card-actions">
                                <a href="event_form.php?id=<?php echo (int) $event['id']; ?>" class="btn-secondary">Edit</a>
                                <form action="delete_event.php" method="POST" onsubmit="return confirm('Delete this event record?');">
                                    <input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination-wrap">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo htmlspecialchars(buildPageLink($page - 1)); ?>" class="page-link">← Prev</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?php echo htmlspecialchars(buildPageLink($i)); ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo htmlspecialchars(buildPageLink($page + 1)); ?>" class="page-link">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📅</div>
                    <h3>No event records yet</h3>
                    <p>Start by adding your first programme, talk, workshop, or competition entry.</p>
                    <a href="event_form.php" class="btn-primary">Create First Event</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
<?php
mysqli_stmt_close($listStmt);
?>

