<?php
// Start session and check admin access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get rental ID from URL
$rental_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get rental details with enhanced equipment and user information
$rental = null;
$stmt = $conn->prepare("
    SELECT r.*, 
           u.first_name, u.last_name, u.email, u.student_id, u.phone as renter_phone,
           e.name as equipment_name, e.description as equipment_description, 
           e.daily_rate, e.image as equipment_image, e.owner_id, e.status as equipment_status,
           c.name as category_name, c.icon as category_icon,
           o.first_name as owner_first_name, o.last_name as owner_last_name, 
           o.email as owner_email, o.phone as owner_phone
    FROM rentals r
    JOIN users u ON r.user_id= u.id
    JOIN equipment e ON r.equipment_id = e.id
    JOIN categories c ON e.category_id = c.id
    LEFT JOIN users o ON e.owner_id = o.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $rental_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: manage-rentals.php');
    exit;
}

$rental = $result->fetch_assoc();

// Calculate rental duration and total cost
$start_date = new DateTime($rental['start_date']);
$end_date = new DateTime($rental['end_date']);
$duration = $start_date->diff($end_date)->days;
$total_cost = $duration * $rental['daily_rate'];

// Get rental history with enhanced provenance tracking
$history = [];
$stmt = $conn->prepare("
    SELECT dp.*, u.first_name, u.last_name, u.email as user_email
    FROM data_provenance dp
    JOIN users u ON dp.user_id = u.id
    WHERE dp.reference_id = ? AND (dp.action LIKE 'rental_%' OR dp.action LIKE 'equipment_status_%')
    ORDER BY dp.timestamp DESC
    LIMIT 15
");
$stmt->bind_param("i", $rental_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

// Get related equipment provenance (for status changes, etc.)
$equipment_provenance = [];
$stmt = $conn->prepare("
    SELECT dp.*, u.first_name, u.last_name
    FROM data_provenance dp
    JOIN users u ON dp.user_id = u.id
    WHERE dp.equipment_id = ? AND dp.action NOT LIKE 'rental_%'
    ORDER BY dp.timestamp DESC
    LIMIT 5
");
$stmt->bind_param("i", $rental['equipment_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $equipment_provenance[] = $row;
}

// Include shared layout
include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Rental Details <small class="text-muted">#<?php echo $rental_id; ?></small></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="manage-rentals.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Rentals
                </a>
                <a href="print-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-print"></i> Print
                </a>
                <?php if ($rental['status'] == RENTAL_PENDING): ?>
                    <a href="approve-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-check"></i> Approve
                    </a>
                    <a href="reject-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-sm btn-danger">
                        <i class="fas fa-times"></i> Reject
                    </a>
                <?php elseif ($rental['status'] == RENTAL_APPROVED): ?>
                    <a href="complete-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-check-double"></i> Complete
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Rental Summary Card -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Rental Summary</h5>
                    <span class="badge bg-<?php 
                        echo $rental['status'] == RENTAL_PENDING ? 'warning' : 
                             ($rental['status'] == RENTAL_APPROVED ? 'success' : 
                             ($rental['status'] == RENTAL_REJECTED ? 'danger' : 
                             ($rental['status'] == RENTAL_COMPLETED ? 'info' : 'secondary'))); 
                    ?>">
                        <?php echo formatProvenanceAction('rental_' . strtolower($rental['status'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="fw-bold">Rental Period</h6>
                                <p>
                                    <?php echo date('M d, Y', strtotime($rental['start_date'])); ?> to 
                                    <?php echo date('M d, Y', strtotime($rental['end_date'])); ?>
                                    <small class="text-muted">(<?php echo $duration; ?> days)</small>
                                </p>
                            </div>
                            <div class="mb-3">
                                <h6 class="fw-bold">Total Cost</h6>
                                <p>$<?php echo number_format($total_cost, 2); ?> 
                                    <small class="text-muted">($<?php echo number_format($rental['daily_rate'], 2); ?>/day)</small>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="fw-bold">Created</h6>
                                <p><?php echo date('M d, Y h:i A', strtotime($rental['created_at'])); ?></p>
                            </div>
                            <div class="mb-3">
                                <h6 class="fw-bold">Last Updated</h6>
                                <p><?php echo date('M d, Y h:i A', strtotime($rental['updated_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Purpose</h6>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($rental['purpose'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Information Cards -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Renter Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-user-circle fa-3x text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5><?php echo htmlspecialchars($rental['first_name'] . ' ' . $rental['last_name']); ?></h5>
                                    <p class="mb-1"><?php echo htmlspecialchars($rental['email']); ?></p>
                                    <?php if (!empty($rental['student_id'])): ?>
                                        <p class="mb-1"><small class="text-muted">ID: <?php echo htmlspecialchars($rental['student_id']); ?></small></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($rental['renter_phone'])): ?>
                                <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($rental['renter_phone']); ?></p>
                            <?php endif; ?>
                            <a href="view-user.php?id=<?php echo $rental['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                View Full Profile
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Equipment Owner</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($rental['owner_id'])): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-tie fa-3x text-secondary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h5><?php echo htmlspecialchars($rental['owner_first_name'] . ' ' . $rental['owner_last_name']); ?></h5>
                                        <p class="mb-1"><?php echo htmlspecialchars($rental['owner_email']); ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($rental['owner_phone'])): ?>
                                    <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($rental['owner_phone']); ?></p>
                                <?php endif; ?>
                                <a href="view-user.php?id=<?php echo $rental['owner_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View Owner Profile
                                </a>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-university fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">University-owned equipment</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#rentalActivity">Rental Activity</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#equipmentActivity">Equipment History</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="rentalActivity">
                            <?php if (!empty($history)): ?>
                                <div class="timeline">
                                    <?php foreach ($history as $event): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-<?php 
                                                echo strpos($event['action'], 'approved') !== false ? 'success' : 
                                                     (strpos($event['action'], 'rejected') !== false ? 'danger' : 
                                                     (strpos($event['action'], 'completed') !== false ? 'info' : 'primary')); 
                                            ?>"></div>
                                            <div class="timeline-content">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="timeline-title mb-1">
                                                        <?php echo formatProvenanceAction($event['action']); ?>
                                                    </h6>
                                                    <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($event['timestamp'])); ?></small>
                                                </div>
                                                <p class="timeline-user mb-1">
                                                    <small>By <?php echo htmlspecialchars($event['first_name'] . ' ' . $event['last_name']); ?></small>
                                                </p>
                                                <?php if (!empty($event['details'])): ?>
                                                    <?php $details = json_decode($event['details'], true); ?>
                                                    <div class="timeline-details bg-light p-2 rounded">
                                                        <?php if (json_last_error() === JSON_ERROR_NONE && is_array($details)): ?>
                                                            <ul class="list-unstyled mb-0">
                                                            <?php foreach ($details as $key => $value): ?>
                                                                <li>
                                                                    <strong><?php echo htmlspecialchars($key); ?>:</strong> 
                                                                    <?php if (is_array($value)): ?>
                                                                        <?php echo htmlspecialchars(json_encode($value)); ?>
                                                                    <?php else: ?>
                                                                        <?php echo htmlspecialchars($value); ?>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['details'])); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <a href="provenance.php?entity_type=rental&entity_id=<?php echo $rental_id; ?>" class="btn btn-sm btn-outline-primary mt-3">
                                    View Full Activity Log
                                </a>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No activity recorded for this rental</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tab-pane fade" id="equipmentActivity">
                            <?php if (!empty($equipment_provenance)): ?>
                                <div class="timeline">
                                    <?php foreach ($equipment_provenance as $event): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-secondary"></div>
                                            <div class="timeline-content">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="timeline-title mb-1">
                                                        <?php echo formatProvenanceAction($event['action']); ?>
                                                    </h6>
                                                    <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($event['timestamp'])); ?></small>
                                                </div>
                                                <p class="timeline-user mb-1">
                                                    <small>By <?php echo htmlspecialchars($event['first_name'] . ' ' . $event['last_name']); ?></small>
                                                </p>
                                                <?php if (!empty($event['details'])): ?>
                                                    <?php $details = json_decode($event['details'], true); ?>
                                                    <div class="timeline-details bg-light p-2 rounded">
                                                        <?php if (json_last_error() === JSON_ERROR_NONE && is_array($details)): ?>
                                                            <ul class="list-unstyled mb-0">
                                                            <?php foreach ($details as $key => $value): ?>
                                                                <li>
                                                                    <strong><?php echo htmlspecialchars($key); ?>:</strong> 
                                                                    <?php if (is_array($value)): ?>
                                                                        <?php echo htmlspecialchars(json_encode($value)); ?>
                                                                    <?php else: ?>
                                                                        <?php echo htmlspecialchars($value); ?>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['details'])); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <a href="provenance.php?entity_type=equipment&entity_id=<?php echo $rental['equipment_id']; ?>" class="btn btn-sm btn-outline-primary mt-3">
                                    View Full Equipment History
                                </a>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No recent equipment activity found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Equipment Information Card -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Equipment Details</h5>
                    <span class="badge bg-<?php echo getStatusBadgeClass($rental['equipment_status']); ?>">
                        <?php echo getStatusText($rental['equipment_status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($rental['equipment_image']) && file_exists('../' . $rental['equipment_image'])): ?>
                            <img src="../<?php echo $rental['equipment_image']; ?>" class="img-fluid rounded" style="max-height: 180px;" alt="<?php echo htmlspecialchars($rental['equipment_name']); ?>">
                        <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 180px;">
                                <i class="fas fa-<?php echo !empty($rental['category_icon']) ? $rental['category_icon'] : 'box'; ?> fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h5 class="mb-3"><?php echo htmlspecialchars($rental['equipment_name']); ?></h5>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Category</h6>
                        <p>
                            <i class="fas fa-<?php echo !empty($rental['category_icon']) ? $rental['category_icon'] : 'tag'; ?> me-2 text-muted"></i>
                            <?php echo htmlspecialchars($rental['category_name']); ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Daily Rate</h6>
                        <p>$<?php echo number_format($rental['daily_rate'], 2); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Description</h6>
                        <div class="bg-light p-2 rounded">
                            <?php echo nl2br(htmlspecialchars($rental['equipment_description'])); ?>
                        </div>
                    </div>
                    
                    <a href="view-equipment.php?id=<?php echo $rental['equipment_id']; ?>" class="btn btn-primary w-100">
                        <i class="fas fa-info-circle me-2"></i> View Equipment Details
                    </a>
                </div>
            </div>
            
            <!-- Rental Actions Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Rental Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($rental['status'] == RENTAL_PENDING): ?>
                            <a href="approve-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-success">
                                <i class="fas fa-check me-2"></i> Approve Rental
                            </a>
                            <a href="reject-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-danger">
                                <i class="fas fa-times me-2"></i> Reject Rental
                            </a>
                        <?php elseif ($rental['status'] == RENTAL_APPROVED): ?>
                            <a href="complete-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-info">
                                <i class="fas fa-check-double me-2"></i> Mark as Completed
                            </a>
                        <?php endif; ?>
                        
                        <a href="print-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-2"></i> Print Rental Agreement
                        </a>
                        
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="fas fa-ban me-2"></i> Cancel Rental
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Rental Documents Card -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Documents</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-file-contract me-2 text-primary"></i>
                                Rental Agreement
                            </span>
                            <a href="#" class="btn btn-sm btn-outline-primary">Download</a>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-receipt me-2 text-success"></i>
                                Invoice
                            </span>
                            <a href="#" class="btn btn-sm btn-outline-success">Generate</a>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-check-circle me-2 text-info"></i>
                                Condition Report
                            </span>
                            <a href="#" class="btn btn-sm btn-outline-info">View</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Rental Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="cancelModalLabel">Cancel Rental</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="cancel-rental.php">
                <div class="modal-body">
                    <input type="hidden" name="id" value="<?php echo $rental_id; ?>">
                    <p>Are you sure you want to cancel this rental?</p>
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Reason for cancellation</label>
                        <textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning text-white">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }
    .timeline-marker {
        position: absolute;
        left: -30px;
        top: 0;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: #0d6efd;
        border: 3px solid white;
    }
    .timeline-content {
        padding-bottom: 15px;
    }
    .timeline-title {
        font-size: 0.95rem;
        font-weight: 600;
    }
    .timeline-user {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .timeline-details {
        font-size: 0.85rem;
        margin-top: 5px;
    }
    .nav-tabs .nav-link {
        border: none;
        color: #495057;
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom: 3px solid #0d6efd;
        background: transparent;
    }
    .card-header {
        font-weight: 600;
    }
</style>

<script>
// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Tab persistence
    const rentalTab = localStorage.getItem('rentalTab');
    if (rentalTab) {
        const tab = new bootstrap.Tab(document.querySelector(rentalTab));
        tab.show();
    }
    
    document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => {
        tab.addEventListener('click', function() {
            localStorage.setItem('rentalTab', this.getAttribute('data-bs-target'));
        });
    });
});
</script>

<?php include 'admin-footer.php'; ?>