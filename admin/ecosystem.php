<?php
/**
 * Ecosystem Manager
 * Admin tool for managing the vendor-donation balance
 */

require_once __DIR__ . '/../config/config.php';

// Require admin login
requireAdminLogin();

// Handle form submission for updating settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE ecosystem_settings SET
                meal_price = ?,
                subsidy_amount = ?,
                meals_per_vendor = ?,
                min_withdrawal = ?,
                vendor_subscription_amount = ?,
                vendor_subscription_tax = ?,
                target_threshold = ?,
                system_costs = ?
            WHERE id = 1
        ");
        $stmt->execute([
            (float)$_POST['meal_price'],
            (float)$_POST['subsidy_amount'],
            (int)$_POST['meals_per_vendor'],
            (float)$_POST['min_withdrawal'],
            (float)$_POST['vendor_subscription_amount'],
            (float)$_POST['vendor_subscription_tax'],
            (float)$_POST['target_threshold'],
            (float)$_POST['system_costs']
        ]);
        
        $successMessage = "Settings updated successfully";
    } catch (Exception $e) {
        $errorMessage = "Failed to update settings: " . $e->getMessage();
    }
}

try {
    $db = getDB();
    
    // Get ecosystem settings
    $settings = getEcosystemSettings();
    
    // Get capacity calculations
    $capacity = calculateEcosystemCapacity();
    
    // Get vendor queue
    $stmt = $db->query("
        SELECT vq.*, v.business_name, v.email, v.city, v.province, v.created_at as registered_at
        FROM vendor_queue vq
        JOIN vendors v ON vq.vendor_id = v.id
        WHERE vq.status = 'waiting'
        ORDER BY vq.queue_position ASC
    ");
    $vendorQueue = $stmt->fetchAll();
    
    // Get approved vendors count
    $stmt = $db->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'approved' AND is_active = 1");
    $approvedVendors = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    error_log("Ecosystem manager error: " . $e->getMessage());
    $error = "Failed to load ecosystem data";
}
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a1a1a">
    <title>Ecosystem Manager | EatFree Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom Fonts -->
    <link rel="stylesheet" href="../assets/css/Cooper%20Black%20Regular.css">
    <link rel="stylesheet" href="../assets/css/Montserrat.css">
    
    <style>
        :root {
            --ef-primary: #6db049;
            --ef-primary-dark: #5a9a3d;
            --ef-secondary: #ebe954;
            --ef-dark: #1a1a1a;
            --ef-gray-900: #212529;
            --ef-gray-700: #495057;
            --ef-gray-500: #6c757d;
            --ef-gray-300: #dee2e6;
            --ef-gray-100: #f8f9fa;
            --ef-white: #ffffff;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 280px;
            background: var(--ef-dark);
            color: var(--ef-white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .admin-sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .admin-sidebar-brand a {
            color: var(--ef-white);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .admin-sidebar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--ef-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-sidebar-nav {
            padding: 1rem 0;
        }
        .admin-sidebar-item {
            padding: 0.875rem 1.5rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        .admin-sidebar-item:hover, .admin-sidebar-item.active {
            background: rgba(255,255,255,0.1);
            color: var(--ef-white);
            border-left-color: var(--ef-primary);
        }
        .admin-sidebar-item i {
            font-size: 1.25rem;
            width: 24px;
        }
        .admin-main {
            flex: 1;
            margin-left: 280px;
            background: var(--ef-gray-100);
            min-height: 100vh;
        }
        .admin-topbar {
            background: var(--ef-white);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .admin-content {
            padding: 2rem;
        }
        .eco-card {
            background: var(--ef-white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            height: 100%;
        }
        .eco-card.dark {
            background: var(--ef-dark);
            color: var(--ef-white);
        }
        .eco-value {
            font-family: 'Cooper Black Regular', serif;
            font-size: 2.5rem;
            color: var(--ef-primary);
        }
        .eco-value.white {
            color: var(--ef-white);
        }
        .eco-label {
            font-size: 0.875rem;
            color: var(--ef-gray-500);
        }
        .eco-label.white {
            color: rgba(255,255,255,0.7);
        }
        .formula-box {
            background: var(--ef-gray-100);
            border-radius: 8px;
            padding: 1rem;
            font-family: monospace;
            margin: 1rem 0;
        }
        .formula-box.dark {
            background: rgba(255,255,255,0.1);
        }
        .progress {
            height: 20px;
            border-radius: 10px;
            background: var(--ef-gray-300);
        }
        .progress-bar {
            background: var(--ef-primary);
            border-radius: 10px;
        }
        .queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--ef-gray-200);
        }
        .queue-item:last-child {
            border-bottom: none;
        }
        .queue-position {
            width: 32px;
            height: 32px;
            background: var(--ef-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .btn-approve-queue {
            background: var(--ef-primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-brand">
                <a href="dashboard.php">
                    <span class="brand-icon"><i class="bi bi-heart-fill"></i></span>
                    <span style="font-family: 'Cooper Black Regular', serif;">EatFree Admin</span>
                </a>
            </div>
            <nav class="admin-sidebar-nav">
                <a href="dashboard.php" class="admin-sidebar-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="vendors.php" class="admin-sidebar-item">
                    <i class="bi bi-shop"></i> Vendors
                </a>
                <a href="withdrawals.php" class="admin-sidebar-item">
                    <i class="bi bi-cash-stack"></i> Withdrawals
                </a>
                <a href="donations.php" class="admin-sidebar-item">
                    <i class="bi bi-heart"></i> Donations
                </a>
                <a href="claims.php" class="admin-sidebar-item">
                    <i class="bi bi-ticket-perforated"></i> Meal Claims
                </a>
                <a href="ecosystem.php" class="admin-sidebar-item active">
                    <i class="bi bi-diagram-3"></i> Ecosystem
                </a>
                <a href="settings.php" class="admin-sidebar-item">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="#" onclick="logout()" class="admin-sidebar-item">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Topbar -->
            <header class="admin-topbar">
                <h4 class="m-0">Ecosystem Manager</h4>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                </div>
            </header>

            <!-- Content -->
            <div class="admin-content">
                <?php if (isset($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Key Metrics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="eco-card dark">
                            <div class="eco-label white">Wallet Balance</div>
                            <div class="eco-value white">R<?php echo number_format($capacity['wallet_balance'], 0); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="eco-card">
                            <div class="eco-label">Total Meals Available</div>
                            <div class="eco-value"><?php echo number_format($capacity['total_meals_available']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="eco-card">
                            <div class="eco-label">Max Vendors</div>
                            <div class="eco-value"><?php echo number_format($capacity['max_vendors']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="eco-card">
                            <div class="eco-label">Available Slots</div>
                            <div class="eco-value" style="color: <?php echo $capacity['available_slots'] > 0 ? 'var(--ef-success)' : 'var(--ef-danger)'; ?>">
                                <?php echo number_format($capacity['available_slots']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress to Full Free Mode -->
                <div class="eco-card dark mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-2"><i class="bi bi-bullseye me-2"></i>Progress to Full Free Mode</h5>
                            <p class="mb-0 opacity-75">Target: R<?php echo number_format($capacity['target_threshold'], 0); ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="opacity-75"><?php echo round($capacity['percent_complete'], 1); ?>% Complete</span>
                                <span class="opacity-75"><?php echo number_format($capacity['meals_until_free']); ?> meals until free</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: <?php echo min(100, $capacity['percent_complete']); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Ecosystem Logic -->
                    <div class="col-lg-6 mb-4">
                        <div class="eco-card">
                            <h5 class="mb-3"><i class="bi bi-calculator me-2"></i>Ecosystem Logic</h5>
                            
                            <div class="formula-box">
                                <strong>Total Meals Available</strong><br>
                                = Wallet Balance ÷ Subsidy Amount<br>
                                = R<?php echo number_format($capacity['wallet_balance'], 2); ?> ÷ R<?php echo $settings['subsidy_amount']; ?><br>
                                = <?php echo number_format($capacity['total_meals_available']); ?> meals
                            </div>
                            
                            <div class="formula-box">
                                <strong>Max Vendors</strong><br>
                                = Total Meals ÷ Meals per Vendor<br>
                                = <?php echo number_format($capacity['total_meals_available']); ?> ÷ <?php echo $settings['meals_per_vendor']; ?><br>
                                = <?php echo number_format($capacity['max_vendors']); ?> vendors
                            </div>
                            
                            <div class="formula-box">
                                <strong>Available Slots</strong><br>
                                = Max Vendors - Approved Vendors<br>
                                = <?php echo number_format($capacity['max_vendors']); ?> - <?php echo $approvedVendors; ?><br>
                                = <?php echo number_format($capacity['available_slots']); ?> slots
                            </div>
                        </div>
                    </div>

                    <!-- Settings Form -->
                    <div class="col-lg-6 mb-4">
                        <div class="eco-card">
                            <h5 class="mb-3"><i class="bi bi-gear me-2"></i>Ecosystem Settings</h5>
                            
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
                                        <label class="form-label">Min Withdrawal (R)</label>
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
                                <button type="submit" class="btn btn-primary mt-3 w-100">
                                    <i class="bi bi-save me-2"></i>Save Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Vendor Queue -->
                <div class="eco-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-list-ol me-2"></i>Vendor Queue (<?php echo count($vendorQueue); ?> waiting)</h5>
                        <?php if ($capacity['available_slots'] > 0 && count($vendorQueue) > 0): ?>
                        <button class="btn btn-success" onclick="approveNextInQueue()">
                            <i class="bi bi-check-circle me-2"></i>Approve Next <?php echo min($capacity['available_slots'], count($vendorQueue)); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($vendorQueue)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-2">No vendors in queue</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Business</th>
                                    <th>Location</th>
                                    <th>Registered</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendorQueue as $vendor): ?>
                                <tr>
                                    <td><span class="queue-position"><?php echo $vendor['queue_position']; ?></span></td>
                                    <td><?php echo htmlspecialchars($vendor['business_name']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['city'] . ', ' . $vendor['province']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($vendor['registered_at'])); ?></td>
                                    <td>
                                        <?php if ($capacity['available_slots'] > 0 && $vendor['queue_position'] <= $capacity['available_slots']): ?>
                                        <button class="btn-approve-queue" onclick="approveVendor(<?php echo $vendor['vendor_id']; ?>)">
                                            <i class="bi bi-check me-1"></i>Approve
                                        </button>
                                        <?php else: ?>
                                        <span class="text-muted">Waiting for funds</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const API_BASE = '../api/';
        
        function logout() {
            fetch(API_BASE + 'logout.php')
                .then(() => window.location.href = '/admin-login.php')
                .catch(() => window.location.href = '/admin-login.php');
        }
        
        function approveVendor(id) {
            if (!confirm('Approve this vendor?')) return;
        
            fetch('../api/approve-vendor.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ vendor_id: id })
                })
            .then(res => res.json())
            .then(data =>
                {
                    alert(data.message);
                    if (data.success)
                    {
                        location.reload();
                    }
                })
                .catch(() => alert('Approval failed'));
            }}
            
        function approveNextInQueue() {
            if (!confirm('Approve next vendors in queue?')) return;
            // Implement batch approval
            alert('Batch approve (Implement API call)');
        }
    </script>
</body>
</html>
