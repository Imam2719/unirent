<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Profile update form
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $department = $_POST['department'] ?? '';
        $student_id = $_POST['student_id'] ?? '';
        
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            // Handle profile image upload
            $profile_image = $user['profile_image'];
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'assets/uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . basename($_FILES['profile_image']['name']);
                $target_path = $upload_dir . $file_name;
                
                // Check file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = $_FILES['profile_image']['type'];
                
                if (in_array($file_type, $allowed_types)) {
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                        // Delete old profile image if it exists
                        if ($profile_image && file_exists($profile_image)) {
                            unlink($profile_image);
                        }
                        $profile_image = $target_path;
                    } else {
                        $error_message = 'Failed to upload profile image';
                    }
                } else {
                    $error_message = 'Only JPG, PNG, and GIF files are allowed for profile images';
                }
            }
            
            if (empty($error_message)) {
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, department = ?, student_id = ?, profile_image = ? WHERE id = ?");
                $stmt->bind_param("sssssssi", $first_name, $last_name, $email, $phone, $department, $student_id, $profile_image, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = 'Profile updated successfully.';
                    // Update session variables
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $email;
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                } else {
                    $error_message = 'Failed to update profile. Please try again.';
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change form
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error_message = 'Current password is incorrect.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_message = 'Password changed successfully.';
            } else {
                $error_message = 'Failed to change password. Please try again.';
            }
        }
    } elseif (isset($_POST['update_preferences'])) {
        // Preferences form
        $notify_rental = isset($_POST['notify_rental']) ? 1 : 0;
        $notify_equipment = isset($_POST['notify_equipment']) ? 1 : 0;
        $notify_system = isset($_POST['notify_system']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE users SET notify_rental = ?, notify_equipment = ?, notify_system = ? WHERE id = ?");
        $stmt->bind_param("iiii", $notify_rental, $notify_equipment, $notify_system, $user_id);
        
        if ($stmt->execute()) {
            $success_message = 'Preferences updated successfully.';
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error_message = 'Failed to update preferences. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <section class="profile-section">
            <div class="container">
             
                <?php if ($success_message): ?>
                    <div class="notification success">
                        <i class="fas fa-check-circle"></i>
                        <p><?php echo $success_message; ?></p>
                        <button class="notification-close"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="notification error">
                        <i class="fas fa-exclamation-circle"></i>
                        <p><?php echo $error_message; ?></p>
                        <button class="notification-close"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>
                
                <div class="dashboard-grid">
                    <div class="dashboard-sidebar">
                        <nav class="dashboard-nav">
                            <ul>
                                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a href="my-rentals.php"><i class="fas fa-list"></i> My Rentals</a></li>
                                <li><a href="my-equipment.php"><i class="fas fa-camera"></i> My Equipment</a></li>
                                <li><a href="add-equipment.php"><i class="fas fa-plus-circle"></i> Add Equipment</a></li>
                                <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                                <?php if($_SESSION['user_type'] == ROLE_ADMIN): ?>
                                    <li><a href="admin/index.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                                <?php endif; ?>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </nav>
                    </div>
                    
                    <div class="dashboard-content">
                        <div class="profile-container">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <?php if ($user['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="profile-info">
                                    <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                                    <p class="user-type"><?php echo ($user['user_type'] == ROLE_ADMIN) ? 'University Admin' : 'Student'; ?></p>
                                </div>
                            </div>
                            
                            <div class="profile-tabs">
                                <button class="tab-button active" data-tab="personal">Personal Information</button>
                                <button class="tab-button" data-tab="security">Security</button>
                                <button class="tab-button" data-tab="preferences">Preferences</button>
                            </div>
                            
                            <div class="profile-content">
                                <!-- Personal Information Tab -->
                                <div class="tab-content active" id="personal">
                                    <form method="post" class="profile-form" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <label>Profile Picture</label>
                                            <div class="profile-image-upload">
                                                <?php if ($user['profile_image']): ?>
                                                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Current Profile Image" class="current-profile-image">
                                                <?php endif; ?>
                                                <input type="file" name="profile_image" accept="image/jpeg,image/png,image/gif">
                                                <small>Max size: 2MB (JPEG, PNG, GIF)</small>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>First Name</label>
                                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Last Name</label>
                                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Email Address</label>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Student ID</label>
                                            <input type="text" name="student_id" value="<?php echo htmlspecialchars($user['student_id'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Phone Number</label>
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Department</label>
                                            <input type="text" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Security Tab -->
                                <div class="tab-content" id="security">
                                    <div class="security-notice">
                                        <p><strong>SUNDERLY NO CHIEFING, WE SHIRT UPON.</strong></p>
                                    </div>
                                    
                                    <form method="post" class="profile-form">
                                        <div class="form-group">
                                            <label>Current Password</label>
                                            <input type="password" name="current_password" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>New Password</label>
                                            <input type="password" name="new_password" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Confirm New Password</label>
                                            <input type="password" name="confirm_password" required>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Preferences Tab -->
                                <div class="tab-content" id="preferences">
                                    <form method="post" class="profile-form">
                                        <div class="form-group">
                                            <label>Email Notifications</label>
                                            <div class="checkbox-group">
                                                <label class="checkbox">
                                                    <input type="checkbox" name="notify_rental" <?php echo ($user['notify_rental'] ?? 1) ? 'checked' : ''; ?>>
                                                    <span>Rental status updates</span>
                                                </label>
                                                <label class="checkbox">
                                                    <input type="checkbox" name="notify_equipment" <?php echo ($user['notify_equipment'] ?? 1) ? 'checked' : ''; ?>>
                                                    <span>Equipment availability</span>
                                                </label>
                                                <label class="checkbox">
                                                    <input type="checkbox" name="notify_system" <?php echo ($user['notify_system'] ?? 1) ? 'checked' : ''; ?>>
                                                    <span>System announcements</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="update_preferences" class="btn btn-primary">Save Preferences</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
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
            // Tab switching
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    button.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Dismiss notifications
            document.querySelectorAll('.notification-close').forEach(button => {
                button.addEventListener('click', () => {
                    button.parentElement.style.display = 'none';
                });
            });
            
            // Preview profile image before upload
            const profileImageInput = document.querySelector('input[name="profile_image"]');
            if (profileImageInput) {
                profileImageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            const preview = document.querySelector('.current-profile-image');
                            if (preview) {
                                preview.src = event.target.result;
                            } else {
                                const profileImageUpload = document.querySelector('.profile-image-upload');
                                const img = document.createElement('img');
                                img.src = event.target.result;
                                img.className = 'current-profile-image';
                                img.alt = 'New Profile Image';
                                profileImageUpload.insertBefore(img, profileImageUpload.firstChild);
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>
</body>
</html>