<?php
/**
 * root/vendor/dashboard.php
 * EatFree Vendor Dashboard (FINAL + WITHDRAWALS)
 * Voucher terminal + R200 withdrawal system
 * Subscriber Option
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
     * FIX 7 — SECTION 5:
     * Vendor dashboard feature unlock must require BOTH:
     *
     * 1. Admin document/application approval:
     *    - vendors.status = approved
     *    - vendors.kyc_verified = 1
     *
     * 2. Verified PayFast subscription:
     *    - vendors.payment_verified = 1
     *    - vendors.subscription_status = active
     *    - vendors.subscription_expires_at is valid
     *    - latest vendor_subscriptions.payment_status = paid
     *
     * This prevents manual vendors-table-only updates from unlocking Shop Assistant.
     */
    $subscriptionExpiresAt = $vendor['subscription_expires_at'] ?? null;
    
    $stmt = $db->prepare("
        SELECT 
            id,
            payment_status,
            subscription_start,
            subscription_end
        FROM vendor_subscriptions
        WHERE vendor_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([(int)$vendor['id']]);
    $latestSubscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $latestSubscriptionStatus = $latestSubscription['payment_status'] ?? null;
    $latestSubscriptionEnd = $latestSubscription['subscription_end'] ?? null;
    
    $documentsApproved = (
        ($vendor['status'] ?? '') === 'approved' &&
        (int)($vendor['kyc_verified'] ?? 0) === 1
    );
    
    $vendorPaymentFieldsVerified = (
        (int)($vendor['payment_verified'] ?? 0) === 1 &&
        ($vendor['subscription_status'] ?? '') === 'active' &&
        !empty($subscriptionExpiresAt) &&
        strtotime($subscriptionExpiresAt) >= strtotime(date('Y-m-d'))
    );
    
    $latestSubscriptionPaid = (
        $latestSubscriptionStatus === 'paid' &&
        !empty($latestSubscriptionEnd) &&
        strtotime($latestSubscriptionEnd) >= strtotime(date('Y-m-d'))
    );
    
    $membershipPaidAndActive = (
        $documentsApproved &&
        $vendorPaymentFieldsVerified &&
        $latestSubscriptionPaid
    );
    
    $canStartMembershipPayment = (
        $documentsApproved &&
        !$membershipPaidAndActive
    );
    
    $awaitingAdminApproval = !$documentsApproved;
    
    $onboardingStep = (int)($vendor['onboarding_step'] ?? 0);
    
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
     * LATEST WITHDRAWAL STATUS
     */
    $stmt = $db->prepare("
        SELECT id, amount, status, created_at, paid_at, payment_reference
        FROM vendor_withdrawals
        WHERE vendor_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$vendor['id']]);
    $latestWithdrawal = $stmt->fetch();

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
    $latestWithdrawal = null;
}
?>
<!DOCTYPE html>
<html lang="en-ZA">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                
                <title>Shop Dashboard - EatFree</title>
                
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
                        
                    .coach-highlight {
                        outline: 3px solid #6db049;
                        border-radius: 8px;
                        box-shadow: 0 0 0 9999px rgba(0,0,0,0.5);
                        position: relative;
                        z-index: 100000;
                    }
                
                </style>
            </head>
        <body>

    <div class="container py-4">
        <!-- Check if the vendor has done a dashboard tutorial -->
        <?php if ($onboardingStep < 5): ?>
        <?php endif; ?>
        
        <div class="mb-4 text-center text-md-start">
            <h2 class="brand-title">EatFree</h2>
            <h4>Welcome, <?= htmlspecialchars($vendor['business_name']) ?></h4>
            <small class="text-muted">To Your Shop Transaction Terminal.</small>
        </div>
        <!--
            MEMBERSHIP STATUS / SUBSCRIBER BUTTON
        -->
        <?php if ($membershipPaidAndActive): ?>
        
            <div class="alert alert-success">
                <strong>EatFree Membership Active ✓</strong><br>
                <small>Your documents and payment have been verified. Your shop features are unlocked.</small>
            </div>
        
        <?php elseif ($awaitingAdminApproval): ?>
        
            <div class="alert alert-secondary d-flex justify-content-between align-items-center">
                <div>
                    <strong>Document Review Pending</strong><br>
                    Your uploaded business documents are being approved before you can activate membership.
                </div>
        
                <button type="button" class="btn btn-secondary" disabled>
                    Awaiting EatFree Review
                </button>
            </div>
        
        <?php elseif ($canStartMembershipPayment && $latestSubscriptionStatus === 'pending'): ?>
        
            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                <div>
                    <strong>Payment Pending</strong><br>
                    Your membership payment was started but has not been confirmed yet.
                </div>
        
                <button 
                    type="button"
                    class="btn btn-secondary" 
                    disabled
                >
                    Awaiting Payment Confirmation
                </button>
            </div>
        
        <?php elseif ($canStartMembershipPayment): ?>
        
            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                <div>
                    <strong>Membership Required</strong><br>
                    Your documents are approved. Activate your EatFree membership to unlock shop features.
                </div>
        
                <button 
                    type="button"
                    class="btn btn-dark" 
                    id="activateMembershipBtn"
                    onclick="startSubscription()"
                >
                    Activate Membership
                </button>
            </div>

        <?php endif; ?>
            
        <!-- VOUCHER TERMINAL -->
        <div class="card p-3" id="step-vouchers">
    
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
            
        <!-- STATS -->
        <div class="row g-3 mb-4" id="step-stats">
    
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
        
        <!-- DASHBOARD NOTIFICATION CARD -->
        <?php if (!empty($latestWithdrawal)): ?>
        
            <div 
                id="withdrawalNotice<?= (int)$latestWithdrawal['id'] ?>_<?= htmlspecialchars($latestWithdrawal['status']) ?>" 
                class="mb-4 withdrawal-status-notice"
                data-withdrawal-id="<?= (int)$latestWithdrawal['id'] ?>"
                data-withdrawal-status="<?= htmlspecialchars($latestWithdrawal['status']) ?>"
            >
        
                <?php if ($latestWithdrawal['status'] === 'pending'): ?>
        
                    <div class="alert alert-warning d-flex align-items-start justify-content-between gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-hourglass-split fs-4"></i>
                            <div>
                                <strong>Withdrawal request submitted</strong><br>
                                Your withdrawal request of
                                <strong>R <?= number_format((float)$latestWithdrawal['amount'], 2) ?></strong>
                                is currently pending EatFree review.
                                <br>
                                <small class="text-muted">
                                    Requested on <?= htmlspecialchars(date('Y-m-d H:i', strtotime($latestWithdrawal['created_at']))) ?>
                                </small>
                            </div>
                        </div>
        
                        <button 
                            type="button" 
                            class="btn btn-sm btn-main"
                            onclick="dismissWithdrawalNotice(<?= (int)$latestWithdrawal['id'] ?>, '<?= htmlspecialchars($latestWithdrawal['status']) ?>')"
                        >
                            OK
                        </button>
                    </div>
        
                <?php elseif ($latestWithdrawal['status'] === 'processing'): ?>
        
                    <div class="alert alert-info d-flex align-items-start justify-content-between gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-arrow-repeat fs-4"></i>
                            <div>
                                <strong>Withdrawal is being processed</strong><br>
                                Your withdrawal of
                                <strong>R <?= number_format((float)$latestWithdrawal['amount'], 2) ?></strong>
                                is currently being processed by EatFree.
                            </div>
                        </div>
        
                        <button 
                            type="button" 
                            class="btn btn-sm btn-dark"
                            onclick="dismissWithdrawalNotice(<?= (int)$latestWithdrawal['id'] ?>, '<?= htmlspecialchars($latestWithdrawal['status']) ?>')"
                        >
                            OK
                        </button>
                    </div>
        
                <?php elseif ($latestWithdrawal['status'] === 'paid'): ?>
        
                    <div class="alert alert-success d-flex align-items-start justify-content-between gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                            <div>
                                <strong>Withdrawal paid successfully</strong><br>
                                Your withdrawal of
                                <strong>R <?= number_format((float)$latestWithdrawal['amount'], 2) ?></strong>
                                has been marked as paid.
                                <?php if (!empty($latestWithdrawal['payment_reference'])): ?>
                                    <br>
                                    <small class="text-muted">
                                        Reference: <?= htmlspecialchars($latestWithdrawal['payment_reference']) ?></br>
                                        Tip: Download eatfree statement
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
        
                        <button 
                            type="button" 
                            class="btn btn-sm btn-dark"
                            onclick="dismissWithdrawalNotice(<?= (int)$latestWithdrawal['id'] ?>, '<?= htmlspecialchars($latestWithdrawal['status']) ?>')"
                        >
                            OK
                        </button>
                    </div>
        
                <?php elseif ($latestWithdrawal['status'] === 'rejected'): ?>
        
                    <div class="alert alert-danger d-flex align-items-start justify-content-between gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-x-circle-fill fs-4"></i>
                            <div>
                                <strong>Withdrawal request rejected</strong><br>
                                Your withdrawal request of
                                <strong>R <?= number_format((float)$latestWithdrawal['amount'], 2) ?></strong>
                                was not approved. Please contact EatFree <mailto:"support@eatfree.co.za">Support</mailto:> if you need help.
                            </div>
                        </div>
        
                        <button 
                            type="button" 
                            class="btn btn-sm btn-dark"
                            onclick="dismissWithdrawalNotice(<?= (int)$latestWithdrawal['id'] ?>, '<?= htmlspecialchars($latestWithdrawal['status']) ?>')"
                        >
                            OK
                        </button>
                    </div>
        
                <?php endif; ?>
        
            </div>
        
        <?php endif; ?>
        
        <!-- WITHDRAWAL PANEL -->
        <div class="card p-3 mb-4" id="step-withdrawals">
    
            <h5><i class="bi bi-bank"></i> Withdraw Earnings</h5>
    
            <p class="mb-2">
                Minimum withdrawal amount:
                <strong>R200</strong>
            </p>
    
            <?php if($canWithdraw): ?>
    
                <div class="d-flex gap-2 align-items-center">
                
                    <input type="number"
                        id="withdrawAmount"
                        class="form-control"
                        placeholder="Enter amount (min R200)"
                        min="200"
                        step="0.01"
                        style="max-width:200px;">
                
                    <button class="btn btn-main"
                        onclick="requestWithdrawal()">
                        Withdraw Profit.
                    </button>
                
                </div>
    
            <?php else: ?>
    
                <button class="btn btn-secondary" disabled>
                    Need R200 Minimum
                </button>
    
            <?php endif; ?>
    
        </div>
     
            <!-- SHOP ASSISTANT -->
            <?php if ($membershipPaidAndActive): ?>
            
                <div class="card p-3 mb-4">
                    <h5>
                        <i class="bi bi-file-earmark-text"></i>
                        Shop Assistant
                    </h5>
            
                    <p class="text-muted mb-2">
                        Download your EatFree weekly, monthly or yearly statement.
                    </p>
            
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <select id="statementType" class="form-select" style="max-width:220px;">
                            <option value="weekly">Weekly Statement</option>
                            <option value="monthly" selected>Monthly Statement</option>
                            <option value="yearly">Yearly Statement</option>
                        </select>
            
                        <button class="btn btn-dark" onclick="downloadStatement()">
                            Download Statement
                        </button>
            
                        <button class="btn btn-dark mt-2" onclick="previewA4Flyer('lounge')">
                            Preview A4 Flyer
                        </button>
            
                        <button class="btn btn-dark mt-2" onclick="downloadA4Flyer('lounge')">
                            Download PDF
                        </button>
                    </div>
                </div>
            
            <?php else: ?>
            
                <div class="card p-3 mb-4 border-warning">
                    <h5>
                        <i class="bi bi-lock-fill"></i>
                        Shop Assistant Locked
                    </h5>
                
                    <?php if ($awaitingAdminApproval): ?>
                
                        <p class="text-muted mb-2">
                            Your Shop Assistant will unlock after EatFree approves your uploaded business documents and Payment confirms your membership payment.
                        </p>
                
                        <button class="btn btn-secondary" disabled>
                            Awaiting Admin Review
                        </button>
                
                    <?php elseif ($latestSubscriptionStatus === 'pending'): ?>
                
                        <p class="text-muted mb-2">
                            Your Shop Assistant will unlock once membership payment is confirmed.
                        </p>
                
                        <button class="btn btn-secondary" disabled>
                            Awaiting Confirmation
                        </button>
                
                    <?php else: ?>
                
                        <p class="text-muted mb-2">
                            Your Shop Assistant will unlock after membership payment.
                        </p>
                
                        <button class="btn btn-secondary" disabled>
                            Awaiting Payment Confirmation
                        </button>
                
                    <?php endif; ?>
                </div>
            
            <?php endif; ?>
    
        <div class="mt-4 text-center text-md-start">
            <a href="/vendor/logout.php" class="btn btn-dark">
                Logout
            </a>
        </div>
    
    </div>
    
        <!-- OVERLAY COACH SYSTEM -->
        <div id="coachOverlay" style="
            position:fixed;
            inset:0;
            background:rgba(0,0,0,0.6);
            display:none;
            z-index:99999;
        ">
            <div id="coachBox" style="
                position:absolute;
                background:#fff;
                padding:20px;
                border-radius:12px;
                max-width:320px;
                transition:all .3s ease;
            ">
                <h5 id="coachTitle">Welcome</h5>
                <p id="coachText"></p>
                <button class="btn btn-success btn-sm" onclick="nextStep()">Next</button>
            </div>
        </div>
    
            <!-- ONBOARDING MODAL -->
        <div class="modal fade" id="onboardingModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3">
        
              <div class="modal-body text-center">
                <h4 class="brand-title">Welcome to EatFree</h4>
                <p>Letâ€™s quickly guide you through your dashboard.</p>
        
                <button class="btn btn-main w-100" onclick="closeOnboarding()">
                  Start Tour
                </button>
              </div>
        
            </div>
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
                    Okay
                </button>
        
            </div>
        </div>
        
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
            
            <script>
            
            // START THE COACH

            window.addEventListener('load', function(){

                const hasSeen = <?= (int)$vendor['onboarding_seen'] ?>;
            
                // FIRST TIME USER
                if(hasSeen === 0){
                    showOnboardingModal();
                    return; // STOP everything else
                }
            
                // ONLY run coach if not completed
                if(<?= $onboardingStep ?> < steps.length){
                    startCoach();
                }
            
            });
            
            async function startSubscription(){

                const button = document.getElementById('activateMembershipBtn');
            
                if(button){
                    button.disabled = true;
                    button.innerText = 'Starting...';
                }
            
                try {
            
                    const response = await fetch('/api/vendor-subscribe.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
            
                    const rawResponse = await response.text();
            
                    let result;
            
                    try {
                        result = JSON.parse(rawResponse);
                    } catch (parseError) {
                        console.error('Subscription response was not valid JSON:', rawResponse);
                        showModal('Membership activation failed. The server returned an invalid response.');
                        return;
                    }
            
                    console.log('Subscription response:', result);
            
                    if(result.success && result.data && result.data.payfast_url){
            
                        window.location.href = result.data.payfast_url;
                        return;
                    }
            
                    showModal(result.message || 'Unable to start membership payment.');
            
                } catch (error) {
            
                    console.error('Subscription error:', error);
                    showModal('Membership activation failed. Please try again.');
            
                } finally {
            
                    if(button){
                        button.disabled = false;
                        button.innerText = 'Activate Membership';
                    }
                }
            }
            
            //Marketing Assets Controller Functions
            function previewA4Flyer(template){
                window.open('/api/vendor-generate-flyer.php?template=' + encodeURIComponent(template) + '&mode=preview', '_blank');
            }
            
            function downloadA4Flyer(template){
                window.open('/api/vendor-generate-flyer.php?template=' + encodeURIComponent(template) + '&mode=download', '_blank');
            }
            
            function downloadStatement() {
                const type = document.getElementById('statementType').value;
            
                if (!type) {
                    showModal('Please select a statement period.');
                    return;
                }
            
                window.open('/api/vendor-statement.php?type=' + encodeURIComponent(type), '_blank');
            }
            
            async function redeemVoucher(voucherId){
            
                const formData = new FormData();
                formData.append('voucher_id', voucherId);
            
                try {
                    const response = await fetch('/api/vendor-redeem-voucher.php', {
                        method: 'POST',
                        body: formData
                    });
            
                    const result = await response.json();
            
                    if(result.success){
            
                        const row = document.getElementById('row' + voucherId);
                        if(row){
                            row.style.display = 'none';
                            row.remove();
                        }
            
                        const served = document.getElementById('mealsServed');
                        if(served){
                            served.innerText = parseInt(served.innerText || '0') + 1;
                        }
            
                        const remain = document.getElementById('mealsRemaining');
                        if(remain){
                            remain.innerText = Math.max(0, parseInt(remain.innerText || '0') - 1);
                        }
            
                        // If no rows remain, show empty state
                        const tbody = document.getElementById('voucherTable');
                        if(tbody && tbody.querySelectorAll('tr').length === 0){
                            const tableWrapper = tbody.closest('.table-responsive');
                            if(tableWrapper){
                                tableWrapper.innerHTML = '<p class="text-muted mb-0">No active vouchers available.</p>';
                            }
                        }
            
                        showModal(result.message || 'Voucher redeemed successfully');
            
                    } else {
                        showModal(result.message || 'Failed to redeem voucher');
                    }
            
                } catch (error) {
                    showModal('Voucher redemption failed. Please try again.');
                }
            }

            async function requestWithdrawal(){
            
                const input = document.getElementById('withdrawAmount');
            
                if(!input){
                    showModal('Withdrawal input not found.');
                    return;
                }
            
                const amount = parseFloat(input.value);
            
                if (!amount || amount < 200) {
                    showModal('Minimum withdrawal is R200');
                    return;
                }
            
                try {
            
                    const response = await fetch('/api/request-withdrawal.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            amount: amount
                        })
                    });
            
                    const result = await response.json();
            
                    console.log('Withdrawal response:', result);
            
                    if(result.success){
            
                        window.shouldReloadAfterModal = true;
            
                        showModal(
                            'Your withdrawal request is being processed. EatFree will review and approve the payout shortly.'
                        );
            
                    } else {
            
                        window.shouldReloadAfterModal = false;
            
                        showModal(result.message || 'Withdrawal failed.');
                    }
            
                } catch (error) {
            
                    console.error(error);
            
                    window.shouldReloadAfterModal = false;
            
                    showModal('Withdrawal failed. Please try again.');
                }
            }
            
            //NOTIFICATION DASHBOARD HANDLER
            function dismissWithdrawalNotice(withdrawalId, status){
            
                const noticeId = 'withdrawalNotice' + withdrawalId + '_' + status;
                const notice = document.getElementById(noticeId);
            
                if(notice){
                    notice.style.display = 'none';
                }
            
                localStorage.setItem(
                    'eatfree_withdrawal_notice_' + withdrawalId + '_' + status,
                    'dismissed'
                );
            }
            
            window.addEventListener('load', function(){
            
                const notices = document.querySelectorAll('.withdrawal-status-notice');
            
                notices.forEach(function(notice){
            
                    const withdrawalId = notice.getAttribute('data-withdrawal-id');
                    const status = notice.getAttribute('data-withdrawal-status');
            
                    if(
                        withdrawalId &&
                        status &&
                        localStorage.getItem('eatfree_withdrawal_notice_' + withdrawalId + '_' + status) === 'dismissed'
                    ){
                        notice.style.display = 'none';
                    }
            
                });
            
            });
            
            // ---------- MODAL ----------
            function showOnboardingModal(){
                const modalEl = document.getElementById('onboardingModal');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
            
            function closeOnboarding(){
                localStorage.setItem('onboarding_seen', '1');
                // mark as seen
                fetch('/api/vendor-onboarding-seen.php', {
                    method: 'POST'
                });
            
                const modalEl = document.getElementById('onboardingModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();
            
                // start coach AFTER modal
                startCoach();
            }
            
            
            // ---------- COACH SYSTEM ----------
            let onboardingStep = <?= $onboardingStep ?>;
            
            const steps = [
                {
                    title: "Welcome to EatFree",
                    text: "Letâ€™s quickly show you how your dashboard works.",
                    target: null
                },
                {
                    title: "Your Earnings",
                    text: "Here you track meals served and earnings.",
                    target: "step-stats"
                },
                {
                    title: "Withdrawals",
                    text: "This is where you cash out your earnings.",
                    target: "step-withdrawals"
                },
                {
                    title: "Vouchers",
                    text: "This is where customers redeem meals.",
                    target: "step-vouchers"
                }
            ];
            
           function showModal(text){
                const modal = document.getElementById('successModal');
                const textBox = document.getElementById('successText');
            
                if(!modal || !textBox){
                    alert(text);
                    return;
                }
            
                textBox.innerText = text;
                modal.style.display = 'flex';
            }
            
            function closeModal(){
                const modal = document.getElementById('successModal');
            
                if(modal){
                    modal.style.display = 'none';
                }
            
                if (window.shouldReloadAfterModal === true) {
                    window.shouldReloadAfterModal = false;
                    window.location.href = '/vendor/dashboard.php';
                }
            }
            
           function startCoach(){

                const seen = <?= (int)$vendor['onboarding_seen'] ?>;
            
                if(seen === 1) return; // HARD STOP
            
                document.getElementById('coachOverlay').style.display = 'block';
                showStep(onboardingStep);
            }
            
            
            function showStep(i){
            
                if(i >= steps.length){
                    finishCoach();
                    return;
                }
            
                const step = steps[i];
            
                document.getElementById('coachTitle').innerText = step.title;
                document.getElementById('coachText').innerText = step.text;
            
                const box = document.getElementById('coachBox');
            
                // Reset styles
                box.style.position = 'fixed';
                box.style.transform = 'none';
            
                if(step.target){
            
                    const el = document.getElementById(step.target);
            
                    if(!el){
                        console.warn("Missing step target:", step.target);
                        return;
                    }
            
                    highlightElement(el);
            
                    const rect = el.getBoundingClientRect();
            
                    const boxHeight = 140; // approx height of coach box
                    const margin = 12;
            
                    let top = rect.bottom + margin;
                    let left = rect.left;
            
                    // ðŸš¨ If bottom overflow â†’ move above element
                    if(top + boxHeight > window.innerHeight){
                        top = rect.top - boxHeight - margin;
                    }
            
                    // ðŸš¨ If still above screen â†’ pin to top
                    if(top < 10){
                        top = 10;
                    }
            
                    // ðŸš¨ Prevent left overflow
                    if(left + 320 > window.innerWidth){
                        left = window.innerWidth - 330;
                    }
            
                    if(left < 10){
                        left = 10;
                    }
            
                    box.style.top = top + 'px';
                    box.style.left = left + 'px';
            
                } else {
            
                    // Center for intro step
                    box.style.top = '50%';
                    box.style.left = '50%';
                    box.style.transform = 'translate(-50%, -50%)';
                }
            }
            
            
            function nextStep(){
            
                onboardingStep++;
            
                fetch('/api/vendor-onboarding-step.php', {
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ step: onboardingStep })
                });
            
                showStep(onboardingStep);
            }
            
            
            async function finishCoach(){

                await fetch('/api/vendor-onboarding-seen.php', {
                    method:'POST'
                });
            
                localStorage.setItem('onboarding_seen', '1'); // keep frontend in sync
            
                document.getElementById('coachOverlay').style.display = 'none';
            
                if(currentHighlighted){
                    currentHighlighted.classList.remove('coach-highlight');
                }
            }
            
            
            // ---------- HIGHLIGHT ----------
            let currentHighlighted = null;
            
            function highlightElement(el){
            
                if(currentHighlighted){
                    currentHighlighted.classList.remove('coach-highlight');
                }
            
                el.classList.add('coach-highlight');
                currentHighlighted = el;
            }
        
        </script>
    
    </body>
</html>