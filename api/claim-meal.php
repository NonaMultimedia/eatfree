<?php
/**
 * root/api/claim-meal.php
 * EatFree Claim Meal API
 *
 * FIX 1B:
 * Compatible with current EatFree DB schema.
 *
 * Creates:
 * - beneficiary
 * - active voucher
 * - ONE pending meal_claim record using voucher_code
 *
 * Does NOT redeem the voucher.
 * Vendor redemption will update this claim later.
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

try {

    $db = getDB();

    /**
     * ======================
     * INPUT
     * ======================
     */
    $vendorId = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
    $name     = trim($_POST['name'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');

    if ($vendorId <= 0) {
        jsonResponse(false, null, 'Invalid vendor QR code');
    }

    if ($name === '' || $idNumber === '' || $address === '') {
        jsonResponse(false, null, 'Please complete all required fields');
    }

    /**
     * ======================
     * VALIDATE VENDOR
     * ======================
     */
    $stmt = $db->prepare("
        SELECT 
            id,
            business_name,
            status,
            subscription_status,
            meals_remaining,
            city,
            province
        FROM vendors
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        jsonResponse(false, null, 'Vendor not found');
    }

    if ($vendor['status'] !== 'approved') {
        jsonResponse(false, null, 'This vendor is not approved yet');
    }

    if (($vendor['subscription_status'] ?? '') !== 'active') {
        jsonResponse(false, null, 'This vendor membership is not active');
    }

    /**
     * NOTE:
     * Some approved test vendors currently have meals_remaining = 0.
     * For now, we do NOT block claim creation here.
     * The vendor redemption step can enforce final availability later.
     */

    /**
     * ======================
     * DUPLICATE CLAIM CHECK
     * Same ID number cannot claim again at the same vendor on the same day.
     * ======================
     */
    $stmt = $db->prepare("
        SELECT mc.id
        FROM meal_claims mc
        INNER JOIN beneficiaries b ON mc.beneficiary_id = b.id
        WHERE mc.vendor_id = ?
        AND b.id_number = ?
        AND DATE(mc.claimed_at) = CURDATE()
        LIMIT 1
    ");
    $stmt->execute([$vendorId, $idNumber]);

    if ($stmt->fetch()) {
        jsonResponse(false, null, 'You have already claimed a meal from this vendor today');
    }

    /**
     * ======================
     * BEGIN TRANSACTION
     * ======================
     */
    $db->beginTransaction();

    /**
     * ======================
     * REUSE OR CREATE BENEFICIARY
     * Your beneficiaries table requires city + province.
     * Since the claim form does not collect those yet, we use vendor city/province for now.
     * ======================
     */
    $beneficiaryCity = $vendor['city'] ?: 'Unknown';
    $beneficiaryProvince = $vendor['province'] ?: 'Unknown';

    $stmt = $db->prepare("
        SELECT id
        FROM beneficiaries
        WHERE id_number = ?
        LIMIT 1
    ");
    $stmt->execute([$idNumber]);
    $beneficiary = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($beneficiary) {

        $beneficiaryId = (int)$beneficiary['id'];

        $stmt = $db->prepare("
            UPDATE beneficiaries
            SET 
                full_name = ?,
                phone = ?,
                address = ?,
                city = ?,
                province = ?,
                claim_count = claim_count + 1,
                last_claim_date = CURDATE()
            WHERE id = ?
        ");
        $stmt->execute([
            $name,
            $phone,
            $address,
            $beneficiaryCity,
            $beneficiaryProvince,
            $beneficiaryId
        ]);

    } else {

        $stmt = $db->prepare("
            INSERT INTO beneficiaries (
                full_name,
                id_number,
                phone,
                address,
                city,
                province,
                claim_count,
                total_meals_claimed,
                last_claim_date,
                is_active,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, 0, CURDATE(), 1, NOW())
        ");
        $stmt->execute([
            $name,
            $idNumber,
            $phone,
            $address,
            $beneficiaryCity,
            $beneficiaryProvince
        ]);

        $beneficiaryId = (int)$db->lastInsertId();
    }

    /**
     * ======================
     * GENERATE UNIQUE VOUCHER CODE
     * ======================
     */
    $voucherCode = 'EF' . strtoupper(bin2hex(random_bytes(5)));

    /**
     * ======================
     * CREATE ACTIVE VOUCHER
     * ======================
     */
    $mealPrice = defined('MEAL_PRICE') ? (float)MEAL_PRICE : 20.00;
    $subsidyAmount = defined('SUBSIDY_AMOUNT') ? (float)SUBSIDY_AMOUNT : 5.00;

    $stmt = $db->prepare("
        INSERT INTO vouchers (
            voucher_code,
            beneficiary_id,
            vendor_id,
            amount,
            subsidy_amount,
            status,
            expires_at,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())
    ");
    $stmt->execute([
        $voucherCode,
        $beneficiaryId,
        $vendorId,
        $mealPrice,
        $subsidyAmount
    ]);

    /**
     * ======================
     * CREATE ONE PENDING MEAL CLAIM
     *
     * IMPORTANT:
     * Your current meal_claims table does NOT have voucher_id.
     * So we connect using voucher_code.
     * ======================
     */
    $qrPayload = json_encode([
        'id_number' => $idNumber,
        'address'   => $address,
        'name'      => $name,
        'phone'     => $phone,
        'source'    => 'claim_form'
    ]);

    $stmt = $db->prepare("
        INSERT INTO meal_claims (
            voucher_code,
            qr_code,
            beneficiary_id,
            name,
            phone,
            vendor_id,
            subsidy_amount,
            meal_price,
            status,
            claimed_at,
            qr_source,
            expires_at,
            is_used,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), 'flyer', DATE_ADD(NOW(), INTERVAL 1 DAY), 0, NOW())
    ");
    $stmt->execute([
        $voucherCode,
        $qrPayload,
        $beneficiaryId,
        $name,
        $phone,
        $vendorId,
        $subsidyAmount,
        $mealPrice
    ]);

    $claimId = (int)$db->lastInsertId();

    /**
     * ======================
     * COMMIT
     * ======================
     */
    $db->commit();

    jsonResponse(true, [
        'claim_id'     => $claimId,
        'voucher_code' => $voucherCode,
        'vendor_name'  => $vendor['business_name'],
        'status'       => 'pending'
    ], 'Voucher created successfully');

} catch (Exception $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("claim-meal.php error: " . $e->getMessage());

    jsonResponse(false, null, 'Failed to create voucher. Please try again.');
}