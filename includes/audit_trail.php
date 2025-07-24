<?php

class SecurityAuditTrail {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get security statistics for a given number of days
     */
    public function getSecurityStats($days = 7) {
        $stats = [
            'total_events' => 0,
            'failed_logins' => 0,
            'high_severity_events' => 0,
            'critical_events' => 0,
            'unique_ips' => 0,
            'successful_logins' => 0
        ];
        
        try {
            // Total security events in the last X days
            $query = "SELECT COUNT(*) as total FROM security_audit_log 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['total_events'] = (int)$row['total'];
            }
            
            // Failed login attempts
            $query = "SELECT COUNT(*) as total FROM security_audit_log 
                     WHERE action_type = 'LOGIN_FAILED' 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['failed_logins'] = (int)$row['total'];
            }
            
            // Successful logins
            $query = "SELECT COUNT(*) as total FROM security_audit_log 
                     WHERE action_type = 'LOGIN_SUCCESS' 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['successful_logins'] = (int)$row['total'];
            }
            
            // High severity events
            $query = "SELECT COUNT(*) as total FROM security_audit_log 
                     WHERE severity_level = 'HIGH' 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['high_severity_events'] = (int)$row['total'];
            }
            
            // Critical events
            $query = "SELECT COUNT(*) as total FROM security_audit_log 
                     WHERE severity_level = 'CRITICAL' 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['critical_events'] = (int)$row['total'];
            }
            
            // Unique IP addresses
            $query = "SELECT COUNT(DISTINCT ip_address) as total FROM security_audit_log 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['unique_ips'] = (int)$row['total'];
            }
            
        } catch (Exception $e) {
            // If tables don't exist, return empty stats
            error_log("Security audit error: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Get recent security events with optional filters
     */
    public function getRecentEvents($limit = 10, $filters = []) {
        $events = [];
        
        try {
            $whereConditions = [];
            $params = [];
            $types = "";
            
            if (isset($filters['severity'])) {
                $whereConditions[] = "severity_level = ?";
                $params[] = $filters['severity'];
                $types .= "s";
            }
            
            if (isset($filters['action_type'])) {
                $whereConditions[] = "action_type = ?";
                $params[] = $filters['action_type'];
                $types .= "s";
            }
            
            if (isset($filters['user_id'])) {
                $whereConditions[] = "user_id = ?";
                $params[] = $filters['user_id'];
                $types .= "i";
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $query = "SELECT log_id, user_id, username, user_role, action_type, resource_type, 
                            resource_id, resource_name, action_description, ip_address, 
                            severity_level, risk_score, success, error_message, created_at
                     FROM security_audit_log 
                     $whereClause
                     ORDER BY created_at DESC 
                     LIMIT ?";
            
            $params[] = $limit;
            $types .= "i";
            
            $stmt = $this->conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            
        } catch (Exception $e) {
            error_log("Error fetching recent events: " . $e->getMessage());
        }
        
        return $events;
    }
    
    /**
     * Get unresolved security events from security_risk_events table
     */
    public function getUnresolvedSecurityEvents() {
        $events = [];
        
        try {
            $query = "SELECT event_id, user_id, event_type, severity, description, 
                            ip_address, user_agent, auto_detected, created_at
                     FROM security_risk_events 
                     WHERE resolved = FALSE 
                     ORDER BY severity DESC, created_at DESC 
                     LIMIT 20";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            
        } catch (Exception $e) {
            error_log("Error fetching unresolved security events: " . $e->getMessage());
        }
        
        return $events;
    }
    
    /**
     * Log a security event
     */
    public function logEvent($userId, $actionType, $resourceType = null, $resourceId = null, 
                            $description = '', $severity = 'LOW', $success = true, $errorMessage = null) {
        try {
            $sessionId = session_id();
            $username = $_SESSION['username'] ?? 'unknown';
            $userRole = $_SESSION['role'] ?? 'guest';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            
            $query = "INSERT INTO security_audit_log 
                     (user_id, session_id, username, user_role, action_type, resource_type, 
                      resource_id, action_description, ip_address, user_agent, request_uri, 
                      request_method, severity_level, success, error_message)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("isssssississsbs", 
                $userId, $sessionId, $username, $userRole, $actionType, $resourceType,
                $resourceId, $description, $ipAddress, $userAgent, $requestUri,
                $requestMethod, $severity, $success, $errorMessage
            );
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error logging security event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if audit tables exist
     */
    public function checkTablesExist() {
        try {
            $tables = ['security_audit_log', 'security_risk_events', 'failed_login_attempts', 
                      'session_activity', 'data_change_history', 'admin_actions_log'];
            
            foreach ($tables as $table) {
                $query = "SELECT 1 FROM information_schema.tables 
                         WHERE table_schema = DATABASE() AND table_name = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("s", $table);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    error_log("Missing audit table: " . $table);
                    return false;
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error checking tables: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Detect suspicious login patterns and create security events
     */
    public function detectSuspiciousActivity() {
        try {
            $detectedThreats = [];
            
            // Detect multiple failed logins from same IP
            $query = "SELECT ip_address, COUNT(*) as failed_count, MAX(created_at) as last_attempt
                     FROM security_audit_log 
                     WHERE action_type = 'LOGIN_FAILED' 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                     GROUP BY ip_address 
                     HAVING failed_count >= 5";
            
            $result = $this->conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $this->createSecurityEvent(
                    null,
                    'MULTIPLE_FAILED_LOGINS',
                    'HIGH',
                    "Multiple failed login attempts ({$row['failed_count']}) from IP {$row['ip_address']}",
                    $row['ip_address']
                );
                $detectedThreats[] = $row;
            }
            
            // Detect unusual login locations (same user from different IPs quickly)
            $query = "SELECT s1.user_id, s1.username, s1.ip_address as ip1, s2.ip_address as ip2,
                            s1.created_at as time1, s2.created_at as time2
                     FROM security_audit_log s1
                     JOIN security_audit_log s2 ON s1.user_id = s2.user_id
                     WHERE s1.action_type = 'LOGIN_SUCCESS' 
                     AND s2.action_type = 'LOGIN_SUCCESS'
                     AND s1.ip_address != s2.ip_address
                     AND s1.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                     AND s2.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                     AND ABS(TIMESTAMPDIFF(MINUTE, s1.created_at, s2.created_at)) <= 10";
            
            $result = $this->conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $this->createSecurityEvent(
                    $row['user_id'],
                    'UNUSUAL_LOGIN_LOCATION',
                    'MEDIUM',
                    "User {$row['username']} logged in from different IPs within 10 minutes: {$row['ip1']} and {$row['ip2']}",
                    $row['ip2']
                );
                $detectedThreats[] = $row;
            }
            
            return $detectedThreats;
            
        } catch (Exception $e) {
            error_log("Error in threat detection: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new security risk event
     */
    public function createSecurityEvent($userId, $eventType, $severity, $description, $ipAddress = null, $additionalData = null) {
        try {
            $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $additionalJson = $additionalData ? json_encode($additionalData) : null;
            
            // Check if similar event already exists in the last hour to avoid duplicates
            $checkQuery = "SELECT COUNT(*) as count FROM security_risk_events 
                          WHERE event_type = ? AND ip_address = ? 
                          AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bind_param("ss", $eventType, $ipAddress);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $existingCount = $result->fetch_assoc()['count'];
            
            if ($existingCount > 0) {
                return false; // Skip duplicate events
            }
            
            $query = "INSERT INTO security_risk_events 
                     (user_id, event_type, severity, description, ip_address, user_agent, additional_data, auto_detected)
                     VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("issssss", $userId, $eventType, $severity, $description, $ipAddress, $userAgent, $additionalJson);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error creating security event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get security insights and recommendations
     */
    public function getSecurityInsights($days = 7) {
        $insights = [
            'recommendations' => [],
            'patterns' => [],
            'risk_assessment' => 'LOW'
        ];
        
        try {
            $stats = $this->getSecurityStats($days);
            
            // Analyze patterns and generate recommendations
            if ($stats['failed_logins'] > 10) {
                $insights['recommendations'][] = [
                    'type' => 'HIGH_PRIORITY',
                    'title' => 'High Failed Login Activity',
                    'description' => "There have been {$stats['failed_logins']} failed login attempts in the last {$days} days. Consider implementing account lockout policies.",
                    'action' => 'Review failed login attempts and consider IP blocking for repeat offenders.'
                ];
            }
            
            if ($stats['unique_ips'] > 50) {
                $insights['recommendations'][] = [
                    'type' => 'MEDIUM_PRIORITY',
                    'title' => 'High IP Diversity',
                    'description' => "Access from {$stats['unique_ips']} unique IP addresses detected. Monitor for unusual geographic patterns.",
                    'action' => 'Review geographic distribution of access attempts.'
                ];
            }
            
            if ($stats['critical_events'] > 0) {
                $insights['risk_assessment'] = 'CRITICAL';
                $insights['recommendations'][] = [
                    'type' => 'CRITICAL',
                    'title' => 'Critical Security Events',
                    'description' => "{$stats['critical_events']} critical security events require immediate attention.",
                    'action' => 'Investigate all critical events immediately and take appropriate action.'
                ];
            } elseif ($stats['high_severity_events'] > 5) {
                $insights['risk_assessment'] = 'HIGH';
            } elseif ($stats['failed_logins'] > 20) {
                $insights['risk_assessment'] = 'MEDIUM';
            }
            
            // Detect patterns in hourly activity
            $hourlyQuery = "SELECT HOUR(created_at) as hour, COUNT(*) as count
                           FROM security_audit_log 
                           WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                           GROUP BY HOUR(created_at)
                           ORDER BY count DESC
                           LIMIT 3";
            
            $stmt = $this->conn->prepare($hourlyQuery);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $peakHours = [];
            while ($row = $result->fetch_assoc()) {
                $peakHours[] = $row['hour'] . ':00';
            }
            
            if (!empty($peakHours)) {
                $insights['patterns'][] = [
                    'type' => 'ACTIVITY_PATTERN',
                    'title' => 'Peak Activity Hours',
                    'description' => 'Most security events occur during: ' . implode(', ', $peakHours),
                    'data' => $peakHours
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error generating security insights: " . $e->getMessage());
        }
        
        return $insights;
    }
    
    /**
     * Auto-cleanup old audit records based on retention policy
     */
    public function cleanupOldRecords($retentionDays = 90) {
        $deletedRecords = 0;
        
        try {
            // Define tables with their timestamp columns
            $tables = [
                'security_audit_log' => 'created_at',
                'failed_login_attempts' => 'attempt_time',
                'session_activity' => 'login_time'
            ];
            
            foreach ($tables as $table => $timeColumn) {
                // Check if table exists first
                $checkQuery = "SHOW TABLES LIKE ?";
                $checkStmt = $this->conn->prepare($checkQuery);
                $checkStmt->bind_param("s", $table);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $query = "DELETE FROM $table WHERE $timeColumn < DATE_SUB(NOW(), INTERVAL ? DAY)";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bind_param("i", $retentionDays);
                    $stmt->execute();
                    $deletedRecords += $stmt->affected_rows;
                }
            }
            
            // Log the cleanup action
            $this->logEvent(
                null,
                'SYSTEM',
                'SYSTEM',
                null,
                "Automated cleanup: deleted $deletedRecords old audit records older than $retentionDays days",
                'LOW'
            );
            
        } catch (Exception $e) {
            error_log("Error during cleanup: " . $e->getMessage());
        }
        
        return $deletedRecords;
    }
}

?>