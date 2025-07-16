<?php
require '../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Get student ID from URL, e.g., ?id=ST-12345
$studentId = $_GET['id'] ?? 'invalid_id';

$qrCode = QrCode::create($studentId)
    ->setSize(300)
    ->setMargin(10);

$writer = new PngWriter();
$result = $writer->write($qrCode);

// Output the image directly to the browser
header('Content-Type: '.$result->getMimeType());
echo $result->getString();
?>