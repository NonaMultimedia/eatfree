<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();

header('Content-Type: application/json');

$db = getDB();

$data = json_decode(file_get_contents("php://input"), true);

$withdrawalId = $data['withdrawal_id'] ?? null;
$action = $data['action'] ?? null;
$adminId = $_SESSION['admin_id'] ?? 0;

if (!$withdrawalId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {

    $db->beginTransaction();

    // LOCK ROW
    $stmt = $db->prepare("
        SELECT * FROM vendor_withdrawals
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch();

    if (!$withdrawal) {
        throw new Exception("Withdrawal not found");
    }

    // prevent double processing
    if ($withdrawal['status'] !== 'pending') {
        throw new Exception("Withdrawal already processed");
    }

    if ($withdrawal['is_locked']) {
        throw new Exception("Transaction already in progress");
    }

    // lock transaction
    $stmt = $db->prepare("
        UPDATE vendor_withdrawals
        SET is_locked = 1,
            locked_at = NOW(),
            status = 'processing'
        WHERE id = ?
    ");
    $stmt->execute([$withdrawalId]);

    /* -------------------------------------------------
       REJECT FLOW
    ------------------------------------------------- */
    if ($action === 'reject') {

        $stmt = $db->prepare("
            UPDATE vendor_withdrawals
            SET status = 'rejected',
                processed_by = ?,
                paid_at = NOW(),
                is_locked = 0
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $withdrawalId]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal rejected'
        ]);
        exit;
    }

    /* -------------------------------------------------
       APPROVE FLOW
    ------------------------------------------------- */
    if ($action === 'approve') {

        // GET VENDOR
        $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$withdrawal['vendor_id']]);
        $vendor = $stmt->fetch();

        if (!$vendor) {
            throw new Exception("Vendor not found");
        }

        if ($vendor['wallet_balance'] < $withdrawal['amount']) {
            throw new Exception("Insufficient vendor balance");
        }

        $reference = "EF-PAYOUT-" . date("Ymd") . "-" . $withdrawalId;

        // deduct wallet
        $stmt = $db->prepare("
            UPDATE vendors 
            SET wallet_balance = wallet_balance - ?
            WHERE id = ?
        ");
        $stmt->execute([$withdrawal['amount'], $vendor['id']]);

        // payout engine hook
        $payoutResult = processPayout($vendor, $withdrawal, $reference);

        if (!$payoutResult['success']) {
            throw new Exception("Payout failed");
        }

        // mark as PAID (matches schema)
        $stmt = $db->prepare("
            UPDATE vendor_withdrawals
            SET status = 'paid',
                payment_reference = ?,
                paid_at = NOW(),
                processed_by = ?,
                is_locked = 0
            WHERE id = ?
        ");
        $stmt->execute([$reference, $adminId, $withdrawalId]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Payout completed',
            'method' => $payoutResult['method'] ?? 'bank',
            'reference' => $reference
        ]);
        exit;
    }

    throw new Exception("Invalid action");

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}