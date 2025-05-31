<?php
// =====================================================
// FILE: query-where-rental-sources.php (FIXED)
// Purpose: WHERE Provenance - Rental Data Sources
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
                <span class="badge bg-info me-2">WHERE</span>
                Rental Data Sources
            </h1>
            <p class="text-muted">Track the origin and dependencies of rental information</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Rental Data Source Mapping</h5>
        </div>
        <div class="card-body">
            <?php
            // Query to show data dependencies for rentals
            $sources_query = "
                SELECT 
                    r.id AS rental_id,
                    e.name AS equipment_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS renter_name,
                    c.name AS category_source,
                    CONCAT(owner.first_name, ' ', owner.last_name) AS equipment_owner,
                    r.created_at AS rental_created,
                    e.created_at AS equipment_created,
                    u.created_at AS user_created,
                    CASE 
                        WHEN r.created_at > e.created_at THEN 'Equipment existed before rental'
                        ELSE 'Equipment created after rental request'
                    END AS equipment_dependency,
                    CASE 
                        WHEN r.created_at > u.created_at THEN 'User existed before rental'
                        ELSE 'User created during rental process'
                    END AS user_dependency
                FROM rentals r
                JOIN equipment e ON r.equipment_id = e.id
                JOIN users u ON r.user_id = u.id
                JOIN categories c ON e.category_id = c.id
                LEFT JOIN users owner ON e.owner_id = owner.id
                ORDER BY r.created_at DESC
                LIMIT 20
            ";
            
            $result = $conn->query($sources_query);
            
            // Check for SQL errors
            if (!$result) {
                echo '<div class="alert alert-danger">';
                echo '<h6>SQL Error</h6>';
                echo 'Query failed: ' . htmlspecialchars($conn->error);
                echo '</div>';
            } else if ($result->num_rows > 0) {
                echo '<div class="table-responsive">';
                echo '<table class="table table-striped">';
                echo '<thead><tr>';
                echo '<th>Rental</th><th>Equipment</th><th>Renter</th><th>Category</th>';
                echo '<th>Owner</th><th>Equipment Dependency</th><th>User Dependency</th>';
                echo '</tr></thead><tbody>';
                
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>';
                    echo '<strong>#' . $row['rental_id'] . '</strong><br>';
                    echo '<small>' . date('M d, Y', strtotime($row['rental_created'])) . '</small>';
                    echo '</td>';
                    echo '<td>';
                    echo htmlspecialchars($row['equipment_name']) . '<br>';
                    echo '<small>Added: ' . date('M d, Y', strtotime($row['equipment_created'])) . '</small>';
                    echo '</td>';
                    echo '<td>';
                    echo htmlspecialchars($row['renter_name']) . '<br>';
                    echo '<small>Joined: ' . date('M d, Y', strtotime($row['user_created'])) . '</small>';
                    echo '</td>';
                    echo '<td>' . htmlspecialchars($row['category_source']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['equipment_owner'] ?? 'University') . '</td>';
                    echo '<td><span class="badge bg-success">' . $row['equipment_dependency'] . '</span></td>';
                    echo '<td><span class="badge bg-info">' . $row['user_dependency'] . '</span></td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table></div>';
                
                // Add summary statistics (FIXED QUERY)
                echo '<div class="row mt-4">';
                echo '<div class="col-md-6">';
                echo '<h6>Data Source Summary</h6>';
                
                $summary_query = "
                    SELECT 
                        COUNT(*) as total_rentals,
                        COUNT(DISTINCT r.equipment_id) as unique_equipment,
                        COUNT(DISTINCT r.user_id) as unique_users,
                        COUNT(DISTINCT c.id) as categories_involved
                    FROM rentals r
                    JOIN equipment e ON r.equipment_id = e.id
                    JOIN categories c ON e.category_id = c.id
                ";
                
                $summary_result = $conn->query($summary_query);
                
                if ($summary_result) {
                    $summary = $summary_result->fetch_assoc();
                    
                    echo '<ul class="list-group">';
                    echo '<li class="list-group-item d-flex justify-content-between">';
                    echo '<span>Total Rentals</span><span class="badge bg-primary">' . $summary['total_rentals'] . '</span>';
                    echo '</li>';
                    echo '<li class="list-group-item d-flex justify-content-between">';
                    echo '<span>Unique Equipment</span><span class="badge bg-success">' . $summary['unique_equipment'] . '</span>';
                    echo '</li>';
                    echo '<li class="list-group-item d-flex justify-content-between">';
                    echo '<span>Unique Users</span><span class="badge bg-info">' . $summary['unique_users'] . '</span>';
                    echo '</li>';
                    echo '<li class="list-group-item d-flex justify-content-between">';
                    echo '<span>Categories Involved</span><span class="badge bg-warning">' . $summary['categories_involved'] . '</span>';
                    echo '</li>';
                    echo '</ul>';
                } else {
                    echo '<div class="alert alert-warning">';
                    echo 'Could not generate summary statistics: ' . htmlspecialchars($conn->error);
                    echo '</div>';
                }
                
                echo '</div>';
                
                // Add data lineage visualization
                echo '<div class="col-md-6">';
                echo '<h6>Data Flow Analysis</h6>';
                
                // Check data quality and relationships
                $quality_query = "
                    SELECT 
                        COUNT(CASE WHEN e.owner_id IS NOT NULL THEN 1 END) as equipment_with_owners,
                        COUNT(CASE WHEN r.purpose IS NOT NULL AND r.purpose != '' THEN 1 END) as rentals_with_purpose,
                        COUNT(*) as total_records
                    FROM rentals r
                    JOIN equipment e ON r.equipment_id = e.id
                ";
                
                $quality_result = $conn->query($quality_query);
                if ($quality_result) {
                    $quality = $quality_result->fetch_assoc();
                    $owner_percentage = ($quality['equipment_with_owners'] / $quality['total_records']) * 100;
                    $purpose_percentage = ($quality['rentals_with_purpose'] / $quality['total_records']) * 100;
                    
                    echo '<div class="card">';
                    echo '<div class="card-body">';
                    echo '<h6 class="card-title">Data Quality Metrics</h6>';
                    echo '<div class="progress mb-2">';
                    echo '<div class="progress-bar bg-success" style="width: ' . $owner_percentage . '%">' . round($owner_percentage) . '% Equipment with Owners</div>';
                    echo '</div>';
                    echo '<div class="progress mb-2">';
                    echo '<div class="progress-bar bg-info" style="width: ' . $purpose_percentage . '%">' . round($purpose_percentage) . '% Rentals with Purpose</div>';
                    echo '</div>';
                    echo '<small class="text-muted">Higher percentages indicate better data lineage tracking</small>';
                    echo '</div>';
                    echo '</div>';
                }
                
                echo '</div>';
                echo '</div>';
                
            } else {
                echo '<div class="alert alert-info">';
                echo '<i class="fas fa-info-circle me-2"></i>';
                echo 'No rental data found. Create some rentals to see data source mapping.';
                echo '</div>';
                
                // Show instructions for generating test data
                echo '<div class="alert alert-warning">';
                echo '<h6>To Generate Test Data:</h6>';
                echo '<ol>';
                echo '<li>Go to the main site and create some equipment rentals</li>';
                echo '<li>Approve or reject some rental requests in <a href="manage-rentals.php">Manage Rentals</a></li>';
                echo '<li>The data lineage will show the relationships between users, equipment, and rentals</li>';
                echo '</ol>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
    
    <!-- Additional WHERE Provenance Information -->
    <div class="card mt-4">
        <div class="card-header">
            <h5>Data Lineage Explanation</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6><i class="fas fa-users text-primary me-2"></i>User Dependencies</h6>
                    <p>Shows whether users existed before making rental requests or were created during the rental process.</p>
                </div>
                <div class="col-md-4">
                    <h6><i class="fas fa-tools text-success me-2"></i>Equipment Dependencies</h6>
                    <p>Tracks whether equipment was available before rental requests or added in response to demand.</p>
                </div>
                <div class="col-md-4">
                    <h6><i class="fas fa-network-wired text-info me-2"></i>Data Flow</h6>
                    <p>Maps the relationships between users, equipment, categories, and rental decisions.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>