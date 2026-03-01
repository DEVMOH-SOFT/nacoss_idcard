<?php
require '../config.php';
session_start();

// hardcoded credentials (in real system, use a table)
$admin_user = 'admin';
$admin_pass = password_hash('password', PASSWORD_DEFAULT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === $admin_user && password_verify($pass, $admin_pass)) {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <h2>Admin Login</h2>
    <?php if (!empty($error)): ?>
        <p style="color:red;"><?=htmlspecialchars($error)?></p>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>