<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();

$db = getDB();
$settings = getEcosystemSettings();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("
            UPDATE ecosystem_settings SET
                meal_price = ?, subsidy_amount = ?, meals_per_vendor = ?,
                min_withdrawal = ?, vendor_subscription_amount = ?,
                vendor_subscription_tax = ?, target_threshold = ?, system_costs = ?
            WHERE id = 1
        ");
        $stmt->execute([
            $_POST['meal_price'], $_POST['subsidy_amount'], $_POST['meals_per_vendor'],
            $_POST['min_withdrawal'], $_POST['vendor_subscription_amount'],
            $_POST['vendor_subscription_tax'], $_POST['target_threshold'], $_POST['system_costs']
        ]);
        $message = 'Settings saved successfully!';
        $settings = getEcosystemSettings();
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | EatFree Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/Cooper%20Black%20Regular.css">
    <style>
        :root { --ef-primary: #6db049; --ef-dark: #1a1a1a; --ef-gray-100: #f8f9fa; }
        .admin-wrapper { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 280px; background: var(--ef-dark); color: white; position: fixed; height: 100vh; }
        .admin-sidebar-item { padding: 0.875rem 1.5rem; color: rgba(255,255,255,0.7); text-decoration: none; display: flex; align-items: center; gap: 0.75rem; border-left: 3px solid transparent; }
        .admin-sidebar-item:hover, .admin-sidebar-item.active { background: rgba(255,255,255,0.1); color: white; border-left-color: var(--ef-primary); }
        .admin-main { flex: 1; margin-left: 280px; background: var(--ef-gray-100); min-height: 100vh; }
        .admin-topbar { background: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .admin-content { padding: 2rem; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="p-4 border-bottom border-secondary">
                <a href="dashboard.php" class="text-white text-decoration-none d-flex align-items-center gap-2">
                    <span class="bg-success rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="bi bi-heart-fill"></i></span>
                    <span style="font-family:'Cooper Black Regular',serif;">EatFree Admin</span>
                </a>
            </div>
            <nav class="py-3">
                <a href="dashboard.php" class="admin-sidebar-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="vendors.php" class="admin-sidebar-item"><i class="bi bi-shop"></i> Vendors</a>
                <a href="withdrawals.php" class="admin-sidebar-item"><i class="bi bi-cash-stack"></i> Withdrawals</a>
                <a href="donations.php" class="admin-sidebar-item"><i class="bi bi-heart"></i> Donations</a>
                <a href="claims.php" class="admin-sidebar-item"><i class="bi bi-ticket-perforated"></i> Meal Claims</a>
                <a href="ecosystem.php" class="admin-sidebar-item"><i class="bi bi-diagram-3"></i> Ecosystem</a>
                <a href="settings.php" class="admin-sidebar-item active"><i class="bi bi-gear"></i> Settings</a>
                <a href="#" onclick="logout()" class="admin-sidebar-item"><i class="bi bi-box-arrow-left"></i> Logout</a>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <h4 class="m-0">System Settings</h4>
                <span class="text-muted"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
            </header>
            <div class="admin-content">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo strpos($message, 'Error') === false ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">Business Rules</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Meal Price (R)</label>
                                    <input type="number" name="meal_price" class="form-control" value="<?php echo $settings['meal_price']; ?>" step="0.01">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Subsidy Amount (R)</label>
                                    <input type="number" name="subsidy_amount" class="form-control" value="<?php echo $settings['subsidy_amount']; ?>" step="0.01">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Meals per Vendor</label>
                                    <input type="number" name="meals_per_vendor" class="form-control" value="<?php echo $settings['meals_per_vendor']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Minimum Withdrawal (R)</label>
                                    <input type="number" name="min_withdrawal" class="form-control" value="<?php echo $settings['min_withdrawal']; ?>" step="0.01">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Subscription Amount (R)</label>
                                    <input type="number" name="vendor_subscription_amount" class="form-control" value="<?php echo $settings['vendor_subscription_amount']; ?>" step="0.01">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Subscription Tax (%)</label>
                                    <input type="number" name="vendor_subscription_tax" class="form-control" value="<?php echo $settings['vendor_subscription_tax']; ?>" step="0.01">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Target Threshold (R)</label>
                                    <input type="number" name="target_threshold" class="form-control" value="<?php echo $settings['target_threshold']; ?>" step="0.01">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">System Costs (R)</label>
                                    <input type="number" name="system_costs" class="form-control" value="<?php echo $settings['system_costs']; ?>" step="0.01">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-4">
                                <i class="bi bi-save me-2"></i>Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>function logout() { fetch('../api/logout.php').then(() => location.href = '/admin-login.php'); }</script>
</body>
</html>
