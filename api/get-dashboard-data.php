<?php
/**
 * root/api/get-dashboard-data.php
 * EatFree Dashboard Data API
 *
 * HOMEPAGE COUNTER FIX:
 * - donations.total = historical completed donations
 * - wallet.balance = current spendable subsidy pool
 * - milestone uses wallet.balance, not raw donations total
 * - meals served counts verified/used meals only
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, 'Invalid request method');
}

$type = $_GET['type'] ?? 'public';

try {

    $db = getDB();

    /**
     * ==============================
     * ECOSYSTEM SETTINGS
     * ==============================
     */
    $settings = getEcosystemSettings();

    $targetThreshold = isset($settings['target_threshold'])
        ? (float)$settings['target_threshold']
        : (defined('TARGET_THRESHOLD') ? (float)TARGET_THRESHOLD : 15000.00);

    $subsidyAmount = isset($settings['subsidy_amount'])
        ? (float)$settings['subsidy_amount']
        : (defined('SUBSIDY_AMOUNT') ? (float)SUBSIDY_AMOUNT : 5.00);

    /**
     * ==============================
     * GLOBAL WALLET
     *
     * This is the current economic truth.
     * Fix 5 debits this when vouchers are redeemed.
     * ==============================
     */
    $stmt = $db->query("
        SELECT 
            id,
            balance,
            total_received,
            total_distributed,
            meals_funded
        FROM global_wallet
        ORDER BY id ASC
        LIMIT 1
    ");
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {

        /**
         * If wallet row does not exist, safely seed it from completed donations.
         */
        $stmt = $db->query("
            SELECT COALESCE(SUM(amount), 0) AS completed_total
            FROM donations
            WHERE status = 'completed'
        ");
        $completedTotal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['completed_total'];

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
            $completedTotal,
            $completedTotal
        ]);

        $wallet = [
            'id' => (int)$db->lastInsertId(),
            'balance' => $completedTotal,
            'total_received' => $completedTotal,
            'total_distributed' => 0,
            'meals_funded' => 0
        ];
    }

    /**
     * ==============================
     * DONATIONS
     *
     * Historical money received.
     * This should NOT decrement when meals are redeemed.
     * ==============================
     */
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(amount), 0) AS total,
            COUNT(*) AS count
        FROM donations
        WHERE status = 'completed'
    ");
    $donations = $stmt->fetch(PDO::FETCH_ASSOC);

    /**
     * ==============================
     * VENDORS
     * ==============================
     */
    $stmt = $db->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending
        FROM vendors
    ");
    $vendors = $stmt->fetch(PDO::FETCH_ASSOC);

    /**
     * ==============================
     * MEALS SERVED
     *
     * Count only actually verified/used meal claims.
     * Pending claims are not served yet.
     * ==============================
     */
    $stmt = $db->query("
        SELECT COUNT(*) AS total
        FROM meal_claims
        WHERE is_used = 1
        OR status = 'verified'
    ");
    $meals = $stmt->fetch(PDO::FETCH_ASSOC);

    /**
     * ==============================
     * CURRENT WALLET BALANCE
     *
     * This is what the homepage must show as Current Belly.
     * ==============================
     */
    $walletBalance = (float)$wallet['balance'];
    $totalReceived = (float)$wallet['total_received'];
    $totalDistributed = (float)$wallet['total_distributed'];
    $mealsFunded = (int)$wallet['meals_funded'];

    /**
     * ==============================
     * MILESTONE CALCULATION
     * ==============================
     */
    $percentComplete = 0;

    if ($targetThreshold > 0) {
        $percentComplete = min(100, round(($walletBalance / $targetThreshold) * 100, 2));
    }

    $remainingToTarget = max(0, $targetThreshold - $walletBalance);

    $mealsUntilFree = 0;

    if ($subsidyAmount > 0) {
        $mealsUntilFree = (int)ceil($remainingToTarget / $subsidyAmount);
    }

    $currentMode = ($walletBalance >= $targetThreshold)
        ? 'full_free'
        : 'subsidized';

    /**
     * ==============================
     * CLEAN RESPONSE
     * ==============================
     */
    $response = [
        'settings' => [
            'current_mode' => $currentMode
        ],

        'wallet' => [
            /**
             * Current spendable pool after meal subsidies.
             */
            'balance' => $walletBalance,

            /**
             * Historical received amount.
             * Useful for admin/history, not the live belly.
             */
            'total_received' => $totalReceived,

            /**
             * Amount already spent on meal subsidies.
             */
            'total_distributed' => $totalDistributed,

            /**
             * Number of meals funded from global wallet.
             */
            'meals_funded' => $mealsFunded
        ],

        'vendors' => [
            'active' => (int)($vendors['active'] ?? 0),
            'pending' => (int)($vendors['pending'] ?? 0),
            'total' => (int)($vendors['total'] ?? 0)
        ],

        'donations' => [
            /**
             * Historical donations. This should not decrement.
             */
            'total' => (float)$donations['total'],
            'count' => (int)$donations['count']
        ],

        'meals' => [
            'total' => (int)$meals['total']
        ],

        'milestone' => [
            /**
             * Homepage counter should use this.
             */
            'wallet_balance' => $walletBalance,
            'meals_until_free' => $mealsUntilFree,
            'percent_complete' => $percentComplete,
            'target_threshold' => $targetThreshold
        ]
    ];

    /**
     * ==============================
     * ADMIN EXTENSION
     * ==============================
     */
    if ($type === 'admin' && isAdminLoggedIn()) {

        $stmt = $db->query("
            SELECT 
                COUNT(*) AS pending_count,
                COALESCE(SUM(amount), 0) AS pending_total
            FROM vendor_withdrawals
            WHERE status = 'pending'
        ");
        $response['withdrawals'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->query("
            SELECT *
            FROM wallet_transactions
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $response['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->query("
            SELECT vq.*, v.business_name
            FROM vendor_queue vq
            JOIN vendors v ON vq.vendor_id = v.id
            WHERE vq.status = 'waiting'
            ORDER BY vq.queue_position ASC
        ");
        $response['vendor_queue'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    jsonResponse(true, $response, 'Dashboard data retrieved successfully');

} catch (Exception $e) {

    error_log("get-dashboard-data.php error: " . $e->getMessage());

    jsonResponse(false, [
        'error' => $e->getMessage()
    ], 'Dashboard data failed');
}