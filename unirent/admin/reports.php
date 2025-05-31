<?php
// Start session and check admin access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/provenance/activity_logger.php';
log_activity('reports', 'Admin viewed reports page');

// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Get date range for filtering
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get statistics
$total_users = 0;
$total_equipment = 0;
$total_rentals = 0;
$pending_rentals = 0;
$completed_rentals = 0;
$total_revenue = 0;

// Get total users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result && $row = $result->fetch_assoc()) {
    $total_users = $row['count'];
}

// Get total equipment
$result = $conn->query("SELECT COUNT(*) as count FROM equipment");
if ($result && $row = $result->fetch_assoc()) {
    $total_equipment = $row['count'];
}

// Get total rentals
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM rentals 
    WHERE created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $total_rentals = $row['count'];
}

// Get pending rentals - Fix status comparison to use constants
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM rentals 
    WHERE status = ? AND created_at BETWEEN ? AND ?
");
$pending_status = RENTAL_PENDING;
$stmt->bind_param("iss", $pending_status, $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $pending_rentals = $row['count'];
}

// Get completed rentals - Fix status comparison to use constants
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM rentals 
    WHERE status = ? AND created_at BETWEEN ? AND ?
");
$completed_status = RENTAL_COMPLETED;
$stmt->bind_param("iss", $completed_status, $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $completed_rentals = $row['count'];
}

// Get total revenue (estimated from completed rentals) - FIXED
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(e.daily_rate * DATEDIFF(r.end_date, r.start_date)), 0) as total
    FROM rentals r
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.status = ? AND r.created_at BETWEEN ? AND ?
");
$stmt->bind_param("iss", $completed_status, $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    // Use COALESCE in SQL and provide fallback
    $total_revenue = isset($row['total']) ? (float)$row['total'] : 0;
}

// Get monthly rental stats
$monthly_stats = [];
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM rentals
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $monthly_stats[] = $row;
}

// Get top categories
$top_categories = [];
$stmt = $conn->prepare("
    SELECT 
        c.name as category,
        COUNT(r.id) as rental_count
    FROM rentals r
    JOIN equipment e ON r.equipment_id = e.id
    JOIN categories c ON e.category_id = c.id
    WHERE r.created_at BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY rental_count DESC
    LIMIT 5
");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $top_categories[] = $row;
}

// Get rental status distribution - FIXED to use numeric status values
$status_distribution = [
    RENTAL_PENDING => 0,
    RENTAL_APPROVED => 0,
    RENTAL_COMPLETED => 0,
    RENTAL_REJECTED => 0,
    RENTAL_CANCELLED => 0
];

$stmt = $conn->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM rentals
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $status = (int)$row['status'];
    if (isset($status_distribution[$status])) {
        $status_distribution[$status] = $row['count'];
    }
}

// Include shared layout
include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Reports & Analytics</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="reports.php" class="row g-3">
                <div class="col-md-4">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-4">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filter</button>
                    <a href="reports.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card bg-primary text-white">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="count"><?php echo $total_users; ?></div>
                <div class="title">Total Users</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-success text-white">
                <div class="icon"><i class="fas fa-camera"></i></div>
                <div class="count"><?php echo $total_equipment; ?></div>
                <div class="title">Total Equipment</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-warning text-white">
                <div class="icon"><i class="fas fa-handshake"></i></div>
                <div class="count"><?php echo $total_rentals; ?></div>
                <div class="title">Total Rentals</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-info text-white">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="count">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="title">Total Revenue</div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Monthly Rentals</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Rental Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Top Categories</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Rental Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_category_rentals = array_sum(array_column($top_categories, 'rental_count'));
                            foreach ($top_categories as $category): 
                                $percentage = ($total_category_rentals > 0) ? ($category['rental_count'] / $total_category_rentals) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['category']); ?></td>
                                    <td><?php echo $category['rental_count']; ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($percentage); ?>%</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($top_categories) == 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Rental Statistics</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td>Total Rentals</td>
                                <td><strong><?php echo $total_rentals; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Pending Rentals</td>
                                <td><strong><?php echo $pending_rentals; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Completed Rentals</td>
                                <td><strong><?php echo $completed_rentals; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Completion Rate</td>
                                <td><strong><?php echo ($total_rentals > 0) ? round(($completed_rentals / $total_rentals) * 100, 2) : 0; ?>%</strong></td>
                            </tr>
                            <tr>
                                <td>Average Revenue per Rental</td>
                                <td><strong>$<?php echo ($completed_rentals > 0) ? number_format($total_revenue / $completed_rentals, 2) : '0.00'; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Rentals Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                foreach ($monthly_stats as $stat) {
                    $date = date_create_from_format('Y-m', $stat['month']);
                    if ($date) {
                        echo "'" . date_format($date, 'M Y') . "', ";
                    }
                }
                ?>
            ],
            datasets: [{
                label: 'Number of Rentals',
                data: [
                    <?php 
                    foreach ($monthly_stats as $stat) {
                        echo intval($stat['count']) . ", ";
                    }
                    ?>
                ],
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
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    // Rental Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled'],
            datasets: [{
                data: [
                    <?php echo intval($status_distribution[RENTAL_PENDING]); ?>,
                    <?php echo intval($status_distribution[RENTAL_APPROVED]); ?>,
                    <?php echo intval($status_distribution[RENTAL_COMPLETED]); ?>,
                    <?php echo intval($status_distribution[RENTAL_REJECTED]); ?>,
                    <?php echo intval($status_distribution[RENTAL_CANCELLED]); ?>
                ],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',  // Warning - Pending
                    'rgba(40, 167, 69, 0.8)',  // Success - Approved
                    'rgba(23, 162, 184, 0.8)', // Info - Completed
                    'rgba(220, 53, 69, 0.8)',  // Danger - Rejected
                    'rgba(108, 117, 125, 0.8)' // Secondary - Cancelled
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
});

// Function to export report as PDF
function exportToPDF() {
    alert('PDF export functionality would be implemented here.');
    // In a real implementation, you would use a library like jsPDF or make an AJAX call to a server-side PDF generator
}
</script>

<?php include 'admin-footer.php'; ?>