<?php
// Start the session to check for an existing login.
session_start();

// If the teacher's session variable is already set, they are logged in.
// Redirect them directly to the dashboard and stop executing this page.
if (isset($_SESSION['teacher_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Registration - Class Attendance</title>
    
    <!-- We can reuse the main stylesheet for basic elements like fonts and buttons -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- We use a specific stylesheet for the unique layout of the login page -->
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">

            <!-- Login Form Container (Visible by default) -->
            <div id="login-form-container">
                <h1>Welcome Back</h1>
                <p>Please enter your details to sign in.</p>
                <div id="login-message" class="message"></div>
                
                <form id="login-form" novalidate>
                    <div class="form-group">
                        <label for="login-username">Username</label>
                        <input type="text" id="login-username" name="username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="button primary full-width">Login</button>
                </form>
                
                <p class="auth-switch">First time here? <a href="#" id="show-register-link">Create an account</a></p>
            </div>

            <!-- Registration Form Container (Initially hidden) -->
            <div id="register-form-container" style="display: none;">
                <h1>Create Account</h1>
                <p>Let's get you set up for your first class.</p>
                <div id="register-message" class="message"></div>
                
                <form id="register-form" novalidate>
                    <div class="form-group">
                        <label for="register-username">Username</label>
                        <input type="text" id="register-username" name="username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="register-password">Password</label>
                        <input type="password" id="register-password" name="password" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Confirm Password</label>
                        <input type="password" id="confirm-password" name="confirm_password" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="button primary full-width">Register</button>
                </form>

                <p class="auth-switch">Already have an account? <a href="#" id="show-login-link">Sign in here</a></p>
            </div>

        </div>
    </div>

    <!-- The JavaScript for this page is separate from the main app's JS -->
    <script src="assets/js/auth.js"></script>

</body>
</html>