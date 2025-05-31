<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get status filter
$status_filter = isset($_GET['status']) ? intval($_GET['status']) : null;

// Get user's rentals
$rentals = getUserRentals($conn, $_SESSION['user_id'], $status_filter);

// Check for success message
$success = isset($_GET['success']) ? true : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rentals - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <section class="my-rentals">
            <div class="container">
               
                
                <?php if ($success): ?>
                    <div class="notification success">
                        <i class="fas fa-check-circle"></i>
                        <p>Your rental request has been submitted successfully. You will be notified once it is approved.</p>
                        <button class="notification-close"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>
                
                <div class="dashboard-grid">
                    <div class="dashboard-sidebar">
                        <nav class="dashboard-nav">
                            <ul>
                                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li class="active"><a href="my-rentals.php"><i class="fas fa-list"></i> My Rentals</a></li>
                                <li><a href="my-equipment.php"><i class="fas fa-camera"></i> My Equipment</a></li>
                                <li><a href="add-equipment.php"><i class="fas fa-plus-circle"></i> Add Equipment</a></li>
                                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                                <?php if($_SESSION['user_type'] == ROLE_ADMIN): ?>
                                    <li><a href="admin/index.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                                <?php endif; ?>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </nav>
                        
                        <div class="filter-section">
                            <h3>Filter Rentals</h3>
                            <ul class="filter-options">
                                <li><a href="my-rentals.php" class="<?php echo !$status_filter ? 'active' : ''; ?>">All Rentals</a></li>
                                <li><a href="my-rentals.php?status=<?php echo RENTAL_PENDING; ?>" class="<?php echo $status_filter == RENTAL_PENDING ? 'active' : ''; ?>">Pending</a></li>
                                <li><a href="my-rentals.php?status=<?php echo RENTAL_APPROVED; ?>" class="<?php echo $status_filter == RENTAL_APPROVED ? 'active' : ''; ?>">Approved</a></li>
                                <li><a href="my-rentals.php?status=<?php echo RENTAL_COMPLETED; ?>" class="<?php echo $status_filter == RENTAL_COMPLETED ? 'active' : ''; ?>">Completed</a></li>
                                <li><a href="my-rentals.php?status=<?php echo RENTAL_REJECTED; ?>" class="<?php echo $status_filter == RENTAL_REJECTED ? 'active' : ''; ?>">Rejected</a></li>
                                <li><a href="my-rentals.php?status=<?php echo RENTAL_CANCELLED; ?>" class="<?php echo $status_filter == RENTAL_CANCELLED ? 'active' : ''; ?>">Cancelled</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="dashboard-content">
                        <?php if (count($rentals) > 0): ?>
                            <div class="rental-list full">
                                <?php foreach($rentals as $rental): ?>
                                    <div class="rental-item">
                                        <div class="rental-image">
                                            <?php if ($rental['image']): ?>
                                                <img src="<?php echo $rental['image']; ?>" alt="<?php echo $rental['equipment_name']; ?>">
                                            <?php else: ?>
                                                <img src="assets/images/placeholder.jpg" alt="<?php echo $rental['equipment_name']; ?>">
                                            <?php endif; ?>
                                            <div class="rental-status <?php echo strtolower(getRentalStatusName($rental['status'])); ?>">
                                                <?php echo getRentalStatusName($rental['status']); ?>
                                            </div>
                                        </div>
                                        <div class="rental-details">
                                            <h3><?php echo $rental['equipment_name']; ?></h3>
                                            <div class="rental-meta">
                                                <div class="rental-dates">
                                                    <i class="fas fa-calendar"></i>
                                                    <span><?php echo date('M d', strtotime($rental['start_date'])); ?> - <?php echo date('M d, Y', strtotime($rental['end_date'])); ?></span>
                                                </div>
                                                <div class="rental-owner">
                                                    <i class="fas fa-user"></i>
                                                    <span>From: <?php echo $rental['first_name'] . ' ' . substr($rental['last_name'], 0, 1) . '.'; ?></span>
                                                </div>
                                                <div class="rental-id">
                                                    <i class="fas fa-hashtag"></i>
                                                    <span>Rental ID: <?php echo $rental['id']; ?></span>
                                                </div>
                                            </div>
                                            <div class="rental-purpose">
                                                <p><strong>Purpose:</strong> <?php echo substr($rental['purpose'], 0, 100) . (strlen($rental['purpose']) > 100 ? '...' : ''); ?></p>
                                            </div>
                                        </div>
                                        <div class="rental-actions">
                                            <a href="rental-details.php?id=<?php echo $rental['id']; ?>" class="btn btn-sm">View Details</a>
                                            
                                            <?php if ($rental['status'] == RENTAL_PENDING): ?>
                                                <a href="cancel-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this rental request?')">Cancel Request</a>
                                            <?php elseif ($rental['status'] == RENTAL_APPROVED): ?>
                                                <a href="return-equipment.php?id=<?php echo $rental['id']; ?>" class="btn btn-sm btn-secondary">Return Equipment</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-list"></i>
                                <p>You don't have any rentals<?php echo $status_filter ? ' with this status' : ''; ?>.</p>
                                <a href="browse.php" class="btn btn-primary">Browse Equipment</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

<?php
// Helper function to get rental status name
function getRentalStatusName($status) {
    switch ($status) {
        case RENTAL_PENDING:
            return 'Pending';
        case RENTAL_APPROVED:
            return 'Approved';
        case RENTAL_REJECTED:
            return 'Rejected';
        case RENTAL_COMPLETED:
            return 'Completed';
        case RENTAL_CANCELLED:
            return 'Cancelled';
        default:
            return 'Unknown';
    }
}
?>
