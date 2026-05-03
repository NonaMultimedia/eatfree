<?php
/**
 * root/api/approve-vendor.php
 * EatFree Admin Vendor Document/Application Approval API
 *
 * FIX 7 — SECTION 4:
 * - Admin approval means documents/application have been reviewed.
 * - Admin approval DOES NOT mean payment has been made.
 * - Admin approval sets:
 *   vendors.status = approved
 *   vendors.kyc_verified = 1
 *   vendor_queue.status = approved
 *
 * PayFast ITN remains responsible for:
 * - vendor_subscriptions.payment_status = paid
 * - vendors.payment_verified = 1
 * - vendors.subscription_status = active
 * - vendors.subscription_expires_at
 */

require_once __DIR__ . '/../config/config.php';

requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

$data = json_decode(file_get_contents('php://input'), true);

$vendorId = isset($data['vendor_id']) ? (int)$data['vendor_id'] : 0;

if ($vendorId <= 0) {
    jsonResponse(false, null, 'Invalid vendor');
}

try {
    $db = getDB();

    $db->beginTransaction();

    /**
     * =========================
     * LOAD VENDOR
     * =========================
     */
    $stmt = $db->prepare("
        SELECT
            id,
            business_name,
            email,
            status,
            logo_path,
            documents_path,
            kyc_verified,
            payment_verified,
            subscription_status
        FROM vendors
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        $db->rollBack();
        jsonResponse(false, null, 'Vendor not found');
    }

    if ($vendor['status'] === 'approved' && (int)$vendor['kyc_verified'] === 1) {
        $db->rollBack();
        jsonResponse(false, null, 'Vendor documents are already approved.');
    }

    if ($vendor['status'] === 'rejected') {
        $db->rollBack();
        jsonResponse(false, null, 'Rejected vendors cannot be approved without first being reset to pending.');
    }

    if (!in_array($vendor['status'], ['pending', 'inactive'], true)) {
        $db->rollBack();
        jsonResponse(false, null, 'Only pending vendors can be document-approved.');
    }

    /**
     * =========================
     * DOCUMENT CHECK
     *
     * Logo is useful for review, but business documents are the hard requirement.
     * =========================
     */
    if (empty($vendor['documents_path'])) {
        $db->rollBack();
        jsonResponse(false, null, 'Cannot approve vendor without uploaded business documents.');
    }

    /**
     * =========================
     * ADMIN DOCUMENT APPROVAL ONLY
     *
     * Do NOT touch:
     * - payment_verified
     * - subscription_status
     * - subscription_expires_at
     *
     * Those belong to PayFast ITN.
     * =========================
     */
    $stmt = $db->prepare("
        UPDATE vendors
        SET
            status = 'approved',
            kyc_verified = 1
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);

    /**
     * =========================
     * UPDATE OR CREATE QUEUE RECORD
     * =========================
     */
    $stmt = $db->prepare("
        SELECT id
        FROM vendor_queue
        WHERE vendor_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$vendorId]);
    $queueRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($queueRow) {
        $stmt = $db->prepare("
            UPDATE vendor_queue
            SET status = 'approved'
            WHERE vendor_id = ?
        ");
        $stmt->execute([$vendorId]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO vendor_queue (
                vendor_id,
                queue_position,
                status
            ) VALUES (?, 0, 'approved')
        ");
        $stmt->execute([$vendorId]);
    }

    /**
     * =========================
     * REORDER WAITING QUEUE
     * =========================
     */
    $stmt = $db->query("
        SELECT id
        FROM vendor_queue
        WHERE status = 'waiting'
        ORDER BY queue_position ASC, id ASC
    ");
    $waitingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $position = 1;

    foreach ($waitingRows as $row) {
        $stmt = $db->prepare("
            UPDATE vendor_queue
            SET queue_position = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $position,
            $row['id']
        ]);

        $position++;
    }

    if (function_exists('logActivity')) {
        logActivity(
            'vendor_documents_approved',
            'Vendor documents approved: ' . $vendor['business_name'],
            $vendorId,
            'vendor'
        );
    }

    $db->commit();

    jsonResponse(true, [
        'vendor_id' => $vendorId,
        'business_name' => $vendor['business_name'],
        'status' => 'approved',
        'kyc_verified' => 1,
        'payment_verified' => (int)($vendor['payment_verified'] ?? 0),
        'subscription_status' => $vendor['subscription_status'] ?? 'none'
    ], 'Vendor documents approved. Vendor can now activate membership payment.');

} catch (Exception $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('approve-vendor.php error: ' . $e->getMessage());

    jsonResponse(false, null, 'Vendor document approval failed. Please try again.');
}