<?php
// This script now requires Composer's autoloader, the database, and the QR Code library.
require '../vendor/autoload.php';
require_once '../config/database.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Font\NotoSans;

// --- CONFIGURATION ---
$font_path = '../assets/fonts/ProductSans-Regular.ttf'; // We'll use this as a fallback.

// --- INPUT & DATABASE ---
$student_id_num = $_GET['id'] ?? 'invalid_id';
$student_name = 'Student Not Found';
$is_download = isset($_GET['download']);

// Fetch the student's name from the database
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE student_id_num = ?");
    $stmt->execute([$student_id_num]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $student_name = "{$student['first_name']} {$student['last_name']}";
    }
} catch (PDOException $e) {
    // If the database fails, we can still generate a QR, but with a default name.
    error_log("Database error in generate_qr.php: " . $e->getMessage());
}

// --- QR CODE GENERATION (Using the Library's Built-in Labeling) ---
// This method is more robust and handles UTF-8 characters better than the GD method.

try {
    // Create the QR code object
    $qrCode = QrCode::create($student_id_num)
        ->setSize(300)
        ->setMargin(15); // Add a nice margin around the QR code

    // Create the label (the student's name)
    $label = Label::create($student_name)
        ->setFont(new NotoSans(18)); // Use a built-in font for better compatibility

    // Generate the QR code as a PngWriter result object
    $writer = new PngWriter();
    $result = $writer->write($qrCode, null, $label);

    // --- OUTPUT THE FINAL IMAGE ---
    // Check if a download was requested
    if ($is_download) {
        $safe_filename = "QR_" . preg_replace('/[^a-zA-Z0-9_-]/', '', $student_name) . ".png";
        header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
    }

    // Send the correct content type header and output the image string
    header('Content-Type: ' . $result->getMimeType());
    echo $result->getString();

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