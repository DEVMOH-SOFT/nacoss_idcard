<?php
require '../config.php';
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) exit('Missing id');

$stmt = $pdo->prepare('SELECT image_path FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();
if ($student) {
    if (file_exists('../' . $student['image_path'])) unlink('../' . $student['image_path']);
    $del = $pdo->prepare('DELETE FROM students WHERE id = ?');
    $del->execute([$id]);
}
header('Location: dashboard.php');
exit;
