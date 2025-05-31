<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get rental ID from URL
$rental_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get rental details
$rental = getRentalDetails($conn, $rental_id, $_SESSION['user_id']);

// Redirect if rental not found or doesn't belong to user
if (!$rental) {
    $_SESSION['error'] = 'Rental not found or you do not have permission to view it.';
    header('Location: rentals.php'); // Redirect to a different page
    exit;
}

// Define rental status constants if not already defined
if (!defined('RENTAL_PENDING')) define('RENTAL_PENDING', 1);
if (!defined('RENTAL_APPROVED')) define('RENTAL_APPROVED', 2);
if (!defined('RENTAL_COMPLETED')) define('RENTAL_COMPLETED', 4);
if (!defined('RENTAL_CANCELLED')) define('RENTAL_CANCELLED', 5);
if (!defined('RENTAL_REJECTED')) define('RENTAL_REJECTED', 3);

// Function to get status name
function getRentalStatusName($status) {
    switch ($status) {
        case RENTAL_PENDING: return 'Pending';
        case RENTAL_APPROVED: return 'Approved';
        case RENTAL_COMPLETED: return 'Completed';
        case RENTAL_CANCELLED: return 'Cancelled';
        case RENTAL_REJECTED: return 'Rejected';
        default: return 'Unknown';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Details - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Additional styles to enhance the rental details page */
        .rental-details-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .rental-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-top: 1.5rem;
        }
        
        .rental-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .rental-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--dark-color);
        }
        
        .rental-status {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .rental-status.pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        .rental-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            padding: 1.5rem;
        }
        
        @media (min-width: 992px) {
            .rental-content {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .rental-section {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1.25rem;
        }
        
        .rental-section h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .rental-section h3 i {
            color: var(--primary-color);
        }
        
        .rental-meta {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .meta-item {
            background: white;
            padding: 0.75rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .meta-item i {
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .date-range {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .date-item {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .date-item i {
            font-size: 1.25rem;
            color: var(--primary-color);
        }
        
        .date-item div strong {
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .rental-actions {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            border-top: 1px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .breadcrumbs {
            font-size: 0.9rem;
            color: var(--gray-color);
            margin-bottom: 1rem;
        }
        
        .breadcrumbs a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumbs span {
            color: var(--dark-color);
        }
        
        .rental-image {
            width: 100%;
            height: 250px;
            overflow: hidden;
            border-radius: 8px;
        }
        
        .rental-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="rental-details-container">
            <div class="breadcrumbs">
                <a href="index.php">Home</a> &gt;
                <a href="my-rentals.php">My Rentals</a> &gt;
                <span>Rental Details</span>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php echo $_SESSION['error']; ?></p>
                    <button class="notification-close"><i class="fas fa-times"></i></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <h1>Rental Details</h1>
            
            <div class="rental-card">
                <div class="rental-header">
                    <h2><?php echo htmlspecialchars($rental['equipment_name']); ?></h2>
                    <span class="rental-status <?php echo strtolower(getRentalStatusName($rental['status'])); ?>">
                        <?php echo getRentalStatusName($rental['status']); ?>
                    </span>
                </div>
                
                <div class="rental-content">
                    <div>
                        <div class="rental-image">
                            <?php if (!empty($rental['image'])): ?>
                                <img src="<?php echo htmlspecialchars($rental['image']); ?>" alt="<?php echo htmlspecialchars($rental['equipment_name']); ?>">
                            <?php else: ?>
                                <img src="assets/images/placeholder.jpg" alt="<?php echo htmlspecialchars($rental['equipment_name']); ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="rental-section">
                            <h3><i class="fas fa-info-circle"></i> Rental Information</h3>
                            <div class="rental-meta">
                                <div class="meta-item">
                                    <i class="fas fa-hashtag"></i>
                                    <span>ID: #<?php echo $rental['id']; ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <span>Owner: <?php echo htmlspecialchars($rental['first_name'] . ' ' . substr($rental['last_name'], 0, 1) . '.'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Requested: <?php echo date('M d, Y', strtotime($rental['created_at'])); ?></span>
                                </div>
                                <?php if ($rental['total_amount'] > 0): ?>
                                <div class="meta-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span>Total: $<?php echo number_format($rental['total_amount'], 2); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="rental-section">
                            <h3><i class="fas fa-calendar-week"></i> Rental Period</h3>
                            <div class="date-range">
                                <div class="date-item">
                                    <i class="fas fa-calendar-day"></i>
                                    <div>
                                        <strong>Start Date</strong>
                                        <p><?php echo date('F j, Y', strtotime($rental['start_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="date-item">
                                    <i class="fas fa-calendar-day"></i>
                                    <div>
                                        <strong>End Date</strong>
                                        <p><?php echo date('F j, Y', strtotime($rental['end_date'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="rental-section">
                            <h3><i class="fas fa-clipboard"></i> Purpose</h3>
                            <p><?php echo nl2br(htmlspecialchars($rental['purpose'])); ?></p>
                        </div>
                        
                        <?php if ($rental['status'] == RENTAL_APPROVED): ?>
                            <div class="rental-section">
                                <h3><i class="fas fa-map-marker-alt"></i> Pickup Information</h3>
                                <p><i class="fas fa-map-pin"></i> Media Center, Building A</p>
                                <p><i class="fas fa-clock"></i> Available pickup times: 9AM - 5PM, Mon-Fri</p>
                                <p><i class="fas fa-id-card"></i> Please bring your student ID when picking up</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="rental-actions">
                    <?php if ($rental['status'] == RENTAL_PENDING): ?>
                        <form action="cancel-rental.php" method="post">
                            <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this rental request?')">
                                <i class="fas fa-times"></i> Cancel Request
                            </button>
                        </form>
                    <?php elseif ($rental['status'] == RENTAL_APPROVED): ?>
                        <form action="return-equipment.php" method="post">
                            <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-check"></i> Mark as Returned
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="my-rentals.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Rentals
                    </a>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>