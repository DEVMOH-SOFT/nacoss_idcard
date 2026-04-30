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

try {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY id DESC");
    $users = $stmt->fetchAll();

    foreach ($users as &$user) {
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
    }

    echo json_encode([
        "status" => "success",
        "data" => $users
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
