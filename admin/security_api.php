<?php
session_start();
require_once('../db_connect.php');
require_once('../includes/auth.php');
require_once('../includes/audit_trail.php');

// Ensure only admins can access this API
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$audit = new SecurityAuditTrail($conn);

switch ($action) {
    case 'resolve_event':
        $eventId = intval($input['event_id'] ?? 0);
        
        if ($eventId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
            exit;
        }
        
        try {
            // Mark event as resolved
            $query = "UPDATE security_risk_events 
                     SET resolved = TRUE, resolved_by = ?, resolved_at = NOW() 
                     WHERE event_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $_SESSION['user_id'], $eventId);
            
            if ($stmt->execute()) {
                // Log the resolution action
                $audit->logEvent(
                    $_SESSION['user_id'], 
                    'ADMIN_ACTION', 
                    'SECURITY_EVENT', 
                    $eventId,
                    "Resolved security event ID: $eventId",
                    'MEDIUM'
                );
                
                echo json_encode(['success' => true, 'message' => 'Event resolved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to resolve event']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_real_time_stats':
        try {
            $stats = $audit->getSecurityStats(1); // Last 24 hours
            $recentEvents = $audit->getRecentEvents(5);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'recent_events' => $recentEvents,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching stats: ' . $e->getMessage()]);
        }
        break;
        
    case 'create_security_alert':
        $eventType = $input['event_type'] ?? '';
        $severity = $input['severity'] ?? 'MEDIUM';
        $description = $input['description'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($eventType) || empty($description)) {
            echo json_encode(['success' => false, 'message' => 'Event type and description are required']);
            exit;
        }
        
        try {
            $query = "INSERT INTO security_risk_events 
                     (user_id, event_type, severity, description, ip_address, user_agent, auto_detected) 
                     VALUES (?, ?, ?, ?, ?, ?, FALSE)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isssss", $_SESSION['user_id'], $eventType, $severity, $description, $ipAddress, $userAgent);
            
            if ($stmt->execute()) {
                $eventId = $conn->insert_id;
                
                // Log the alert creation
                $audit->logEvent(
                    $_SESSION['user_id'], 
                    'ADMIN_ACTION', 
                    'SECURITY_EVENT', 
                    $eventId,
                    "Created manual security alert: $eventType",
                    'MEDIUM'
                );
                
                echo json_encode(['success' => true, 'message' => 'Security alert created', 'event_id' => $eventId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create alert']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'export_security_report':
        $days = intval($input['days'] ?? 7);
        $format = $input['format'] ?? 'json';
        
        try {
            $stats = $audit->getSecurityStats($days);
            $events = $audit->getRecentEvents(100);
            $unresolved = $audit->getUnresolvedSecurityEvents();
            
            $reportData = [
                'report_generated' => date('Y-m-d H:i:s'),
                'time_range_days' => $days,
                'statistics' => $stats,
                'recent_events' => $events,
                'unresolved_events' => $unresolved,
                'generated_by' => $_SESSION['username']
            ];
            
            if ($format === 'csv') {
                // Generate CSV format
                $csv = "Security Report - Generated on " . date('Y-m-d H:i:s') . "\n\n";
                $csv .= "STATISTICS:\n";
                foreach ($stats as $key => $value) {
                    $csv .= ucfirst(str_replace('_', ' ', $key)) . "," . $value . "\n";
                }
                
                $csv .= "\nRECENT EVENTS:\n";
                $csv .= "Timestamp,User,Action,Resource,IP Address,Severity,Success\n";
                foreach ($events as $event) {
                    $csv .= implode(',', [
                        $event['created_at'],
                        $event['username'] ?? 'N/A',
                        $event['action_type'],
                        $event['resource_type'] ?? 'N/A',
                        $event['ip_address'] ?? 'N/A',
                        $event['severity_level'],
                        $event['success'] ? 'Yes' : 'No'
                    ]) . "\n";
                }
                
                echo json_encode(['success' => true, 'data' => $csv, 'format' => 'csv']);
            } else {
                echo json_encode(['success' => true, 'data' => $reportData, 'format' => 'json']);
            }
            
            // Log the export action
            $audit->logEvent(
                $_SESSION['user_id'], 
                'EXPORT_DATA', 
                'REPORT', 
                null,
                "Exported security report for $days days in $format format",
                'LOW'
            );
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error generating report: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_threat_intelligence':
        try {
            // Get IP addresses with multiple failed logins
            $suspiciousIPs = [];
            $query = "SELECT ip_address, COUNT(*) as failed_count, MAX(created_at) as last_attempt
                     FROM security_audit_log 
                     WHERE action_type = 'LOGIN_FAILED' 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     GROUP BY ip_address 
                     HAVING failed_count >= 3
                     ORDER BY failed_count DESC";
            
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $suspiciousIPs[] = $row;
            }
            
            // Get users with suspicious activity patterns
            $suspiciousUsers = [];
            $query = "SELECT username, user_role, 
                            COUNT(*) as total_actions,
                            SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_actions,
                            COUNT(DISTINCT ip_address) as unique_ips,
                            AVG(risk_score) as avg_risk
                     FROM security_audit_log 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     AND username IS NOT NULL
                     GROUP BY username, user_role
                     HAVING failed_actions > 2 OR unique_ips > 3 OR avg_risk > 5
                     ORDER BY avg_risk DESC";
            
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $suspiciousUsers[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'suspicious_ips' => $suspiciousIPs,
                'suspicious_users' => $suspiciousUsers,
                'analysis_timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error generating threat intelligence: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
