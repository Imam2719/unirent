<?php
// =====================================================
// ENHANCED provenance-activity.php
// =====================================================
?>
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Log the activity
if (function_exists('log_activity')) {
    log_activity('view_provenance_activity', 'Admin viewed user activity logs');
}

// Get filter parameters
$user_filter = $_GET['user_filter'] ?? '';
$activity_filter = $_GET['activity_filter'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';
$limit = intval($_GET['limit'] ?? 50);

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-history me-2"></i>
            User Activity Logs
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="provenance-queries.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-database"></i> Query Logs
                </a>
                <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user_filter" class="form-select">
                        <option value="">All Users</option>
                        <?php
                        $users_query = "SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name";
                        $users_result = $conn->query($users_query);
                        if ($users_result) {
                            while ($user = $users_result->fetch_assoc()) {
                                $selected = ($user_filter == $user['id']) ? 'selected' : '';
                                echo "<option value='{$user['id']}' $selected>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Activity Type</label>
                    <select name="activity_filter" class="form-select">
                        <option value="">All Activities</option>
                        <option value="view_dashboard" <?php echo $activity_filter == 'view_dashboard' ? 'selected' : ''; ?>>Dashboard Views</option>
                        <option value="login" <?php echo $activity_filter == 'login' ? 'selected' : ''; ?>>Logins</option>
                        <option value="rental" <?php echo $activity_filter == 'rental' ? 'selected' : ''; ?>>Rental Activities</option>
                        <option value="equipment" <?php echo $activity_filter == 'equipment' ? 'selected' : ''; ?>>Equipment Activities</option>
                        <option value="user" <?php echo $activity_filter == 'user' ? 'selected' : ''; ?>>User Activities</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" name="date_filter" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Limit</label>
                    <select name="limit" class="form-select">
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="provenance-activity.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Logs -->
    <div class="card">
        <div class="card-header">
            <h5>User Activity Records</h5>
        </div>
        <div class="card-body">
            <?php
            // Check if user_activity table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'user_activity'");
            
            if ($table_check && $table_check->num_rows > 0) {
                // Build the query with filters
                $where_conditions = [];
                $params = [];
                $types = '';
                
                if ($user_filter) {
                    $where_conditions[] = "ua.user_id = ?";
                    $params[] = $user_filter;
                    $types .= 'i';
                }
                
                if ($activity_filter) {
                    $where_conditions[] = "ua.activity_type LIKE ?";
                    $params[] = "%$activity_filter%";
                    $types .= 's';
                }
                
                if ($date_filter) {
                    $where_conditions[] = "DATE(ua.timestamp) = ?";
                    $params[] = $date_filter;
                    $types .= 's';
                }
                
                $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                
                $query = "
                    SELECT ua.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as user_name,
                           u.user_type
                    FROM user_activity ua
                    LEFT JOIN users u ON ua.user_id = u.id
                    $where_clause
                    ORDER BY ua.timestamp DESC
                    LIMIT ?
                ";
                
                $params[] = $limit;
                $types .= 'i';
                
                $stmt = $conn->prepare($query);
                if ($stmt) {
                    if ($params) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-striped table-hover">';
                        echo '<thead class="table-dark">';
                        echo '<tr>';
                        echo '<th>ID</th>';
                        echo '<th>User</th>';
                        echo '<th>Activity Type</th>';
                        echo '<th>Description</th>';
                        echo '<th>Page</th>';
                        echo '<th>Method</th>';
                        echo '<th>IP Address</th>';
                        echo '<th>Timestamp</th>';
                        echo '<th>Actions</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . $row['id'] . '</td>';
                            echo '<td>';
                            if ($row['user_name']) {
                                echo htmlspecialchars($row['user_name']);
                                echo '<br><small class="text-muted">';
                                echo $row['user_type'] == 2 ? 'Admin' : 'Student';
                                echo '</small>';
                            } else {
                                echo '<span class="text-muted">Unknown User</span>';
                            }
                            echo '</td>';
                            echo '<td><span class="badge bg-info">' . htmlspecialchars($row['activity_type']) . '</span></td>';
                            echo '<td>' . htmlspecialchars($row['activity_description'] ?? 'No description') . '</td>';
                            echo '<td>';
                            if ($row['page_url']) {
                                echo '<small>' . htmlspecialchars(basename($row['page_url'])) . '</small>';
                            } else {
                                echo '<span class="text-muted">N/A</span>';
                            }
                            echo '</td>';
                            echo '<td><span class="badge bg-secondary">' . ($row['http_method'] ?? 'N/A') . '</span></td>';
                            echo '<td><small>' . htmlspecialchars($row['ip_address'] ?? 'Unknown') . '</small></td>';
                            echo '<td>';
                            echo '<small>' . date('M d, Y', strtotime($row['timestamp'])) . '</small><br>';
                            echo '<small class="text-muted">' . date('H:i:s', strtotime($row['timestamp'])) . '</small>';
                            echo '</td>';
                            echo '<td>';
                            echo '<button class="btn btn-sm btn-outline-primary" onclick="showDetails(' . $row['id'] . ')" title="View Details">';
                            echo '<i class="fas fa-eye"></i>';
                            echo '</button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                        
                        // Show total count
                        $count_query = "SELECT COUNT(*) as total FROM user_activity ua $where_clause";
                        $count_stmt = $conn->prepare($count_query);
                        if ($count_stmt) {
                            if ($where_conditions) {
                                $count_stmt->bind_param(substr($types, 0, -1), ...array_slice($params, 0, -1));
                            }
                            $count_stmt->execute();
                            $count_result = $count_stmt->get_result();
                            $total = $count_result->fetch_assoc()['total'];
                            
                            echo '<div class="mt-3">';
                            echo '<small class="text-muted">Showing ' . $result->num_rows . ' of ' . $total . ' total records</small>';
                            echo '</div>';
                        }
                        
                    } else {
                        echo '<div class="alert alert-info">';
                        echo '<i class="fas fa-info-circle me-2"></i>';
                        echo 'No activity records found for the selected criteria.';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert alert-danger">';
                    echo 'Error preparing query: ' . htmlspecialchars($conn->error);
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-warning">';
                echo '<h6>Activity Logging Not Available</h6>';
                echo 'The <code>user_activity</code> table does not exist. Activity logging may not be configured.';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Activity Details Modal -->
<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="activityDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function showDetails(activityId) {
    // Simple details display - you can enhance this to fetch more details via AJAX
    document.getElementById('activityDetails').innerHTML = '<p>Activity ID: ' + activityId + '</p><p>Detailed information would be loaded here...</p>';
    new bootstrap.Modal(document.getElementById('activityModal')).show();
}

// Auto-refresh every 30 seconds
setInterval(function() {
    if (!document.querySelector('.modal.show')) {
        window.location.reload();
    }
}, 30000);
</script>

<?php include 'admin-footer.php'; ?>
