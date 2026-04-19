<?php
/**
 * Payment Success Page
 * Displays payment confirmation
 */

require_once __DIR__ . '/config/config.php';

$donationId = $_GET['donation_id'] ?? null;
$subscriptionId = $_GET['subscription_id'] ?? null;

$paymentType = '';
$amount = 0;
$reference = '';

if ($donationId) {
    $paymentType = 'donation';
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT amount, payment_reference, donor_name FROM donations WHERE id = ?");
        $stmt->execute([$donationId]);
        $donation = $stmt->fetch();
        if ($donation) {
            $amount = $donation['amount'];
            $reference = $donation['payment_reference'];
        }
    } catch (Exception $e) {
        error_log("Error fetching donation: " . $e->getMessage());
    }
} elseif ($subscriptionId) {
    $paymentType = 'subscription';
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT total_amount FROM vendor_subscriptions WHERE id = ?");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch();
        if ($subscription) {
            $amount = $subscription['total_amount'];
            $reference = 'SUB-' . $subscriptionId;
        }
    } catch (Exception $e) {
        error_log("Error fetching subscription: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6db049">
    <title>Payment Successful | EatFree</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom Fonts -->
    <link rel="stylesheet" href="assets/css/Cooper%20Black%20Regular.css">
    <link rel="stylesheet" href="assets/css/Montserrat.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/istyle3.css">
    
    <style>
        .success-page {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--ef-primary) 0%, #5a9a3d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .success-card {
            background: var(--ef-white);
            border-radius: var(--ef-radius-lg);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--ef-shadow-xl);
            animation: popIn 0.5s ease;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--ef-success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--ef-white);
            font-size: 3rem;
            animation: pulse 2s infinite;
        }
        .success-title {
            font-family: 'Cooper Black Regular', serif;
            color: var(--ef-gray-900);
            margin-bottom: 1rem;
        }
        .success-message {
            color: var(--ef-gray-500);
            margin-bottom: 2rem;
        }
        .payment-details {
            background: var(--ef-gray-100);
            border-radius: var(--ef-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--ef-gray-300);
        }
        .payment-row:last-child {
            border-bottom: none;
        }
        .payment-label {
            color: var(--ef-gray-500);
        }
        .payment-value {
            font-weight: 600;
            color: var(--ef-gray-900);
        }
        .btn-home {
            background: var(--ef-primary);
            color: var(--ef-white);
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--ef-transition);
        }
        .btn-home:hover {
            background: var(--ef-primary-dark);
            color: var(--ef-white);
            transform: translateY(-2px);
        }
        @keyframes popIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body>
    <div class="success-page">
        <div class="success-card">
            <div class="success-icon">
                <i class="bi bi-check-lg"></i>
            </div>
            
            <h1 class="success-title">Payment Successful!</h1>
            
            <?php if ($paymentType === 'donation'): ?>
                <p class="success-message">
                    Thank you for your generous donation! Your contribution will help feed families in communities across South Africa.
                </p>
            <?php elseif ($paymentType === 'subscription'): ?>
                <p class="success-message">
                    Your subscription is now active! You can start serving meals to your community.
                </p>
            <?php else: ?>
                <p class="success-message">
                    Your payment has been processed successfully.
                </p>
            <?php endif; ?>
            
            <div class="payment-details">
                <div class="payment-row">
                    <span class="payment-label">Payment Type</span>
                    <span class="payment-value">
                        <?php echo $paymentType === 'donation' ? 'Donation' : ($paymentType === 'subscription' ? 'Subscription' : 'Payment'); ?>
                    </span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Amount</span>
                    <span class="payment-value">R<?php echo number_format($amount, 2); ?></span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Reference</span>
                    <span class="payment-value" style="font-size: 0.875rem;"><?php echo htmlspecialchars($reference); ?></span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Date</span>
                    <span class="payment-value"><?php echo date('Y-m-d H:i'); ?></span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Status</span>
                    <span class="payment-value" style="color: var(--ef-success);">Completed</span>
                </div>
            </div>
            
            <?php if ($paymentType === 'subscription'): ?>
                <a href="/vendor/dashboard.php" class="btn-home">
                    <i class="bi bi-shop"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="/" class="btn-home">
                    <i class="bi bi-house"></i> Back to Home
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
