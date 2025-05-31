<?php
require_once 'includes/config.php';

echo "<h1>UniRent Database Diagnostic</h1>";

// Check database connection
echo "<h2>Database Connection</h2>";
if ($conn->connect_error) {
    echo "<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>Connection successful!</p>";
}

// Check users table structure
echo "<h2>Users Table Structure</h2>";
$result = $conn->query("DESCRIBE users");

if ($result === false) {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Check if there are any users
echo "<h2>Existing Users</h2>";
$result = $conn->query("SELECT id, first_name, last_name, email, user_type FROM users");

if ($result === false) {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
} else {
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
        echo "<p>No users found.</p>";
    }
}

// Check if the status column exists in the users table
echo "<h2>Status Column Check</h2>";
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");

if ($result === false) {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
} else {
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>Status column exists in the users table.</p>";
    } else {
        echo "<p style='color: red;'>Status column does not exist in the users table.</p>";
        echo "<p>You need to add the status column to the users table:</p>";
        echo "<pre>ALTER TABLE users ADD COLUMN status TINYINT NOT NULL DEFAULT 1;</pre>";
    }
}
?>