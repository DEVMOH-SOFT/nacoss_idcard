<?php
require '../config.php';
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// fetch students
$stmt = $pdo->query('SELECT * FROM students ORDER BY date_created DESC');
students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <h1>Students</h1>
    <p><a href="logout.php">Logout</a></p>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Matric</th>
            <th>Department</th>
            <th>Post</th>
            <th>Photo</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($students as $s): ?>
        <tr>
            <td><?=htmlspecialchars($s['id'])?></td>
            <td><?=htmlspecialchars($s['full_name'])?></td>
            <td><?=htmlspecialchars($s['matric_no'])?></td>
            <td><?=htmlspecialchars($s['department'])?></td>
            <td><?=htmlspecialchars($s['post'])?></td>
            <td><img src="../<?=$s['image_path']?>" width="40"></td>
            <td>
                <a href="edit.php?id=<?=$s['id']?>">Edit</a> |
                <a href="delete.php?id=<?=$s['id']?>" onclick="return confirm('Remove record?');">Delete</a> |
                <a href="../generate.php?id=<?=$s['id']?>&type=png" target="_blank">PNG</a> |
                <a href="../generate.php?id=<?=$s['id']?>&type=pdf" target="_blank">PDF</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>