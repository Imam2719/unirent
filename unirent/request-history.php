<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'includes/header.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM rentals WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h1>My Rental Requests</h1>

<table class="table">
    <tr>
        <th>Equipment</th>
        <th>Status</th>
        <th>Date</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['equipment_id']; ?></td>
        <td><?php echo $row['status']; ?></td>
        <td><?php echo $row['created_at']; ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<?php include 'includes/footer.php'; ?>

