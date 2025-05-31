<?php
// Start session and check admin access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 2) {
    header('Location: ../login.php');
    exit;
}

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get user details
$user = null;
$stmt = $conn->prepare("
    SELECT * FROM users WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    // User not found, redirect to users list
    header('Location: manage-users.php');
    exit;
}

// Get user's equipment
$equipment_list = [];
$stmt = $conn->prepare("
    SELECT e.*, c.name as category_name
    FROM equipment e
    JOIN categories c ON e.category_id = c.id
    WHERE e.owner_id = ?
    ORDER BY e.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $equipment_list[] = $row;
}

// Get user's rentals
$rentals_list = [];
$stmt = $conn->prepare("
    SELECT r.*, e.name as equipment_name
    FROM rentals r
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.user_id= ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $rentals_list[] = $row;
}

// Include shared layout
include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">User Details</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="manage-users.php" class="btn btn-sm btn-outline-secondary">Back to Users</a>
                <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">Edit User</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">User Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-placeholder rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; font-size: 2.5rem;">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <h4 class="mt-3"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                        <p>
                            <?php if ($user['user_type'] == 2): ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Student</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <table class="table">
                        <tbody>
                            <tr>
                                <td>Email</td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                            </tr>
                            <tr>
                                <td>Student ID</td>
                                <td><?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td>Department</td>
                                <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td>Phone</td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td>Joined</td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-3">
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <?php if ($user['user_type'] != 2): ?>
                                <a href="promote-user.php?id=<?php echo $user['id']; ?>" class="btn btn-success">Promote to Admin</a>
                            <?php else: ?>
                                <a href="demote-user.php?id=<?php echo $user['id']; ?>" class="btn btn-info">Demote to Student</a>
                            <?php endif; ?>
                            <a href="delete-user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete User</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">User's Equipment (<?php echo count($equipment_list); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($equipment_list) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Daily Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipment_list as $equipment): ?>
                                        <tr>
                                            <td><?php echo $equipment['id']; ?></td>
                                            <td><?php echo htmlspecialchars($equipment['name']); ?></td>
                                            <td><?php echo htmlspecialchars($equipment['category_name']); ?></td>
                                            <td>
                                                <?php 
                                                    $status_class = '';
                                                    $status_text = '';
                                                    
                                                    switch ($equipment['status']) {
                                                        case STATUS_AVAILABLE:
                                                            $status_class = 'bg-success';
                                                            $status_text = 'Available';
                                                            break;
                                                        case STATUS_RENTED:
                                                            $status_class = 'bg-warning';
                                                            $status_text = 'Rented';
                                                            break;
                                                        case STATUS_MAINTENANCE:
                                                            $status_class = 'bg-danger';
                                                            $status_text = 'Maintenance';
                                                            break;
                                                        case STATUS_RESERVED:
                                                            $status_class = 'bg-info';
                                                            $status_text = 'Reserved';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-secondary';
                                                            $status_text = 'Unknown';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                            <td>$<?php echo number_format($equipment['daily_rate'], 2); ?></td>
                                            <td>
                                                <a href="view-equipment.php?id=<?php echo $equipment['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">This user has not added any equipment.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">User's Rentals (<?php echo count($rentals_list); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($rentals_list) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Equipment</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rentals_list as $rental): ?>
                                        <tr>
                                            <td><?php echo $rental['id']; ?></td>
                                            <td><?php echo htmlspecialchars($rental['equipment_name']); ?></td>
                                            <td><?php echo date('M d', strtotime($rental['start_date'])); ?> - <?php echo date('M d, Y', strtotime($rental['end_date'])); ?></td>
                                            <td>
                                                <?php 
                                                    $status_class = '';
                                                    $status_text = '';
                                                    
                                                    switch ($rental['status']) {
                                                        case RENTAL_PENDING:
                                                            $status_class = 'bg-warning';
                                                            $status_text = 'Pending';
                                                            break;
                                                        case RENTAL_APPROVED:
                                                            $status_class = 'bg-success';
                                                            $status_text = 'Approved';
                                                            break;
                                                        case RENTAL_REJECTED:
                                                            $status_class = 'bg-danger';
                                                            $status_text = 'Rejected';
                                                            break;
                                                        case RENTAL_COMPLETED:
                                                            $status_class = 'bg-info';
                                                            $status_text = 'Completed';
                                                            break;
                                                        case RENTAL_CANCELLED:
                                                            $status_class = 'bg-secondary';
                                                            $status_text = 'Cancelled';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-secondary';
                                                            $status_text = 'Unknown';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($rental['created_at'])); ?></td>
                                            <td>
                                                <a href="view-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">This user has not rented any equipment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>