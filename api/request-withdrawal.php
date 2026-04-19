<?php
/**
 * Request Withdrawal API
 * Allows vendors to request withdrawals
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

// Require vendor login
if (!isVendorLoggedIn()) {
    jsonResponse(false, null, 'Please login to request withdrawal');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, null, 'Invalid input data');
}

$amount = (float)($input['amount'] ?? 0);
$vendorId = $_SESSION['vendor_id'];

try {
    $db = getDB();
    
    // Get ecosystem settings
    $settings = getEcosystemSettings();
    
    // Validate minimum withdrawal
    if ($amount < $settings['min_withdrawal']) {
        jsonResponse(false, null, 'Minimum withdrawal amount is R' . $settings['min_withdrawal']);
    }
    
    // Get vendor wallet balance
    $stmt = $db->prepare("SELECT wallet_balance, bank_name, bank_account_number FROM vendors WHERE id = ?");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        jsonResponse(false, null, 'Vendor not found');
    }
    
    // Check if bank details are set
    if (empty($vendor['bank_name']) || empty($vendor['bank_account_number'])) {
        jsonResponse(false, null, 'Please update your bank details before requesting withdrawal');
    }
    
    // Check sufficient balance
    if ($amount > $vendor['wallet_balance']) {
        jsonResponse(false, null, 'Insufficient balance. Your available balance is R' . number_format($vendor['wallet_balance'], 2));
    }
    
    // Check for pending withdrawals
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM vendor_withdrawals 
        WHERE vendor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$vendorId]);
    $pending = $stmt->fetch();
    
    if ($pending['count'] > 0) {
        jsonResponse(false, null, 'You already have a pending withdrawal request');
    }
    
    // Create withdrawal request
    $stmt = $db->prepare("
        INSERT INTO vendor_withdrawals (vendor_id, amount, status, created_at)
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$vendorId, $amount]);
    
    $withdrawalId = $db->lastInsertId();
    
    // Reserve the amount (optional - can also deduct on approval)
    // $stmt = $db->prepare("UPDATE vendors SET wallet_balance = wallet_balance - ? WHERE id = ?");
    // $stmt->execute([$amount, $vendorId]);
    
    jsonResponse(true, [
        'withdrawal_id' => $withdrawalId,
        'amount' => $amount,
        'status' => 'pending',
        'message' => 'Withdrawal request submitted successfully. You will be notified once it is processed.'
    ], 'Withdrawal request submitted');
    
} catch (Exception $e) {
    error_log("Request withdrawal error: " . $e->getMessage());
    jsonResponse(false, null, 'Failed to submit withdrawal request. Please try again.');
}
