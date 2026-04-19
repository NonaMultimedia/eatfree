<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$vendorId = (int)($data['vendor_id'] ?? 0);

if ($vendorId <= 0) {
    jsonResponse(false, null, 'Invalid vendor');
}

try {
    $db = getDB();

    $capacity = calculateEcosystemCapacity();

    if ($capacity['available_slots'] <= 0) {
        jsonResponse(false, null, 'No wallet capacity available');
    }

    $settings = getEcosystemSettings();

    $stmt = $db->prepare("
        UPDATE vendors
            SET status='approved',
            subscription_status='active',
            is_active=1,
            meals_remaining=?,
            wallet_balance=0
        WHERE id=?
    ");

    $stmt->execute([
        $settings['meals_per_vendor'],
        $vendorId
    ]);

    $stmt = $db->prepare("
        UPDATE vendor_queue
        SET status='approved'
        WHERE vendor_id=?
    ");

    $stmt->execute([$vendorId]);

    jsonResponse(true, null, 'Vendor approved successfully');

} catch (Exception $e) {
    jsonResponse(false, null, 'Approval failed');
}
?>