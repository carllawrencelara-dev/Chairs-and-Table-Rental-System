<?php
require_once __DIR__ . '/../config/database.php';

// Sanitize input
function sanitize($input) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

// Generate booking reference
function generateBookingRef() {
    return 'CTR-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Get availability for a specific date
function getAvailability($date) {
    global $conn;
    
    $sql = "SELECT SUM(chairs) as booked_chairs, SUM(tables) as booked_tables 
        FROM bookings 
        WHERE event_date = ? AND status IN ('pending', 'accepted')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $bookedChairs = $row['booked_chairs'] ?? 0;
    $bookedTables = $row['booked_tables'] ?? 0;
    
    return [
        'available_chairs' => TOTAL_CHAIRS - $bookedChairs,
        'available_tables' => TOTAL_TABLES - $bookedTables,
        'booked_chairs' => $bookedChairs,
        'booked_tables' => $bookedTables
    ];
}

// Calculate costs
function calculateCosts($chairs, $tables, $distance, $deliveryType) {
    $chairCost = $chairs * CHAIR_PRICE;
    $tableCost = $tables * TABLE_PRICE;
    $totalItems = $chairs + $tables;
    
    if ($deliveryType === 'delivery') {
        $laborCost = ceil($totalItems / 100) * LABOR_RATE;
        $fuelCost = $distance * FUEL_RATE;
    } else {
        $laborCost = 0;
        $fuelCost = 0;
    }
    
    $total = $chairCost + $tableCost + $laborCost + $fuelCost;
    
    return [
        'chair_cost' => (float)$chairCost,
        'table_cost' => (float)$tableCost,
        'labor_cost' => (float)$laborCost,
        'fuel_cost' => (float)$fuelCost,
        'total' => (float)$total
    ];
}

// Get dashboard statistics
function getDashboardStats() {
    global $conn;
    
    $stats = [];
    
    // Total bookings
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings");
    $totalRow = mysqli_fetch_assoc($result);
    $stats['total_bookings'] = $totalRow['total'];
    
    // Pending
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
    $stats['pending'] = mysqli_fetch_assoc($result)['count'];
    
    // Accepted
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE status = 'accepted'");
    $stats['accepted'] = mysqli_fetch_assoc($result)['count'];
    
    // Completed
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE status = 'completed'");
    $stats['completed'] = mysqli_fetch_assoc($result)['count'];
    
    // Total revenue
    $result = mysqli_query($conn, "SELECT SUM(total_amount) as revenue FROM bookings WHERE status IN ('accepted', 'completed')");
    $stats['total_revenue'] = mysqli_fetch_assoc($result)['revenue'] ?? 0;
    
    // Used inventory
$result = mysqli_query($conn, "SELECT SUM(chairs) as used_chairs, SUM(tables) as used_tables FROM bookings WHERE status IN ('pending', 'accepted')");    $row = mysqli_fetch_assoc($result);
    $stats['used_chairs'] = $row['used_chairs'] ?? 0;
    $stats['used_tables'] = $row['used_tables'] ?? 0;
    $stats['available_chairs'] = TOTAL_CHAIRS - $stats['used_chairs'];
    $stats['available_tables'] = TOTAL_TABLES - $stats['used_tables'];
    
    return $stats;
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Get status badge class
function getStatusBadge($status) {
    $badges = [
        'pending' => 'status-pending',
        'accepted' => 'status-accepted',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled'
    ];
    return $badges[$status] ?? 'status-pending';
}

// Get unread count
function getUnreadCount() {
    global $conn;
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending' AND is_read = 0");
    return mysqli_fetch_assoc($result)['count'];
}

// Mark as read
function markAsRead($id) {
    global $conn;
    $sql = "UPDATE bookings SET is_read = 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    return mysqli_stmt_execute($stmt);
}

// Get monthly revenue data
function getMonthlyRevenue() {
    global $conn;
    $sql = "SELECT DATE_FORMAT(event_date, '%Y-%m') as month, SUM(total_amount) as revenue 
            FROM bookings 
            WHERE status IN ('accepted', 'completed') 
            GROUP BY DATE_FORMAT(event_date, '%Y-%m') 
            ORDER BY month DESC LIMIT 6";
    $result = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return array_reverse($data);
}

// Get weekly revenue data
function getWeeklyRevenue() {
    global $conn;
    $revenue = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = date('D', strtotime($date));
        $sql = "SELECT SUM(total_amount) as revenue FROM bookings WHERE event_date = ? AND status IN ('accepted', 'completed')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $revenue[] = [
            'day' => $dayName,
            'revenue' => $row['revenue'] ?? 0
        ];
    }
    return $revenue;
}
?>