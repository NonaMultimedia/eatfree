<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // dompdf
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php'; //send mail auto-responders
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;

//HARD STOP DUPLICATES (SAFEGUARD)
header('X-Statement-System: v1-single-email');

if (!isVendorLoggedIn()) {
    die('Unauthorized');
}

$type = $_GET['type'] ?? 'monthly';
$vendorId = $_SESSION['vendor_id'];

$db = getDB();

/**
 * DATE RANGE
 */
switch ($type) {
    case 'weekly':
        $start = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'yearly':
        $start = date('Y-m-d', strtotime('-1 year'));
        break;
    default:
        $start = date('Y-m-d', strtotime('-1 month'));
}

$end = date('Y-m-d');

/**
 * GET VENDOR
 */
$stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

/**
 * GET TRANSACTIONS
 */
$stmt = $db->prepare("
    SELECT created_at, amount, status, 'Withdrawal' as type
    FROM vendor_withdrawals
    WHERE vendor_id = ?
    AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$vendorId, $start, $end]);
$transactions = $stmt->fetchAll();

/**
 * LOAD TEMPLATE
 */
ob_start();
//DISABLE TEMPLATE PDF, USE ONLY FOR TESTING
// *** include __DIR__ . '/../templates/statement-template.php'; ***

    $totalSubsidyEarned = 0;
    $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        
        <title>EatFree Notification</title>
        
        <style>
            body {
                margin:0;
                padding:0;
                background:#f4f6f4;
                font-family: Arial, sans-serif;
            }
        
            .wrapper {
                width:100%;
                padding:30px 0;
                background:#f4f6f4;
            }
        
            .container {
                max-width:600px;
                margin:0 auto;
                background:#ffffff;
                border-radius:12px;
                overflow:hidden;
                box-shadow:0 4px 18px rgba(0,0,0,0.08);
            }
        
            .header {
                background:#6db049;
                padding:20px;
                text-align:center;
            }
        
            .header img {
                width:120px;
                margin-bottom:10px;
            }
        
            .header h1 {
                color:#fff;
                font-size:20px;
                margin:0;
                font-weight:600;
            }
        
            .content {
                padding:25px;
                color:#333;
                font-size:14px;
                line-height:1.6;
            }
        
            .section {
                margin-bottom:20px;
            }
        
            .label {
                font-weight:bold;
                color:#6db049;
            }
        
            .card {
                background:#f9faf9;
                padding:15px;
                border-radius:8px;
                margin-top:10px;
                border:1px solid #e6eae6;
            }
        
            .footer {
                text-align:center;
                font-size:12px;
                color:#888;
                padding:20px;
                background:#fafafa;
                border-top:1px solid #eee;
            }
        
            .btn {
                display:inline-block;
                padding:10px 18px;
                background:#6db049;
                color:#fff;
                text-decoration:none;
                border-radius:6px;
                margin-top:10px;
                font-size:13px;
            }
        
        </style>
        </head>
        
        <body>
        
        <div class="wrapper">
        
            <div class="container">
        
                <!-- HEADER -->
                <div class="header">
                    <h1>EatFree</h1>
                </div>
        
                <!-- CONTENT -->
                <div class="content">
        
                    <div class="section">
                        <p>Hi <strong>' . $vendor["business_name"] . '</strong>,</p>
                        <p>Here is your EatFree statement summary.</p>
                    </div>
        
                    <div class="section card">
                        <div><span class="label">Business Name:</span> ' . $vendor["business_name"] . '</div>
                        <div><span class="label">Email:</span> ' . $vendor["email"] . '</div>
                        <div><span class="label">City:</span> ' . $vendor["city"] . '</div>
                    </div>
        
                    <div class="section card">
                        <div><span class="label">Membership:</span> ' . ($vendor["subscription_status"] === "active" ? "Active" : "Inactive") . '</div>
                        <div><span class="label">Wallet Balance:</span> R ' . number_format($vendor["wallet_balance"],2) . '</div>
                    </div>
        
                    <div class="section">
                        <p style="font-size:13px;color:#666;">
                            Your attached document contains full transaction details.
                        </p>
                    </div>
        
                    <div class="section">
                        <a class="btn" href="https://eatfree.co.za/vendor/dashboard.php">View Dashboard</a>
                    </div>
        
                </div>
                <!-- CONNECT WITH EATFREE NOW-->
                <div class="section" style="margin-top:40px; text-align:center; border-top:1px solid #ddd; padding-top:15px;">

                    <p style="font-size:12px; color:#666; margin-bottom:10px;">
                        Connect with EatFree
                    </p>
                
                    <p style="font-size:12px; margin:0;">
                        <a href="https://www.tiktok.com/@eatfr.ee" style="margin:0 8px; color:#6db049; text-decoration:none;">TikTok</a> |
                        <a href="https://www.facebook.com/eatzfree/" style="margin:0 8px; color:#6db049; text-decoration:none;">Facebook</a> |
                        <a href="https://www.instagram.com/eatfree" style="margin:0 8px; color:#6db049; text-decoration:none;">Instagram</a> |
                        <a href="https://www.linkedin.com/company/eatfree" style="margin:0 8px; color:#6db049; text-decoration:none;">LinkedIn</a>
                    </p>
                
                </div>
        
                <!-- FOOTER -->
                <div class="footer">
                    © ' . date("Y") . ' EatFree. All rights reserved.<br>
                    Powered by EatFree Ecosystem
                </div>
        
            </div>
        
        </div>
        
        </body>
        </html>';

    /**
     * GENERATE PDF
     */
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $pdf = $dompdf->output();
    
    //ADDITIONAL EMAIL SUBMISSION FUNCTION
        $mail = new PHPMailer(true);
    //STOP DUPLICATE EMAILS    
        static $sent = false;
            if ($sent)
            {
                exit;
            }
            $sent = true;
            
        try {
        
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;
        
            $mail->setFrom('no-reply@eatfree.co.za', 'EatFree');
            $mail->addAddress($vendor['email'], $vendor['business_name']);
        
            $mail->Subject = 'Your EatFree Statement';
        
            $mail->Body = "Hello {$vendor['business_name']},
        
        Your EatFree {$type} statement is attached.
        
        Thank you for using EatFree.";
        
            // Attach PDF
            $mail->addStringAttachment($pdf, 'EatFree-'.$type.'-Statement.pdf');
        
            $mail->send();
        
        } catch (Exception $e) {
            error_log("Email failed: " . $mail->ErrorInfo);
        }
        
    $mode = $_GET['mode'] ?? 'download';
    if ($mode === 'preview') {
        header('Content-Type: text/html');
        echo $html;
        exit;
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="EatFree-Statement.pdf"');
    echo $pdf;
    exit;