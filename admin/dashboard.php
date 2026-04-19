<?php
/**
 * Admin Dashboard
 * Main dashboard for administrators
 */

require_once __DIR__ . '/../config/config.php';

// Require admin login
requireAdminLogin();

try {
    $db = getDB();
    
    // Get dashboard summary
    $stmt = $db->query("SELECT * FROM dashboard_summary LIMIT 1");
    $summary = $stmt->fetch();
    
    // Get pending vendors
    $stmt = $db->query("
        SELECT v.*, vq.queue_position 
        FROM vendors v 
        LEFT JOIN vendor_queue vq ON v.id = vq.vendor_id 
        WHERE v.status = 'pending' 
        ORDER BY v.created_at DESC 
        LIMIT 5
    ");
    $pendingVendors = $stmt->fetchAll();
    
    // Get pending withdrawals
    $stmt = $db->query("
        SELECT vw.*, v.business_name, v.email
        FROM vendor_withdrawals vw
        JOIN vendors v ON vw.vendor_id = v.id
        WHERE vw.status = 'pending'
        ORDER BY vw.created_at DESC
        LIMIT 5
    ");
    $pendingWithdrawals = $stmt->fetchAll();
    
    // Get recent donations
    $stmt = $db->query("
        SELECT * FROM donations 
        WHERE status = 'completed'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentDonations = $stmt->fetchAll();
    
    // Get ecosystem capacity
    $capacity = calculateEcosystemCapacity();
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $error = "Failed to load dashboard data";
}
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a1a1a">
    <title>Admin Dashboard | EatFree</title>
    
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
            --ef-success: #198754;
            --ef-warning: #ffc107;
            --ef-danger: #dc3545;
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
        .stat-card {
            background: var(--ef-white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-icon.primary { background: rgba(109, 176, 73, 0.1); color: var(--ef-primary); }
        .stat-icon.success { background: rgba(25, 135, 84, 0.1); color: var(--ef-success); }
        .stat-icon.warning { background: rgba(255, 193, 7, 0.1); color: #b8a800; }
        .stat-icon.info { background: rgba(74, 144, 217, 0.1); color: #4a90d9; }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--ef-gray-900);
        }
        .stat-label {
            font-size: 0.875rem;
            color: var(--ef-gray-500);
        }
        .admin-card {
            background: var(--ef-white);
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: none;
        }
        .admin-card-header {
            background: transparent;
            border-bottom: 1px solid var(--ef-gray-200);
            padding: 1.25rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ecosystem-alert {
            background: linear-gradient(135deg, var(--ef-dark) 0%, var(--ef-gray-900) 100%);
            color: var(--ef-white);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .ecosystem-alert.warning {
            background: linear-gradient(135deg, #b8a800 0%, #d4d24a 100%);
            color: var(--ef-dark);
        }
        .progress {
            height: 12px;
            border-radius: 6px;
            background: rgba(255,255,255,0.2);
        }
        .progress-bar {
            background: var(--ef-primary);
            border-radius: 6px;
        }
        .table-action-btn {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            border: none;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .btn-approve { background: var(--ef-success); color: white; }
        .btn-reject { background: var(--ef-danger); color: white; }
        .btn-view { background: var(--ef-gray-300); color: var(--ef-gray-700); }
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pending { background: var(--ef-warning); color: var(--ef-dark); }
        .badge-approved { background: var(--ef-success); color: white; }
        .badge-rejected { background: var(--ef-danger); color: white; }
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
                <a href="dashboard.php" class="admin-sidebar-item active">
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
                <a href="ecosystem.php" class="admin-sidebar-item">
                    <i class="bi bi-diagram-3"></i> Ecosystem
                </a>
                <a href="settings.php" class="admin-sidebar-item">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="" onclick="logout()" class="admin-sidebar-item">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Topbar -->
            <header class="admin-topbar">
                <h4 class="m-0">Dashboard</h4>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                    <span class="badge bg-dark"><?php echo ucfirst($_SESSION['admin_role']); ?></span>
                </div>
            </header>

            <!-- Content -->
            <div class="admin-content">
                <?php if ($capacity['available_slots'] <= 0 && $capacity['waiting_vendors'] > 0): ?>
                <!-- Ecosystem Warning -->
                <div class="ecosystem-alert warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Ecosystem at Capacity</h5>
                            <p class="mb-0"><?php echo $capacity['waiting_vendors']; ?> vendors waiting for approval. More donations needed.</p>
                        </div>
                        <a href="ecosystem.php" class="btn btn-dark">View Ecosystem</a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats Row -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon primary">
                                <i class="bi bi-shop"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?php echo $summary['total_vendors']; ?></div>
                                <div class="stat-label">Total Vendors</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon success">
                                <i class="bi bi-heart"></i>
                            </div>
                            <div>
                                <div class="stat-value">R<?php echo number_format($summary['total_donations'], 0); ?></div>
                                <div class="stat-label">Total Donations</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon info">
                                <i class="bi bi-utensils"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?php echo number_format($summary['total_meals_served']); ?></div>
                                <div class="stat-label">Meals Served</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon warning">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div>
                                <div class="stat-value">R<?php echo number_format($summary['wallet_balance'], 0); ?></div>
                                <div class="stat-label">Wallet Balance</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ecosystem Status -->
                <div class="ecosystem-alert mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h5 class="mb-1">Ecosystem Status</h5>
                            <p class="mb-0 opacity-75">Meals until Full Free mode</p>
                            <h3 class="mb-0"><?php echo number_format($capacity['meals_until_free']); ?></h3>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <p class="mb-1">Progress to Target</p>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo min(100, $capacity['percent_complete']); ?>%"></div>
                                </div>
                                <small class="opacity-75"><?php echo round($capacity['percent_complete'], 1); ?>% Complete</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <p class="mb-1">Available Vendor Slots</p>
                            <h4 class="mb-0"><?php echo $capacity['available_slots']; ?></h4>
                            <small class="opacity-75"><?php echo $capacity['waiting_vendors']; ?> in queue</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Pending Vendors -->
                    <div class="col-lg-6 mb-4">
                        <div class="admin-card">
                            <div class="admin-card-header">
                                <span><i class="bi bi-shop me-2"></i>Pending Vendors</span>
                                <a href="vendors.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($pendingVendors)): ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-check-circle fs-1 text-success"></i>
                                    <p class="mt-2">No pending vendors</p>
                                </div>
                                <?php else: ?>
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Business</th>
                                            <th>Location</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingVendors as $vendor): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($vendor['business_name']); ?></td>
                                            <td><?php echo htmlspecialchars($vendor['city']); ?></td>
                                            <td>
                                                <button class="table-action-btn btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">Approve</button>
                                                <button class="table-action-btn btn-reject" onclick="rejectVendor(<?php echo $vendor['id']; ?>)">Reject</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Withdrawals -->
                    <div class="col-lg-6 mb-4">
                        <div class="admin-card">
                            <div class="admin-card-header">
                                <span><i class="bi bi-cash-stack me-2"></i>Pending Withdrawals</span>
                                <a href="withdrawals.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($pendingWithdrawals)): ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-check-circle fs-1 text-success"></i>
                                    <p class="mt-2">No pending withdrawals</p>
                                </div>
                                <?php else: ?>
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vendor</th>
                                            <th>Amount</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($withdrawal['business_name']); ?></td>
                                            <td>R<?php echo number_format($withdrawal['amount'], 2); ?></td>
                                            <td>
                                                <button class="table-action-btn btn-approve" onclick="approveWithdrawal(<?php echo $withdrawal['id']; ?>)">Approve</button>
                                                <button class="table-action-btn btn-reject" onclick="rejectWithdrawal(<?php echo $withdrawal['id']; ?>)">Reject</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Donations -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <span><i class="bi bi-heart me-2"></i>Recent Donations</span>
                        <a href="donations.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentDonations)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-inbox fs-1"></i>
                            <p class="mt-2">No donations yet</p>
                        </div>
                        <?php else: ?>
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Donor</th>
                                    <th>Amount</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDonations as $donation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($donation['donor_name'] ?: 'Anonymous'); ?></td>
                                    <td>R<?php echo number_format($donation['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(substr($donation['message'] ?: '-', 0, 50)); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($donation['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const API_BASE = '../api/';
        
        function logout() {
            fetch('../api/logout.php', {
                method: 'POST',
                credentials: 'include'
            })
            .finally(() => {
                window.location.href = '/admin-login.php';
            });
        }
        
        function approveVendor(id) {
            if (!confirm('Approve this vendor?')) return;
        
            fetch('../api/approve-vendor.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_id: id })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(err => alert('Request failed'));
        }
        
        function rejectVendor(id) {
            if (!confirm('Reject this vendor?')) return;
        
            fetch('../api/reject-vendor.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_id: id })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(err => alert('Request failed'));
        }
        
        function approveWithdrawal(id){
            alert('Need withdrawal API next');
        }
        
        function rejectWithdrawal(id){
            alert('Need withdrawal API next');
        }
    </script>
</body>
</html>
