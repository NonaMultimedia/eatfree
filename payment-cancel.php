<?php
/**
 * Payment Cancel Page
 * Displays payment cancellation message
 */

require_once __DIR__ . '/config/config.php';

$donationId = $_GET['donation_id'] ?? null;
$subscriptionId = $_GET['subscription_id'] ?? null;

// Update status to cancelled if applicable
if ($donationId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE donations SET status = 'failed' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$donationId]);
    } catch (Exception $e) {
        error_log("Error updating donation status: " . $e->getMessage());
    }
} elseif ($subscriptionId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE vendor_subscriptions SET payment_status = 'failed' WHERE id = ? AND payment_status = 'pending'");
        $stmt->execute([$subscriptionId]);
    } catch (Exception $e) {
        error_log("Error updating subscription status: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6db049">
    <title>Payment Cancelled | EatFree</title>
    
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
        .cancel-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .cancel-card {
            background: var(--ef-white);
            border-radius: var(--ef-radius-lg);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--ef-shadow-xl);
            animation: popIn 0.5s ease;
        }
        .cancel-icon {
            width: 100px;
            height: 100px;
            background: var(--ef-warning);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--ef-dark);
            font-size: 3rem;
        }
        .cancel-title {
            font-family: 'Cooper Black Regular', serif;
            color: var(--ef-gray-900);
            margin-bottom: 1rem;
        }
        .cancel-message {
            color: var(--ef-gray-500);
            margin-bottom: 2rem;
        }
        .btn-retry {
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
            margin-right: 1rem;
        }
        .btn-retry:hover {
            background: var(--ef-primary-dark);
            color: var(--ef-white);
            transform: translateY(-2px);
        }
        .btn-home {
            background: var(--ef-gray-100);
            color: var(--ef-gray-700);
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
            background: var(--ef-gray-300);
            color: var(--ef-gray-900);
        }
        @keyframes popIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="cancel-page">
        <div class="cancel-card">
            <div class="cancel-icon">
                <i class="bi bi-x-lg"></i>
            </div>
            
            <h1 class="cancel-title">Payment Cancelled</h1>
            
            <p class="cancel-message">
                Your payment was cancelled. Don't worry - no amount has been charged. 
                You can try again whenever you're ready.
            </p>
            
            <div class="action-buttons">
                <a href="javascript:history.back()" class="btn-retry">
                    <i class="bi bi-arrow-counterclockwise"></i> Try Again
                </a>
                <a href="/" class="btn-home">
                    <i class="bi bi-house"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>
