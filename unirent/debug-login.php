<?php
require_once 'includes/config.php';

echo "<h1>Database Connection Test</h1>";

// Test database connection
if ($conn->connect_error) {
    echo "<p style='color: red;'>Database connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>Database connection successful!</p>";
    
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>Users table exists.</p>";
        
        // Check if there are any users in the database
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        $row = $result->fetch_assoc();
        echo "<p>Number of users in database: " . $row['count'] . "</p>";
        
        // List all users
        echo "<h2>Users in Database:</h2>";
        $result = $conn->query("SELECT id, first_name, last_name, email, user_type FROM users");
        
        if ($result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>User Type</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
                echo "<td>" . $row['email'] . "</td>";
                echo "<td>" . ($row['user_type'] == 1 ? 'Student' : 'Admin') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p style='color: red;'>No users found in the database!</p>";
        }
        
        // Test password verification for admin user
        $email = 'admin@university.edu';
        $password = 'admin123';
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "<h2>Password Verification Test:</h2>";
            echo "<p>Testing login for: " . $email . "</p>";
            
            if (password_verify($password, $user['password'])) {
                echo "<p style='color: green;'>Password verification successful!</p>";
            } else {
                echo "<p style='color: red;'>Password verification failed!</p>";
                echo "<p>Stored hash: " . $user['password'] . "</p>";
                echo "<p>New hash for 'admin123': " . password_hash('admin123', PASSWORD_DEFAULT) . "</p>";
            }
        } else {
            echo "<p style='color: red;'>Admin user not found!</p>";
        }
    } else {
        echo "<p style='color: red;'>Users table does not exist!</p>";
    }
}
?>