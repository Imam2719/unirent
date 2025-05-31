<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];
$success = false;

// ✅ Allow edit only if student rented this equipment
$stmt = $conn->prepare("
    SELECT e.*
    FROM equipment e
    JOIN rentals r ON r.equipment_id = e.id
    WHERE e.id = ? AND r.user_id= ?
    LIMIT 1
");
$stmt->bind_param("ii", $equipment_id, $user_id);

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: my-equipment.php'); // deny access if not rented by this student
    exit;
}

$equipment = $result->fetch_assoc();
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $daily_rate = floatval($_POST['daily_rate'] ?? 0);

    if ($name === '') $errors[] = 'Name is required';
    if ($description === '') $errors[] = 'Description is required';
    if ($category_id <= 0) $errors[] = 'Select a valid category';
    if ($daily_rate < 0) $errors[] = 'Daily rate cannot be negative';

    // ✅ Handle image upload
    $image_path = $equipment['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024;

        if (!in_array($_FILES['image']['type'], $allowed)) {
            $errors[] = 'Invalid image format';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'Image too large (max 5MB)';
        } else {
            $upload_dir = 'uploads/equipment/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['image']['name']);
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                if (!empty($equipment['image']) && file_exists($equipment['image'])) {
                    unlink($equipment['image']);
                }
                $image_path = $target_path;
            } else {
                $errors[] = 'Failed to upload image';
            }
        }
    }

    // ✅ Update equipment
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE equipment 
            SET name = ?, description = ?, category_id = ?, daily_rate = ?, image = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssidsi", $name, $description, $category_id, $daily_rate, $image_path, $equipment_id);

        if ($stmt->execute()) {
            $success = true;
            $equipment = $conn->query("SELECT * FROM equipment WHERE id = $equipment_id")->fetch_assoc();

            // Optional provenance logging
            if (function_exists('trackProvenance')) {
                trackProvenance($conn, 'equipment', $equipment_id, 'student_equipment_update', null, json_encode([
                    'editor_id' => $user_id,
                    'changes' => [
                        'name' => $name,
                        'description' => $description,
                        'category_id' => $category_id,
                        'daily_rate' => $daily_rate,
                        'image' => $image_path
                    ]
                ]));
            }
        } else {
            $errors[] = 'Update failed: ' . $stmt->error;
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<main>
    <section class="edit-equipment">
        <div class="container">
            <h2>Edit Rented Equipment</h2>

            <?php if ($success): ?>
                <div class="alert alert-success">Equipment updated successfully!</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="edit-equipment.php?id=<?= $equipment_id ?>" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($equipment['name']) ?>" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $equipment['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" required><?= htmlspecialchars($equipment['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Daily Rate ($)</label>
                    <input type="number" step="0.01" name="daily_rate" value="<?= htmlspecialchars($equipment['daily_rate']) ?>" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Current Image</label><br>
                    <?php if ($equipment['image'] && file_exists($equipment['image'])): ?>
                        <img src="<?= $equipment['image'] ?>" class="img-thumbnail" style="max-height: 120px;">
                    <?php else: ?>
                        <p>No image available</p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Upload New Image</label>
                    <input type="file" name="image" class="form-control">
                </div>

                <div class="form-actions mt-3">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="my-equipment.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
