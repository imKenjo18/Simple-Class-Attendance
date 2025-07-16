document.addEventListener('DOMContentLoaded', () => {

    // --- DOM ELEMENT SELECTORS ---
    const loginFormContainer = document.getElementById('login-form-container');
    const registerFormContainer = document.getElementById('register-form-container');
    const showRegisterLink = document.getElementById('show-register-link');
    const showLoginLink = document.getElementById('show-login-link');
    
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    
    const loginMessageDiv = document.getElementById('login-message');
    const registerMessageDiv = document.getElementById('register-message');

    // --- FORM SWITCHING LOGIC ---
    showRegisterLink.addEventListener('click', (e) => {
        e.preventDefault();
        loginFormContainer.style.display = 'none';
        registerFormContainer.style.display = 'block';
    });

    showLoginLink.addEventListener('click', (e) => {
        e.preventDefault();
        registerFormContainer.style.display = 'none';
        loginFormContainer.style.display = 'block';
    });


    // --- FORM SUBMISSION HANDLERS ---
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(loginForm);
        formData.append('action', 'login');
        
        try {
            const response = await fetch('api/auth.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // On successful login, redirect to the dashboard.
                window.location.href = 'dashboard.php';
            } else {
                displayMessage(loginMessageDiv, result.message, 'error');
            }
        } catch (error) {
            displayMessage(loginMessageDiv, 'An unexpected error occurred. Please try again.', 'error');
            console.error('Login error:', error);
        }
    });

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        // --- Client-side validation ---
        if (password !== confirmPassword) {
            displayMessage(registerMessageDiv, 'Passwords do not match.', 'error');
            return;
        }

        const formData = new FormData(registerForm);
        formData.append('action', 'register');
        
        try {
            const response = await fetch('api/auth.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                displayMessage(registerMessageDiv, result.message + ' You can now log in.', 'success');
                registerForm.reset();
                // Optionally switch to login form after successful registration
                setTimeout(() => {
                    registerFormContainer.style.display = 'none';
                    loginFormContainer.style.display = 'block';
                    loginMessageDiv.innerHTML = ''; // Clear any old login messages
                }, 2000);
            } else {
                displayMessage(registerMessageDiv, result.message, 'error');
            }
        } catch (error) {
            displayMessage(registerMessageDiv, 'An unexpected error occurred. Please try again.', 'error');
            console.error('Registration error:', error);
        }
    });

    // --- HELPER FUNCTION ---
    function displayMessage(element, message, type) {
        element.textContent = message;
        element.className = `message ${type}`; // e.g., 'message success' or 'message error'
    }
});