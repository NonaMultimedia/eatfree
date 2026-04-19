<?php
/**
 * PayFast ITN (Instant Transaction Notification) Handler
 * Processes payment notifications from PayFast
 */

require_once __DIR__ . '/../config/config.php';

// ITN must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Log raw ITN data for debugging
$logFile = __DIR__ . '/../logs/payfast_itn.log';
$rawData = file_get_contents('php://input');
error_log("[" . date('Y-m-d H:i:s') . "] PayFast ITN Received: " . $rawData . "\n", 3, $logFile);

// Get POST data
$pfData = $_POST;

if (empty($pfData)) {
    error_log("[" . date('Y-m-d H:i:s') . "] Empty ITN data received\n", 3, $logFile);
    exit('No data received');
}

// Verify PayFast signature
$signature = $pfData['signature'] ?? '';
unset($pfData['signature']);

$calculatedSignature = generatePayFastSignature($pfData, PAYFAST_PASSPHRASE);

if ($signature !== $calculatedSignature) {
    error_log("[" . date('Y-m-d H:i:s') . "] Invalid signature. Received: $signature, Calculated: $calculatedSignature\n", 3, $logFile);
    exit('Invalid signature');
}

// Verify payment with PayFast server (optional but recommended)
$verifyData = [];
foreach ($pfData as $key => $val) {
    $verifyData[] = $key . '=' . urlencode($val);
}
$verifyString = implode('&', $verifyData);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, PAYFAST_VALIDATE_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $verifyString);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
curl_close($ch);

if ($response !== 'VALID') {
    error_log("[" . date('Y-m-d H:i:s') . "] PayFast validation failed: $response\n", 3, $logFile);
    exit('Validation failed');
}

// Check payment status
$paymentStatus = $pfData['payment_status'] ?? '';
if ($paymentStatus !== 'COMPLETE') {
    error_log("[" . date('Y-m-d H:i:s') . "] Payment not complete. Status: $paymentStatus\n", 3, $logFile);
    exit('Payment not complete');
}

// Get transaction details
$mPaymentId = $pfData['m_payment_id'] ?? '';
$pfPaymentId = $pfData['pf_payment_id'] ?? '';
$amountGross = (float)($pfData['amount_gross'] ?? 0);
$customStr1 = $pfData['custom_str1'] ?? '';
$customStr2 = $pfData['custom_str2'] ?? '';

try {
    $db = getDB();
    
    // Determine transaction type
    $transactionType = $customStr2 ?: 'donation';
    
    if ($transactionType === 'donation') {
        // Process donation
        $donationId = (int)$customStr1;
        
        // Update donation record
        $stmt = $db->prepare("
            UPDATE donations 
            SET status = 'completed', 
                payfast_m_payment_id = ?,
                payment_method = 'payfast'
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$pfPaymentId, $donationId]);
        
        if ($stmt->rowCount() > 0) {
            // Update global wallet
            updateGlobalWallet($amountGross, 'add');
            
            // Log transaction
            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (transaction_type, amount, reference_id, reference_type, description, balance_after)
                VALUES ('donation', ?, ?, 'donation', ?, ?)
            ");
            $stmt->execute([
                $amountGross,
                $donationId,
                'Donation received: ' . $mPaymentId,
                getWalletBalance()
            ]);
            
            // Check if we should approve vendors from queue
            $capacity = calculateEcosystemCapacity();
            if ($capacity['available_slots'] > 0) {
                // Approve waiting vendors
                $stmt = $db->prepare("
                    SELECT vq.vendor_id 
                    FROM vendor_queue vq
                    WHERE vq.status = 'waiting'
                    ORDER BY vq.queue_position ASC
                    LIMIT ?
                ");
                $stmt->execute([$capacity['available_slots']]);
                $vendorsToApprove = $stmt->fetchAll();
                
                foreach ($vendorsToApprove as $vendor) {
                    // Update vendor status
                    $stmt = $db->prepare("UPDATE vendors SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$vendor['vendor_id']]);
                    
                    // Update queue status
                    $stmt = $db->prepare("UPDATE vendor_queue SET status = 'approved' WHERE vendor_id = ?");
                    $stmt->execute([$vendor['vendor_id']]);
                    
                    // Get vendor email for notification
                    $stmt = $db->prepare("SELECT email, business_name FROM vendors WHERE id = ?");
                    $stmt->execute([$vendor['vendor_id']]);
                    $vendorData = $stmt->fetch();
                    
                    if ($vendorData) {
                        // Send approval email
                        $emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; }
        .header { background: #6db049; color: #fff; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background: #6db049; color: #fff; text-decoration: none; border-radius: 25px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Great News!</h1>
        </div>
        <div class="content">
            <h2>Hi {$vendorData['business_name']},</h2>
            <p>Your EatFree vendor application has been approved!</p>
            <p>A slot has become available due to new donations to the platform.</p>
            <p>You can now subscribe to start serving meals to your community.</p>
            <p style="text-align: center; margin-top: 30px;">
                <a href="https://eatfree.co.za/vendor/dashboard.php" class="btn">Subscribe Now</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
                        sendEmail($vendorData['email'], 'Your EatFree Application is Approved!', $emailBody);
                    }
                }
                
                // Reorder queue positions
                $stmt = $db->query("
                    SELECT id FROM vendor_queue 
                    WHERE status = 'waiting' 
                    ORDER BY queue_position ASC
                ");
                $waitingVendors = $stmt->fetchAll();
                
                $position = 1;
                foreach ($waitingVendors as $waiting) {
                    $stmt = $db->prepare("UPDATE vendor_queue SET queue_position = ? WHERE id = ?");
                    $stmt->execute([$position, $waiting['id']]);
                    $position++;
                }
            }
            
            error_log("[" . date('Y-m-d H:i:s') . "] Donation processed successfully. ID: $donationId, Amount: $amountGross\n", 3, $logFile);
        }
        
    } elseif ($transactionType === 'subscription') {
        // Process vendor subscription
        $subscriptionId = (int)$customStr1;
        
        // Update subscription record
        $stmt = $db->prepare("
            UPDATE vendor_subscriptions 
            SET payment_status = 'paid', 
                payfast_m_payment_id = ?,
                subscription_start = CURDATE(),
                subscription_end = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            WHERE id = ? AND payment_status = 'pending'
        ");
        $stmt->execute([$pfPaymentId, $subscriptionId]);
        
        if ($stmt->rowCount() > 0) {
            // Get subscription details
            $stmt = $db->prepare("SELECT vendor_id, meals_included FROM vendor_subscriptions WHERE id = ?");
            $stmt->execute([$subscriptionId]);
            $subscription = $stmt->fetch();
            
            if ($subscription) {
                // Update vendor subscription status and add meals
                $stmt = $db->prepare("
                    UPDATE vendors 
                    SET subscription_status = 'active',
                        subscription_expires_at = DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
                        meals_remaining = meals_remaining + ?
                    WHERE id = ?
                ");
                $stmt->execute([$subscription['meals_included'], $subscription['vendor_id']]);
                
                error_log("[" . date('Y-m-d H:i:s') . "] Subscription processed successfully. ID: $subscriptionId, Vendor: {$subscription['vendor_id']}\n", 3, $logFile);
            }
        }
    }
    
    // Return success response to PayFast
    echo 'OK';
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] ITN processing error: " . $e->getMessage() . "\n", 3, $logFile);
    exit('Error processing ITN');
}
