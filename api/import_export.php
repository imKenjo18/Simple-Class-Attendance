<?php
/**
 * API endpoint for handling CSV data import and export operations.
 * All actions require an authenticated teacher session.
 */

session_start();

// 1. SECURITY: Ensure a teacher is logged in.
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(403);
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    } else {
        echo "Authentication Required.";
    }
    exit;
}

// 2. SETUP: Include database configuration.
require_once '../config/database.php';
$teacher_id = $_SESSION['teacher_id'];

$action = $_REQUEST['action'] ?? '';

// 3. ROUTING: Use a switch to handle the requested action.
switch ($action) {

    case 'export_attendance':
        // --- INPUT VALIDATION ---
        $class_id = $_GET['class_id'] ?? null;
        $start_date = $_GET['start_date'] ?? null;
        $end_date = $_GET['end_date'] ?? null;

        if (!$class_id || !$start_date || !$end_date) {
            http_response_code(400);
            echo "Error: Missing required parameters (class_id, start_date, end_date).";
            exit;
        }

        // --- SECURITY CHECK ---
        $class_stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ? AND teacher_id = ?");
        $class_stmt->execute([$class_id, $teacher_id]);
        $class = $class_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$class) {
            http_response_code(403);
            echo "Error: You do not have permission to access this class report.";
            exit;
        }
        $class_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $class['class_name']);

        // Set HTTP headers for download.
        $filename = "attendance_{$class_name_safe}_{$start_date}_to_{$end_date}.csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $sql = "SELECT s.last_name, s.first_name, s.student_id_num, ar.attendance_date, ar.attendance_time, ar.status 
                FROM attendance_records ar
                JOIN students s ON ar.student_id = s.id
                WHERE ar.class_id = ? AND ar.attendance_date BETWEEN ? AND ?
                ORDER BY ar.attendance_date, s.last_name, s.first_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$class_id, $start_date, $end_date]);

        $output = fopen('php://output', 'w');
        
        // ** FIX 1: Explicitly provide all parameters for fputcsv **
        fputcsv($output, ['LastName', 'FirstName', 'StudentID', 'Date', 'Time', 'Status'], ',', '"', '\\');
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // ** FIX 2: Explicitly provide all parameters for fputcsv **
            fputcsv($output, $row, ',', '"', '\\');
        }
        fclose($output);
        exit;

    case 'import_students':
        header('Content-Type: application/json');
        $class_id = $_POST['class_id'] ?? null;
        if (!$class_id) {
            echo json_encode(['success' => false, 'message' => 'No class was specified for the import.']);
            exit;
        }

        if (!isset($_FILES['student_csv']) || $_FILES['student_csv']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error.']);
            exit;
        }

        $file_path = $_FILES['student_csv']['tmp_name'];
        $pdo->beginTransaction();
        try {
            $handle = fopen($file_path, 'r');
            $row_count = 0;
            $newly_imported_count = 0;
            $enrolled_count = 0;

            $find_student_stmt = $pdo->prepare("SELECT id FROM students WHERE student_id_num = ?");
            $insert_student_stmt = $pdo->prepare("INSERT INTO students (student_id_num, last_name, first_name, phone) VALUES (?, ?, ?, ?)");
            $enroll_stmt = $pdo->prepare("INSERT IGNORE INTO class_enrollment (class_id, student_id) VALUES (?, ?)");

            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_count++;
                if ($row_count == 1) continue;
                if (count($data) < 3 || empty($data[0]) || empty($data[1]) || empty($data[2])) {
                    throw new Exception("Invalid data on row {$row_count}.");
                }
                $student_id_num = trim($data[0]);
                $last_name = trim($data[1]);
                $first_name = trim($data[2]);
                $phone = isset($data[3]) ? trim($data[3]) : null;

                $find_student_stmt->execute([$student_id_num]);
                $student = $find_student_stmt->fetch(PDO::FETCH_ASSOC);
                
                $student_pk_id = $student ? $student['id'] : null;
                if (!$student_pk_id) {
                    $insert_student_stmt->execute([$student_id_num, $last_name, $first_name, $phone]);
                    $student_pk_id = $pdo->lastInsertId();
                    $newly_imported_count++;
                }
                
                $enroll_stmt->execute([$class_id, $student_pk_id]);
                if ($enroll_stmt->rowCount() > 0) {
                    $enrolled_count++;
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Import complete! Added {$newly_imported_count} new students and enrolled {$enrolled_count} into the class."]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Import Failed: ' . $e->getMessage()]);
        } finally {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
        }
        exit;

    default:
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}