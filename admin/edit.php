<?php
require '../config.php';
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) exit('Missing id');

// fetch record
$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) exit('Not found');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $department = htmlspecialchars(trim($_POST['department']));
    $post = htmlspecialchars(trim($_POST['post']));
    $matric_no = htmlspecialchars(trim($_POST['matric_no']));

    // update matric uniqueness if changed
    if ($matric_no !== $student['matric_no']) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM students WHERE matric_no = ?');
        $check->execute([$matric_no]);
        if ($check->fetchColumn() > 0) {
            $error = 'Matric number already used';
        }
    }

    if (empty($error)) {
        $image_path = $student['image_path'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!array_key_exists($file['type'], $allowed)) {
                $error = 'Invalid file type';
            } elseif ($file['size'] > 5*1024*1024) {
                $error = 'File too large';
            } else {
                $ext = $allowed[$file['type']];
                $newName = $matric_no . '_' . time() . '.' . $ext;
                $dest = '../uploads/students/' . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // delete old
                    if (file_exists('../' . $image_path)) unlink('../' . $image_path);
                    $image_path = 'uploads/students/' . $newName;
                }
            }
        }

        if (empty($error)) {
            $upd = $pdo->prepare('UPDATE students SET full_name=?, department=?, post=?, matric_no=?, image_path=? WHERE id=?');
            $upd->execute([$full_name, $department, $post, $matric_no, $image_path, $id]);
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <h2>Edit Student</h2>
    <?php if (!empty($error)): ?><p style="color:red"><?=htmlspecialchars($error)?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?=htmlspecialchars($student['full_name'])?>" required>
        </div>
        <div class="form-group">
            <label>Department</label>
            <input type="text" name="department" value="<?=htmlspecialchars($student['department'])?>" required>
        </div>
        <div class="form-group">
            <label>Post</label>
            <input type="text" name="post" value="<?=htmlspecialchars($student['post'])?>" required>
        </div>
        <div class="form-group">
            <label>Matric Number</label>
            <input type="text" name="matric_no" value="<?=htmlspecialchars($student['matric_no'])?>" required>
        </div>
        <div class="form-group">
            <label>Photo (leave blank to keep existing)</label>
            <input type="file" name="photo" accept="image/png, image/jpeg">
        </div>
        <button type="submit">Save</button>
    </form>
</div>
</body>
</html>