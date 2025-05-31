<?php
// Enhanced dashboard.php with proper provenance integration - FIXED
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/provenance/activity_logger.php';
require_once __DIR__ . '/includes/provenance/query_logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}


log_activity('view_dashboard', 'Admin viewed dashboard');

// Initialize variables
$total_users = 0;
$total_equipment = 0;
$pending_rentals = 0;
$active_rentals = 0;
$total_revenue = 0;
$recent_users = [];
$recent_equipment = [];
$recent_rentals = [];

try {
    // Get total users
    $query = "SELECT COUNT(*) as count FROM users";
  $total_users_result = log_query("SELECT COUNT(*) as count FROM users", 'SELECT', 'users');
$total_users = $total_users_result->fetch_assoc()['count'];
  if ($total_users_result && $row = $total_users_result->fetch_assoc()) {
        $total_users = $row['count'];
    }

    // Get total equipment
    $query = "SELECT COUNT(*) as count FROM equipment";
  $total_equipment_result = log_query("SELECT COUNT(*) as count FROM equipment", 'SELECT', 'equipment');
$total_equipment = $total_equipment_result->fetch_assoc()['count'];
  if ($total_equipment_result && $row = $total_equipment_result->fetch_assoc()) {
        $total_equipment = $row['count'];
    }

    // Get pending rentals
    $query = "SELECT COUNT(*) as count FROM rentals WHERE status = 1";
    $pending_rentals_result = log_query("SELECT COUNT(*) as count FROM rentals WHERE status = 1", 'SELECT', 'rentals');
$pending_rentals = $pending_rentals_result->fetch_assoc()['count'];
if ($pending_rentals_result && $row = $pending_rentals_result->fetch_assoc()) {
        $pending_rentals = $row['count'];
    }

    // Get active rentals
    $query = "SELECT COUNT(*) as count FROM rentals WHERE status = 2";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $active_rentals = $row['count'];
    }

    // Get total revenue
    $query = "
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM rentals 
        WHERE status = 4 AND total_amount IS NOT NULL
    ";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $total_revenue = $row['total'];
    }

    // Get recent users
    $query = "
        SELECT id, first_name, last_name, email, user_type, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 5
    ";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_users[] = $row;
        }
    }

    // Get recent equipment
    $query = "
        SELECT e.id, e.name, e.daily_rate, e.status, e.created_at, c.name as category_name
        FROM equipment e
        JOIN categories c ON e.category_id = c.id
        ORDER BY e.created_at DESC
        LIMIT 5
    ";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_equipment[] = $row;
        }
    }

    // Get recent rentals
    $query = "
        SELECT r.id, r.start_date, r.end_date, r.status, r.created_at,
               u.first_name, u.last_name, 
               e.name as equipment_name
        FROM rentals r
        JOIN users u ON r.user_id = u.id
        JOIN equipment e ON r.equipment_id = e.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_rentals[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    // Continue with default values
}

// Get provenance statistics for the dashboard - FIXED
$provenance_stats = [
    'total_records' => 0,
    'total_queries' => 0,
    'total_activities' => 0,
    'total_events' => 0
];

try {
    // Get data provenance records
    $query = "SELECT COUNT(*) as count FROM data_provenance";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $provenance_stats['total_records'] = $row['count'];
    }

    // Get query provenance records (if table exists)
    $query = "SHOW TABLES LIKE 'query_provenance'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $query = "SELECT COUNT(*) as count FROM query_provenance";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $provenance_stats['total_queries'] = $row['count'];
        }
    }

    // Get user activity records (if table exists)
    $query = "SHOW TABLES LIKE 'user_activity'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $query = "SELECT COUNT(*) as count FROM user_activity";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $provenance_stats['total_activities'] = $row['count'];
        }
    }

    // Get system events (if table exists)
    $query = "SHOW TABLES LIKE 'system_events_enhanced'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $query = "SELECT COUNT(*) as count FROM system_events_enhanced";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $provenance_stats['total_events'] = $row['count'];
        }
    }

} catch (Exception $e) {
    error_log("Provenance stats error: " . $e->getMessage());
    // Keep default values
}

include 'admin-header.php';
?>

<style>
/* Enhanced Dashboard Styles */
:root {
    --primary: #3498db;
    --success: #2ecc71;
    --warning: #f39c12;
    --danger: #e74c3c;
    --info: #1abc9c;
    --dark: #2c3e50;
    --light: #f8f9fa;
}

.dashboard-container {
    background-color: #f5f7fa;
    min-height: 100vh;
}

.stat-card {
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    color: white;
}

.stat-card:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    z-index: 1;
}

.stat-card .icon {
    font-size: 2.8rem;
    margin-bottom: 15px;
    position: relative;
    z-index: 2;
    opacity: 0.8;
}

.stat-card .count {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 5px;
    position: relative;
    z-index: 2;
}

.stat-card .title {
    font-size: 1rem;
    opacity: 0.9;
    position: relative;
    z-index: 2;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.stat-card.bg-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #2980b9 100%);
}

.stat-card.bg-success {
    background: linear-gradient(135deg, var(--success) 0%, #27ae60 100%);
}

.stat-card.bg-warning {
    background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
}

.stat-card.bg-info {
    background: linear-gradient(135deg, var(--info) 0%, #16a085 100%);
}

.stat-card.bg-secondary {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
}

.provenance-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
}

.provenance-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.provenance-stat {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.card-header {
    background-color: white;
    border-bottom: 1px solid #e9ecef;
    padding: 20px;
    border-radius: 12px 12px 0 0 !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h5 {
    font-weight: 600;
    margin-bottom: 0;
    color: var(--dark);
}

.table-responsive {
    border-radius: 12px;
    overflow: hidden;
}

.table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    padding: 15px;
    color: #495057;
}

.table tbody td {
    padding: 12px 15px;
    vertical-align: middle;
    border-top: 1px solid #e9ecef;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.badge {
    padding: 6px 10px;
    font-weight: 500;
    font-size: 0.75rem;
    border-radius: 50px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.page-header h1 {
    font-weight: 600;
    color: var(--dark);
}

.btn {
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.8rem;
}
</style>

<div class="dashboard-container container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
        <h1 class="h2">
            <i class="fas fa-tachometer-alt me-2"></i>
            Dashboard Overview
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="reports.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-chart-bar"></i> View Reports
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Provenance System Overview -->
    <div class="provenance-section">
        <h4><i class="fas fa-shield-alt me-2"></i>Provenance System Status</h4>
        <p class="mb-3">Complete audit trail and data lineage tracking active</p>
        <div class="provenance-stats">
            <div class="provenance-stat">
                <h5><?php echo number_format($provenance_stats['total_records']); ?></h5>
                <small>Audit Records</small>
            </div>
            <div class="provenance-stat">
                <h5><?php echo number_format($provenance_stats['total_queries']); ?></h5>
                <small>Tracked Queries</small>
            </div>
            <div class="provenance-stat">
                <h5><?php echo number_format($provenance_stats['total_activities']); ?></h5>
                <small>User Activities</small>
            </div>
            <div class="provenance-stat">
                <h5><?php echo number_format($provenance_stats['total_events']); ?></h5>
                <small>System Events</small>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-primary text-white">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="count"><?php echo number_format($total_users); ?></div>
                <div class="title">Total Users</div>
                <a href="manage-users.php" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-success text-white">
                <div class="icon"><i class="fas fa-camera"></i></div>
                <div class="count"><?php echo number_format($total_equipment); ?></div>
                <div class="title">Total Equipment</div>
                <a href="manage-equipment.php" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-warning text-white">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="count"><?php echo number_format($pending_rentals); ?></div>
                <div class="title">Pending Rentals</div>
                <a href="manage-rentals.php?status=1" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-info text-white">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="count">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="title">Total Revenue</div>
                <a href="reports.php" class="stretched-link"></a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Rentals</h5>
                    <a href="manage-rentals.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Equipment</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_rentals) > 0): ?>
                                <?php foreach ($recent_rentals as $rental): ?>
                                <tr>
                                    <td>#<?php echo $rental['id']; ?></td>
                                    <td><?php echo htmlspecialchars($rental['first_name'] . ' ' . $rental['last_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($rental['equipment_name']); ?></td>
                                    <td>
                                        <?php 
                                                    $status_class = '';
                                                    $status_text = '';
                                                    
                                                    switch ($rental['status']) {
                                                        case 1:
                                                            $status_class = 'bg-warning';
                                                            $status_text = 'Pending';
                                                            break;
                                                        case 2:
                                                            $status_class = 'bg-success';
                                                            $status_text = 'Approved';
                                                            break;
                                                        case 3:
                                                            $status_class = 'bg-danger';
                                                            $status_text = 'Rejected';
                                                            break;
                                                        case 4:
                                                            $status_class = 'bg-info';
                                                            $status_text = 'Completed';
                                                            break;
                                                        case 5:
                                                            $status_class = 'bg-secondary';
                                                            $status_text = 'Cancelled';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-secondary';
                                                            $status_text = 'Unknown';
                                                    }
                                                ?>
                                        <span
                                            class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($rental['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No recent rentals found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Users</h5>
                    <a href="manage-users.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_users) > 0): ?>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if ($user['user_type'] == 2): ?>
                                        <span class="badge bg-warning">Admin</span>
                                        <?php else: ?>
                                        <span class="badge bg-info">Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No recent users found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Equipment</h5>
                    <a href="manage-equipment.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Daily Rate</th>
                                    <th>Status</th>
                                    <th>Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_equipment) > 0): ?>
                                <?php foreach ($recent_equipment as $equipment): ?>
                                <tr>
                                    <td>#<?php echo $equipment['id']; ?></td>
                                    <td><?php echo htmlspecialchars($equipment['name']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['category_name']); ?></td>
                                    <td>$<?php echo number_format($equipment['daily_rate'], 2); ?></td>
                                    <td>
                                        <?php 
                                                    $status_class = '';
                                                    $status_text = '';
                                                    
                                                    switch ($equipment['status']) {
                                                        case 1:
                                                            $status_class = 'bg-success';
                                                            $status_text = 'Available';
                                                            break;
                                                        case 2:
                                                            $status_class = 'bg-warning';
                                                            $status_text = 'Rented';
                                                            break;
                                                        case 3:
                                                            $status_class = 'bg-danger';
                                                            $status_text = 'Maintenance';
                                                            break;
                                                        case 4:
                                                            $status_class = 'bg-info';
                                                            $status_text = 'Reserved';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-secondary';
                                                            $status_text = 'Unknown';
                                                    }
                                                ?>
                                        <span
                                            class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($equipment['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No recent equipment found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>