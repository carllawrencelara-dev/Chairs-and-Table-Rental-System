<?php
require_once __DIR__ . '/../config/database.php';

// Admin login
function adminLogin($email, $password) {
    global $conn;
    $sql = "SELECT * FROM admin_users WHERE email = ? AND password = MD5(?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $email, $password);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_name'] = $row['fullname'];
        $_SESSION['admin_email'] = $row['email'];
        return true;
    }
    return false;
}

// Admin logout
function adminLogout() {
    session_destroy();
}

// Check if logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// Require login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin.php?action=login');
        exit();
    }
}

// Change password
function changePassword($adminId, $currentPassword, $newPassword) {
    global $conn;
    
    // Verify current password
    $sql = "SELECT id FROM admin_users WHERE id = ? AND password = MD5(?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $adminId, $currentPassword);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        return false;
    }
    
    // Update password
    $sql = "UPDATE admin_users SET password = MD5(?) WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $newPassword, $adminId);
    return mysqli_stmt_execute($stmt);
}

// Create admin account
function createAdmin($fullname, $email, $password, $securityCode) {
    global $conn;
    
    // Security code check
    if ($securityCode !== 'CT2025ADMIN') {
        return ['success' => false, 'message' => 'Invalid security code'];
    }
    
    // Check if email exists
    $sql = "SELECT id FROM admin_users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Create account
    $sql = "INSERT INTO admin_users (fullname, email, password) VALUES (?, ?, MD5(?))";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $fullname, $email, $password);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Account created successfully'];
    }
    return ['success' => false, 'message' => 'Failed to create account'];
}

// Forgot password - generate reset token
function generateResetToken($email) {
    global $conn;
    
    $sql = "SELECT id FROM admin_users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        return false;
    }
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $sql = "UPDATE admin_users SET reset_token = ?, reset_expires = ? WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $token, $expires, $email);
    
    return mysqli_stmt_execute($stmt) ? $token : false;
}

// Reset password with token
function resetPassword($token, $newPassword) {
    global $conn;
    
    $sql = "SELECT id FROM admin_users WHERE reset_token = ? AND reset_expires > NOW()";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $sql = "UPDATE admin_users SET password = MD5(?), reset_token = NULL, reset_expires = NULL WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $newPassword, $row['id']);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}
?>