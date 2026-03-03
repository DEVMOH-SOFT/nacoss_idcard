<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$level = trim($_POST['level'] ?? '');
$matricNo = strtoupper(trim($_POST['matric_no'] ?? ''));
$post = 'Student';

$allowedLevels = ['100', '200', '300', '400'];
if ($fullName === '' || strlen($fullName) < 3) {
    header('Location: index.php?error=' . urlencode('Enter a valid full name.'));
    exit;
}
if (!in_array($level, $allowedLevels, true)) {
    header('Location: index.php?error=' . urlencode('Select a valid level.'));
    exit;
}
if ($matricNo === '' || !preg_match('/^[A-Z0-9\/-]+$/', $matricNo)) {
    header('Location: index.php?error=' . urlencode('Enter a valid matric number.'));
    exit;
}
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Location: index.php?error=' . urlencode('Upload a valid image file.'));
    exit;
}

$file = $_FILES['image'];
$maxSize = 2 * 1024 * 1024;
if ((int) $file['size'] > $maxSize) {
    header('Location: index.php?error=' . urlencode('Image is too large. Maximum size is 2MB.'));
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
if (!isset($allowedMimes[$mime])) {
    header('Location: index.php?error=' . urlencode('Invalid image format. Use JPG, PNG, or WEBP.'));
    exit;
}

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'students';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$ext = $allowedMimes[$mime];
$filename = preg_replace('/[^A-Z0-9]/', '', $matricNo) . '_' . time() . '.' . $ext;
$fullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
$relativePath = 'uploads/students/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    header('Location: index.php?error=' . urlencode('Failed to save uploaded image.'));
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO students (full_name, level, post, matric_no, image_path) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$fullName, $level, $post, $matricNo, $relativePath]);

    header('Location: index.php?status=success');
    exit;
} catch (PDOException $e) {
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    if ($e->getCode() === '23000') {
        header('Location: index.php?error=' . urlencode('Matric number already exists. Please check and use the correct matric number.'));
        exit;
    }

    header('Location: index.php?error=' . urlencode('Failed to submit details.'));
    exit;
}
