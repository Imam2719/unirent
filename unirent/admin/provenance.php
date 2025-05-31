<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Get filters
$entity_type = $_GET['entity_type'] ?? '';
$entity_id = $_GET['entity_id'] ?? '';
$action = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$where = ["dp.timestamp BETWEEN ? AND ?"];
$params = [$date_from, $date_to];
$types = "ss";

if (!empty($entity_type)) {
    if ($entity_type === 'equipment') {
        $where[] = "dp.equipment_id = ?";
        $params[] = $entity_id;
        $types .= "i";
    } elseif ($entity_type === 'rental') {
        $rental_sql = "SELECT equipment_id FROM rentals WHERE id = ?";
        $stmt = $conn->prepare($rental_sql);
        $stmt->bind_param("i", $entity_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $where[] = "dp.equipment_id = ?";
            $params[] = $row['equipment_id'];
            $types .= "i";
        }
    }
}

if (!empty($action)) {
    $where[] = "dp.action = ?";
    $params[] = $action;
    $types .= "s";
}

$sql = "SELECT dp.*, u.first_name, u.last_name, e.name as equipment_name
        FROM data_provenance dp
        JOIN users u ON dp.user_id = u.id
        LEFT JOIN equipment e ON dp.equipment_id = e.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY dp.timestamp DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$provenance = $result->fetch_all(MYSQLI_ASSOC);

include 'admin-header.php';
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Data Provenance Tracking</h1>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Entity Type</label>
                    <select name="entity_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="equipment" <?= $entity_type === 'equipment' ? 'selected' : '' ?>>Equipment</option>
                        <option value="rental" <?= $entity_type === 'rental' ? 'selected' : '' ?>>Rental</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Entity ID</label>
                    <input type="text" name="entity_id" class="form-control" value="<?= htmlspecialchars($entity_id) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <option value="rental_created" <?= $action === 'rental_created' ? 'selected' : '' ?>>Rental Created</option>
                        <option value="rental_approved" <?= $action === 'rental_approved' ? 'selected' : '' ?>>Rental Approved</option>
                        <option value="rental_rejected" <?= $action === 'rental_rejected' ? 'selected' : '' ?>>Rental Rejected</option>
                        <option value="rental_completed" <?= $action === 'rental_completed' ? 'selected' : '' ?>>Rental Completed</option>
                        <option value="equipment_updated" <?= $action === 'equipment_updated' ? 'selected' : '' ?>>Equipment Updated</option>
                        <option value="equipment_status_changed" <?= $action === 'equipment_status_changed' ? 'selected' : '' ?>>Status Changed</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <div class="input-group">
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                        <span class="input-group-text">to</span>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="provenance.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Provenance Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Equipment</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($provenance as $record): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($record['timestamp'])) ?></td>
                                <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></td>
                                <td>
                                    <?php if ($record['equipment_name']): ?>
                                        <a href="view-equipment.php?id=<?= $record['equipment_id'] ?>">
                                            <?= htmlspecialchars($record['equipment_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= formatProvenanceAction($record['action']) ?></td>
                                <td>
                                    <?php if ($record['details']): ?>
                                        <?php $details = json_decode($record['details'], true); ?>
                                        <?php if (json_last_error() === JSON_ERROR_NONE && is_array($details)): ?>
                                            <ul class="list-unstyled mb-0">
                                            <?php foreach ($details as $key => $value): ?>
                                                <li>
                                                    <strong><?= htmlspecialchars($key) ?>:</strong> 
                                                    <?php if (is_array($value)): ?>
                                                        <?= htmlspecialchars(json_encode($value)) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($value) ?>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <?= htmlspecialchars($record['details']) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($record['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($provenance)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">No provenance records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>