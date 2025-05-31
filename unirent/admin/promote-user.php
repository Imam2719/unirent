<?php
// promote-user.php - Enhanced with full provenance
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/provenance/activity_logger.php';

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: manage-users.php');
    exit;
}

if ($user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = 'You cannot promote yourself';
    header('Location: manage-users.php');
    exit;
}

// Check if user exists and is student
$stmt = $conn->prepare("SELECT id, user_type, first_name, last_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'User not found';
    header('Location: manage-users.php');
    exit;
}

$user = $result->fetch_assoc();
if ($user['user_type'] != 1) {
    $_SESSION['error'] = 'User is already an admin';
    header('Location: manage-users.php');
    exit;
}

$reason = 'Promoted to admin';
$current_user = $_SESSION['user_id'];
$current_ip = $_SERVER['REMOTE_ADDR'];

// Use the enhanced promoteUser function with full provenance
if (promoteUser($conn, $user_id, $current_user, $reason, $current_ip)) {
    $_SESSION['success'] = $user['first_name'] . ' ' . $user['last_name'] . ' has been promoted to admin successfully with full audit trail';
    log_activity('user_promoted', "Promoted user {$user['first_name']} {$user['last_name']} (ID: $user_id) to admin");
} else {
    $_SESSION['error'] = 'Failed to promote user: ' . $conn->error;
}

header('Location: manage-users.php');
exit;
?>