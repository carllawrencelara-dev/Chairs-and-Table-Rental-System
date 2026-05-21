<?php
header('Content-Type: application/json');

// Direct connection to avoid config issues
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'ct_rental_system';

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit();
}

$date = isset($_GET['date']) ? $_GET['date'] : '';

if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit();
}

$sql = "SELECT booking_ref, customer_name, chairs, tables, status FROM bookings WHERE event_date = ? AND status != 'cancelled' ORDER BY event_time ASC";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "s", $date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$bookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
}

echo json_encode(['success' => true, 'bookings' => $bookings]);

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>