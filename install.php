<?php
/**
 * EatFree Installation Verification
 * Run this file to verify your installation
 */

// Check PHP version
$phpVersion = phpversion();
$phpOk = version_compare($phpVersion, '8.0.0', '>=');

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

// Check directories
$directories = [
    'uploads' => is_writable(__DIR__ . '/uploads'),
    'uploads/logos' => is_writable(__DIR__ . '/uploads/logos'),
    'uploads/documents' => is_writable(__DIR__ . '/uploads/documents'),
    'logs' => is_writable(__DIR__ . '/logs'),
];

// Check config file
$configExists = file_exists(__DIR__ . '/config/config.php');
$configReadable = is_readable(__DIR__ . '/config/config.php');

// Check if database credentials are set
$dbConfigured = false;
if ($configExists) {
    $configContent = file_get_contents(__DIR__ . '/config/config.php');
    $dbConfigured = strpos($configContent, "define('DB_HOST', '')") === false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EatFree Installation Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 2rem; }
        .check-card { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        .status-icon { font-size: 1.5rem; }
        .status-ok { color: #198754; }
        .status-fail { color: #dc3545; }
        .status-warn { color: #ffc107; }
    </style>
</head>
<body>
    <div class="check-card">
        <h1 class="mb-4"><i class="bi bi-heart-fill text-success"></i> EatFree Installation Check</h1>
        
        <h4>PHP Environment</h4>
        <table class="table">
            <tr>
                <td>PHP Version (>= 8.0)</td>
                <td><?php echo $phpVersion; ?></td>
                <td><span class="status-icon <?php echo $phpOk ? 'status-ok' : 'status-fail'; ?>"><?php echo $phpOk ? '✓' : '✗'; ?></span></td>
            </tr>
            <?php foreach ($requiredExtensions as $ext): ?>
            <tr>
                <td>Extension: <?php echo $ext; ?></td>
                <td><?php echo extension_loaded($ext) ? 'Installed' : 'Missing'; ?></td>
                <td><span class="status-icon <?php echo extension_loaded($ext) ? 'status-ok' : 'status-fail'; ?>"><?php echo extension_loaded($ext) ? '✓' : '✗'; ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h4>Directory Permissions</h4>
        <table class="table">
            <?php foreach ($directories as $dir => $writable): ?>
            <tr>
                <td><?php echo $dir; ?></td>
                <td><?php echo $writable ? 'Writable' : 'Not Writable'; ?></td>
                <td><span class="status-icon <?php echo $writable ? 'status-ok' : 'status-fail'; ?>"><?php echo $writable ? '✓' : '✗'; ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h4>Configuration</h4>
        <table class="table">
            <tr>
                <td>Config file exists</td>
                <td><?php echo $configExists ? 'Yes' : 'No'; ?></td>
                <td><span class="status-icon <?php echo $configExists ? 'status-ok' : 'status-fail'; ?>"><?php echo $configExists ? '✓' : '✗'; ?></span></td>
            </tr>
            <tr>
                <td>Config file readable</td>
                <td><?php echo $configReadable ? 'Yes' : 'No'; ?></td>
                <td><span class="status-icon <?php echo $configReadable ? 'status-ok' : 'status-fail'; ?>"><?php echo $configReadable ? '✓' : '✗'; ?></span></td>
            </tr>
            <tr>
                <td>Database configured</td>
                <td><?php echo $dbConfigured ? 'Yes' : 'No'; ?></td>
                <td><span class="status-icon <?php echo $dbConfigured ? 'status-ok' : 'status-warn'; ?>"><?php echo $dbConfigured ? '✓' : '!'; ?></span></td>
            </tr>
        </table>
        
        <?php if (!$dbConfigured): ?>
        <div class="alert alert-warning">
            <strong>Action Required:</strong> Please update your database credentials in <code>config/config.php</code>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($missingExtensions)): ?>
        <div class="alert alert-danger">
            <strong>Missing Extensions:</strong> Please install the following PHP extensions: <?php echo implode(', ', $missingExtensions); ?>
        </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="/" class="btn btn-primary">Go to Homepage</a>
            <a href="admin-login.php" class="btn btn-dark">Admin Login</a>
        </div>
    </div>
</body>
</html>
