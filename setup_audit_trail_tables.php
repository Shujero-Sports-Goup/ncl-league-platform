<?php
require_once('db_connect.php');

echo "<h2>Setting up Audit Trail Tables</h2>\n";

// Read the SQL schema file
$sqlFile = 'sql/audit_trail_schema.sql';
if (!file_exists($sqlFile)) {
    die("Error: audit_trail_schema.sql file not found!\n");
}

$sql = file_get_contents($sqlFile);

// Remove comments and split by semicolon
$sql = preg_replace('/--.*$/m', '', $sql);  // Remove single-line comments
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);  // Remove multi-line comments
$statements = array_filter(array_map('trim', explode(';', $sql)));

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strtoupper(trim($statement)) === 'USE NCL_LEAGUE_SYSTEM') {
        continue;
    }
    
    try {
        if ($conn->query($statement)) {
            echo "✓ Successfully executed statement\n";
            $successCount++;
        } else {
            echo "✗ Error executing statement: " . $conn->error . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
            $errorCount++;
        }
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n";
        echo "Statement: " . substr($statement, 0, 100) . "...\n";
        $errorCount++;
    }
}

echo "\n<h3>Setup Complete</h3>\n";
echo "Successful statements: $successCount\n";
echo "Failed statements: $errorCount\n";

// Test if all tables were created successfully
echo "\n<h3>Verifying Tables</h3>\n";
$requiredTables = [
    'security_audit_log',
    'failed_login_attempts', 
    'session_activity',
    'data_change_history',
    'security_risk_events',
    'admin_actions_log'
];

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' not found\n";
    }
}

// Test the SecurityAuditTrail class
echo "\n<h3>Testing SecurityAuditTrail Class</h3>\n";
require_once('includes/audit_trail.php');

try {
    $audit = new SecurityAuditTrail($conn);
    
    if ($audit->checkTablesExist()) {
        echo "✓ All audit tables exist and are accessible\n";
        
        // Test getting stats
        $stats = $audit->getSecurityStats(7);
        echo "✓ getSecurityStats() method working\n";
        echo "Total events in last 7 days: " . $stats['total_events'] . "\n";
        
        // Test getting recent events
        $events = $audit->getRecentEvents(5);
        echo "✓ getRecentEvents() method working\n";
        echo "Recent events found: " . count($events) . "\n";
        
        // Test getting unresolved events
        $unresolved = $audit->getUnresolvedSecurityEvents();
        echo "✓ getUnresolvedSecurityEvents() method working\n";
        echo "Unresolved events found: " . count($unresolved) . "\n";
        
    } else {
        echo "✗ Some audit tables are missing\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error testing SecurityAuditTrail class: " . $e->getMessage() . "\n";
}

$conn->close();
?>
