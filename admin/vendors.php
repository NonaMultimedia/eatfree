<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();

$db = getDB();
$status = $_GET['status'] ?? 'all';

$where = "WHERE 1=1";
if ($status !== 'all') {
    $where .= " AND status = :status";
}

$stmt = $db->prepare("SELECT * FROM vendors $where ORDER BY created_at DESC");
if ($status !== 'all') {
    $stmt->bindValue(':status', $status);
}
$stmt->execute();
$vendors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendors | EatFree Admin</title>
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
        .badge-pending { background: #ffc107; color: #000; }
        .badge-approved { background: #198754; color: white; }
        .badge-rejected { background: #dc3545; color: white; }
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
                <a href="vendors.php" class="admin-sidebar-item active"><i class="bi bi-shop"></i> Vendors</a>
                <a href="withdrawals.php" class="admin-sidebar-item"><i class="bi bi-cash-stack"></i> Withdrawals</a>
                <a href="donations.php" class="admin-sidebar-item"><i class="bi bi-heart"></i> Donations</a>
                <a href="claims.php" class="admin-sidebar-item"><i class="bi bi-ticket-perforated"></i> Meal Claims</a>
                <a href="ecosystem.php" class="admin-sidebar-item"><i class="bi bi-diagram-3"></i> Ecosystem</a>
                <a href="settings.php" class="admin-sidebar-item"><i class="bi bi-gear"></i> Settings</a>
                <a href="#" onclick="logout()" class="admin-sidebar-item"><i class="bi bi-box-arrow-left"></i> Logout</a>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <h4 class="m-0">Vendors</h4>
                <span class="text-muted"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
            </header>
            <div class="admin-content">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>All Vendors</span>
                        <div class="btn-group">
                            <a href="?status=all" class="btn btn-sm btn-outline-secondary <?php echo $status === 'all' ? 'active' : ''; ?>">All</a>
                            <a href="?status=pending" class="btn btn-sm btn-outline-warning <?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
                            <a href="?status=approved" class="btn btn-sm btn-outline-success <?php echo $status === 'approved' ? 'active' : ''; ?>">Approved</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Business</th>
                                    <th>Email</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Meals</th>
                                    <th>Wallet</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $vendor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vendor['business_name']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['city']); ?></td>
                                    <td><span class="badge badge-<?php echo $vendor['status']; ?>"><?php echo ucfirst($vendor['status']); ?></span></td>
                                    <td><?php echo $vendor['meals_remaining']; ?></td>
                                    <td>R<?php echo number_format($vendor['wallet_balance'], 2); ?></td>
                                    <td>
                                        <?php if ($vendor['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approve(<?php echo $vendor['id']; ?>)">Approve</button>
                                        <button class="btn btn-sm btn-danger" onclick="reject(<?php echo $vendor['id']; ?>)">Reject</button>
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
        <script>
        function logout() {
            fetch('../api/logout.php')
            .then(() => location.href = '/admin-login.php');
        }
        
        function approve(id) {
            if (!confirm('Approve this vendor?')) return;
        
            fetch('../api/approve-vendor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    vendor_id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(() => {
                alert('Approval failed');
            });
        }
        
        function reject(id) {
            if (!confirm('Reject this vendor?')) return;
        
            fetch('../api/reject-vendor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    vendor_id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(() => {
                alert('Rejection failed');
            });
        }
</script>
    </script>
</body>
</html>
