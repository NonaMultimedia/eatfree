<?php
/**
 * root/api/vendor-subscribe.php
 * EatFree Vendor Subscription API
 *
 * FIX 6:
 * - Safe vendor subscription initiation
 * - No undefined vendor usage
 * - No broken PayFast syntax
 * - Prevents stale pending subscription lock
 * - Validates subscription settings before PayFast redirect
 * - Creates pending subscription in a transaction
 * - Stores EatFree payment reference before redirect
 * - Final activation must happen through PayFast ITN
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

if (!isVendorLoggedIn()) {
    jsonResponse(false, null, 'Please sign in to activate your EatFree membership.');
}

$vendorId = (int)($_SESSION['vendor_id'] ?? 0);

if ($vendorId <= 0) {
    jsonResponse(false, null, 'Invalid vendor session. Please sign in again.');
}

try {
    $db = getDB();

    /**
     * =========================
     * LOAD VENDOR FIRST
     * =========================
     */
    $stmt = $db->prepare("
        SELECT 
            id,
            business_name,
            email,
            status,
            subscription_status,
            subscription_expires_at
        FROM vendors
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        jsonResponse(false, null, 'Shop not found.');
    }

    /**
     * =========================
     * VENDOR APPROVAL CHECK
     * =========================
     */
    if ($vendor['status'] !== 'approved') {
        jsonResponse(false, null, 'Your shop must be approved before activating membership.');
    }

    /**
     * =========================
     * BASIC VENDOR PAYMENT DATA CHECK
     * =========================
     */
    $businessName = trim((string)($vendor['business_name'] ?? ''));
    $vendorEmail = trim((string)($vendor['email'] ?? ''));

    if ($businessName === '') {
        jsonResponse(false, null, 'Your shop profile is missing a business name.');
    }

    if ($vendorEmail === '' || !filter_var($vendorEmail, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, null, 'Your shop profile is missing a valid email address.');
    }

    /**
     * =========================
     * EXISTING ACTIVE MEMBERSHIP CHECK
     * =========================
     */
    $subscriptionExpiresAt = $vendor['subscription_expires_at'] ?? null;

    if (
        $vendor['subscription_status'] === 'active' &&
        !empty($subscriptionExpiresAt) &&
        strtotime($subscriptionExpiresAt) >= strtotime(date('Y-m-d'))
    ) {
        jsonResponse(
            false,
            null,
            'Your membership is already active until ' . $subscriptionExpiresAt . '.'
        );
    }

    /**
     * =========================
     * RELEASE STALE PENDING SUBSCRIPTIONS
     *
     * Prevents old unpaid attempts from blocking the vendor forever.
     * =========================
     */
    $stmt = $db->prepare("
        UPDATE vendor_subscriptions
        SET payment_status = 'failed'
        WHERE vendor_id = ?
        AND payment_status = 'pending'
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$vendorId]);

    /**
     * =========================
     * BLOCK DUPLICATE ACTIVE PENDING SUBSCRIPTIONS
     * =========================
     */
    $stmt = $db->prepare("
        SELECT id, created_at
        FROM vendor_subscriptions
        WHERE vendor_id = ?
        AND payment_status = 'pending'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);
    $pendingSubscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pendingSubscription) {
        jsonResponse(false, [
            'subscription_id' => (int)$pendingSubscription['id'],
            'created_at' => $pendingSubscription['created_at']
        ], 'You already have a pending membership payment. Please complete it first or try again after 24 hours.');
    }

    /**
     * =========================
     * SETTINGS
     * =========================
     */
    $settings = getEcosystemSettings();

    $subscriptionAmount = isset($settings['vendor_subscription_amount'])
        ? (float)$settings['vendor_subscription_amount']
        : (defined('VENDOR_SUBSCRIPTION') ? (float)VENDOR_SUBSCRIPTION : 99.00);

    $taxPercent = isset($settings['vendor_subscription_tax'])
        ? (float)$settings['vendor_subscription_tax']
        : (defined('VENDOR_SUBSCRIPTION_TAX') ? (float)VENDOR_SUBSCRIPTION_TAX : 15.00);

    $mealsIncluded = isset($settings['meals_per_vendor'])
        ? (int)$settings['meals_per_vendor']
        : (defined('MEALS_PER_VENDOR') ? (int)MEALS_PER_VENDOR : 50);

    /**
     * =========================
     * DEFENSIVE SETTINGS VALIDATION
     * =========================
     */
    if ($subscriptionAmount <= 0) {
        $subscriptionAmount = defined('VENDOR_SUBSCRIPTION') ? (float)VENDOR_SUBSCRIPTION : 99.00;
    }

    if ($taxPercent < 0) {
        $taxPercent = defined('VENDOR_SUBSCRIPTION_TAX') ? (float)VENDOR_SUBSCRIPTION_TAX : 15.00;
    }

    if ($mealsIncluded <= 0) {
        $mealsIncluded = defined('MEALS_PER_VENDOR') ? (int)MEALS_PER_VENDOR : 50;
    }

    $taxAmount = round($subscriptionAmount * ($taxPercent / 100), 2);
    $totalAmount = round($subscriptionAmount + $taxAmount, 2);

    if ($totalAmount <= 0) {
        jsonResponse(false, null, 'Membership amount is invalid. Please contact EatFree support.');
    }

    /**
     * =========================
     * CREATE PENDING SUBSCRIPTION
     * =========================
     */
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO vendor_subscriptions (
            vendor_id,
            amount,
            tax_amount,
            total_amount,
            meals_included,
            payment_status,
            subscription_start,
            subscription_end,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', NULL, NULL, NOW())
    ");
    $stmt->execute([
        $vendorId,
        $subscriptionAmount,
        $taxAmount,
        $totalAmount,
        $mealsIncluded
    ]);

    $subscriptionId = (int)$db->lastInsertId();

    /**
     * =========================
     * PAYFAST PAYMENT REFERENCE
     * =========================
     */
    $paymentReference = 'EF-SUB-' . $subscriptionId . '-' . time();

    /**
     * Store the EatFree payment reference while pending.
     * PayFast ITN may later overwrite this with the PayFast payment ID,
     * based on the current payfast-itn.php logic.
     */
    $stmt = $db->prepare("
        UPDATE vendor_subscriptions
        SET payfast_m_payment_id = ?
        WHERE id = ?
        AND vendor_id = ?
        AND payment_status = 'pending'
    ");
    $stmt->execute([
        $paymentReference,
        $subscriptionId,
        $vendorId
    ]);

    $db->commit();

    /**
     * =========================
     * PAYFAST URLS
     *
     * Return URL only shows user-facing confirmation.
     * Real activation must come from PayFast ITN.
     * =========================
     */
    $returnUrl = SITE_URL . '/payment-success.php?subscription_id=' . $subscriptionId;
    $cancelUrl = SITE_URL . '/payment-cancel.php?subscription_id=' . $subscriptionId;
    $notifyUrl = PAYFAST_NOTIFY_URL;

    /**
     * =========================
     * PAYFAST DATA
     * =========================
     */
    $payfastData = [
        'merchant_id' => PAYFAST_MERCHANT_ID,
        'merchant_key' => PAYFAST_MERCHANT_KEY,
        'return_url' => $returnUrl,
        'cancel_url' => $cancelUrl,
        'notify_url' => $notifyUrl,

        'name_first' => substr($businessName, 0, 100),
        'email_address' => $vendorEmail,

        'm_payment_id' => $paymentReference,
        'amount' => number_format($totalAmount, 2, '.', ''),
        'item_name' => 'EatFree Vendor Membership',
        'item_description' => 'EatFree vendor membership including ' . $mealsIncluded . ' meals',

        'custom_str1' => (string)$subscriptionId,
        'custom_str2' => 'subscription',
        'custom_str3' => (string)$vendorId
    ];

    /**
     * =========================
     * SIGNATURE
     * =========================
     */
    $payfastData['signature'] = generatePayFastSignature($payfastData, PAYFAST_PASSPHRASE);

    /**
     * =========================
     * BUILD PAYFAST URL
     * =========================
     */
    $payfastUrl = PAYFAST_PROCESS_URL . '?' . http_build_query($payfastData);

    jsonResponse(true, [
        'subscription_id' => $subscriptionId,
        'payment_reference' => $paymentReference,
        'amount' => $subscriptionAmount,
        'tax_amount' => $taxAmount,
        'total_amount' => $totalAmount,
        'meals_included' => $mealsIncluded,
        'payfast_url' => $payfastUrl
    ], 'Membership payment started. Redirecting to PayFast...');

} catch (Exception $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('vendor-subscribe.php error: ' . $e->getMessage());

    jsonResponse(false, null, 'Failed to start membership payment. Please try again.');
}