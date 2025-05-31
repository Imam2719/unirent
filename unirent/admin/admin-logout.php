<?php
// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to admin login page
header('Location: admin-login.php');
exit;
?>