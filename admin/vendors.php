<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();

$db = getDB();
$status = $_GET['status'] ?? 'all';

$where = "WHERE 1=1";

    if ($status !== 'all') {
        $where .= " AND v.status = :status";
    }
    
    /**
     * FIX 7:
     * Use the latest vendor subscription only.
     * vendor_subscriptions.payment_status uses 'paid', not 'completed'.
     */
    $stmt = $db->prepare("
        SELECT 
            v.*,
            latest_vs.payment_status AS latest_payment_status,
            latest_vs.subscription_end AS latest_subscription_end,
            latest_vs.id AS latest_subscription_id
        FROM vendors v
        LEFT JOIN (
            SELECT vs1.*
            FROM vendor_subscriptions vs1
            INNER JOIN (
                SELECT vendor_id, MAX(id) AS latest_id
                FROM vendor_subscriptions
                GROUP BY vendor_id
            ) latest
                ON latest.latest_id = vs1.id
        ) latest_vs
            ON latest_vs.vendor_id = v.id
        $where
        ORDER BY v.created_at DESC
    ");
    
    if ($status !== 'all') {
        $stmt->bindValue(':status', $status);
    }
    
    $stmt->execute();
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shops Keepers | EatFree Admin</title>
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
        .logo-thumb {width: 40px;height: 40px;object-fit: cover;border-radius: 6px;}
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
                <a href="vendors.php" class="admin-sidebar-item active"><i class="bi bi-shop"></i> Shops</a>
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
                <h4 class="m-0">Shops</h4>
                <span class="text-muted"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
            </header>
            <div class="admin-content">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>All Shops</span>
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
                                    <th>Logo</th>
                                    <th>Shop Name</th>
                                    <th>Email</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Membership</th>
                                    <th>Payment</th>
                                    <th>Wallet</th>
                                    <th>Profit</th>
                                    <th>Docs</th>
                                    <th>Actions</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $vendor): ?>
                                    <?php
                                        $latestPaymentStatus = $vendor['latest_payment_status'] ?? null;
                                        $subscriptionExpiresAt = $vendor['subscription_expires_at'] ?? null;
                                    
                                        $documentsUploaded = !empty($vendor['documents_path']);
                                        $logoUploaded = !empty($vendor['logo_path']);
                                        $kycApproved = (int)($vendor['kyc_verified'] ?? 0) === 1;
                                    
                                        $paymentVerified = (
                                            (int)($vendor['payment_verified'] ?? 0) === 1 &&
                                            ($vendor['subscription_status'] ?? '') === 'active' &&
                                            $latestPaymentStatus === 'paid' &&
                                            !empty($subscriptionExpiresAt) &&
                                            strtotime($subscriptionExpiresAt) >= strtotime(date('Y-m-d'))
                                        );
                                    
                                        $documentsApproved = (
                                            ($vendor['status'] ?? '') === 'approved' &&
                                            $kycApproved
                                        );
                                    
                                        $fullyActive = (
                                            $documentsApproved &&
                                            $paymentVerified
                                        );
                                    ?>
                                <tr 
                                    id="vendor-row-<?= (int)$vendor['id']; ?>"
                                    data-vendor-id="<?= (int)$vendor['id']; ?>"
                                    data-logo-viewed="0"
                                    data-docs-viewed="0"
                                    class="<?php echo (!$kycApproved && $documentsUploaded) ? 'table-warning' : ''; ?>"
                                >
                                     <!-- LOGO PREVIEW -->
                                    <td>
                                       <?php if ($logoUploaded): ?>
                                            
                                            <img 
                                                src="../api/file-view.php?file=<?= urlencode($vendor['logo_path']); ?>" 
                                                class="logo-thumb"
                                                style="cursor:pointer;"
                                                onclick="openVendorLogo(<?= (int)$vendor['id']; ?>, '<?= htmlspecialchars($vendor['logo_path'], ENT_QUOTES); ?>')"
                                                alt="Shop logo"
                                                onerror="this.style.display='none'; this.insertAdjacentHTML('afterend', '<span class=&quot;text-danger small&quot;>Logo error</span>');"
                                            >
                                            
                                        <?php else: ?>
                                            <span class="text-muted">No logo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($vendor['business_name']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['city']); ?></td>
                                    <td>
                                      <?php if ($fullyActive): ?>
                                            <span class="badge bg-success">Fully Active</span>
                                        <?php elseif ($documentsApproved && !$paymentVerified): ?>
                                            <span class="badge bg-warning text-dark">Docs Approved / Awaiting Payment</span>
                                        <?php elseif (!$documentsApproved && $paymentVerified): ?>
                                            <span class="badge bg-info text-dark">Paid / Needs Document Approval</span>
                                        <?php elseif (($vendor['status'] ?? '') === 'pending'): ?>
                                            <span class="badge bg-secondary">Pending Document Review</span>
                                        <?php elseif (($vendor['status'] ?? '') === 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark"><?php echo htmlspecialchars(ucfirst($vendor['status'])); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- MEMBERSHIP -->
                                    <td>
                                        <?php if ($paymentVerified): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif (($vendor['subscription_status'] ?? '') === 'active' && (int)($vendor['payment_verified'] ?? 0) !== 1): ?>
                                            <span class="badge bg-warning text-dark">Needs Verification</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Payment -->
                                    <td>
                                        <?php if ($latestPaymentStatus === 'paid'): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php elseif ($latestPaymentStatus === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif ($latestPaymentStatus === 'failed'): ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php elseif ($latestPaymentStatus === 'refunded'): ?>
                                            <span class="badge bg-dark">Refunded</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $vendor['meals_remaining']; ?></td>
                                    <td>R<?php echo number_format($vendor['wallet_balance'], 2); ?></td>
                                    
                                    <!-- DOCUMENT PREVIEW -->
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if ($logoUploaded): ?>
                                                <button 
                                                    class="btn btn-sm btn-outline-dark"
                                                    onclick="openVendorLogo(<?= (int)$vendor['id']; ?>, '<?= htmlspecialchars($vendor['logo_path'], ENT_QUOTES); ?>')">
                                                    Logo
                                                </button>
                                            <?php endif; ?>
                                        
                                            <?php if ($documentsUploaded): ?>
                                                <button 
                                                    class="btn btn-sm btn-dark"
                                                    onclick="openVendorDocs(<?= (int)$vendor['id']; ?>, '<?= htmlspecialchars($vendor['documents_path'], ENT_QUOTES); ?>')">
                                                    Docs
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">No docs</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <!-- Actions column logic -->
                                    <td>
                                        <?php if (($vendor['status'] ?? '') === 'pending'): ?>

                                            <?php if ($documentsUploaded): ?>
                                        
                                                <button 
                                                    id="approve-<?= (int)$vendor['id']; ?>"
                                                    class="btn btn-sm btn-secondary"
                                                    disabled
                                                    onclick="approve(<?= (int)$vendor['id']; ?>)">
                                                    Mark Docs Approved
                                                </button>
                                        
                                                <small class="d-block text-muted mt-1">
                                                    View docs first
                                                </small>
                                        
                                            <?php else: ?>
                                        
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    Missing Docs
                                                </button>
                                        
                                            <?php endif; ?>
                                        
                                            <button 
                                                class="btn btn-sm btn-danger mt-1"
                                                onclick="reject(<?= (int)$vendor['id']; ?>)">
                                                Reject
                                            </button>
                                        
                                        <?php elseif ($fullyActive): ?>
                                        
                                            <span class="badge bg-success">Live</span>
                                        
                                        <?php elseif ($documentsApproved && !$paymentVerified): ?>
                                        
                                            <span class="badge bg-warning text-dark">Awaiting Payment</span>
                                        
                                        <?php elseif (!$documentsApproved && $paymentVerified): ?>
                                        
                                            <button 
                                                id="approve-<?= (int)$vendor['id']; ?>"
                                                class="btn btn-sm btn-secondary"
                                                disabled
                                                onclick="approve(<?= (int)$vendor['id']; ?>)">
                                                Mark Docs Approved
                                            </button>
                                        
                                            <small class="d-block text-muted mt-1">
                                                View docs first
                                            </small>
                                        
                                        <?php else: ?>
                                        
                                            <span class="text-muted">No action</span>
                                        
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
    
    <!-- FILE VIEW MODAL -->
    <div class="modal fade" id="fileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
    
                <div class="modal-header">
                    <h5>Document Preview</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
    
                <div class="modal-body text-center">
    
                    <img id="filePreview" style="max-width:100%; display:none;">
                    
                    <iframe id="fileFrame"
                            style="width:100%; height:70vh; display:none;"
                            frameborder="0"></iframe>
    
                </div>
    
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    
            // Track which vendors have had files viewed during this admin session.
            const reviewedVendors = new Set();
            
            function openVendorLogo(vendorId, path) {
                viewFile(path);
            
                const row = document.getElementById('vendor-row-' + vendorId);
            
                if (row) {
                    row.setAttribute('data-logo-viewed', '1');
                }
            
                reviewedVendors.add('logo-' + vendorId);
                maybeEnableDocumentApproval(vendorId);
            }
            
            function openVendorDocs(vendorId, path) {
                viewFile(path);
            
                const row = document.getElementById('vendor-row-' + vendorId);
            
                if (row) {
                    row.setAttribute('data-docs-viewed', '1');
                }
            
                reviewedVendors.add('docs-' + vendorId);
                maybeEnableDocumentApproval(vendorId);
            }
            
            function maybeEnableDocumentApproval(vendorId) {
                const btn = document.getElementById('approve-' + vendorId);
            
                if (!btn) {
                    return;
                }
            
                // Business documents are the hard requirement.
                // Logo review is supported, but not required to avoid blocking vendors with missing logos.
                if (reviewedVendors.has('docs-' + vendorId)) {
                    btn.disabled = false;
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-success');
                    btn.innerText = 'Mark Docs Approved';
                }
            }
            
            function viewFile(path) {
            
                const img = document.getElementById('filePreview');
                const frame = document.getElementById('fileFrame');
            
                // vendors.php is inside /admin, so use ../api/
                const file = '../api/file-view.php?file=' + encodeURIComponent(path);
            
                const lowerPath = path.toLowerCase();
                const isPdf = lowerPath.includes('.pdf');
            
                if (isPdf) {
                    img.style.display = 'none';
                    img.removeAttribute('src');
            
                    frame.style.display = 'block';
                    frame.src = file;
                } else {
                    frame.style.display = 'none';
                    frame.removeAttribute('src');
            
                    img.style.display = 'block';
                    img.src = file;
                }
            
                new bootstrap.Modal(document.getElementById('fileModal')).show();
            }
    
            function logout() {
                fetch('../api/logout.php')
                .then(() => location.href = '/admin-login.php');
            }
        
        function approve(id) {
            if (!reviewedVendors.has('docs-' + id)) {
                alert('Please view the vendor business documents before marking them approved.');
                return;
            }
        
            if (!confirm('Mark this vendor’s documents/application as approved?')) {
                return;
            }
        
            fetch('../api/approve-vendor.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_id: id })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message || 'Document approval completed.');
        
                if (data.success) {
                    location.reload();
                } else {
                    console.warn('Document approval blocked:', data);
                }
            })
            .catch(() => alert('Document approval request failed'));
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
</body>
</html>
