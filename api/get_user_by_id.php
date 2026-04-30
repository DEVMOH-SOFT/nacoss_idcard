<?php
header('Content-Type: application/json');
require_once '../config.php';

// Security check: API Key
$provided_key = $_GET['key'] ?? '';
if ($provided_key !== API_KEY) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized access. Invalid API key."
    ]);
    exit;
}

$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing id parameter."
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        // Normalize is_generated to boolean
        $user['is_generated'] = (bool)$user['is_generated'];
        
        // Construct full image URL
        if (!empty($user['image_path'])) {
            $relPath = ltrim($user['image_path'], '/');
            if (strpos($relPath, 'uploads/') === 0) {
                $user['image_url'] = rtrim(BASE_URL, '/') . '/' . $relPath;
            } else {
                $user['image_url'] = rtrim(BASE_URL, '/') . '/uploads/' . $relPath;
            }
        } else {
            $user['image_url'] = null;
        }

        echo json_encode([
            "status" => "success",
            "data" => $user
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "User not found."
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
