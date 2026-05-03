<?php

require_once __DIR__ . '/../config/config.php';
startSession();

header('Content-Type: application/json');

if (!isset($_SESSION['vendor_id'])) {
    echo json_encode(['success'=>false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$step = (int)($data['step'] ?? 0);

try {

    $db = getDB();

    $stmt = $db->prepare("
        UPDATE vendors 
        SET onboarding_step = ?
        WHERE id = ?
    ");

    $stmt->execute([$step, $_SESSION['vendor_id']]);

    echo json_encode(['success'=>true]);

} catch(Exception $e){
    error_log($e->getMessage());
    echo json_encode(['success'=>false]);
}