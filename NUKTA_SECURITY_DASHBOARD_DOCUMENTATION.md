# NCL League Platform - Intelligent Security Dashboard

## Overview

The Intelligent Security Dashboard provides comprehensive real-time security monitoring, threat detection, and audit trail management for the NCL League Platform. It's designed to help administrators maintain platform security and quickly respond to potential threats.

## Features

### 🛡️ Real-Time Security Monitoring
- **Live Security Metrics**: Track total events, failed logins, high-risk events, and unique IP addresses
- **Threat Level Assessment**: Automatic calculation of overall security threat level (LOW, MEDIUM, HIGH, CRITICAL)
- **Real-Time Updates**: Dashboard updates every 30 seconds to show current security status

### 📊 Comprehensive Analytics
- **Security Statistics**: 1, 7, 30, and 90-day views of security events
- **User Activity Analysis**: Track user behavior, failed actions, and risk scores
- **Geographic Analysis**: Monitor login attempts from different IP addresses
- **24-Hour Activity Patterns**: Visualize security events throughout the day

### 🚨 Intelligent Threat Detection
- **Automated Threat Detection**: Automatically identifies suspicious patterns:
  - Multiple failed logins from same IP (5+ in 1 hour)
  - Unusual login locations (same user from different IPs within 10 minutes)
  - Privilege escalation attempts
  - Suspicious data access patterns

### 📋 Event Management
- **Unresolved Security Events**: Track and manage security incidents requiring attention
- **Event Resolution**: Mark security events as resolved with admin tracking
- **Event Details**: Comprehensive logging of all security-related activities

### 📈 Security Insights & Recommendations
- **Smart Recommendations**: AI-powered suggestions based on security patterns
- **Risk Assessment**: Automated security scoring and recommendations
- **Pattern Recognition**: Identify peak activity hours and unusual behavior

## Files Structure

```
admin/
├── security_dashboard.php    # Main security dashboard interface
├── security_api.php         # API endpoints for security actions
├── security_monitor.php     # Background monitoring script
└── run_security_monitor.bat  # Windows batch file for scheduled monitoring

includes/
└── audit_trail.php          # Enhanced SecurityAuditTrail class

sql/
└── audit_trail_schema.sql   # Database schema for security tables
```

## Database Tables

### Primary Tables
1. **security_audit_log** - Main audit trail with all security events
2. **security_risk_events** - High-priority security incidents
3. **failed_login_attempts** - Failed authentication attempts
4. **session_activity** - User session tracking
5. **data_change_history** - Critical data modifications
6. **admin_actions_log** - Administrative actions

## Installation & Setup

### 1. Database Setup
Run the audit trail schema to create required tables:
```bash
php setup_audit_trail_tables.php
```

### 2. Security Dashboard Access
Navigate to: `/admin/security_dashboard.php`
- **Required Role**: Admin
- **Access Control**: Automatically enforced via auth.php

### 3. Background Monitoring (Optional)
Set up automated security monitoring:

#### Windows (Task Scheduler):
```bash
# Run every hour
run_security_monitor.bat
```

#### Linux/Mac (Cron):
```bash
# Add to crontab (run every hour)
0 * * * * cd /path/to/ncl-league-platform && php admin/security_monitor.php
```

## Dashboard Sections

### 1. Threat Level Alert
- Displays current threat level with color-coded alerts
- Shows number of unresolved security events
- Provides quick action recommendations

### 2. Security Metrics Overview
- **Total Events**: All security activities in selected timeframe
- **Failed Logins**: Authentication failures vs successes
- **High Risk Events**: Events marked as high/critical severity
- **Unique IPs**: Number of different IP addresses accessing the system

### 3. Activity Timeline
- **Recent Security Events**: Chronological list of latest security activities
- **Event Details**: User, IP address, action type, and severity
- **Status Indicators**: Success/failure status for each event

### 4. Unresolved Threats
- **Active Security Incidents**: Events requiring admin attention
- **Threat Details**: Event type, severity, and description
- **Resolution Actions**: One-click event resolution

### 5. User Activity Analysis
- **User Behavior Tracking**: Actions per user with risk scoring
- **Failed Action Monitoring**: Identify problematic user behavior
- **Role-Based Analysis**: Different risk assessment for admin vs regular users

### 6. Geographic Analysis
- **IP Address Monitoring**: Track access from different locations
- **Failed Login Geography**: Identify potential attack sources
- **Access Pattern Analysis**: Unusual geographic access patterns

### 7. Activity Pattern Chart
- **24-Hour Activity Graph**: Visualize security events throughout the day
- **Peak Hour Identification**: Identify busiest and quietest periods
- **Pattern Recognition**: Spot unusual activity spikes

## API Endpoints

### POST /admin/security_api.php

#### Resolve Security Event
```json
{
  "action": "resolve_event",
  "event_id": 123
}
```

#### Get Real-Time Stats
```json
{
  "action": "get_real_time_stats"
}
```

#### Create Security Alert
```json
{
  "action": "create_security_alert",
  "event_type": "SUSPICIOUS_ACTIVITY",
  "severity": "HIGH",
  "description": "Manual security alert description"
}
```

#### Export Security Report
```json
{
  "action": "export_security_report",
  "days": 30,
  "format": "csv|json"
}
```

#### Get Threat Intelligence
```json
{
  "action": "get_threat_intelligence"
}
```

## Security Monitoring Script

The `security_monitor.php` script provides automated background monitoring:

### Features:
1. **Automatic Threat Detection** - Scans for suspicious patterns
2. **Security Health Check** - Verifies system integrity
3. **Performance Cleanup** - Removes old audit records (configurable retention)
4. **Alert Generation** - Creates notifications for critical issues
5. **Security Scoring** - Calculates overall security score (0-100)

### Output Example:
```
=== NCL Security Monitor Started ===
Timestamp: 2025-07-22 17:05:52

1. Running Threat Detection...
   ✅ No threats detected

2. Generating Security Insights...
   Risk Assessment: LOW
   Recommendations: 0

3. System Health Check...
   Audit Tables: ✅ OK
   Database Connection: ✅ OK
   Last Security Event: 2025-07-22 12:37:25

4. Running Performance Cleanup...
   Cleaned up: 0 old records

5. Alert Generation...
   ✅ No immediate alerts

6. Security Summary (Last 24 Hours)...
   Total Events: 1
   Failed Logins: 0
   Successful Logins: 0
   High Risk Events: 0
   Critical Events: 0
   Unique IPs: 1

   🛡️ Security Score: 98/100
   Status: EXCELLENT ✅

=== Security Monitor Completed ===
```

## Security Best Practices

### 1. Regular Monitoring
- Check security dashboard daily
- Review unresolved events promptly
- Monitor threat level changes

### 2. Automated Monitoring
- Set up hourly background monitoring
- Configure alerts for critical events
- Implement log rotation for performance

### 3. User Management
- Regularly review user activity patterns
- Investigate users with high risk scores
- Monitor privilege escalation attempts

### 4. IP Address Monitoring
- Track failed logins from specific IPs
- Consider IP blocking for repeat offenders
- Monitor geographic access patterns

### 5. Data Retention
- Configure appropriate audit log retention
- Balance storage space with security needs
- Regular cleanup of old records

## Troubleshooting

### Common Issues:

#### 1. "Class SecurityAuditTrail not found"
**Solution**: Ensure audit_trail.php is included and SecurityAuditTrail class exists

#### 2. Missing audit tables
**Solution**: Run `php setup_audit_trail_tables.php`

#### 3. Permission denied on security dashboard
**Solution**: Verify user has 'admin' role in session

#### 4. Background monitor not running
**Solution**: Check file permissions and PHP path in batch/cron job

### Performance Optimization:

1. **Database Indexing**: Ensure proper indexes on audit tables
2. **Log Rotation**: Regular cleanup of old audit records
3. **Query Optimization**: Monitor slow queries in audit operations

## Integration with Main Dashboard

The security dashboard is integrated into the main admin dashboard:

- **Security Overview Card**: Shows key metrics and alerts
- **Quick Access Button**: Direct link to full security dashboard
- **Unresolved Events Alert**: Prominent display of critical security issues

## Security Score Calculation

The intelligent security scoring system evaluates platform security:

```
Base Score: 100 points
- Failed Logins: -2 points each (max -30)
- High Severity Events: -5 points each
- Critical Events: -15 points each
- Unresolved Events: -2 points each (max -20)

Final Score: max(calculated_score, 0)
```

### Score Interpretation:
- **90-100**: EXCELLENT ✅
- **75-89**: GOOD 👍
- **60-74**: FAIR ⚠️
- **40-59**: POOR ❌
- **0-39**: CRITICAL 🚨

## Extensibility

The security system is designed for easy extension:

### Adding New Threat Types:
1. Update `security_risk_events.event_type` enum
2. Implement detection logic in `detectSuspiciousActivity()`
3. Add handling in security dashboard

### Custom Security Metrics:
1. Extend `getSecurityStats()` method
2. Add new database queries for custom metrics
3. Update dashboard display accordingly

### Integration with External Systems:
- Email notifications for critical events
- Slack/Teams integration for alerts
- SIEM system integration
- Mobile push notifications

## Conclusion

The Intelligent Security Dashboard provides comprehensive security monitoring and threat detection for the NCL League Platform. With real-time monitoring, automated threat detection, and intelligent analytics, administrators can maintain a secure environment and quickly respond to potential security incidents.

For support or feature requests, please refer to the main project documentation or contact the development team.
