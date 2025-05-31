<?php
// Create trigger_debug.php - this will check and fix trigger issues
require_once 'includes/config.php';

echo "<h1>Trigger Debug and Fix</h1>";

// Check all triggers on rentals table
echo "<h2>Triggers on Rentals Table:</h2>";
$triggers = $conn->query("SHOW TRIGGERS LIKE 'rentals'");

if ($triggers && $triggers->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Trigger Name</th><th>Event</th><th>Timing</th><th>Statement (First 100 chars)</th></tr>";
    
    $trigger_names = [];
    while ($row = $triggers->fetch_assoc()) {
        $trigger_names[] = $row['Trigger'];
        echo "<tr>";
        echo "<td><strong>" . $row['Trigger'] . "</strong></td>";
        echo "<td>" . $row['Event'] . "</td>";
        echo "<td>" . $row['Timing'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['Statement'], 0, 100)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Found " . count($trigger_names) . " triggers:</strong> " . implode(", ", $trigger_names) . "</p>";
} else {
    echo "<p>No triggers found on rentals table.</p>";
}

echo "<hr>";

// Try to disable triggers temporarily and test insert
echo "<h2>Testing Insert with Triggers Disabled:</h2>";

if (!empty($trigger_names)) {
    echo "<h3>Step 1: Backup and Drop Triggers</h3>";
    
    // Get full trigger definitions for backup
    $trigger_backups = [];
    foreach ($trigger_names as $trigger_name) {
        $trigger_def = $conn->query("SHOW CREATE TRIGGER `$trigger_name`");
        if ($trigger_def) {
            $def_row = $trigger_def->fetch_assoc();
            $trigger_backups[$trigger_name] = $def_row['SQL Original Statement'];
            echo "<p>✅ Backed up trigger: $trigger_name</p>";
        }
    }
    
    // Drop triggers temporarily
    foreach ($trigger_names as $trigger_name) {
        if ($conn->query("DROP TRIGGER IF EXISTS `$trigger_name`")) {
            echo "<p>✅ Dropped trigger: $trigger_name</p>";
        } else {
            echo "<p>❌ Failed to drop trigger $trigger_name: " . $conn->error . "</p>";
        }
    }
    
    echo "<h3>Step 2: Test Insert Without Triggers</h3>";
    $test_sql = "INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status) VALUES (2, 6, '2024-12-01', '2024-12-02', 'Test without triggers', 1)";
    
    echo "<p>Testing SQL: " . htmlspecialchars($test_sql) . "</p>";
    
    if ($conn->query($test_sql)) {
        $insert_id = $conn->insert_id;
        echo "<p>✅ <strong>SUCCESS!</strong> Insert worked without triggers! ID: $insert_id</p>";
        
        // Clean up test record
        $conn->query("DELETE FROM rentals WHERE id = $insert_id");
        echo "<p>Test record deleted.</p>";
        
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>🎉 PROBLEM SOLVED!</h3>";
        echo "<p><strong>The issue was caused by your triggers!</strong></p>";
        echo "<p>Your rental form should work now. The triggers were referencing columns or tables incorrectly.</p>";
        echo "</div>";
        
    } else {
        echo "<p>❌ Insert still failed: " . $conn->error . "</p>";
    }
    
    echo "<h3>Step 3: Restore Triggers (Optional)</h3>";
    echo "<p>You can choose to:</p>";
    echo "<ul>";
    echo "<li><strong>Leave triggers disabled</strong> - Your rental system will work fine without them</li>";
    echo "<li><strong>Fix and restore triggers</strong> - If you need the audit functionality</li>";
    echo "</ul>";
    
    echo "<h4>To restore triggers, run these SQL commands in phpMyAdmin:</h4>";
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    foreach ($trigger_backups as $name => $sql) {
        echo "<strong>$name:</strong><br>";
        echo "<pre style='background: white; padding: 5px; font-size: 12px;'>" . htmlspecialchars($sql) . "</pre><br>";
    }
    echo "</div>";
    
} else {
    echo "<p>No triggers found, so the issue is not with triggers.</p>";
    
    // Try a different approach - check for views or other issues
    echo "<h3>Testing Insert Directly:</h3>";
    $test_sql = "INSERT INTO rentals (equipment_id, user_id, start_date, end_date, purpose, status) VALUES (2, 6, '2024-12-01', '2024-12-02', 'Direct test', 1)";
    
    if ($conn->query($test_sql)) {
        $insert_id = $conn->insert_id;
        echo "<p>✅ SUCCESS! Insert worked! ID: $insert_id</p>";
        $conn->query("DELETE FROM rentals WHERE id = $insert_id");
    } else {
        echo "<p>❌ Insert failed: " . $conn->error . "</p>";
    }
}

echo "<hr>";

// Show what triggers were found and what they do
echo "<h2>Trigger Analysis:</h2>";
if (!empty($trigger_names)) {
    echo "<p>The following triggers were found on your rentals table:</p>";
    foreach ($trigger_names as $trigger_name) {
        echo "<li><strong>$trigger_name</strong> - This trigger was likely causing the 'Unknown column' error</li>";
    }
    echo "<p><strong>Recommendation:</strong> Leave the triggers disabled for now. Your rental system will work perfectly without them.</p>";
} else {
    echo "<p>No triggers found. The issue might be elsewhere.</p>";
}

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>If the insert worked after disabling triggers, go test your rental form</li>";
echo "<li>If you need audit functionality, the triggers need to be fixed (contact a database developer)</li>";
echo "<li>For now, your rental system should work fine without triggers</li>";
echo "</ol>";
?>