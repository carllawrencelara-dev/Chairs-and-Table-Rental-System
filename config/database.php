<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ct_rental_system');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'CT Rental');
define('SITE_PHONE', '09105898665');
define('SITE_EMAIL', 'CTRental@gmail.com');
define('TOTAL_CHAIRS', 1500);
define('TOTAL_TABLES', 500);
define('CHAIR_PRICE', 30);
define('TABLE_PRICE', 130);
define('LABOR_RATE', 50);
define('FUEL_RATE', 50);
?>