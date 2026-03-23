<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$level = trim($_POST['level'] ?? '');
$matricNo = strtoupper(trim($_POST['matric_no'] ?? ''));
$formMode = trim($_POST['form_mode'] ?? '');
$studentId = (int) ($_POST['student_id'] ?? 0);
$post = 'Student';
$allowedLevels = ['100', '200', '300', '400'];
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$maxSize = 2 * 1024 * 1024;

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

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'students';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$imageError = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
if (!in_array($imageError, [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
    header('Location: index.php?error=' . urlencode('Image upload failed. Please try again with a valid file.'));
    exit;
}

$imageUploaded = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
$newImageFullPath = null;

$storeUploadedImage = static function (array $file, string $matricNo) use ($allowedMimes, $maxSize, $uploadDir, &$newImageFullPath): string {
    if ((int) $file['size'] > $maxSize) {
        header('Location: index.php?error=' . urlencode('Image is too large. Maximum size is 2MB.'));
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMimes[$mime])) {
        header('Location: index.php?error=' . urlencode('Invalid image format. Use JPG, PNG, or WEBP.'));
        exit;
    }

    $filename = preg_replace('/[^A-Z0-9]/', '', $matricNo) . '_' . time() . '.' . $allowedMimes[$mime];
    $newImageFullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    $relativePath = 'uploads/students/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $newImageFullPath)) {
        header('Location: index.php?error=' . urlencode('Failed to save uploaded image.'));
        exit;
    }

    return $relativePath;
};

if ($formMode === 'edit') {
    if ($studentId <= 0) {
        header('Location: index.php?error=' . urlencode('Invalid edit request.'));
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student || strtoupper($student['matric_no']) !== $matricNo) {
        header('Location: index.php?error=' . urlencode('Student record not found for this matric number.'));
        exit;
    }

    $imagePath = $student['image_path'];
    if ($imageUploaded) {
        if (($_POST['photo_instruction_ack'] ?? '') !== '1') {
            header('Location: index.php?error=' . urlencode('Please read and acknowledge the photo instructions before uploading a new image.'));
            exit;
        }

        $imagePath = $storeUploadedImage($_FILES['image'], $matricNo);
    }

    try {
        $stmt = $pdo->prepare('UPDATE students SET full_name = ?, level = ?, post = ?, matric_no = ?, image_path = ? WHERE id = ?');
        $stmt->execute([$fullName, $level, $student['post'], $matricNo, $imagePath, $studentId]);

        if ($imageUploaded) {
            $oldPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($student['image_path'], '/\\'));
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        header('Location: index.php?status=success');
        exit;
    } catch (PDOException $e) {
        if ($newImageFullPath && file_exists($newImageFullPath)) {
            unlink($newImageFullPath);
        }

        $message = $e->getCode() === '23000'
            ? 'Matric number already exists. Please check and use the correct matric number.'
            : 'Failed to update details.';
        header('Location: index.php?error=' . urlencode($message));
        exit;
    }
}

if ($formMode !== 'create') {
    header('Location: index.php?error=' . urlencode('Check your matric number before submitting the form.'));
    exit;
}
if (($_POST['photo_instruction_ack'] ?? '') !== '1') {
    header('Location: index.php?error=' . urlencode('Please read and acknowledge the photo instructions before uploading.'));
    exit;
}
if (!$imageUploaded) {
    header('Location: index.php?error=' . urlencode('Upload a valid image file.'));
    exit;
}

$imagePath = $storeUploadedImage($_FILES['image'], $matricNo);

try {
    $stmt = $pdo->prepare('INSERT INTO students (full_name, level, post, matric_no, image_path) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$fullName, $level, $post, $matricNo, $imagePath]);

    header('Location: index.php?status=success');
    exit;
} catch (PDOException $e) {
    if ($newImageFullPath && file_exists($newImageFullPath)) {
        unlink($newImageFullPath);
    }

    if ($e->getCode() === '23000') {
        header('Location: index.php?error=' . urlencode('Matric number already exists. Please check and use the correct matric number.'));
        exit;
    }

    header('Location: index.php?error=' . urlencode('Failed to submit details.'));
    exit;
}
