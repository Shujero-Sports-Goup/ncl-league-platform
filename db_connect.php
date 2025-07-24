<?php
// Database connection credentials
$host     = 'localhost';
$dbname   = 'ncl_league_system';
$username = 'root';
$password = ''; // For XAMPP/MAMP default; adjust if you set a password

// Establish connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check for connection errors
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set proper character encoding (important for names and symbols)
$conn->set_charset("utf8");
?>
