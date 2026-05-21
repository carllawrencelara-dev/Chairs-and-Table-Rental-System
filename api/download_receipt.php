<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/** @var mysqli $conn */

require_once '../config/database.php';
require_once '../includes/functions.php';

$ref = isset($_GET['ref']) ? sanitize($_GET['ref']) : '';

if (empty($ref)) {
    die('Invalid request');
}

$sql = "SELECT * FROM bookings WHERE booking_ref = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $ref);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    die('Booking not found');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?php echo $booking['booking_ref']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .receipt {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1a4a2a;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1a4a2a;
        }
        .ref {
            background: #f0f0f0;
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            margin: 15px 0;
            font-family: monospace;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .total {
            font-weight: bold;
            font-size: 18px;
            color: #1a4a2a;
            border-top: 2px solid #1a4a2a;
            margin-top: 10px;
            padding-top: 10px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-completed { background: #cce5ff; color: #004085; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
        }
        .button-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
            width: 100%;
        }
        .btn-print {
            padding: 10px 25px;
            background: #1a4a2a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-close {
            padding: 10px 25px;
            background: #666;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        @media print {
            body { background: white; padding: 0; }
            .button-container { display: none; }
        }
    </style>
</head>
<body>
<div class="receipt">
    <div class="header">
        <div class="logo">CT RENTAL</div>
        <div>Tables & Chairs Rental</div>
        <div style="font-size: 12px; color: #666;">Sogod, Southern Leyte</div>
    </div>
    
    <div class="ref">Booking Reference: <?php echo $booking['booking_ref']; ?></div>
    
    <div class="row"><strong>Customer:</strong> <span><?php echo htmlspecialchars($booking['customer_name']); ?></span></div>
    <div class="row"><strong>Contact:</strong> <span><?php echo htmlspecialchars($booking['customer_contact']); ?></span></div>
    <div class="row"><strong>Event Date:</strong> <span><?php echo date('F d, Y', strtotime($booking['event_date'])); ?></span></div>
    <div class="row"><strong>Event Time:</strong> <span><?php echo $booking['event_time']; ?></span></div>
    <div class="row"><strong>Venue:</strong> <span><?php echo htmlspecialchars($booking['venue_location']); ?></span></div>
    <?php if ($booking['event_type']): ?>
    <div class="row"><strong>Event Type:</strong> <span><?php echo htmlspecialchars($booking['event_type']); ?></span></div>
    <?php endif; ?>
    
    <div style="margin: 15px 0;">
        <div class="row"><strong>Chairs:</strong> <span><?php echo $booking['chairs']; ?> × ₱30 = ₱<?php echo number_format($booking['chair_cost'], 2); ?></span></div>
        <div class="row"><strong>Tables:</strong> <span><?php echo $booking['tables']; ?> × ₱130 = ₱<?php echo number_format($booking['table_cost'], 2); ?></span></div>
        <?php if ($booking['delivery_type'] === 'delivery'): ?>
        <div class="row"><strong>Delivery Fee:</strong> <span>₱<?php echo number_format($booking['fuel_cost'], 2); ?></span></div>
        <div class="row"><strong>Labor Cost:</strong> <span>₱<?php echo number_format($booking['labor_cost'], 2); ?></span></div>
        <?php endif; ?>
        <div class="row total"><strong>TOTAL:</strong> <span>₱<?php echo number_format($booking['total_amount'], 2); ?></span></div>
    </div>
    
    <div class="row"><strong>Status:</strong> 
        <span class="status status-<?php echo $booking['status']; ?>">
            <?php echo ucfirst($booking['status']); ?>
        </span>
    </div>
    
    <div class="footer">
        <p>Thank you for choosing CT Rental!</p>
        <p>📞 09105898665 | ✉️ CTRental@gmail.com</p>
        <p>This is a computer-generated receipt. No signature required.</p>
    </div>
</div>

<div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px; width: 100%;">
<button onclick="window.print()" style="display: flex; justify-content: center; align-items: center; gap: 8px; padding: 10px 25px; background: #1a4a2a; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; width: 100%;">
    🖨️ Print
</button><button onclick="window.close()" style="padding: 10px 25px; background: #666; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">Close</button>
</div>

</body>
</html>