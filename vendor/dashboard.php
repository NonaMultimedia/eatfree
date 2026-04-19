<?php
/**
 * Vendor Dashboard (FIXED SESSION SAFE VERSION)
 * Ensures consistent login session handling
 */

require_once __DIR__ . '/../config/config.php';

startSession();

/**
 * SECURITY CHECK
 * Must be logged in as vendor
 */
if (!isset($_SESSION['vendor_id']) || $_SESSION['user_type'] !== 'vendor') {
    header("Location: /login.html");
    exit;
}

try {
    $db = getDB();

    /**
     * Get vendor data (ALWAYS fresh from DB)
     */
    $stmt = $db->prepare("
        SELECT *
        FROM vendors
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['vendor_id']]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        session_destroy();
        header("Location: /login.html");
        exit;
    }

    /**
     * Get voucher stats
     */
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_vouchers
        FROM vouchers
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor['id']]);
    $vouchers = $stmt->fetch();

    /**
     * Get meal claims
     */
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_claims
        FROM meal_claims
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor['id']]);
    $claims = $stmt->fetch();
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(subsidy_amount), 0) as total_subsidy
        FROM meal_claims
        WHERE vendor_id = ?");
        
    $stmt->execute([$vendor['id']]);
    $subsidy = $stmt->fetch();
    
    $totalSubsidyEarned = (float)$subsidy['total_subsidy'];
    
    /**
     * Pending withdrawal (FIXED SAFE DEFAULT)
     */
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as pending_withdrawal
        FROM vendor_withdrawals
        WHERE vendor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$vendor['id']]);
    $withdrawal = $stmt->fetch();
    $pendingWithdrawal = $withdrawal['pending_withdrawal'] ?? 0;

} catch (Exception $e) {
    error_log("Vendor dashboard error: " . $e->getMessage());
    $vendor = null;
    $vouchers = ['total_vouchers' => 0];
    $claims = ['total_claims' => 0];
    $pendingWithdrawal = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Dashboard - EatFree</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .stat-box {
            padding: 20px;
            border-radius: 12px;
            background: white;
            text-align: center;
        }
        .big-number {
            font-size: 28px;
            font-weight: bold;
            color: #6db049;
        }
    </style>
</head>

<body>

<div class="container py-4">

    <!-- Header -->
    <div class="mb-4">
        <h2>Welcome, <?php echo htmlspecialchars($vendor['business_name']); ?></h2>
        <p class="text-muted">Vendor Dashboard</p>
    </div>

    <!-- Stats -->
    <div class="row g-3">

        <div class="col-md-3">
            <div class="stat-box">
                <div class="big-number">
                    <?php echo (int)$vendor['meals_remaining']; ?>
                </div>
                <div>Meals Remaining</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-box">
                <div class="big-number">
                    <?php echo (int)$claims['total_claims']; ?>
                </div>
                <div>Total Meals Served</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-box">
                <div class="big-number">
                    R <?php echo number_format($vendor['wallet_balance'], 2); ?>
                </div>
                <div>Wallet Balance</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-box">
                <div class="big-number">
                    R <?php echo number_format($pendingWithdrawal, 2); ?>
                </div>
                <div>Pending Withdrawal</div>
            </div>
        </div>

    </div>

    <!-- Status -->
    <div class="mt-4 card p-3">
        <h5>Account Status</h5>
        <p>
            Status:
            <strong><?php echo ucfirst($vendor['status']); ?></strong>
        </p>

        <p>
            Subscription:
            <strong><?php echo ucfirst($vendor['subscription_status']); ?></strong>
        </p>
    </div>

    <!-- Actions -->
    <div class="mt-4">
        <a href="/vendor/logout.php" class="btn btn-dark">Logout</a>
    </div>

</div>

</body>
</html>