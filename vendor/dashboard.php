<?php
/**
 * root/vendor/dashboard.php
 * EatFree Vendor Dashboard (FINAL + WITHDRAWALS)
 * Voucher terminal + R200 withdrawal system
 */

require_once __DIR__ . '/../config/config.php';

startSession();

/**
 * SECURITY CHECK
 */
if (!isset($_SESSION['vendor_id']) || $_SESSION['user_type'] !== 'vendor') {
    header("Location: /login.html");
    exit;
}

try {

    $db = getDB();

    /**
     * LOAD VENDOR
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
     * ACTIVE VOUCHERS
     */
    $stmt = $db->prepare("
        SELECT 
            v.id,
            v.voucher_code,
            v.created_at,
            v.expires_at,
            b.full_name AS beneficiary_name,
            b.id_number AS beneficiary_id_number
        FROM vouchers v
        INNER JOIN beneficiaries b ON v.beneficiary_id = b.id
        WHERE v.vendor_id = ?
        AND v.status = 'active'
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$vendor['id']]);
    $activeVouchers = $stmt->fetchAll();

    /**
     * CLAIMS
     */
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total_claims
        FROM meal_claims
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor['id']]);
    $claims = $stmt->fetch();

    /**
     * SUBSIDY
     */
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(subsidy_amount),0) AS total_subsidy
        FROM meal_claims
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor['id']]);
    $subsidy = $stmt->fetch();

    $totalSubsidyEarned = (float)$subsidy['total_subsidy'];

    /**
     * PENDING WITHDRAWALS
     */
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount),0) total_pending
        FROM vendor_withdrawals
        WHERE vendor_id = ?
        AND status = 'pending'
    ");
    $stmt->execute([$vendor['id']]);
    $pending = $stmt->fetch();

    $pendingWithdrawal = (float)$pending['total_pending'];

    /**
     * TOTAL EARNINGS
     */
    $earnings = (float)$vendor['wallet_balance'] + $totalSubsidyEarned;

    /**
     * CAN WITHDRAW
     */
    $canWithdraw = $earnings >= 200;

} catch (Exception $e) {

    error_log("Vendor dashboard error: ".$e->getMessage());

    $vendor = [
        'business_name' => 'Vendor',
        'meals_remaining' => 0,
        'wallet_balance' => 0
    ];

    $claims = ['total_claims' => 0];
    $totalSubsidyEarned = 0;
    $activeVouchers = [];
    $earnings = 0;
    $pendingWithdrawal = 0;
    $canWithdraw = false;
}
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Vendor Dashboard - EatFree</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<link rel="stylesheet" href="/assets/css/Montserrat.css">
<link rel="stylesheet" href="/assets/css/Cooper%20Black%20Regular.css">

<style>

body{
    background:#f5f8f3;
    font-family:'Montserrat', Arial, sans-serif;
}

.brand-title{
    font-family:'Cooper Black Regular', serif;
    color:#6db049;
}

.card{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.06);
}

.stat-box{
    background:#fff;
    padding:18px;
    border-radius:16px;
    text-align:center;
}

.big-number{
    font-size:26px;
    font-weight:700;
    color:#6db049;
}

.btn-main{
    background:#6db049;
    color:#fff;
    border:none;
}

.btn-main:hover{
    background:#5a973b;
    color:#fff;
}

.table thead{
    background:#6db049;
    color:#fff;
}

.successModal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.55);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
}

.successCard{
    background:#fff;
    width:95%;
    max-width:420px;
    border-radius:22px;
    padding:30px;
    text-align:center;
    animation:pop .25s ease;
}

.successIcon{
    font-size:58px;
    color:#6db049;
}

@keyframes pop{
    from{transform:scale(.85);opacity:0;}
    to{transform:scale(1);opacity:1;}
}

@media(max-width:768px){
    .big-number{font-size:22px;}
    table{font-size:13px;}
}

</style>
</head>
<body>

<div class="container py-4">

    <div class="mb-4 text-center text-md-start">
        <h2 class="brand-title">EatFree</h2>
        <h4>Welcome, <?= htmlspecialchars($vendor['business_name']) ?></h4>
        <small class="text-muted">Vendor Transaction Terminal</small>
    </div>

    <!-- STATS -->
    <div class="row g-3 mb-4">

        <div class="col-md-3">
            <div class="stat-box">
                <div class="big-number" id="mealsRemaining">
                    <?= (int)$vendor['meals_remaining'] ?>
                </div>
                <div>Meals Remaining</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-box">
                <div class="big-number" id="mealsServed">
                    <?= (int)$claims['total_claims'] ?>
                </div>
                <div>Meals Served</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-box">
                <div class="big-number" id="earnings">
                    R <?= number_format($earnings,2) ?>
                </div>
                <div>Total Earnings</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-box">
                <div class="big-number">
                    R <?= number_format($pendingWithdrawal,2) ?>
                </div>
                <div>Pending</div>
            </div>
        </div>

    </div>

    <!-- WITHDRAWAL PANEL -->
    <div class="card p-3 mb-4">

        <h5><i class="bi bi-bank"></i> Withdraw Earnings</h5>

        <p class="mb-2">
            Minimum withdrawal amount:
            <strong>R200</strong>
        </p>

        <?php if($canWithdraw): ?>

            <button class="btn btn-main"
                onclick="requestWithdrawal()">
                Request Withdrawal
            </button>

        <?php else: ?>

            <button class="btn btn-secondary" disabled>
                Need R200 Minimum
            </button>

        <?php endif; ?>

    </div>

    <!-- VOUCHER TERMINAL -->
    <div class="card p-3">

        <h5 class="mb-3">
            <i class="bi bi-ticket-perforated"></i>
            Voucher Redemption Terminal
        </h5>

        <?php if(empty($activeVouchers)): ?>

            <p class="text-muted mb-0">No active vouchers available.</p>

        <?php else: ?>

        <div class="table-responsive">

        <table class="table align-middle">

            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Voucher</th>
                    <th>Confirm</th>
                </tr>
            </thead>

            <tbody id="voucherTable">

            <?php foreach($activeVouchers as $v): ?>

            <tr id="row<?= (int)$v['id'] ?>">

                <td><?= htmlspecialchars($v['beneficiary_id_number']) ?></td>
                <td><?= htmlspecialchars($v['beneficiary_name']) ?></td>
                <td><strong><?= htmlspecialchars($v['voucher_code']) ?></strong></td>

                <td>
                    <button
                        class="btn btn-main btn-sm"
                        onclick="redeemVoucher(<?= (int)$v['id'] ?>)">
                        Confirm
                    </button>
                </td>

            </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

        </div>

        <?php endif; ?>

    </div>

    <div class="mt-4 text-center text-md-start">
        <a href="/vendor/logout.php" class="btn btn-dark">
            Logout
        </a>
    </div>

</div>


<!-- MODAL -->
<div class="successModal" id="successModal">
    <div class="successCard">

        <div class="successIcon">
            <i class="bi bi-check-circle-fill"></i>
        </div>

        <h4 class="mt-3 brand-title">EatFree</h4>

        <p id="successText"></p>

        <button class="btn btn-main w-100" onclick="closeModal()">
            Continue
        </button>

    </div>
</div>

<script>

function showModal(text){
    document.getElementById('successText').innerText = text;
    document.getElementById('successModal').style.display='flex';
}

function closeModal(){
    document.getElementById('successModal').style.display='none';
}

async function redeemVoucher(voucherId){

    const formData = new FormData();
    formData.append('voucher_id', voucherId);

    const response = await fetch('/api/vendor-redeem-voucher.php',{
        method:'POST',
        body:formData
    });

    const result = await response.json();

    if(result.success){

        let row = document.getElementById('row'+voucherId);
        if(row) row.remove();

        let served = document.getElementById('mealsServed');
        served.innerText = parseInt(served.innerText)+1;

        let remain = document.getElementById('mealsRemaining');
        remain.innerText = parseInt(remain.innerText)-1;

        showModal(result.message);

    }else{
        showModal(result.message);
    }
}

async function requestWithdrawal(){

    const response = await fetch('/api/request-withdrawal.php',{
        method:'POST'
    });

    const result = await response.json();

    showModal(result.message);

    if(result.success){
        setTimeout(()=>location.reload(),1200);
    }
}

</script>

</body>
</html>
