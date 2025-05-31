<?php
// Enhanced manage-rentals.php with query logging
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/functions.php';
// Add query logging support
require_once __DIR__ . '/includes/provenance/activity_logger.php';
require_once __DIR__ . '/includes/provenance/query_logger.php';

// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Log admin access
log_activity('rental_management', 'Admin accessed rental management page');

$success = '';
$error = '';

// Handle rental actions with proper logging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rental_id = intval($_POST['rental_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($rental_id > 0 && !empty($action)) {
        // Get rental details first using logged query
        $stmt = prepare_logged($conn, "SELECT r.*, e.id as equipment_id, e.name as equipment_name FROM rentals r JOIN equipment e ON r.equipment_id = e.id WHERE r.id = ?", 'SELECT', 'rentals');
        $stmt->bind_param('i', $rental_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $rental = $result->fetch_assoc();
            $admin_id = $_SESSION['user_id'];
            
            switch ($action) {
                case 'approve':
                    if ($rental['status'] == 1) { // RENTAL_PENDING
                        // Update rental status using logged query
                        $update_stmt = prepare_logged($conn, "UPDATE rentals SET status = 2, updated_at = NOW() WHERE id = ?", 'UPDATE', 'rentals');
                        $update_stmt->bind_param('i', $rental_id);
                        
                        if ($update_stmt->execute()) {
                            // Update equipment status using logged query
                            $equip_stmt = prepare_logged($conn, "UPDATE equipment SET status = 2, updated_at = NOW() WHERE id = ?", 'UPDATE', 'equipment');
                            $equip_stmt->bind_param('i', $rental['equipment_id']);
                            $equip_stmt->execute();
                            
                            // Log the activity
                            log_activity('approve_rental', "Approved rental ID $rental_id for equipment: {$rental['equipment_name']}");
                            
                            // Track provenance if function exists
                            if (function_exists('trackProvenance')) {
                                $details = json_encode([
                                    'old_status' => 1, // RENTAL_PENDING
                                    'new_status' => 2, // RENTAL_APPROVED
                                    'approver_id' => $admin_id,
                                    'equipment_name' => $rental['equipment_name']
                                ]);
                                trackProvenance($conn, 'rental', $rental_id, 'rental_approved', $rental_id, $details);
                            }
                            
                            $success = "Rental #{$rental_id} for {$rental['equipment_name']} has been approved successfully.";
                            $equip_stmt->close();
                        } else {
                            $error = "Failed to approve rental.";
                            log_activity('rental_approval_failed', "Failed to approve rental ID $rental_id");
                        }
                        $update_stmt->close();
                    } else {
                        $error = "Only pending rentals can be approved.";
                    }
                    break;
                    
                case 'reject':
                    if ($rental['status'] == 1) { // RENTAL_PENDING
                        // Update rental status to rejected using logged query
                        $update_stmt = prepare_logged($conn, "UPDATE rentals SET status = 3, updated_at = NOW() WHERE id = ?", 'UPDATE', 'rentals');
                        $update_stmt->bind_param('i', $rental_id);
                        
                        if ($update_stmt->execute()) {
                            // Log the activity
                            log_activity('reject_rental', "Rejected rental ID $rental_id for equipment: {$rental['equipment_name']}");
                            
                            // Track provenance if function exists
                            if (function_exists('trackProvenance')) {
                                $details = json_encode([
                                    'old_status' => 1, // RENTAL_PENDING
                                    'new_status' => 3, // RENTAL_REJECTED
                                    'rejector_id' => $admin_id,
                                    'equipment_name' => $rental['equipment_name'],
                                    'reason' => $_POST['rejection_reason'] ?? 'No reason provided'
                                ]);
                                trackProvenance($conn, 'rental', $rental_id, 'rental_rejected', $rental_id, $details);
                            }
                            
                            $success = "Rental #{$rental_id} for {$rental['equipment_name']} has been rejected.";
                        } else {
                            $error = "Failed to reject rental.";
                            log_activity('rental_rejection_failed', "Failed to reject rental ID $rental_id");
                        }
                        $update_stmt->close();
                    } else {
                        $error = "Only pending rentals can be rejected.";
                    }
                    break;
                    
                case 'complete':
                    if ($rental['status'] == 2) { // RENTAL_APPROVED
                        // Update rental status to completed using logged query
                        $update_stmt = prepare_logged($conn, "UPDATE rentals SET status = 4, updated_at = NOW() WHERE id = ?", 'UPDATE', 'rentals');
                        $update_stmt->bind_param('i', $rental_id);
                        
                        if ($update_stmt->execute()) {
                            // Update equipment status back to available using logged query
                            $equip_stmt = prepare_logged($conn, "UPDATE equipment SET status = 1, updated_at = NOW() WHERE id = ?", 'UPDATE', 'equipment');
                            $equip_stmt->bind_param('i', $rental['equipment_id']);
                            $equip_stmt->execute();
                            
                            // Log the activity
                            log_activity('complete_rental', "Completed rental ID $rental_id for equipment: {$rental['equipment_name']}");
                            
                            // Track provenance if function exists
                            if (function_exists('trackProvenance')) {
                                $details = json_encode([
                                    'old_status' => 2, // RENTAL_APPROVED
                                    'new_status' => 4, // RENTAL_COMPLETED
                                    'completer_id' => $admin_id,
                                    'equipment_name' => $rental['equipment_name']
                                ]);
                                trackProvenance($conn, 'rental', $rental_id, 'rental_completed', $rental_id, $details);
                            }
                            
                            $success = "Rental #{$rental_id} for {$rental['equipment_name']} has been completed.";
                            $equip_stmt->close();
                        } else {
                            $error = "Failed to complete rental.";
                        }
                        $update_stmt->close();
                    } else {
                        $error = "Only approved rentals can be completed.";
                    }
                    break;
                    
                case 'cancel':
                    if (in_array($rental['status'], [1, 2])) { // PENDING or APPROVED
                        // Update rental status to cancelled using logged query
                        $update_stmt = prepare_logged($conn, "UPDATE rentals SET status = 5, updated_at = NOW() WHERE id = ?", 'UPDATE', 'rentals');
                        $update_stmt->bind_param('i', $rental_id);
                        
                        if ($update_stmt->execute()) {
                            // If it was approved, make equipment available again
                            if ($rental['status'] == 2) {
                                $equip_stmt = prepare_logged($conn, "UPDATE equipment SET status = 1, updated_at = NOW() WHERE id = ?", 'UPDATE', 'equipment');
                                $equip_stmt->bind_param('i', $rental['equipment_id']);
                                $equip_stmt->execute();
                                $equip_stmt->close();
                            }
                            
                            // Log the activity
                            log_activity('cancel_rental', "Cancelled rental ID $rental_id for equipment: {$rental['equipment_name']}");
                            
                            // Track provenance if function exists
                            if (function_exists('trackProvenance')) {
                                $details = json_encode([
                                    'old_status' => $rental['status'],
                                    'new_status' => 5, // RENTAL_CANCELLED
                                    'canceller_id' => $admin_id,
                                    'equipment_name' => $rental['equipment_name']
                                ]);
                                trackProvenance($conn, 'rental', $rental_id, 'rental_cancelled', $rental_id, $details);
                            }
                            
                            $success = "Rental #{$rental_id} for {$rental['equipment_name']} has been cancelled.";
                        } else {
                            $error = "Failed to cancel rental.";
                        }
                        $update_stmt->close();
                    } else {
                        $error = "Only pending or approved rentals can be cancelled.";
                    }
                    break;
            }
        } else {
            $error = "Rental not found.";
            log_activity('rental_not_found', "Rental ID $rental_id not found during action: $action");
        }
        $stmt->close();
    } else {
        $error = "Invalid request.";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with logging
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
    $types .= 'i';
}

if ($date_filter) {
    $where_conditions[] = "DATE(r.created_at) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(e.name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT r.*, 
           e.name as equipment_name, e.daily_rate,
           CONCAT(u.first_name, ' ', u.last_name) as renter_name,
           u.email as renter_email,
           CONCAT(owner.first_name, ' ', owner.last_name) as owner_name,
           DATEDIFF(r.end_date, r.start_date) as rental_days
    FROM rentals r
    JOIN equipment e ON r.equipment_id = e.id
    JOIN users u ON r.user_id = u.id
    JOIN users owner ON e.owner_id = owner.id
    $where_clause
    ORDER BY r.created_at DESC
";

// Use logged query for the main data retrieval
if ($params) {
    $stmt = prepare_logged($conn, $query, 'SELECT', 'rentals');
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // For queries without parameters, use the log_query function
    $result = log_query($query, 'SELECT', 'rentals');
}

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-calendar-check me-2"></i>
            Rental Management
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="rental-report.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="debug-logging.php" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-bug"></i> Debug Logs
                </a>
                <a href="provenance-queries.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-database"></i> Query Logs
                </a>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Rental Statistics Cards -->
    <div class="row mb-4">
        <?php
        // Get rental statistics using logged query
        $stats_query = "
            SELECT 
                COUNT(*) as total_rentals,
                COUNT(CASE WHEN status = 1 THEN 1 END) as pending,
                COUNT(CASE WHEN status = 2 THEN 1 END) as approved,
                COUNT(CASE WHEN status = 3 THEN 1 END) as rejected,
                COUNT(CASE WHEN status = 4 THEN 1 END) as completed,
                COUNT(CASE WHEN status = 5 THEN 1 END) as cancelled
            FROM rentals
        ";
        $stats_result = log_query($stats_query, 'SELECT', 'rentals');
        $stats = $stats_result->fetch_assoc();
        ?>
        
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['total_rentals']); ?></h4>
                    <small>Total Rentals</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['pending']); ?></h4>
                    <small>Pending</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['approved']); ?></h4>
                    <small>Approved</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['rejected']); ?></h4>
                    <small>Rejected</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['completed']); ?></h4>
                    <small>Completed</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['cancelled']); ?></h4>
                    <small>Cancelled</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="1" <?php echo $status_filter == '1' ? 'selected' : ''; ?>>Pending</option>
                        <option value="2" <?php echo $status_filter == '2' ? 'selected' : ''; ?>>Approved</option>
                        <option value="3" <?php echo $status_filter == '3' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="4" <?php echo $status_filter == '4' ? 'selected' : ''; ?>>Completed</option>
                        <option value="5" <?php echo $status_filter == '5' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Equipment or user name" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="manage-rentals.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Rentals Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Equipment</th>
                            <th>Renter</th>
                            <th>Owner</th>
                            <th>Period</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($rental = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $rental['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($rental['equipment_name']); ?></strong>
                                    <br><small class="text-muted">$<?php echo number_format($rental['daily_rate'], 2); ?>/day</small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($rental['renter_name']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($rental['renter_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($rental['owner_name']); ?></td>
                                <td>
                                    <?php echo date('M d', strtotime($rental['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($rental['end_date'])); ?>
                                    <br><small class="text-muted"><?php echo $rental['rental_days']; ?> days</small>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($rental['total_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch($rental['status']) {
                                        case 1: $status_class = 'warning'; $status_text = 'Pending'; break;
                                        case 2: $status_class = 'success'; $status_text = 'Approved'; break;
                                        case 3: $status_class = 'danger'; $status_text = 'Rejected'; break;
                                        case 4: $status_class = 'info'; $status_text = 'Completed'; break;
                                        case 5: $status_class = 'secondary'; $status_text = 'Cancelled'; break;
                                        default: $status_class = 'secondary'; $status_text = 'Unknown';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($rental['created_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($rental['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm">
                                        <?php if ($rental['status'] == 1): // Pending ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this rental?')">
                                                <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this rental?')">
                                                <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                            
                                        <?php elseif ($rental['status'] == 2): // Approved ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to complete this rental?')">
                                                <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn btn-info btn-sm">
                                                    <i class="fas fa-check-double"></i> Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($rental['status'], [1, 2])): // Pending or Approved ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this rental?')">
                                                <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-ban"></i> Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="view-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.btn-group-vertical .btn {
    margin-bottom: 2px;
}
.btn-group-vertical form {
    margin-bottom: 2px;
}
</style>

<script>
// Log page load for debugging
console.log('Manage Rentals page loaded with query logging enabled');
</script>

<?php include 'admin-footer.php'; ?>