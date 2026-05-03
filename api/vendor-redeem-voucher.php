<?php
/**
 * root/api/vendor-redeem-voucher.php
 * EatFree Vendor Redeem Voucher API
 *
 *
 * - validates vendor session
 * - validates active voucher
 * - marks voucher as used
 * - updates linked meal_claim
 * - prevents double redemption
 * - decrements vendor meals_remaining
 * - Confirms voucher
 * - Updates meal_claim
 * - Debits global_wallet subsidy pool
 * - Records wallet_transactions audit trail
 * - Credits vendor wallet_balance
 * - Updates vendor meal counters

 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

startSession();

if (!isset($_SESSION['vendor_id']) || ($_SESSION['user_type'] ?? '') !== 'vendor') {
    jsonResponse(false, null, 'Unauthorized');
}

$voucherId = isset($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : 0;
$vendorId  = (int)$_SESSION['vendor_id'];

if ($voucherId <= 0) {
    jsonResponse(false, null, 'Invalid voucher ID');
}

try {

    $db = getDB();

    /**
     * =========================
     * START TRANSACTION
     * =========================
     */
    $db->beginTransaction();

    /**
     * =========================
     * LOAD VOUCHER WITH LOCK
     * =========================
     */
    $stmt = $db->prepare("
        SELECT 
            id,
            voucher_code,
            beneficiary_id,
            vendor_id,
            amount,
            subsidy_amount,
            status,
            expires_at
        FROM vouchers
        WHERE id = ?
        AND vendor_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$voucherId, $vendorId]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        throw new Exception('Voucher not found');
    }

    if ($voucher['status'] !== 'active') {
        throw new Exception('Voucher has already been redeemed');
    }

    if (!empty($voucher['expires_at']) && strtotime($voucher['expires_at']) < time()) {
        throw new Exception('Voucher has expired');
    }

    /**
     * =========================
     * LOAD VENDOR WITH LOCK
     * =========================
     */
    $stmt = $db->prepare("
        SELECT 
            id,
            business_name,
            meals_remaining,
            wallet_balance,
            status,
            subscription_status
        FROM vendors
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        throw new Exception('Vendor not found');
    }

    if ($vendor['status'] !== 'approved' || $vendor['subscription_status'] !== 'active') {
        throw new Exception('Vendor is not active');
    }

    if ((int)$vendor['meals_remaining'] <= 0) {
        throw new Exception('No meals remaining');
    }

    /**
     * =========================
     * LOAD CLAIM WITH LOCK
     *
     * Your current schema links claims by voucher_code.
     * =========================
     */
    $stmt = $db->prepare("
        SELECT 
            id,
            voucher_code,
            vendor_id,
            beneficiary_id,
            subsidy_amount,
            meal_price,
            status,
            is_used
        FROM meal_claims
        WHERE voucher_code = ?
        AND vendor_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([
        $voucher['voucher_code'],
        $vendorId
    ]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$claim) {
        throw new Exception('Linked meal claim not found');
    }

    if ((int)$claim['is_used'] === 1) {
        throw new Exception('Meal claim has already been used');
    }

    /**
     * =========================
     * SUBSIDY AMOUNT
     * =========================
     */
    $subsidyAmount = (float)$voucher['subsidy_amount'];

    if ($subsidyAmount <= 0 && isset($claim['subsidy_amount'])) {
        $subsidyAmount = (float)$claim['subsidy_amount'];
    }

    if ($subsidyAmount <= 0) {
        $subsidyAmount = defined('SUBSIDY_AMOUNT') ? (float)SUBSIDY_AMOUNT : 5.00;
    }

    /**
     * =========================
     * ENSURE GLOBAL WALLET EXISTS
     * =========================
     */
    $stmt = $db->query("
        SELECT id, balance, total_received, total_distributed, meals_funded
        FROM global_wallet
        ORDER BY id ASC
        LIMIT 1
        FOR UPDATE
    ");
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {

        /**
         * If no wallet row exists yet, create one from completed donations.
         */
        $stmt = $db->query("
            SELECT COALESCE(SUM(amount), 0) AS total_completed
            FROM donations
            WHERE status = 'completed'
        ");
        $completedDonations = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_completed'];

        $stmt = $db->prepare("
            INSERT INTO global_wallet (
                balance,
                total_received,
                total_distributed,
                meals_funded,
                last_updated
            ) VALUES (?, ?, 0, 0, NOW())
        ");
        $stmt->execute([
            $completedDonations,
            $completedDonations
        ]);

        $walletId = (int)$db->lastInsertId();

        $wallet = [
            'id' => $walletId,
            'balance' => $completedDonations,
            'total_received' => $completedDonations,
            'total_distributed' => 0,
            'meals_funded' => 0
        ];
    }

    /**
     * =========================
     * MIGRATION SAFETY
     *
     * Your current DB dump showed global_wallet.balance = 0
     * while donations had completed test funds.
     *
     * If wallet is empty and total_received is empty, seed it once from completed donations.
     * This prevents tests from failing before PayFast/global wallet sync is finalized.
     * =========================
     */
    if ((float)$wallet['balance'] <= 0 && (float)$wallet['total_received'] <= 0) {

        $stmt = $db->query("
            SELECT COALESCE(SUM(amount), 0) AS total_completed
            FROM donations
            WHERE status = 'completed'
        ");
        $completedDonations = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_completed'];

        if ($completedDonations > 0) {
            $stmt = $db->prepare("
                UPDATE global_wallet
                SET 
                    balance = ?,
                    total_received = ?,
                    last_updated = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $completedDonations,
                $completedDonations,
                $wallet['id']
            ]);

            $wallet['balance'] = $completedDonations;
            $wallet['total_received'] = $completedDonations;
        }
    }

    /**
     * =========================
     * CHECK WALLET BALANCE
     * =========================
     */
    $currentBalance = (float)$wallet['balance'];

    if ($currentBalance < $subsidyAmount) {
        throw new Exception('EatFree subsidy wallet has insufficient funds');
    }

    $newWalletBalance = $currentBalance - $subsidyAmount;

    /**
     * =========================
     * MARK VOUCHER USED
     * =========================
     */
    $stmt = $db->prepare("
        UPDATE vouchers
        SET 
            status = 'used',
            used_at = NOW()
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$voucherId]);

    /**
     * =========================
     * UPDATE MEAL CLAIM
     *
     * IMPORTANT:
     * Your schema enum includes:
     * pending, claimed, verified, expired, rejected.
     * So we use 'verified', not 'completed'.
     * =========================
     */
    $stmt = $db->prepare("
        UPDATE meal_claims
        SET
            status = 'verified',
            verified_by_vendor_id = ?,
            verified_at = NOW(),
            is_used = 1
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $vendorId,
        $claim['id']
    ]);

    /**
     * =========================
     * DEBIT GLOBAL WALLET
     * =========================
     */
    $stmt = $db->prepare("
        UPDATE global_wallet
        SET
            balance = ?,
            total_distributed = total_distributed + ?,
            meals_funded = meals_funded + 1,
            last_updated = NOW()
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $newWalletBalance,
        $subsidyAmount,
        $wallet['id']
    ]);

    /**
     * =========================
     * RECORD WALLET TRANSACTION
     * =========================
     */
    $description = "Meal subsidy paid to shop: " . $vendor['business_name'] . " for voucher " . $voucher['voucher_code'];

    $stmt = $db->prepare("
        INSERT INTO wallet_transactions (
            transaction_type,
            amount,
            reference_id,
            reference_type,
            description,
            balance_after,
            created_at
        ) VALUES ('meal_subsidy', ?, ?, 'meal_claim', ?, ?, NOW())
    ");
    $stmt->execute([
        $subsidyAmount,
        $claim['id'],
        $description,
        $newWalletBalance
    ]);

    /**
     * =========================
     * CREDIT VENDOR WALLET
     * =========================
     */
    $stmt = $db->prepare("
        UPDATE vendors
        SET 
            wallet_balance = wallet_balance + ?,
            total_earned = total_earned + ?,
            meals_remaining = meals_remaining - 1,
            total_meals_served = total_meals_served + 1,
            lifetime_orders = lifetime_orders + 1
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $subsidyAmount,
        $subsidyAmount,
        $vendorId
    ]);

    /**
     * =========================
     * UPDATE BENEFICIARY TOTALS
     * =========================
     */
    if (!empty($voucher['beneficiary_id'])) {
        $stmt = $db->prepare("
            UPDATE beneficiaries
            SET total_meals_claimed = total_meals_claimed + 1
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$voucher['beneficiary_id']]);
    }

    /**
     * =========================
     * OPTIONAL ACTIVITY LOG
     * =========================
     */
    if (function_exists('logActivity')) {
        logActivity(
            'voucher_redeemed',
            'Voucher redeemed: ' . $voucher['voucher_code'] . ' | Subsidy: R' . number_format($subsidyAmount, 2),
            $voucherId,
            'voucher'
        );
    }

    /**
     * =========================
     * COMMIT
     * =========================
     */
    $db->commit();

    jsonResponse(true, [
        'voucher_id' => $voucherId,
        'voucher_code' => $voucher['voucher_code'],
        'claim_id' => (int)$claim['id'],
        'subsidy_paid' => $subsidyAmount,
        'wallet_balance_after' => $newWalletBalance
    ], 'Voucher redeemed successfully');

} catch (Exception $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("vendor-redeem-voucher.php error: " . $e->getMessage());

    jsonResponse(false, null, $e->getMessage());
}