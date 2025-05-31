<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get equipment ID from URL
$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get equipment details
$equipment = getEquipmentById($conn, $equipment_id);

// Redirect if equipment not found
if (!$equipment) {
    header('Location: browse.php');
    exit;
}

// Get equipment provenance
$provenance = getEquipmentProvenance($conn, $equipment_id);

// Get similar equipment
$similar_equipment = [];
$sql = "SELECT e.*, c.name as category_name, u.first_name, u.last_name, u.user_type
        FROM equipment e
        JOIN categories c ON e.category_id = c.id
        JOIN users u ON e.owner_id = u.id
        WHERE e.category_id = ? AND e.id != ? AND e.status = " . STATUS_AVAILABLE . "
        ORDER BY RAND()
        LIMIT 4";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $equipment['category_id'], $equipment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $similar_equipment[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $equipment['name']; ?> - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <section class="equipment-details">
            <div class="container">
                <div class="breadcrumbs">
                    <a href="index.php">Home</a> &gt;
                    <a href="browse.php">Browse</a> &gt;
                    <a href="browse.php?category=<?php echo $equipment['category_id']; ?>"><?php echo $equipment['category_name']; ?></a> &gt;
                    <span><?php echo $equipment['name']; ?></span>
                </div>
                
                <div class="equipment-layout">
                    <div class="equipment-gallery">
                        <div class="equipment-main-image">
                            <?php if ($equipment['image']): ?>
                                <img src="<?php echo $equipment['image']; ?>" alt="<?php echo $equipment['name']; ?>">
                            <?php else: ?>
                                <img src="assets/images/placeholder.jpg" alt="<?php echo $equipment['name']; ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="equipment-thumbnails">
                            <?php if ($equipment['image']): ?>
                                <div class="equipment-thumbnail active" data-src="<?php echo $equipment['image']; ?>">
                                    <img src="<?php echo $equipment['image']; ?>" alt="<?php echo $equipment['name']; ?>">
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($equipment['image2']): ?>
                                <div class="equipment-thumbnail" data-src="<?php echo $equipment['image2']; ?>">
                                    <img src="<?php echo $equipment['image2']; ?>" alt="<?php echo $equipment['name']; ?>">
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($equipment['image3']): ?>
                                <div class="equipment-thumbnail" data-src="<?php echo $equipment['image3']; ?>">
                                    <img src="<?php echo $equipment['image3']; ?>" alt="<?php echo $equipment['name']; ?>">
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!$equipment['image']): ?>
                                <div class="equipment-thumbnail active" data-src="assets/images/placeholder.jpg">
                                    <img src="assets/images/placeholder.jpg" alt="<?php echo $equipment['name']; ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="equipment-info">
                        <div class="equipment-header">
                            <h1><?php echo $equipment['name']; ?></h1>
                            <div class="equipment-meta">
                                <div class="equipment-category">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo $equipment['category_name']; ?></span>
                                </div>
                                <div class="equipment-owner">
                                    <i class="fas fa-user"></i>
                                    <span>
                                        <?php echo $equipment['first_name'] . ' ' . substr($equipment['last_name'], 0, 1) . '.'; ?>
                                        <?php if ($equipment['user_type'] == ROLE_ADMIN): ?>
                                            <span class="badge university">University</span>
                                        <?php else: ?>
                                            <span class="badge student">Student</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="equipment-price">
                            <?php if ($equipment['daily_rate'] > 0): ?>
                                <h2>$<?php echo number_format($equipment['daily_rate'], 2); ?> <span>/ day</span></h2>
                            <?php else: ?>
                                <h2>Free</h2>
                            <?php endif; ?>
                        </div>
                        
                        <div class="equipment-description">
                            <h3>Description</h3>
                            <p><?php echo nl2br($equipment['description']); ?></p>
                        </div>
                        
                        <div class="equipment-specs">
                            <h3>Specifications</h3>
                            <ul>
                                <?php if ($equipment['brand']): ?>
                                    <li><strong>Brand:</strong> <?php echo $equipment['brand']; ?></li>
                                <?php endif; ?>
                                
                                <?php if ($equipment['model']): ?>
                                    <li><strong>Model:</strong> <?php echo $equipment['model']; ?></li>
                                <?php endif; ?>
                                
                                <?php if ($equipment['condition']): ?>
                                    <li><strong>Condition:</strong> <?php echo $equipment['condition']; ?></li>
                                <?php endif; ?>
                                
                                <?php if ($equipment['location']): ?>
                                    <li><strong>Pickup Location:</strong> <?php echo $equipment['location']; ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <div class="equipment-actions">
                            <a href="rent.php?id=<?php echo $equipment['id']; ?>" class="btn btn-primary btn-block">Rent Now</a>
                            
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="btn btn-outline btn-block add-to-wishlist" data-id="<?php echo $equipment['id']; ?>">
                                    <i class="far fa-heart"></i> Add to Wishlist
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == ROLE_ADMIN): ?>
                <div class="provenance-section">
                    <h2>Data Provenance</h2>
                    <div class="provenance-timeline">
                        <?php foreach($provenance as $record): ?>
                            <div class="provenance-item">
                                <div class="provenance-icon">
                                    <?php if ($record['action'] == 'created'): ?>
                                        <i class="fas fa-plus-circle"></i>
                                    <?php elseif ($record['action'] == 'updated'): ?>
                                        <i class="fas fa-edit"></i>
                                    <?php elseif ($record['action'] == 'rental_created'): ?>
                                        <i class="fas fa-handshake"></i>
                                    <?php elseif ($record['action'] == 'rental_completed'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-history"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="provenance-content">
                                    <div class="provenance-header">
                                        <h4>
                                            <?php 
                                            switch($record['action']) {
                                                case 'created':
                                                    echo 'Equipment Added';
                                                    break;
                                                case 'updated':
                                                    echo 'Equipment Updated';
                                                    break;
                                                case 'rental_created':
                                                    echo 'Rental Requested';
                                                    break;
                                                case 'rental_approved':
                                                    echo 'Rental Approved';
                                                    break;
                                                case 'rental_rejected':
                                                    echo 'Rental Rejected';
                                                    break;
                                                case 'rental_completed':
                                                    echo 'Rental Completed';
                                                    break;
                                                default:
                                                    echo ucfirst(str_replace('_', ' ', $record['action']));
                                            }
                                            ?>
                                        </h4>
                                        <span class="provenance-time">
                                            <?php echo date('M d, Y h:i A', strtotime($record['timestamp'])); ?>
                                        </span>
                                    </div>
                                    <p>
                                        <?php echo $record['first_name'] . ' ' . $record['last_name']; ?>
                                        <?php 
                                        switch($record['action']) {
                                            case 'created':
                                                echo ' added this equipment to the system.';
                                                break;
                                            case 'updated':
                                                echo ' updated the equipment details.';
                                                break;
                                            case 'rental_created':
                                                echo ' requested to rent this equipment.';
                                                break;
                                            case 'rental_approved':
                                                echo ' approved the rental request.';
                                                break;
                                            case 'rental_rejected':
                                                echo ' rejected the rental request.';
                                                break;
                                            case 'rental_completed':
                                                echo ' marked the rental as completed.';
                                                break;
                                            default:
                                                echo ' performed action: ' . str_replace('_', ' ', $record['action']);
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (count($similar_equipment) > 0): ?>
                <div class="similar-equipment">
                    <h2>Similar Equipment</h2>
                    <div class="equipment-grid">
                        <?php foreach($similar_equipment as $item): ?>
                            <?php include 'includes/equipment-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
