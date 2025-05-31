<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'includes/provenance/query_logger.php';
require_once 'includes/provenance/activity_logger.php';

// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

if (!isset($_POST['id'])) {
    $_SESSION['error'] = 'Rental ID not specified.';
    header('Location: manage-rentals.php');
    exit;
}

$rental_id = intval($_POST['id']);
$rejection_reason = $_POST['rejection_reason'] ?? 'No reason provided';

// Get rental details including equipment_id
$query = "SELECT r.*, e.id as equipment_id FROM rentals r JOIN equipment e ON r.equipment_id = e.id WHERE r.id = ?";
$stmt = prepare_logged($conn, $query, 'SELECT', 'rentals');

if ($stmt === false) {
    $_SESSION['error'] = 'Database error occurred.';
    header('Location: manage-rentals.php');
    exit;
}

$stmt->bind_param('i', $rental_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Rental not found.';
    header('Location: manage-rentals.php');
    exit;
}

$rental = $result->fetch_assoc();

if ($rental['status'] != RENTAL_PENDING) {
    $_SESSION['error'] = 'Only pending rentals can be rejected.';
    header('Location: manage-rentals.php');
    exit;
}

// Update rental status to rejected
$rejected_status = RENTAL_REJECTED;
$update_query = "UPDATE rentals SET status = ? WHERE id = ?";
$update_stmt = prepare_logged($conn, $update_query, 'UPDATE', 'rentals');

if ($update_stmt === false) {
    $_SESSION['error'] = 'Database error occurred.';
    header('Location: manage-rentals.php');
    exit;
}

$update_stmt->bind_param('ii', $rejected_status, $rental_id);

if ($update_stmt->execute()) {
    $_SESSION['success'] = 'Rental rejected successfully.';
    
    // Log activity
    log_activity('reject_rental', "Rejected rental ID $rental_id");
    
    // Track provenance
    $details = json_encode([
        'old_status' => RENTAL_PENDING,
        'new_status' => RENTAL_REJECTED,
        'rejecter_id' => $_SESSION['user_id'],
        'reason' => $rejection_reason
    ]);
    trackProvenance($conn, 'rental', $rental_id, 'rental_rejected', $rental_id, $details);
} else {
    $_SESSION['error'] = 'Failed to reject rental.';
}

$update_stmt->close();
header('Location: manage-rentals.php');
exit;
?>