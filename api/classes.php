<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

require_once '../config/database.php';
$teacher_id = $_SESSION['teacher_id'];
$action = $_REQUEST['action'] ?? ''; // Use $_REQUEST to handle GET and POST

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get_classes':
            $stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ? ORDER BY day_of_week, start_time");
            $stmt->execute([$teacher_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'add_class':
            $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, class_name, unit_code, start_time, end_time, day_of_week) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$teacher_id, $_POST['class_name'], $_POST['unit_code'], $_POST['start_time'], $_POST['end_time'], $_POST['day_of_week']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'update_class':
            $stmt = $pdo->prepare("UPDATE classes SET class_name=?, unit_code=?, start_time=?, end_time=?, day_of_week=? WHERE id=? AND teacher_id=?");
            $stmt->execute([
                $_POST['class_name'], 
                $_POST['unit_code'], 
                $_POST['start_time'], 
                $_POST['end_time'], 
                $_POST['day_of_week'], 
                $_POST['class_id'], 
                $teacher_id
            ]);
            
            $message = ($stmt->rowCount() > 0) ? 'Class updated successfully.' : 'No changes were needed.';
            
            // The key change: 'success' is always true.
            echo json_encode(['success' => true, 'message' => $message]);
            break;

        case 'delete_class':
            // Note: ON DELETE CASCADE in the DB will also remove enrollments and attendance records.
            $stmt = $pdo->prepare("DELETE FROM classes WHERE id=? AND teacher_id=?");
            $stmt->execute([$_POST['class_id'], $teacher_id]);
            echo json_encode(['success' => $stmt->rowCount() > 0]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>