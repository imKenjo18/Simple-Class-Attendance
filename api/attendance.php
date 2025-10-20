<?php

use Twilio\Rest\Client;
/**
 * API endpoint for handling attendance records.
 * Supports marking students present and fetching attendance history.
 * All actions are class-centric and require authentication.
 */

session_start();

date_default_timezone_set('Asia/Manila');

// 1. SECURITY: Ensure a teacher is logged in for all actions.
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// 2. SETUP: Include database configuration and set JSON header.
require_once '../config/database.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$teacher_id = $_SESSION['teacher_id'];

// 3. LOGIC: Use a try-catch block for robust database error handling.
try {
    switch ($action) {

        /**
         * ACTION: mark_present
         * Marks a student as 'Present' via QR code scan for a specific class.
         */
        case 'mark_present':
            // --- INPUT VALIDATION ---
            if (empty($_POST['student_id_num']) || empty($_POST['class_id'])) {
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Missing student ID or class ID.']);
                exit;
            }

            $student_id_num = $_POST['student_id_num'];
            $class_id = $_POST['class_id'];

            // --- GET CURRENT TIME & DAY ---
            $current_date = date('Y-m-d');
            $current_time = date('H:i:s');
            $current_day_of_week = date('l'); // e.g., "Monday"

            // --- FETCH STUDENT AND CLASS DATA ---
            $stmt_student = $pdo->prepare("SELECT id, first_name, last_name, phone FROM students WHERE student_id_num = ?");
            $stmt_student->execute([$student_id_num]);
            $student = $stmt_student->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                http_response_code(404); // Not Found
                echo json_encode(['success' => false, 'message' => 'Student ID not found.']);
                exit;
            }
            $student_pk_id = $student['id'];

            $stmt_class = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
            $stmt_class->execute([$class_id, $teacher_id]);
            $class = $stmt_class->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Class not found or you do not have permission.']);
                exit;
            }

            // --- BUSINESS LOGIC CHECKS ---
            // Require custom schedule for today
            $stmt_sched = $pdo->prepare("SELECT start_time, end_time FROM class_schedules WHERE class_id = ? AND day_of_week = ?");
            $stmt_sched->execute([$class_id, $current_day_of_week]); // $current_day_of_week like 'Monday'
            $custom = $stmt_sched->fetch(PDO::FETCH_ASSOC);

            if (!$custom) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "No schedule set for today ({$current_day_of_week}). Configure custom schedules in the class settings."]);
                exit;
            }

            // Within day and time?
            if ($current_time < $custom['start_time'] || $current_time > $custom['end_time']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Attendance can only be taken during class hours ({$custom['start_time']} - {$custom['end_time']})."]);
                exit;
            }

            // Determine status: Late if 15+ minutes after start_time, else Present
            $start_ts = strtotime($custom['start_time']);
            $current_ts = strtotime($current_time);
            $late_threshold = $start_ts + (15 * 60); // 15 minutes after start
            $status_to_set = ($current_ts >= $late_threshold) ? 'Late' : 'Present';

            // 3. Check if attendance has already been recorded
            $stmt_check = $pdo->prepare("SELECT id, status FROM attendance_records WHERE student_id = ? AND class_id = ? AND attendance_date = ?");
            $stmt_check->execute([$student_pk_id, $class_id, $current_date]);
            $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $existing_status = $existing['status'] ?? 'Present';
                echo json_encode(['success' => true, 'message' => "Student already recorded today as {$existing_status}.", 'student_name' => "{$student['first_name']} {$student['last_name']}", 'status' => $existing_status]);
                exit;
            }

            // --- PERFORM INSERTION ---
            $sql = "INSERT INTO attendance_records (class_id, student_id, attendance_date, attendance_time, status) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $pdo->prepare($sql);
            if ($stmt_insert->execute([$class_id, $student_pk_id, $current_date, $current_time, $status_to_set])) {
                updateStudentStatus($pdo, $student_pk_id);
                $msg = ($status_to_set === 'Late') ? 'Attendance marked: Late.' : 'Attendance marked successfully!';
                echo json_encode(['success' => true, 'message' => $msg, 'student_name' => "{$student['first_name']} {$student['last_name']}", 'status' => $status_to_set]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save attendance record.']);
            }

            $_SESSION['student_first_name'] = $student['first_name'];
            $_SESSION['student_phone'] = $student['phone'] ?? null;
            $_SESSION['class_id'] = $class_id;

            // if (!empty($_SESSION['student_phone'])) {
            //     require_once 'notify.php';
            // }
            break;

        /**
         * ACTION: get_student_attendance_for_class (THIS IS THE MISSING PART)
         * Fetches the full attendance history for one student within one class.
         */
        case 'get_student_attendance_for_class':
             // --- INPUT VALIDATION ---
            if (empty($_GET['class_id']) || empty($_GET['student_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing student or class ID.']);
                exit;
            }
            $class_id = $_GET['class_id'];
            $student_id = $_GET['student_id'];

            // --- SECURITY CHECK ---
            $stmt_check = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
            $stmt_check->execute([$class_id, $teacher_id]);
            if (!$stmt_check->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied to this class data.']);
                exit;
            }

            // --- DATABASE QUERY ---
            $sql = "SELECT attendance_date, attendance_time, status
                    FROM attendance_records
                    WHERE student_id = ? AND class_id = ?
                    ORDER BY attendance_date DESC, attendance_time DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$student_id, $class_id]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'records' => $records]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred on the server.']);
}

/**
 * Updates a student's global status to 'Green'.
 */
function updateStudentStatus($pdo, $student_pk_id) {
    $new_status = 'Green';
    $update_stmt = $pdo->prepare("UPDATE students SET status = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $student_pk_id]);
}
