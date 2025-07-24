-- Audit Trail Security System
-- Run this after your main schema.sql

USE ncl_league_system;

-- 1. Security Audit Log Table
CREATE TABLE security_audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    session_id VARCHAR(255),
    username VARCHAR(50),
    user_role ENUM('admin', 'manager', 'referee', 'guest') DEFAULT 'guest',
    action_type ENUM(
        'LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT',
        'CREATE', 'READ', 'UPDATE', 'DELETE',
        'ACCESS_DENIED', 'PERMISSION_VIOLATION',
        'PASSWORD_CHANGE', 'ROLE_CHANGE',
        'TEAM_REGISTRATION', 'FIXTURE_CREATE', 'SCORE_SUBMIT',
        'ADMIN_ACTION', 'EXPORT_DATA', 'BULK_OPERATION'
    ) NOT NULL,
    resource_type ENUM(
        'USER', 'TEAM', 'PLAYER', 'FIXTURE', 'LEAGUE', 
        'MATCH_RESULT', 'REGISTRATION', 'SYSTEM', 'REPORT'
    ),
    resource_id INT NULL,
    resource_name VARCHAR(255),
    action_description TEXT,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_uri VARCHAR(500),
    request_method ENUM('GET', 'POST', 'PUT', 'DELETE', 'PATCH'),
    http_status INT DEFAULT 200,
    execution_time DECIMAL(8,3) DEFAULT 0.000,
    severity_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
    risk_score TINYINT DEFAULT 0,
    success BOOLEAN DEFAULT TRUE,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_resource_type (resource_type),
    INDEX idx_created_at (created_at),
    INDEX idx_severity (severity_level),
    INDEX idx_ip_address (ip_address),
    INDEX idx_session_id (session_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 2. Failed Login Attempts Table
CREATE TABLE failed_login_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    username_attempted VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    failure_reason ENUM('INVALID_USERNAME', 'INVALID_PASSWORD', 'ACCOUNT_LOCKED', 'SECURITY_VIOLATION'),
    INDEX idx_ip_address (ip_address),
    INDEX idx_username (username_attempted),
    INDEX idx_attempt_time (attempt_time)
);

-- 3. Session Activity Tracking
CREATE TABLE session_activity (
    session_log_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255),
    user_id INT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    pages_visited INT DEFAULT 0,
    actions_performed INT DEFAULT 0,
    session_status ENUM('ACTIVE', 'EXPIRED', 'LOGGED_OUT', 'TERMINATED') DEFAULT 'ACTIVE',
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 4. Data Change History (for critical changes)
CREATE TABLE data_change_history (
    change_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    table_name VARCHAR(64),
    record_id INT,
    operation_type ENUM('INSERT', 'UPDATE', 'DELETE'),
    field_name VARCHAR(64),
    old_value TEXT,
    new_value TEXT,
    change_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_reason VARCHAR(255),
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (change_timestamp),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 5. Security Risk Events
CREATE TABLE security_risk_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    event_type ENUM(
        'MULTIPLE_FAILED_LOGINS', 'UNUSUAL_LOGIN_LOCATION', 'PRIVILEGE_ESCALATION_ATTEMPT',
        'SUSPICIOUS_DATA_ACCESS', 'BULK_DATA_EXPORT', 'UNAUTHORIZED_ACCESS_ATTEMPT',
        'SQL_INJECTION_ATTEMPT', 'XSS_ATTEMPT', 'CSRF_ATTEMPT'
    ),
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    additional_data JSON,
    auto_detected BOOLEAN DEFAULT TRUE,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_severity (severity),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_resolved (resolved),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 6. Admin Actions Log (for privileged operations)
CREATE TABLE admin_actions_log (
    action_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT,
    target_user_id INT NULL,
    action_type ENUM(
        'USER_CREATE', 'USER_UPDATE', 'USER_DELETE', 'USER_ROLE_CHANGE',
        'TEAM_APPROVE', 'TEAM_REJECT', 'LEAGUE_CREATE', 'LEAGUE_UPDATE',
        'SYSTEM_CONFIG', 'DATA_EXPORT', 'BULK_UPDATE', 'FIXTURE_OVERRIDE'
    ),
    action_details TEXT,
    affected_records JSON,
    justification VARCHAR(500),
    approval_required BOOLEAN DEFAULT FALSE,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_user (admin_user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (admin_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
);
