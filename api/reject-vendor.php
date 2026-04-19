<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();

$data = json_decode(file_get_contents("php://input"), true);
$id = (int)($data['vendor_id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, null, 'Invalid vendor');
}

try {
    $db = getDB();

    $stmt = $db->prepare("
        UPDATE vendors
        SET status='rejected', is_active=0
        WHERE id=?
    ");
    $stmt->execute([$id]);

    $stmt = $db->prepare("
        UPDATE vendor_queue
        SET status='rejected'
        WHERE vendor_id=?
    ");
    $stmt->execute([$id]);

    jsonResponse(true, null, 'Vendor rejected');

} catch (Exception $e) {
    jsonResponse(false, null, 'Rejection failed');
}
?>