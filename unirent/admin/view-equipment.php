<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$equipment_id = $_GET['id'] ?? 0;

// Check database connection
if (!$conn) {
    die("Database connection failed: " . $conn->connect_error);
}

// Main equipment query
$query = "
    SELECT e.*, 
           c.name AS category_name,
           u.first_name AS owner_first_name, 
           u.last_name AS owner_last_name,
           u.email AS owner_email,
           (SELECT COUNT(*) FROM rentals WHERE equipment_id = e.id) AS rental_count
    FROM equipment e
    LEFT JOIN categories c ON e.category_id = c.id
    LEFT JOIN users u ON e.owner_id = u.id
    WHERE e.id = ?
";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing equipment query: " . $conn->error);
}

if (!$stmt->bind_param("i", $equipment_id) || !$stmt->execute()) {
    die("Error executing equipment query: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger mt-4">Equipment not found.</div>';
    include 'admin-footer.php';
    exit;
}

$equipment = $result->fetch_assoc();

// Set default values
$equipment['replacement_value'] = $equipment['replacement_value'] ?? 0.00;
$equipment['purchase_date'] = $equipment['purchase_date'] ?? null;
$equipment['purchase_cost'] = $equipment['purchase_cost'] ?? null;

// Get rental history
$rental_history = [];
$rental_query = "
    SELECT r.*, 
           u.first_name, 
           u.last_name,
           DATEDIFF(r.end_date, r.start_date) AS duration,
           (DATEDIFF(r.end_date, r.start_date) * e.daily_rate) AS total_cost
    FROM rentals r
    JOIN users u ON r.user_id= u.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.equipment_id = ?
    ORDER BY r.start_date DESC
    LIMIT 5
";

$rental_stmt = $conn->prepare($rental_query);
if ($rental_stmt === false) {
    die("Error preparing rental history query: " . $conn->error);
}

if (!$rental_stmt->bind_param("i", $equipment_id) || !$rental_stmt->execute()) {
    die("Error executing rental history query: " . $rental_stmt->error);
}

$rental_result = $rental_stmt->get_result();

while ($row = $rental_result->fetch_assoc()) {
    $rental_history[] = $row;
}

// Get provenance history for this equipment
$provenance_history = [];
$provenance_query = "
    SELECT dp.*, u.first_name, u.last_name
    FROM data_provenance dp
    JOIN users u ON dp.user_id = u.id
    WHERE dp.equipment_id = ?
    ORDER BY dp.timestamp DESC
    LIMIT 10
";

$provenance_stmt = $conn->prepare($provenance_query);
if ($provenance_stmt === false) {
    die("Error preparing provenance query: " . $conn->error);
}

if (!$provenance_stmt->bind_param("i", $equipment_id) || !$provenance_stmt->execute()) {
    die("Error executing provenance query: " . $provenance_stmt->error);
}

$provenance_result = $provenance_stmt->get_result();

while ($row = $provenance_result->fetch_assoc()) {
    $provenance_history[] = $row;
}

include 'admin-header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Equipment Details</h2>
                    <span class="badge bg-light text-dark">ID: <?php echo $equipment['id']; ?></span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <?php if (!empty($equipment['image']) && file_exists('../' . $equipment['image'])): ?>
                                <img src="../<?php echo $equipment['image']; ?>" class="img-fluid rounded mb-3" style="max-height: 200px;" alt="<?php echo htmlspecialchars($equipment['name']); ?>">
                            <?php else: ?>
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-camera fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-3"><?php echo htmlspecialchars($equipment['name']); ?></h5>
                                    <p class="text-muted"><?php echo htmlspecialchars($equipment['category_name']); ?></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <span class="badge <?php 
                                        echo $equipment['status'] == STATUS_AVAILABLE ? 'bg-success' : 
                                             ($equipment['status'] == STATUS_RENTED ? 'bg-warning' : 'bg-danger'); 
                                    ?>">
                                        <?php echo getStatusText($equipment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Daily Rate:</strong> $<?php echo number_format($equipment['daily_rate'], 2); ?></p>
                                    <p><strong>Replacement Value:</strong> $<?php echo number_format($equipment['replacement_value'], 2); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Purchase Date:</strong> <?php echo !empty($equipment['purchase_date']) ? date('M d, Y', strtotime($equipment['purchase_date'])) : 'N/A'; ?></p>
                                    <p><strong>Purchase Cost:</strong> <?php echo !empty($equipment['purchase_cost']) ? '$' . number_format($equipment['purchase_cost'], 2) : 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-3">Description</h5>
                        <div class="bg-light p-3 rounded">
                            <?php echo !empty($equipment['description']) ? nl2br(htmlspecialchars($equipment['description'])) : 'No description provided'; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Specifications</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($equipment['specifications'])): ?>
                                        <?php $specs = json_decode($equipment['specifications'], true); ?>
                                        <?php if (is_array($specs)): ?>
                                            <ul class="list-unstyled">
                                                <?php foreach ($specs as $key => $value): ?>
                                                    <li class="mb-2"><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p><?php echo nl2br(htmlspecialchars($equipment['specifications'])); ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-muted">No specifications provided</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Owner Information</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($equipment['owner_id'])): ?>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($equipment['owner_first_name'] . ' ' . $equipment['owner_last_name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($equipment['owner_email']); ?></p>
                                        <a href="view-user.php?id=<?php echo $equipment['owner_id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                            View Owner Profile
                                        </a>
                                    <?php else: ?>
                                        <p class="text-muted">System-owned equipment</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#rentalHistory">Rental History (<?php echo $equipment['rental_count']; ?>)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#provenanceHistory">Audit Trail</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="rentalHistory">
                            <?php if (!empty($rental_history)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rental ID</th>
                                                <th>Renter</th>
                                                <th>Period</th>
                                                <th>Status</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rental_history as $rental): ?>
                                                <tr>
                                                    <td><?php echo $rental['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($rental['first_name'] . ' ' . $rental['last_name']); ?></td>
                                                    <td>
                                                        <?php echo date('M d', strtotime($rental['start_date'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($rental['end_date'])); ?>
                                                        (<?php echo $rental['duration']; ?> days)
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            $status_class = '';
                                                            switch ($rental['status']) {
                                                                case RENTAL_PENDING: $status_class = 'bg-warning'; break;
                                                                case RENTAL_APPROVED: $status_class = 'bg-success'; break;
                                                                case RENTAL_REJECTED: $status_class = 'bg-danger'; break;
                                                                case RENTAL_COMPLETED: $status_class = 'bg-info'; break;
                                                                default: $status_class = 'bg-secondary';
                                                            }
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>">
                                                            <?php echo formatProvenanceAction('rental_' . strtolower($rental['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>$<?php echo number_format($rental['total_cost'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="rental-history.php?equipment_id=<?php echo $equipment['id']; ?>" class="btn btn-sm btn-outline-primary">View Full History</a>
                            <?php else: ?>
                                <p class="text-muted">No rental history found for this equipment.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tab-pane fade" id="provenanceHistory">
                            <?php if (!empty($provenance_history)): ?>
                                <div class="timeline">
                                    <?php foreach ($provenance_history as $event): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title">
                                                    <?php echo formatProvenanceAction($event['action']); ?>
                                                </h6>
                                                <p class="timeline-date">
                                                    <?php echo date('M d, Y h:i A', strtotime($event['timestamp'])); ?> by 
                                                    <?php echo htmlspecialchars($event['first_name'] . ' ' . $event['last_name']); ?>
                                                </p>
                                                <?php if (!empty($event['details'])): ?>
                                                    <?php $details = json_decode($event['details'], true); ?>
                                                    <?php if (json_last_error() === JSON_ERROR_NONE && is_array($details)): ?>
                                                        <ul class="timeline-details">
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
                                                        <p class="timeline-details">
                                                            <?php echo nl2br(htmlspecialchars($event['details'])); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <a href="provenance.php?entity_type=equipment&entity_id=<?php echo $equipment['id']; ?>" class="btn btn-sm btn-outline-primary mt-3">View Full Audit Trail</a>
                            <?php else: ?>
                                <p class="text-muted">No provenance history found for this equipment.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit-equipment.php?id=<?php echo $equipment['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Equipment
                        </a>
                        <?php if ($equipment['status'] == STATUS_AVAILABLE): ?>
                            <a href="create-rental.php?equipment_id=<?php echo $equipment['id']; ?>" class="btn btn-success">
                                <i class="fas fa-calendar-plus me-2"></i>Create Rental
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash me-2"></i>Delete Equipment
                        </button>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Equipment Status</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="update-equipment-status.php">
                        <input type="hidden" name="id" value="<?php echo $equipment['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <select class="form-select" name="status" required>
                                <option value="<?php echo STATUS_AVAILABLE; ?>" <?php echo $equipment['status'] == STATUS_AVAILABLE ? 'selected' : ''; ?>>Available</option>
                                <option value="<?php echo STATUS_RENTED; ?>" <?php echo $equipment['status'] == STATUS_RENTED ? 'selected' : ''; ?>>Rented</option>
                                <option value="<?php echo STATUS_MAINTENANCE; ?>" <?php echo $equipment['status'] == STATUS_MAINTENANCE ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add any status notes..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Equipment Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="d-flex justify-content-between">
                            <span>Total Rentals:</span>
                            <span><?php echo $equipment['rental_count']; ?></span>
                        </h6>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" style="width: <?php echo min($equipment['rental_count'] * 10, 100); ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6 class="d-flex justify-content-between">
                            <span>Availability Rate:</span>
                            <span>
                                <?php 
                                    $available_days = 365 - ($equipment['rental_count'] * 5); // Simplified calculation
                                    echo round(($available_days / 365) * 100); 
                                ?>%
                            </span>
                        </h6>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: <?php echo round(($available_days / 365) * 100); ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6 class="d-flex justify-content-between">
                            <span>Revenue Generated:</span>
                            <span>
                                $<?php 
                                    echo number_format($equipment['rental_count'] * $equipment['daily_rate'] * 3, 2); 
                                ?>
                            </span>
                        </h6>
                    </div>
                    <div class="mb-3">
                        <h6 class="d-flex justify-content-between">
                            <span>Last Rented:</span>
                            <span>
                                <?php 
                                    echo !empty($rental_history) ? 
                                        date('M d, Y', strtotime($rental_history[0]['start_date'])) : 'Never';
                                ?>
                            </span>
                        </h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this equipment?</p>
                <p class="fw-bold"><?php echo htmlspecialchars($equipment['name']); ?></p>
                <p class="text-danger">This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="delete-equipment.php" style="display: inline;">
                    <input type="hidden" name="id" value="<?php echo $equipment['id']; ?>">
                    <button type="submit" class="btn btn-danger">Delete Equipment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }
    .card-header {
        padding: 1rem 1.25rem;
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
    .progress {
        border-radius: 10px;
    }
    .badge {
        font-weight: 500;
    }
    .img-thumbnail {
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }
    /* Timeline styles */
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
        padding: 10px 15px;
        background-color: #f8f9fa;
        border-radius: 6px;
        margin-bottom: 10px;
    }
    .timeline-title {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 5px;
    }
    .timeline-date {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 5px;
    }
    .timeline-details {
        font-size: 0.85rem;
        margin-bottom: 0;
        padding-left: 15px;
    }
    .timeline-details li {
        margin-bottom: 3px;
    }
</style>

<script>
// Form validation
(function () {
    'use strict'
    const forms = document.querySelectorAll('form.needs-validation')
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
    
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
})()
</script>

<?php include 'admin-footer.php'; ?>