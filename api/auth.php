<?php
session_start();
require_once '../config/database.php';

// For Registration
if ($_POST['action'] == 'register') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO teachers (username, password_hash) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$username, $password_hash])) {
        echo json_encode(['success' => true, 'message' => 'Registration successful!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed.']);
    }
}

// For Login
if ($_POST['action'] == 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, password_hash FROM teachers WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $teacher = $stmt->fetch();

    if ($teacher && password_verify($password, $teacher['password_hash'])) {
        $_SESSION['teacher_id'] = $teacher['id'];
        $_SESSION['username'] = $username;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
    }
}
?>