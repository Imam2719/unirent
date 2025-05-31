<?php
/**
 * Enhanced Data Provenance Functions
 * These functions help track the history and lineage of equipment and rental data
 */

/**
 * Create a provenance record
 * 
 * @param mysqli $conn Database connection
 * @param int $equipment_id Equipment ID
 * @param int $user_id User ID
 * @param string $action Action performed (created, updated, rental_created, etc.)
 * @param int|null $reference_id Reference ID (e.g., rental ID)
 * @param array|null $details Additional details about the action
 * @return bool Success or failure
 */
function createProvenanceRecord($conn, $equipment_id, $user_id, $action, $reference_id = null, $details = null) {
    $sql = "INSERT INTO data_provenance (equipment_id, user_id, action, reference_id, details, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $details_json = $details ? json_encode($details) : null;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisis", $equipment_id, $user_id, $action, $reference_id, $details_json);
    
    return $stmt->execute();
}

/**
 * Get equipment provenance history
 * 
 * @param mysqli $conn Database connection
 * @param int $equipment_id Equipment ID
 * @return array Provenance records
 */
function getEquipmentProvenance($conn, $equipment_id) {
    $provenance = [];
    $sql = "SELECT dp.*, u.first_name, u.last_name, u.user_type
            FROM data_provenance dp
            JOIN users u ON dp.user_id = u.id
            WHERE dp.equipment_id = ?
            ORDER BY dp.timestamp DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Parse JSON details if available
            if ($row['details']) {
                $row['details'] = json_decode($row['details'], true);
            }
            $provenance[] = $row;
        }
    }
    
    return $provenance;
}

/**
 * Get rental provenance history
 * 
 * @param mysqli $conn Database connection
 * @param int $rental_id Rental ID
 * @return array Provenance records
 */
function getRentalProvenance($conn, $rental_id) {
    $provenance = [];
    $sql = "SELECT dp.*, u.first_name, u.last_name, u.user_type, e.name as equipment_name
            FROM data_provenance dp
            JOIN users u ON dp.user_id = u.id
            JOIN equipment e ON dp.equipment_id = e.id
            WHERE dp.reference_id = ? AND dp.action LIKE 'rental_%'
            ORDER BY dp.timestamp DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rental_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Parse JSON details if available
            if ($row['details']) {
                $row['details'] = json_decode($row['details'], true);
            }
            $provenance[] = $row;
        }
    }
    
    return $provenance;
}

/**
 * Format provenance action for display
 * 
 * @param string $action Action name
 * @return string Formatted action name
 */
function formatProvenanceAction($action) {
    switch($action) {
        case 'created':
            return 'Equipment Added';
        case 'updated':
            return 'Equipment Updated';
        case 'rental_created':
            return 'Rental Requested';
        case 'rental_approved':
            return 'Rental Approved';
        case 'rental_rejected':
            return 'Rental Rejected';
        case 'rental_completed':
            return 'Rental Completed';
        case 'rental_cancelled':
            return 'Rental Cancelled';
        case 'status_changed':
            return 'Status Changed';
        default:
            return ucfirst(str_replace('_', ' ', $action));
    }
}
?>