<?php
require_once 'includes/config.php';

echo "<h1>Equipment Debug</h1>";

// Check if equipment table exists
$result = $conn->query("SHOW TABLES LIKE 'equipment'");
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Equipment table does not exist!</p>";
    exit;
}

// Check if there are any equipment items
$sql = "SELECT COUNT(*) as count FROM equipment";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
echo "<p>Total equipment items: " . $row['count'] . "</p>";

if ($row['count'] == 0) {
    echo "<p style='color: red;'>No equipment found in the database. Run add-sample-equipment.php to add sample equipment.</p>";
    exit;
}

// Get featured equipment
$sql = "SELECT e.*, c.name as category_name, u.first_name, u.last_name, u.user_type 
        FROM equipment e 
        JOIN categories c ON e.category_id = c.id 
        JOIN users u ON e.owner_id = u.id 
        WHERE e.is_featured = 1 
        ORDER BY e.created_at DESC 
        LIMIT 4";
$result = $conn->query($sql);

echo "<h2>Featured Equipment</h2>";
if ($result->num_rows > 0) {
    echo "<div style='display: flex; flex-wrap: wrap; gap: 20px;'>";
    while($item = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; width: 300px;'>";
        echo "<h3>" . $item['name'] . "</h3>";
        echo "<p>Category: " . $item['category_name'] . "</p>";
        echo "<p>Owner: " . $item['first_name'] . " " . $item['last_name'] . "</p>";
        echo "<p>Status: " . ($item['status'] == 1 ? 'Available' : 'Unavailable') . "</p>";
        echo "<p>Daily Rate: $" . number_format($item['daily_rate'], 2) . "</p>";
        
        // Check if image exists
        if (!empty($item['image']) && file_exists($item['image'])) {
            echo "<img src='" . $item['image'] . "' alt='" . $item['name'] . "' style='width: 100%; height: 150px; object-fit: cover;'>";
        } else {
            echo "<p style='color: red;'>Image not found: " . $item['image'] . "</p>";
            echo "<img src='assets/images/placeholder.jpg' alt='" . $item['name'] . "' style='width: 100%; height: 150px; object-fit: cover;'>";
        }
        
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<p>No featured equipment found.</p>";
}

// Check equipment card template
echo "<h2>Equipment Card Template</h2>";
$template_file = 'includes/equipment-card.php';
if (file_exists($template_file)) {
    echo "<p style='color: green;'>Equipment card template exists.</p>";
    echo "<pre>" . htmlspecialchars(file_get_contents($template_file)) . "</pre>";
} else {
    echo "<p style='color: red;'>Equipment card template not found!</p>";
}

$conn->close();
?>