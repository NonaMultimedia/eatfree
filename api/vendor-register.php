<?php
/**
 * Vendor Registration API (FIXED: NO AUTO-APPROVAL)
 * All vendors now ALWAYS go through admin approval OR queue.
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

$data = $_POST;

/**
 * Validate required fields
 */
$required = ['business_name', 'email', 'password', 'phone', 'address', 'city', 'province'];

foreach ($required as $field) {
    if (empty($data[$field])) {
        jsonResponse(false, null, "Missing required field: $field");
    }
}

/**
 * Email validation
 */
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, null, 'Invalid email address');
}

/**
 * Password validation
 */
if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
    jsonResponse(false, null, 'Password too short');
}

/**
 * R20 rule enforcement
 */
if (empty($data['agrees_r20']) || $data['agrees_r20'] !== 'on') {
    jsonResponse(false, null, 'Must agree to R20 meals rule');
}

try {
    $db = getDB();

    /**
     * Check duplicate email
     */
    $stmt = $db->prepare("SELECT id FROM vendors WHERE email = ?");
    $stmt->execute([$data['email']]);

    if ($stmt->fetch()) {
        jsonResponse(false, null, 'Email already registered');
    }

    /**
     * Upload logo
     */
    $logoPath = null;

    if (!empty($_FILES['logo']['name'])) {
        $logo = $_FILES['logo'];

        if (!in_array($logo['type'], ALLOWED_LOGO_TYPES)) {
            jsonResponse(false, null, 'Invalid logo type');
        }

        if ($logo['size'] > UPLOAD_MAX_SIZE) {
            jsonResponse(false, null, 'Logo too large');
        }

        if (!is_dir(LOGO_UPLOAD_PATH)) {
            mkdir(LOGO_UPLOAD_PATH, 0755, true);
        }

        $filename = 'logo_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($logo['name'], PATHINFO_EXTENSION);
        $destination = LOGO_UPLOAD_PATH . $filename;

        move_uploaded_file($logo['tmp_name'], $destination);

        $logoPath = LOGO_WEB_PATH . $filename;
    }

    /**
     * Upload documents
     */
    $docPath = null;

    if (!empty($_FILES['documents']['name'])) {
        $doc = $_FILES['documents'];

        if (!in_array($doc['type'], ALLOWED_DOC_TYPES)) {
            jsonResponse(false, null, 'Invalid document type');
        }

        if ($doc['size'] > UPLOAD_MAX_SIZE) {
            jsonResponse(false, null, 'Document too large');
        }

        if (!is_dir(DOC_UPLOAD_PATH)) {
            mkdir(DOC_UPLOAD_PATH, 0755, true);
        }

        $filename = 'doc_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($doc['name'], PATHINFO_EXTENSION);
        $destination = DOC_UPLOAD_PATH . $filename;

        move_uploaded_file($doc['tmp_name'], $destination);

        $docPath = DOC_WEB_PATH . $filename;
    }

    /**
     * Hash password
     */
    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

    /**
     * IMPORTANT FIX:
     * ❌ NO AUTO APPROVAL
     * ✔ ALL VENDORS START AS "PENDING"
     */
    $status = 'pending';

    /**
     * Insert vendor
     */
    $stmt = $db->prepare("
        INSERT INTO vendors (
            business_name, email, password_hash, phone,
            registration_number, tax_number,
            bank_name, bank_account_number, bank_branch_code, bank_account_holder,
            address, city, province, postal_code,
            logo_path, documents_path,
            latitude, longitude,
            status, agrees_to_r20_meals
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            ?, ?,
            ?, ?
        )
    ");

    $stmt->execute([
        sanitize($data['business_name']),
        sanitize($data['email']),
        $passwordHash,
        sanitize($data['phone']),

        sanitize($data['registration_number'] ?? ''),
        sanitize($data['tax_number'] ?? ''),

        sanitize($data['bank_name'] ?? ''),
        sanitize($data['bank_account_number'] ?? ''),
        sanitize($data['bank_branch_code'] ?? ''),
        sanitize($data['bank_account_holder'] ?? ''),

        sanitize($data['address']),
        sanitize($data['city']),
        sanitize($data['province']),
        sanitize($data['postal_code'] ?? ''),

        $logoPath,
        $docPath,

        $data['latitude'] ?? null,
        $data['longitude'] ?? null,

        $status,
        1
    ]);

    $vendorId = $db->lastInsertId();

    /**
     * ALWAYS ADD TO QUEUE (NO EXCEPTION)
     */
    $stmt = $db->query("SELECT COUNT(*) as total FROM vendor_queue WHERE status = 'waiting'");
    $queue = $stmt->fetch();

    $queuePosition = ($queue['total'] ?? 0) + 1;

    $stmt = $db->prepare("
        INSERT INTO vendor_queue (vendor_id, queue_position, status)
        VALUES (?, ?, 'waiting')
    ");

    $stmt->execute([$vendorId, $queuePosition]);

    /**
     * OPTIONAL: LOG
     */
    logActivity('vendor_registration', 'New vendor registered (pending approval)', $vendorId, 'vendor');

    /**
     * RESPONSE
     */
    jsonResponse(true, [
        'vendor_id' => $vendorId,
        'status' => 'pending',
        'queue_position' => $queuePosition,
        'message' => 'Registration submitted for admin approval'
    ], 'Vendor registered successfully');

} catch (Exception $e) {
    error_log("Vendor registration error: " . $e->getMessage());
    jsonResponse(false, null, 'Registration failed');
}