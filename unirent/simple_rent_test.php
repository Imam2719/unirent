<?php
// Create simple_rent_test.php - a minimal rental form to test
session_start();
require_once 'includes/config.php';

// Set user session
$_SESSION['user_id'] = 6;
$_SESSION['user_type'] = 1;

$equipment_id = 2;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $purpose = $_POST['purpose'];
    
    echo "<h2>Form Submission Debug</h2>";
    echo "Equipment ID: $equipment_id<br>";
    echo "User ID: {$_SESSION['user_id']}<br>";
    echo "Start Date: $start_date<br>";
    echo "End Date: $end_date<br>";
    echo "Purpose: $purpose<br><br>";
    
    // Method 1: Ultra simple insert
    $simple_sql = "INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status) VALUES ($equipment_id, {$_SESSION['user_id']}, '$start_date', '$end_date', '" . addslashes($purpose) . "', 1)";
    
    echo "<strong>Method 1: Direct SQL</strong><br>";
    echo "SQL: " . htmlspecialchars($simple_sql) . "<br>";
    
    if (mysqli_query($conn, $simple_sql)) {
        $id1 = mysqli_insert_id($conn);
        echo "✅ Insert successful! ID: $id1<br>";
        if ($id1 > 0) {
            $message = "SUCCESS: Rental created with ID $id1 using direct SQL!";
        } else {
            $message = "WARNING: Insert succeeded but ID is 0";
        }
    } else {
        echo "❌ Insert failed: " . mysqli_error($conn) . "<br>";
        $message = "FAILED: " . mysqli_error($conn);
    }
    
    echo "<br>";
    
    // Method 2: Prepared statement
    echo "<strong>Method 2: Prepared Statement</strong><br>";
    $stmt = $conn->prepare("INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $status = 1;
        $stmt->bind_param("iisssi", $equipment_id, $_SESSION['user_id'], $start_date, $end_date, $purpose, $status);
        
        if ($stmt->execute()) {
            $id2 = $stmt->insert_id;
            echo "✅ Prepared statement successful! ID: $id2<br>";
            if ($id2 > 0) {
                $message .= " | ALSO SUCCESS with prepared statement ID $id2!";
            }
        } else {
            echo "❌ Prepared statement failed: " . $stmt->error . "<br>";
        }
        $stmt->close();
    }
    
    echo "<br><hr><br>";
}

// Get equipment details
$eq_result = $conn->query("SELECT * FROM equipment WHERE id = $equipment_id");
$equipment = $eq_result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple Rental Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .message { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
    </style>
</head>
<body>
    <h1>Simple Rental Creation Test</h1>
    
    <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'SUCCESS') !== false ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="info">
        <h3>Equipment Details:</h3>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($equipment['name']); ?></p>
        <p><strong>Daily Rate:</strong> $<?php echo number_format($equipment['daily_rate'], 2); ?></p>
        <p><strong>Status:</strong> <?php echo $equipment['status']; ?> (1 = Available)</p>
    </div>
    
    <div class="info">
        <h3>User Details:</h3>
        <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
        <?php
        $user_result = $conn->query("SELECT * FROM users WHERE id = {$_SESSION['user_id']}");
        $user = $user_result->fetch_assoc();
        ?>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label>Start Date:</label>
            <input type="date" name="start_date" value="<?php echo $_POST['start_date'] ?? '2024-12-01'; ?>" required>
        </div>
        
        <div class="form-group">
            <label>End Date:</label>
            <input type="date" name="end_date" value="<?php echo $_POST['end_date'] ?? '2024-12-02'; ?>" required>
        </div>
        
        <div class="form-group">
            <label>Purpose:</label>
            <textarea name="purpose" rows="3" required><?php echo htmlspecialchars($_POST['purpose'] ?? 'Test rental from simple form'); ?></textarea>
        </div>
        
        <button type="submit">Create Rental</button>
    </form>
    
    <hr>
    
    <h3>Recent Rentals:</h3>
    <?php
    $recent = $conn->query("SELECT r.*, e.name as equipment_name, u.first_name, u.last_name FROM rentals r JOIN equipment e ON r.equipment_id = e.id JOIN users u ON r.user_id = u.id ORDER BY r.id DESC LIMIT 5");
    if ($recent && $recent->num_rows > 0) {
        echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Equipment</th><th>User</th><th>Start</th><th>End</th><th>Status</th><th>Created</th></tr>";
        while ($row = $recent->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['equipment_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
            echo "<td>" . $row['start_date'] . "</td>";
            echo "<td>" . $row['end_date'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No rentals found.</p>";
    }
    ?>
</body>
</html>