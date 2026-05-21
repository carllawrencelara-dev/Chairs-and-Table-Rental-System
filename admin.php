<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get database connection first
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Make sure $conn is available
global $conn;

$action = $_GET['action'] ?? 'login';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Login
    if (isset($_POST['login'])) {
        $email = sanitize($_POST['email']);
        $password = sanitize($_POST['password']);
        if (adminLogin($email, $password)) {
            header('Location: admin.php?action=dashboard');
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    }
    
    // Logout
    elseif (isset($_POST['logout'])) {
        adminLogout();
        header('Location: admin.php');
        exit();
    }
    
    // Signup
    elseif (isset($_POST['signup'])) {
        $result = createAdmin(
            sanitize($_POST['fullname']),
            sanitize($_POST['email']),
            sanitize($_POST['password']),
            sanitize($_POST['security_code'])
        );
        $signupMessage = $result['message'];
        $signupType = $result['success'] ? 'success' : 'danger';
    }
    
    // Forgot Password
    elseif (isset($_POST['forgot_password'])) {
        $email = sanitize($_POST['email']);
        $token = generateResetToken($email);
        if ($token) {
            $resetMessage = 'Password reset link has been sent to your email.';
            $resetType = 'success';
        } else {
            $resetMessage = 'Email address not found';
            $resetType = 'danger';
        }
    }
    
    // Reset Password
    elseif (isset($_POST['reset_password'])) {
        $token = sanitize($_POST['token']);
        $newPassword = sanitize($_POST['new_password']);
        $confirmPassword = sanitize($_POST['confirm_password']);
        
        if ($newPassword !== $confirmPassword) {
            $resetError = 'Passwords do not match';
        } elseif (strlen($newPassword) < 6) {
            $resetError = 'Password must be at least 6 characters';
        } else {
            if (resetPassword($token, $newPassword)) {
                $resetSuccess = 'Password reset successfully. Please login.';
            } else {
                $resetError = 'Invalid or expired reset token';
            }
        }
    }
    
    // Change Password
    elseif (isset($_POST['change_password']) && isAdminLoggedIn()) {
        $current = sanitize($_POST['current_password']);
        $new = sanitize($_POST['new_password']);
        $confirm = sanitize($_POST['confirm_password']);
        
        if ($new !== $confirm) {
            $passMessage = 'New passwords do not match';
            $passType = 'danger';
        } elseif (strlen($new) < 6) {
            $passMessage = 'Password must be at least 6 characters';
            $passType = 'danger';
        } else {
            if (changePassword($_SESSION['admin_id'], $current, $new)) {
                $passMessage = 'Password changed successfully';
                $passType = 'success';
            } else {
                $passMessage = 'Current password is incorrect';
                $passType = 'danger';
            }
        }
    }
    
    // Update Booking Status
   // Update Booking Status
elseif (isset($_POST['update_status']) && isAdminLoggedIn()) {
    $id = (int)$_POST['booking_id'];
    $newStatus = sanitize($_POST['status']);
    
    // Get current status and booking details
    $sql = "SELECT status, chairs, tables FROM bookings WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    $oldStatus = $booking['status'];
    $chairs = $booking['chairs'];
    $tables = $booking['tables'];
    
    // Update the status
    $sql = "UPDATE bookings SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $newStatus, $id);
    mysqli_stmt_execute($stmt);
    
    header('Location: admin.php?action=' . $action);
    exit();
}
    
    // Delete Booking
    elseif (isset($_POST['delete_booking']) && isAdminLoggedIn()) {
        $id = (int)$_POST['booking_id'];
        
        $sql = "DELETE FROM bookings WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        
        header('Location: admin.php?action=' . $action);
        exit();
    }
    
    // Create Booking
    elseif (isset($_POST['create_booking']) && isAdminLoggedIn()) {
        $ref = generateBookingRef();
        $name = sanitize($_POST['name']);
        $contact = sanitize($_POST['contact']);
        $date = sanitize($_POST['date']);
        $time = sanitize($_POST['time']);
        $location = sanitize($_POST['location']);
        $eventType = !empty($_POST['event_type']) ? sanitize($_POST['event_type']) : null;
        $chairs = (int)$_POST['chairs'];
        $tables = (int)$_POST['tables'];
        $deliveryType = sanitize($_POST['delivery_type']);
        $distance = (float)$_POST['distance'];
        $status = sanitize($_POST['status']);
        
        $costs = calculateCosts($chairs, $tables, $distance, $deliveryType);
        
        $sql = "INSERT INTO bookings (booking_ref, customer_name, customer_contact, event_date, event_time, venue_location, event_type, chairs, tables, delivery_type, distance_km, chair_cost, table_cost, labor_cost, fuel_cost, total_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssiisddddds", 
            $ref, $name, $contact, $date, $time, $location, $eventType,
            $chairs, $tables, $deliveryType, $distance,
            $costs['chair_cost'], $costs['table_cost'], $costs['labor_cost'], $costs['fuel_cost'], $costs['total'], $status);
        mysqli_stmt_execute($stmt);
        
        header('Location: admin.php?action=all');
        exit();
    }
    
    // Edit Booking
    elseif (isset($_POST['edit_booking']) && isAdminLoggedIn()) {
        $id = (int)$_POST['booking_id'];
        $name = sanitize($_POST['name']);
        $contact = sanitize($_POST['contact']);
        $date = sanitize($_POST['date']);
        $time = sanitize($_POST['time']);
        $location = sanitize($_POST['location']);
        $eventType = !empty($_POST['event_type']) ? sanitize($_POST['event_type']) : null;
        $chairs = (int)$_POST['chairs'];
        $tables = (int)$_POST['tables'];
        $deliveryType = sanitize($_POST['delivery_type']);
        $distance = (float)$_POST['distance'];
        $status = sanitize($_POST['status']);
        
        $costs = calculateCosts($chairs, $tables, $distance, $deliveryType);
        
        $sql = "UPDATE bookings SET customer_name=?, customer_contact=?, event_date=?, event_time=?, venue_location=?, event_type=?, chairs=?, tables=?, delivery_type=?, distance_km=?, chair_cost=?, table_cost=?, labor_cost=?, fuel_cost=?, total_amount=?, status=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssiisdddddsi", 
            $name, $contact, $date, $time, $location, $eventType,
            $chairs, $tables, $deliveryType, $distance,
            $costs['chair_cost'], $costs['table_cost'], $costs['labor_cost'], $costs['fuel_cost'], $costs['total'], $status, $id);
        mysqli_stmt_execute($stmt);
        
        header('Location: admin.php?action=' . $action);
        exit();
    }
    
    // Mark Notification as Read
    elseif (isset($_POST['mark_read']) && isAdminLoggedIn()) {
        $id = (int)$_POST['booking_id'];
        markAsRead($id);
        header('Location: admin.php?action=notifications');
        exit();
    }
}

// Get data for logged in admin
if (isAdminLoggedIn()) {
    $stats = getDashboardStats();
    $unreadCount = getUnreadCount();
    $monthlyRevenue = getMonthlyRevenue();
    $weeklyRevenue = getWeeklyRevenue();
    
    // Get bookings based on status
    if (in_array($action, ['pending', 'accepted', 'completed', 'cancelled'])) {
        $statusFilter = $action;
        $sql = "SELECT * FROM bookings WHERE status = ? ORDER BY created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $statusFilter);
        mysqli_stmt_execute($stmt);
        $bookings = mysqli_stmt_get_result($stmt);
    } elseif ($action === 'all') {
        $sql = "SELECT * FROM bookings ORDER BY created_at DESC";
        $bookings = mysqli_query($conn, $sql);
    } elseif ($action === 'notifications') {
        $sql = "SELECT * FROM bookings WHERE status = 'pending' AND is_read = 0 ORDER BY created_at DESC";
        $notifications = mysqli_query($conn, $sql);
    }
    
    // Get single booking for edit
    if ($action === 'edit' && isset($_GET['id'])) {
        $editId = (int)$_GET['id'];
        $sql = "SELECT * FROM bookings WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $editBooking = mysqli_stmt_get_result($stmt)->fetch_assoc();
    }
    
    // Get calendar data
    $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
    $currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
    $firstDayOfMonth = date('N', strtotime("$currentYear-$currentMonth-01"));
    
    $calendarData = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = sprintf("%d-%02d-%02d", $currentYear, $currentMonth, $d);
        $calendarData[$date] = getAvailability($date);
    }
}

$includeCharts = true;
?>
<?php include 'includes/header.php'; ?>

<style>
/* Additional Admin Styles */
.admin-wrapper { display: flex; min-height: 100vh; }
.admin-sidebar { width: 280px; background: var(--dark-card); border-right: 1px solid var(--border); position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
.notifications-table { width: 100%; border-collapse: collapse; }
.notifications-table th, .notifications-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
.notifications-table th:nth-child(1) { width: 12%; }
.notifications-table th:nth-child(2) { width: 18%; }
.notifications-table th:nth-child(3) { width: 10%; }
.notifications-table th:nth-child(4) { width: 10%; }
.notifications-table th:nth-child(5) { width: 12%; }
.notifications-table th:nth-child(6) { width: 38%; }
.action-btns { display: flex; gap: 8px; flex-wrap: wrap; }
.action-btn { padding: 5px 12px; border-radius: 5px; font-size: 12px; cursor: pointer; background: transparent; border: 1px solid var(--border); color: var(--text-secondary); }
.action-btn.accept:hover { border-color: var(--success); color: var(--success); }
.action-btn.danger:hover { border-color: var(--danger); color: var(--danger); }
.admin-sidebar-logo { padding: 1.5rem; border-bottom: 1px solid var(--border); text-align: center; }
.admin-sidebar-logo .logo { justify-content: center; }
.admin-nav { padding: 1rem 0; }
.nav-section { padding: 0.75rem 1.5rem; font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1.5px; }
.nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 1.5rem; color: var(--text-secondary); text-decoration: none; transition: all 0.3s ease; cursor: pointer; font-size: 0.9rem; }
.nav-item:hover, .nav-item.active { background: rgba(74, 222, 128, 0.1); color: var(--accent); border-left: 3px solid var(--accent); }
.nav-item i { width: 20px; font-size: 1rem; }
.nav-group-header { display: flex; justify-content: space-between; align-items: center; padding: 0.7rem 1.5rem; color: var(--text-secondary); cursor: pointer; font-size: 0.9rem; }
.nav-group-header:hover { color: var(--accent); }
.nav-sub { display: none; padding-left: 2rem; }
.nav-sub.open { display: block; }
.nav-sub .nav-item { padding: 0.5rem 1rem; font-size: 0.85rem; }
.admin-main-content { margin-left: 280px; flex: 1; padding: 2rem; }
.admin-header { background: var(--dark-card); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; margin-bottom: 2rem; }
.admin-title { font-size: 1.3rem; font-weight: 600; }
.admin-user { display: flex; align-items: center; gap: 1rem; }
.notif-bell { position: relative; cursor: pointer; }
.notif-bell .badge { position: absolute; top: -8px; right: -8px; background: var(--danger); color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 50%; }
.admin-avatar { width: 40px; height: 40px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { background: var(--dark-surface); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; transition: all 0.3s ease; }
.stat-card:hover { transform: translateY(-3px); border-color: var(--accent); }
.stat-card .stat-icon { width: 50px; height: 50px; background: rgba(74, 222, 128, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
.stat-card .stat-icon i { font-size: 1.5rem; color: var(--accent); }
.stat-card .stat-label { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; }
.stat-card .stat-value { font-size: 2rem; font-weight: 700; color: var(--accent); }
.data-card { background: var(--dark-surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; margin-bottom: 2rem; }
.data-card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.data-card-header h3 { font-size: 1.1rem; }
.search-box { display: flex; align-items: center; gap: 0.5rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; }
.search-box i { color: var(--text-muted); }
.search-box input { background: none; border: none; color: var(--text-primary); outline: none; }
.data-table { overflow-x: auto; }
.data-table table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
.data-table th { color: var(--text-muted); font-weight: 500; font-size: 0.8rem; text-transform: uppercase; background: rgba(0, 0, 0, 0.2); }
.status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.7rem; font-weight: 600; }
.status-pending { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
.status-accepted { background: rgba(74, 222, 128, 0.2); color: var(--success); }
.status-completed { background: rgba(59, 130, 246, 0.2); color: var(--info); }
.status-cancelled { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
.calendar-wrapper { background: var(--dark-surface); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; }
.calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.calendar-nav { display: flex; gap: 0.5rem; }
.calendar-nav button, .calendar-nav a { background: var(--dark-card); border: 1px solid var(--border); padding: 0.5rem 1rem; border-radius: 8px; color: var(--text-secondary); cursor: pointer; text-decoration: none; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; }
.calendar-weekday { text-align: center; padding: 0.75rem; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }
.calendar-day { background: var(--dark-card); border: 1px solid var(--border); border-radius: 12px; padding: 0.75rem; min-height: 100px; transition: all 0.3s ease; }
.calendar-day:hover { border-color: var(--accent); }
.calendar-day-number { font-weight: 600; margin-bottom: 0.5rem; }
.calendar-day-stock { font-size: 0.7rem; color: var(--text-muted); }
.calendar-day.fully-booked { background: rgba(239, 68, 68, 0.1); border-color: var(--danger); }
.calendar-day.partial { background: rgba(245, 158, 11, 0.1); border-color: var(--warning); }
.calendar-day.available { background: rgba(74, 222, 128, 0.05); }
.legend { display: flex; gap: 1.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border); }
.legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--text-muted); }
.legend-dot { width: 12px; height: 12px; border-radius: 3px; }
.legend-dot.red { background: rgba(239, 68, 68, 0.3); border: 1px solid var(--danger); }
.legend-dot.yellow { background: rgba(245, 158, 11, 0.3); border: 1px solid var(--warning); }
.legend-dot.green { background: rgba(74, 222, 128, 0.3); border: 1px solid var(--success); }
.charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
.chart-card { background: var(--dark-surface); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; }
.chart-card h3 { margin-bottom: 1rem; font-size: 1rem; }
.chart-card canvas { max-height: 300px; }
.form-card { background: var(--dark-surface); border: 1px solid var(--border); border-radius: 16px; padding: 2rem; max-width: 800px; margin: 0 auto; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 500; color: var(--text-secondary); }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 0.9rem; }
.auth-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--dark) 0%, var(--primary-dark) 100%); padding: 2rem; }
.auth-card { background: var(--dark-surface); border: 1px solid var(--border); border-radius: 24px; padding: 2.5rem; max-width: 450px; width: 100%; position: relative; }
.auth-logo { text-align: center; margin-bottom: 2rem; }
.auth-title { text-align: center; font-size: 1.5rem; margin-bottom: 0.5rem; }
.auth-subtitle { text-align: center; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 2rem; }
.auth-link { color: var(--accent); text-decoration: none; }
.close-btn { position: absolute; top: 1rem; right: 1.5rem; background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); transition: all 0.3s ease; line-height: 1; }
.close-btn:hover { color: var(--danger); transform: scale(1.1); }
@media (max-width: 768px) { .admin-sidebar { display: none; } .admin-main-content { margin-left: 0; } .form-row { grid-template-columns: 1fr; } .charts-grid { grid-template-columns: 1fr; } }
</style>

<?php if ($action === 'login' && !isAdminLoggedIn()): ?>
<div class="auth-container">
    <div class="auth-card">
        <button class="close-btn" onclick="window.location.href='index.php'">&times;</button>
        <div class="auth-logo">
            <div class="logo" style="justify-content: center;">
                <div class="logo-icon"><i class="fas fa-chair"></i></div>
                <div class="logo-text"><h1 style="font-size: 1.3rem;">CT RENTAL</h1><p>Admin Portal</p></div>
            </div>
        </div>
        <div class="auth-title">Welcome Back</div>
        <div class="auth-subtitle">Sign in to manage your bookings</div>
        <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="admin@ctrental.com" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="••••••••" required></div>
            <button type="submit" name="login" class="btn-primary" style="width: 100%; justify-content: center;"><i class="fas fa-sign-in-alt"></i> Sign In</button>
        </form>
        <div style="text-align: center; margin-top: 1.5rem;"><a href="?action=signup" class="auth-link">Create Account</a> | <a href="?action=forgot" class="auth-link">Forgot Password?</a></div>
    </div>
</div>

<?php elseif ($action === 'signup'): ?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="logo" style="justify-content: center;">
                <div class="logo-icon"><i class="fas fa-chair"></i></div>
                <div class="logo-text"><h1 style="font-size: 1.3rem;">CT RENTAL</h1><p>Create Admin Account</p></div>
            </div>
        </div>
        <div class="auth-title">Get Started</div>
        <div class="auth-subtitle">Create your administrator account</div>
        <?php if (isset($signupMessage)): ?><div class="alert alert-<?php echo $signupType; ?>"><?php echo $signupMessage; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Full Name</label><input type="text" name="fullname" required></div>
            <div class="form-group"><label>Email Address</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <div class="form-group"><label>Security Code</label><input type="text" name="security_code" placeholder="CT2025ADMIN" required></div>
            <button type="submit" name="signup" class="btn-primary" style="width: 100%;"><i class="fas fa-user-plus"></i> Create Account</button>
        </form>
        <div style="text-align: center; margin-top: 1.5rem;"><a href="?action=login" class="auth-link">Back to Login</a></div>
    </div>
</div>

<?php elseif ($action === 'forgot'): ?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="logo" style="justify-content: center;">
                <div class="logo-icon"><i class="fas fa-lock"></i></div>
                <div class="logo-text"><h1 style="font-size: 1.3rem;">CT RENTAL</h1><p>Reset Password</p></div>
            </div>
        </div>
        <div class="auth-title">Forgot Password?</div>
        <div class="auth-subtitle">Enter your email to receive reset instructions</div>
        <?php if (isset($resetMessage)): ?><div class="alert alert-<?php echo $resetType; ?>"><?php echo $resetMessage; ?></div><?php endif; ?>
        <?php if (!isset($_GET['token'])): ?>
        <form method="POST">
            <div class="form-group"><label>Email Address</label><input type="email" name="email" required></div>
            <button type="submit" name="forgot_password" class="btn-primary" style="width: 100%;"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
        </form>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
            <?php if (isset($resetError)): ?><div class="alert alert-danger"><?php echo $resetError; ?></div><?php endif; ?>
            <?php if (isset($resetSuccess)): ?><div class="alert alert-success"><?php echo $resetSuccess; ?></div><?php endif; ?>
            <button type="submit" name="reset_password" class="btn-primary" style="width: 100%;"><i class="fas fa-key"></i> Reset Password</button>
        </form>
        <?php endif; ?>
        <div style="text-align: center; margin-top: 1.5rem;"><a href="?action=login" class="auth-link">Back to Login</a></div>
    </div>
</div>

<?php elseif (isAdminLoggedIn()): ?>

<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="admin-sidebar-logo">
            <div class="logo"><div class="logo-icon"><i class="fas fa-chair"></i></div><div class="logo-text"><h1 style="font-size: 1.1rem;">CT RENTAL</h1><p>Admin Panel</p></div></div>
        </div>
        <nav class="admin-nav">
            <div class="nav-section">Main</div>
            <a href="?action=dashboard" class="nav-item <?php echo $action === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="?action=calendar" class="nav-item <?php echo $action === 'calendar' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Inventory Calendar</a>
            <a href="?action=notifications" class="nav-item <?php echo $action === 'notifications' ? 'active' : ''; ?>"><i class="fas fa-bell"></i> Notifications<?php if ($unreadCount > 0): ?><span style="background: var(--danger); color: white; padding: 2px 6px; border-radius: 50%; font-size: 0.7rem; margin-left: auto;"><?php echo $unreadCount; ?></span><?php endif; ?></a>
            
            <div class="nav-section">Bookings</div>
            <div class="nav-group">
                <div class="nav-group-header" onclick="toggleSubMenu(this)"><span><i class="fas fa-folder"></i> Status Management</span><i class="fas fa-chevron-down"></i></div>
                <div class="nav-sub"><a href="?action=pending" class="nav-item">Pending</a><a href="?action=accepted" class="nav-item">Accepted</a><a href="?action=completed" class="nav-item">Completed</a><a href="?action=cancelled" class="nav-item">Cancelled</a></div>
            </div>
            <a href="?action=all" class="nav-item <?php echo $action === 'all' ? 'active' : ''; ?>"><i class="fas fa-list"></i> All Bookings</a>
            <a href="?action=create" class="nav-item <?php echo $action === 'create' ? 'active' : ''; ?>"><i class="fas fa-plus-circle"></i> Create Booking</a>
            
            <div class="nav-section">Financials</div>
            <a href="?action=revenue" class="nav-item <?php echo $action === 'revenue' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> Revenue & Analytics</a>
            
            <div class="nav-section">Account</div>
            <a href="?action=change-password" class="nav-item <?php echo $action === 'change-password' ? 'active' : ''; ?>"><i class="fas fa-key"></i> Change Password</a>
            
            <form method="POST" style="margin-top: 2rem; padding: 1rem;"><button type="submit" name="logout" class="btn-outline" style="width: 100%;"><i class="fas fa-sign-out-alt"></i> Logout</button></form>
        </nav>
    </aside>
    
    <div class="admin-main-content">
        <div class="admin-header">
            <div class="admin-title"><?php 
                $titles = ['dashboard' => 'Dashboard', 'calendar' => 'Inventory Calendar', 'notifications' => 'Notifications', 'pending' => 'Pending Bookings', 'accepted' => 'Accepted Bookings', 'completed' => 'Completed Bookings', 'cancelled' => 'Cancelled Bookings', 'all' => 'All Bookings', 'create' => 'Create Booking', 'revenue' => 'Revenue & Analytics', 'change-password' => 'Change Password', 'edit' => 'Edit Booking'];
                echo $titles[$action] ?? ucfirst(str_replace('-', ' ', $action));
            ?></div>
            <div class="admin-user"><div class="notif-bell" onclick="window.location.href='?action=notifications'"><i class="fas fa-bell"></i><?php if ($unreadCount > 0): ?><span class="badge"><?php echo $unreadCount; ?></span><?php endif; ?></div><div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?></div><span><?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span></div>
        </div>
        
        <!-- DASHBOARD -->
        <?php if ($action === 'dashboard'): ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div class="stat-label">Total Bookings</div><div class="stat-value"><?php echo $stats['total_bookings']; ?></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-spinner"></i></div><div class="stat-label">Pending</div><div class="stat-value"><?php echo $stats['pending']; ?></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-label">Accepted</div><div class="stat-value"><?php echo $stats['accepted']; ?></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-flag-checkered"></i></div><div class="stat-label">Completed</div><div class="stat-value"><?php echo $stats['completed']; ?></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-line"></i></div><div class="stat-label">Total Revenue</div><div class="stat-value"><?php echo formatCurrency($stats['total_revenue']); ?></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chair"></i></div><div class="stat-label">Available Chairs</div><div class="stat-value"><?php echo number_format($stats['available_chairs']); ?></div></div>
        </div>
        <div class="data-card"><div class="data-card-header"><h3>Recent Bookings</h3><a href="?action=all" style="color: var(--accent);">View All</a></div>
        <div class="data-table"><table><thead><tr><th>Reference</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
        <tbody><?php $recentSql = "SELECT * FROM bookings ORDER BY created_at DESC LIMIT 5"; $recentResult = mysqli_query($conn, $recentSql); while ($row = mysqli_fetch_assoc($recentResult)): ?>
        <tr><td><?php echo $row['booking_ref']; ?></td><td><?php echo $row['customer_name']; ?></td><td><?php echo formatDate($row['event_date']); ?></td><td>🪑<?php echo $row['chairs']; ?> 🪵<?php echo $row['tables']; ?></td><td><?php echo formatCurrency($row['total_amount']); ?></td><td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td></tr>
        <?php endwhile; ?></tbody></table></div></div>
        
        <!-- CALENDAR -->
<?php elseif ($action === 'calendar'): ?>
<div class="calendar-wrapper">
    <div class="calendar-header">
        <h3><i class="fas fa-calendar-alt"></i> <?php echo date('F Y', strtotime("$currentYear-$currentMonth-01")); ?></h3>
        <div class="calendar-nav">
            <a href="?action=calendar&month=<?php echo $currentMonth - 1; ?>&year=<?php echo $currentYear; ?>" class="btn-outline">&lt; Prev</a>
            <a href="?action=calendar" class="btn-outline">Today</a>
            <a href="?action=calendar&month=<?php echo $currentMonth + 1; ?>&year=<?php echo $currentYear; ?>" class="btn-outline">Next &gt;</a>
        </div>
    </div>
    
    <div class="calendar-grid">
        <?php $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']; 
        foreach ($weekdays as $day): ?>
            <div class="calendar-weekday"><?php echo $day; ?></div>
        <?php endforeach; ?>
        
        <?php for ($i = 1; $i < $firstDayOfMonth; $i++): ?>
            <div class="calendar-day empty"></div>
        <?php endfor; ?>
        
        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
            $date = sprintf("%d-%02d-%02d", $currentYear, $currentMonth, $d);
            
            // Get booked chairs and tables for this date
            $bookedSql = "SELECT SUM(chairs) as booked_chairs, SUM(tables) as booked_tables FROM bookings WHERE event_date = ? AND status != 'cancelled'";
            $bookedStmt = mysqli_prepare($conn, $bookedSql);
            mysqli_stmt_bind_param($bookedStmt, "s", $date);
            mysqli_stmt_execute($bookedStmt);
            $bookedResult = mysqli_stmt_get_result($bookedStmt);
            $bookedRow = mysqli_fetch_assoc($bookedResult);
            $bookedChairs = $bookedRow['booked_chairs'] ?? 0;
            $bookedTables = $bookedRow['booked_tables'] ?? 0;
            
            $hasBookings = ($bookedChairs > 0 || $bookedTables > 0);
            $statusClass = $hasBookings ? 'has-bookings' : '';
        ?>
            <div class="calendar-day <?php echo $statusClass; ?>" onclick="showDateBookings('<?php echo $date; ?>')" style="cursor: pointer;">
                <div class="calendar-day-number"><?php echo $d; ?></div>
                <?php if ($hasBookings): ?>
                    <div class="calendar-day-stock">
                        🪑 BOOKED: <?php echo $bookedChairs; ?><br>
                        🪵 BOOKED: <?php echo $bookedTables; ?>
                    </div>
                    <div class="calendar-day-bookings">
                        📅 VIEW DETAILS
                    </div>
                <?php else: ?>
                    <div class="calendar-day-stock">
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
    
    <div class="legend">
        <div class="legend-item"><div class="legend-dot green"></div> Has Bookings</div>
        <div class="legend-item"><div class="legend-dot gray"></div> No Bookings</div>
    </div>
</div>

<!-- Bookings Modal for Date -->
<div id="dateBookingsModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 600px; background: var(--dark-surface); border-radius: 16px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: var(--accent);"><i class="fas fa-calendar-day"></i> Bookings for <span id="modalDate"></span></h3>
            <button onclick="closeDateBookingsModal()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div id="dateBookingsList" style="max-height: 400px; overflow-y: auto;">Loading...</div>
    </div>
</div>

<style>
.calendar-day.has-bookings {
    background: rgba(74, 222, 128, 0.15);
    border-color: var(--accent);
    cursor: pointer;
}
.calendar-day.has-bookings:hover {
    background: rgba(74, 222, 128, 0.25);
}
.calendar-day-bookings {
    font-size: 0.7rem;
    margin-top: 5px;
    color: var(--accent);
    font-weight: 500;
}
.calendar-day-stock {
    font-size: 0.7rem;
    margin-top: 5px;
}
.legend-dot.green {
    background: rgba(74, 222, 128, 0.3);
    border: 1px solid var(--accent);
    width: 12px;
    height: 12px;
    border-radius: 3px;
    display: inline-block;
}
.legend-dot.gray {
    background: #333;
    border: 1px solid #555;
    width: 12px;
    height: 12px;
    border-radius: 3px;
    display: inline-block;
}
</style>

<script>
function showDateBookings(date) {
    fetch('api/get_date_bookings.php?date=' + date)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalDate').innerHTML = date;
            if (data.success && data.bookings.length > 0) {
                let html = '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr><th>Reference</th><th>Customer</th><th>Chairs</th><th>Tables</th><th>Status</th></tr></thead><tbody>';
                data.bookings.forEach(booking => {
                    html += '<tr>';
                    html += '<td>' + booking.booking_ref + '</td>';
                    html += '<td>' + booking.customer_name + '</td>';
                    html += '<td>' + booking.chairs + '</td>';
                    html += '<td>' + booking.tables + '</td>';
                    html += '<td><span class="status-badge status-' + booking.status + '">' + booking.status.charAt(0).toUpperCase() + booking.status.slice(1) + '</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                document.getElementById('dateBookingsList').innerHTML = html;
            } else {
                document.getElementById('dateBookingsList').innerHTML = '<p style="text-align: center; color: var(--text-muted);">No bookings for this date</p>';
            }
            document.getElementById('dateBookingsModal').style.display = 'flex';
        })
        .catch(error => {
            document.getElementById('dateBookingsList').innerHTML = '<p style="text-align: center; color: var(--danger);">Error loading bookings</p>';
            document.getElementById('dateBookingsModal').style.display = 'flex';
        });
}

function closeDateBookingsModal() {
    document.getElementById('dateBookingsModal').style.display = 'none';
}
</script>

<!-- Bookings Modal for Date -->
<div id="dateBookingsModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 600px; background: var(--dark-surface); border-radius: 16px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: var(--accent);"><i class="fas fa-calendar-day"></i> Bookings for <span id="modalDate"></span></h3>
            <button onclick="closeDateBookingsModal()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div id="dateBookingsList" style="max-height: 400px; overflow-y: auto;">Loading...</div>
    </div>
</div>

<style>
.calendar-day.has-bookings {
    background: rgba(74, 222, 128, 0.15);
    border-color: var(--accent);
}
.calendar-day.has-bookings:hover {
    background: rgba(74, 222, 128, 0.25);
}
.calendar-day-bookings {
    font-size: 0.7rem;
    margin-top: 5px;
    color: var(--accent);
    font-weight: 500;
}
.legend-dot.orange {
    background: rgba(74, 222, 128, 0.3);
    border: 1px solid var(--accent);
}
</style>

<script>
function showDateBookings(date) {
    fetch('api/get_date_bookings.php?date=' + date)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalDate').innerHTML = date;
            if (data.success && data.bookings.length > 0) {
                let html = '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr><th>Reference</th><th>Customer</th><th>Chairs</th><th>Tables</th><th>Status</th></tr></thead><tbody>';
                data.bookings.forEach(booking => {
                    html += '<tr>';
                    html += '<td>' + booking.booking_ref + '</tr>';
                    html += '<td>' + booking.customer_name + '</td>';
                    html += '<td>' + booking.chairs + '</td>';
                    html += '<td>' + booking.tables + '</td>';
                    html += '<td><span class="status-badge status-' + booking.status + '">' + booking.status.charAt(0).toUpperCase() + booking.status.slice(1) + '</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></tr>';
                document.getElementById('dateBookingsList').innerHTML = html;
            } else {
                document.getElementById('dateBookingsList').innerHTML = '<p style="text-align: center; color: var(--text-muted);">No bookings for this date</p>';
            }
            document.getElementById('dateBookingsModal').style.display = 'flex';
        })
        .catch(error => {
            document.getElementById('dateBookingsList').innerHTML = '<p style="text-align: center; color: var(--danger);">Error loading bookings</p>';
            document.getElementById('dateBookingsModal').style.display = 'flex';
        });
}

function closeDateBookingsModal() {
    document.getElementById('dateBookingsModal').style.display = 'none';
}
</script>

<!-- Bookings Modal for Date -->
<div id="dateBookingsModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 600px; background: var(--dark-surface); border-radius: 16px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: var(--accent);"><i class="fas fa-calendar-day"></i> Bookings for <span id="modalDate"></span></h3>
            <button onclick="closeDateBookingsModal()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div id="dateBookingsList" style="max-height: 400px; overflow-y: auto;">Loading...</div>
    </div>
</div>

<style>
.calendar-day.has-bookings {
    background: rgba(74, 222, 128, 0.15);
    border-color: var(--accent);
}
.calendar-day.has-bookings:hover {
    background: rgba(74, 222, 128, 0.25);
}
.calendar-day-bookings {
    font-size: 0.7rem;
    margin-top: 5px;
    color: var(--accent);
    font-weight: 500;
}
.legend-dot.orange {
    background: rgba(74, 222, 128, 0.3);
    border: 1px solid var(--accent);
}
</style>

<script>
function showDateBookings(date) {
    fetch('api/get_date_bookings.php?date=' + date)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalDate').innerHTML = date;
            if (data.success && data.bookings.length > 0) {
                let html = '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr><th>Reference</th><th>Customer</th><th>Chairs</th><th>Tables</th><th>Status</th></tr></thead><tbody>';
                data.bookings.forEach(booking => {
                    html += '<tr>';
                    html += '<td>' + booking.booking_ref + '</td>';
                    html += '<td>' + booking.customer_name + '</td>';
                    html += '<td>' + booking.chairs + '</td>';
                    html += '<td>' + booking.tables + '</td>';
                    html += '<td><span class="status-badge status-' + booking.status + '">' + booking.status.charAt(0).toUpperCase() + booking.status.slice(1) + '</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                document.getElementById('dateBookingsList').innerHTML = html;
            } else {
                document.getElementById('dateBookingsList').innerHTML = '<p style="text-align: center; color: var(--text-muted);">No bookings for this date</p>';
            }
            document.getElementById('dateBookingsModal').style.display = 'flex';
        })
        .catch(error => {
            document.getElementById('dateBookingsList').innerHTML = '<p style="text-align: center; color: var(--danger);">Error loading bookings</p>';
            document.getElementById('dateBookingsModal').style.display = 'flex';
        });
}

function closeDateBookingsModal() {
    document.getElementById('dateBookingsModal').style.display = 'none';
}
</script>

        
        <!-- NOTIFICATIONS -->
        <?php elseif ($action === 'notifications'): ?>
<div class="data-card"><div class="data-card-header"><h3>Pending Review</h3></div>
<div class="data-table"><table class="notifications-table"><thead><tr><th>Ref</th><th>Customer</th><th>Date</th><th>Items</th><th>ID Upload</th><th>Actions</th></tr></thead>
<tbody><?php $notifSql = "SELECT * FROM bookings WHERE status = 'pending' ORDER BY created_at DESC"; $notifResult = mysqli_query($conn, $notifSql); if (mysqli_num_rows($notifResult) === 0): ?><tr><td colspan="6" style="text-align: center;">No pending notifications</td><?php else: while ($row = mysqli_fetch_assoc($notifResult)): ?>
<tr>
    <td><?php echo $row['booking_ref']; ?></td>
    <td><?php echo $row['customer_name']; ?></td>
    <td><?php echo formatDate($row['event_date']); ?></td>
    <td>🪑<?php echo $row['chairs']; ?> 🪵<?php echo $row['tables']; ?></td>
    <td><?php echo $row['id_file_path'] ? '<span style="color: var(--success);">✓ Uploaded</span>' : '<span style="color: var(--danger);">✗ Missing</span>'; ?></td>
    <td class="action-btns">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
            <input type="hidden" name="status" value="accepted">
            <button type="submit" name="update_status" class="action-btn" style="border-color: var(--success); color: var(--success);">
                <i class="fas fa-check-circle"></i> Accept
            </button>
        </form>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
            <input type="hidden" name="status" value="cancelled">
            <button type="submit" name="update_status" class="action-btn" style="border-color: var(--danger); color: var(--danger);">
                <i class="fas fa-times-circle"></i> Reject
            </button>
        </form>
        <button onclick="viewBooking(<?php echo $row['id']; ?>)" class="action-btn" style="border-color: var(--info); color: var(--info);">
            <i class="fas fa-eye"></i> View
        </button>
    </td>
</tr>
<?php endwhile; endif; ?></tbody>
</table></div></div>
        
        <!-- BOOKINGS LIST -->
<?php elseif (in_array($action, ['pending', 'accepted', 'completed', 'cancelled', 'all'])): ?>
<div class="data-card"><div class="data-card-header"><h3><?php echo ucfirst($action); ?> Bookings</h3><div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="Search..." id="searchInput" onkeyup="filterTable()"></div></div>
<div class="data-table"><table id="bookingsTable"><thead><tr><th>Ref</th><th>Customer</th><th>Contact</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php while ($row = mysqli_fetch_assoc($bookings ?? [])): ?>
<tr>
    <td><?php echo $row['booking_ref']; ?></td>
    <td><?php echo $row['customer_name']; ?></td>
    <td><?php echo $row['customer_contact']; ?></td>
    <td><?php echo formatDate($row['event_date']); ?></td>
    <td>🪑<?php echo $row['chairs']; ?> 🪵<?php echo $row['tables']; ?></td>
    <td><?php echo formatCurrency($row['total_amount']); ?></td>
    <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
    <td class="action-btns">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
            <select name="status" onchange="this.form.submit()" style="background: #1a1a1a; color: #fff; padding: 6px; border-radius: 6px; border: 1px solid #333;">
                <option value="pending" <?php echo $row['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="accepted" <?php echo $row['status'] === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                <option value="completed" <?php echo $row['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $row['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <button type="submit" name="update_status" style="background: transparent; border: 1px solid #f59e0b; color: #f59e0b; padding: 6px 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-size: 12px;">
                <i class="fas fa-sync-alt"></i> Update
            </button>
        </form>
        <a href="?action=edit&id=<?php echo $row['id']; ?>" style="background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 12px;">
            <i class="fas fa-pen"></i> Edit
        </a>
        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this booking?')">
            <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
            <button type="submit" name="delete_booking" style="background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 6px 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-size: 12px;">
                <i class="fas fa-trash-alt"></i> Delete
            </button>
        </form>
    </td>
</tr>
<?php endwhile; if (mysqli_num_rows($bookings ?? []) === 0): ?>
<tr><td colspan="8" style="text-align: center;">No bookings found</td></tr>
<?php endif; ?>
</tbody>
</table></div></div>

        <!-- CREATE BOOKING -->
<?php elseif ($action === 'create'): ?>
<div class="form-card">
    <h3>Create New Booking</h3>
    <form method="POST" id="admin-booking-form">
        <!-- Row 1: Name and Contact -->
        <div class="form-row">
            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="name" id="admin-name" required>
            </div>
            <div class="form-group">
                <label>Contact Number <span class="required">*</span></label>
                <input type="tel" name="contact" id="admin-contact" placeholder="09123456789" required maxlength="11" pattern="09[0-9]{9}">
            </div>
        </div>
        
        <!-- Row 2: Event Date and Time -->
        <div class="form-row">
            <div class="form-group">
                <label>Event Date <span class="required">*</span></label>
                <input type="date" name="date" id="admin-date" required>
            </div>
            <div class="form-group">
                <label>Event Time <span class="required">*</span></label>
                <select name="time" id="admin-time" required style="width: 100%; padding: 0.75rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);">
                    <option value="">Select Time</option>
                    <option value="08:00">08:00 AM</option>
                    <option value="09:00">09:00 AM</option>
                    <option value="10:00">10:00 AM</option>
                    <option value="11:00">11:00 AM</option>
                    <option value="12:00">12:00 PM</option>
                    <option value="13:00">01:00 PM</option>
                    <option value="14:00">02:00 PM</option>
                    <option value="15:00">03:00 PM</option>
                    <option value="16:00">04:00 PM</option>
                    <option value="17:00">05:00 PM</option>
                    <option value="18:00">06:00 PM</option>
                    <option value="19:00">07:00 PM</option>
                    <option value="20:00">08:00 PM</option>
                </select>
            </div>
        </div>
        
        <!-- Row 3: Venue Location -->
        <div class="form-group">
            <label>Venue / Specific Location <span class="required">*</span></label>
            <input type="text" name="location" id="admin-location" placeholder="Full address of event venue" required>
        </div>
        
        <!-- Row 4: Delivery Location and Event Type -->
        <div class="form-row">
            <div class="form-group">
                <label>Delivery Location <span class="required">*</span></label>
                <select name="location_area" id="admin-location-area" style="width: 100%; padding: 0.75rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);">
                    <option value="">Select Location</option>
                    <option value="Sogod" data-fee="50">Sogod - ₱50</option>
                    <option value="Bontoc" data-fee="100">Bontoc - ₱100</option>
                    <option value="Divisorya" data-fee="100">Divisorya - ₱100</option>
                    <option value="Libagon" data-fee="100">Libagon - ₱100</option>
                    <option value="Malitbog" data-fee="150">Malitbog - ₱150</option>
                    <option value="Tomas Oppus" data-fee="100">Tomas Oppus - ₱100</option>
                    <option value="Other" data-fee="200">Other Location - ₱200</option>
                </select>
                <input type="hidden" name="distance" id="admin-distance-input" value="0">
                <small id="admin-delivery-fee-display" style="color: var(--accent); display: block; margin-top: 5px;">Delivery Fee: ₱0</small>
            </div>
            <div class="form-group">
                <label>Event Type <span style="color: var(--text-muted);">(Optional)</span></label>
                <input type="text" name="event_type" list="admin-event-types" placeholder="Select or type event type..." style="width: 100%; padding: 0.75rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);">
                <datalist id="admin-event-types">
                    <option value="Wedding">
                    <option value="Birthday Party">
                    <option value="Corporate Event">
                    <option value="Fiesta / Community">
                    <option value="Graduation">
                    <option value="Funeral / Wake">
                    <option value="Reunion">
                    <option value="Other">
                </datalist>
            </div>
        </div>
        
        <!-- Row 5: Quantity Selection -->
        <div class="form-row">
            <div class="form-group">
                <label>Chairs (₱30 each)</label>
                <input type="number" name="chairs" id="admin-chairs" value="0" min="0" onchange="adminUpdatePriceSummary()" style="width: 100%; padding: 0.75rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);">
                <div class="stock-info" id="admin-chair-avail">Available: <?php echo number_format(TOTAL_CHAIRS); ?></div>
            </div>
            <div class="form-group">
                <label>Tables (₱130 each)</label>
                <input type="number" name="tables" id="admin-tables" value="0" min="0" onchange="adminUpdatePriceSummary()" style="width: 100%; padding: 0.75rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);">
                <div class="stock-info" id="admin-table-avail">Available: <?php echo number_format(TOTAL_TABLES); ?></div>
            </div>
        </div>
        
        <!-- Row 6: Logistics Toggle -->
        <div class="form-group">
            <label>Logistics</label>
            <div class="toggle-group">
                <button type="button" class="toggle-btn active" id="admin-toggle-delivery" onclick="adminSetDeliveryType('delivery')">
                    <i class="fas fa-truck"></i> Company Delivery
                </button>
                <button type="button" class="toggle-btn" id="admin-toggle-pickup" onclick="adminSetDeliveryType('pickup')">
                    <i class="fas fa-store"></i> Customer Pick-up
                </button>
            </div>
            <input type="hidden" name="delivery_type" id="admin-delivery-type" value="delivery">
        </div>
        
        <!-- Row 7: Price Summary -->
        <div class="price-summary">
            <div class="price-summary-title">Cost Summary</div>
            <div class="price-row">
                <span>Chairs (<span id="admin-summary-chairs">0</span> × ₱30)</span>
                <span>₱<span id="admin-summary-chair-cost">0</span></span>
            </div>
            <div class="price-row">
                <span>Tables (<span id="admin-summary-tables">0</span> × ₱130)</span>
                <span>₱<span id="admin-summary-table-cost">0</span></span>
            </div>
            <div class="price-row" id="admin-labor-row">
                <span>Labor Cost</span>
                <span>₱<span id="admin-summary-labor">0</span></span>
            </div>
            <div class="price-row" id="admin-fuel-row">
                <span>Delivery Fee</span>
                <span>₱<span id="admin-summary-fuel">0</span></span>
            </div>
            <div class="price-row total">
                <span>Total Estimate</span>
                <span>₱<span id="admin-summary-total">0</span></span>
            </div>
        </div>
        
        <!-- Row 8: Status -->
        <div class="form-group">
            <label>Booking Status</label>
            <select name="status" style="width: 100%; padding: 0.75rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);">
                <option value="pending">Pending</option>
                <option value="accepted">Accepted</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        
        <button type="submit" name="create_booking" class="btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Create Booking</button>
    </form>
</div>

<style>
.required { color: #ef4444; }
.stock-info { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem; }
.price-summary { background: var(--dark-card); border-radius: 12px; padding: 1rem; margin: 1rem 0; }
.price-summary-title { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem; }
.price-row { display: flex; justify-content: space-between; padding: 0.5rem 0; color: var(--text-secondary); }
.price-row.total { border-top: 1px solid var(--border); margin-top: 0.5rem; padding-top: 0.75rem; font-weight: 700; color: var(--accent); font-size: 1.1rem; }
.toggle-group { display: flex; gap: 1rem; }
.toggle-btn { flex: 1; padding: 0.7rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-secondary); cursor: pointer; transition: all 0.3s ease; }
.toggle-btn.active { background: var(--primary); border-color: var(--accent); color: white; }
select:focus, input:focus { border-color: #4ade80 !important; outline: none !important; box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.3) !important; }
</style>

<script>
let adminQuantities = { chairs: 0, tables: 0 };
let adminDeliveryType = 'delivery';

function adminUpdatePriceSummary() {
    var chairs = parseInt(document.getElementById('admin-chairs').value) || 0;
    var tables = parseInt(document.getElementById('admin-tables').value) || 0;
    var distance = parseFloat(document.getElementById('admin-distance-input').value) || 0;
    var chairCost = chairs * 30;
    var tableCost = tables * 130;
    var items = chairs + tables;
    var laborCost = 0, fuelCost = 0;
    
    if (adminDeliveryType === 'delivery') {
        laborCost = Math.ceil(items / 100) * 50;
        fuelCost = distance;
    }
    
    var total = chairCost + tableCost + laborCost + fuelCost;
    
    document.getElementById('admin-summary-chairs').textContent = chairs;
    document.getElementById('admin-summary-tables').textContent = tables;
    document.getElementById('admin-summary-chair-cost').textContent = chairCost.toLocaleString();
    document.getElementById('admin-summary-table-cost').textContent = tableCost.toLocaleString();
    document.getElementById('admin-summary-labor').textContent = laborCost.toLocaleString();
    document.getElementById('admin-summary-fuel').textContent = fuelCost.toLocaleString();
    document.getElementById('admin-summary-total').textContent = total.toLocaleString();
}

function adminSetDeliveryType(type) {
    adminDeliveryType = type;
    var deliveryBtn = document.getElementById('admin-toggle-delivery');
    var pickupBtn = document.getElementById('admin-toggle-pickup');
    var distanceInput = document.getElementById('admin-distance-input');
    var locationSelect = document.getElementById('admin-location-area');
    var feeDisplay = document.getElementById('admin-delivery-fee-display');
    
    if (deliveryBtn) deliveryBtn.classList.toggle('active', type === 'delivery');
    if (pickupBtn) pickupBtn.classList.toggle('active', type === 'pickup');
    document.getElementById('admin-delivery-type').value = type;
    
    if (type === 'pickup') {
        if (distanceInput) distanceInput.disabled = true;
        if (locationSelect) locationSelect.disabled = true;
        if (feeDisplay) feeDisplay.innerHTML = 'Delivery Fee: ₱0 (Pickup)';
    } else {
        if (distanceInput) distanceInput.disabled = false;
        if (locationSelect) locationSelect.disabled = false;
        adminUpdateDeliveryFee();
    }
    adminUpdatePriceSummary();
}

function adminUpdateDeliveryFee() {
    var locationSelect = document.getElementById('admin-location-area');
    var feeDisplay = document.getElementById('admin-delivery-fee-display');
    var distanceInput = document.getElementById('admin-distance-input');
    
    if (locationSelect && locationSelect.value) {
        var selectedOption = locationSelect.options[locationSelect.selectedIndex];
        var fee = parseInt(selectedOption.getAttribute('data-fee')) || 0;
        if (feeDisplay) feeDisplay.innerHTML = 'Delivery Fee: ₱' + fee.toLocaleString();
        if (distanceInput) distanceInput.value = fee;
        adminUpdatePriceSummary();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var dateInput = document.getElementById('admin-date');
    if (dateInput) dateInput.min = new Date().toISOString().split('T')[0];
    
    var locationSelect = document.getElementById('admin-location-area');
    if (locationSelect) {
        locationSelect.addEventListener('change', adminUpdateDeliveryFee);
    }
    
    var chairsInput = document.getElementById('admin-chairs');
    var tablesInput = document.getElementById('admin-tables');
    if (chairsInput) chairsInput.addEventListener('input', adminUpdatePriceSummary);
    if (tablesInput) tablesInput.addEventListener('input', adminUpdatePriceSummary);
    
    adminUpdatePriceSummary();
});
</script>
        
        <!-- EDIT BOOKING -->
        <?php elseif ($action === 'edit' && isset($editBooking)): ?>
        <div class="form-card" style="position: relative;">
            <div style="position: absolute; top: 20px; right: 20px;"><a href="?action=all" style="font-size: 1.8rem; text-decoration: none; color: var(--text-muted);">&times;</a></div>
            <h3>Edit Booking</h3>
            <form method="POST"><input type="hidden" name="booking_id" value="<?php echo $editBooking['id']; ?>">
            <div class="form-row"><div class="form-group"><label>Customer Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($editBooking['customer_name']); ?>" required></div><div class="form-group"><label>Contact Number</label><input type="text" name="contact" value="<?php echo htmlspecialchars($editBooking['customer_contact']); ?>"></div></div>
<select name="time" required style="width: 100%; padding: 0.75rem; background: var(--dark-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);">
    <option value="">Select Time</option>
    <option value="08:00" <?php echo ($editBooking['event_time'] == '08:00') ? 'selected' : ''; ?>>8:00 AM</option>
    <option value="09:00" <?php echo ($editBooking['event_time'] == '09:00') ? 'selected' : ''; ?>>9:00 AM</option>
    <option value="10:00" <?php echo ($editBooking['event_time'] == '10:00') ? 'selected' : ''; ?>>10:00 AM</option>
    <option value="11:00" <?php echo ($editBooking['event_time'] == '11:00') ? 'selected' : ''; ?>>11:00 AM</option>
    <option value="12:00" <?php echo ($editBooking['event_time'] == '12:00') ? 'selected' : ''; ?>>12:00 PM</option>
    <option value="13:00" <?php echo ($editBooking['event_time'] == '13:00') ? 'selected' : ''; ?>>1:00 PM</option>
    <option value="14:00" <?php echo ($editBooking['event_time'] == '14:00') ? 'selected' : ''; ?>>2:00 PM</option>
    <option value="15:00" <?php echo ($editBooking['event_time'] == '15:00') ? 'selected' : ''; ?>>3:00 PM</option>
    <option value="16:00" <?php echo ($editBooking['event_time'] == '16:00') ? 'selected' : ''; ?>>4:00 PM</option>
    <option value="17:00" <?php echo ($editBooking['event_time'] == '17:00') ? 'selected' : ''; ?>>5:00 PM</option>
    <option value="18:00" <?php echo ($editBooking['event_time'] == '18:00') ? 'selected' : ''; ?>>6:00 PM</option>
    <option value="19:00" <?php echo ($editBooking['event_time'] == '19:00') ? 'selected' : ''; ?>>7:00 PM</option>
    <option value="20:00" <?php echo ($editBooking['event_time'] == '20:00') ? 'selected' : ''; ?>>8:00 PM</option>
</select>            <div class="form-group"><label>Venue Location</label><input type="text" name="location" value="<?php echo htmlspecialchars($editBooking['venue_location']); ?>"></div>
            <div class="form-row"><div class="form-group"><label>Chairs</label><input type="number" name="chairs" value="<?php echo $editBooking['chairs']; ?>" min="0"></div><div class="form-group"><label>Tables</label><input type="number" name="tables" value="<?php echo $editBooking['tables']; ?>" min="0"></div></div>
            <div class="form-row"><div class="form-group"><label>Distance (km)</label><input type="number" name="distance" value="<?php echo $editBooking['distance_km']; ?>" step="0.5"></div><div class="form-group"><label>Delivery Type</label><select name="delivery_type"><option value="pickup" <?php echo $editBooking['delivery_type'] === 'pickup' ? 'selected' : ''; ?>>Pick-up</option><option value="delivery" <?php echo $editBooking['delivery_type'] === 'delivery' ? 'selected' : ''; ?>>Delivery</option></select></div></div>
            <div class="form-row"><div class="form-group"><label>Event Type</label><select name="event_type"><option value="">— Select —</option><option <?php echo $editBooking['event_type'] === 'Wedding' ? 'selected' : ''; ?>>Wedding</option><option <?php echo $editBooking['event_type'] === 'Birthday Party' ? 'selected' : ''; ?>>Birthday Party</option><option <?php echo $editBooking['event_type'] === 'Corporate Event' ? 'selected' : ''; ?>>Corporate Event</option><option <?php echo $editBooking['event_type'] === 'Fiesta' ? 'selected' : ''; ?>>Fiesta</option><option <?php echo $editBooking['event_type'] === 'Graduation' ? 'selected' : ''; ?>>Graduation</option><option <?php echo $editBooking['event_type'] === 'Other' ? 'selected' : ''; ?>>Other</option></select></div>
            <div class="form-group"><label>Status</label><select name="status"><option value="pending" <?php echo $editBooking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="accepted" <?php echo $editBooking['status'] === 'accepted' ? 'selected' : ''; ?>>Accepted</option><option value="completed" <?php echo $editBooking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option><option value="cancelled" <?php echo $editBooking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option></select></div></div>
<div style="display: flex; gap: 10px; margin-top: 20px;">
    <button type="submit" name="edit_booking" class="btn-primary" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;"><i class="fas fa-save"></i> Save Changes</button>
    <a href="?action=all" class="btn-outline" style="flex: 1; text-align: center; text-decoration: none; padding: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;"></i> Cancel</a>
        
        <!-- REVENUE & ANALYTICS -->
        <?php elseif ($action === 'revenue'): ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-line"></i></div><div class="stat-label">Total Revenue</div><div class="stat-value">₱<?php echo number_format($stats['total_revenue'], 2); ?></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-week"></i></div><div class="stat-label">Completed Bookings</div><div class="stat-value"><?php echo $stats['completed']; ?></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-spinner"></i></div><div class="stat-label">Pending Bookings</div><div class="stat-value"><?php echo $stats['pending']; ?></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-simple"></i></div><div class="stat-label">Average Order</div><div class="stat-value">₱<?php echo number_format(($stats['total_revenue'] / max($stats['total_bookings'], 1)), 2); ?></div></div>
        </div>
        <div class="data-card"><div class="data-card-header"><h3>Revenue by Booking Type</h3></div>
        
        <div class="data-table"><table><thead><tr><th>Event Type</th><th>Bookings</th><th>Revenue</th></tr></thead>
        <tbody><?php $typeSql = "SELECT COALESCE(event_type, 'Not Specified') as event_type, COUNT(*) as count, SUM(total_amount) as revenue FROM bookings WHERE status IN ('accepted', 'completed') GROUP BY COALESCE(event_type, 'Not Specified') ORDER BY revenue DESC"; $typeResult = mysqli_query($conn, $typeSql); while ($row = mysqli_fetch_assoc($typeResult)): ?>
        <tr><td><?php echo $row['event_type']; ?></td><td><?php echo $row['count']; ?></td><td>₱<?php echo number_format($row['revenue'], 2); ?></td></tr>
        <?php endwhile; ?></tbody></table></div></div>
        
        <!-- CHANGE PASSWORD -->
        <?php elseif ($action === 'change-password'): ?>
        <div class="form-card" style="max-width: 500px;">
            <h3 style="text-align: center;">Change Password</h3>
            <?php if (isset($passMessage)): ?><div class="alert alert-<?php echo $passType; ?>"><?php echo $passMessage; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
                <button type="submit" name="change_password" class="btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;"><i class="fas fa-key"></i> Update Password</button>
            </form>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
function toggleSubMenu(element) { const subMenu = element.nextElementSibling; subMenu.classList.toggle('open'); const icon = element.querySelector('.fa-chevron-down'); if (icon) icon.style.transform = subMenu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0)'; }
function filterTable() { const input = document.getElementById('searchInput'); if (!input) return; const filter = input.value.toLowerCase(); const table = document.getElementById('bookingsTable'); if (!table) return; const rows = table.getElementsByTagName('tr'); for (let i = 1; i < rows.length; i++) { if (rows[i].textContent.toLowerCase().includes(filter)) rows[i].style.display = ''; else rows[i].style.display = 'none'; } }
function viewBooking(id) { window.location.href = '?action=edit&id=' + id; }
document.querySelectorAll('.nav-sub .nav-item').forEach(item => { if (item.classList.contains('active')) item.closest('.nav-sub')?.classList.add('open'); });
</script>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>