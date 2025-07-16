<?php
/**
 * Logout script for the Class Attendance system.
 * This script destroys the current session and redirects the user to the login page.
 */

// 1. Initialize the session.
// This is necessary to access the session variables.
session_start();

// 2. Unset all session variables.
// This clears all data stored in the session, like 'teacher_id' and 'username'.
$_SESSION = array();

// 3. Destroy the session itself.
// This removes the session from the server.
session_destroy();

// 4. Redirect the user to the login page.
// The login page is assumed to be 'index.php' in the root directory.
header("location: index.php");

// 5. Ensure no further code is executed after the redirect.
exit;
?>