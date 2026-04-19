<?php
/**
 * EatFree Transaction Engine
 * root/api/process-meal-claim.php
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

/**
 * INPUT SUPPORT (POST or JSON)
 */
$data = $_POST;

if (empty($data)) {
    $raw = file_get_contents("php://input");
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $data = $json;
    }
}

$voucherCode = trim($data['voucher_code'] ?? '');
$vendorId = (int)($_SESSION['vendor_id'] ?? 0);

if ($vendorId <= 0) {
    jsonResponse(false, null, 'Unauthorized vendor session');
}

if ($voucherCode === '') {
    jsonResponse(false, null, 'Voucher code required');
}

try {
    $db = getDB();
    $db->beginTransaction();

    /**
     * 1. Get voucher
     */
    $stmt = $db->prepare("
        SELECT * FROM vouchers
        WHERE voucher_code = ?
        AND status = 'active'
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$voucherCode]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        $db->rollBack();
        jsonResponse(false, null, 'Invalid or used voucher');
    }

    /**
     * 2. Verify vendor match
     */
    if ((int)$voucher['vendor_id'] !== $vendorId) {
        $db->rollBack();
        jsonResponse(false, null, 'Voucher not valid for this vendor');
    }

    /**
     * 3. Get ecosystem settings
     */
    $settings = getEcosystemSettings();
    $subsidy = (float)$settings['subsidy_amount'];
    $mealPrice = (float)$settings['meal_price'];

    /**
     * 4. Insert meal claim
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
        $subsidy,
        $mealPrice
    ]);

    /**
     * 5. Mark voucher as used
     */
    $stmt = $db->prepare("
        UPDATE vouchers
        SET status = 'used', used_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$voucher['id']]);

    /**
     * 6. Deduct meal from vendor
     */
    $stmt = $db->prepare("
        UPDATE vendors
        SET meals_remaining = GREATEST(meals_remaining - 1, 0)
        WHERE id = ?
    ");
    $stmt->execute([$vendorId]);

    /**
     * 7. CREDIT vendor wallet (REAL MONEY LOGIC)
     */
    $stmt = $db->prepare("
        UPDATE vendors
        SET wallet_balance = wallet_balance + ?
        WHERE id = ?
    ");
    $stmt->execute([$subsidy, $vendorId]);

    /**
     * 8. Deduct from global wallet
     */
    $stmt = $db->prepare("
        UPDATE global_wallet
        SET balance = balance - ?
        WHERE id = 1
    ");
    $stmt->execute([$subsidy]);

    /**
     * 9. Log transaction
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
            'meal_subsidy',
            ?,
            ?,
            'voucher',
            'Meal subsidy credited to vendor',
            (SELECT wallet_balance FROM vendors WHERE id = ?)
        )
    ");
    $stmt->execute([$subsidy, $voucher['id'], $vendorId]);

    $db->commit();

    /**
     * 10. SUCCESS RESPONSE
     */
    jsonResponse(true, [
        'voucher_code' => $voucherCode,
        'subsidy_credited' => $subsidy,
        'meal_price' => $mealPrice,
        'vendor_id' => $vendorId
    ], 'Meal successfully claimed');

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Meal claim error: " . $e->getMessage());
    jsonResponse(false, null, 'Transaction failed');
}