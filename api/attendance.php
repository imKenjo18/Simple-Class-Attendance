<?php
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
            $stmt_student = $pdo->prepare("SELECT id, first_name, last_name FROM students WHERE student_id_num = ?");
            $stmt_student->execute([$student_id_num]);
            $student = $stmt_student->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                http_response_code(404); // Not Found
                echo json_encode(['success' => false, 'message' => 'Error: Student ID not found.']);
                exit;
            }
            $student_pk_id = $student['id'];

            $stmt_class = $pdo->prepare("SELECT start_time, end_time, day_of_week FROM classes WHERE id = ? AND teacher_id = ?");
            $stmt_class->execute([$class_id, $teacher_id]);
            $class = $stmt_class->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Error: Class not found or you do not have permission.']);
                exit;
            }

            // --- BUSINESS LOGIC CHECKS ---
            
            // 1. Check if the class is scheduled for today (Handles MWF, TTH, S)
            $schedule_map = [
                'MWF' => ['Monday', 'Wednesday', 'Friday'],
                'TTH' => ['Tuesday', 'Thursday'],
                'S'   => ['Saturday']
            ];
            $class_schedule_code = $class['day_of_week'];

            if (!isset($schedule_map[$class_schedule_code]) || !in_array($current_day_of_week, $schedule_map[$class_schedule_code])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "This class is not scheduled for today ({$current_day_of_week}). It runs on {$class_schedule_code}."]);
                exit;
            }

            // 2. Check if the current time is within the class schedule
            if ($current_time < $class['start_time'] || $current_time > $class['end_time']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Attendance can only be taken during class hours ({$class['start_time']} - {$class['end_time']})."]);
                exit;
            }
            
            // 3. Check if attendance has already been recorded
            $stmt_check = $pdo->prepare("SELECT id FROM attendance_records WHERE student_id = ? AND class_id = ? AND attendance_date = ?");
            $stmt_check->execute([$student_pk_id, $class_id, $current_date]);
            if ($stmt_check->fetch()) {
                echo json_encode(['success' => true, 'message' => 'Student already marked present today.', 'student_name' => "{$student['first_name']} {$student['last_name']}", 'status' => 'Present']);
                exit;
            }
            
            // --- PERFORM INSERTION ---
            $sql = "INSERT INTO attendance_records (class_id, student_id, attendance_date, attendance_time, status) VALUES (?, ?, ?, ?, 'Present')";
            $stmt_insert = $pdo->prepare($sql);
            if ($stmt_insert->execute([$class_id, $student_pk_id, $current_date, $current_time])) {
                updateStudentStatus($pdo, $student_pk_id);
                echo json_encode(['success' => true, 'message' => 'Attendance marked successfully!', 'student_name' => "{$student['first_name']} {$student['last_name']}", 'status' => 'Present']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save attendance record.']);
            }
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