<?php
require '../config.php';
session_start();
if (empty($_SESSION['admin_user_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Invalid student ID.');
}

$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) {
    exit('Student not found.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $post = trim($_POST['post'] ?? 'Student');
    $matricNo = strtoupper(trim($_POST['matric_no'] ?? ''));

    if ($fullName === '' || strlen($fullName) < 3) {
        $error = 'Enter a valid full name.';
    } elseif (!in_array($level, ['100', '200', '300', '400'], true)) {
        $error = 'Select a valid level.';
    } elseif ($post === '') {
        $error = 'Post cannot be empty.';
    } elseif ($matricNo === '' || !preg_match('/^[A-Z0-9\/-]+$/', $matricNo)) {
        $error = 'Enter a valid matric number.';
    }

    $newImagePath = $student['image_path'];
    $newImageFullPath = null;

    if ($error === '' && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Image upload failed.';
        } elseif ((int) $_FILES['image']['size'] > 2 * 1024 * 1024) {
            $error = 'Image too large. Max size is 2MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['image']['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($allowed[$mime])) {
                $error = 'Invalid image format. Use JPG, PNG, or WEBP.';
            } else {
                $uploadDir = dirname(__DIR__) . '/uploads/students';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = preg_replace('/[^A-Z0-9]/', '', $matricNo) . '_' . time() . '.' . $allowed[$mime];
                $newImageFullPath = $uploadDir . '/' . $filename;
                $newImagePath = 'uploads/students/' . $filename;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $newImageFullPath)) {
                    $error = 'Failed to save new image.';
                }
            }
        }
    }

    if ($error === '') {
        try {
            $update = $pdo->prepare('UPDATE students SET full_name = ?, level = ?, post = ?, matric_no = ?, image_path = ? WHERE id = ?');
            $update->execute([$fullName, $level, $post, $matricNo, $newImagePath, $id]);

            if ($newImagePath !== $student['image_path']) {
                $oldPath = dirname(__DIR__) . '/' . ltrim($student['image_path'], '/');
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            header('Location: dashboard.php?msg=' . urlencode('Student updated successfully.'));
            exit;
        } catch (PDOException $e) {
            if ($newImageFullPath && file_exists($newImageFullPath)) {
                unlink($newImageFullPath);
            }
            $error = $e->getCode() === '23000'
                ? 'Matric number already exists.'
                : 'Failed to update student details.';
        }
    }

    $student['full_name'] = $fullName;
    $student['level'] = $level;
    $student['post'] = $post;
    $student['matric_no'] = $matricNo;
    $student['image_path'] = $newImagePath;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Student</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <main class="container">
        <section class="card">
            <h1>Edit Student</h1>
            <?php if ($error !== ''): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">

                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required value="<?= e($student['full_name']) ?>">

                <label for="level">Level</label>
                <select id="level" name="level" required>
                    <option value="100" <?= $student['level'] === '100' ? 'selected' : '' ?>>100</option>
                    <option value="200" <?= $student['level'] === '200' ? 'selected' : '' ?>>200</option>
                    <option value="300" <?= $student['level'] === '300' ? 'selected' : '' ?>>300</option>
                    <option value="400" <?= $student['level'] === '400' ? 'selected' : '' ?>>400</option>
                </select>

                <label for="post">Post</label>
                <input type="text" id="post" name="post" required value="<?= e($student['post']) ?>">

                <label for="matric_no">Matric Number</label>
                <input type="text" id="matric_no" name="matric_no" required value="<?= e($student['matric_no']) ?>">

                <label for="image">Replace Photo (optional)</label>
                <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <p class="warning">Allowed image types: JPG, JPEG, PNG, WEBP. Max size: 2MB.</p>

                <img src="../<?= e($student['image_path']) ?>" class="preview always" alt="Current image">

                <button type="submit">Save Changes</button>
            </form>
            <p class="admin-link"><a class="link-btn secondary" href="dashboard.php">Back to Dashboard</a></p>
        </section>
    </main>
</body>
</html>

