<?php
// Fixed query_logger.php with proper error handling

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Log query execution details without executing the query
 * Use this for prepared statements that are executed separately
 */
function log_query_execution($query_text, $type = 'SELECT', $table = null, $affected_rows = 0, $execution_time = 0) {
    global $conn;
    
    // Check if connection exists
    if (!$conn || $conn->connect_error) {
        error_log("Query logging failed: No database connection");
        return;
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Get file and line info from backtrace
    $backtrace = debug_backtrace();
    $file = $backtrace[1]['file'] ?? 'unknown';  // Use [1] instead of [0] to get the calling file
    $line = $backtrace[1]['line'] ?? 0;
    
    $hash = hash('sha256', $query_text);
    
    try {
        // Check if table exists first
        $table_check = $conn->query("SHOW TABLES LIKE 'query_provenance'");
        if (!$table_check || $table_check->num_rows == 0) {
            // Table doesn't exist, skip logging
            return;
        }
        
        $stmt = $conn->prepare("INSERT INTO query_provenance 
            (session_id, user_id, query_type, query_text, query_hash, table_name, affected_rows, execution_time, ip_address, user_agent, file_path, line_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            error_log("Query logging failed: Prepare failed - " . $conn->error);
            return;
        }
        
        $stmt->bind_param("sissssissssi", $sessionId, $userId, $type, $query_text, $hash, $table, $affected_rows, $execution_time, $ip, $agent, $file, $line);
        
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Silently handle logging errors to avoid breaking the main application
        error_log("Query logging failed: " . $e->getMessage());
    }
}

/**
 * Execute and log a query (for simple queries)
 * Use this for direct mysqli_query calls
 */
function log_query($query, $type = 'SELECT', $table = null) {
    global $conn;
    $start = microtime(true);
    $result = mysqli_query($conn, $query);
    $time = microtime(true) - $start;
    
    $affected_rows = 0;
    if ($result) {
        if (is_bool($result)) {
            $affected_rows = mysqli_affected_rows($conn);
        } else {
            $affected_rows = mysqli_num_rows($result);
        }
    }
    
    // Log the execution
    log_query_execution($query, $type, $table, $affected_rows, $time);
    
    return $result;
}

/**
 * Wrapper for prepared statements with logging
 */
class LoggedStatement {
    private $stmt;
    private $query_text;
    private $type;
    private $table;
    private $start_time;
    private $is_valid;
    
    public function __construct($stmt, $query_text, $type = 'SELECT', $table = null) {
        $this->stmt = $stmt;
        $this->query_text = $query_text;
        $this->type = $type;
        $this->table = $table;
        $this->is_valid = ($stmt !== false);
    }
    
    public function bind_param($types, ...$params) {
        if (!$this->is_valid) {
            return false;
        }
        return $this->stmt->bind_param($types, ...$params);
    }
    
    public function execute() {
        if (!$this->is_valid) {
            return false;
        }
        
        $this->start_time = microtime(true);
        $result = $this->stmt->execute();
        $execution_time = microtime(true) - $this->start_time;
        
        $affected_rows = $this->stmt->affected_rows;
        
        // Log the execution
        log_query_execution($this->query_text, $this->type, $this->table, $affected_rows, $execution_time);
        
        return $result;
    }
    
    public function get_result() {
        if (!$this->is_valid) {
            return false;
        }
        return $this->stmt->get_result();
    }
    
    public function close() {
        if (!$this->is_valid) {
            return false;
        }
        return $this->stmt->close();
    }
    
    public function __call($method, $args) {
        if (!$this->is_valid) {
            return false;
        }
        return call_user_func_array([$this->stmt, $method], $args);
    }
    
    public function __get($property) {
        if (!$this->is_valid) {
            return null;
        }
        return $this->stmt->$property;
    }
}

/**
 * Create a logged prepared statement
 */
function prepare_logged($conn, $query, $type = 'SELECT', $table = null) {
    $stmt = $conn->prepare($query);
    return new LoggedStatement($stmt, $query, $type, $table);
}

?>