<?php
/**
 * Logout API
 * Clears session and logs out user
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Start session
startSession();

// Log the logout if user was logged in
if (isset($_SESSION['user_type'])) {
    $userType = $_SESSION['user_type'];
    $userId = $_SESSION[$userType . '_id'] ?? null;
    logActivity($userType . '_logout', ucfirst($userType) . ' logged out', $userId, $userType);
}

// Clear all session data
$_SESSION = [];

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Destroy session
session_destroy();

jsonResponse(true, null, 'Logged out successfully');
