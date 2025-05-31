<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if admin user exists
$admin_id = null;
$sql = "SELECT id FROM users WHERE user_type = 2 LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $admin_id = $result->fetch_assoc()['id'];
} else {
    die("No admin user found. Please run create-test-users.php first.");
}

// Check if student user exists
$student_id = null;
$sql = "SELECT id FROM users WHERE user_type = 1 LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $student_id = $result->fetch_assoc()['id'];
} else {
    die("No student user found. Please run create-test-users.php first.");
}

// Get all categories
$categories = [];
$sql = "SELECT * FROM categories";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    die("No categories found. Please run the database.sql script first.");
}

// Sample equipment data
$equipment = [
    [
        'name' => 'Canon EOS 5D Mark IV',
        'description' => 'Professional DSLR camera with 30.4MP full-frame CMOS sensor and DIGIC 6+ image processor.',
        'category_id' => 1, // Cameras
        'owner_id' => $admin_id,
        'daily_rate' => 25.00,
        'brand' => 'Canon',
        'model' => 'EOS 5D Mark IV',
        'condition' => 'Excellent',
        'location' => 'Media Center, Building A',
        'image' => 'assets/images/categories/cameras.jpg',
        'is_featured' => 1
    ],
    [
        'name' => 'MacBook Pro 16"',
        'description' => 'Powerful laptop with M1 Pro chip, 16GB RAM, and 512GB SSD storage. Perfect for video editing and programming.',
        'category_id' => 2, // Laptops
        'owner_id' => $admin_id,
        'daily_rate' => 20.00,
        'brand' => 'Apple',
        'model' => 'MacBook Pro 16" (2021)',
        'condition' => 'Like New',
        'location' => 'Computer Lab, Building B',
        'image' => 'assets/images/categories/laptops.jpg',
        'is_featured' => 1
    ],
    [
        'name' => 'Shure SM7B Microphone',
        'description' => 'Professional dynamic microphone, ideal for vocals, podcasts, and streaming.',
        'category_id' => 3, // Audio Equipment
        'owner_id' => $student_id,
        'daily_rate' => 10.00,
        'brand' => 'Shure',
        'model' => 'SM7B',
        'condition' => 'Good',
        'location' => 'Music Department',
        'image' => 'assets/images/categories/audio.jpg',
        'is_featured' => 1
    ],
    [
        'name' => 'Epson PowerLite Projector',
        'description' => 'High-brightness projector with HDMI connectivity and wireless presentation capabilities.',
        'category_id' => 4, // Projectors
        'owner_id' => $admin_id,
        'daily_rate' => 15.00,
        'brand' => 'Epson',
        'model' => 'PowerLite 1781W',
        'condition' => 'Good',
        'location' => 'AV Department, Building C',
        'image' => 'assets/images/categories/projectors.jpg',
        'is_featured' => 1
    ]
];

// Insert equipment into database
$count = 0;
foreach ($equipment as $item) {
    // Check if equipment already exists
    $stmt = $conn->prepare("SELECT id FROM equipment WHERE name = ? AND owner_id = ?");
    $stmt->bind_param("si", $item['name'], $item['owner_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Insert equipment
        $sql = "INSERT INTO equipment (name, description, category_id, owner_id, status, daily_rate, brand, model, `condition`, location, image, is_featured, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $status = 1; // Available
        
        $stmt = $conn->prepare($sql);
        // FIXED: Added one more 's' for image parameter
        $stmt->bind_param("ssiiddsssssi", 
            $item['name'], 
            $item['description'], 
            $item['category_id'], 
            $item['owner_id'], 
            $status, 
            $item['daily_rate'], 
            $item['brand'], 
            $item['model'], 
            $item['condition'], 
            $item['location'], 
            $item['image'], 
            $item['is_featured']
        );
        
        if ($stmt->execute()) {
            $equipment_id = $stmt->insert_id;
            
            // Create provenance record if function exists
            if (function_exists('createProvenanceRecord')) {
                createProvenanceRecord($conn, $equipment_id, $item['owner_id'], 'created');
            }
            
            $count++;
            echo "Added equipment: {$item['name']}<br>";
        } else {
            echo "Error adding equipment {$item['name']}: " . $stmt->error . "<br>";
        }
    } else {
        echo "Equipment {$item['name']} already exists.<br>";
    }
}

echo "<h2>Added $count new equipment items to the database.</h2>";
echo "<p>Go to <a href='index.php'>homepage</a> to see the changes.</p>";

$conn->close();
?>