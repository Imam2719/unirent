<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get categories for dropdown
$categories = [];
$stmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
if ($stmt) {
    $categories = $stmt->fetch_all(MYSQLI_ASSOC);
}

$message = '';
$message_type = '';
$name = $description = $brand = $model = $location = '';
$category_id = $daily_rate = 0;
$condition = 'good';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $daily_rate = floatval($_POST['daily_rate'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $condition = trim($_POST['condition'] ?? 'good');
    $location = trim($_POST['location'] ?? '');
    $image_path = '';

    // Handle file upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/equipment/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('equip_', true) . '.' . $file_ext;
        $target_path = $upload_dir . $file_name;

        // Validate image file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($file_ext), $allowed_types)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
            } else {
                $message = "Error uploading image. Please try again.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            $message_type = "error";
        }
    }

    if (empty($message) && (empty($name) || empty($description) || empty($category_id))) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    }

    if (empty($message)) {
        // Fix for bind_param issue - convert values to variables
        $owner_id = $_SESSION['user_id'];
        $status = STATUS_AVAILABLE;
        
        $stmt = $conn->prepare("
            INSERT INTO equipment 
            (name, description, category_id, daily_rate, brand, model, `condition`, location, owner_id, status, image, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Bind parameters directly (not by reference)
        $stmt->bind_param(
            "ssidssssiis",
            $name,
            $description,
            $category_id,
            $daily_rate,
            $brand,
            $model,
            $condition,
            $location,
            $owner_id,
            $status,
            $image_path
        );

        if ($stmt->execute()) {
            $equipment_id = $stmt->insert_id;
            createProvenanceRecord($conn, $equipment_id, $_SESSION['user_id'], 'created');
            
            $message = "Equipment added successfully!";
            $message_type = "success";
            
            // Clear form on success
            $name = $description = $brand = $model = $location = '';
            $category_id = $daily_rate = 0;
            $condition = 'good';
        } else {
            $message = "Error adding equipment. Please try again.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Equipment - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="add-equipment-page">
        <div class="container">
            

            <div class="content-wrapper">
                <div class="form-container">
                    <?php if ($message): ?>
                        <div class="notification <?php echo $message_type; ?>">
                            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <p><?php echo $message; ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="equipment-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="name">Name*</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required placeholder="Enter equipment name">
                        </div>

                        <div class="form-group">
                            <label for="description">Description*</label>
                            <textarea id="description" name="description" rows="4" required placeholder="Describe the equipment in detail"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="category_id">Category*</label>
                            <select id="category_id" name="category_id" required>
                                <option value="" disabled selected>Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="image">Equipment Image</label>
                            <input type="file" id="image" name="image" accept="image/*">
                            <p class="form-hint">Recommended size: 800x600px (JPG, PNG, GIF)</p>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="brand">Brand</label>
                                <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($brand); ?>" placeholder="e.g., Canon, Sony">
                            </div>

                            <div class="form-group">
                                <label for="model">Model</label>
                                <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($model); ?>" placeholder="e.g., EOS 5D Mark IV">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="condition">Condition</label>
                                <select id="condition" name="condition" class="styled-select">
                                    <option value="new" <?php echo $condition == 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="excellent" <?php echo $condition == 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                                    <option value="good" <?php echo $condition == 'good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="fair" <?php echo $condition == 'fair' ? 'selected' : ''; ?>>Fair</option>
                                    <option value="poor" <?php echo $condition == 'poor' ? 'selected' : ''; ?>>Poor</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="daily_rate">Daily Rate ($)</label>
                                <div class="input-with-icon">
                                    <span class="currency">$</span>
                                    <input type="number" id="daily_rate" name="daily_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($daily_rate); ?>" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="location">Pickup Location</label>
                            <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>" placeholder="Where will renters pick up the equipment?">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Equipment
                            </button>
                            <a href="my-equipment.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="sidebar">
                    <div class="sidebar-section">
                        <h3><i class="fas fa-camera"></i> UniRent</h3>
                        <p>The ultimate equipment rental platform for university students.</p>
                    </div>

                    <div class="sidebar-section">
                        <h3><i class="fas fa-link"></i> Quick Links</h3>
                        <ul class="quick-links">
                            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                            <li><a href="browse.php"><i class="fas fa-search"></i> Browse Equipment</a></li>
                            <li><a href="how-it-works.php"><i class="fas fa-question-circle"></i> How it Works</a></li>
                            <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                        </ul>
                    </div>

                    
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>