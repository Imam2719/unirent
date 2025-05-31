<?php
require_once 'includes/config.php';

// Create admin user
$admin_first_name = 'Admin';
$admin_last_name = 'User';
$admin_email = 'admin@university.edu';
$admin_password = 'admin123'; // Plain text for testing
$admin_hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
$admin_user_type = 2; // Admin
$admin_department = 'IT Department';

// Check if admin already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Insert admin user
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, user_type, department, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssis", $admin_first_name, $admin_last_name, $admin_email, $admin_hashed_password, $admin_user_type, $admin_department);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully.<br>";
        echo "Email: $admin_email<br>";
        echo "Password: $admin_password<br><br>";
    } else {
        echo "Error creating admin user: " . $stmt->error . "<br>";
    }
} else {
    echo "Admin user already exists.<br>";
}

// Create student user
$student_first_name = 'John';
$student_last_name = 'Doe';
$student_email = 'john.doe@university.edu';
$student_password = 'student123'; // Plain text for testing
$student_hashed_password = password_hash($student_password, PASSWORD_DEFAULT);
$student_id = 'S12345';
$student_user_type = 1; // Student
$student_department = 'Computer Science';

// Check if student already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $student_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Insert student user
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, student_id, user_type, department, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssss", $student_first_name, $student_last_name, $student_email, $student_hashed_password, $student_id, $student_user_type, $student_department);
    
    if ($stmt->execute()) {
        echo "Student user created successfully.<br>";
        echo "Email: $student_email<br>";
        echo "Password: $student_password<br>";
    } else {
        echo "Error creating student user: " . $stmt->error . "<br>";
    }
} else {
    echo "Student user already exists.<br>";
}

$conn->close();
?>