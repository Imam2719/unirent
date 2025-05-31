<?php
// Start session and check admin access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Check if user ID is provided
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: manage-users.php');
    exit;
}

$user_id = intval($_POST['id']);

// Prevent admin from deleting themselves
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = 'You cannot delete your own account';
    header('Location: manage-users.php');
    exit;
}

try {
    // Check if user has any equipment assigned as owner
    $stmt = $conn->prepare("SELECT COUNT(*) FROM equipment WHERE owner_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $equipment_count = $result->fetch_row()[0];
    
    // Check if user has any rentals
    $stmt = $conn->prepare("SELECT COUNT(*) FROM rentals WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rental_count = $result->fetch_row()[0];
    
    if ($equipment_count > 0 || $rental_count > 0) {
        $_SESSION['error'] = 'Cannot delete user that owns equipment or has rental history.';
        header('Location: manage-users.php');
        exit;
    }
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Delete user's activity logs
    $stmt = $conn->prepare("DELETE FROM admin_activity WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Delete user's provenance records
    $stmt = $conn->prepare("DELETE FROM data_provenance WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Delete user's notifications
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Delete user's wishlist items
    $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Now delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'User deleted successfully';
    } else {
        $_SESSION['error'] = 'Failed to delete user: ' . $stmt->error;
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    $stmt->close();
} catch (Exception $e) {
    // Make sure to re-enable foreign key checks even if error occurs
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: manage-users.php');
exit;
?>