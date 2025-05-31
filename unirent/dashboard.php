<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


$first_name = $_SESSION['first_name'] ?? 'Student';
$last_name = $_SESSION['last_name'] ?? '';
$email = $_SESSION['email'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'student';


if (!defined('RENTAL_APPROVED')) define('RENTAL_APPROVED', 'approved');
if (!defined('RENTAL_PENDING')) define('RENTAL_PENDING', 'pending');
if (!defined('ROLE_ADMIN')) define('ROLE_ADMIN', 'admin');


$active_rentals = getUserRentals($conn, $_SESSION['user_id'], RENTAL_APPROVED);


$pending_rentals = getUserRentals($conn, $_SESSION['user_id'], RENTAL_PENDING);


$user_equipment = [];
$sql = "SELECT e.*, c.name as category_name
        FROM equipment e
        JOIN categories c ON e.category_id = c.id
        WHERE e.owner_id = ?
        ORDER BY e.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $user_equipment[] = $row;
}


$activity = [];
$sql = "SELECT dp.*, e.name as equipment_name
        FROM data_provenance dp
        JOIN equipment e ON dp.equipment_id = e.id
        WHERE dp.user_id = ? OR e.owner_id = ?
        ORDER BY dp.timestamp DESC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $activity[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<main>
    <section class="dashboard">
        <div class="container">
            

            <div class="dashboard-grid">
                <div class="dashboard-sidebar">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="user-info">
                            <h3><?= htmlspecialchars($first_name . ' ' . $last_name) ?></h3>
                            <p><?= htmlspecialchars($email) ?></p>
                            <p class="user-type"><?= ($user_type == ROLE_ADMIN) ? 'University Admin' : 'Student' ?></p>
                        </div>
                    </div>

                    <nav class="dashboard-nav">
                        <ul>
                            <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="my-rentals.php"><i class="fas fa-list"></i> My Rentals</a></li>
                            <li><a href="my-equipment.php"><i class="fas fa-camera"></i> My Equipment</a></li>
                            <li><a href="add-equipment.php"><i class="fas fa-plus-circle"></i> Add Equipment</a></li>
                            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                            <?php if ($user_type == ROLE_ADMIN): ?>
                                <li><a href="admin/index.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                            <?php endif; ?>
                            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </nav>
                </div>

                <div class="dashboard-content">
                    <div class="dashboard-stats">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                            <div class="stat-info">
                                <h3><?= count($active_rentals) ?></h3>
                                <p>Active Rentals</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-clock"></i></div>
                            <div class="stat-info">
                                <h3><?= count($pending_rentals) ?></h3>
                                <p>Pending Requests</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-camera"></i></div>
                            <div class="stat-info">
                                <h3><?= count($user_equipment) ?></h3>
                                <p>My Equipment</p>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-sections">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Active Rentals</h2>
                                <a href="my-rentals.php" class="btn btn-sm">View All</a>
                            </div>

                            <?php if ($active_rentals): ?>
                                <div class="rental-list">
                                    <?php foreach ($active_rentals as $rental): ?>
                                        <div class="rental-item">
                                            <div class="rental-image">
                                                <img src="<?= $rental['image'] ?: 'assets/images/placeholder.jpg' ?>" alt="<?= htmlspecialchars($rental['equipment_name']) ?>">
                                            </div>
                                            <div class="rental-details">
                                                <h3><?= htmlspecialchars($rental['equipment_name']) ?></h3>
                                                <div class="rental-meta">
                                                    <div class="rental-dates">
                                                        <i class="fas fa-calendar"></i>
                                                        <span><?= date('M d', strtotime($rental['start_date'])) ?> - <?= date('M d, Y', strtotime($rental['end_date'])) ?></span>
                                                    </div>
                                                    <div class="rental-owner">
                                                        <i class="fas fa-user"></i>
                                                        <span>From: <?= htmlspecialchars($rental['first_name'] . ' ' . substr($rental['last_name'], 0, 1) . '.') ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="rental-actions">
                                                <a href="rental-details.php?id=<?= $rental['id'] ?>" class="btn btn-sm">View Details</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-handshake"></i>
                                    <p>You don't have any active rentals.</p>
                                    <a href="browse.php" class="btn btn-primary">Browse Equipment</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="dashboard-section">
                            <div class="section-header">
                                <h2>Recent Activity</h2>
                            </div>

                            <?php if ($activity): ?>
                                <div class="activity-list">
                                    <?php foreach ($activity as $item): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">
                                                <i class="fas <?= match ($item['action']) {
                                                    'created' => 'fa-plus-circle',
                                                    'updated' => 'fa-edit',
                                                    'rental_created' => 'fa-handshake',
                                                    'rental_completed' => 'fa-check-circle',
                                                    default => 'fa-history'
                                                } ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-header">
                                                    <h4>
                                                        <?= match ($item['action']) {
                                                            'created' => 'Equipment Added',
                                                            'updated' => 'Equipment Updated',
                                                            'rental_created' => 'Rental Requested',
                                                            'rental_approved' => 'Rental Approved',
                                                            'rental_rejected' => 'Rental Rejected',
                                                            'rental_completed' => 'Rental Completed',
                                                            default => ucfirst(str_replace('_', ' ', $item['action']))
                                                        } ?>
                                                    </h4>
                                                    <span class="activity-time"><?= date('M d, Y h:i A', strtotime($item['timestamp'])) ?></span>
                                                </div>
                                                <p>
                                                    <?= match ($item['action']) {
                                                        'created' => 'You added ' . $item['equipment_name'] . ' to the system.',
                                                        'updated' => 'You updated ' . $item['equipment_name'] . ' details.',
                                                        'rental_created' => 'You requested to rent ' . $item['equipment_name'] . '.',
                                                        'rental_approved' => 'Your request to rent ' . $item['equipment_name'] . ' was approved.',
                                                        'rental_rejected' => 'Your request to rent ' . $item['equipment_name'] . ' was rejected.',
                                                        'rental_completed' => 'Your rental of ' . $item['equipment_name'] . ' was completed.',
                                                        default => 'Action performed on ' . $item['equipment_name'] . ': ' . str_replace('_', ' ', $item['action'])
                                                    } ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <p>No recent activity to display.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
