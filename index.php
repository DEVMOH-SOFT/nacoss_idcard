<?php
require 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Generator</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <h1 style="text-align: center; text-decoration: underline;">COMPUTER SCIENCE STUDENTS ID CARD GENERATOR</h1>
    <form action="process.php" method="post" enctype="multipart/form-data" id="studentForm">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" name="full_name" id="full_name" required>
        </div>
        <div class="form-group">
            <label for="post">Post</label>
            <input type="text" name="post" id="post" required>
        </div>
        <div class="form-group">
            <label for="matric_no">Matric Number</label>
            <input type="text" name="matric_no" id="matric_no" required>
        </div>
        <div class="form-group">
            <label for="photo">Passport Photo</label>
            <input type="file" name="photo" id="photo" accept="image/png, image/jpeg" required>
        </div>
        <button type="submit">Submit</button>
    </form>
    <div id="preview">
        <!-- ID card preview goes here -->
    </div>
</div>
<script src="assets/script.js"></script>
</body>
</html> 