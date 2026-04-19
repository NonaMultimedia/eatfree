<?php
/**
 * Generate Voucher API
 * Creates meal vouchers for beneficiaries
 * Compatible with current EatFree schema + architecture
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !is_array($input)) {
    jsonResponse(false, null, 'Invalid input data');
}

/* =====================================================
   VALIDATION
===================================================== */

$required = ['full_name', 'id_number', 'address', 'vendor_id'];

foreach ($required as $field) {
    if (empty($input[$field])) {
        jsonResponse(false, null, "Missing required field: {$field}");
    }
}

$fullName = sanitize($input['full_name']);
$idNumber = trim($input['id_number']);
$phone    = sanitize($input['phone'] ?? '');
$address  = sanitize($input['address']);
$city     = sanitize($input['city'] ?? 'Unknown');
$province = sanitize($input['province'] ?? 'Unknown');
$vendorId = (int)$input['vendor_id'];

// South African ID validation
if (!preg_match('/^\d{13}$/', $idNumber)) {
    jsonResponse(false, null, 'Invalid ID number. Use a valid 13-digit South African ID.');
}

if ($vendorId <= 0) {
    jsonResponse(false, null, 'Invalid vendor selected');
}

try {
    $db = getDB();

    /* =====================================================
       CHECK VENDOR
    ===================================================== */

    $stmt = $db->prepare("
        SELECT id, business_name, meals_remaining, status, is_active
        FROM vendors
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        jsonResponse(false, null, 'Vendor not found');
    }

    if ($vendor['status'] !== 'approved') {
        jsonResponse(false, null, 'Vendor is not approved');
    }

    if ((int)$vendor['is_active'] !== 1) {
        jsonResponse(false, null, 'Vendor is not active');
    }

    if ((int)$vendor['meals_remaining'] <= 0) {
        jsonResponse(false, null, 'Vendor has no meals remaining');
    }

    /* =====================================================
       GET SYSTEM SETTINGS
    ===================================================== */

    $settings = getEcosystemSettings();

    $mealPrice = (float)$settings['meal_price'];
    $subsidy   = (float)$settings['subsidy_amount'];

    /* =====================================================
       BENEFICIARY UPSERT
    ===================================================== */

    $stmt = $db->prepare("
        SELECT id
        FROM beneficiaries
        WHERE id_number = ?
        LIMIT 1
    ");
    $stmt->execute([$idNumber]);
    $beneficiary = $stmt->fetch();

    if ($beneficiary) {

        $beneficiaryId = (int)$beneficiary['id'];

        $stmt = $db->prepare("
            UPDATE beneficiaries
            SET full_name = ?,
                phone = ?,
                address = ?,
                city = ?,
                province = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $fullName,
            $phone,
            $address,
            $city,
            $province,
            $beneficiaryId
        ]);

    } else {

        $stmt = $db->prepare("
            INSERT INTO beneficiaries
            (full_name, id_number, phone, address, city, province)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $fullName,
            $idNumber,
            $phone,
            $address,
            $city,
            $province
        ]);

        $beneficiaryId = (int)$db->lastInsertId();
    }

    /* =====================================================
       GENERATE UNIQUE VOUCHER CODE
    ===================================================== */

    $voucherCode = '';
    $attempts = 0;

    do {
        $voucherCode = generateVoucherCode();

        $stmt = $db->prepare("
            SELECT id
            FROM vouchers
            WHERE voucher_code = ?
            LIMIT 1
        ");
        $stmt->execute([$voucherCode]);

        $exists = $stmt->fetch();
        $attempts++;

    } while ($exists && $attempts < 10);

    if ($exists) {
        throw new Exception('Unable to generate unique voucher code');
    }

    /* =====================================================
       EXPIRY END OF DAY
    ===================================================== */

    $expiresAt = date('Y-m-d 23:59:59');

    /* =====================================================
       CREATE VOUCHER
    ===================================================== */

    $stmt = $db->prepare("
        INSERT INTO vouchers
        (
            voucher_code,
            beneficiary_id,
            vendor_id,
            amount,
            subsidy_amount,
            status,
            expires_at
        )
        VALUES (?, ?, ?, ?, ?, 'active', ?)
    ");

    $stmt->execute([
        $voucherCode,
        $beneficiaryId,
        $vendorId,
        $mealPrice,
        $subsidy,
        $expiresAt
    ]);

    $voucherId = (int)$db->lastInsertId();

    /* =====================================================
       REDUCE MEAL SLOT ONLY
       (NO MONEY MOVEMENT HERE)
    ===================================================== */

    $stmt = $db->prepare("
        UPDATE vendors
        SET meals_remaining = meals_remaining - 1
        WHERE id = ?
    ");
    $stmt->execute([$vendorId]);

    /* =====================================================
       SUCCESS RESPONSE
    ===================================================== */

    jsonResponse(true, [
        'voucher_id' => $voucherId,
        'voucher_code' => $voucherCode,
        'beneficiary_name' => $fullName,
        'vendor_name' => $vendor['business_name'],
        'amount' => $mealPrice,
        'subsidy_amount' => $subsidy,
        'expires_at' => $expiresAt,
        'meals_remaining_at_vendor' => ((int)$vendor['meals_remaining'] - 1)
    ], 'Voucher generated successfully');

} catch (Exception $e) {

    error_log('Generate voucher error: ' . $e->getMessage());

    jsonResponse(false, null, 'Failed to generate voucher. Please try again.');
}