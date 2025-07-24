<?php
session_start();
require_once('../db_connect.php');
require_once('../includes/auth.php');
require_once('../includes/audit_trail.php');
requireRole('admin');

$base = '/ncl-league-platform';

// Initialize security audit trail
$audit = new SecurityAuditTrail($conn);

// Get time range filter
$timeRange = $_GET['range'] ?? '7';
$validRanges = ['1', '7', '30', '90'];
if (!in_array($timeRange, $validRanges)) {
    $timeRange = '7';
}

// Get security statistics
$securityStats = $audit->getSecurityStats($timeRange);
$recentEvents = $audit->getRecentEvents(20);
$unresolvedEvents = $audit->getUnresolvedSecurityEvents();
$highSeverityEvents = $audit->getRecentEvents(10, ['severity' => 'HIGH']);
$criticalEvents = $audit->getRecentEvents(5, ['severity' => 'CRITICAL']);

// Calculate risk score and threat level
function calculateThreatLevel($stats, $unresolved) {
    $riskScore = 0;
    $riskScore += $stats['failed_logins'] * 2;
    $riskScore += $stats['high_severity_events'] * 5;
    $riskScore += $stats['critical_events'] * 10;
    $riskScore += count($unresolved) * 3;
    
    if ($riskScore >= 50) return ['level' => 'CRITICAL', 'color' => 'danger', 'icon' => 'exclamation-triangle'];
    if ($riskScore >= 25) return ['level' => 'HIGH', 'color' => 'warning', 'icon' => 'exclamation-circle'];
    if ($riskScore >= 10) return ['level' => 'MEDIUM', 'color' => 'info', 'icon' => 'info-circle'];
    return ['level' => 'LOW', 'color' => 'success', 'icon' => 'check-circle'];
}

$threatLevel = calculateThreatLevel($securityStats, $unresolvedEvents);

// Get geographical analysis of login attempts
function getGeoAnalysis($conn, $days) {
    $query = "SELECT ip_address, COUNT(*) as attempts, 
              SUM(CASE WHEN action_type = 'LOGIN_FAILED' THEN 1 ELSE 0 END) as failed_attempts,
              MAX(created_at) as last_attempt
              FROM security_audit_log 
              WHERE action_type IN ('LOGIN_SUCCESS', 'LOGIN_FAILED')
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY ip_address 
              ORDER BY attempts DESC 
              LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$geoData = getGeoAnalysis($conn, $timeRange);

// Get hourly activity pattern
function getHourlyActivity($conn, $days) {
    $query = "SELECT HOUR(created_at) as hour, COUNT(*) as activity_count
              FROM security_audit_log 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY HOUR(created_at)
              ORDER BY hour";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$hourlyData = getHourlyActivity($conn, $timeRange);

// Get user activity analysis
function getUserActivity($conn, $days) {
    $query = "SELECT username, user_role, COUNT(*) as actions,
              SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_actions,
              MAX(created_at) as last_activity,
              AVG(risk_score) as avg_risk_score
              FROM security_audit_log 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND username IS NOT NULL
              GROUP BY username, user_role
              ORDER BY actions DESC 
              LIMIT 15";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$userActivity = getUserActivity($conn, $timeRange);

include('../includes/header.php');
?>

<style>
.security-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.security-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.threat-level-critical { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
.threat-level-high { background: linear-gradient(135deg, #fd7e14 0%, #e55a4e 100%); }
.threat-level-medium { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
.threat-level-low { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }

.metric-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    border-left: 4px solid;
    transition: all 0.3s ease;
}

.metric-card:hover { transform: translateX(5px); }

.activity-timeline {
    max-height: 400px;
    overflow-y: auto;
}

.timeline-item {
    border-left: 2px solid #e9ecef;
    padding-left: 1rem;
    margin-bottom: 1rem;
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 8px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #6c757d;
}

.timeline-item.high-risk::before { background: #dc3545; }
.timeline-item.medium-risk::before { background: #ffc107; }
.timeline-item.low-risk::before { background: #28a745; }

.chart-container {
    position: relative;
    height: 300px;
}

.realtime-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #28a745;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

.alert-banner {
    border-radius: 10px;
    border: none;
    font-weight: 500;
}

.badge-pulse {
    animation: badge-pulse 2s infinite;
}

@keyframes badge-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}
</style>

<div class="container-fluid py-4" x-data="securityDashboard()">
    
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-shield-alt text-primary me-2"></i>
                        Security Command Center
                    </h1>
                    <p class="text-muted mb-0">
                        <span class="realtime-indicator me-2"></span>
                        Real-time security monitoring and threat detection
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <select class="form-select" onchange="window.location.href='?range='+this.value">
                        <option value="1" <?= $timeRange == '1' ? 'selected' : '' ?>>Last 24 Hours</option>
                        <option value="7" <?= $timeRange == '7' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30" <?= $timeRange == '30' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90" <?= $timeRange == '90' ? 'selected' : '' ?>>Last 90 Days</option>
                    </select>
                    <button class="btn btn-outline-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Threat Level Alert -->
    <?php if ($threatLevel['level'] !== 'LOW'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-<?= $threatLevel['color'] ?> alert-banner d-flex align-items-center">
                <i class="fas fa-<?= $threatLevel['icon'] ?> fa-2x me-3"></i>
                <div>
                    <h5 class="mb-1">Threat Level: <?= $threatLevel['level'] ?></h5>
                    <p class="mb-0">
                        Elevated security activity detected. 
                        <?= count($unresolvedEvents) ?> unresolved security events require attention.
                    </p>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-light text-dark badge-pulse fs-6">
                        Action Required
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Security Metrics Overview -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card border-start-success">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-success mb-1">Total Events</h6>
                        <h3 class="mb-0"><?= number_format($securityStats['total_events']) ?></h3>
                        <small class="text-muted">Last <?= $timeRange ?> days</small>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-list-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card border-start-danger">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-danger mb-1">Failed Logins</h6>
                        <h3 class="mb-0"><?= number_format($securityStats['failed_logins']) ?></h3>
                        <small class="text-muted">
                            <?= $securityStats['successful_logins'] ?> successful
                        </small>
                    </div>
                    <div class="text-danger">
                        <i class="fas fa-user-slash fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card border-start-warning">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-warning mb-1">High Risk Events</h6>
                        <h3 class="mb-0"><?= number_format($securityStats['high_severity_events']) ?></h3>
                        <small class="text-muted">
                            <?= $securityStats['critical_events'] ?> critical
                        </small>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card border-start-info">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-info mb-1">Unique IPs</h6>
                        <h3 class="mb-0"><?= number_format($securityStats['unique_ips']) ?></h3>
                        <small class="text-muted">Different sources</small>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-globe fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Activity Timeline -->
        <div class="col-xl-6 mb-4">
            <div class="card security-card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="card-title">
                        <i class="fas fa-clock text-primary me-2"></i>
                        Recent Security Events
                    </h5>
                </div>
                <div class="card-body">
                    <div class="activity-timeline">
                        <?php foreach ($recentEvents as $event): ?>
                        <div class="timeline-item <?= 
                            $event['severity_level'] === 'HIGH' ? 'high-risk' : 
                            ($event['severity_level'] === 'MEDIUM' ? 'medium-risk' : 'low-risk') 
                        ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?= htmlspecialchars($event['action_type']) ?>
                                        <?php if (!$event['success']): ?>
                                        <span class="badge bg-danger ms-2">Failed</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted mb-1 small">
                                        User: <?= htmlspecialchars($event['username'] ?? 'Unknown') ?> |
                                        IP: <?= htmlspecialchars($event['ip_address'] ?? 'N/A') ?>
                                    </p>
                                    <?php if ($event['action_description']): ?>
                                    <p class="text-muted mb-1 small">
                                        <?= htmlspecialchars($event['action_description']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">
                                        <?= date('M j, H:i', strtotime($event['created_at'])) ?>
                                    </small>
                                    <br>
                                    <span class="badge bg-<?= 
                                        $event['severity_level'] === 'HIGH' ? 'danger' : 
                                        ($event['severity_level'] === 'MEDIUM' ? 'warning' : 'secondary') 
                                    ?> small">
                                        <?= $event['severity_level'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentEvents)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-shield-alt fa-3x mb-3 opacity-50"></i>
                            <p>No security events in the selected time range.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Threat Analysis -->
        <div class="col-xl-6 mb-4">
            <div class="card security-card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="card-title">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Unresolved Threats
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($unresolvedEvents)): ?>
                    <div class="activity-timeline">
                        <?php foreach ($unresolvedEvents as $event): ?>
                        <div class="timeline-item high-risk">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 text-danger">
                                        <?= htmlspecialchars($event['event_type']) ?>
                                    </h6>
                                    <p class="text-muted mb-1 small">
                                        <?= htmlspecialchars($event['description']) ?>
                                    </p>
                                    <?php if ($event['ip_address']): ?>
                                    <p class="text-muted mb-1 small">
                                        IP: <?= htmlspecialchars($event['ip_address']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">
                                        <?= date('M j, H:i', strtotime($event['created_at'])) ?>
                                    </small>
                                    <br>
                                    <span class="badge bg-<?= 
                                        $event['severity'] === 'CRITICAL' ? 'danger' : 
                                        ($event['severity'] === 'HIGH' ? 'warning' : 'info') 
                                    ?>">
                                        <?= $event['severity'] ?>
                                    </span>
                                    <br>
                                    <button class="btn btn-sm btn-outline-success mt-1" 
                                            onclick="resolveEvent(<?= $event['event_id'] ?>)">
                                        <i class="fas fa-check"></i> Resolve
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-success py-4">
                        <i class="fas fa-shield-check fa-3x mb-3"></i>
                        <h5 class="text-success">All Clear!</h5>
                        <p class="text-muted">No unresolved security threats detected.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Activity & Geographic Analysis -->
    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card security-card">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="card-title">
                        <i class="fas fa-users text-info me-2"></i>
                        User Activity Analysis
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Total Actions</th>
                                    <th>Failed Actions</th>
                                    <th>Risk Score</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userActivity as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $user['user_role'] === 'admin' ? 'danger' : 
                                            ($user['user_role'] === 'manager' ? 'warning' : 'secondary') 
                                        ?>">
                                            <?= ucfirst($user['user_role']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($user['actions']) ?></td>
                                    <td>
                                        <?php if ($user['failed_actions'] > 0): ?>
                                        <span class="text-danger fw-bold">
                                            <?= number_format($user['failed_actions']) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $riskScore = round($user['avg_risk_score'], 1);
                                        $riskColor = $riskScore >= 7 ? 'danger' : ($riskScore >= 4 ? 'warning' : 'success');
                                        ?>
                                        <span class="badge bg-<?= $riskColor ?>">
                                            <?= $riskScore ?>/10
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M j, H:i', strtotime($user['last_activity'])) ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card security-card">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="card-title">
                        <i class="fas fa-globe text-primary me-2"></i>
                        Geographic Analysis
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($geoData as $geo): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                        <div>
                            <strong><?= htmlspecialchars($geo['ip_address']) ?></strong>
                            <br>
                            <small class="text-muted">
                                <?= $geo['attempts'] ?> attempts
                                <?php if ($geo['failed_attempts'] > 0): ?>
                                | <span class="text-danger"><?= $geo['failed_attempts'] ?> failed</span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                <?= date('M j, H:i', strtotime($geo['last_attempt'])) ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Pattern Chart -->
    <div class="row">
        <div class="col-12">
            <div class="card security-card">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="card-title">
                        <i class="fas fa-chart-line text-success me-2"></i>
                        24-Hour Activity Pattern
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Alpine.js component for interactivity
function securityDashboard() {
    return {
        autoRefresh: true,
        refreshInterval: 30000, // 30 seconds
        
        init() {
            if (this.autoRefresh) {
                setInterval(() => {
                    location.reload();
                }, this.refreshInterval);
            }
            
            this.initCharts();
        },
        
        initCharts() {
            // Activity pattern chart
            const hourlyData = <?= json_encode($hourlyData) ?>;
            const hours = Array.from({length: 24}, (_, i) => i);
            const activityCounts = hours.map(hour => {
                const data = hourlyData.find(d => d.hour == hour);
                return data ? data.activity_count : 0;
            });
            
            const ctx = document.getElementById('activityChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: hours.map(h => `${h}:00`),
                    datasets: [{
                        label: 'Security Events',
                        data: activityCounts,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }
}

// Resolve security event
function resolveEvent(eventId) {
    if (confirm('Mark this security event as resolved?')) {
        fetch('security_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'resolve_event',
                event_id: eventId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error resolving event: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}

// Auto-refresh toggle
function toggleAutoRefresh() {
    const component = Alpine.$data(document.querySelector('[x-data]'));
    component.autoRefresh = !component.autoRefresh;
}
</script>

<?php include('../includes/footer.php'); ?>
