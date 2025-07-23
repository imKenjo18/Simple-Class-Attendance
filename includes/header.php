<?php
// Start the session on every page that includes this header.
// This must be the very first thing in the script.
session_start();

// SECURITY: This is the most important part of the file.
// If the teacher's session ID is not set, it means they are not logged in.
// Redirect them to the login page and stop the script from running further.
if (!isset($_SESSION['teacher_id'])) {
    // We assume the login page is named 'index.php' and is in the root directory.
    header('Location: index.php');
    exit;
}

// For convenience, get the username from the session and sanitize it for safe display.
// htmlspecialchars() prevents Cross-Site Scripting (XSS) attacks.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard</title>
    <link rel="stylesheet" href="assets/css/google-fonts.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header>
        <h1>Class Attendance</h1>
        <nav>
            <a href="logout.php" class="button">Logout</a>
        </nav>
    </header>

    <!-- We open the <main> tag here. The content of each page will go inside it,
         and the footer.php will close it. -->
    <main>