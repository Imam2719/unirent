<?php
// Create debug_rental.php in your project root
// This will test the exact PHP process that's failing

session_start();
require_once 'includes/config.php';

// Simulate the logged-in user
$_SESSION['user_id'] = 6; // Maruf hasan

echo "<h1>PHP Rental Creation Debug</h1>";
echo "<p>Testing rental creation for User ID 6 and Equipment ID 2</p>";

$equipment_id = 2;
$user_id = 6;
$start_date = '2024-12-01';
$end_date = '2024-12-02';
$purpose = 'PHP Debug Test Rental';

echo "<h2>Test Parameters:</h2>";
echo "Equipment ID: $equipment_id<br>";
echo "User ID: $user_id<br>";
echo "Start Date: $start_date<br>";
echo "End Date: $end_date<br>";
echo "Purpose: $purpose<br><br>";

// Test 1: Simple direct insert
echo "<h2>Test 1: Direct SQL Insert</h2>";
$sql1 = "INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status) VALUES ($equipment_id, $user_id, '$start_date', '$end_date', '$purpose', 1)";
echo "SQL: " . htmlspecialchars($sql1) . "<br>";

if (mysqli_query($conn, $sql1)) {
    $insert_id1 = mysqli_insert_id($conn);
    echo "✅ SUCCESS! Insert ID: $insert_id1<br>";
    echo "mysqli_affected_rows: " . mysqli_affected_rows($conn) . "<br>";
    
    if ($insert_id1 > 0) {
        echo "✅ Insert ID is valid<br>";
    } else {
        echo "⚠️ Insert ID is 0 but query succeeded<br>";
    }
    
    // Clean up
    mysqli_query($conn, "DELETE FROM rentals WHERE id = $insert_id1");
    echo "Test record deleted.<br><br>";
} else {
    echo "❌ FAILED: " . mysqli_error($conn) . "<br><br>";
}

// Test 2: Prepared statement (like your functions.php)
echo "<h2>Test 2: Prepared Statement</h2>";
$stmt = $conn->prepare("INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status) VALUES (?, ?, ?, ?, ?, ?)");

if ($stmt) {
    $status = 1;
    $bind_result = $stmt->bind_param("iisssi", $equipment_id, $user_id, $start_date, $end_date, $purpose, $status);
    echo "Bind result: " . ($bind_result ? "SUCCESS" : "FAILED") . "<br>";
    
    $execute_result = $stmt->execute();
    echo "Execute result: " . ($execute_result ? "SUCCESS" : "FAILED") . "<br>";
    echo "Statement error: " . $stmt->error . "<br>";
    echo "Affected rows: " . $stmt->affected_rows . "<br>";
    
    if ($execute_result) {
        $insert_id2 = $stmt->insert_id;
        echo "Insert ID: $insert_id2<br>";
        
        if ($insert_id2 > 0) {
            echo "✅ SUCCESS! Valid insert ID<br>";
            
            // Clean up
            mysqli_query($conn, "DELETE FROM rentals WHERE id = $insert_id2");
            echo "Test record deleted.<br>";
        } else {
            echo "⚠️ Insert ID is 0 - checking manually<br>";
            
            // Check if record exists despite ID being 0
            $check = $conn->query("SELECT id FROM rentals WHERE equipment_id = $equipment_id AND user_id = $user_id AND purpose = '$purpose' ORDER BY id DESC LIMIT 1");
            if ($check && $check->num_rows > 0) {
                $row = $check->fetch_assoc();
                echo "✅ Record found with ID: " . $row['id'] . "<br>";
                mysqli_query($conn, "DELETE FROM rentals WHERE id = " . $row['id']);
                echo "Record deleted.<br>";
            } else {
                echo "❌ No record found<br>";
            }
        }
    } else {
        echo "❌ Execute failed<br>";
    }
    $stmt->close();
} else {
    echo "❌ Prepare failed: " . $conn->error . "<br>";
}

echo "<br>";

// Test 3: Your current createRental function logic
echo "<h2>Test 3: Simulating createRental Function Logic</h2>";

// Step 1: Get equipment details
$equipment_sql = "SELECT daily_rate, status FROM equipment WHERE id = ?";
$equipment_stmt = $conn->prepare($equipment_sql);
$equipment_stmt->bind_param("i", $equipment_id);
$equipment_stmt->execute();
$equipment_result = $equipment_stmt->get_result();

if ($equipment_result->num_rows > 0) {
    $equipment = $equipment_result->fetch_assoc();
    echo "Equipment found - Rate: {$equipment['daily_rate']}, Status: {$equipment['status']}<br>";
    
    // Step 2: Calculate total
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = $start->diff($end)->days;
    $total_amount = $days * $equipment['daily_rate'];
    echo "Calculated - Days: $days, Total: $total_amount<br>";
    
    // Step 3: Set audit variables
    $conn->query("SET @current_user_id = " . intval($user_id));
    $conn->query("SET @current_session_id = '" . $conn->real_escape_string(session_id()) . "'");
    $conn->query("SET @current_ip = '" . $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "'");
    echo "Session variables set<br>";
    
    // Step 4: Insert with total amount
    $sql3 = "INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status, total_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt3 = $conn->prepare($sql3);
    
    if ($stmt3) {
        $status = 1;
        $stmt3->bind_param("iisssid", $equipment_id, $user_id, $start_date, $end_date, $purpose, $status, $total_amount);
        
        if ($stmt3->execute()) {
            $insert_id3 = $stmt3->insert_id;
            echo "✅ SUCCESS! Insert ID: $insert_id3<br>";
            
            if ($insert_id3 > 0) {
                echo "✅ Valid insert ID<br>";
                
                // Clean up
                mysqli_query($conn, "DELETE FROM rentals WHERE id = $insert_id3");
                echo "Test record deleted.<br>";
            } else {
                echo "⚠️ Insert ID is 0<br>";
            }
        } else {
            echo "❌ Execute failed: " . $stmt3->error . "<br>";
        }
        $stmt3->close();
    } else {
        echo "❌ Prepare failed: " . $conn->error . "<br>";
    }
} else {
    echo "❌ Equipment not found<br>";
}

echo "<br>";

// Test 4: Check auto-increment value
echo "<h2>Test 4: Auto-increment Status</h2>";
$auto_result = $conn->query("SHOW TABLE STATUS LIKE 'rentals'");
if ($auto_result) {
    $auto_row = $auto_result->fetch_assoc();
    echo "Current auto-increment value: " . $auto_row['Auto_increment'] . "<br>";
    echo "Engine: " . $auto_row['Engine'] . "<br>";
}

// Test 5: Check recent rentals
echo "<h2>Test 5: Recent Rentals</h2>";
$recent = $conn->query("SELECT id, equipment_id, user_id, status, created_at FROM rentals ORDER BY id DESC LIMIT 5");
if ($recent) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Equipment ID</th><th>User ID</th><th>Status</th><th>Created</th></tr>";
    while ($row = $recent->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['equipment_id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Summary</h2>";
echo "<p>This debug script tests the exact same logic your rental creation uses.</p>";
echo "<p>If any of these tests succeed, then we know the database works and the issue is in your form handling.</p>";
?>