<?php
// Test dashboard functionality without session requirements
require_once('db_connect.php');
require_once('includes/audit_trail.php');

echo "<h2>Testing Dashboard SecurityAuditTrail Integration</h2>\n";

try {
    // Test the SecurityAuditTrail class instantiation
    $audit = new SecurityAuditTrail($conn);
    echo "✓ SecurityAuditTrail class instantiated successfully\n";
    
    // Test the methods used in dashboard
    $securityStats = $audit->getSecurityStats(7);
    echo "✓ getSecurityStats(7) executed successfully\n";
    echo "Stats: " . json_encode($securityStats) . "\n";
    
    $recentSecurityEvents = $audit->getRecentEvents(10, ['severity' => 'HIGH']);
    echo "✓ getRecentEvents(10, ['severity' => 'HIGH']) executed successfully\n";
    echo "High severity events found: " . count($recentSecurityEvents) . "\n";
    
    $unresolvedEvents = $audit->getUnresolvedSecurityEvents();
    echo "✓ getUnresolvedSecurityEvents() executed successfully\n";
    echo "Unresolved events found: " . count($unresolvedEvents) . "\n";
    
    echo "\n<h3>Success!</h3>\n";
    echo "The SecurityAuditTrail class is working correctly and the dashboard should load without errors.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

$conn->close();
?>
