<?php
/**
 * Process Donation API
 * Initiates PayFast payment for donations
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

$amount = (float)($input['amount'] ?? 0);
$donorName = trim($input['donor_name'] ?? '');
$donorEmail = trim($input['donor_email'] ?? '');
$donorPhone = trim($input['donor_phone'] ?? '');
$isAnonymous = (bool)($input['is_anonymous'] ?? false);
$message = trim($input['message'] ?? '');

// Validate amount
if ($amount <= 0) {
    jsonResponse(false, null, 'Please enter a valid donation amount');
}

if ($amount < 10) {
    jsonResponse(false, null, 'Minimum donation amount is R10');
}

// Validate email if provided
if (!empty($donorEmail) && !filter_var($donorEmail, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, null, 'Please enter a valid email address');
}

try {
    $db = getDB();
    
    // Generate unique payment reference
    $paymentReference = 'EF-DON-' . time() . '-' . rand(1000, 9999);
    
    // Create pending donation record
    $stmt = $db->prepare("
        INSERT INTO donations (donor_name, donor_email, donor_phone, amount, payment_reference, is_anonymous, message, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $isAnonymous ? 'Anonymous' : sanitize($donorName),
        sanitize($donorEmail),
        sanitize($donorPhone),
        $amount,
        $paymentReference,
        $isAnonymous ? 1 : 0,
        sanitize($message)
    ]);
    
    $donationId = $db->lastInsertId();
    
    // Prepare PayFast data
    $payfastData = [
        'merchant_id' => PAYFAST_MERCHANT_ID,
        'merchant_key' => PAYFAST_MERCHANT_KEY,
        'return_url' => PAYFAST_RETURN_URL . '?donation_id=' . $donationId,
        'cancel_url' => PAYFAST_CANCEL_URL . '?donation_id=' . $donationId,
        'notify_url' => PAYFAST_NOTIFY_URL,
        'name_first' => $isAnonymous ? 'Anonymous' : substr($donorName, 0, 100),
        'email_address' => $donorEmail,
        'm_payment_id' => $paymentReference,
        'amount' => number_format($amount, 2, '.', ''),
        'item_name' => 'EatFree Donation',
        'item_description' => 'Donation to feed communities through EatFree',
        'custom_str1' => $donationId,
        'custom_str2' => 'donation'
    ];
    
    // Generate signature
    $signature = generatePayFastSignature($payfastData, PAYFAST_PASSPHRASE);
    $payfastData['signature'] = $signature;
    
    // Build PayFast URL
    $payfastUrl = PAYFAST_PROCESS_URL . '?' . http_build_query($payfastData);
    
    jsonResponse(true, [
        'donation_id' => $donationId,
        'payment_reference' => $paymentReference,
        'amount' => $amount,
        'payfast_url' => $payfastUrl,
        'payfast_data' => $payfastData
    ], 'Donation initiated. Redirecting to PayFast...');
    
} catch (Exception $e) {
    error_log("Process donation error: " . $e->getMessage());
    jsonResponse(false, null, 'Failed to process donation. Please try again.');
}
