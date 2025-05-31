<?php
session_start(); // Start session early

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_POST['id'])) {
    $_SESSION['error'] = 'Rental ID not specified.';
    header('Location: manage-rentals.php');
    exit;
}

$rentalId = intval($_POST['id']); // ✅ Now defined before used

// Include logger after variable is defined
require_once __DIR__ . '/includes/provenance/query_logger.php';
require_once __DIR__ . '/includes/provenance/activity_logger.php';

// Optional quick update with provenance (can be removed if not needed)
$quickQuery = "UPDATE rentals SET status=2 WHERE id=$rentalId";
log_query($quickQuery, 'UPDATE', 'rentals'); // ✅ log_query now has a defined $rentalId

log_activity('approve_rental', "Approved rental ID $rentalId");

// Get rental details including equipment_id
$query = "SELECT r.*, e.id as equipment_id FROM rentals r 
          JOIN equipment e ON r.equipment_id = e.id 
          WHERE r.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $rentalId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Rental not found.';
    header('Location: manage-rentals.php');
    exit;
}

$rental = $result->fetch_assoc();

if ($rental['status'] != RENTAL_APPROVED) {
    $_SESSION['error'] = 'Only approved rentals can be marked as completed.';
    header('Location: manage-rentals.php');
    exit;
}

// Update rental status
$completed_status = RENTAL_COMPLETED;
$update = $conn->prepare("UPDATE rentals SET status = ? WHERE id = ?");
$update->bind_param('ii', $completed_status, $rentalId);

if ($update->execute()) {
    $_SESSION['success'] = 'Rental marked as completed successfully.';

    // Track provenance: rental status change
    $details = json_encode([
        'old_status' => RENTAL_APPROVED,
        'new_status' => RENTAL_COMPLETED,
        'completer_id' => $_SESSION['user_id'],
        'completion_date' => date('Y-m-d H:i:s')
    ]);
    trackProvenance($conn, 'rental', $rentalId, 'rental_completed', $rentalId, $details);

    // Update equipment status to available
    $update_equipment = $conn->prepare("UPDATE equipment SET status = ? WHERE id = ?");
    $available_status = STATUS_AVAILABLE;
    $update_equipment->bind_param('ii', $available_status, $rental['equipment_id']);
    $update_equipment->execute();

    // Track equipment status change
    trackProvenance($conn, 'equipment', $rental['equipment_id'], 'equipment_status_changed', $rentalId, json_encode([
        'old_status' => STATUS_RENTED,
        'new_status' => STATUS_AVAILABLE,
        'reason' => 'Rental completed'
    ]));
} else {
    $_SESSION['error'] = 'Failed to complete rental.';
}

header('Location: manage-rentals.php');
exit;
?>
