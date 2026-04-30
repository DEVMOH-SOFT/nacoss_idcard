<?php
require '../config.php';
session_start();
if (empty($_SESSION['admin_user_id'])) {
    header('Location: login.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$levelFilter = $_GET['level'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$where = [];
$params = [];

// Statistics Calculation
$totalStudents = $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalGenerated = $pdo->query('SELECT COUNT(*) FROM students WHERE is_generated = 1')->fetchColumn();
$totalPending = $pdo->query('SELECT COUNT(*) FROM students WHERE is_generated = 0')->fetchColumn();

if ($q !== '') {
    // Global search: ignores level/status filters if searching
    $where[] = '(full_name LIKE ? OR matric_no LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
} else {
    // Normal Tabbed Filtering
    if ($levelFilter !== 'all' && in_array($levelFilter, ['100', '200', '300', '400'], true)) {
        $where[] = 'level = ?';
        $params[] = $levelFilter;
    }

    if ($statusFilter === 'generated') {
        $where[] = 'is_generated = 1';
    } elseif ($statusFilter === 'ungenerated') {
        $where[] = 'is_generated = 0';
    }
}

$sql = 'SELECT * FROM students';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY matric_no ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$flash = $_GET['msg'] ?? '';

function buildUrl(array $overrides): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    // If we change level or status, clear the search query to avoid confusion
    if (isset($overrides['level']) || isset($overrides['status'])) {
        unset($params['q']);
    }
    return '?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Student ID System</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <main class="container wide">
        <header class="topbar">
            <h1>Admin Panel</h1>
            <div class="topbar-actions">
                <span class="muted">Logged in as <strong><?= e($_SESSION['admin_username']) ?></strong></span>
                <a class="link-btn danger" href="logout.php">Logout</a>
            </div>
        </header>

        <?php if ($flash !== ''): ?>
            <div class="alert success"><?= e($flash) ?></div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="dashboard-stats">
            <div class="stat-card primary">
                <span class="label">Total Students</span>
                <span class="value"><?= (int)$totalStudents ?></span>
            </div>
            <div class="stat-card success">
                <span class="label">Cards Generated</span>
                <span class="value"><?= (int)$totalGenerated ?></span>
            </div>
            <div class="stat-card warning">
                <span class="label">Pending Generation</span>
                <span class="value"><?= (int)$totalPending ?></span>
            </div>
        </div>

        <div class="admin-layout">
            <aside class="admin-sidebar">
                <section class="card">
                    <h3>Levels</h3>
                    <nav class="nav-group">
                        <a href="<?= buildUrl(['level' => 'all']) ?>" class="nav-link <?= ($levelFilter === 'all' && $q === '') ? 'active' : '' ?>">All Categories</a>
                        <?php foreach(['100', '200', '300', '400'] as $lv): ?>
                            <a href="<?= buildUrl(['level' => $lv]) ?>" class="nav-link <?= ($levelFilter === $lv && $q === '') ? 'active' : '' ?>"><?= $lv ?> Level</a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="admin-link">
                        <a href="../index.php" class="link-btn secondary">Go to Public Form</a>
                    </div>
                </section>
            </aside>

            <section class="admin-content">
                <div class="search-container">
                    <form method="get">
                        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Global Search: Search by name or matric number across all levels...">
                        <button type="submit">Search</button>
                        <?php if($q !== ''): ?>
                            <a href="dashboard.php" class="status-btn" style="display: flex; align-items: center; border-radius: 8px;">Clear Search</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card">
                    <div class="btn-group">
                        <a href="<?= buildUrl(['status' => 'all']) ?>" class="status-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">Show All</a>
                        <a href="<?= buildUrl(['status' => 'generated']) ?>" class="status-btn <?= $statusFilter === 'generated' ? 'active' : '' ?>">Generated</a>
                        <a href="<?= buildUrl(['status' => 'ungenerated']) ?>" class="status-btn <?= $statusFilter === 'ungenerated' ? 'active' : '' ?>">Ungenerated</a>
                    </div>

                    <form method="post" action="../generate.php" id="bulkForm">
                        <div class="bulk-row">
                            <button type="submit" name="action" value="selected" id="generateSelected">Generate Selected</button>
                            <button type="submit" name="action" value="all">Generate All in View</button>
                            <input type="hidden" name="gen_level" value="<?= e($levelFilter !== 'all' ? $levelFilter : '') ?>">
                        </div>
<?php /* Rest of the table remains same... */ ?>


                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="checkAll"></th>
                                        <th>Full Name</th>
                                        <th>Level</th>
                                        <th>Matric No</th>
                                        <th>Status</th>
                                        <th>Image</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($students === []): ?>
                                        <tr>
                                            <td colspan="7" class="muted" style="text-align: center; padding: 2rem;">No students found matching your criteria.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><input type="checkbox" name="ids[]" value="<?= (int) $student['id'] ?>" class="studentCheck"></td>
                                                <td><?= e($student['full_name']) ?></td>
                                                <td><span class="muted"><?= e($student['level']) ?> Lvl</span></td>
                                                <td><strong><?= e($student['matric_no']) ?></strong></td>
                                                <td>
                                                    <?php if ($student['is_generated']): ?>
                                                        <span class="badge success">Generated</span>
                                                    <?php else: ?>
                                                        <span class="badge warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $displayPath = (strpos($student['image_path'], 'uploads/') === 0) ? '../' . $student['image_path'] : '../uploads/' . $student['image_path'];
                                                    ?>
                                                    <img class="thumb" src="<?= e($displayPath) ?>" alt="Student photo">
                                                </td>
                                                <td class="action-links">
                                                    <a class="link-btn" href="../generate.php?action=single&id=<?= (int) $student['id'] ?>">Generate</a>
                                                    <a class="link-btn secondary" href="edit.php?id=<?= (int) $student['id'] ?>">Edit</a>
                                                    <a class="link-btn danger" href="delete.php?id=<?= (int) $student['id'] ?>" onclick="return confirm('Delete this student?');">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <script src="../assets/script.js"></script>
</body>
</html>


