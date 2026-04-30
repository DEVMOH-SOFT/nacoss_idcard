<?php
require 'config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Optimization: Increase limits for bulk processing
if (function_exists('ini_set')) {
    @ini_set('memory_limit', '1024M');
    @ini_set('display_errors', '0');
}
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

function loadStudents(PDO $pdo): array
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'single') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            return [];
        }

        $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        return $student ? [$student] : [];
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [];
    }

    $action = $_POST['action'] ?? '';
    
    // Support filtering for "Generate All in View"
    if ($action === 'all' || $action === 'level') {
        $level = $_POST['gen_level'] ?? '';
        if ($level !== '' && in_array($level, ['100', '200', '300', '400'], true)) {
            $stmt = $pdo->prepare('SELECT * FROM students WHERE level = ? ORDER BY full_name');
            $stmt->execute([$level]);
            return $stmt->fetchAll();
        }
        return $pdo->query('SELECT * FROM students ORDER BY level, full_name')->fetchAll();
    }

    if ($action === 'selected') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return [];
        }

        $cleanIds = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $cleanIds[] = $id;
            }
        }
        $cleanIds = array_values(array_unique($cleanIds));
        if ($cleanIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id IN ($placeholders) ORDER BY level, full_name");
        $stmt->execute($cleanIds);
        return $stmt->fetchAll();
    }

    return [];
}

function openImageResource(string $path)
{
    if (!file_exists($path)) {
        return null;
    }

    $mime = @mime_content_type($path);
    if ($mime === 'image/jpeg') {
        return @imagecreatefromjpeg($path);
    }
    if ($mime === 'image/png') {
        return @imagecreatefrompng($path);
    }
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($path);
    }

    return null;
}

function measureTextWidth(string $text, float $size, string $fontPath): int
{
    $box = @imagettfbbox($size, 0, $fontPath, $text);
    if ($box === false) {
        return 0;
    }
    return abs($box[2] - $box[0]);
}

function fitText(string $text, string $fontPath, int $maxWidth, int $startSize, int $minSize = 18): array
{
    $size = $startSize;
    while ($size >= $minSize) {
        $width = measureTextWidth($text, $size, $fontPath);
        if ($width > 0 && $width <= $maxWidth) {
            return [$size, $width];
        }
        $size--;
    }
    $width = measureTextWidth($text, $minSize, $fontPath);
    return [$minSize, $width];
}

function resolveFonts(): array
{
    $fonts = ['regular' => null, 'bold' => null, 'black' => null];
    $blackCandidates = [__DIR__ . '/assets/ariblk.ttf', 'C:/Windows/Fonts/ariblk.ttf'];
    foreach ($blackCandidates as $path) {
        if (is_file($path)) { $fonts['black'] = $path; break; }
    }
    $boldCandidates = [__DIR__ . '/assets/arialbd.ttf', 'C:/Windows/Fonts/arialbd.ttf', __DIR__ . '/assets/impact.ttf', 'C:/Windows/Fonts/impact.ttf'];
    foreach ($boldCandidates as $path) {
        if (is_file($path)) { $fonts['bold'] = $path; break; }
    }
    $regularCandidates = [__DIR__ . '/assets/arial.ttf', 'C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/calibri.ttf', 'C:/Windows/Fonts/segoeui.ttf'];
    foreach ($regularCandidates as $path) {
        if (is_file($path)) { $fonts['regular'] = $path; break; }
    }
    if ($fonts['black'] === null) $fonts['black'] = $fonts['bold'] ?? $fonts['regular'];
    if ($fonts['bold'] === null) $fonts['bold'] = $fonts['black'] ?? $fonts['regular'];
    if ($fonts['regular'] === null) $fonts['regular'] = $fonts['bold'];
    return $fonts;
}

function drawCenteredText($card, float $size, int $y, int $color, string $fontPath, string $text, int $fauxBoldPasses = 1): void
{
    $cardWidth = imagesx($card);
    $width = measureTextWidth($text, $size, $fontPath);
    $x = (int) (($cardWidth - $width) / 2);

    if ($fauxBoldPasses <= 1) {
        imagettftext($card, $size, 0, $x, $y, $color, $fontPath, $text);
    } else {
        $offsets = [[0, 0], [1, 0], [0, 1], [1, 1]];
        if ($fauxBoldPasses >= 3) {
            $offsets = array_merge($offsets, [[2, 0], [0, 2], [2, 1], [1, 2]]);
        }
        foreach ($offsets as [$ox, $oy]) {
            imagettftext($card, $size, 0, $x + $ox, $y + $oy, $color, $fontPath, $text);
        }
    }
}

function overlayCircularPhoto($card, $photo, int $cx, int $cy, int $radius): void
{
    $srcW = imagesx($photo);
    $srcH = imagesy($photo);
    $square = min($srcW, $srcH);
    $srcX = (int) (($srcW - $square) / 2);
    $srcY = (int) (($srcH - $square) / 2);
    $diameter = $radius * 2;
    $temp = imagecreatetruecolor($diameter, $diameter);
    imagealphablending($temp, false);
    imagesavealpha($temp, true);
    $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
    imagefill($temp, 0, 0, $transparent);

    if ($srcH > $srcW) {
        $srcY = (int) max(0, $srcY - ($srcH - $srcW) * 0.15);
    }

    imagecopyresampled($temp, $photo, 0, 0, $srcX, $srcY, $diameter, $diameter, $square, $square);
    imagealphablending($temp, false);
    $radiusSq = $radius * $radius;
    for ($py = 0; $py < $diameter; $py++) {
        $dy = $py - $radius;
        $dySq = $dy * $dy;
        for ($px = 0; $px < $diameter; $px++) {
            $dx = $px - $radius;
            $distSq = $dx * $dx + $dySq;
            if ($distSq > $radiusSq) {
                imagesetpixel($temp, $px, $py, $transparent);
            } elseif ($distSq > ($radius - 1.5) * ($radius - 1.5)) {
                $dist = sqrt($distSq);
                $alpha = (int) (($dist - ($radius - 1.5)) / 1.5 * 127);
                $alpha = min(127, max(0, $alpha));
                $rgb = imagecolorat($temp, $px, $py);
                $newColor = imagecolorallocatealpha($temp, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF, $alpha);
                imagesetpixel($temp, $px, $py, $newColor);
            }
        }
    }
    imagealphablending($card, true);
    imagecopy($card, $temp, $cx - $radius, $cy - $radius, 0, 0, $diameter, $diameter);
    imagedestroy($temp);
}

function renderCard(array $student, string $outputPath, $templateBase, array $fonts): bool
{
    if (!$templateBase) return false;
    $card = imagecreatetruecolor(imagesx($templateBase), imagesy($templateBase));
    imagealphablending($card, false);
    imagesavealpha($card, true);
    imagecopy($card, $templateBase, 0, 0, 0, 0, imagesx($templateBase), imagesy($templateBase));
    imagealphablending($card, true);

    $relPath = ltrim($student['image_path'], '/');
    if (strpos($relPath, 'uploads/') === 0) {
        $photoPath = __DIR__ . '/' . $relPath;
    } else {
        $photoPath = __DIR__ . '/uploads/' . $relPath;
    }
    $photo = openImageResource($photoPath);
    if (!$photo) {
        imagedestroy($card);
        return false;
    }

    overlayCircularPhoto($card, $photo, 374, 469, 215);
    imagedestroy($photo);

    $darkGreen = imagecolorallocate($card, 6, 96, 0);
    $white = imagecolorallocate($card, 255, 255, 255);
    imagefilledrectangle($card, 180, 802, 570, 915, $white);

    $fullName = strtoupper(trim((string) $student['full_name']));
    $matric = strtoupper(trim((string) $student['matric_no']));
    $post = trim((string) $student['post']);
    $level = trim((string) $student['level']);

    $nameParts = preg_split('/\s+/', $fullName, 2);
    $surname = $nameParts[0] ?? '';
    $otherNames = $nameParts[1] ?? '';

    [$surnameSize] = fitText($surname, $fonts['black'], 600, 48, 28);
    drawCenteredText($card, $surnameSize, 735, $darkGreen, $fonts['black'], $surname, 3);
    if ($otherNames !== '') {
        [$otherSize] = fitText($otherNames, $fonts['bold'], 600, 31, 16);
        drawCenteredText($card, $otherSize, 775, $darkGreen, $fonts['bold'], $otherNames);
    }

    $matricLine = "MATRIC: " . $matric;
    $postLine = "POST: " . $post . ", " . $level . "Lvl";
    [$mSize] = fitText($matricLine, $fonts['bold'], 600, 26, 16);
    drawCenteredText($card, $mSize, 840, $darkGreen, $fonts['bold'], $matricLine);
    [$pSize] = fitText($postLine, $fonts['bold'], 600, 24, 16);
    drawCenteredText($card, $pSize, 890, $darkGreen, $fonts['bold'], $postLine);

    $ok = imagepng($card, $outputPath);
    imagedestroy($card);
    return $ok;
}

function streamDownload(string $path, string $contentType, string $filename): void
{
    if (!is_file($path)) {
        http_response_code(500); exit('File not found.');
    }
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (empty($_SESSION['admin_user_id'])) {
        http_response_code(403); exit('Admin login required.');
    }
    $students = loadStudents($pdo);
    if ($students === []) {
        http_response_code(400); exit('No students found.');
    }

    $templatePath = __DIR__ . '/assets/Id_card_design.png';
    $templateBase = @imagecreatefrompng($templatePath);
    $fonts = resolveFonts();

    if (!$templateBase || !$fonts['bold']) {
        http_response_code(500); exit('Template or fonts missing.');
    }

    $tmpFiles = [];
    foreach ($students as $student) {
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'id_' . $student['id'] . '_' . uniqid() . '.png';
        if (renderCard($student, $tempPath, $templateBase, $fonts)) {
            $pdo->prepare('UPDATE students SET is_generated = 1 WHERE id = ?')->execute([$student['id']]);
            $tmpFiles[] = ['path' => $tempPath, 'name' => preg_replace('/[^A-Za-z0-9_-]/', '_', $student['matric_no']) . '.png'];
        }
    }
    imagedestroy($templateBase);

    if ($tmpFiles === []) {
        http_response_code(500); exit('Failed to generate any cards.');
    }

    if (count($tmpFiles) === 1) {
        $f = $tmpFiles[0];
        register_shutdown_function(fn() => @unlink($f['path']));
        streamDownload($f['path'], 'image/png', $f['name']);
    }

    $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'id_cards_' . uniqid() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        foreach ($tmpFiles as $t) @unlink($t['path']);
        http_response_code(500); exit('Failed to create ZIP.');
    }
    foreach ($tmpFiles as $t) $zip->addFile($t['path'], $t['name']);
    $zip->close();

    register_shutdown_function(function() use ($tmpFiles, $zipPath) {
        foreach ($tmpFiles as $t) @unlink($t['path']);
        @unlink($zipPath);
    });
    streamDownload($zipPath, 'application/zip', 'id_cards_' . date('Ymd_His') . '.zip');
}

