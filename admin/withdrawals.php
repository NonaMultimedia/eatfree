<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();

$db = getDB();
$status = $_GET['status'] ?? 'pending';

$stmt = $db->prepare("
    SELECT vw.*, v.business_name, v.bank_name, v.bank_account_number
    FROM vendor_withdrawals vw
    JOIN vendors v ON vw.vendor_id = v.id
    WHERE vw.status = :status
    ORDER BY vw.created_at DESC
");
$stmt->execute([':status' => $status]);
$withdrawals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals | EatFree Admin</title>
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
                <a href="withdrawals.php" class="admin-sidebar-item active"><i class="bi bi-cash-stack"></i> Withdrawals</a>
                <a href="donations.php" class="admin-sidebar-item"><i class="bi bi-heart"></i> Donations</a>
                <a href="claims.php" class="admin-sidebar-item"><i class="bi bi-ticket-perforated"></i> Meal Claims</a>
                <a href="ecosystem.php" class="admin-sidebar-item"><i class="bi bi-diagram-3"></i> Ecosystem</a>
                <a href="settings.php" class="admin-sidebar-item"><i class="bi bi-gear"></i> Settings</a>
                <a href="#" onclick="logout()" class="admin-sidebar-item"><i class="bi bi-box-arrow-left"></i> Logout</a>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <h4 class="m-0">Withdrawals</h4>
                <span class="text-muted"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
            </header>
            <div class="admin-content">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Withdrawal Requests</span>
                        <div class="btn-group">
                            <a href="?status=pending" class="btn btn-sm btn-outline-warning <?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
                            <a href="?status=approved" class="btn btn-sm btn-outline-success <?php echo $status === 'approved' ? 'active' : ''; ?>">Approved</a>
                            <a href="?status=processed" class="btn btn-sm btn-outline-primary <?php echo $status === 'processed' ? 'active' : ''; ?>">Processed</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Vendor</th>
                                    <th>Amount</th>
                                    <th>Bank</th>
                                    <th>Account</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $w): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($w['business_name']); ?></td>
                                    <td>R<?php echo number_format($w['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($w['bank_name'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($w['bank_account_number'] ?: '-', -4)); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($w['created_at'])); ?></td>
                                    <td>
                                        <?php if ($w['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approve(<?php echo $w['id']; ?>)">Approve</button>
                                        <button class="btn btn-sm btn-danger" onclick="reject(<?php echo $w['id']; ?>)">Reject</button>
                                        <?php endif; ?>
                                    </td>
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
    <script>
        function logout() { fetch('../api/logout.php').then(() => location.href = '/admin-login.php'); }
        function approve(id) { if(confirm('Approve this withdrawal?')) alert('Approve: ' + id); }
        function reject(id) { if(confirm('Reject this withdrawal?')) alert('Reject: ' + id); }
    </script>
</body>
</html>
