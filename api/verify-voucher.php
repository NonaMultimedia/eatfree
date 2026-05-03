<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/wallet.service.php';
require_once __DIR__ . '/../core/vendor.service.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

//Define Membership Logic
if ($vendor['status'] !== 'approved' || $vendor['subscription_status'] !== 'active') {
    die("Shop not fully activated.");
}

$data = json_decode(file_get_contents("php://input"), true);

$voucherCode = trim($data['voucher_code'] ?? '');
$vendorId    = (int)($data['vendor_id'] ?? 0);

if (!$voucherCode) {
    jsonResponse(false, null, 'Voucher code required');
}

if ($vendorId <= 0) {
    jsonResponse(false, null, 'Vendor required');
}

try {
    $pdo = DB::conn();
    $pdo->beginTransaction();

    /**
     * 1. Lock voucher row (prevents double spend)
     */
    $stmt = $pdo->prepare("
        SELECT v.*, b.full_name, ven.business_name
        FROM vouchers v
        INNER JOIN beneficiaries b ON b.id = v.beneficiary_id
        INNER JOIN vendors ven ON ven.id = v.vendor_id
        WHERE v.voucher_code = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$voucherCode]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        throw new Exception("Invalid voucher");
    }

    /**
     * 2. Status checks
     */
    if ($voucher['status'] !== 'pending') {
        throw new Exception("Voucher already used or invalid");
    }

    /**
     * 3. Expiry check
     */
    if (strtotime($voucher['expires_at']) < time()) {
        $update = $pdo->prepare("
            UPDATE vouchers SET status='expired' WHERE id=?
        ");
        $update->execute([$voucher['id']]);

        throw new Exception("Voucher expired");
    }

    /**
     * 4. Vendor match check
     */
    if ((int)$voucher['vendor_id'] !== $vendorId) {
        throw new Exception("Invalid vendor for this voucher");
    }

    /**
     * 5. Vendor must be active
     */
    $stmt = $pdo->prepare("
        SELECT id FROM vendors 
        WHERE id=? AND status='approved' AND is_active=1
    ");
    $stmt->execute([$vendorId]);

    if (!$stmt->fetch()) {
        throw new Exception("Vendor not active");
    }

    $amount = (float)$voucher['subsidy_amount']; // USE REAL VALUE

    /**
     * 6. Deduct from donations / global wallet
     */
    WalletService::deductForMeal($amount);

    /**
     * 7. Credit vendor earnings
     */
    VendorService::addEarnings($vendorId, $amount);

    /**
     * 8. Mark voucher used
     */
    $stmt = $pdo->prepare("
        UPDATE vouchers 
        SET status='used', used_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([$voucher['id']]);

    /**
     * 9. Insert meal claim
     */
    $stmt = $pdo->prepare("
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
        $amount,
        $voucher['amount']
    ]);

    /**
     * 10. Update beneficiary stats
     */
    $stmt = $pdo->prepare("
        UPDATE beneficiaries
        SET total_meals_claimed = total_meals_claimed + 1,
            last_claim_date = CURDATE()
        WHERE id = ?
    ");
    $stmt->execute([$voucher['beneficiary_id']]);

    /**
     * 11. Update global stats (THIS feeds homepage)
     */
    $stmt = $pdo->prepare("
        UPDATE global_wallet
        SET meals_funded = meals_funded + 1,
            total_distributed = total_distributed + ?
        WHERE id = 1
    ");
    $stmt->execute([$amount]);

    /**
     * 12. Log transaction
     */
    WalletService::logTransaction(
        'meal_claim',
        $amount,
        $voucher['id'],
        'voucher',
        'Voucher claimed: ' . $voucherCode
    );

    $pdo->commit();

    jsonResponse(true, [
        'voucher_code'     => $voucherCode,
        'beneficiary_name' => $voucher['full_name'],
        'vendor_name'      => $voucher['business_name'],
        'amount'           => $amount
    ], 'Meal successfully claimed');

} catch (Exception $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("VERIFY ERROR: " . $e->getMessage());

    jsonResponse(false, null, $e->getMessage());
}