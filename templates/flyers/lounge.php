<?php
// ROOT/templates/flyers/lounge.php
// Expected variables:
// $vendorName
// $vendorEmail
// $vendorCity
// $promoText
// $qrImage
// $qrLink
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>
    @page {
        margin: 0;
        size: A4 portrait;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        font-family: DejaVu Sans, Arial, sans-serif;
        background: #f8f9fa;
    }

    .page {
        width: 190mm;
        height: 277mm;
        margin: 10mm;
        background: #f8f9fa;
        overflow: hidden;
        border-radius: 8mm;
        border: 2mm solid #6db049;
    }

    .top {
        height: 42mm;
        background: #6db049;
        color: #ffffff;
        text-align: center;
        padding-top: 10mm;
    }

    .brand {
        font-size: 34px;
        font-weight: bold;
        margin-bottom: 2mm;
    }

    .brand-sub {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #f4f8f1;
    }

    .gold-line {
        height: 4mm;
        background: #ebe954;
    }

    .content {
        text-align: center;
        padding: 9mm 12mm 0 12mm;
    }

    .vendor-pill {
        display: inline-block;
        background: #ffffff;
        border: 1.5mm solid #6db049;
        border-radius: 30px;
        padding: 3mm 8mm;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        color: #1a1a1a;
        margin-bottom: 7mm;
    }

    .headline {
        font-size: 31px;
        line-height: 1.05;
        font-weight: bold;
        text-transform: uppercase;
        color: #1a1a1a;
        margin-bottom: 5mm;
    }

    .headline span {
        color: #6db049;
    }

    .promo {
        font-size: 13px;
        line-height: 1.35;
        color: #495057;
        margin-bottom: 7mm;
    }

    .qr-wrap {
        width: 58mm;
        height: 58mm;
        margin: 0 auto;
        background: #ffffff;
        border: 2mm solid #6db049;
        border-radius: 5mm;
        padding: 3mm;
        text-align: center;
    }

    .qr-wrap img {
        width: 48mm;
        height: 48mm;
    }

    .scan {
        margin-top: 4mm;
        font-size: 15px;
        font-weight: bold;
        text-transform: uppercase;
        color: #6db049;
        letter-spacing: 1px;
    }

    .link {
        margin-top: 2mm;
        font-size: 7px;
        color: #6c757d;
    }

    .info-strip {
        margin: 7mm auto 0 auto;
        width: 150mm;
        background: #ffffff;
        border-radius: 6mm;
        padding: 4mm;
        border: 1px solid #dee2e6;
    }

    .info-strip table {
        width: 100%;
        border-collapse: collapse;
    }

    .info-strip td {
        width: 33.333%;
        text-align: center;
        font-size: 9px;
        color: #495057;
        padding: 1.5mm;
    }

    .info-strip strong {
        display: block;
        color: #1a1a1a;
        font-size: 10px;
        margin-bottom: 1mm;
    }

    .footer {
        margin-top: 8mm;
        background: #1a1a1a;
        color: #ffffff;
        text-align: center;
        padding: 6mm;
        border-radius: 5mm;
    }

    .footer-title {
        font-size: 13px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #ebe954;
        margin-bottom: 2mm;
    }

    .footer-text {
        font-size: 9px;
        color: #ffffff;
    }
</style>
</head>

<body>

<div class="page">

    <div class="top">
        <div class="brand">EatFree</div>
        <div class="brand-sub">Real Meals • Real Shops • Real Impact</div>
    </div>

    <div class="gold-line"></div>

    <div class="content">

        <div class="vendor-pill">
            <?= htmlspecialchars($vendorName) ?>
        </div>

        <div class="headline">
            Scan<br>
            <span>To Eat</span><br>
            Today
        </div>

        <div class="promo">
            <?= htmlspecialchars($promoText) ?><br>
            Scan this QR code and claim R5 discount through EatFree.
        </div>

        <div class="qr-wrap">
            <img src="<?= htmlspecialchars($qrImage) ?>" alt="EatFree QR Code">
        </div>

        <div class="scan">Scan QR Code</div>

        <div class="link">
            <?= htmlspecialchars($qrLink) ?>
        </div>

        <div class="info-strip">
            <table>
                <tr>
                    <td>
                        <strong>R15 Meal</strong>
                        Customer access
                    </td>
                    <td>
                        <strong>Approved Shop</strong>
                        EatFree vendor
                    </td>
                    <td>
                        <strong>Local Impact</strong>
                        Community meals
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <div class="footer-title">Powered by EatFree</div>
            <div class="footer-text">
                <?= htmlspecialchars($vendorCity ?: 'South Africa') ?> • Anywhere. Anytime. Forever.
            </div>
        </div>

    </div>

</div>

</body>
</html>