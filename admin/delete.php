<?php
require '../config.php';
session_start();
if (empty($_SESSION['admin_user_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: dashboard.php?msg=' . urlencode('Invalid student ID.'));
    exit;
}

$stmt = $pdo->prepare('SELECT image_path FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();

if ($student) {
    $imagePath = dirname(__DIR__) . '/' . ltrim($student['image_path'], '/');
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }

    $delete = $pdo->prepare('DELETE FROM students WHERE id = ?');
    $delete->execute([$id]);
    header('Location: dashboard.php?msg=' . urlencode('Student deleted.'));
    exit;
}

header('Location: dashboard.php?msg=' . urlencode('Student not found.'));
exit;
