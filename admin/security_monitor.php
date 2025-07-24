<?php
/**
 * Security Monitor - Background threat detection script
 * Run this periodically via cron job or task scheduler
 * Example: php security_monitor.php
 */

require_once('db_connect.php');
require_once('includes/audit_trail.php');

echo "=== NCL Security Monitor Started ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$audit = new SecurityAuditTrail($conn);

// 1. Run automatic threat detection
echo "1. Running Threat Detection...\n";
$threats = $audit->detectSuspiciousActivity();
if (!empty($threats)) {
    echo "   ⚠️  " . count($threats) . " threats detected!\n";
    foreach ($threats as $threat) {
        if (isset($threat['ip_address'])) {
            echo "   - Suspicious IP: {$threat['ip_address']}\n";
        }
        if (isset($threat['username'])) {
            echo "   - Suspicious User: {$threat['username']}\n";
        }
    }
} else {
    echo "   ✅ No threats detected\n";
}

// 2. Generate security insights
echo "\n2. Generating Security Insights...\n";
$insights = $audit->getSecurityInsights(7);
echo "   Risk Assessment: {$insights['risk_assessment']}\n";
echo "   Recommendations: " . count($insights['recommendations']) . "\n";

if (!empty($insights['recommendations'])) {
    foreach ($insights['recommendations'] as $rec) {
        echo "   - {$rec['type']}: {$rec['title']}\n";
    }
}

// 3. Check system health
echo "\n3. System Health Check...\n";
$tablesExist = $audit->checkTablesExist();
echo "   Audit Tables: " . ($tablesExist ? "✅ OK" : "❌ Missing") . "\n";

// Check database connection
$dbStatus = $conn->ping();
echo "   Database Connection: " . ($dbStatus ? "✅ OK" : "❌ Failed") . "\n";

// Check recent activity
$recentEvents = $audit->getRecentEvents(1);
$lastActivity = !empty($recentEvents) ? $recentEvents[0]['created_at'] : 'None';
echo "   Last Security Event: $lastActivity\n";

// 4. Performance cleanup (run weekly)
$lastCleanup = file_get_contents(__DIR__ . '/last_cleanup.txt');
$lastCleanupTime = $lastCleanup ? strtotime($lastCleanup) : 0;
$weekAgo = time() - (7 * 24 * 60 * 60);

if ($lastCleanupTime < $weekAgo) {
    echo "\n4. Running Performance Cleanup...\n";
    $deletedRecords = $audit->cleanupOldRecords(90);
    echo "   Cleaned up: $deletedRecords old records\n";
    file_put_contents(__DIR__ . '/last_cleanup.txt', date('Y-m-d H:i:s'));
} else {
    echo "\n4. Cleanup not needed (last run: " . date('Y-m-d H:i:s', $lastCleanupTime) . ")\n";
}

// 5. Generate alerts for critical issues
echo "\n5. Alert Generation...\n";
$criticalEvents = $audit->getRecentEvents(10, ['severity' => 'CRITICAL']);
$unresolvedEvents = $audit->getUnresolvedSecurityEvents();

if (!empty($criticalEvents)) {
    echo "   🚨 " . count($criticalEvents) . " CRITICAL events in last 10!\n";
    
    // Here you could integrate with email, Slack, SMS, etc.
    foreach ($criticalEvents as $event) {
        echo "   - {$event['action_type']} from {$event['ip_address']} at {$event['created_at']}\n";
    }
}

if (count($unresolvedEvents) > 10) {
    echo "   ⚠️  " . count($unresolvedEvents) . " unresolved security events need attention!\n";
}

if (empty($criticalEvents) && count($unresolvedEvents) <= 5) {
    echo "   ✅ No immediate alerts\n";
}

// 6. Security Summary Report
echo "\n6. Security Summary (Last 24 Hours)...\n";
$stats24h = $audit->getSecurityStats(1);
echo "   Total Events: {$stats24h['total_events']}\n";
echo "   Failed Logins: {$stats24h['failed_logins']}\n";
echo "   Successful Logins: {$stats24h['successful_logins']}\n";
echo "   High Risk Events: {$stats24h['high_severity_events']}\n";
echo "   Critical Events: {$stats24h['critical_events']}\n";
echo "   Unique IPs: {$stats24h['unique_ips']}\n";

// Calculate security score (0-100, where 100 is best)
$securityScore = 100;
$securityScore -= min($stats24h['failed_logins'] * 2, 30); // Max -30 for failed logins
$securityScore -= $stats24h['high_severity_events'] * 5; // -5 per high severity
$securityScore -= $stats24h['critical_events'] * 15; // -15 per critical event
$securityScore -= min(count($unresolvedEvents), 10) * 2; // -2 per unresolved event (max 10)
$securityScore = max($securityScore, 0); // Ensure not negative

echo "\n   🛡️  Security Score: $securityScore/100\n";

if ($securityScore >= 90) {
    echo "   Status: EXCELLENT ✅\n";
} elseif ($securityScore >= 75) {
    echo "   Status: GOOD 👍\n";
} elseif ($securityScore >= 60) {
    echo "   Status: FAIR ⚠️\n";
} elseif ($securityScore >= 40) {
    echo "   Status: POOR ❌\n";
} else {
    echo "   Status: CRITICAL 🚨\n";
}

// Log the monitoring run
$audit->logEvent(
    null,
    'SYSTEM',
    'SYSTEM',
    null,
    "Security monitoring completed. Score: $securityScore/100, Threats: " . count($threats),
    'LOW'
);

echo "\n=== Security Monitor Completed ===\n";
echo "Next recommended run: " . date('Y-m-d H:i:s', time() + 3600) . " (1 hour)\n";

$conn->close();
?>
