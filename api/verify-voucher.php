<?php
/**
 * Verify Voucher API
 * Works with current EatFree schema + architecture
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

// Read JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, null, 'Invalid input data');
}

$voucherCode = trim($input['voucher_code'] ?? '');
$vendorId    = (int)($input['vendor_id'] ?? 0);

if ($voucherCode === '') {
    jsonResponse(false, null, 'Voucher code required');
}

if ($vendorId <= 0) {
    jsonResponse(false, null, 'Vendor required');
}

try {
    $db = getDB();
    $db->beginTransaction();

    /**
     * Get voucher
     */
    $stmt = $db->prepare("
        SELECT 
            v.*,
            b.full_name,
            b.id_number,
            ven.business_name
        FROM vouchers v
        INNER JOIN beneficiaries b ON b.id = v.beneficiary_id
        INNER JOIN vendors ven ON ven.id = v.vendor_id
        WHERE v.voucher_code = ?
        LIMIT 1
    ");
    $stmt->execute([$voucherCode]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        $db->rollBack();
        jsonResponse(false, null, 'Invalid voucher code');
    }

    /**
     * Status checks
     */
    if ($voucher['status'] === 'used') {
        $db->rollBack();
        jsonResponse(false, null, 'Voucher already used');
    }

    if ($voucher['status'] === 'cancelled') {
        $db->rollBack();
        jsonResponse(false, null, 'Voucher cancelled');
    }

    if ($voucher['status'] === 'expired') {
        $db->rollBack();
        jsonResponse(false, null, 'Voucher expired');
    }

    /**
     * Expiry check
     */
    if (strtotime($voucher['expires_at']) < time()) {
        $stmt = $db->prepare("
            UPDATE vouchers
            SET status='expired'
            WHERE id=?
        ");
        $stmt->execute([$voucher['id']]);

        $db->commit();
        jsonResponse(false, null, 'Voucher expired');
    }

    /**
     * Vendor must match voucher vendor
     */
    if ((int)$voucher['vendor_id'] !== $vendorId) {
        $db->rollBack();
        jsonResponse(false, null, 'Voucher not valid for selected vendor');
    }

    /**
     * Confirm vendor still active
     */
    $stmt = $db->prepare("
        SELECT id
        FROM vendors
        WHERE id=? AND status='approved' AND is_active=1
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);

    if (!$stmt->fetch()) {
        $db->rollBack();
        jsonResponse(false, null, 'Vendor not active');
    }

    /**
     * Mark voucher used
     */
    $stmt = $db->prepare("
        UPDATE vouchers
        SET status='used',
            used_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([$voucher['id']]);

    /**
     * Insert claim record
     */
    $stmt = $db->prepare("
        INSERT INTO meal_claims (
            voucher_id,
            beneficiary_id,
            vendor_id,
            claimed_at,
            verified_by_vendor_id,
            subsidy_amount,
            meal_price
        ) VALUES (?, ?, ?, NOW(), ?, ?, ?)
    ");

    $stmt->execute([
        $voucher['id'],
        $voucher['beneficiary_id'],
        $vendorId,
        $vendorId,
        $voucher['subsidy_amount'],
        $voucher['amount']
    ]);

    /**
     * Pay vendor subsidy
     * Donations/global wallet was already reduced during voucher generation.
     * So here we only credit vendor wallet.
     */
    $stmt = $db->prepare("
        UPDATE vendors
        SET total_meals_served = total_meals_served + 1,
            wallet_balance    = wallet_balance + ?
        WHERE id = ?
    ");
    $stmt->execute([
        $voucher['subsidy_amount'],
        $vendorId
    ]);

    /**
     * Beneficiary stats
     */
    $stmt = $db->prepare("
        UPDATE beneficiaries
        SET total_meals_claimed = total_meals_claimed + 1,
            last_claim_date = CURDATE()
        WHERE id = ?
    ");
    $stmt->execute([$voucher['beneficiary_id']]);

    /**
     * Meals funded counter only
     */
    $stmt = $db->prepare("
        UPDATE global_wallet
        SET meals_funded = meals_funded + 1
        WHERE id = 1
    ");
    $stmt->execute();

    /**
     * Log transaction
     */
    $stmt = $db->prepare("
        INSERT INTO wallet_transactions (
            transaction_type,
            amount,
            reference_id,
            reference_type,
            description,
            balance_after
        ) VALUES (
            'vendor_payment',
            ?,
            ?,
            'meal_claim',
            ?,
            ?
        )
    ");

    $stmt->execute([
        $voucher['subsidy_amount'],
        $voucher['id'],
        'Voucher claimed: ' . $voucher['voucher_code'],
        getWalletBalance()
    ]);

    $db->commit();

    jsonResponse(true, [
        'voucher_code'      => $voucher['voucher_code'],
        'beneficiary_name'  => $voucher['full_name'],
        'vendor_name'       => $voucher['business_name'],
        'amount'            => (float)$voucher['amount'],
        'subsidy_amount'    => (float)$voucher['subsidy_amount'],
        'claimed_at'        => date('Y-m-d H:i:s')
    ], 'Voucher verified successfully');

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Verify voucher error: ' . $e->getMessage());

    jsonResponse(false, null, 'Failed to verify voucher. Please try again.');
}