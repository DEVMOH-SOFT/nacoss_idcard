<?php
require '../config.php';
session_start();
if (empty($_SESSION['admin_user_id'])) {
    header('Location: login.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$levelFilter = $_GET['level'] ?? '';
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(full_name LIKE ? OR matric_no LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if (in_array($levelFilter, ['100', '200', '300', '400'], true)) {
    $where[] = 'level = ?';
    $params[] = $levelFilter;
}

$sql = 'SELECT * FROM students';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY level ASC, full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$flash = $_GET['msg'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <main class="container wide">
        <section class="card">
            <div class="topbar">
                <h1>Admin Dashboard</h1>
                <div class="topbar-actions">
                    <span class="muted">Signed in as <?= e($_SESSION['admin_username']) ?></span>
                    <a class="link-btn danger" href="logout.php">Logout</a>
                </div>
            </div>

            <?php if ($flash !== ''): ?>
                <div class="alert success"><?= e($flash) ?></div>
            <?php endif; ?>

            <form method="get" class="filters">
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by full name or matric number">
                <select name="level">
                    <option value="">All Levels</option>
                    <option value="100" <?= $levelFilter === '100' ? 'selected' : '' ?>>100</option>
                    <option value="200" <?= $levelFilter === '200' ? 'selected' : '' ?>>200</option>
                    <option value="300" <?= $levelFilter === '300' ? 'selected' : '' ?>>300</option>
                    <option value="400" <?= $levelFilter === '400' ? 'selected' : '' ?>>400</option>
                </select>
                <button type="submit">Search</button>
            </form>

            <form method="post" action="../generate.php" id="bulkForm">
                <div class="bulk-row">
                    <button type="submit" name="action" value="selected" id="generateSelected">Generate Selected</button>
                    <button type="submit" name="action" value="all">Generate All</button>
                    <select name="gen_level">
                        <option value="">Level for bulk generate</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="300">300</option>
                        <option value="400">400</option>
                    </select>
                    <button type="submit" name="action" value="level">Generate By Level</button>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="checkAll"></th>
                                <th>Full Name</th>
                                <th>Level</th>
                                <th>Post</th>
                                <th>Matric No</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students === []): ?>
                                <tr>
                                    <td colspan="7" class="muted">No students found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="<?= (int) $student['id'] ?>" class="studentCheck"></td>
                                        <td><?= e($student['full_name']) ?></td>
                                        <td><?= e($student['level']) ?></td>
                                        <td><?= e($student['post']) ?></td>
                                        <td><?= e($student['matric_no']) ?></td>
                                        <td><img class="thumb" src="../<?= e($student['image_path']) ?>" alt="Student photo"></td>
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
        </section>
    </main>

    <script src="../assets/script.js"></script>
</body>
</html>

