<?php
// Start session and include required files
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in as admin
if (isAdmin()) {
    // User is logged in as admin, redirect to dashboard
    header('Location: dashboard.php');
    exit;
} else {
    // User is not logged in as admin, redirect to admin login page
    header('Location: admin-login.php');
    exit;
}
?>