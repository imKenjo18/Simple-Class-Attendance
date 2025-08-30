<?php
// This script now requires Composer's autoloader, the database, and the QR Code library.
require '../vendor/autoload.php';
require_once '../config/database.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Label\Font\OpenSans;

// --- CONFIGURATION ---
$font_path = '../assets/fonts/ProductSans-Regular.ttf'; // We'll use this as a fallback.

// --- INPUT & DATABASE ---
$student_id_num = $_GET['id'] ?? '';
// Decode if URL-encoded
if ($student_id_num !== '') {
    $student_id_num = urldecode($student_id_num);
}
$student_name = '';
$is_download = isset($_GET['download']);
// Track if a student record was found (null = unknown due to DB error)
$studentFound = null;

// Guard: empty or whitespace-only input => generate nothing (hide image)
if (trim((string)$student_id_num) === '') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('HTTP/1.1 404 Not Found');
    exit;
}

// Validate characters for Code 128 (printable ASCII 0x20-0x7E). If invalid, return 400 with no image.
if (!preg_match('/^[ -~]+$/', (string)$student_id_num)) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain');
    echo 'Invalid characters for Code 128';
    exit;
}

// Fetch the student's name from the database
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE student_id_num = ?");
    $stmt->execute([$student_id_num]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $student_name = "{$student['first_name']} {$student['last_name']}";
        $studentFound = true;
    } else {
        $studentFound = false;
    }
} catch (PDOException $e) {
    // Database error: log and handle via guard below (no QR generation on DB failure)
    error_log("Database error in generate_qr.php: " . $e->getMessage());
}

// Fail fast on database error (studentFound remains null)
if ($studentFound === null) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('HTTP/1.1 503 Service Unavailable');
    if (function_exists('imagecreatetruecolor')) {
        header('Content-Type: image/png');
        $error_image = imagecreatetruecolor(460, 140);
        $bg = imagecolorallocate($error_image, 255, 255, 255);
        $fg = imagecolorallocate($error_image, 66, 66, 66);
        $ac = imagecolorallocate($error_image, 230, 74, 25);
        imagefill($error_image, 0, 0, $bg);
        imagestring($error_image, 5, 10, 10, 'Service unavailable', $ac);
        imagestring($error_image, 3, 10, 40, 'A database error occurred. Please try again later.', $fg);
        imagepng($error_image);
        imagedestroy($error_image);
    } else {
        header('Content-Type: text/plain');
        echo 'Service unavailable. Database error.';
    }
    exit;
}

// If the student wasn't found, do not generate a QR code
if ($studentFound === false) {
    // Clear any previous output (avoid corrupting image bytes)
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('HTTP/1.1 404 Not Found');
    // No body content so the client can hide the image cleanly
    exit;
}

// --- QR CODE GENERATION (Using the Library's Built-in Labeling) ---
// This method is more robust and handles UTF-8 characters better than the GD method.

try {
    // Create the QR code object with a 15px margin (v6 constructor is immutable)
    $qrCode = new QrCode(
        $student_id_num,
        new Encoding('UTF-8'),
        ErrorCorrectionLevel::Low,
        300,  // size
        15,   // margin
        RoundBlockSizeMode::Margin
    );

    // Optional label with default OpenSans font (requires GD + FreeType)
    $label = null;
    if (extension_loaded('gd') && function_exists('imagettfbbox') && !empty($student_name)) {
        // Increase label font size to 18 using built-in OpenSans
        $label = new Label($student_name, new OpenSans(18));
    }

    // Generate the QR code as a PngWriter result object
    $writer = new PngWriter();
    $result = $writer->write($qrCode, null, $label);

    // --- OUTPUT THE FINAL IMAGE ---
    // Check if a download was requested
    if ($is_download) {
        $safe_filename = "QR_" . preg_replace('/[^a-zA-Z0-9_-]/', '', $student_name) . ".png";
        header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
    }

    // Clear any previous output (BOM/whitespace/notices) to avoid corrupting PNG bytes
    while (ob_get_level() > 0) { ob_end_clean(); }

    // Send the correct content type header and output the image string
    header('Content-Type: ' . $result->getMimeType());
    echo $result->getString();
    exit;

} catch (Exception $e) {
    // If the QR library fails for any reason, generate a placeholder error image.
    header('Content-Type: image/png');
    $error_image = imagecreatetruecolor(300, 100);
    $bg = imagecolorallocate($error_image, 255, 255, 255);
    $fg = imagecolorallocate($error_image, 211, 47, 47); // Red color
    imagefill($error_image, 0, 0, $bg);
    imagestring($error_image, 5, 10, 10, 'Error:', $fg);
    imagestring($error_image, 3, 10, 30, 'Could not generate QR code.', $fg);
    imagestring($error_image, 3, 10, 50, 'Check server logs for details.', $fg);
    imagepng($error_image);
    imagedestroy($error_image);
    // Log the actual error for the developer
    error_log("QR Code Generation Error: " . $e->getMessage());
}
