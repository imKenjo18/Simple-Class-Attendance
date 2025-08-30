<?php
// Generate a barcode image (PNG) for a given student ID number using Picqer barcode library
require '../vendor/autoload.php';
require_once '../config/database.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

// --- INPUT & FLAGS ---
$student_id_num = $_GET['id'] ?? '';
$is_download = isset($_GET['download']);

// Guard: empty or whitespace-only input => generate nothing (hide image)
if (trim($student_id_num) === '') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('HTTP/1.1 404 Not Found');
    // No body content
    exit;
}

// Validate characters: allow only printable ASCII supported by Code 128 text usage (space to tilde)
if (!preg_match('/^[ -~]+$/', $student_id_num)) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain');
    echo 'Invalid characters for Code 128';
    exit;
}

// --- LOOKUP STUDENT (for validation and optional filename) ---
$student = null;
try {
    $stmt = $pdo->prepare('SELECT first_name, last_name FROM students WHERE student_id_num = ?');
    $stmt->execute([$student_id_num]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    error_log('Database error in generate_barcode.php: ' . $e->getMessage());
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('HTTP/1.1 503 Service Unavailable');
    if (function_exists('imagecreatetruecolor')) {
        header('Content-Type: image/png');
        $img = imagecreatetruecolor(460, 120);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $fg = imagecolorallocate($img, 66, 66, 66);
        $ac = imagecolorallocate($img, 230, 74, 25);
        imagefill($img, 0, 0, $bg);
        imagestring($img, 5, 10, 10, 'Service unavailable', $ac);
        imagestring($img, 3, 10, 40, 'A database error occurred.', $fg);
        imagepng($img);
        imagedestroy($img);
    } else {
        header('Content-Type: text/plain');
        echo 'Service unavailable. Database error.';
    }
    exit;
}

if (!$student) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('HTTP/1.1 404 Not Found');
    // Intentionally no image body so the UI shows no image
    exit;
}

// --- BARCODE GENERATION ---
try {
    $generator = new BarcodeGeneratorPNG();
    // TYPE_CODE_128 is widely supported and compact for numeric/alphanumeric IDs
    $pngData = $generator->getBarcode($student_id_num, $generator::TYPE_CODE_128, 2, 40);

    // Prepare headers and output
    if ($is_download) {
        $student_name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $student_name);
        $filename = ($safe !== '' ? 'BARCODE_' . $safe : 'BARCODE_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$student_id_num)) . '.png';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: image/png');
    echo $pngData;
    exit;
} catch (Throwable $e) {
    error_log('Barcode generation error: ' . $e->getMessage());
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: image/png');
    $img = imagecreatetruecolor(420, 100);
    $bg = imagecolorallocate($img, 255, 255, 255);
    $fg = imagecolorallocate($img, 211, 47, 47);
    imagefill($img, 0, 0, $bg);
    imagestring($img, 5, 10, 10, 'Error generating barcode', $fg);
    imagestring($img, 3, 10, 40, 'Check server logs for details.', $fg);
    imagepng($img);
    imagedestroy($img);
    exit;
}
