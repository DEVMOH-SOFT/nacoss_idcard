<?php
require 'config.php';

$status = $_GET['status'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student ID Request</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container">
        <section class="card">
            <h1>NACOS ID Card Application Form</h1>
            <p class="muted">Ensure you fill in your accurate details to avoid any issues with your ID card and to avoid delay in processing your ID card.</p>

            <?php if ($status === 'success'): ?>
                <div class="alert success">Your details have been submitted successfully.</div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>

            <form action="process.php" method="post" enctype="multipart/form-data" id="studentForm" novalidate>
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required maxlength="255" placeholder="Surname Firstname Middlename">

                <label for="level">Level</label>
                <select id="level" name="level" required>
                    <option value="">Select level</option>
                    <option value="100">100 Level</option>
                    <option value="200">200 Level</option>
                    <option value="300">300 Level</option>
                    <option value="400">400 Level</option>
                </select>

                <label for="matric_no">Matric Number</label>
                <input type="text" id="matric_no" name="matric_no" required maxlength="100" placeholder="e.g. 230303010052">

                <label for="image">Passport Photograph</label>
                <div class="upload-guide" id="photoInstructionBox">
                    <p class="upload-guide-title">Mandatory Photo Guidelines</p>
                    
                    <div class="photo-guidelines">
                        <div class="guideline-item accepted">
                            <img src="assets/accepted_male.png" alt="Accepted Male" class="guideline-img">
                            <span class="guideline-label">ACCEPTED (M)</span>
                            <p class="guideline-desc">Facing front, clear view.</p>
                        </div>
                        <div class="guideline-item accepted">
                            <img src="assets/accepted_female.png" alt="Accepted Female" class="guideline-img">
                            <span class="guideline-label">ACCEPTED (F)</span>
                            <p class="guideline-desc">Facing front, clear view.</p>
                        </div>
                        <div class="guideline-item unaccepted">
                            <img src="assets/unaccepted_side.png" alt="Side View" class="guideline-img">
                            <span class="guideline-label">REJECTED</span>
                            <p class="guideline-desc">No side view images.</p>
                        </div>
                        <div class="guideline-item unaccepted">
                            <img src="assets/unaccepted_wrong.png" alt="Improper" class="guideline-img">
                            <span class="guideline-label">REJECTED</span>
                            <p class="guideline-desc">No glasses, hats, or blur.</p>
                        </div>
                    </div>

                    <ol>
                        <li>Upload a clear picture of yourself (Passport style).</li>
                        <li><b>No side view images:</b> You must face the camera directly.</li>
                        <li><b>Transparent background is COMPULSORY.</b></li>
                        <li>If your background is not transparent, use <a href="https://remove.bg" target="_blank" rel="noopener noreferrer">remove.bg</a> to remove it first.</li>
                        <li>If you have issues uploading your image, contact your HOC or any of the NACOS Excos.</li>
                    </ol>
                </div>
                <div class="instruction-ack">
                    <input type="checkbox" id="photoInstructionAck" name="photo_instruction_ack" value="1" required>
                    <label for="photoInstructionAck">I have read and understood the photo instructions.</label>
                </div>
                <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required disabled>
                <p class="warning" id="imageWarning">Allowed image types: JPG, JPEG, PNG, WEBP. Max size: 2MB. Must be transparent background.</p>
                <img id="preview" class="preview" alt="Image preview" hidden>

                <button type="submit">Submit Details</button>
            </form>

            <!-- <p class="admin-link"><a class="link-btn secondary" href="admin/login.php">Admin Dashboard</a></p> -->
            <footer class="site-footer">
                Built by <a href="https://tmb.it.com" target="_blank" rel="noopener noreferrer">TMB</a> and <a href="#">DEVMOH</a>
            </footer>
        </section>
    </main>

    <script src="assets/script.js"></script>
</body>
</html>
