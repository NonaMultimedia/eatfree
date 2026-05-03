<?php
require_once __DIR__ . '/config.php';

function requireVendorSubscription($vendor) {

    if (!isset($vendor['subscription_status']) || $vendor['subscription_status'] !== 'active') {
        jsonResponse(false, null, 'Subscription required to access this feature');
        exit;
    }
}