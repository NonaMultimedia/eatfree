<?php
require_once __DIR__ . '/../config/config.php';

startSession();

header('Content-Type: application/json');

if (!isset($_SESSION['vendor_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

try {

    $db = getDB();

    $stmt = $db->prepare("
        UPDATE vendors
        SET onboarding_seen = 1
        WHERE id = ?
    ");

    $stmt->execute([$_SESSION['vendor_id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false]);
}