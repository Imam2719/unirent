<?php
// Create connection_debug.php - this will check what database you're actually connected to
require_once 'includes/config.php';

echo "<h1>Database Connection Debug</h1>";

// Check current database
$current_db = $conn->query("SELECT DATABASE() as current_db");
if ($current_db) {
    $db_row = $current_db->fetch_assoc();
    echo "<p><strong>Currently connected to database:</strong> " . $db_row['current_db'] . "</p>";
}

// Check connection details
echo "<p><strong>Host:</strong> " . $conn->host_info . "</p>";
echo "<p><strong>Server version:</strong> " . $conn->server_info . "</p>";

echo "<hr>";

// Check what tables exist in the current connection
echo "<h2>Tables in Current Database:</h2>";
$tables = $conn->query("SHOW TABLES");
if ($tables) {
    $table_list = [];
    while ($row = $tables->fetch_array()) {
        $table_list[] = $row[0];
        echo "- " . $row[0] . "<br>";
    }
} else {
    echo "Error getting tables: " . $conn->error;
}

echo "<hr>";

// Check rentals table structure in current connection
echo "<h2>Rentals Table Structure in Current Connection:</h2>";
if (in_array('rentals', $table_list)) {
    echo "<p>✅ Rentals table found</p>";
    
    $structure = $conn->query("DESCRIBE rentals");
    if ($structure) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        $columns = [];
        while ($row = $structure->fetch_assoc()) {
            $columns[] = $row['Field'];
            echo "<tr>";
            echo "<td><strong>" . $row['Field'] . "</strong></td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Column List:</h3>";
        echo "<p>" . implode(", ", $columns) . "</p>";
        
        // Check if equipment_id specifically exists
        if (in_array('equipment_id', $columns)) {
            echo "<p>✅ <strong>equipment_id column EXISTS</strong></p>";
        } else {
            echo "<p>❌ <strong>equipment_id column MISSING</strong></p>";
        }
    } else {
        echo "<p>❌ Error describing table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>❌ Rentals table NOT FOUND in current connection</p>";
}

echo "<hr>";

// Try a simple SELECT to see what columns actually exist
echo "<h2>Test SELECT Query:</h2>";
$test_select = $conn->query("SELECT * FROM rentals LIMIT 1");
if ($test_select) {
    echo "<p>✅ SELECT * worked</p>";
    
    if ($test_select->num_rows > 0) {
        $row = $test_select->fetch_assoc();
        echo "<h3>Actual columns returned by SELECT *:</h3>";
        echo "<p>" . implode(", ", array_keys($row)) . "</p>";
        
        echo "<h3>Sample data:</h3>";
        echo "<pre>" . print_r($row, true) . "</pre>";
    } else {
        echo "<p>No rows in table</p>";
    }
} else {
    echo "<p>❌ SELECT * failed: " . $conn->error . "</p>";
}

echo "<hr>";

// Check your config file contents
echo "<h2>Config File Check:</h2>";
echo "<p><strong>Database name from config:</strong> " . (defined('DB_NAME') ? DB_NAME : 'Not defined') . "</p>";

// Show config variables (without passwords)
echo "<p><strong>Host:</strong> " . ($host ?? 'not set') . "</p>";
echo "<p><strong>User:</strong> " . ($user ?? 'not set') . "</p>";
echo "<p><strong>Database:</strong> " . ($database ?? 'not set') . "</p>";

echo "<hr>";

// Try to manually connect to unirent database
echo "<h2>Manual Database Selection Test:</h2>";
if ($conn->select_db('unirent')) {
    echo "<p>✅ Successfully selected 'unirent' database</p>";
    
    // Check structure again after manual selection
    $manual_structure = $conn->query("DESCRIBE rentals");
    if ($manual_structure) {
        echo "<h3>Rentals structure after manual DB selection:</h3>";
        $manual_columns = [];
        while ($row = $manual_structure->fetch_assoc()) {
            $manual_columns[] = $row['Field'];
        }
        echo "<p>Columns: " . implode(", ", $manual_columns) . "</p>";
        
        if (in_array('equipment_id', $manual_columns)) {
            echo "<p>✅ equipment_id found after manual selection</p>";
            
            // Try the insert again
            echo "<h3>Test Insert After Manual Selection:</h3>";
            $test_insert = "INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status) VALUES (2, 6, '2024-12-01', '2024-12-02', 'Test after manual selection', 1)";
            
            if ($conn->query($test_insert)) {
                $insert_id = $conn->insert_id;
                echo "<p>✅ Insert successful! ID: $insert_id</p>";
                
                // Clean up
                $conn->query("DELETE FROM rentals WHERE id = $insert_id");
                echo "<p>Test record deleted</p>";
            } else {
                echo "<p>❌ Insert still failed: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>❌ equipment_id still missing after manual selection</p>";
        }
    }
} else {
    echo "<p>❌ Failed to select 'unirent' database: " . $conn->error . "</p>";
}

echo "<h2>Summary</h2>";
echo "<p>This debug will help us understand if you're connected to the wrong database or if there's a connection issue.</p>";
?>