<?php
/**
 * API endpoint for managing class enrollments.
 * This is the bridge between the 'classes' and 'students' tables.
 * All actions require an authenticated teacher session.
 */

session_start();

// 1. SECURITY: Ensure a teacher is logged in.
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// 2. SETUP: Include database and set headers.
require_once '../config/database.php';
header('Content-Type: application/json');

$teacher_id = $_SESSION['teacher_id'];
// Use $_REQUEST to handle both GET (for fetching data) and POST (for creating data).
$action = $_REQUEST['action'] ?? '';

// 3. LOGIC: Use a try-catch block for database error handling.
try {
    switch ($action) {

        /**
         * ACTION: get_enrolled_students
         * Fetches all students currently enrolled in a specific class.
         * Used to display the student list on the class detail page.
         * Method: GET
         * Params: class_id
         */
        case 'get_enrolled_students':
            // --- INPUT VALIDATION ---
            if (empty($_GET['class_id'])) {
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Class ID is required.']);
                exit;
            }
            $class_id = $_GET['class_id'];

            // --- SECURITY CHECK ---
            // Ensure the requested class belongs to the logged-in teacher to prevent data snooping.
            $stmt_check = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
            $stmt_check->execute([$class_id, $teacher_id]);
            if (!$stmt_check->fetch()) {
                http_response_code(403); // Forbidden
                echo json_encode(['success' => false, 'message' => 'Access denied to this class.']);
                exit;
            }

            // --- DATABASE QUERY ---
            // Join students with class_enrollment to find all students for the given class_id.
            $sql = "SELECT s.id, s.student_id_num, s.first_name, s.last_name, s.phone, s.status 
                    FROM students s
                    JOIN class_enrollment ce ON s.id = ce.student_id
                    WHERE ce.class_id = ?
                    ORDER BY s.last_name, s.first_name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$class_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        /**
         * ACTION: get_unenrolled_students
         * Fetches students who exist in the system but are NOT YET enrolled in a specific class.
         * Used to populate the dropdown in the "Enroll Student" modal.
         * Method: GET
         * Params: class_id
         */
        case 'get_unenrolled_students':
            // --- INPUT VALIDATION ---
            if (empty($_GET['class_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Class ID is required.']);
                exit;
            }
            $class_id = $_GET['class_id'];
            
            // --- DATABASE QUERY ---
            // This subquery finds all students who are NOT in the enrollment list for the target class.
            $sql = "SELECT id, first_name, last_name, student_id_num FROM students s
                    WHERE s.id NOT IN (SELECT student_id FROM class_enrollment WHERE class_id = ?)
                    ORDER BY s.last_name, s.first_name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$class_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        /**
         * ACTION: enroll_student
         * Creates a new record in the class_enrollment table.
         * Method: POST
         * Params: class_id, student_id
         */
        case 'enroll_student':
            // --- INPUT VALIDATION ---
            if (empty($_POST['class_id']) || empty($_POST['student_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Class ID and Student ID are required.']);
                exit;
            }
            $class_id = $_POST['class_id'];
            $student_id = $_POST['student_id'];

            // --- PERFORM INSERTION ---
            // The database has a UNIQUE constraint on (class_id, student_id), so it will
            // naturally prevent duplicates. The catch block below handles the resulting error.
            $stmt = $pdo->prepare("INSERT INTO class_enrollment (class_id, student_id) VALUES (?, ?)");
            $stmt->execute([$class_id, $student_id]);
            
            echo json_encode(['success' => true, 'message' => 'Student enrolled successfully.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid enrollment action specified.']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    
    // Provide a more user-friendly error message for common issues like duplicates.
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'This student is already enrolled in this class.']);
    } else {
        // For other database errors, provide a generic message.
        // You can log the specific error for debugging: error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred on the server.']);
    }
}
?>