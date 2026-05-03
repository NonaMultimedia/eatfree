<?php
//USE THIS FILE TO TEST PDF FUNCTIONALITY ONLY!!

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();

$dompdf->loadHtml("<h1>EatFree PDF Test</h1><p>If you see this, it works.</p>");
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

//REMOVE HTTP 500 ERROR COMPLETELY
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="test.pdf"');

echo $dompdf->output();
exit;

?>