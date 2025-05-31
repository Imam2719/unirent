<?php
// XAMPP MySQL Configuration
$host = 'localhost';        
$user = 'root';            
$password = '';            
$database = 'unirent';

// Create connection
$conn = new mysqli($host, $user, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select the database
if (!$conn->select_db($database)) {
    die("Database selection failed: " . $conn->error . " - Database '$database' may not exist.");
}

// Set charset for proper character handling
$conn->set_charset("utf8mb4");

// Equipment Status Constants
define('STATUS_AVAILABLE', 1);
define('STATUS_RENTED', 2);
define('STATUS_MAINTENANCE', 3);
define('STATUS_RESERVED', 4);

// Rental Status Constants  
define('RENTAL_PENDING', 1);
define('RENTAL_APPROVED', 2);
define('RENTAL_REJECTED', 3);
define('RENTAL_COMPLETED', 4);
define('RENTAL_CANCELLED', 5);

// User Type Constants
define('USER_STUDENT', 1);
define('USER_ADMIN', 2);
define('ROLE_ADMIN', 2);
define('ROLE_USER', 1);

// Set session variables for audit context
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set MySQL session variables for audit tracking
if (isset($_SESSION['user_id'])) {
    $conn->query("SET @current_user_id = " . $_SESSION['user_id']);
}
$conn->query("SET @current_session_id = '" . session_id() . "'");
$conn->query("SET @current_ip = '" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "'");

?>