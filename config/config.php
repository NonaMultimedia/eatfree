<?php
/**
 * EATFREE Configuration File
 * 
 * This file contains all system-wide configuration settings.
 * Update database credentials and PayFast settings before deployment.
 */

// =====================================================
// DATABASE CONFIGURATION
// Update these with your actual database credentials
// =====================================================
define('DB_HOST', 'localhost');           	// Database host (e.g., localhost)
define('DB_NAME', 'eatfres2a1d6_eatfree_db');   // Database name
define('DB_USER', 'eatfres2a1d6_admin');        // Database username
define('DB_PASS', 'eatfree_Gl0b@l');            // Database password
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// PAYFAST CONFIGURATION
// =====================================================
define('PAYFAST_SANDBOX', true);
define('PAYFAST_MERCHANT_ID', '10000100');
define('PAYFAST_MERCHANT_KEY', '46f0cd694581a');
define('PAYFAST_PASSPHRASE', '');
define('PAYFAST_RETURN_URL', 'https://eatfree.co.za/payment-success.php');
define('PAYFAST_CANCEL_URL', 'https://eatfree.co.za/payment-cancel.php');
define('PAYFAST_NOTIFY_URL', 'https://eatfree.co.za/api/payfast-itn.php');

// PayFast URLs
if (PAYFAST_SANDBOX) {
    define('PAYFAST_HOST', 'sandbox.payfast.co.za');
} else {
    define('PAYFAST_HOST', 'www.payfast.co.za');
}
define('PAYFAST_PROCESS_URL', 'https://' . PAYFAST_HOST . '/eng/process');
define('PAYFAST_VALIDATE_URL', 'https://' . PAYFAST_HOST . '/eng/query/validate');

// =====================================================
// SITE CONFIGURATION
// =====================================================
define('SITE_URL', 'https://eatfree.co.za');
define('ADMIN_URL', 'https://admin.eatfree.co.za');
define('ECO_URL', 'https://eco.eatfree.co.za');
define('SITE_NAME', 'EatFree');
define('SITE_TAGLINE', 'Anywhere. Anytime. Forever.');
define('COMPANY_NAME', 'WiseLink PTY (LTD)');
define('SUPPORT_EMAIL', 'support@eatfree.co.za');

// =====================================================
// BUSINESS RULES
// =====================================================
define('MEAL_PRICE', 15.00);              // Price per meal in Rands
define('SUBSIDY_AMOUNT', 5.00);           // EatFree subsidy per meal
define('VENDOR_SUBSCRIPTION', 99.00);     // Monthly vendor subscription
define('VENDOR_SUBSCRIPTION_TAX', 15.00); // Tax percentage on subscription
define('MEALS_PER_VENDOR', 50);           // Meals included in subscription
define('MIN_WITHDRAWAL', 200.00);         // Minimum withdrawal amount
define('TARGET_THRESHOLD', 1000000.00);   // Target for full free mode

// =====================================================
// FILE UPLOAD SETTINGS
// =====================================================
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB max upload
define('ALLOWED_LOGO_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'image/jpeg', 'image/png']);
define('LOGO_UPLOAD_PATH', __DIR__ . '/../uploads/logos/');
define('DOC_UPLOAD_PATH', __DIR__ . '/../uploads/documents/');
define('LOGO_WEB_PATH', '/uploads/logos/');
define('DOC_WEB_PATH', '/uploads/documents/');

// =====================================================
// SESSION SETTINGS
// =====================================================
define('SESSION_NAME', 'EatFreeSession');
define('SESSION_LIFETIME', 7200); // 2 hours
define('SESSION_PATH', '/');

// =====================================================
// SECURITY SETTINGS
// =====================================================
define('CSRF_TOKEN_NAME', 'ef_csrf_token');
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// =====================================================
// DATABASE CONNECTION
// =====================================================
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================

/**
 * Start secure session
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {

        // IMPORTANT FIX: hosting-safe session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);

        // DO NOT force secure cookie on non-HTTPS environments
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        ini_set('session.cookie_secure', $isHttps ? 1 : 0);

        // FIX: Strict breaks login persistence in many setups
        ini_set('session.cookie_samesite', 'Lax');

        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

        session_name(SESSION_NAME);
        session_start();

        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        }

        if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Check if user is logged in as vendor
 */
function isVendorLoggedIn() {
    startSession();
    return isset($_SESSION['vendor_id']) && $_SESSION['user_type'] === 'vendor';
}

/**
 * Check if user is logged in as admin
 */
function isAdminLoggedIn() {
    startSession();
    return isset($_SESSION['admin_id']) && $_SESSION['user_type'] === 'admin';
}

/**
 * Require vendor login
 */
function requireVendorLogin() {
    if (!isVendorLoggedIn()) {
        header("Location: /login.html");
        exit;
    }
}

/**
 * Require admin login
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header("Location: /admin-login.php");
        exit;
    }
}

/**
 * Send JSON response (for API endpoints)
 */
function jsonResponse($success, $data = null, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return 'R' . number_format($amount, 2);
}

/**
 * Generate unique voucher code
 */
function generateVoucherCode() {
    return 'EF' . strtoupper(substr(uniqid(), -6)) . rand(100, 999);
}

/**
 * Generate PayFast signature
 */
function generatePayFastSignature($data, $passphrase = '') {
    $pfOutput = '';
    foreach ($data as $key => $val) {
        if ($val !== '') {
            $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
        }
    }
    $getString = substr($pfOutput, 0, -1);
    
    if ($passphrase !== '') {
        $getString .= '&passphrase=' . urlencode($passphrase);
    }
    
    return md5($getString);
}

/**
 * Log activity
 */
function logActivity($type, $description, $userId = null, $userType = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (type, description, user_id, user_type, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$type, $description, $userId, $userType, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Send email using PHP mail()
 */
function sendEmail($to, $subject, $body, $from = null) {
    $from = $from ?? SUPPORT_EMAIL;
    $headers = "From: " . SITE_NAME . " <" . $from . ">\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

/**
 * Get ecosystem settings
 */
function getEcosystemSettings() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM ecosystem_settings LIMIT 1");
        return $stmt->fetch();
    } catch (Exception $e) {
        return [
            'meal_price' => MEAL_PRICE,
            'subsidy_amount' => SUBSIDY_AMOUNT,
            'meals_per_vendor' => MEALS_PER_VENDOR,
            'min_withdrawal' => MIN_WITHDRAWAL,
            'vendor_subscription_amount' => VENDOR_SUBSCRIPTION,
            'vendor_subscription_tax' => VENDOR_SUBSCRIPTION_TAX,
            'target_threshold' => TARGET_THRESHOLD,
            'current_mode' => 'subsidized'
        ];
    }
}

/**
 * Update global wallet
 */
function updateGlobalWallet($amount, $type = 'add') {
    try {
        $db = getDB();
        if ($type === 'add') {
            $stmt = $db->prepare("UPDATE global_wallet SET balance = balance + ?, total_received = total_received + ? ORDER BY id LIMIT 1");
            $stmt->execute([$amount, $amount]);
        } else {
            $stmt = $db->prepare("UPDATE global_wallet SET balance = balance - ?, total_distributed = total_distributed + ? ORDER BY id LIMIT 1");
            $stmt->execute([$amount, $amount]);
        }
        return true;
    } catch (Exception $e) {
        error_log("Failed to update global wallet: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current wallet balance
 */
function getWalletBalance() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT balance FROM global_wallet LIMIT 1");
        $result = $stmt->fetch();
        return $result ? (float)$result['balance'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Calculate ecosystem capacity
 */
function calculateEcosystemCapacity() {
    $settings = getEcosystemSettings();
    $walletBalance = getWalletBalance();
    
    $totalMealsAvailable = floor($walletBalance / $settings['subsidy_amount']);
    $maxVendors = floor($totalMealsAvailable / $settings['meals_per_vendor']);
    
    try {
        $db = getDB();
        $stmt = $db->query("SELECT COUNT(*) as approved_vendors FROM vendors WHERE status = 'approved'");
        $result = $stmt->fetch();
        $approvedVendors = $result['approved_vendors'];
        
        $stmt = $db->query("SELECT COUNT(*) as waiting_vendors FROM vendor_queue WHERE status = 'waiting'");
        $result = $stmt->fetch();
        $waitingVendors = $result['waiting_vendors'];
    } catch (Exception $e) {
        $approvedVendors = 0;
        $waitingVendors = 0;
    }
    
    $availableSlots = max(0, $maxVendors - $approvedVendors);
    $mealsUntilFree = max(0, ($settings['target_threshold'] - $walletBalance) / $settings['subsidy_amount']);
    $percentComplete = min(100, ($walletBalance / $settings['target_threshold']) * 100);
    
    return [
        'wallet_balance' => $walletBalance,
        'total_meals_available' => $totalMealsAvailable,
        'max_vendors' => $maxVendors,
        'approved_vendors' => $approvedVendors,
        'waiting_vendors' => $waitingVendors,
        'available_slots' => $availableSlots,
        'meals_until_free' => floor($mealsUntilFree),
        'percent_complete' => round($percentComplete, 2),
        'target_threshold' => $settings['target_threshold'],
        'current_mode' => $settings['current_mode']
    ];
}

// =====================================================
// ERROR HANDLING
// =====================================================
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Custom error handler
set_error_handler(function($severity, $message, $file, $line) {
    error_log("[$severity] $message in $file on line $line");
    return true;
});

// Exception handler
set_exception_handler(function($e) {
    error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        jsonResponse(false, null, 'An internal error occurred');
    }
});
