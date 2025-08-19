<?php

const BASE_DIR = __DIR__ . '/../';

require_once BASE_DIR . 'vendor/autoload.php';

use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

$twilioConfig = require_once BASE_DIR . 'config/twilio.php';

$sid = $twilioConfig['sid'];
$token = $twilioConfig['token'];
$from = $twilioConfig['from'];
$messagingServiceSid = $twilioConfig['messagingServiceSid'];

$student_first_name = $_SESSION['student_first_name'];
// $student_first_name = 'John Wick';
$student_phone = $_SESSION['student_phone'];
$class_id = $_SESSION['class_id'];
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

$twilio = new Client($sid, $token);

try {
    $message = $twilio->messages->create(
        $student_phone,
        array(
            // 'from' => $from,
            'messagingServiceSid' => $messagingServiceSid,
            'body' => "Hello parent/guardian. {$student_first_name} has been marked present for class ID {$class_id} on {$current_date} at {$current_time}. This is an automated notification. Do not reply to this message."
        )
    );
    echo json_encode([
        'success' => true,
        'message' => 'Notification sent successfully.'
    ]);
} catch (TwilioException $e) {
    // error_log("Twilio error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send notification.'
    ]);
}

// $phone_number = $twilio->lookups->v2->phoneNumbers($student_phone)->fetch();

// var_dump($phone_number);
// exit();

unset($_SESSION['student_first_name'], $_SESSION['student_phone'], $_SESSION['class_id']);
