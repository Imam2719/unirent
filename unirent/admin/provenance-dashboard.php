<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Try to include activity logger, but don't fail if it doesn't exist
$activity_logger_path = __DIR__ . '/includes/provenance/activity_logger.php';
if (file_exists($activity_logger_path)) {
    require_once $activity_logger_path;
    // Track this page access
    if (function_exists('trackUserActivity')) {
        trackUserActivity('dashboard_access', 'Accessed Data Provenance Dashboard');
    }
}

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$entity_type = $_GET['entity_type'] ?? '';
$action_type = $_GET['action_type'] ?? '';
$user_filter = $_GET['user_filter'] ?? '';

// Initialize stats with default values
$stats = [
    'total_records' => 0,
    'total_queries' => 0,
    'total_activities' => 0,
    'total_events' => 0
];

// Function to safely execute queries and return count
function getSafeCount($conn, $table, $date_from, $date_to) {
    try {
        // Check if table exists
        $table_check = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$table_check || $table_check->num_rows == 0) {
            return 0;
        }
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE timestamp BETWEEN ? AND ?");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error querying $table: " . $e->getMessage());
        return 0;
    }
}

// Get provenance statistics safely
$stats['total_records'] = getSafeCount($conn, 'data_provenance', $date_from, $date_to);
$stats['total_queries'] = getSafeCount($conn, 'query_provenance', $date_from, $date_to);
$stats['total_activities'] = getSafeCount($conn, 'user_activity', $date_from, $date_to);

// Try both possible system events table names
$stats['total_events'] = getSafeCount($conn, 'system_events_enhanced', $date_from, $date_to);
if ($stats['total_events'] == 0) {
    $stats['total_events'] = getSafeCount($conn, 'system_events', $date_from, $date_to);
}

// Get recent provenance records with enhanced details (safely)
$provenance_records = [];
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'data_provenance'");
    if ($table_check && $table_check->num_rows > 0) {
        $provenance_query = "
            SELECT dp.*, u.first_name, u.last_name, u.email,
                   e.name as equipment_name,
                   r.id as rental_id
            FROM data_provenance dp
            LEFT JOIN users u ON dp.user_id = u.id
            LEFT JOIN equipment e ON dp.equipment_id = e.id
            LEFT JOIN rentals r ON dp.reference_id = r.id
            WHERE dp.timestamp BETWEEN ? AND ?
        ";

        $params = [$date_from, $date_to];
        $types = "ss";

        if (!empty($action_type)) {
            $provenance_query .= " AND dp.action = ?";
            $params[] = $action_type;
            $types .= "s";
        }

        if (!empty($user_filter)) {
            $provenance_query .= " AND dp.user_id = ?";
            $params[] = $user_filter;
            $types .= "i";
        }

        $provenance_query .= " ORDER BY dp.timestamp DESC LIMIT 50";

        $stmt = $conn->prepare($provenance_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $provenance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error querying provenance records: " . $e->getMessage());
}

// Get query statistics by type (safely)
$query_stats = [];
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'query_provenance'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT query_type, COUNT(*) as count, AVG(execution_time) as avg_time
            FROM query_provenance 
            WHERE timestamp BETWEEN ? AND ?
            GROUP BY query_type
            ORDER BY count DESC
        ");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $query_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error querying query statistics: " . $e->getMessage());
}

// Get user activity breakdown (safely)
$activity_stats = [];
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'user_activity'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT activity_type, COUNT(*) as count
            FROM user_activity 
            WHERE timestamp BETWEEN ? AND ?
            GROUP BY activity_type
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $activity_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error querying activity statistics: " . $e->getMessage());
}

// Get all users for filter dropdown
$users = [];
try {
    $result = $conn->query("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name");
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} catch (Exception $e) {
    error_log("Error querying users: " . $e->getMessage());
}

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-search-plus me-2"></i>
            Data Provenance Dashboard
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportProvenanceData()">
                    <i class="fas fa-download"></i> Export Data
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>
                Provenance Filters
            </h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <div class="input-group">
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                        <span class="input-group-text">to</span>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Action Type</label>
                    <select name="action_type" class="form-select">
                        <option value="">All Actions</option>
                        <option value="equipment_created" <?= $action_type === 'equipment_created' ? 'selected' : '' ?>>Equipment Created</option>
                        <option value="equipment_updated" <?= $action_type === 'equipment_updated' ? 'selected' : '' ?>>Equipment Updated</option>
                        <option value="rental_created" <?= $action_type === 'rental_created' ? 'selected' : '' ?>>Rental Created</option>
                        <option value="rental_approved" <?= $action_type === 'rental_approved' ? 'selected' : '' ?>>Rental Approved</option>
                        <option value="rental_rejected" <?= $action_type === 'rental_rejected' ? 'selected' : '' ?>>Rental Rejected</option>
                        <option value="rental_completed" <?= $action_type === 'rental_completed' ? 'selected' : '' ?>>Rental Completed</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user_filter" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="data-provenance-dashboard.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['total_records']) ?></h4>
                            <p class="mb-0">Provenance Records</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-database fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['total_queries']) ?></h4>
                            <p class="mb-0">Database Queries</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['total_activities']) ?></h4>
                            <p class="mb-0">User Activities</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['total_events']) ?></h4>
                            <p class="mb-0">System Events</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Analytics -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Query Statistics by Type</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($query_stats)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-info-circle mb-2"></i><br>
                            No query data available
                        </div>
                    <?php else: ?>
                        <canvas id="queryChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">User Activity Breakdown</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activity_stats)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-info-circle mb-2"></i><br>
                            No activity data available
                        </div>
                    <?php else: ?>
                        <canvas id="activityChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Provenance Records -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Recent Provenance Records
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($provenance_records as $record): ?>
                            <tr>
                                <td>
                                    <small><?= date('M d, Y H:i:s', strtotime($record['timestamp'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($record['user_id']): ?>
                                        <a href="view-user.php?id=<?= $record['user_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= getActionBadgeClass($record['action']) ?>">
                                        <?= formatProvenanceAction($record['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['equipment_name']): ?>
                                        <a href="view-equipment.php?id=<?= $record['equipment_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($record['equipment_name']) ?>
                                        </a>
                                    <?php elseif ($record['rental_id']): ?>
                                        <a href="view-rental.php?id=<?= $record['rental_id'] ?>" class="text-decoration-none">
                                            Rental #<?= $record['rental_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['details']): ?>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="showDetails('<?= htmlspecialchars(json_encode($record['details'])) ?>')">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($record['ip_address']) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewFullRecord(<?= $record['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                onclick="exportRecord(<?= $record['id'] ?>)">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($provenance_records)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No provenance records found for the selected criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Provenance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="detailsContent" class="bg-light p-3 rounded"></pre>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Query Statistics Chart
<?php if (!empty($query_stats)): ?>
const queryCtx = document.getElementById('queryChart').getContext('2d');
new Chart(queryCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($query_stats as $stat) echo "'" . $stat['query_type'] . "',"; ?>],
        datasets: [{
            data: [<?php foreach ($query_stats as $stat) echo $stat['count'] . ","; ?>],
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Activity Statistics Chart
<?php if (!empty($activity_stats)): ?>
const activityCtx = document.getElementById('activityChart').getContext('2d');
new Chart(activityCtx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($activity_stats as $stat) echo "'" . $stat['activity_type'] . "',"; ?>],
        datasets: [{
            label: 'Activity Count',
            data: [<?php foreach ($activity_stats as $stat) echo $stat['count'] . ","; ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>

// Helper functions
function showDetails(details) {
    try {
        const parsed = JSON.parse(details);
        document.getElementById('detailsContent').textContent = JSON.stringify(parsed, null, 2);
    } catch (e) {
        document.getElementById('detailsContent').textContent = details;
    }
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function viewFullRecord(id) {
    window.open('view-provenance-record.php?id=' + id, '_blank');
}

function exportRecord(id) {
    window.location.href = 'export-provenance.php?id=' + id;
}

function exportProvenanceData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = 'export-provenance.php?' + params.toString();
}
</script>

<?php include 'admin-footer.php'; ?>