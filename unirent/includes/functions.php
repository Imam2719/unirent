<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Equipment Status Constants
if (!defined('STATUS_AVAILABLE')) define('STATUS_AVAILABLE', 1);
if (!defined('STATUS_RENTED')) define('STATUS_RENTED', 2);
if (!defined('STATUS_MAINTENANCE')) define('STATUS_MAINTENANCE', 3);
if (!defined('STATUS_RESERVED')) define('STATUS_RESERVED', 4);

// Rental Status Constants
if (!defined('RENTAL_PENDING')) define('RENTAL_PENDING', 1);
if (!defined('RENTAL_APPROVED')) define('RENTAL_APPROVED', 2);
if (!defined('RENTAL_REJECTED')) define('RENTAL_REJECTED', 3);
if (!defined('RENTAL_COMPLETED')) define('RENTAL_COMPLETED', 4);
if (!defined('RENTAL_CANCELLED')) define('RENTAL_CANCELLED', 5);

// User session checks
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 2;
}

// Status helper functions
function getStatusText($status) {
    $statuses = [
        STATUS_AVAILABLE => 'Available',
        STATUS_RENTED => 'Rented',
        STATUS_MAINTENANCE => 'Maintenance',
        STATUS_RESERVED => 'Reserved'
    ];
    return $statuses[$status] ?? 'Unknown';
}

function getStatusBadgeClass($status) {
    $classes = [
        STATUS_AVAILABLE => 'success',
        STATUS_RENTED => 'warning',
        STATUS_MAINTENANCE => 'danger',
        STATUS_RESERVED => 'info'
    ];
    return $classes[$status] ?? 'secondary';
}

// Category list
function getCategories($conn) {
    $categories = [];
    $sql = "SELECT c.id, c.name, c.icon, COUNT(e.id) as item_count 
            FROM categories c
            LEFT JOIN equipment e ON c.id = e.category_id
            WHERE e.status = " . STATUS_AVAILABLE . "
            GROUP BY c.id
            ORDER BY c.name";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    return $categories;
}

// Featured equipment
function getFeaturedEquipment($conn) {
    $equipment = [];
    $sql = "SELECT e.*, c.name as category_name, u.first_name, u.last_name, u.user_type
            FROM equipment e
            JOIN categories c ON e.category_id = c.id
            JOIN users u ON e.owner_id = u.id
            WHERE e.status = " . STATUS_AVAILABLE . " AND e.is_featured = 1
            ORDER BY e.created_at DESC
            LIMIT 8";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $equipment[] = $row;
        }
    }

    return $equipment;
}

// Equipment details
function getEquipmentById($conn, $id) {
    $sql = "SELECT e.*, c.name as category_name, u.first_name, u.last_name, u.user_type
            FROM equipment e
            JOIN categories c ON e.category_id = c.id
            JOIN users u ON e.owner_id = u.id
            WHERE e.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

// Equipment search
function searchEquipment($conn, $keyword, $category = null, $availability = null) {
    $equipment = [];
    $sql = "SELECT e.*, c.name as category_name, u.first_name, u.last_name, u.user_type
            FROM equipment e
            JOIN categories c ON e.category_id = c.id
            JOIN users u ON e.owner_id = u.id
            WHERE (e.name LIKE ? OR e.description LIKE ?)";

    $params = ["%$keyword%", "%$keyword%"];
    $types = "ss";

    if ($category) {
        $sql .= " AND e.category_id = ?";
        $params[] = $category;
        $types .= "i";
    }

    if ($availability) {
        $sql .= " AND e.status = ?";
        $params[] = $availability;
        $types .= "i";
    } else {
        $sql .= " AND e.status = " . STATUS_AVAILABLE;
    }

    $sql .= " ORDER BY e.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $equipment[] = $row;
    }

    return $equipment;
}

// Create new rental
function createRental($conn, $equipment_id, $user_id, $start_date, $end_date, $purpose) {
    // Get equipment details for total amount calculation
    $equipment_sql = "SELECT daily_rate FROM equipment WHERE id = ?";
    $equipment_stmt = $conn->prepare($equipment_sql);
    $equipment_stmt->bind_param("i", $equipment_id);
    $equipment_stmt->execute();
    $equipment_result = $equipment_stmt->get_result();
    
    if ($equipment_result->num_rows === 0) {
        return false;
    }
    
    $equipment = $equipment_result->fetch_assoc();
    
    // Calculate total amount
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = $start->diff($end)->days;
    $total_amount = $days * $equipment['daily_rate'];
    
    // Set audit session variables if needed
    $conn->query("SET @current_user_id = " . intval($user_id));
    $conn->query("SET @current_session_id = '" . $conn->real_escape_string(session_id()) . "'");
    $conn->query("SET @current_ip = '" . $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "'");
    
    // Insert rental
    $status = RENTAL_PENDING;
    $sql = "INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssid", $equipment_id, $user_id, $start_date, $end_date, $purpose, $status, $total_amount);
    
    if ($stmt->execute()) {
        $insert_id = $stmt->insert_id;
        
        if ($insert_id > 0) {
            return $insert_id;
        } else {
            // Fallback check if insert ID is 0 but record was created
            $check_sql = "SELECT id FROM rentals WHERE equipment_id = ? AND user_id = ? AND start_date = ? AND end_date = ? ORDER BY created_at DESC LIMIT 1";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iiss", $equipment_id, $user_id, $start_date, $end_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $record = $check_result->fetch_assoc();
                return $record['id'];
            }
        }
    }
    
    return false;
}

// Get user's rentals
function getUserRentals($conn, $user_id, $status = null) {
    $rentals = [];
    $sql = "SELECT r.*, e.name as equipment_name, e.image, u.first_name, u.last_name
            FROM rentals r
            JOIN equipment e ON r.equipment_id = e.id
            JOIN users u ON e.owner_id = u.id
            WHERE r.user_id = ?";

    $params = [$user_id];
    $types = "i";

    if ($status) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
        $types .= "i";
    }

    $sql .= " ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $rentals[] = $row;
    }

    return $rentals;
}

// User authentication
function authenticateUser($conn, $email, $password) {
    $sql = "SELECT * FROM users WHERE email = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        return password_verify($password, $user['password']) ? $user : false;
    }

    return false;
}

// User registration
function registerUser($conn, $first_name, $last_name, $email, $password, $student_id, $user_type) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (first_name, last_name, email, password, student_id, user_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $first_name, $last_name, $email, $hashed_password, $student_id, $user_type);

    return $stmt->execute() ? $stmt->insert_id : false;
}

// Get equipment provenance/history
function getEquipmentProvenance($conn, $equipment_id) {
    $provenance = [];
    $sql = "SELECT dp.*, u.first_name, u.last_name
            FROM data_provenance dp
            JOIN users u ON dp.user_id = u.id
            WHERE dp.equipment_id = ?
            ORDER BY dp.timestamp DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $provenance[] = $row;
    }

    return $provenance;
}

// Enhanced functions.php for provenance system
// Add these functions to your existing functions.php

// =====================================================
// PROVENANCE HELPER FUNCTIONS
// =====================================================

/**
 * Format provenance action for display
 */
function formatProvenanceAction($action) {
    $actions = [
        'rental_created' => 'Rental Request Created',
        'rental_approved' => 'Rental Approved',
        'rental_rejected' => 'Rental Rejected', 
        'rental_completed' => 'Rental Completed',
        'rental_cancelled' => 'Rental Cancelled',
        'equipment_created' => 'Equipment Added',
        'equipment_updated' => 'Equipment Updated',
        'equipment_deleted' => 'Equipment Deleted',
        'equipment_status_changed' => 'Equipment Status Changed',
        'user_created' => 'User Registered',
        'user_updated' => 'User Profile Updated',
        'user_promoted' => 'User Promoted to Admin',
        'user_demoted' => 'User Demoted to Student',
        'price_updated' => 'Price Updated',
        'student_equipment_update' => 'Student Equipment Update'
    ];
    
    return $actions[$action] ?? ucwords(str_replace('_', ' ', $action));
}

/**
 * Get action badge class for CSS styling
 */
function getActionBadgeClass($action) {
    if (strpos($action, 'created') !== false || strpos($action, 'approved') !== false) {
        return 'success';
    }
    if (strpos($action, 'updated') !== false || strpos($action, 'completed') !== false) {
        return 'info';
    }
    if (strpos($action, 'deleted') !== false || strpos($action, 'rejected') !== false) {
        return 'danger';
    }
    if (strpos($action, 'cancelled') !== false) {
        return 'warning';
    }
    return 'secondary';
}

// Create a provenance record in data_provenance table
function createProvenanceRecord($conn, $equipment_id, $user_id, $action, $reference_id = null, $details = null) {
    $sql = "INSERT INTO data_provenance (equipment_id, user_id, action, reference_id, details, timestamp, ip_address, session_id) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $session_id = session_id();
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisisss", $equipment_id, $user_id, $action, $reference_id, $details, $ip_address, $session_id);
    
    return $stmt->execute();
}

// =====================================================
// ENHANCED RENTAL MANAGEMENT WITH PROVENANCE
// =====================================================

/**
 * Approve rental with full provenance tracking
 */
function approveRental($conn, $rental_id, $approver_id, $reason = null, $ip_address = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    $conn->autocommit(false);
    
    try {
        // Get rental details
        $stmt = $conn->prepare("SELECT r.*, e.id as equipment_id FROM rentals r JOIN equipment e ON r.equipment_id = e.id WHERE r.id = ?");
        $stmt->bind_param('i', $rental_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Rental not found');
        }
        
        $rental = $result->fetch_assoc();
        
        if ($rental['status'] != RENTAL_PENDING) {
            throw new Exception('Only pending rentals can be approved');
        }
        
        // Update rental status to approved
        $stmt = $conn->prepare("UPDATE rentals SET status = ? WHERE id = ?");
        $stmt->bind_param('ii', $approved_status = RENTAL_APPROVED, $rental_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update rental status');
        }
        
        // Update equipment status to rented
        $stmt = $conn->prepare("UPDATE equipment SET status = ? WHERE id = ?");
        $stmt->bind_param('ii', $rented_status = STATUS_RENTED, $rental['equipment_id']);
        $stmt->execute();
        
        // Create provenance record
        createProvenanceRecord($conn, $rental['equipment_id'], $approver_id, 'rental_approved', $rental_id, 
            json_encode([
                'old_status' => RENTAL_PENDING,
                'new_status' => RENTAL_APPROVED,
                'approver_id' => $approver_id,
                'reason' => $reason
            ]));
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Rental approval failed: " . $e->getMessage());
        return false;
    } finally {
        $conn->autocommit(true);
    }
}

/**
 * Reject rental with full provenance tracking
 */
function rejectRental($conn, $rental_id, $rejecter_id, $reason = null, $ip_address = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    $conn->autocommit(false);
    
    try {
        // Get rental details
        $stmt = $conn->prepare("SELECT r.*, e.id as equipment_id FROM rentals r JOIN equipment e ON r.equipment_id = e.id WHERE r.id = ?");
        $stmt->bind_param('i', $rental_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Rental not found');
        }
        
        $rental = $result->fetch_assoc();
        
        if ($rental['status'] != RENTAL_PENDING) {
            throw new Exception('Only pending rentals can be rejected');
        }
        
        // Update rental status to rejected
        $stmt = $conn->prepare("UPDATE rentals SET status = ? WHERE id = ?");
        $stmt->bind_param('ii', $rejected_status = RENTAL_REJECTED, $rental_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update rental status');
        }
        
        // Create provenance record
        createProvenanceRecord($conn, $rental['equipment_id'], $rejecter_id, 'rental_rejected', $rental_id, 
            json_encode([
                'old_status' => RENTAL_PENDING,
                'new_status' => RENTAL_REJECTED,
                'rejecter_id' => $rejecter_id,
                'reason' => $reason
            ]));
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Rental rejection failed: " . $e->getMessage());
        return false;
    } finally {
        $conn->autocommit(true);
    }
}

/**
 * Complete rental with full provenance tracking
 */
function completeRental($conn, $rental_id, $completer_id, $ip_address = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    $conn->autocommit(false);
    
    try {
        // Get rental details
        $stmt = $conn->prepare("SELECT r.*, e.id as equipment_id FROM rentals r JOIN equipment e ON r.equipment_id = e.id WHERE r.id = ?");
        $stmt->bind_param('i', $rental_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Rental not found');
        }
        
        $rental = $result->fetch_assoc();
        
        if ($rental['status'] != RENTAL_APPROVED) {
            throw new Exception('Only approved rentals can be completed');
        }
        
        // Update rental status to completed
        $stmt = $conn->prepare("UPDATE rentals SET status = ? WHERE id = ?");
        $stmt->bind_param('ii', $completed_status = RENTAL_COMPLETED, $rental_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update rental status');
        }
        
        // Update equipment status to available
        $stmt = $conn->prepare("UPDATE equipment SET status = ? WHERE id = ?");
        $stmt->bind_param('ii', $available_status = STATUS_AVAILABLE, $rental['equipment_id']);
        $stmt->execute();
        
        // Create provenance record
        createProvenanceRecord($conn, $rental['equipment_id'], $completer_id, 'rental_completed', $rental_id, 
            json_encode([
                'old_status' => RENTAL_APPROVED,
                'new_status' => RENTAL_COMPLETED,
                'completer_id' => $completer_id,
                'completion_date' => date('Y-m-d H:i:s')
            ]));
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Rental completion failed: " . $e->getMessage());
        return false;
    } finally {
        $conn->autocommit(true);
    }
}

// =====================================================
// ENHANCED USER MANAGEMENT WITH PROVENANCE  
// =====================================================

/**
 * Promote user to admin with provenance tracking
 */
function promoteUser($conn, $user_id, $promoter_id, $reason = null, $ip_address = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    // Set audit context
    setAuditContext($conn, $promoter_id, $ip_address, $reason);
    
    $stmt = $conn->prepare("UPDATE users SET user_type = 2 WHERE id = ? AND user_type = 1");
    $stmt->bind_param("i", $user_id);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

/**
 * Demote user from admin with provenance tracking
 */
function demoteUser($conn, $user_id, $demoter_id, $reason = null, $ip_address = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    // Set audit context
    setAuditContext($conn, $demoter_id, $ip_address, $reason);
    
    $stmt = $conn->prepare("UPDATE users SET user_type = 1 WHERE id = ? AND user_type = 2");
    $stmt->bind_param("i", $user_id);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

// =====================================================
// ENHANCED EQUIPMENT MANAGEMENT WITH PROVENANCE
// =====================================================

/**
 * Update equipment price with provenance tracking
 */
function updateEquipmentPrice($conn, $equipment_id, $new_price, $changer_id, $reason = null, $ip_address = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    // Set audit context
    setAuditContext($conn, $changer_id, $ip_address, $reason);
    
    $stmt = $conn->prepare("UPDATE equipment SET daily_rate = ? WHERE id = ?");
    $stmt->bind_param("di", $new_price, $equipment_id);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

/**
 * Set audit context for manual operations
 */
function setAuditContext($conn, $user_id, $ip_address = null, $reason = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    $conn->query("SET @current_user_id = " . intval($user_id));
    $conn->query("SET @current_ip = '" . $conn->real_escape_string($ip_address) . "'");
    $conn->query("SET @current_session_id = '" . $conn->real_escape_string(session_id()) . "'");
    
    if ($reason) {
        $conn->query("SET @change_reason = '" . $conn->real_escape_string($reason) . "'");
    }
}

/**
 * Enhanced createRental function with full provenance
 */
function createRentalWithProvenance($conn, $equipment_id, $user_id, $start_date, $end_date, $purpose) {
    // Set audit context
    setAuditContext($conn, $user_id);
    
    // Get equipment details for total amount calculation
    $equipment_sql = "SELECT daily_rate FROM equipment WHERE id = ?";
    $equipment_stmt = $conn->prepare($equipment_sql);
    $equipment_stmt->bind_param("i", $equipment_id);
    $equipment_stmt->execute();
    $equipment_result = $equipment_stmt->get_result();
    
    if ($equipment_result->num_rows === 0) {
        return false;
    }
    
    $equipment = $equipment_result->fetch_assoc();
    
    // Calculate total amount
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = $start->diff($end)->days;
    $total_amount = $days * $equipment['daily_rate'];
    
    // Insert rental - triggers will handle provenance automatically
    $status = RENTAL_PENDING;
    $sql = "INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssid", $equipment_id, $user_id, $start_date, $end_date, $purpose, $status, $total_amount);
    
    if ($stmt->execute()) {
        return $stmt->insert_id ?: $conn->insert_id;
    }
    
    return false;
}

?>