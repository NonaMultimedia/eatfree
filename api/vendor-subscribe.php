<?php
/**
 * Vendor Subscription API
 * Initiates PayFast payment for vendor subscriptions
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

// Require vendor login
if (!isVendorLoggedIn()) {
    jsonResponse(false, null, 'Please login to subscribe');
}

$vendorId = $_SESSION['vendor_id'];

try {
    $db = getDB();
    
    // Get vendor details
    $stmt = $db->prepare("
        SELECT id, business_name, email, subscription_status, subscription_expires_at
        FROM vendors WHERE id = ?
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        jsonResponse(false, null, 'Vendor not found');
    }
    
    // Check if already has active subscription
    if ($vendor['subscription_status'] === 'active' && $vendor['subscription_expires_at'] > date('Y-m-d')) {
        jsonResponse(false, null, 'You already have an active subscription until ' . $vendor['subscription_expires_at']);
    }
    
    // Get ecosystem settings
    $settings = getEcosystemSettings();
    
    $subscriptionAmount = $settings['vendor_subscription_amount'];
    $taxAmount = $subscriptionAmount * ($settings['vendor_subscription_tax'] / 100);
    $totalAmount = $subscriptionAmount + $taxAmount;
    
    // Generate unique payment reference
    $paymentReference = 'EF-SUB-' . time() . '-' . $vendorId;
    
    // Create pending subscription record
    $stmt = $db->prepare("
        INSERT INTO vendor_subscriptions (
            vendor_id, amount, tax_amount, total_amount, meals_included, 
            payment_status, subscription_start, subscription_end
        ) VALUES (?, ?, ?, ?, ?, 'pending', NULL, NULL)
    ");
    $stmt->execute([
        $vendorId,
        $subscriptionAmount,
        $taxAmount,
        $totalAmount,
        $settings['meals_per_vendor']
    ]);
    
    $subscriptionId = $db->lastInsertId();
    
    // Prepare PayFast data
    $payfastData = [
        'merchant_id' => PAYFAST_MERCHANT_ID,
        'merchant_key' => PAYFAST_MERCHANT_KEY,
        'return_url' => PAYFAST_RETURN_URL . '?subscription_id=' . $subscriptionId,
        'cancel_url' => PAYFAST_CANCEL_URL . '?subscription_id=' . $subscriptionId,
        'notify_url' => PAYFAST_NOTIFY_URL,
        'name_first' => substr($vendor['business_name'], 0, 100),
        'email_address' => $vendor['email'],
        'm_payment_id' => $paymentReference,
        'amount' => number_format($totalAmount, 2, '.', ''),
        'item_name' => 'EatFree Vendor Subscription',
        'item_description' => 'Monthly subscription for ' . $settings['meals_per_vendor'] . ' meals',
        'custom_str1' => $subscriptionId,
        'custom_str2' => 'subscription'
    ];
    
    // Generate signature
    $signature = generatePayFastSignature($payfastData, PAYFAST_PASSPHRASE);
    $payfastData['signature'] = $signature;
    
    // Build PayFast URL
    $payfastUrl = PAYFAST_PROCESS_URL . '?' . http_build_query($payfastData);
    
    jsonResponse(true, [
        'subscription_id' => $subscriptionId,
        'payment_reference' => $paymentReference,
        'amount' => $subscriptionAmount,
        'tax_amount' => $taxAmount,
        'total_amount' => $totalAmount,
        'meals_included' => $settings['meals_per_vendor'],
        'payfast_url' => $payfastUrl,
        'payfast_data' => $payfastData
    ], 'Subscription initiated. Redirecting to PayFast...');
    
} catch (Exception $e) {
    error_log("Vendor subscription error: " . $e->getMessage());
    jsonResponse(false, null, 'Failed to initiate subscription. Please try again.');
}
