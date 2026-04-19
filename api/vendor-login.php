<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

/**
 * STEP 1: LOG EVERYTHING COMING IN
 */
error_log("VENDOR LOGIN HIT");

error_log("CONTENT TYPE: " . ($_SERVER["CONTENT_TYPE"] ?? 'none'));
error_log("POST DATA: " . json_encode($_POST));

$raw = file_get_contents("php://input");
error_log("RAW INPUT: " . $raw);

/**
 * STEP 2: NORMALISE INPUT
 */
$data = $_POST;

if (empty($data)) {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $data = $json;
    }
}

$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

error_log("EMAIL RECEIVED: " . $email);
error_log("PASSWORD RECEIVED: " . ($password ? 'YES' : 'NO'));

if ($email === '' || $password === '') {
    jsonResponse(false, [
        'debug' => [
            'post' => $_POST,
            'raw' => $raw,
            'email' => $email,
            'password' => $password
        ]
    ], 'Login payload not reaching server correctly');
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM vendors WHERE email = ?");
    $stmt->execute([$email]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        jsonResponse(false, null, 'Vendor not found');
    }

    if (!password_verify($password, $vendor['password_hash'])) {
        jsonResponse(false, null, 'Password mismatch');
    }

    startSession();
    session_regenerate_id(true);

    $_SESSION['vendor_id'] = $vendor['id'];
    $_SESSION['user_type'] = 'vendor';

    jsonResponse(true, [
        'vendor_id' => $vendor['id'],
        'status' => $vendor['status'],
        'redirect_url' => '/vendor/dashboard.php'
    ], 'Login success');

} catch (Exception $e) {
    error_log($e->getMessage());
    jsonResponse(false, null, 'Server error');
}