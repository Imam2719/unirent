<?php
// Create a file called database_inspector.php in your project root
// This will show you exactly what's in your database

require_once 'includes/config.php';

echo "<h1>Database Inspector</h1>";
echo "<p>Database: " . $conn->get_server_info() . "</p>";

// Check what database we're using
$db_result = $conn->query("SELECT DATABASE() as db_name");
if ($db_result) {
    $db_row = $db_result->fetch_assoc();
    echo "<p>Current Database: <strong>" . $db_row['db_name'] . "</strong></p>";
}

echo "<hr>";

// 1. Show all tables
echo "<h2>1. All Tables in Database</h2>";
$tables_result = $conn->query("SHOW TABLES");
if ($tables_result) {
    echo "<ul>";
    $tables = [];
    while ($row = $tables_result->fetch_array()) {
        $table_name = $row[0];
        $tables[] = $table_name;
        echo "<li>$table_name</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Error getting tables: " . $conn->error . "</p>";
}

echo "<hr>";

// 2. Check if rentals table exists and show its structure
echo "<h2>2. Rentals Table Analysis</h2>";
if (in_array('rentals', $tables)) {
    echo "<p>✅ Rentals table EXISTS</p>";
    
    // Show structure
    echo "<h3>Table Structure:</h3>";
    $structure = $conn->query("DESCRIBE rentals");
    if ($structure) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $structure->fetch_assoc()) {
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
    
    // Count records
    $count_result = $conn->query("SELECT COUNT(*) as total FROM rentals");
    if ($count_result) {
        $count_row = $count_result->fetch_assoc();
        echo "<p>Records in table: " . $count_row['total'] . "</p>";
    }
    
    // Show CREATE statement
    echo "<h3>CREATE Statement:</h3>";
    $create_result = $conn->query("SHOW CREATE TABLE rentals");
    if ($create_result) {
        $create_row = $create_result->fetch_assoc();
        echo "<pre>" . htmlspecialchars($create_row['Create Table']) . "</pre>";
    }
    
} else {
    echo "<p>❌ Rentals table DOES NOT EXIST</p>";
    echo "<p><strong>This is why your rental creation is failing!</strong></p>";
}

echo "<hr>";

// 3. Check equipment and users tables
echo "<h2>3. Related Tables Check</h2>";

if (in_array('equipment', $tables)) {
    echo "<p>✅ Equipment table exists</p>";
    $eq_count = $conn->query("SELECT COUNT(*) as total FROM equipment");
    if ($eq_count) {
        $eq_row = $eq_count->fetch_assoc();
        echo "<p>Equipment records: " . $eq_row['total'] . "</p>";
    }
} else {
    echo "<p>❌ Equipment table missing</p>";
}

if (in_array('users', $tables)) {
    echo "<p>✅ Users table exists</p>";
    $user_count = $conn->query("SELECT COUNT(*) as total FROM users");
    if ($user_count) {
        $user_row = $user_count->fetch_assoc();
        echo "<p>User records: " . $user_row['total'] . "</p>";
    }
} else {
    echo "<p>❌ Users table missing</p>";
}

echo "<hr>";

// 4. Check if the specific equipment and user exist
echo "<h2>4. Test Data Check</h2>";

if (in_array('equipment', $tables)) {
    $eq_check = $conn->query("SELECT id, name, status FROM equipment WHERE id = 2");
    if ($eq_check && $eq_check->num_rows > 0) {
        $eq_data = $eq_check->fetch_assoc();
        echo "<p>✅ Equipment ID 2 exists: " . $eq_data['name'] . " (Status: " . $eq_data['status'] . ")</p>";
    } else {
        echo "<p>❌ Equipment ID 2 not found</p>";
    }
}

if (in_array('users', $tables)) {
    $user_check = $conn->query("SELECT id, first_name, last_name FROM users WHERE id = 6");
    if ($user_check && $user_check->num_rows > 0) {
        $user_data = $user_check->fetch_assoc();
        echo "<p>✅ User ID 6 exists: " . $user_data['first_name'] . " " . $user_data['last_name'] . "</p>";
    } else {
        echo "<p>❌ User ID 6 not found</p>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>If the rentals table is missing or has wrong columns, you need to create/fix it using the SQL provided.</p>";
?>