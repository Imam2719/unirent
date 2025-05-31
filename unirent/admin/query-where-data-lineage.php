
<?php
// =====================================================
// FILE: fixed-query-where-data-lineage.php  
// Purpose: WHERE Provenance - Data Source Tracing (FIXED)
// =====================================================

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2">
                <span class="badge bg-success me-2">WHERE</span>
                Data Lineage Tracking
            </h1>
            <p class="text-muted">Track data sources and find original contributors to rental decisions</p>
        </div>
    </div>

    <!-- Results showing data lineage -->
    <div class="card">
        <div class="card-header">
            <h5>Data Source Analysis</h5>
        </div>
        <div class="card-body">
            <?php
            // Check if required tables exist
            $table_checks = [
                'user_activity' => $conn->query("SHOW TABLES LIKE 'user_activity'")->num_rows > 0,
                'query_provenance' => $conn->query("SHOW TABLES LIKE 'query_provenance'")->num_rows > 0
            ];
            
            if (!$table_checks['user_activity'] && !$table_checks['query_provenance']) {
                echo '<div class="alert alert-warning">';
                echo '<h6>Provenance Tables Missing</h6>';
                echo 'Both <code>user_activity</code> and <code>query_provenance</code> tables are missing.';
                echo '</div>';
            } else {
                // Execute enhanced WHERE provenance query
                $lineage_parts = [];
                
                // Part 1: User Activity Data
                if ($table_checks['user_activity']) {
                    $lineage_parts[] = "
                        SELECT 
                            'User Activity' AS data_source,
                            ua.activity_type AS source_type,
                            ua.activity_description AS source_details,
                            ua.timestamp AS source_timestamp,
                            ua.ip_address AS source_location,
                            CONCAT(u.first_name, ' ', u.last_name) AS source_user,
                            ua.page_url AS context
                        FROM user_activity ua
                        LEFT JOIN users u ON ua.user_id = u.id
                        WHERE ua.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ";
                }
                
                // Part 2: Query Provenance Data
                if ($table_checks['query_provenance']) {
                    $lineage_parts[] = "
                        SELECT 
                            'Query Log' AS data_source,
                            qp.query_type AS source_type,
                            CONCAT('Query on ', qp.table_name, ': ', LEFT(qp.query_text, 50), '...') AS source_details,
                            qp.timestamp AS source_timestamp,
                            qp.ip_address AS source_location,
                            CONCAT(u.first_name, ' ', u.last_name) AS source_user,
                            qp.file_path AS context
                        FROM query_provenance qp
                        LEFT JOIN users u ON qp.user_id = u.id
                        WHERE qp.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ";
                }
                
                // Part 3: Data Provenance (Equipment Actions)
                $data_prov_check = $conn->query("SHOW TABLES LIKE 'data_provenance'");
                if ($data_prov_check->num_rows > 0) {
                    $lineage_parts[] = "
                        SELECT 
                            'Equipment Activity' AS data_source,
                            dp.action AS source_type,
                            CONCAT('Equipment #', dp.equipment_id, ': ', dp.action) AS source_details,
                            dp.timestamp AS source_timestamp,
                            dp.ip_address AS source_location,
                            CONCAT(u.first_name, ' ', u.last_name) AS source_user,
                            'Equipment Management' AS context
                        FROM data_provenance dp
                        LEFT JOIN users u ON dp.user_id = u.id
                        WHERE dp.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ";
                }
                
                if (!empty($lineage_parts)) {
                    $lineage_query = implode(' UNION ALL ', $lineage_parts);
                    $lineage_query .= " ORDER BY source_timestamp DESC LIMIT 50";
                    
                    $result = $conn->query($lineage_query);
                    
                    if ($result && $result->num_rows > 0) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-striped">';
                        echo '<thead><tr>';
                        echo '<th>Data Source</th><th>Activity Type</th><th>Details</th>';
                        echo '<th>User</th><th>Timestamp</th><th>Location</th><th>Context</th>';
                        echo '</tr></thead><tbody>';
                        
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td><span class="badge bg-info">' . $row['data_source'] . '</span></td>';
                            echo '<td>' . htmlspecialchars($row['source_type']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['source_details']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['source_user'] ?? 'System') . '</td>';
                            echo '<td>' . date('M d, Y H:i:s', strtotime($row['source_timestamp'])) . '</td>';
                            echo '<td><small>' . htmlspecialchars($row['source_location']) . '</small></td>';
                            echo '<td><small>' . htmlspecialchars($row['context']) . '</small></td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody></table></div>';
                        
                        // Add summary statistics
                        echo '<div class="mt-4">';
                        echo '<h6>Data Lineage Summary</h6>';
                        $summary_query = "
                            SELECT 
                                COUNT(*) as total_activities,
                                COUNT(DISTINCT source_user) as unique_users,
                                COUNT(DISTINCT source_location) as unique_locations
                            FROM (" . $lineage_query . ") AS summary
                        ";
                        
                        $summary_result = $conn->query($summary_query);
                        if ($summary_result) {
                            $summary = $summary_result->fetch_assoc();
                            echo '<div class="row">';
                            echo '<div class="col-md-4"><div class="card bg-primary text-white"><div class="card-body text-center">';
                            echo '<h4>' . $summary['total_activities'] . '</h4><small>Total Activities</small></div></div></div>';
                            echo '<div class="col-md-4"><div class="card bg-success text-white"><div class="card-body text-center">';
                            echo '<h4>' . $summary['unique_users'] . '</h4><small>Unique Users</small></div></div></div>';
                            echo '<div class="col-md-4"><div class="card bg-info text-white"><div class="card-body text-center">';
                            echo '<h4>' . $summary['unique_locations'] . '</h4><small>Unique Locations</small></div></div></div>';
                            echo '</div>';
                        }
                        echo '</div>';
                        
                    } else {
                        echo '<div class="alert alert-info">';
                        echo '<i class="fas fa-info-circle me-2"></i>';
                        echo 'No recent data lineage found. Activity will appear here as users interact with the system.';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert alert-warning">';
                    echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                    echo 'No lineage data sources are available. Please ensure the required tables exist.';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>
