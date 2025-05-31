
<?php
// =====================================================
// FILE: fixed-query-how-rental-lifecycle.php
// Purpose: HOW Provenance - Rental Lifecycle Tracing (FIXED)
// =====================================================

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Handle form submission
$rental_id = $_GET['rental_id'] ?? '';
$date_range = $_GET['date_range'] ?? '30';

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2">
                <span class="badge bg-primary me-2">HOW</span>
                Rental Lifecycle Analysis
            </h1>
            <p class="text-muted">Trace the complete transformation history of rental records</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Rental ID</label>
                    <select name="rental_id" class="form-select">
                        <option value="">All Rentals</option>
                        <?php
                        $rentals = $conn->query("SELECT r.id, e.name, CONCAT(u.first_name, ' ', u.last_name) as user_name 
                                                FROM rentals r 
                                                JOIN equipment e ON r.equipment_id = e.id 
                                                JOIN users u ON r.user_id = u.id 
                                                ORDER BY r.id DESC");
                        if ($rentals) {
                            while ($row = $rentals->fetch_assoc()) {
                                $selected = ($rental_id == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $selected>#{$row['id']} - {$row['name']} ({$row['user_name']})</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date Range</label>
                    <select name="date_range" class="form-select">
                        <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 days</option>
                        <option value="all" <?php echo $date_range == 'all' ? 'selected' : ''; ?>>All time</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Trace Lifecycle
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-header">
            <h5>Rental Lifecycle Analysis Results</h5>
        </div>
        <div class="card-body">
            <?php
            // Check if audit_rentals table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'audit_rentals'");
            
            if ($table_check->num_rows == 0) {
                echo '<div class="alert alert-danger">';
                echo '<h6>Audit Table Missing</h6>';
                echo 'The <code>audit_rentals</code> table does not exist. Please run the database setup script first.';
                echo '</div>';
            } else {
                // Execute the HOW provenance query
                $query = "
                    SELECT 
                        ar.rental_id,
                        e.name AS equipment_name,
                        CONCAT(u.first_name, ' ', u.last_name) AS renter_name,
                        CASE COALESCE(ar.old_status, 0)
                            WHEN 1 THEN 'Pending'
                            WHEN 2 THEN 'Approved' 
                            WHEN 3 THEN 'Rejected'
                            WHEN 4 THEN 'Completed'
                            WHEN 5 THEN 'Cancelled'
                            WHEN 0 THEN 'New'
                            ELSE 'Unknown'
                        END AS previous_status,
                        CASE ar.new_status
                            WHEN 1 THEN 'Pending'
                            WHEN 2 THEN 'Approved'
                            WHEN 3 THEN 'Rejected' 
                            WHEN 4 THEN 'Completed'
                            WHEN 5 THEN 'Cancelled'
                            ELSE 'Unknown'
                        END AS new_status,
                        ar.operation_type,
                        ar.change_timestamp,
                        CONCAT(changer.first_name, ' ', changer.last_name) AS changed_by,
                        COALESCE(ar.approval_reason, ar.rejection_reason, 'System change') AS reason
                    FROM audit_rentals ar
                    LEFT JOIN equipment e ON COALESCE(ar.new_equipment_id, ar.old_equipment_id) = e.id
                    LEFT JOIN users u ON COALESCE(ar.new_user_id, ar.old_user_id) = u.id
                    LEFT JOIN users changer ON ar.changed_by = changer.id
                    WHERE 1=1
                ";
                
                if (!empty($rental_id)) {
                    $query .= " AND ar.rental_id = " . intval($rental_id);
                }
                
                if ($date_range !== 'all') {
                    $query .= " AND ar.change_timestamp >= DATE_SUB(NOW(), INTERVAL " . intval($date_range) . " DAY)";
                }
                
                $query .= " ORDER BY ar.rental_id, ar.change_timestamp ASC";
                
                $result = $conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-striped">';
                    echo '<thead><tr>';
                    echo '<th>Rental ID</th><th>Equipment</th><th>Renter</th>';
                    echo '<th>Previous Status</th><th>New Status</th><th>Operation</th>';
                    echo '<th>Timestamp</th><th>Changed By</th><th>Reason</th>';
                    echo '</tr></thead><tbody>';
                    
                    $current_rental = null;
                    while ($row = $result->fetch_assoc()) {
                        // Add divider between different rentals
                        if ($current_rental !== null && $current_rental != $row['rental_id']) {
                            echo '<tr class="table-secondary"><td colspan="9"><strong>--- End of Rental #' . $current_rental . ' ---</strong></td></tr>';
                        }
                        $current_rental = $row['rental_id'];
                        
                        echo '<tr>';
                        echo '<td><strong>#' . $row['rental_id'] . '</strong></td>';
                        echo '<td>' . htmlspecialchars($row['equipment_name'] ?? 'Unknown') . '</td>';
                        echo '<td>' . htmlspecialchars($row['renter_name'] ?? 'Unknown') . '</td>';
                        echo '<td><span class="badge bg-secondary">' . $row['previous_status'] . '</span></td>';
                        echo '<td><span class="badge bg-primary">' . $row['new_status'] . '</span></td>';
                        echo '<td>' . $row['operation_type'] . '</td>';
                        echo '<td>' . date('M d, Y H:i:s', strtotime($row['change_timestamp'])) . '</td>';
                        echo '<td>' . htmlspecialchars($row['changed_by'] ?? 'System') . '</td>';
                        echo '<td>' . htmlspecialchars($row['reason']) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table></div>';
                } else {
                    echo '<div class="alert alert-info">';
                    echo '<i class="fas fa-info-circle me-2"></i>';
                    echo 'No rental lifecycle data found. To generate data:';
                    echo '<ol>';
                    echo '<li>Go to <a href="manage-rentals.php">Manage Rentals</a></li>';
                    echo '<li>Approve, reject, or complete some rentals</li>';
                    echo '<li>The changes will be tracked automatically</li>';
                    echo '</ol>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>
