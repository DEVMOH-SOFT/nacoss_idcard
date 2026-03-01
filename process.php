<?php
require 'config.php';

function sanitize($data) {
    return htmlspecialchars(trim($data));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    // $department = sanitize($_POST['department']);
    $post = sanitize($_POST['post']);
    $matric_no = sanitize($_POST['matric_no']);

    // check duplicate matric
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE matric_no = ?');
    $stmt->execute([$matric_no]);
    if ($stmt->fetchColumn() > 0) {
        die('Matric number already exists');
    }

    // handle photo
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        die('Photo upload failed');
    }

    $file = $_FILES['photo'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!array_key_exists($file['type'], $allowed)) {
        die('Invalid file type');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        die('File too large');
    }

    $ext = $allowed[$file['type']];
    $timestamp = time();
    $newName = $matric_no . '_' . $timestamp . '.' . $ext;
    $destination = 'uploads/students/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        die('Failed to move uploaded file');
    }

    // insert into database
    $stmt = $pdo->prepare('INSERT INTO students (full_name, post, matric_no, image_path) VALUES (?, ?, ?, ?)');
    $stmt->execute([$full_name, $post, $matric_no, $destination]);

    header('Location: admin/dashboard.php');
    exit;
}
