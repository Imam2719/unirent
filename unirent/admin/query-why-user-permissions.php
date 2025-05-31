<?php
// =====================================================
// FILE: fixed-query-why-user-permissions.php
// Purpose: WHY Provenance - User Permission Changes (FIXED)
// =====================================================

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Handle form submission
$user_filter = $_GET['user_filter'] ?? '';
$date_range = $_GET['date_range'] ?? '30';

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2">
                <span class="badge bg-warning me-2">WHY</span>
                User Permission Changes Analysis
            </h1>
            <p class="text-muted">Understand justification for user role modifications</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">User</label>
                    <select name="user_filter" class="form-select">
                        <option value="">All Users</option>
                        <?php
                        $users = $conn->query("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name");
                        while ($row = $users->fetch_assoc()) {
                            $selected = ($user_filter == $row['id']) ? 'selected' : '';
                            echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date Range</label>
                    <select name="date_range" class="form-select">
                        <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 days</option>
                        <option value="all" <?php echo $date_range == 'all' ? 'selected' : ''; ?>>All time</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-search me-2"></i>Analyze Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-header">
            <h5>Permission Change Analysis</h5>
        </div>
        <div class="card-body">
            <?php
            // Check if audit_users table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'audit_users'");
            
            if ($table_check->num_rows == 0) {
                echo '<div class="alert alert-warning">';
                echo '<h6>Audit Table Not Found</h6>';
                echo 'The <code>audit_users</code> table does not exist. Please run the database setup script first.';
                echo '</div>';
            } else {
                // Execute WHY provenance query for user permissions
                $permission_query = "
                    SELECT 
                        CONCAT(target.first_name, ' ', target.last_name) AS affected_user,
                        target.email AS user_email,
                        CASE COALESCE(au.old_user_type, 1)
                            WHEN 1 THEN 'Student'
                            WHEN 2 THEN 'Admin'
                            ELSE 'Unknown'
                        END AS previous_role,
                        CASE au.new_user_type
                            WHEN 1 THEN 'Student'
                            WHEN 2 THEN 'Admin'
                            ELSE 'Unknown'
                        END AS new_role,
                        CASE 
                            WHEN COALESCE(au.old_user_type, 1) = 1 AND au.new_user_type = 2 THEN 'PROMOTION'
                            WHEN COALESCE(au.old_user_type, 2) = 2 AND au.new_user_type = 1 THEN 'DEMOTION'
                            ELSE 'CHANGE'
                        END AS change_type,
                        CONCAT(changer.first_name, ' ', changer.last_name) AS changed_by,
                        au.change_timestamp AS when_changed,
                        COALESCE(au.change_reason, 'No reason provided') AS justification,
                        au.ip_address AS change_location
                    FROM audit_users au
                    JOIN users target ON au.user_id = target.id
                    LEFT JOIN users changer ON au.changed_by = changer.id
                    WHERE (au.old_user_type != au.new_user_type OR au.old_user_type IS NULL)
                ";
                
                if (!empty($user_filter)) {
                    $permission_query .= " AND au.user_id = " . intval($user_filter);
                }
                
                if ($date_range !== 'all') {
                    $permission_query .= " AND au.change_timestamp >= DATE_SUB(NOW(), INTERVAL " . intval($date_range) . " DAY)";
                }
                
                $permission_query .= " ORDER BY au.change_timestamp DESC";
                
                $result = $conn->query($permission_query);
                
                if ($result && $result->num_rows > 0) {
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-striped">';
                    echo '<thead><tr>';
                    echo '<th>User</th><th>Email</th><th>Previous Role</th><th>New Role</th>';
                    echo '<th>Change Type</th><th>Changed By</th><th>When</th><th>Justification</th>';
                    echo '</tr></thead><tbody>';
                    
                    while ($row = $result->fetch_assoc()) {
                        $change_class = '';
                        switch($row['change_type']) {
                            case 'PROMOTION': $change_class = 'bg-success'; break;
                            case 'DEMOTION': $change_class = 'bg-danger'; break;
                            default: $change_class = 'bg-secondary'; break;
                        }
                        
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['affected_user']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['user_email']) . '</td>';
                        echo '<td><span class="badge bg-secondary">' . $row['previous_role'] . '</span></td>';
                        echo '<td><span class="badge bg-primary">' . $row['new_role'] . '</span></td>';
                        echo '<td><span class="badge ' . $change_class . '">' . $row['change_type'] . '</span></td>';
                        echo '<td>' . htmlspecialchars($row['changed_by'] ?? 'System') . '</td>';
                        echo '<td>' . date('M d, Y H:i:s', strtotime($row['when_changed'])) . '</td>';
                        echo '<td>' . htmlspecialchars($row['justification']) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table></div>';
                } else {
                    echo '<div class="alert alert-info">';
                    echo '<i class="fas fa-info-circle me-2"></i>';
                    echo 'No permission changes found for the selected criteria.';
                    echo '</div>';
                    
                    // Show how to generate test data
                    echo '<div class="alert alert-warning">';
                    echo '<h6>To Generate Test Data:</h6>';
                    echo '<ol>';
                    echo '<li>Go to <a href="manage-users.php">Manage Users</a></li>';
                    echo '<li>Promote a student to admin or demote an admin to student</li>';
                    echo '<li>The change will be logged automatically</li>';
                    echo '</ol>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>