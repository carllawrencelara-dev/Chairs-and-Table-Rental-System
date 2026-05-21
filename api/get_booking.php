<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';

if (empty($ref)) {
    echo json_encode(['success' => false, 'message' => 'Booking reference is required']);
    exit();
}

// Direct connection with CORRECT database name
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'ct_rental_system';

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit();
}

// SAFE: Using prepared statement to prevent SQL injection
$sql = "SELECT * FROM bookings WHERE booking_ref = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $ref);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'booking' => [
            'booking_ref' => $row['booking_ref'],
            'customer_name' => $row['customer_name'],
            'customer_contact' => $row['customer_contact'],
            'event_date' => $row['event_date'],
            'event_time' => $row['event_time'],
            'venue_location' => $row['venue_location'],
            'event_type' => $row['event_type'] ?? '',
            'chairs' => $row['chairs'],
            'tables' => $row['tables'],
            'delivery_type' => $row['delivery_type'],
            'distance_km' => $row['distance_km'],
            'chair_cost' => $row['chair_cost'],
            'table_cost' => $row['table_cost'],
            'labor_cost' => $row['labor_cost'],
            'fuel_cost' => $row['fuel_cost'],
            'total_amount' => $row['total_amount'],
            'status' => $row['status']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No booking found with reference: ' . htmlspecialchars($ref)]);
}

mysqli_close($conn);
?>