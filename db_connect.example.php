<?php
// Database configuration template
// Copy this file to db_connect.php and update with your actual database credentials

$servername = "localhost";
$username = "your_username";      // Replace with your database username
$password = "your_password";      // Replace with your database password
$dbname = "your_database_name";   // Replace with your database name

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4 for proper emoji and international character support
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Optional: Set timezone
date_default_timezone_set('Africa/Nairobi');
?>
