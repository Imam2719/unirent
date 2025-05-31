<?php
// Enhanced manage-equipment.php with full provenance support
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/provenance/activity_logger.php';
require_once __DIR__ . '/includes/provenance/query_logger.php';

// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

log_activity('view_manage_equipment', 'Admin viewed manage equipment page');

$success = '';
$error = '';

// Handle equipment actions with full provenance tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['equipment_id'])) {
        $equipment_id = intval($_POST['equipment_id']);
        $action = $_POST['action'];
        $reason = $_POST['reason'] ?? null;
        $admin_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        switch ($action) {
            case 'update_price':
                $new_price = floatval($_POST['new_price']);
                if ($new_price >= 0) {
                    if (updateEquipmentPrice($conn, $equipment_id, $new_price, $admin_id, $reason, $ip_address)) {
                        $success = 'Equipment price updated successfully with full audit trail.';
                        log_activity('equipment_price_updated', "Updated price for equipment ID: $equipment_id to $new_price");
                    } else {
                        $error = 'Failed to update equipment price.';
                    }
                } else {
                    $error = 'Price must be a positive number.';
                }
                break;
                
            case 'change_status':
                $new_status = intval($_POST['new_status']);
                setAuditContext($conn, $admin_id, $ip_address, $reason);
                $stmt = $conn->prepare("UPDATE equipment SET status = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_status, $equipment_id);
                if ($stmt->execute()) {
                    $success = 'Equipment status updated successfully with full audit trail.';
                    log_activity('equipment_status_updated', "Updated status for equipment ID: $equipment_id to $new_status");
                } else {
                    $error = 'Failed to update equipment status.';
                }
                break;
                
            case 'toggle_featured':
                setAuditContext($conn, $admin_id, $ip_address, $reason);
                $stmt = $conn->prepare("UPDATE equipment SET is_featured = NOT is_featured WHERE id = ?");
                $stmt->bind_param("i", $equipment_id);
                if ($stmt->execute()) {
                    $success = 'Equipment featured status updated successfully.';
                    log_activity('equipment_featured_toggled', "Toggled featured status for equipment ID: $equipment_id");
                } else {
                    $error = 'Failed to update featured status.';
                }
                break;
                
            case 'delete':
                // Check if equipment has active rentals
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM rentals WHERE equipment_id = ? AND status IN (1, 2)");
                $check_stmt->bind_param("i", $equipment_id);
                $check_stmt->execute();
                $rental_count = $check_stmt->get_result()->fetch_assoc()['count'];
                
                if ($rental_count > 0) {
                    $error = 'Cannot delete equipment with active or pending rentals.';
                } else {
                    setAuditContext($conn, $admin_id, $ip_address, $reason);
                    
                    // Get image path before deleting
                    $stmt = $conn->prepare("SELECT image FROM equipment WHERE id = ?");
                    $stmt->bind_param("i", $equipment_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $equipment = $result->fetch_assoc();
                    
                    // Delete the equipment
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
                        
                        $success = 'Equipment deleted successfully with full audit trail.';
                        log_activity('equipment_deleted', "Deleted equipment ID: $equipment_id");
                    } else {
                        $error = 'Failed to delete equipment.';
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$owner_filter = $_GET['owner'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with logging
$where_conditions = [];
$params = [];
$types = '';

if ($category_filter) {
    $where_conditions[] = "e.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if ($status_filter !== '') {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
    $types .= 'i';
}

if ($owner_filter) {
    $where_conditions[] = "e.owner_id = ?";
    $params[] = $owner_filter;
    $types .= 'i';
}

if ($search) {
    $where_conditions[] = "(e.name LIKE ? OR e.description LIKE ? OR e.brand LIKE ? OR e.model LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT e.*, 
           c.name as category_name,
           CONCAT(u.first_name, ' ', u.last_name) as owner_name,
           COUNT(r.id) as rental_count,
           COUNT(CASE WHEN r.status IN (1, 2) THEN 1 END) as active_rentals,
           MAX(r.created_at) as last_rental
    FROM equipment e
    JOIN categories c ON e.category_id = c.id
    JOIN users u ON e.owner_id = u.id
    LEFT JOIN rentals r ON e.id = r.equipment_id
    $where_clause
    GROUP BY e.id
    ORDER BY e.created_at DESC
";

if ($params) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Get categories for filter
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name");

// Get owners for filter
$owners_result = $conn->query("
    SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
    FROM users u 
    JOIN equipment e ON u.id = e.owner_id 
    ORDER BY name
");

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-camera me-2"></i>
            Equipment Management
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="add-equipment.php" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Add Equipment
                </a>
                <a href="equipment-report.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Equipment Statistics Cards -->
    <div class="row mb-4">
        <?php
        // Get equipment statistics with logging
        $stats_query = "
            SELECT 
                COUNT(*) as total_equipment,
                COUNT(CASE WHEN status = 1 THEN 1 END) as available,
                COUNT(CASE WHEN status = 2 THEN 1 END) as rented,
                COUNT(CASE WHEN status = 3 THEN 1 END) as maintenance,
                AVG(daily_rate) as avg_price
            FROM equipment
        ";
        $stats_result = $conn->query($stats_query);
        $stats = $stats_result->fetch_assoc();
        ?>
        
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['total_equipment']); ?></h4>
                    <small>Total Equipment</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['available']); ?></h4>
                    <small>Available</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['rented']); ?></h4>
                    <small>Rented Out</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo number_format($stats['maintenance']); ?></h4>
                    <small>Maintenance</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0">$<?php echo number_format($stats['avg_price'], 2); ?></h4>
                    <small>Average Daily Rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="1" <?php echo $status_filter == '1' ? 'selected' : ''; ?>>Available</option>
                        <option value="2" <?php echo $status_filter == '2' ? 'selected' : ''; ?>>Rented</option>
                        <option value="3" <?php echo $status_filter == '3' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="4" <?php echo $status_filter == '4' ? 'selected' : ''; ?>>Reserved</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Owner</label>
                    <select name="owner" class="form-select">
                        <option value="">All Owners</option>
                        <?php while ($owner = $owners_result->fetch_assoc()): ?>
                            <option value="<?php echo $owner['id']; ?>" 
                                    <?php echo $owner_filter == $owner['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($owner['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, description, brand, or model" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="manage-equipment.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Equipment Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Category</th>
                            <th>Owner</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Activity</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($equipment = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="equipment-image me-3">
                                            <?php if ($equipment['image']): ?>
                                                <img src="../<?php echo htmlspecialchars($equipment['image']); ?>" 
                                                     class="rounded" width="50" height="50" alt="Equipment">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded d-flex align-items-center justify-content-center" 
                                                     style="width: 50px; height: 50px; color: white;">
                                                    <i class="fas fa-camera"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($equipment['name']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($equipment['brand'] . ' ' . $equipment['model']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($equipment['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($equipment['owner_name']); ?></td>
                                <td>
                                    <strong>$<?php echo number_format($equipment['daily_rate'], 2); ?></strong>/day
                                    <br><button class="btn btn-sm btn-link p-0 text-decoration-none" 
                                              onclick="showPriceModal(<?php echo $equipment['id']; ?>, <?php echo $equipment['daily_rate']; ?>, '<?php echo htmlspecialchars($equipment['name']); ?>')">
                                        <i class="fas fa-edit"></i> Change
                                    </button>
                                </td>
                                <td>
                                    <?php
                                    $status_classes = [1 => 'success', 2 => 'warning', 3 => 'danger', 4 => 'info'];
                                    $status_texts = [1 => 'Available', 2 => 'Rented', 3 => 'Maintenance', 4 => 'Reserved'];
                                    $status_class = $status_classes[$equipment['status']] ?? 'secondary';
                                    $status_text = $status_texts[$equipment['status']] ?? 'Unknown';
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    <br><button class="btn btn-sm btn-link p-0 text-decoration-none" 
                                              onclick="showStatusModal(<?php echo $equipment['id']; ?>, <?php echo $equipment['status']; ?>, '<?php echo htmlspecialchars($equipment['name']); ?>')">
                                        <i class="fas fa-edit"></i> Change
                                    </button>
                                </td>
                                <td>
                                    <small>
                                        <strong><?php echo $equipment['rental_count']; ?></strong> total rentals<br>
                                        <strong><?php echo $equipment['active_rentals']; ?></strong> active
                                        <?php if ($equipment['last_rental']): ?>
                                            <br>Last: <?php echo date('M d', strtotime($equipment['last_rental'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($equipment['is_featured']): ?>
                                        <span class="badge bg-warning"><i class="fas fa-star"></i> Featured</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Normal</span>
                                    <?php endif; ?>
                                    <br><button class="btn btn-sm btn-link p-0 text-decoration-none" 
                                              onclick="toggleFeatured(<?php echo $equipment['id']; ?>, '<?php echo htmlspecialchars($equipment['name']); ?>')">
                                        <i class="fas fa-star"></i> Toggle
                                    </button>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view-equipment.php?id=<?php echo $equipment['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-equipment.php?id=<?php echo $equipment['id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="showDeleteModal(<?php echo $equipment['id']; ?>, '<?php echo htmlspecialchars($equipment['name']); ?>', <?php echo $equipment['active_rentals']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<!-- Price Change Modal -->
<div class="modal fade" id="priceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Equipment Price</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="equipment_id" id="priceEquipmentId">
                    <input type="hidden" name="action" value="update_price">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-dollar-sign me-2"></i>
                        <strong>Price Change Audit:</strong> This change will be logged with full provenance tracking for compliance and transparency.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Equipment</label>
                        <input type="text" class="form-control" id="priceEquipmentName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Price</label>
                        <input type="text" class="form-control" id="currentPrice" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Price (per day)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="new_price" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Price Change <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="Market adjustment, competitive pricing, cost changes, etc." required></textarea>
                        <div class="form-text">Required for audit compliance and price history tracking.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Price</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Equipment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="equipment_id" id="statusEquipmentId">
                    <input type="hidden" name="action" value="change_status">
                    
                    <div class="mb-3">
                        <label class="form-label">Equipment</label>
                        <input type="text" class="form-control" id="statusEquipmentName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="new_status" class="form-select" required>
                            <option value="1">Available</option>
                            <option value="2">Rented</option>
                            <option value="3">Maintenance</option>
                            <option value="4">Reserved</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="2" 
                                  placeholder="Reason for status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Equipment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="equipment_id" id="deleteEquipmentId">
                    <input type="hidden" name="action" value="delete">
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone and will be permanently logged in the audit trail.
                    </div>
                    
                    <div id="deleteWarning" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-ban me-2"></i>
                        <strong>Cannot Delete:</strong> This equipment has active or pending rentals.
                    </div>
                    
                    <p>Are you sure you want to delete <strong id="deleteEquipmentName"></strong>?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Deletion <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="Equipment damaged, obsolete, sold, etc." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="deleteSubmitBtn">Delete Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showPriceModal(equipmentId, currentPrice, equipmentName) {
    document.getElementById('priceEquipmentId').value = equipmentId;
    document.getElementById('priceEquipmentName').value = equipmentName;
    document.getElementById('currentPrice').value = '$' + parseFloat(currentPrice).toFixed(2);
    new bootstrap.Modal(document.getElementById('priceModal')).show();
}

function showStatusModal(equipmentId, currentStatus, equipmentName) {
    document.getElementById('statusEquipmentId').value = equipmentId;
    document.getElementById('statusEquipmentName').value = equipmentName;
    document.querySelector('[name="new_status"]').value = currentStatus;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

function showDeleteModal(equipmentId, equipmentName, activeRentals) {
    document.getElementById('deleteEquipmentId').value = equipmentId;
    document.getElementById('deleteEquipmentName').textContent = equipmentName;
    
    const warning = document.getElementById('deleteWarning');
    const submitBtn = document.getElementById('deleteSubmitBtn');
    
    if (activeRentals > 0) {
        warning.style.display = 'block';
        submitBtn.disabled = true;
    } else {
        warning.style.display = 'none';
        submitBtn.disabled = false;
    }
    
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function toggleFeatured(equipmentId, equipmentName) {
    if (confirm('Toggle featured status for ' + equipmentName + '?')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `
            <input type="hidden" name="equipment_id" value="${equipmentId}">
            <input type="hidden" name="action" value="toggle_featured">
            <input type="hidden" name="reason" value="Featured status toggled by admin">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'admin-footer.php'; ?>