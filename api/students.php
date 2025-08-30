<?php
/**
 * API endpoint for managing students (CRUD).
 * All actions require an authenticated teacher session.
 */

session_start();

// 1. SECURITY: Check if the user is authenticated.
if (!isset($_SESSION['teacher_id'])) {
    // If not logged in, send a 403 Forbidden response and stop execution.
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// 2. SETUP: Include database configuration and set headers.
require_once '../config/database.php';
header('Content-Type: application/json');

// Get the requested action. Default to an empty string if not set.
$action = $_REQUEST['action'] ?? '';
// Note: In this schema, students are global and not tied to a specific teacher.
// A potential future improvement would be to add a `teacher_id` to the `students` table
// to scope students to the teacher who created them.

// 3. LOGIC: Use a try-catch block for robust error handling.
try {
    // 4. ROUTING: Use a switch statement to handle different actions.
    switch ($action) {
        case 'add_and_enroll_student':
            // --- INPUT VALIDATION ---
            if (empty($_POST['student_id_num']) || empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['class_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields or class ID.']);
                exit;
            }

            // Validate Code 128-compatible characters (printable ASCII 0x20-0x7E)
            if (!preg_match('/^[ -~]+$/', $_POST['student_id_num'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Student ID Number contains invalid characters for Code 128.']);
                exit;
            }

            $class_id = $_POST['class_id'];

            // Use a transaction to ensure both operations succeed or fail together.
            $pdo->beginTransaction();
            try {
                // Step 1: Insert the new student into the 'students' table.
                $sql_student = "INSERT INTO students (student_id_num, last_name, first_name, phone) VALUES (?, ?, ?, ?)";
                $stmt_student = $pdo->prepare($sql_student);
                $stmt_student->execute([
                    $_POST['student_id_num'],
                    $_POST['last_name'],
                    $_POST['first_name'],
                    $_POST['phone'] ?? null
                ]);

                // Get the ID of the student we just created.
                $new_student_id = $pdo->lastInsertId();

                // Step 2: Enroll the new student into the specified class.
                $sql_enroll = "INSERT INTO class_enrollment (class_id, student_id) VALUES (?, ?)";
                $stmt_enroll = $pdo->prepare($sql_enroll);
                $stmt_enroll->execute([$class_id, $new_student_id]);

                // If both queries were successful, commit the transaction.
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Student added and enrolled successfully.']);

            } catch (PDOException $e) {
                // If any error occurred, roll back all changes.
                $pdo->rollBack();

                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo json_encode(['success' => false, 'message' => 'Error: A student with that ID Number already exists.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            }
            break;

        case 'get_students':
            // Fetches all students, ordered by name.
            $stmt = $pdo->prepare("SELECT * FROM students ORDER BY last_name, first_name");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($students);
            break;

        case 'add_student':
            // Adds a new student to the database.
            // Check for required fields.
            if (empty($_POST['student_id_num']) || empty($_POST['first_name']) || empty($_POST['last_name'])) {
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
                exit;
            }

            if (!preg_match('/^[ -~]+$/', $_POST['student_id_num'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Student ID Number contains invalid characters for Code 128.']);
                exit;
            }

            $sql = "INSERT INTO students (student_id_num, last_name, first_name, phone) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            // Execute with data from the form.
            $stmt->execute([
                $_POST['student_id_num'],
                $_POST['last_name'],
                $_POST['first_name'],
                $_POST['phone'] ?? null // Use null if phone is not provided
            ]);

            echo json_encode(['success' => true, 'message' => 'Student added successfully.']);
            break;

        case 'update_student':
            // Updates an existing student's details.
            if (empty($_POST['student_id']) || empty($_POST['student_id_num']) || empty($_POST['first_name']) || empty($_POST['last_name'])) {
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Missing required fields for update.']);
                exit;
            }

            if (!preg_match('/^[ -~]+$/', $_POST['student_id_num'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Student ID Number contains invalid characters for Code 128.']);
                exit;
            }

            $sql = "UPDATE students SET student_id_num = ?, last_name = ?, first_name = ?, phone = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $_POST['student_id_num'],
                $_POST['last_name'],
                $_POST['first_name'],
                $_POST['phone'] ?? null,
                $_POST['student_id'] // The ID for the WHERE clause
            ]);

            $success = $stmt->rowCount() > 0;

                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Student updated.' : 'No changes made.'
                ]);
                break;

        case 'delete_student':
            // Deletes a student.
            // The ON DELETE CASCADE in the database schema will automatically remove
            // associated records from `class_enrollment` and `attendance_records`.
            if (empty($_POST['student_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Student ID is required for deletion.']);
                exit;
            }

            $sql = "DELETE FROM students WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['student_id']]);

            $success = $stmt->rowCount() > 0;
            echo json_encode(['success' => $success, 'message' => $success ? 'Student deleted.' : 'Student not found.']);
            break;

        default:
            // Handle any unknown actions.
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
            break;
    }
} catch (PDOException $e) {
    // 5. ERROR HANDLING: Catch any database errors and return a generic server error message.
    http_response_code(500); // Internal Server Error
    // In a development environment, you might want to log the specific error.
    // error_log($e->getMessage());
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        // Provide a more specific error for unique constraint violations
        echo json_encode(['success' => false, 'message' => 'Database Error: A student with that ID Number already exists.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
    }
}
