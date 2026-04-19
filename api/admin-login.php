<?php
/**
 * Admin Login API
 * Authenticates administrators
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, null, 'Invalid input data');
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

// Validate inputs
if (empty($username) || empty($password)) {
    jsonResponse(false, null, 'Username and password are required');
}

try {
    $db = getDB();
    
    // Find admin by username or email
    $stmt = $db->prepare("
        SELECT id, username, email, password_hash, full_name, role, is_active
        FROM administrators 
        WHERE (username = ? OR email = ?) AND is_active = 1
    ");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        jsonResponse(false, null, 'Invalid username or password');
    }
    
    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        jsonResponse(false, null, 'Invalid username or password');
    }
    
    // Start session and store admin data
    startSession();
    
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['user_type'] = 'admin';
    $_SESSION['login_time'] = time();
    
    // Update last login
    $stmt = $db->prepare("UPDATE administrators SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);
    
    // Log activity
    logActivity('admin_login', 'Admin logged in: ' . $admin['username'], $admin['id'], 'admin');
    
    // Return admin data (excluding sensitive info)
    jsonResponse(true, [
        'admin_id' => $admin['id'],
        'username' => $admin['username'],
        'email' => $admin['email'],
        'full_name' => $admin['full_name'],
        'role' => $admin['role'],
        'redirect_url' => 'https://admin.eatfree.co.za/dashboard.php'
    ], 'Login successful');
    
} catch (Exception $e) {
    error_log("Admin login error: " . $e->getMessage());
    jsonResponse(false, null, 'Login failed. Please try again.');
}
