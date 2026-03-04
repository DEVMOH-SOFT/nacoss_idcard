<?php
require 'config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// Binary responses (PNG/ZIP) break if warnings/notices are printed.
if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
}

if (empty($_SESSION['admin_user_id'])) {
    http_response_code(403);
    exit('Admin login required.');
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
    if ($action === 'all') {
        return $pdo->query('SELECT * FROM students ORDER BY level, full_name')->fetchAll();
    }

    if ($action === 'level') {
        $level = $_POST['gen_level'] ?? '';
        if (!in_array($level, ['100', '200', '300', '400'], true)) {
            return [];
        }
        $stmt = $pdo->prepare('SELECT * FROM students WHERE level = ? ORDER BY full_name');
        $stmt->execute([$level]);
        return $stmt->fetchAll();
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

    $mime = mime_content_type($path);
    if ($mime === 'image/jpeg') {
        return imagecreatefromjpeg($path);
    }
    if ($mime === 'image/png') {
        return imagecreatefrompng($path);
    }
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return imagecreatefromwebp($path);
    }

    return null;
}

/**
 * Measure text width for a given string, font size, and font path.
 */
function measureTextWidth(string $text, float $size, string $fontPath): int
{
    $box = @imagettfbbox($size, 0, $fontPath, $text);
    if ($box === false) {
        return 0;
    }
    return abs($box[2] - $box[0]);
}

/**
 * Auto-size text down from startSize until it fits within maxWidth.
 * Returns [fontSize, textWidth].
 */
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

/**
 * Resolve font paths. Returns associative array with 'black', 'bold', and 'regular' keys.
 * 'black' is the heaviest weight for the surname (like the reference design).
 */
function resolveFonts(): array
{
    $fonts = ['regular' => null, 'bold' => null, 'black' => null];

    // Black (extra-heavy) font candidates — for surname
    $blackCandidates = [
        __DIR__ . '/assets/ariblk.ttf',
        'C:/Windows/Fonts/ariblk.ttf',
    ];
    foreach ($blackCandidates as $path) {
        if (is_file($path) && @imagettfbbox(20, 0, $path, 'TEST') !== false) {
            $fonts['black'] = $path;
            break;
        }
    }

    // Bold font candidates
    $boldCandidates = [
        __DIR__ . '/assets/arialbd.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        __DIR__ . '/assets/impact.ttf',
        'C:/Windows/Fonts/impact.ttf',
    ];
    foreach ($boldCandidates as $path) {
        if (is_file($path) && @imagettfbbox(20, 0, $path, 'TEST') !== false) {
            $fonts['bold'] = $path;
            break;
        }
    }

    // Regular font candidates
    $regularCandidates = [
        __DIR__ . '/assets/arial.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/calibri.ttf',
        'C:/Windows/Fonts/segoeui.ttf',
    ];
    foreach ($regularCandidates as $path) {
        if (is_file($path) && @imagettfbbox(20, 0, $path, 'TEST') !== false) {
            $fonts['regular'] = $path;
            break;
        }
    }

    // Fallback chain: black -> bold -> regular
    if ($fonts['black'] === null) {
        $fonts['black'] = $fonts['bold'] ?? $fonts['regular'];
    }
    if ($fonts['bold'] === null) {
        $fonts['bold'] = $fonts['black'] ?? $fonts['regular'];
    }
    if ($fonts['regular'] === null) {
        $fonts['regular'] = $fonts['bold'];
    }

    return $fonts;
}

/**
 * Draw text centered horizontally on the card.
 * If $fauxBoldPasses > 1, draws the text multiple times with tiny offsets
 * to simulate an even heavier weight.
 */
function drawCenteredText($card, float $size, int $y, int $color, string $fontPath, string $text, int $fauxBoldPasses = 1): void
{
    $cardWidth = imagesx($card);
    $width = measureTextWidth($text, $size, $fontPath);
    $x = (int) (($cardWidth - $width) / 2);

    if ($fauxBoldPasses <= 1) {
        imagettftext($card, $size, 0, $x, $y, $color, $fontPath, $text);
    } else {
        // Faux-bold: render text at small offsets for extra thickness
        $offsets = [[0, 0], [1, 0], [0, 1], [1, 1]];
        if ($fauxBoldPasses >= 3) {
            $offsets[] = [2, 0];
            $offsets[] = [0, 2];
            $offsets[] = [2, 1];
            $offsets[] = [1, 2];
        }
        foreach ($offsets as [$ox, $oy]) {
            imagettftext($card, $size, 0, $x + $ox, $y + $oy, $color, $fontPath, $text);
        }
    }
}

/**
 * Crop the photo into a circle and overlay it onto the card at the specified position.
 * The photo is placed inside the green circle area of the template.
 */
function overlayCircularPhoto($card, $photo, int $cx, int $cy, int $radius): void
{
    $srcW = imagesx($photo);
    $srcH = imagesy($photo);

    // Crop to square from center
    $square = min($srcW, $srcH);
    $srcX = (int) (($srcW - $square) / 2);
    $srcY = (int) (($srcH - $square) / 2);

    // Diameter of the destination circle
    $diameter = $radius * 2;

    // Create a temporary image for the circular photo
    $temp = imagecreatetruecolor($diameter, $diameter);
    imagealphablending($temp, false);
    imagesavealpha($temp, true);
    $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
    imagefill($temp, 0, 0, $transparent);

    // Remove the previous zoom factor to ensure the image fills the circle (fixing "straight edges")
    // and use a slightly higher crop to avoid cutting the top of the head.
    $targetSize = $diameter;
    $offset = 0;

    // Shift crop area slightly UP (15% of the difference) to capture more headroom if portrait
    if ($srcH > $srcW) {
        $srcY = (int) max(0, $srcY - ($srcH - $srcW) * 0.15);
    }

    // Resample the photo onto the temp image
    imagecopyresampled($temp, $photo, $offset, $offset, $srcX, $srcY, $targetSize, $targetSize, $square, $square);

    // Now apply circular mask: make pixels outside the circle transparent
    imagealphablending($temp, false);
    for ($py = 0; $py < $diameter; $py++) {
        for ($px = 0; $px < $diameter; $px++) {
            $dx = $px - $radius;
            $dy = $py - $radius;
            $dist = sqrt($dx * $dx + $dy * $dy);
            if ($dist > $radius) {
                imagesetpixel($temp, $px, $py, $transparent);
            } elseif ($dist > $radius - 1.5) {
                // Anti-alias the edge
                $alpha = (int) (($dist - ($radius - 1.5)) / 1.5 * 127);
                $alpha = min(127, max(0, $alpha));
                $rgb = imagecolorat($temp, $px, $py);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $newColor = imagecolorallocatealpha($temp, $r, $g, $b, $alpha);
                imagesetpixel($temp, $px, $py, $newColor);
            }
        }
    }

    // Composite the circular photo onto the card
    $dstX = $cx - $radius;
    $dstY = $cy - $radius;
    imagealphablending($card, true);
    imagecopy($card, $temp, $dstX, $dstY, 0, 0, $diameter, $diameter);
    imagedestroy($temp);
}

/**
 * Render an ID card for a student matching the reference design.
 *
 * Layout (on 890×1422 canvas using Id_card_design.png template):
 * ─ Circular photo placed inside the green circle area
 * ─ Student surname (large, bold, dark green, centered)
 * ─ Student first+middle names (slightly smaller, bold, dark green, centered)
 * ─ Green separator line (already on template at ~y=1055)
 * ─ "MATRIC: <value>" and "POST: <value>" centered, bold, dark green
 *
 * Uses Id_card_design.png as the template (blank design with labels).
 */
function renderCard(array $student, string $outputPath): bool
{
    $templatePath = __DIR__ . '/assets/Id_card_design.png';
    if (!is_file($templatePath)) {
        return false;
    }

    $fonts = resolveFonts();
    if ($fonts['bold'] === null) {
        return false;
    }

    $card = @imagecreatefrompng($templatePath);
    if (!$card) {
        return false;
    }

    imagealphablending($card, true);
    imagesavealpha($card, true);

    $cardWidth = imagesx($card);
    // $cardHeight = imagesy($card); // 1422

    // ─────────────────────────────────────────────
    // 1. Place circular photo inside the green circle
    // ─────────────────────────────────────────────
    $photoPath = __DIR__ . '/' . ltrim($student['image_path'], '/');
    $photo = openImageResource($photoPath);
    if (!$photo) {
        imagedestroy($card);
        return false;
    }

    // Circle geometry (from template analysis):
    // Green fill: x=208→686, y=356→870
    // Center: x≈447, y≈613  Radius≈230
    // Circle geometry (Increased size and shifted down slightly):
    $circleCX = 457;
    $circleCY = 625; // moved down from 615
    $circleRadius = 248; // increased from 240 for a slightly larger fit

    overlayCircularPhoto($card, $photo, $circleCX, $circleCY, $circleRadius);
    imagedestroy($photo);

    // ─────────────────────────────────────────────
    // 2. Prepare text data
    // ─────────────────────────────────────────────
    $darkGreen = imagecolorallocate($card, 6, 96, 0); // #066000

    $fullName = strtoupper(trim((string) $student['full_name']));
    $matric = strtoupper(trim((string) $student['matric_no']));
    $post = trim((string) $student['post']);
    $level = trim((string) $student['level']);

    // Split full name: first word = surname, rest = other names
    $nameParts = preg_split('/\s+/', $fullName, 2);
    $surname = $nameParts[0] ?? '';
    $otherNames = $nameParts[1] ?? '';

    $blackFont = $fonts['black'];  // heaviest weight for surname
    $boldFont = $fonts['bold'];
    $regularFont = $fonts['regular'];
    $maxTextWidth = 720; // max horizontal space for text

    // ─────────────────────────────────────────────
    // 3. Draw student name (centered, below circle)
    // ─────────────────────────────────────────────
    // Reference measurements:
    //   Circle bottom: y≈898, white gap then surname at y=913→980 (67px tall)
    //   Other names: y=994→1029 (35px tall)
    //   Separator line: y=1055
    //
    // On the design template the green circle fill ends at y≈870,
    // with the circle border/anti-alias ending at y≈898.
    // Surname baseline at y≈975, other names baseline at y≈1025.

    // First, cover the existing "MATRIC:" and "POST:" labels with white
    // so we can redraw them centered with values
    $white = imagecolorallocate($card, 255, 255, 255);
    imagefilledrectangle($card, 200, 1065, 600, 1165, $white);

    // Draw surname — extra bold (Arial Black + faux-bold passes)
    [$surnameSize] = fitText($surname, $blackFont, $maxTextWidth, 65, 35);
    drawCenteredText($card, $surnameSize, 975, $darkGreen, $blackFont, $surname, 2);

    // Draw other names — bold but lighter weight than surname
    if ($otherNames !== '') {
        [$otherSize] = fitText($otherNames, $boldFont, $maxTextWidth, 42, 22);
        drawCenteredText($card, $otherSize, 1030, $darkGreen, $boldFont, $otherNames);
    }

    // ─────────────────────────────────────────────
    // 4. Draw MATRIC and POST lines (centered with values)
    // ─────────────────────────────────────────────
    // The separator line is at ~y=1055 on the template (already drawn)
    // MATRIC goes below separator, POST below that

    // Build combined strings like the reference
    $matricLine = "MATRIC: " . $matric;
    $postLine = "POST: " . $post . ", " . $level . "Lvl";

    [$matricSize] = fitText($matricLine, $boldFont, $maxTextWidth, 34, 18);
    drawCenteredText($card, $matricSize, 1115, $darkGreen, $boldFont, $matricLine);

    [$postSize] = fitText($postLine, $boldFont, $maxTextWidth, 32, 18);
    drawCenteredText($card, $postSize, 1160, $darkGreen, $boldFont, $postLine);

    // ─────────────────────────────────────────────
    // 5. Save output
    // ─────────────────────────────────────────────
    $ok = imagepng($card, $outputPath);
    imagedestroy($card);
    return $ok;
}

function streamDownload(string $path, string $contentType, string $filename): void
{
    if (!is_file($path)) {
        http_response_code(500);
        exit('Generated file not found.');
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');

    readfile($path);
    exit;
}

$students = loadStudents($pdo);
if ($students === []) {
    http_response_code(400);
    exit('No students found for this generation request.');
}

$tmpFiles = [];
foreach ($students as $student) {
    $tempPath = sys_get_temp_dir() . '/id_card_' . $student['id'] . '_' . uniqid('', true) . '.png';
    if (renderCard($student, $tempPath)) {
        $tmpFiles[] = [
            'path' => $tempPath,
            'name' => preg_replace('/[^A-Za-z0-9_-]/', '_', $student['matric_no']) . '.png',
        ];
    }
}

if ($tmpFiles === []) {
    http_response_code(500);
    exit('Could not generate cards. Check student images and template files.');
}

if (count($tmpFiles) === 1) {
    $file = $tmpFiles[0];
    register_shutdown_function(static function () use ($file): void {
        if (file_exists($file['path'])) {
            unlink($file['path']);
        }
    });
    streamDownload($file['path'], 'image/png', $file['name']);
}

$zipPath = sys_get_temp_dir() . '/id_cards_' . time() . '_' . uniqid('', true) . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    foreach ($tmpFiles as $tmp) {
        unlink($tmp['path']);
    }
    http_response_code(500);
    exit('Failed to create ZIP file.');
}

foreach ($tmpFiles as $tmp) {
    $zip->addFile($tmp['path'], $tmp['name']);
}
$zip->close();

register_shutdown_function(static function () use ($tmpFiles, $zipPath): void {
    foreach ($tmpFiles as $tmp) {
        if (file_exists($tmp['path'])) {
            unlink($tmp['path']);
        }
    }
    if (file_exists($zipPath)) {
        unlink($zipPath);
    }
});
streamDownload($zipPath, 'application/zip', 'id_cards_' . date('Ymd_His') . '.zip');
