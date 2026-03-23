<?php
require 'config.php';

header('Content-Type: application/json');

$matricNo = strtoupper(trim($_GET['matric_no'] ?? $_POST['matric_no'] ?? ''));

if ($matricNo === '' || !preg_match('/^[A-Z0-9\/-]+$/', $matricNo)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Enter a valid matric number first.',
    ]);
    exit;
}

$stmt = $pdo->prepare('SELECT id, full_name, level, post, matric_no, image_path FROM students WHERE matric_no = ? LIMIT 1');
$stmt->execute([$matricNo]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode([
        'success' => true,
        'exists' => false,
        'message' => 'Matric number not found. You can continue with a new submission.',
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'exists' => true,
    'message' => 'Matric number already exists.',
    'student' => [
        'id' => (int) $student['id'],
        'full_name' => $student['full_name'],
        'level' => $student['level'],
        'post' => $student['post'],
        'matric_no' => $student['matric_no'],
        'image_path' => $student['image_path'],
    ],
]);
