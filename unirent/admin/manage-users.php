<?php
// Enhanced manage-users.php with full provenance support
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

log_activity('view_manage_users', 'Admin viewed manage users page');

$success = '';
$error = '';

// Handle user actions with full provenance tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];
        $reason = $_POST['reason'] ?? null;
        $admin_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Prevent self-modification
        if ($user_id == $admin_id) {
            $error = 'You cannot modify your own permissions.';
        } else {
            switch ($action) {
                case 'promote':
                    if (promoteUser($conn, $user_id, $admin_id, $reason, $ip_address)) {
                        $success = 'User promoted to admin successfully with full audit trail.';
                        log_activity('user_promoted', "Promoted user ID: $user_id to admin");
                    } else {
                        $error = 'Failed to promote user.';
                    }
                    break;
                    
                case 'demote':
                    if (demoteUser($conn, $user_id, $admin_id, $reason, $ip_address)) {
                        $success = 'User demoted to student successfully with full audit trail.';
                        log_activity('user_demoted', "Demoted user ID: $user_id to student");
                    } else {
                        $error = 'Failed to demote user.';
                    }
                    break;
                    
                case 'activate':
                    setAuditContext($conn, $admin_id, $ip_address, $reason);
                    $stmt = $conn->prepare("UPDATE users SET status = 1 WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    if ($stmt->execute()) {
                        $success = 'User activated successfully.';
                        log_activity('user_activated', "Activated user ID: $user_id");
                    } else {
                        $error = 'Failed to activate user.';
                    }
                    break;
                    
                case 'deactivate':
                    setAuditContext($conn, $admin_id, $ip_address, $reason);
                    $stmt = $conn->prepare("UPDATE users SET status = 0 WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    if ($stmt->execute()) {
                        $success = 'User deactivated successfully.';
                        log_activity('user_deactivated', "Deactivated user ID: $user_id");
                    } else {
                        $error = 'Failed to deactivate user.';
                    }
                    break;
                    
                case 'delete':
                    // Check if user has any equipment assigned as owner
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM equipment WHERE owner_id = ?");
                    $check_stmt->bind_param("i", $user_id);
                    $check_stmt->execute();
                    $equipment_count = $check_stmt->get_result()->fetch_assoc()['count'];
                    
                    // Check if user has any rentals
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM rentals WHERE user_id = ?");
                    $check_stmt->bind_param("i", $user_id);
                    $check_stmt->execute();
                    $rental_count = $check_stmt->get_result()->fetch_assoc()['count'];
                    
                    if ($equipment_count > 0 || $rental_count > 0) {
                        $error = 'Cannot delete user that owns equipment or has rental history.';
                    } else {
                        setAuditContext($conn, $admin_id, $ip_address, $reason);
                        
                        // Use transaction for safe deletion
                        $conn->autocommit(false);
                        
                        try {
                            // Delete user's activity logs
                            $stmt = $conn->prepare("DELETE FROM admin_activity WHERE user_id = ?");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            
                            // Delete user's notifications
                            $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            
                            // Delete user's wishlist items
                            $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ?");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            
                            // Delete the user
                            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                            $stmt->bind_param("i", $user_id);
                            
                            if ($stmt->execute()) {
                                $conn->commit();
                                $success = 'User deleted successfully.';
                                log_activity('user_deleted', "Deleted user ID: $user_id");
                            } else {
                                throw new Exception('Failed to delete user');
                            }
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = 'Failed to delete user: ' . $e->getMessage();
                        } finally {
                            $conn->autocommit(true);
                        }
                    }
                    break;
            }
        }
    }
}

// Get filter parameters
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with logging
$where_conditions = [];
$params = [];
$types = '';

if ($type_filter) {
    $where_conditions[] = "user_type = ?";
    $params[] = $type_filter;
    $types .= 'i';
}

if ($status_filter !== '') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 'i';
}

if ($search) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT u.*,
           COUNT(r.id) as rental_count,
           COUNT(e.id) as equipment_count,
           MAX(r.created_at) as last_rental
    FROM users u
    LEFT JOIN rentals r ON u.id = r.user_id
    LEFT JOIN equipment e ON u.id = e.owner_id
    $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
";

if ($params) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-users me-2"></i>
            User Management
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="add-user.php" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Add User
                </a>
                <a href="user-report.php" class="btn btn-sm btn-outline-secondary">
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

    <!-- User Statistics Cards -->
    <div class="row mb-4">
        <?php
        // Get user statistics with logging
        $stats_query = "
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN user_type = 1 THEN 1 END) as students,
                COUNT(CASE WHEN user_type = 2 THEN 1 END) as admins,
                COUNT(CASE WHEN status = 1 THEN 1 END) as active_users
            FROM users
        ";
        $stats_result = $conn->query($stats_query);
        $stats = $stats_result->fetch_assoc();
        ?>
        
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo number_format($stats['total_users']); ?></h4>
                            <p class="mb-0">Total Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
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
                            <h4 class="mb-0"><?php echo number_format($stats['students']); ?></h4>
                            <p class="mb-0">Students</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-graduation-cap fa-2x"></i>
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
                            <h4 class="mb-0"><?php echo number_format($stats['admins']); ?></h4>
                            <p class="mb-0">Administrators</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-shield fa-2x"></i>
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
                            <h4 class="mb-0"><?php echo number_format($stats['active_users']); ?></h4>
                            <p class="mb-0">Active Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="1" <?php echo $type_filter == '1' ? 'selected' : ''; ?>>Students</option>
                        <option value="2" <?php echo $type_filter == '2' ? 'selected' : ''; ?>>Administrators</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="1" <?php echo $status_filter == '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $status_filter == '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, or student ID" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="manage-users.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>Student ID</th>
                            <th>Activity</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-3">
                                            <?php if ($user['profile_image']): ?>
                                                <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                     class="rounded-circle" width="40" height="40" alt="Profile">
                                            <?php else: ?>
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px; color: white; font-weight: bold;">
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['user_type'] == 2): ?>
                                        <span class="badge bg-warning"><i class="fas fa-user-shield"></i> Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-info"><i class="fas fa-graduation-cap"></i> Student</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['student_id'] ?: 'N/A'); ?></td>
                                <td>
                                    <small>
                                        <strong><?php echo $user['rental_count']; ?></strong> rentals<br>
                                        <strong><?php echo $user['equipment_count']; ?></strong> equipment
                                        <?php if ($user['last_rental']): ?>
                                            <br>Last: <?php echo date('M d, Y', strtotime($user['last_rental'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($user['status'] == 1): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-ban"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($user['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <div class="btn-group">
                                            <?php if ($user['user_type'] == 1): ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="showActionModal(<?php echo $user['id']; ?>, 'promote', '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                    <i class="fas fa-arrow-up"></i> Promote
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" 
                                                        onclick="showActionModal(<?php echo $user['id']; ?>, 'demote', '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                    <i class="fas fa-arrow-down"></i> Demote
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] == 1): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="showActionModal(<?php echo $user['id']; ?>, 'deactivate', '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="showActionModal(<?php echo $user['id']; ?>, 'activate', '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="showDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>', <?php echo $user['equipment_count']; ?>, <?php echo $user['rental_count']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-primary">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="modalUserId">
                    <input type="hidden" name="action" id="modalAction">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> This action will be <strong>permanently logged</strong> in the audit trail with your admin ID, timestamp, IP address, and reason.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <strong>Reason for Action</strong> 
                            <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="Enter a detailed reason for this permission change..." required></textarea>
                        <div class="form-text">
                            <strong>Required for compliance:</strong> All permission changes must include justification for audit purposes.
                        </div>
                    </div>
                    
                    <div id="actionDescription" class="alert alert-info">
                        <!-- Will be filled by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Confirm Action</button>
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
                <h5 class="modal-title">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <input type="hidden" name="action" value="delete">
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone and will be permanently logged in the audit trail.
                    </div>
                    
                    <div id="deleteWarning" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-ban me-2"></i>
                        <strong>Cannot Delete:</strong> This user owns equipment or has rental history.
                    </div>
                    
                    <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Deletion <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="Account deactivation request, policy violation, etc." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="deleteSubmitBtn">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showActionModal(userId, action, userName) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalAction').value = action;
    
    const titles = {
        'promote': 'Promote User to Administrator',
        'demote': 'Demote User to Student',
        'activate': 'Activate User Account',
        'deactivate': 'Deactivate User Account'
    };
    
    const descriptions = {
        'promote': `<strong>Promoting ${userName} to Administrator</strong><br>
                   This will grant full administrative privileges including user management, equipment management, and system configuration access.`,
        'demote': `<strong>Demoting ${userName} to Student</strong><br>
                  This will remove all administrative privileges and restrict access to standard student features only.`,
        'activate': `<strong>Activating ${userName}</strong><br>
                    This will restore full access to the system for this user account.`,
        'deactivate': `<strong>Deactivating ${userName}</strong><br>
                      This will temporarily suspend access to the system for this user account.`
    };
    
    const buttonClasses = {
        'promote': 'btn-warning',
        'demote': 'btn-secondary',
        'activate': 'btn-success',
        'deactivate': 'btn-danger'
    };
    
    document.getElementById('actionModalTitle').textContent = titles[action];
    document.getElementById('actionDescription').innerHTML = descriptions[action];
    
    const submitBtn = document.getElementById('modalSubmitBtn');
    submitBtn.className = 'btn ' + buttonClasses[action];
    submitBtn.textContent = 'Confirm ' + action.charAt(0).toUpperCase() + action.slice(1);
    
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}

function showDeleteModal(userId, userName, equipmentCount, rentalCount) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    
    const warning = document.getElementById('deleteWarning');
    const submitBtn = document.getElementById('deleteSubmitBtn');
    
    if (equipmentCount > 0 || rentalCount > 0) {
        warning.style.display = 'block';
        submitBtn.disabled = true;
    } else {
        warning.style.display = 'none';
        submitBtn.disabled = false;
    }
    
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include 'admin-footer.php'; ?>