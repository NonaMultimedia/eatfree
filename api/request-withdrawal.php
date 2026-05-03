<?php
/**
 * root/api/request-withdrawal.php
 * EatFree Vendor Withdrawal Request API
 *
 * FIX 4:
 * Clean withdrawal request flow.
 * - no duplicate validation blocks
 * - checks vendor exists before reading vendor fields
 * - blocks inactive/unapproved vendors
 * - blocks vendors without banking details
 * - calculates available balance safely
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

if (!isVendorLoggedIn()) {
    jsonResponse(false, null, 'Please login to request withdrawal');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    jsonResponse(false, null, 'Invalid input data');
}

$amount = isset($input['amount']) ? (float)$input['amount'] : 0;
$vendorId = (int)$_SESSION['vendor_id'];

if ($amount <= 0) {
    jsonResponse(false, null, 'Please enter a valid withdrawal amount');
}

try {

    $db = getDB();

    /**
     * =========================
     * SETTINGS
     * =========================
     */
    $settings = getEcosystemSettings();

    $minWithdrawal = isset($settings['min_withdrawal'])
        ? (float)$settings['min_withdrawal']
        : 200.00;

    if ($amount < $minWithdrawal) {
        jsonResponse(false, null, 'Minimum withdrawal amount is R' . number_format($minWithdrawal, 2));
    }

    /**
     * =========================
     * LOAD VENDOR
     * =========================
     */
    $stmt = $db->prepare("
        SELECT 
            id,
            business_name,
            wallet_balance,
            bank_name,
            bank_account_number,
            bank_branch_code,
            bank_account_holder,
            status,
            subscription_status
        FROM vendors
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        jsonResponse(false, null, 'Vendor not found');
    }

    /**
     * =========================
     * VENDOR ACTIVATION CHECK
     * =========================
     */
    if ($vendor['status'] !== 'approved' || $vendor['subscription_status'] !== 'active') {
        jsonResponse(false, null, 'Your shop is not fully activated yet.');
    }

    /**
     * =========================
     * BANK DETAILS CHECK
     * =========================
     */
    if (
        empty($vendor['bank_name']) ||
        empty($vendor['bank_account_number']) ||
        empty($vendor['bank_branch_code']) ||
        empty($vendor['bank_account_holder'])
    ) {
        jsonResponse(false, null, 'Please complete your banking details before requesting a withdrawal.');
    }

    /**
     * =========================
     * EXISTING PENDING WITHDRAWAL CHECK
     * =========================
     */
    $stmt = $db->prepare("
        SELECT COUNT(*) AS pending_count
        FROM vendor_withdrawals
        WHERE vendor_id = ?
        AND status = 'pending'
    ");
    $stmt->execute([$vendorId]);
    $pendingCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

    if ($pendingCount > 0) {
        jsonResponse(false, null, 'You already have a pending withdrawal request');
    }

    /**
     * =========================
     * PENDING / PROCESSING RESERVED AMOUNT
     *
     * We include processing here so a withdrawal already being handled
     * by admin cannot be requested again.
     * =========================
     */
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS reserved_amount
        FROM vendor_withdrawals
        WHERE vendor_id = ?
        AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$vendorId]);
    $reservedAmount = (float)$stmt->fetch(PDO::FETCH_ASSOC)['reserved_amount'];

    /**
     * =========================
     * AVAILABLE BALANCE
     *
     * Current live truth:
     * vendors.wallet_balance is the spendable vendor wallet.
     * Later we can replace this with vendor-transaction-engine summary.
     * =========================
     */
    $walletBalance = (float)$vendor['wallet_balance'];
    $availableBalance = max(0, $walletBalance - $reservedAmount);

    if ($amount > $availableBalance) {
        jsonResponse(
            false,
            null,
            'Insufficient available balance. You can withdraw up to R' . number_format($availableBalance, 2)
        );
    }

    /**
     * =========================
     * CREATE WITHDRAWAL REQUEST
     * =========================
     */
    $stmt = $db->prepare("
        INSERT INTO vendor_withdrawals (
            vendor_id,
            amount,
            status,
            created_at
        ) VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $vendorId,
        $amount
    ]);

    $withdrawalId = (int)$db->lastInsertId();

    if (function_exists('logActivity')) {
        logActivity(
            'vendor_withdrawal_requested',
            'Withdrawal requested by ' . $vendor['business_name'] . ' for R' . number_format($amount, 2),
            $vendorId,
            'vendor'
        );
    }

    jsonResponse(true, [
        'withdrawal_id' => $withdrawalId,
        'amount' => $amount,
        'status' => 'pending',
        'available_balance_before_request' => $availableBalance
    ], 'Withdrawal request submitted successfully. You will be notified once it is processed.');

} catch (Exception $e) {

    error_log("request-withdrawal.php error: " . $e->getMessage());

    jsonResponse(false, null, 'Failed to submit withdrawal request. Please try again.');
}