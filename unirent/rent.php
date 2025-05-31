<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit;
}

// Get equipment ID from URL
$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get equipment details
$equipment = getEquipmentById($conn, $equipment_id);

// Redirect if equipment not found or not available
if (!$equipment || $equipment['status'] != STATUS_AVAILABLE) {
    header('Location: browse.php');
    exit;
}

$error = '';
$success = '';

// Process rental form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    
    // Validate inputs
    if (empty($start_date) || empty($end_date) || empty($purpose)) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = 'Start date cannot be in the past.';
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $error = 'End date must be after start date.';
    } else {
        // Create rental
        $rental_id = createRental($conn, $equipment_id, $_SESSION['user_id'], $start_date, $end_date, $purpose);
        
        if ($rental_id) {
            $_SESSION['success'] = 'Your rental request has been submitted successfully. You will be notified once it is approved.';
            header('Location: my-rentals.php?success=1');
            exit;
        } else {
            $error = 'Failed to submit rental request. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent <?php echo htmlspecialchars($equipment['name']); ?> - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <section class="rent-section">
            <div class="container">
                <div class="breadcrumbs">
                    <a href="index.php">Home</a> &gt;
                    <a href="browse.php">Browse</a> &gt;
                    <a href="equipment-details.php?id=<?php echo $equipment['id']; ?>"><?php echo htmlspecialchars($equipment['name']); ?></a> &gt;
                    <span>Rent</span>
                </div>

                <div class="rent-layout">
                    <div class="rent-form-container">
                        <h1>Rent Equipment</h1>

                        <?php if ($error): ?>
                            <div class="notification error">
                                <i class="fas fa-exclamation-circle"></i>
                                <p><?php echo htmlspecialchars($error); ?></p>
                                <button class="notification-close"><i class="fas fa-times"></i></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="notification success">
                                <i class="fas fa-check-circle"></i>
                                <p><?php echo htmlspecialchars($success); ?></p>
                                <button class="notification-close"><i class="fas fa-times"></i></button>
                            </div>
                        <?php endif; ?>

                        <form action="rent.php?id=<?php echo $equipment['id']; ?>" method="post" class="rent-form validate">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="date-picker" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="date-picker" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                       value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="purpose">Purpose of Rental</label>
                                <textarea id="purpose" name="purpose" rows="4" required><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
                                <p class="form-hint">Briefly describe why you need this equipment and how you plan to use it.</p>
                            </div>
                            
                            <div class="rental-summary">
                                <h3>Rental Summary</h3>
                                <div class="summary-item">
                                    <span>Equipment:</span>
                                    <span><?php echo htmlspecialchars($equipment['name']); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span>Daily Rate:</span>
                                    <span><?php echo ($equipment['daily_rate'] > 0) ? '$' . number_format($equipment['daily_rate'], 2) : 'Free'; ?></span>
                                </div>
                                <div class="summary-item">
                                    <span>Rental Duration:</span>
                                    <span id="rental-duration">0 days</span>
                                </div>
                                <div class="summary-item total">
                                    <span>Estimated Total:</span>
                                    <span id="rental-total">$0.00</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox">
                                    <input type="checkbox" id="terms" name="terms" required>
                                    <label for="terms">I agree to the <a href="terms.php">Rental Terms</a> and will take responsibility for the equipment during the rental period.</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn-submit">Submit Rental Request</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="equipment-summary">
                        <div class="equipment-card">
                            <div class="equipment-image">
                                <?php if (!empty($equipment['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($equipment['image']); ?>" alt="<?php echo htmlspecialchars($equipment['name']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/placeholder.jpg" alt="<?php echo htmlspecialchars($equipment['name']); ?>">
                                <?php endif; ?>
                                <div class="equipment-badge">
                                    <?php if ($equipment['user_type'] == 2): ?>
                                        <span class="badge university">University</span>
                                    <?php else: ?>
                                        <span class="badge student">Student</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="equipment-info">
                                <h3><?php echo htmlspecialchars($equipment['name']); ?></h3>
                                <p class="equipment-category"><?php echo htmlspecialchars($equipment['category_name']); ?></p>
                                <div class="equipment-meta">
                                    <div class="equipment-price">
                                        <i class="fas fa-tag"></i>
                                        <span><?php echo ($equipment['daily_rate'] > 0) ? '$' . number_format($equipment['daily_rate'], 2) . '/day' : 'Free'; ?></span>
                                    </div>
                                    <div class="equipment-owner">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($equipment['first_name'] . ' ' . substr($equipment['last_name'], 0, 1) . '.'); ?></span>
                                    </div>
                                </div>
                                <p class="equipment-description"><?php echo htmlspecialchars(substr($equipment['description'], 0, 100) . '...'); ?></p>
                            </div>
                        </div>
                        
                        <div class="rental-info">
                            <h3>Rental Information</h3>
                            <ul>
                                <li><i class="fas fa-info-circle"></i> Rental requests are subject to approval by the equipment owner.</li>
                                <li><i class="fas fa-clock"></i> You will be notified once your request is approved or rejected.</li>
                                <li><i class="fas fa-map-marker-alt"></i> Pickup and return location: <?php echo htmlspecialchars($equipment['location'] ?? 'To be arranged with owner'); ?></li>
                                <li><i class="fas fa-exclamation-triangle"></i> Late returns may incur additional charges.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const rentalDuration = document.getElementById('rental-duration');
            const rentalTotal = document.getElementById('rental-total');
            const dailyRate = <?php echo $equipment['daily_rate'] ?: 0; ?>;
            
            function updateRentalSummary() {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                if (startDate && endDate && endDate > startDate) {
                    const diffTime = Math.abs(endDate - startDate);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    rentalDuration.textContent = diffDays + ' day' + (diffDays !== 1 ? 's' : '');
                    
                    const total = diffDays * dailyRate;
                    rentalTotal.textContent = dailyRate > 0 ? '$' + total.toFixed(2) : 'Free';
                } else {
                    rentalDuration.textContent = '0 days';
                    rentalTotal.textContent = '$0.00';
                }
            }
            
            startDateInput.addEventListener('change', updateRentalSummary);
            endDateInput.addEventListener('change', updateRentalSummary);
            
            // Dismiss notifications
            document.querySelectorAll('.notification-close').forEach(btn => {
                btn.addEventListener('click', () => {
                    btn.parentElement.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>