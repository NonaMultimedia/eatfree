<?php
/**
 * Vendor Logout (SAFE SESSION CLEAR)
 */

require_once __DIR__ . '/../config/config.php';

startSession();

/**
 * Clear session data
 */
$_SESSION = [];

/**
 * Remove session cookie
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/**
 * Destroy session
 */
session_destroy();

/**
 * Redirect to homepage or login
 */
header("Location: /index.html");
exit;