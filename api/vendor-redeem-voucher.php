<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

startSession();

/**
 * =========================
 * AUTH CHECK
 * =========================
 */
if (!isset($_SESSION['vendor_id']) || $_SESSION['user_type'] !== 'vendor') {
    jsonResponse(false, null, 'Unauthorized');
}

/**
 * =========================
 * INPUT
 * =========================
 */
$voucherId = $_POST['voucher_id'] ?? null;

if (!$voucherId) {
    jsonResponse(false, null, 'Voucher ID required');
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
     * FETCH VOUCHER
     * =========================
     */
    $stmt = $db->prepare("
        SELECT *
        FROM vouchers
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$voucherId]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        $db->rollBack();
        jsonResponse(false, null, 'Voucher not found');
    }

    /**
     * =========================
     * SECURITY CHECK
     * =========================
     */
    if ($voucher['vendor_id'] != $_SESSION['vendor_id']) {
        $db->rollBack();
        jsonResponse(false, null, 'Invalid vendor access');
    }

    /**
     * =========================
     * STATUS CHECK
     * =========================
     */
    if ($voucher['status'] !== 'active') {
        $db->rollBack();
        jsonResponse(false, null, 'Voucher already used');
    }

    /**
     * =========================
     * EXPIRY CHECK
     * =========================
     */
    if (!empty($voucher['expires_at']) && strtotime($voucher['expires_at']) < time()) {

        $stmt = $db->prepare("
            UPDATE vouchers
            SET status = 'expired'
            WHERE id = ?
        ");
        $stmt->execute([$voucherId]);

        $db->commit();

        jsonResponse(false, null, 'Voucher expired');
    }

    /**
     * =========================
     * MARK VOUCHER USED
     * =========================
     */
    $stmt = $db->prepare("
        UPDATE vouchers
        SET status = 'used',
            used_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$voucherId]);

    /**
     * =========================
     * INSERT CLAIM RECORD
     * =========================
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
        $voucher['vendor_id'],
        $_SESSION['vendor_id'],
        SUBSIDY_AMOUNT,
        MEAL_PRICE
    ]);

    /**
     * =========================
     * DEDUCT MEAL ONLY NOW
     * =========================
     */
    $stmt = $db->prepare("
        UPDATE vendors
        SET meals_remaining = CASE
            WHEN meals_remaining > 0 THEN meals_remaining - 1
            ELSE 0
        END
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['vendor_id']]);

    /**
     * =========================
     * COMMIT
     * =========================
     */
    $db->commit();

    jsonResponse(true, null, 'Voucher redeemed successfully');

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Redeem error: " . $e->getMessage());

    jsonResponse(false, null, 'Server error');
}
?>
