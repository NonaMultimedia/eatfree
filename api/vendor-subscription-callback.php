<?php
/**
 * root/api/vendor-subscription-callback.php
 *
 * FIX 7:
 * This file must NOT activate memberships.
 *
 * Vendor subscription activation must only happen inside:
 * root/api/payfast-itn.php
 *
 * This endpoint is kept only to prevent old links/routes from
 * accidentally activating vendors without verified PayFast payment.
 */

require_once __DIR__ . '/../config/config.php';

http_response_code(403);

header('Content-Type: application/json');

jsonResponse(false, null, 'Direct subscription callbacks are disabled. Eatfree Memberships are activated only after verified PayFast ITN confirmation.');