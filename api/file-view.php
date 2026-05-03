<?php
/**
 * root/api/file-view.php
 *
 * Secure file preview endpoint for admin file viewing.
 *
 * Supports:
 * - uploads/documents
 * - uploads/logos
 *
 * Used by:
 * - root/admin/vendors.php
 */

require_once __DIR__ . '/../config/config.php';

requireAdminLogin();

$file = $_GET['file'] ?? '';

if ($file === '') {
    http_response_code(400);
    exit('Missing file');
}

/**
 * Normalize slashes and remove null bytes.
 */
$file = str_replace("\0", '', $file);
$file = str_replace('\\', '/', $file);

/**
 * Remove query fragments if accidentally included.
 */
$file = explode('?', $file)[0];
$file = explode('#', $file)[0];

/**
 * Allow paths like:
 * /uploads/logos/logo.jpg
 * uploads/logos/logo.jpg
 * /uploads/documents/doc.pdf
 * uploads/documents/doc.pdf
 */
$file = ltrim($file, '/');

$allowedPrefixes = [
    'uploads/documents/',
    'uploads/logos/'
];

$isAllowed = false;

foreach ($allowedPrefixes as $prefix) {
    if (strpos($file, $prefix) === 0) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    exit('File path not allowed');
}

$basePath = realpath(__DIR__ . '/..');

if ($basePath === false) {
    http_response_code(500);
    exit('Server path error');
}

$fullPath = realpath($basePath . '/' . $file);

if ($fullPath === false || !file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

/**
 * Prevent path traversal outside project root.
 */
if (strpos($fullPath, $basePath) !== 0) {
    http_response_code(403);
    exit('Invalid file path');
}

$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif'
];

if (!isset($mimeTypes[$extension])) {
    http_response_code(415);
    exit('Unsupported file type');
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
exit;