<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user's equipment
$user_id = $_SESSION['user_id'];
$sql = "SELECT e.*, c.name as category_name 
        FROM equipment e
        JOIN categories c ON e.category_id = c.id
        WHERE e.owner_id = ?
        ORDER BY e.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$equipment = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Equipment - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <section class="my-equipment">
            <div class="container">
              
                
                <div class="dashboard-grid">
                    <div class="dashboard-sidebar">
                        <nav class="dashboard-nav">
                            <ul>
                                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a href="my-rentals.php"><i class="fas fa-list"></i> My Rentals</a></li>
                                <li class="active"><a href="my-equipment.php"><i class="fas fa-camera"></i> My Equipment</a></li>
                                <li><a href="add-equipment.php"><i class="fas fa-plus-circle"></i> Add Equipment</a></li>
                                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                                <?php if($_SESSION['user_type'] == ROLE_ADMIN): ?>
                                    <li><a href="admin/index.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                                <?php endif; ?>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </nav>
                    </div>
                    
                    <div class="dashboard-content">
                        <div class="section-header">
                            <h2>My Listed Equipment</h2>
                            <a href="add-equipment.php" class="btn btn-primary">Add New Equipment</a>
                        </div>
                        
                        <?php if (count($equipment) > 0): ?>
                            <div class="equipment-grid">
                                <?php foreach ($equipment as $item): ?>
                                    <div class="equipment-card">
                                        <div class="equipment-image">
                                            <?php if ($item['image']): ?>
                                                <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            <?php else: ?>
                                                <img src="assets/images/placeholder.jpg" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            <?php endif; ?>
                                        </div>
                                        <div class="equipment-info">
                                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                            <p class="equipment-category"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                            <div class="equipment-meta">
                                                <div class="equipment-status">
                                                    <span class="badge <?php echo getStatusBadgeClass($item['status']); ?>">
                                                        <?php echo getStatusText($item['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="equipment-price">
                                                    <?php if ($item['daily_rate'] > 0): ?>
                                                        $<?php echo number_format($item['daily_rate'], 2); ?>/day
                                                    <?php else: ?>
                                                        Free
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="equipment-actions">
                                                <a href="edit-equipment.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                <a href="equipment.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-camera"></i>
                                <p>You haven't listed any equipment yet.</p>
                                <a href="add-equipment.php" class="btn btn-primary">Add Equipment</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>