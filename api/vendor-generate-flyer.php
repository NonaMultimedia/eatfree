<?php
// ROOT/api/vendor-generate-flyer.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

startSession();

if (!isset($_SESSION['vendor_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

function fetchRemoteImageAsDataUri($url)
{
    $imageData = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $imageData = curl_exec($ch);
        curl_close($ch);
    }

    if (!$imageData && ini_get('allow_url_fopen')) {
        $imageData = @file_get_contents($url);
    }

    if (!$imageData) {
        return null;
    }

    return 'data:image/png;base64,' . base64_encode($imageData);
}

function buildFlyerPdf($html)
{
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function sendFlyerEmail($toEmail, $toName, $pdfData, $templateName)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        if (defined('SMTP_HOST')) {
            $mail->Host = SMTP_HOST;
        }

        if (defined('SMTP_USER')) {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
        }

        if (defined('SMTP_PASS')) {
            $mail->Password = SMTP_PASS;
        }

        if (defined('SMTP_SECURE')) {
            $mail->SMTPSecure = SMTP_SECURE;
        }

        if (defined('SMTP_PORT')) {
            $mail->Port = SMTP_PORT;
        }

        /*
         * IMPORTANT:
         * This sender address must exist in Afrihost/email hosting.
         * If no-replys@eatfree.co.za does not exist, use no-reply@eatfree.co.za.
         */
        $mail->setFrom('no-reply@eatfree.co.za', 'EatFree');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your EatFree Marketing Flyer';

        $safeName = htmlspecialchars($toName);

        $mail->Body = "
            <div style='font-family:Arial,sans-serif; line-height:1.6;'>
                <h2 style='color:#6db049;'>Your EatFree Flyer is Ready</h2>
                <p>Hello <strong>{$safeName}</strong>,</p>
                <p>Your EatFree A4 marketing flyer is attached.</p>
                <p>You can print it, share it, or use it to promote your shop.</p>
                <p style='font-size:12px;color:#777;'>Powered by EatFree</p>
            </div>
        ";

        $mail->AltBody = "Your EatFree A4 marketing flyer is attached.";

        $filename = 'EatFree-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $templateName) . '-Flyer.pdf';
        $mail->addStringAttachment($pdfData, $filename, 'base64', 'application/pdf');

        $mail->send();

        return [
            'success' => true,
            'message' => 'Flyer emailed successfully to ' . $toEmail
        ];

    } catch (MailException $e) {
        error_log("Flyer email failed: " . $mail->ErrorInfo);

        return [
            'success' => false,
            'message' => 'Flyer was generated, but email sending failed: ' . $mail->ErrorInfo
        ];
    }
}

try {

    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, business_name, email, city
        FROM vendors
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['vendor_id']]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        throw new Exception("Vendor not found");
    }

    $vendorId = (int)$vendor['id'];
    $vendorName = $vendor['business_name'];
    $vendorEmail = $vendor['email'];
    $vendorCity = $vendor['city'] ?? 'South Africa';

    $promoText = "Get a meal for just R15!";
    $qrLink = "https://www.eatfree.co.za/claim.php?vendor_id=" . $vendorId . "&src=flyer";

    /*
     * QR is generated as a remote image, fetched server-side, then embedded as base64.
     * This keeps the QR stable inside Dompdf.
     */
    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=360x360&data=" . urlencode($qrLink);
    $qrImage = fetchRemoteImageAsDataUri($qrApiUrl);

    if (!$qrImage) {
        throw new Exception("QR code could not be generated.");
    }

    $template = $_GET['template'] ?? 'lounge';
    $template = preg_replace('/[^a-zA-Z0-9_-]/', '', $template);

    $mode = $_GET['mode'] ?? 'preview';

    $templatePath = __DIR__ . "/../templates/flyers/{$template}.php";

    if (!file_exists($templatePath)) {
        throw new Exception("Flyer template not found: " . $template);
    }

    ob_start();
    include $templatePath;
    $html = ob_get_clean();

    if (trim($html) === '') {
        throw new Exception("Flyer template rendered empty.");
    }

    $pdfData = buildFlyerPdf($html);

    if ($mode === 'email') {
        header('Content-Type: application/json');

        $emailResult = sendFlyerEmail($vendorEmail, $vendorName, $pdfData, $template);

        echo json_encode($emailResult);
        exit;
    }

    if ($mode === 'download') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="EatFree-A4-Flyer.pdf"');
        echo $pdfData;
        exit;
    }
    
    /*
     * Default mode: preview in browser.
     * Note: some browsers may still download PDFs depending on browser settings.
     */
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="EatFree-A4-Flyer.pdf"');
    echo $pdfData;
    exit;

} catch (Throwable $e) {

    error_log("Flyer generation error: " . $e->getMessage());

    if (($_GET['mode'] ?? '') === 'email') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Flyer email failed: ' . $e->getMessage()
        ]);
        exit;
    }

    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Flyer generation failed: " . $e->getMessage();
    exit;
}