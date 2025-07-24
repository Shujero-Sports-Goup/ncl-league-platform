<?php
// Nukta League Management System - InfinityFree Database Configuration Template
// Copy this and update db_connect.php with your actual InfinityFree database details

// InfinityFree Database Settings (UPDATE THESE WITH YOUR ACTUAL DETAILS)
$host = 'sqlXXX.infinityfree.com'; // Your InfinityFree MySQL server
$username = 'if0_XXXXXXXX'; // Your InfinityFree database username
$password = 'your_password'; // Your InfinityFree database password
$database = 'if0_XXXXXXXX_nukta_league'; // Your InfinityFree database name

// Connection code (same as original)
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Note: InfinityFree uses older MySQL version, so some features may need adjustment
?>
