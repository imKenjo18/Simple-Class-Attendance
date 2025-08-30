<?php
/**
 * API endpoint for handling CSV data import and export operations.
 * This version includes advanced summary report generation.
 */

session_start();

// 1. SECURITY: Ensure a teacher is logged in.
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(403);
    // Respond with JSON for AJAX requests, plain text for direct access.
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    } else {
        echo "Authentication Required.";
    }
    exit;
}

require_once '../config/database.php';
$teacher_id = $_SESSION['teacher_id'];
$action = $_REQUEST['action'] ?? '';

/**
 * Generates the attendance report data matrix. This is the core logic for summary reports.
 * It calculates all scheduled class days, gets all enrolled students, and merges this with
 * existing attendance records, marking any missing records as 'Absent'.
 *
 * @param PDO $pdo The database connection.
 * @param int $class_id The ID of the class.
 * @param string $start_date The start of the date range (Y-m-d).
 * @param string $end_date The end of the date range (Y-m-d).
 * @return array An array containing the report headers (dates) and student data rows.
 */
function generateAttendanceReportData(PDO $pdo, int $class_id, string $start_date, string $end_date): array {
    // 1. Try custom schedule days
    $stmt = $pdo->prepare("SELECT day_of_week FROM class_schedules WHERE class_id = ?");
    $stmt->execute([$class_id]);
    $custom_days = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$custom_days || count($custom_days) === 0) {
        return ['dates' => [], 'students' => []];
    }

    $valid_class_dates = [];
    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))->modify('+1 day')
    );
    foreach ($period as $date) {
        if (in_array($date->format('l'), $custom_days, true)) {
            $valid_class_dates[] = $date->format('Y-m-d');
        }
    }
    if (empty($valid_class_dates)) {
        return ['dates' => [], 'students' => []];
    }

    // 3. Get all students enrolled in the class, sorted alphabetically by last name
    $stmt = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name
        FROM students s
        JOIN class_enrollment ce ON s.id = ce.student_id
        WHERE ce.class_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Get all existing attendance records for this class within the date range
    $stmt = $pdo->prepare("
        SELECT student_id, attendance_date, status
        FROM attendance_records
        WHERE class_id = ? AND attendance_date BETWEEN ? AND ?
    ");
    $stmt->execute([$class_id, $start_date, $end_date]);
    $records_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Re-index records into a map for fast O(1) lookups: [student_id][date] => status
    $records_map = [];
    foreach ($records_raw as $record) {
        $records_map[$record['student_id']][$record['attendance_date']] = $record['status'];
    }

    // 5. Build the final report data by merging students, dates, and records
    $student_report_rows = [];
    $total_class_days = count($valid_class_dates);

    foreach ($students as $student) {
        $present_count = 0;
        $row_data = [];

        foreach ($valid_class_dates as $date) {
            // If a record exists for this student on this date, use its status
            if (isset($records_map[$student['id']][$date])) {
                $status = $records_map[$student['id']][$date];
                if ($status === 'Present') {
                    $present_count++;
                }
            } else {
                // If no record exists, the student is considered 'Absent'
                $status = 'Absent';
            }
            $row_data[$date] = $status;
        }

        $student_report_rows[] = [
            'name' => "{$student['last_name']}, {$student['first_name']}",
            'data' => $row_data,
            'summary' => "{$present_count} / {$total_class_days}"
        ];
    }

    return [
        'dates' => $valid_class_dates,
        'students' => $student_report_rows
    ];
}

// --- API ACTION ROUTING ---
try {
    switch ($action) {
        case 'get_attendance_report':
            header('Content-Type: application/json');
            $class_id = (int)($_GET['class_id'] ?? 0);
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';

            if (!$class_id || !$start_date || !$end_date) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
                exit;
            }

            $report = generateAttendanceReportData($pdo, $class_id, $start_date, $end_date);
            echo json_encode(['success' => true, 'report' => $report]);
            break;

        case 'export_attendance':
            $class_id = (int)($_GET['class_id'] ?? 0);
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';

            if (!$class_id || !$start_date || !$end_date) {
                http_response_code(400); echo "Missing parameters."; exit;
            }

            $report = generateAttendanceReportData($pdo, $class_id, $start_date, $end_date);

            $filename = "Attendance_Report_{$start_date}_to_{$end_date}.csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            $header = array_merge(['Name'], $report['dates'], ['Total Attendance']);
            fputcsv($output, $header, ',', '"', '\\');

            foreach ($report['students'] as $student_row) {
                $csv_row = [$student_row['name']];
                foreach($report['dates'] as $date) {
                    $csv_row[] = $student_row['data'][$date] ?? 'N/A';
                }
                $csv_row[] = $student_row['summary'];
                fputcsv($output, $csv_row, ',', '"', '\\');
            }

            fclose($output);
            exit;

        case 'export_student_history':
            $class_id = $_GET['class_id'] ?? null;
            $student_id = $_GET['student_id'] ?? null;
            if (!$class_id || !$student_id) { http_response_code(400); echo "Missing class ID or student ID."; exit; }

            $class_stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ? AND teacher_id = ?");
            $class_stmt->execute([$class_id, $teacher_id]);
            $class = $class_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$class) { http_response_code(403); echo "Permission denied."; exit; }

            $student_stmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
            $student_stmt->execute([$student_id]);
            $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) { http_response_code(404); echo "Student not found."; exit; }

            $class_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $class['class_name']);
            $student_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $student['first_name'] . '_' . $student['last_name']);
            $filename = "History_{$student_name_safe}_{$class_name_safe}.csv";

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $sql = "SELECT attendance_date, attendance_time, status FROM attendance_records WHERE student_id = ? AND class_id = ? ORDER BY attendance_date DESC, attendance_time DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$student_id, $class_id]);

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Time', 'Status'], ',', '"', '\\');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
                echo json_encode(['success' => false, 'message' => 'File upload error. Please try again.']);
                exit;
            }

            $file_path = $_FILES['student_csv']['tmp_name'];

            // Use a transaction to ensure the import is atomic (all or nothing)
            $pdo->beginTransaction();
            try {
                $handle = fopen($file_path, 'r');
                $row_count = 0;
                $newly_imported_count = 0;
                $enrolled_count = 0;

                // Prepare SQL statements once for efficiency inside the loop
                $find_student_stmt = $pdo->prepare("SELECT id FROM students WHERE student_id_num = ?");
                $insert_student_stmt = $pdo->prepare("INSERT INTO students (student_id_num, last_name, first_name, phone) VALUES (?, ?, ?, ?)");
                // INSERT IGNORE will silently fail if the student is already enrolled, which is desired behavior.
                $enroll_stmt = $pdo->prepare("INSERT IGNORE INTO class_enrollment (class_id, student_id) VALUES (?, ?)");

                while (($data = fgetcsv($handle)) !== FALSE) {
                    $row_count++;
                    if ($row_count == 1) continue; // Skip header row

                    // Basic validation for the row data
                    if (count($data) < 3 || empty($data[0]) || empty($data[1]) || empty($data[2])) {
                        throw new Exception("Invalid or incomplete data found on row {$row_count}. Aborting import.");
                    }

                    $student_id_num = trim($data[0]);
                    $last_name = trim($data[1]);
                    $first_name = trim($data[2]);
                    $phone = isset($data[3]) ? trim($data[3]) : null;

                    // Step 1: Find student by ID number or create them if they don't exist.
                    $find_student_stmt->execute([$student_id_num]);
                    $student = $find_student_stmt->fetch(PDO::FETCH_ASSOC);

                    $student_pk_id = null;
                    if ($student) {
                        // Student already exists, just get their primary key
                        $student_pk_id = $student['id'];
                    } else {
                        // Student is new, so insert them and get the new primary key
                        $insert_student_stmt->execute([$student_id_num, $last_name, $first_name, $phone]);
                        $student_pk_id = $pdo->lastInsertId();
                        $newly_imported_count++;
                    }

                    // Step 2: Enroll the student (found or newly created) into the target class.
                    $enroll_stmt->execute([$class_id, $student_pk_id]);
                    // Check if a row was actually inserted (i.e., they weren't already enrolled)
                    if ($enroll_stmt->rowCount() > 0) {
                        $enrolled_count++;
                    }
                }

                // If all rows processed without error, commit the changes to the database.
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => "Import complete! Added {$newly_imported_count} new students and enrolled {$enrolled_count} into the class."
                ]);

            } catch (Exception $e) {
                // An error occurred, so undo all changes from this import.
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Import Failed: ' . $e->getMessage()]);
            } finally {
                // Always close the file handle if it was opened
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
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    // For debugging: error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
}
