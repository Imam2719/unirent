<?php
// Start session and check admin access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'includes/provenance/query_logger.php';
$query = "UPDATE rentals SET status=2 WHERE id=$rentalId";
log_query($query, 'UPDATE', 'rentals');
require_once 'includes/provenance/activity_logger.php';
log_activity('approve_rental', "Approved rental ID $rentalId");

// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Check if equipment ID is provided
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = 'Invalid equipment ID';
    header('Location: manage-equipment.php');
    exit;
}

$equipment_id = intval($_POST['id']);

try {
    // Check if there are any rentals associated with this equipment
    $stmt = $conn->prepare("SELECT COUNT(*) FROM rentals WHERE equipment_id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    
    if ($count > 0) {
        $_SESSION['error'] = 'Cannot delete equipment that has associated rentals.';
        header('Location: manage-equipment.php');
        exit;
    }
    
    // Get image path before deleting
    $stmt = $conn->prepare("SELECT image FROM equipment WHERE id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $equipment = $result->fetch_assoc();
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Delete provenance records first
    $stmt = $conn->prepare("DELETE FROM data_provenance WHERE equipment_id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    
    // Now delete the equipment
    $stmt = $conn->prepare("DELETE FROM equipment WHERE id = ?");
    $stmt->bind_param("i", $equipment_id);
    
    if ($stmt->execute()) {
        // Delete the image file if it exists
        if (!empty($equipment['image'])) {
            $file_path = '../' . $equipment['image'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $_SESSION['success'] = 'Equipment deleted successfully';
    } else {
        $_SESSION['error'] = 'Failed to delete equipment: ' . $stmt->error;
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    $stmt->close();
} catch (Exception $e) {
    // Make sure to re-enable foreign key checks even if error occurs
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: manage-equipment.php');
exit;
?>