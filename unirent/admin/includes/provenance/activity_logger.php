<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function log_activity($type, $description) {
    global $conn;

    // Check if connection exists
    if (!$conn || $conn->connect_error) {
        error_log("Activity logging failed: No database connection");
        return false;
    }

    // Check if table exists
    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'user_activity'");
        if (!$table_check || $table_check->num_rows == 0) {
            // Table doesn't exist, skip logging
            return false;
        }
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }

    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $url = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    
    try {
        $stmt = $conn->prepare("INSERT INTO user_activity 
            (user_id, session_id, activity_type, activity_description, page_url, http_method, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
        if (!$stmt) {
            error_log("Activity logging failed: Prepare failed - " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("isssssss", $userId, $sessionId, $type, $description, $url, $method, $ip, $agent);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

function trackUserActivity($type, $message) {
    global $conn;
    
    // Check if connection exists
    if (!$conn || $conn->connect_error) {
        error_log("User activity tracking failed: No database connection");
        return false;
    }

    // Check if table exists
    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'user_activity'");
        if (!$table_check || $table_check->num_rows == 0) {
            // Table doesn't exist, skip logging
            return false;
        }
    } catch (Exception $e) {
        error_log("User activity tracking failed: " . $e->getMessage());
        return false;
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    try {
        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_type, activity_description, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())");
        
        if (!$stmt) {
            error_log("User activity tracking failed: Prepare failed - " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("isss", $user_id, $type, $message, $ip);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("User activity tracking failed: " . $e->getMessage());
        return false;
    }
}

?>