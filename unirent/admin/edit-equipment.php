<?php
session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

$errors = [];
$success = false;
$equipment = null;
$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get equipment details
if ($equipment_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $equipment = $result->fetch_assoc();
    } else {
        header('Location: manage-equipment.php');
        exit;
    }
}

// Get categories and users
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = intval($_POST['category_id'] ?? 0);
    $owner_id = intval($_POST['owner_id'] ?? 0);
    $daily_rate = floatval($_POST['daily_rate'] ?? 0);
    $status = intval($_POST['status'] ?? STATUS_AVAILABLE);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validate inputs
    if (empty($name)) $errors[] = 'Equipment name is required';
    if (empty($description)) $errors[] = 'Description is required';
    if ($category_id <= 0) $errors[] = 'Please select a category';
    if ($owner_id <= 0) $errors[] = 'Please select an owner';
    if ($daily_rate < 0) $errors[] = 'Daily rate cannot be negative';
    
    // Handle image upload
    $image_path = $equipment['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = 'Invalid file type. Only JPEG, PNG, and GIF are allowed';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'File size exceeds the maximum limit of 5MB';
        } else {
            $upload_dir = '../uploads/equipment/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $filename = time() . '_' . basename($_FILES['image']['name']);
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                if (!empty($equipment['image']) && file_exists('../' . $equipment['image'])) {
                    unlink('../' . $equipment['image']);
                }
                $image_path = 'uploads/equipment/' . $filename;
            } else {
                $errors[] = 'Failed to upload image';
            }
        }
    }
    
    // If no errors, update equipment
    if (empty($errors)) {
        // Store old values for comparison
        $old_values = $equipment;
        
        $stmt = $conn->prepare("
            UPDATE equipment 
            SET name = ?, description = ?, category_id = ?, owner_id = ?, daily_rate = ?, 
                status = ?, is_featured = ?, image = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssiidissi", $name, $description, $category_id, $owner_id, 
                         $daily_rate, $status, $is_featured, $image_path, $equipment_id);
        
        if ($stmt->execute()) {
            // Create audit trail entry directly in data_provenance table
            try {
                $changes = [];
                if ($old_values['name'] != $name) {
                    $changes['name'] = ['old' => $old_values['name'], 'new' => $name];
                }
                if ($old_values['daily_rate'] != $daily_rate) {
                    $changes['daily_rate'] = ['old' => $old_values['daily_rate'], 'new' => $daily_rate];
                }
                if ($old_values['status'] != $status) {
                    $changes['status'] = ['old' => $old_values['status'], 'new' => $status];
                }
                if ($old_values['category_id'] != $category_id) {
                    $changes['category_id'] = ['old' => $old_values['category_id'], 'new' => $category_id];
                }
                if ($old_values['owner_id'] != $owner_id) {
                    $changes['owner_id'] = ['old' => $old_values['owner_id'], 'new' => $owner_id];
                }
                
                if (!empty($changes)) {
                    $details = json_encode([
                        'changes' => $changes,
                        'editor_id' => $_SESSION['user_id']
                    ]);
                    
                    $audit_stmt = $conn->prepare("
                        INSERT INTO data_provenance (equipment_id, user_id, action, timestamp, ip_address, details) 
                        VALUES (?, ?, 'equipment_updated', NOW(), ?, ?)
                    ");
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $audit_stmt->bind_param("iiss", $equipment_id, $_SESSION['user_id'], $ip_address, $details);
                    $audit_stmt->execute();
                }
            } catch (Exception $e) {
                error_log("Audit logging failed: " . $e->getMessage());
                // Continue anyway - don't fail the main operation
            }
            
            $success = true;
            // Refresh equipment data
            $equipment = $conn->query("SELECT * FROM equipment WHERE id = $equipment_id")->fetch_assoc();
        } else {
            $errors[] = 'Failed to update equipment: ' . $stmt->error;
        }
    }
}

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit Equipment</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="manage-equipment.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Equipment
                </a>
                <a href="view-equipment.php?id=<?php echo $equipment_id; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-eye"></i> View Equipment
                </a>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i> Equipment updated successfully!
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" action="edit-equipment.php?id=<?php echo $equipment_id; ?>" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Equipment Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($equipment['name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($equipment['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($equipment['description']); ?></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="owner_id" class="form-label">Owner</label>
                        <select class="form-select" id="owner_id" name="owner_id" required>
                            <option value="">Select Owner</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($equipment['owner_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="daily_rate" class="form-label">Daily Rate ($)</label>
                        <input type="number" class="form-control" id="daily_rate" name="daily_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($equipment['daily_rate']); ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="1" <?php echo ($equipment['status'] == 1) ? 'selected' : ''; ?>>Available</option>
                            <option value="2" <?php echo ($equipment['status'] == 2) ? 'selected' : ''; ?>>Rented</option>
                            <option value="3" <?php echo ($equipment['status'] == 3) ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="4" <?php echo ($equipment['status'] == 4) ? 'selected' : ''; ?>>Reserved</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="image" class="form-label">Equipment Image</label>
                        <?php if (!empty($equipment['image']) && file_exists('../' . $equipment['image'])): ?>
                            <div class="mb-2">
                                <img src="../<?php echo $equipment['image']; ?>" alt="<?php echo $equipment['name']; ?>" class="img-thumbnail" style="max-height: 100px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="image" name="image">
                        <div class="form-text">Upload a new image to replace the current one (JPEG, PNG, or GIF, max 5MB)</div>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" <?php echo ($equipment['is_featured'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_featured">Feature this equipment on the homepage</label>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="manage-equipment.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>