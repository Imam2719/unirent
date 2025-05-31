<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch notifications (sample: based on rental status changes)
$stmt = $conn->prepare("
    SELECT rentals.id, equipment.name AS equipment_name, rentals.status, rentals.updated_at 
    FROM rentals 
    JOIN equipment ON rentals.equipment_id = equipment.id 
    WHERE rentals.user_id = ? 
    ORDER BY rentals.updated_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>Notifications</h2>

<?php if ($result->num_rows === 0): ?>
    <p>No notifications available.</p>
<?php else: ?>
    <ul class="notification-list">
        <?php while ($row = $result->fetch_assoc()): ?>
            <li>
                Equipment <strong><?php echo htmlspecialchars($row['equipment_name']); ?></strong> 
                has been <strong><?php echo ucfirst($row['status']); ?></strong> 
                (Updated: <?php echo $row['updated_at']; ?>)
            </li>
        <?php endwhile; ?>
    </ul>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
