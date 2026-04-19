<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();

$db = getDB();
$stmt = $db->query("
    SELECT mc.*, b.full_name as beneficiary_name, v.business_name as vendor_name, vo.voucher_code
    FROM meal_claims mc
    JOIN beneficiaries b ON mc.beneficiary_id = b.id
    JOIN vendors v ON mc.vendor_id = v.id
    JOIN vouchers vo ON mc.voucher_id = vo.id
    ORDER BY mc.claimed_at DESC
    LIMIT 100
");
$claims = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Claims | EatFree Admin</title>
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
                <a href="claims.php" class="admin-sidebar-item active"><i class="bi bi-ticket-perforated"></i> Meal Claims</a>
                <a href="ecosystem.php" class="admin-sidebar-item"><i class="bi bi-diagram-3"></i> Ecosystem</a>
                <a href="settings.php" class="admin-sidebar-item"><i class="bi bi-gear"></i> Settings</a>
                <a href="#" onclick="logout()" class="admin-sidebar-item"><i class="bi bi-box-arrow-left"></i> Logout</a>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <h4 class="m-0">Meal Claims</h4>
                <span class="text-muted"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
            </header>
            <div class="admin-content">
                <div class="card">
                    <div class="card-header">All Meal Claims</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Beneficiary</th>
                                    <th>Vendor</th>
                                    <th>Voucher</th>
                                    <th>Amount</th>
                                    <th>Subsidy</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($claims as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['beneficiary_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['vendor_name']); ?></td>
                                    <td><code><?php echo $c['voucher_code']; ?></code></td>
                                    <td>R<?php echo number_format($c['meal_price'], 2); ?></td>
                                    <td class="text-success">+R<?php echo number_format($c['subsidy_amount'], 2); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($c['claimed_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>function logout() { fetch('../api/logout.php').then(() => location.href = '/admin-login.php'); }</script>
</body>
</html>
