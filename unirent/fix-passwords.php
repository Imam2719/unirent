<?php
require_once 'includes/config.php';

// This script will update all plain text passwords to hashed versions
echo "Starting password update process...\n";

// Get all users
$stmt = $conn->prepare("SELECT id, password FROM users");
$stmt->execute();
$result = $stmt->get_result();

$updated = 0;
$already_hashed = 0;
$errors = 0;

while ($user = $result->fetch_assoc()) {
    $id = $user['id'];
    $password = $user['password'];
    
    // Check if password is already hashed
    if (password_get_info($password)['algo'] !== 0) {
        echo "User ID {$id}: Password already hashed.\n";
        $already_hashed++;
        continue;
    }
    
    // Hash the plain text password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Update the user's password
    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $id);
    
    if ($update_stmt->execute()) {
        echo "User ID {$id}: Password updated successfully.\n";
        $updated++;
    } else {
        echo "User ID {$id}: Failed to update password. Error: " . $conn->error . "\n";
        $errors++;
    }
}

echo "\nPassword update complete.\n";
echo "Summary:\n";
echo "- {$updated} passwords updated\n";
echo "- {$already_hashed} passwords already hashed\n";
echo "- {$errors} errors encountered\n";
?>