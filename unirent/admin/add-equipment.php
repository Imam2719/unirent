<?php
// Start session and check admin access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/provenance/activity_logger.php';
require_once __DIR__ . '/includes/provenance/query_logger.php';


// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}
log_activity('add_equipment', 'Admin viewed add equipment page');

$errors = [];
$success = false;

// Get categories
$categories = [];
$categories_result = log_query("SELECT id, name FROM categories ORDER BY name", 'SELECT', 'categories');
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Get users for owner selection
$users = [];
$users_result = log_query("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name", 'SELECT', 'users');
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
     log_activity('equipment_form_submit', 'Admin submitted equipment form');
  
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $owner_id = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : 0;
    $daily_rate = isset($_POST['daily_rate']) ? floatval($_POST['daily_rate']) : 0;
    $status = isset($_POST['status']) ? intval($_POST['status']) : STATUS_AVAILABLE;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validate inputs
    if (empty($name)) {
        $errors[] = 'Equipment name is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if ($category_id <= 0) {
        $errors[] = 'Please select a category';
    }
    
    if ($owner_id <= 0) {
        $errors[] = 'Please select an owner';
    }
    
    if ($daily_rate < 0) {
        $errors[] = 'Daily rate cannot be negative';
    }
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = 'Invalid file type. Only JPEG, PNG, and GIF are allowed';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'File size exceeds the maximum limit of 5MB';
        } else {
            $upload_dir = '../uploads/equipment/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = time() . '_' . basename($_FILES['image']['name']);
            
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = 'uploads/equipment/' . $filename;
            } else {
                $errors[] = 'Failed to upload image';
            }
        }
    }
    
    // If no errors, add equipment
    if (empty($errors)) {
     $stmt = prepare_logged($conn, "
            INSERT INTO equipment (name, description, category_id, owner_id, daily_rate, status, is_featured, image, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", 'INSERT', 'equipment');
        
        $stmt->bind_param("ssiidsss", $name, $description, $category_id, $owner_id, $daily_rate, $status, $is_featured, $image_path);
        
        if ($stmt->execute()) {
            $equipment_id = $stmt->insert_id;
           
           log_activity('equipment_created', "Created equipment: $name (ID: $equipment_id)");
            
            // Create provenance record
            createProvenanceRecord($conn, $equipment_id, $_SESSION['user_id'], 'equipment_created');
            
            $success = true;
        } else {
          log_activity('equipment_create_failed', "Failed to create equipment: " . $stmt->error);
            $errors[] = 'Failed to add equipment: ' . $stmt->error; }
        
        $stmt->close();
    }
}

// Include shared layout
include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add New Equipment</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="manage-equipment.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Equipment
                </a>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i> Equipment added successfully!
            <a href="manage-equipment.php" class="alert-link">Return to equipment list</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" action="add-equipment.php" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Equipment Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="owner_id" class="form-label">Owner</label>
                        <select class="form-select" id="owner_id" name="owner_id" required>
                            <option value="">Select Owner</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['owner_id']) && $_POST['owner_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="daily_rate" class="form-label">Daily Rate ($)</label>
                        <input type="number" class="form-control" id="daily_rate" name="daily_rate" min="0" step="0.01" value="<?php echo isset($_POST['daily_rate']) ? htmlspecialchars($_POST['daily_rate']) : '0.00'; ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="<?php echo STATUS_AVAILABLE; ?>" <?php echo (!isset($_POST['status']) || $_POST['status'] == STATUS_AVAILABLE) ? 'selected' : ''; ?>>Available</option>
                            <option value="<?php echo STATUS_RENTED; ?>" <?php echo (isset($_POST['status']) && $_POST['status'] == STATUS_RENTED) ? 'selected' : ''; ?>>Rented</option>
                            <option value="<?php echo STATUS_MAINTENANCE; ?>" <?php echo (isset($_POST['status']) && $_POST['status'] == STATUS_MAINTENANCE) ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="<?php echo STATUS_RESERVED; ?>" <?php echo (isset($_POST['status']) && $_POST['status'] == STATUS_RESERVED) ? 'selected' : ''; ?>>Reserved</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="image" class="form-label">Equipment Image</label>
                        <input type="file" class="form-control" id="image" name="image">
                        <div class="form-text">Upload an image of the equipment (JPEG, PNG, or GIF, max 5MB)</div>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" <?php echo (isset($_POST['is_featured'])) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_featured">Feature this equipment on the homepage</label>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="manage-equipment.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>