<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['rental_id'])) {
    header('Location: login.php');
    exit;
}

$rental_id = intval($_POST['rental_id']);

// Process equipment return
$success = returnEquipment($conn, $rental_id, $_SESSION['user_id']);

if ($success) {
    $_SESSION['success'] = 'Equipment return has been processed successfully.';
} else {
    $_SESSION['error'] = 'Failed to process equipment return. Please try again.';
}

header('Location: my-rentals.php');
exit;
?>