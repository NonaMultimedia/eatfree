<?php

//ROOT/api/vendor-social-flyer.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

startSession();

if (!isset($_SESSION['vendor_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

try {

    $db = getDB();

    $stmt = $db->prepare("SELECT business_name FROM vendors WHERE id = ?");
    $stmt->execute([$_SESSION['vendor_id']]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        throw new Exception("Vendor not found");
    }

    $vendorName = $vendor['business_name'];
    $promoText = "Get a meal for just R15!";

    $qrLink = "https://yourdomain.com/vendor/scan.php?vendor_id=" . $_SESSION['vendor_id'];
    $qrImage = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($qrLink);

    /**
     * SELECT TEMPLATE
     */
     $html = "
                <html>
                    <body style='font-family:Arial; text-align:center; background:#f5f8f3; padding:40px;'>
                
                        <h1 style='color:#6db049;'>EatFree</h1>
                        <h2>{$vendorName}</h2>
                
                        <p style='font-size:18px;'>
                            Get a meal for just R15!<br><br>
                            Scan the QR code below
                        </p>
                
                        <img src='{$qrImage}' style='width:250px; margin-top:20px;'>
                
                        <p style='margin-top:40px; font-size:12px; color:#666;'>
                            Powered by EatFree
                        </p>
                
                    </body>
                </html>
            ";
     
    $template = $_GET['template'] ?? 'lounge';

    $templatePath = __DIR__ . "/../templates/flyers/{$template}.php";

    if (!file_exists($templatePath)) {
        throw new Exception("Template not found");
    }

    /**
     * RENDER TEMPLATE
     */
    ob_start();
    include $templatePath;
    $html = ob_get_clean();

    /**
     * PDF
     */
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="flyer-'.$template.'.pdf"');

    echo $dompdf->output();

} catch (Exception $e) {

    error_log("Flyer error: " . $e->getMessage());
    http_response_code(500);
    echo "Flyer generation failed.";
}