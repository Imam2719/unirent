<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

// Handle form submission
$equipment_id = $_GET['equipment_id'] ?? '';
$date_range = $_GET['date_range'] ?? '30';

include 'admin-header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2">
                <span class="badge bg-success me-2">WHY</span>
                Equipment Price Changes Analysis
            </h1>
            <p class="text-muted">Understand the business justification for equipment price modifications</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Equipment</label>
                    <select name="equipment_id" class="form-select">
                        <option value="">All Equipment</option>
                        <?php
                        $equipment = $conn->query("SELECT id, name FROM equipment ORDER BY name");
                        while ($row = $equipment->fetch_assoc()) {
                            $selected = ($equipment_id == $row['id']) ? 'selected' : '';
                            echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date Range</label>
                    <select name="date_range" class="form-select">
                        <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 days</option>
                        <option value="all" <?php echo $date_range == 'all' ? 'selected' : ''; ?>>All time</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-search me-2"></i>Analyze Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-header">
            <h5>Price Change Analysis Results</h5>
        </div>
        <div class="card-body">
            <?php
            // Execute the WHY provenance query
            $query = "
                SELECT 
                    e.name AS equipment_name,
                    ae.old_daily_rate AS previous_price,
                    ae.new_daily_rate AS new_price,
                    (ae.new_daily_rate - ae.old_daily_rate) AS price_change,
                    CASE 
                        WHEN ae.new_daily_rate > ae.old_daily_rate THEN 'INCREASE'
                        WHEN ae.new_daily_rate < ae.old_daily_rate THEN 'DECREASE'
                        ELSE 'NO CHANGE'
                    END AS change_type,
                    CONCAT(u.first_name, ' ', u.last_name) AS changed_by,
                    ae.change_timestamp AS when_changed,
                    ae.reason_for_change AS justification,
                    ROUND(((ae.new_daily_rate - ae.old_daily_rate) / ae.old_daily_rate) * 100, 2) AS percentage_change
                FROM audit_equipment ae
                JOIN equipment e ON ae.equipment_id = e.id
                LEFT JOIN users u ON ae.changed_by = u.id
                WHERE ae.old_daily_rate IS NOT NULL 
                    AND ae.new_daily_rate IS NOT NULL
                    AND ae.old_daily_rate != ae.new_daily_rate
            ";
            
            if (!empty($equipment_id)) {
                $query .= " AND e.id = " . intval($equipment_id);
            }
            
            if ($date_range !== 'all') {
                $query .= " AND ae.change_timestamp >= DATE_SUB(NOW(), INTERVAL " . intval($date_range) . " DAY)";
            }
            
            $query .= " ORDER BY ae.change_timestamp DESC";
            
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                echo '<div class="table-responsive">';
                echo '<table class="table table-striped">';
                echo '<thead><tr>';
                echo '<th>Equipment</th><th>Previous Price</th><th>New Price</th>';
                echo '<th>Change</th><th>% Change</th><th>Changed By</th>';
                echo '<th>When</th><th>Justification</th>';
                echo '</tr></thead><tbody>';
                
                while ($row = $result->fetch_assoc()) {
                    $change_class = $row['change_type'] === 'INCREASE' ? 'text-success' : 'text-danger';
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['equipment_name']) . '</td>';
                    echo '<td>$' . number_format($row['previous_price'], 2) . '</td>';
                    echo '<td>$' . number_format($row['new_price'], 2) . '</td>';
                    echo '<td class="' . $change_class . '">$' . number_format($row['price_change'], 2) . '</td>';
                    echo '<td class="' . $change_class . '">' . $row['percentage_change'] . '%</td>';
                    echo '<td>' . htmlspecialchars($row['changed_by'] ?? 'System') . '</td>';
                    echo '<td>' . date('M d, Y H:i', strtotime($row['when_changed'])) . '</td>';
                    echo '<td>' . htmlspecialchars($row['justification'] ?? 'No reason provided') . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table></div>';
            } else {
                echo '<div class="alert alert-info">';
                echo '<i class="fas fa-info-circle me-2"></i>';
                echo 'No price changes found for the selected criteria. Try creating some test data by editing equipment prices.';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</div>

<?php include 'admin-footer.php'; ?>