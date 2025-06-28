<?php
session_start();            // Start the session
session_unset();            // Unset all session variables
session_destroy();          // Destroy the session completely

// Optional: remove session cookie
if (ini_get("session.use_cookies")) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Redirect to login page
header("Location: views/auth/login.php");
exit();
