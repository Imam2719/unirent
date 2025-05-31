
<?php
// =====================================================
// ENHANCED provenance-queries.php
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
    log_activity('view_provenance_queries', 'Admin viewed query logs');
}

// Get filter parameters
$user_filter = $_GET['user_filter'] ?? '';
$query_type_filter = $_GET['query_type_filter'] ?? '';
$table_filter = $_GET['table_filter'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';
$limit = intval($_GET['limit'] ?? 50);

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-database me-2"></i>
            Query Provenance Logs
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="provenance-activity.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-history"></i> Activity Logs
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
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <label class="form-label">Query Type</label>
                    <select name="query_type_filter" class="form-select">
                        <option value="">All Types</option>
                        <option value="SELECT" <?php echo $query_type_filter == 'SELECT' ? 'selected' : ''; ?>>SELECT</option>
                        <option value="INSERT" <?php echo $query_type_filter == 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                        <option value="UPDATE" <?php echo $query_type_filter == 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                        <option value="DELETE" <?php echo $query_type_filter == 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Table</label>
                    <select name="table_filter" class="form-select">
                        <option value="">All Tables</option>
                        <option value="users" <?php echo $table_filter == 'users' ? 'selected' : ''; ?>>users</option>
                        <option value="equipment" <?php echo $table_filter == 'equipment' ? 'selected' : ''; ?>>equipment</option>
                        <option value="rentals" <?php echo $table_filter == 'rentals' ? 'selected' : ''; ?>>rentals</option>
                        <option value="categories" <?php echo $table_filter == 'categories' ? 'selected' : ''; ?>>categories</option>
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
                        <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                        <option value="1000" <?php echo $limit == 1000 ? 'selected' : ''; ?>>1000</option>
                        <option value="2000" <?php echo $limit == 2000 ? 'selected' : ''; ?>>2000</option>
                        <option value="5000" <?php echo $limit == 5000 ? 'selected' : ''; ?>>5000</option>
                        <option value="10000" <?php echo $limit == 10000 ? 'selected' : ''; ?>>10000</option>
                        <option value="20000" <?php echo $limit == 20000 ? 'selected' : ''; ?>>20000</option>
                        <option value="50000" <?php echo $limit == 50000 ? 'selected' : ''; ?>>50000</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="provenance-queries.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Query Logs -->
    <div class="card">
        <div class="card-header">
            <h5>Database Query Records</h5>
        </div>
        <div class="card-body">
            <?php
            // Check if query_provenance table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'query_provenance'");
            
            if ($table_check && $table_check->num_rows > 0) {
                // Build the query with filters
                $where_conditions = [];
                $params = [];
                $types = '';
                
                if ($user_filter) {
                    $where_conditions[] = "qp.user_id = ?";
                    $params[] = $user_filter;
                    $types .= 'i';
                }
                
                if ($query_type_filter) {
                    $where_conditions[] = "qp.query_type = ?";
                    $params[] = $query_type_filter;
                    $types .= 's';
                }
                
                if ($table_filter) {
                    $where_conditions[] = "qp.table_name = ?";
                    $params[] = $table_filter;
                    $types .= 's';
                }
                
                if ($date_filter) {
                    $where_conditions[] = "DATE(qp.timestamp) = ?";
                    $params[] = $date_filter;
                    $types .= 's';
                }
                
                $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                
                $query = "
                    SELECT qp.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as user_name,
                           u.user_type
                    FROM query_provenance qp
                    LEFT JOIN users u ON qp.user_id = u.id
                    $where_clause
                    ORDER BY qp.timestamp DESC
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
                        echo '<th>Type</th>';
                        echo '<th>Table</th>';
                        echo '<th>Query</th>';
                        echo '<th>Execution Time</th>';
                        echo '<th>Affected Rows</th>';
                        echo '<th>IP Address</th>';
                        echo '<th>Source</th>';
                        echo '<th>Timestamp</th>';
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
                                echo '<span class="text-muted">System</span>';
                            }
                            echo '</td>';
                            
                            $type_class = '';
                            switch($row['query_type']) {
                                case 'SELECT': $type_class = 'bg-info'; break;
                                case 'INSERT': $type_class = 'bg-success'; break;
                                case 'UPDATE': $type_class = 'bg-warning'; break;
                                case 'DELETE': $type_class = 'bg-danger'; break;
                                default: $type_class = 'bg-secondary';
                            }
                            echo '<td><span class="badge ' . $type_class . '">' . htmlspecialchars($row['query_type']) . '</span></td>';
                            
                            echo '<td>' . htmlspecialchars($row['table_name'] ?? 'N/A') . '</td>';
                            echo '<td>';
                            echo '<div style="max-width: 300px; max-height: 60px; overflow-y: auto;">';
                            echo '<small><code>' . htmlspecialchars(substr($row['query_text'], 0, 120)) . '...</code></small>';
                            echo '</div>';
                            echo '</td>';
                            echo '<td>';
                            $exec_time = floatval($row['execution_time']);
                            if ($exec_time > 0.1) {
                                echo '<span class="badge bg-warning">' . round($exec_time, 4) . 's</span>';
                            } else {
                                echo '<small>' . round($exec_time, 4) . 's</small>';
                            }
                            echo '</td>';
                            echo '<td>' . ($row['affected_rows'] ?? 0) . '</td>';
                            echo '<td><small>' . htmlspecialchars($row['ip_address'] ?? 'Unknown') . '</small></td>';
                            echo '<td>';
                            if ($row['file_path']) {
                                echo '<small>' . htmlspecialchars(basename($row['file_path']));
                                if ($row['line_number']) {
                                    echo ':' . $row['line_number'];
                                }
                                echo '</small>';
                            } else {
                                echo '<span class="text-muted">N/A</span>';
                            }
                            echo '</td>';
                            echo '<td>';
                            echo '<small>' . date('M d, Y', strtotime($row['timestamp'])) . '</small><br>';
                            echo '<small class="text-muted">' . date('H:i:s', strtotime($row['timestamp'])) . '</small>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                        
                        // Show summary statistics
                        echo '<div class="row mt-3">';
                        
                        $stats_query = "
                            SELECT 
                                query_type,
                                COUNT(*) as count,
                                AVG(execution_time) as avg_time,
                                SUM(affected_rows) as total_affected
                            FROM query_provenance qp
                            $where_clause
                            GROUP BY query_type
                            ORDER BY count DESC
                        ";
                        
                        $stats_stmt = $conn->prepare($stats_query);
                        if ($stats_stmt) {
                            if ($where_conditions) {
                                $stats_stmt->bind_param(substr($types, 0, -1), ...array_slice($params, 0, -1));
                            }
                            $stats_stmt->execute();
                            $stats_result = $stats_stmt->get_result();
                            
                            echo '<div class="col-md-8">';
                            echo '<h6>Query Statistics</h6>';
                            echo '<div class="row">';
                            
                            while ($stat = $stats_result->fetch_assoc()) {
                                echo '<div class="col-md-3 mb-2">';
                                echo '<div class="card text-center">';
                                echo '<div class="card-body py-2">';
                                echo '<h6 class="card-title mb-1">' . $stat['count'] . '</h6>';
                                echo '<small class="text-muted">' . $stat['query_type'] . '</small><br>';
                                echo '<small class="text-muted">Avg: ' . round($stat['avg_time'], 4) . 's</small>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                        
                    } else {
                        echo '<div class="alert alert-info">';
                        echo '<i class="fas fa-info-circle me-2"></i>';
                        echo 'No query records found for the selected criteria.';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert alert-danger">';
                    echo 'Error preparing query: ' . htmlspecialchars($conn->error);
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-warning">';
                echo '<h6>Query Logging Not Available</h6>';
                echo 'The <code>query_provenance</code> table does not exist. Query logging may not be configured.';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 30 seconds
setInterval(function() {
    window.location.reload();
}, 30000);
</script>

<?php include 'admin-footer.php'; ?>