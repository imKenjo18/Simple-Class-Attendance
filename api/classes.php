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
        case 'get_class_schedule':
            header('Content-Type: application/json');
            $class_id = (int)($_GET['class_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT day_of_week, start_time, end_time
                                FROM class_schedules
                                WHERE class_id = ?
                                ORDER BY FIELD(day_of_week,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')");
            $stmt->execute([$class_id]);
            echo json_encode(['success' => true, 'schedule' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

                case 'get_classes':
                        // Return classes with a readable schedule summary built from class_schedules
                        $sql = "
                                SELECT c.*,
                                             TRIM(BOTH '; ' FROM IFNULL(
                                                 GROUP_CONCAT(
                                                     CONCAT(cs.day_of_week, ' ', DATE_FORMAT(cs.start_time, '%H:%i'), '-', DATE_FORMAT(cs.end_time, '%H:%i'))
                                                     ORDER BY FIELD(cs.day_of_week,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') SEPARATOR '; '
                                                 ), ''
                                             )) AS schedule_summary
                                FROM classes c
                                LEFT JOIN class_schedules cs ON cs.class_id = c.id
                                WHERE c.teacher_id = ?
                                GROUP BY c.id
                                ORDER BY c.class_name, c.id
                        ";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$teacher_id]);
                        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                        break;

        case 'add_class':
            // Legacy schedule columns are deprecated; keep nulls
            $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, class_name, unit_code, start_time, end_time, day_of_week) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $teacher_id,
                $_POST['class_name'] ?? '',
                $_POST['unit_code'] ?? null,
                $_POST['start_time'] ?? null,
                $_POST['end_time'] ?? null,
                $_POST['day_of_week'] ?? null
            ]);

            $class_id = (int)$pdo->lastInsertId();

            // Persist custom schedule if provided (empty array means clear/no schedule)
            $schedule_msg = '';
            if (isset($_POST['schedule_json'])) {
                $schedule = json_decode($_POST['schedule_json'], true) ?: [];
                if (!empty($schedule)) {
                    $stmtIns = $pdo->prepare("INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                    foreach ($schedule as $row) {
                        if (!isset($row['day_of_week'],$row['start_time'],$row['end_time'])) continue;
                        $stmtIns->execute([$class_id, $row['day_of_week'], $row['start_time'], $row['end_time']]);
                    }
                    $schedule_msg = 'Schedules added.';
                } else {
                    $schedule_msg = 'No schedules set.';
                }
            }

            $message = 'Class added successfully.' . ($schedule_msg ? ' ' . $schedule_msg : '');
            echo json_encode(['success' => true, 'message' => $message, 'id' => $class_id]);
            break;

        case 'update_class':
            // Legacy schedule columns are deprecated; keep them as-is/null
            $stmt = $pdo->prepare("UPDATE classes SET class_name=?, unit_code=? WHERE id=? AND teacher_id=?");
            $stmt->execute([
                $_POST['class_name'],
                $_POST['unit_code'],
                // $_POST['start_time'],
                // $_POST['end_time'],
                // $_POST['day_of_week'],
                $_POST['class_id'],
                $teacher_id
            ]);

            $class_id = (int)($_POST['class_id'] ?? 0);

            // Upsert custom schedule (clear + insert)
            $schedule_msg = '';
            if (isset($_POST['schedule_json'])) {
                $schedule = json_decode($_POST['schedule_json'], true) ?: [];
                $pdo->prepare("DELETE FROM class_schedules WHERE class_id = ?")->execute([$class_id]);
                if (!empty($schedule)) {
                    $stmtIns = $pdo->prepare("INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                    foreach ($schedule as $row) {
                        if (!isset($row['day_of_week'],$row['start_time'],$row['end_time'])) continue;
                        $stmtIns->execute([$class_id, $row['day_of_week'], $row['start_time'], $row['end_time']]);
                    }
                    $schedule_msg = 'Schedules updated.';
                } else {
                    $schedule_msg = 'Schedules cleared.';
                }
            }

            // Always return success with a friendly message (even if class row unchanged)
            $message = 'Class updated successfully.' . ($schedule_msg ? ' ' . $schedule_msg : '');
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
