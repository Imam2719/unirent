<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'includes/provenance/activity_logger.php';

// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple logging function
function log_admin_query($query, $type = 'SELECT', $table = null, $affected_rows = 0) {
    global $conn;
    
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $file = basename(__FILE__);
    $line = __LINE__;
    
    $safe_query = mysqli_real_escape_string($conn, substr($query, 0, 1000));
    $safe_table = mysqli_real_escape_string($conn, $table ?? '');
    $safe_session = mysqli_real_escape_string($conn, $sessionId);
    $safe_ip = mysqli_real_escape_string($conn, $ip);
    $safe_agent = mysqli_real_escape_string($conn, substr($agent, 0, 250));
    $safe_file = mysqli_real_escape_string($conn, $file);
    
    $hash = hash('sha256', $query);
    
    $log_sql = "INSERT INTO query_provenance 
        (session_id, user_id, query_type, query_text, query_hash, table_name, affected_rows, execution_time, ip_address, user_agent, file_path, line_number) 
        VALUES ('$safe_session', $userId, '$type', '$safe_query', '$hash', '$safe_table', $affected_rows, 0.001, '$safe_ip', '$safe_agent', '$safe_file', $line)";
    
    mysqli_query($conn, $log_sql);
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

// First, get rental details
$query = "SELECT r.*, e.id as equipment_id FROM rentals r JOIN equipment e ON r.equipment_id = e.id WHERE r.id = ?";
$stmt = $conn->prepare($query);

if ($stmt === false) {
    $_SESSION['error'] = 'Database error occurred.';
    header('Location: manage-rentals.php');
    exit;
}

$stmt->bind_param('i', $rental_id);
$stmt->execute();
$result = $stmt->get_result();

// Log the query
log_admin_query($query, 'SELECT', 'rentals', $result->num_rows);

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Rental not found.';
    header('Location: manage-rentals.php');
    exit;
}

$rental = $result->fetch_assoc();
$stmt->close();

if ($rental['status'] != RENTAL_PENDING) {
    $_SESSION['error'] = 'Only pending rentals can be approved.';
    header('Location: manage-rentals.php');
    exit;
}

// Update rental status to approved
$approved_status = RENTAL_APPROVED;
$update_query = "UPDATE rentals SET status = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_query);

if ($update_stmt === false) {
    $_SESSION['error'] = 'Database error occurred.';
    header('Location: manage-rentals.php');
    exit;
}

$update_stmt->bind_param('ii', $approved_status, $rental_id);
$update_result = $update_stmt->execute();

// Log the update query
log_admin_query($update_query, 'UPDATE', 'rentals', $update_stmt->affected_rows);

if ($update_result) {
    $_SESSION['success'] = 'Rental approved successfully.';
    
    // Log activity
    log_activity('approve_rental', "Approved rental ID $rental_id");
    
    // Track provenance
    $details = json_encode([
        'old_status' => RENTAL_PENDING,
        'new_status' => RENTAL_APPROVED,
        'approver_id' => $_SESSION['user_id']
    ]);
    trackProvenance($conn, 'rental', $rental_id, 'rental_approved', $rental_id, $details);
    
    // Update equipment status to rented
    $equipment_query = "UPDATE equipment SET status = ? WHERE id = ?";
    $equipment_stmt = $conn->prepare($equipment_query);
    
    if ($equipment_stmt !== false) {
        $rented_status = STATUS_RENTED;
        $equipment_stmt->bind_param('ii', $rented_status, $rental['equipment_id']);
        $equipment_stmt->execute();
        
        // Log the equipment update query
        log_admin_query($equipment_query, 'UPDATE', 'equipment', $equipment_stmt->affected_rows);
        
        // Track equipment status change
        trackProvenance($conn, 'equipment', $rental['equipment_id'], 'equipment_status_changed', $rental_id, 
            json_encode([
                'old_status' => STATUS_AVAILABLE,
                'new_status' => STATUS_RENTED,
                'reason' => 'Rental approved'
            ]));
            
        $equipment_stmt->close();
    }
} else {
    $_SESSION['error'] = 'Failed to approve rental.';
}

$update_stmt->close();
header('Location: manage-rentals.php');
exit;
?>