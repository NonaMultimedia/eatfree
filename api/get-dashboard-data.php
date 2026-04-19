<?php
/**
 * Get Dashboard Data API
 * Returns statistics for dashboard displays
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, 'Invalid request method');
}

$type = $_GET['type'] ?? 'public';

try {
    $db = getDB();
    
    // Get ecosystem settings
    $settings = getEcosystemSettings();
    
    // Get global wallet
    $stmt = $db->query("SELECT balance, total_received, total_distributed, meals_funded FROM global_wallet LIMIT 1");
    $wallet = $stmt->fetch();
    
    // Get vendor stats
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM vendors
        WHERE is_active = 1
    ");
    $vendors = $stmt->fetch();
    
    // Get donation stats
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(amount), 0) as total_amount,
            COUNT(*) as total_count,
            COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END), 0) as this_month
        FROM donations
        WHERE status = 'completed'
    ");
    $donations = $stmt->fetch();
    
    // Get meal stats
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN claimed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as this_month,
            COUNT(CASE WHEN claimed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as this_week,
            COUNT(CASE WHEN DATE(claimed_at) = CURDATE() THEN 1 END) as today
        FROM meal_claims
    ");
    $meals = $stmt->fetch();
    
    // Get beneficiary stats
    $stmt = $db->query("
        SELECT COUNT(*) as total,
               COUNT(CASE WHEN last_claim_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as active
        FROM beneficiaries
    ");
    $beneficiaries = $stmt->fetch();
    
    // Calculate milestone data
    $capacity = calculateEcosystemCapacity();
    
    $response = [
        'settings' => $settings,
        'wallet' => $wallet,
        'vendors' => $vendors,
        'donations' => $donations,
        'meals' => $meals,
        'beneficiaries' => $beneficiaries,
        'milestone' => $capacity
    ];
    
    // Add admin-specific data
    if ($type === 'admin' && isAdminLoggedIn()) {
        // Get pending withdrawals
        $stmt = $db->query("
            SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
            FROM vendor_withdrawals
            WHERE status = 'pending'
        ");
        $response['withdrawals'] = $stmt->fetch();
        
        // Get recent activity
        $stmt = $db->query("
            SELECT * FROM wallet_transactions
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $response['recent_transactions'] = $stmt->fetchAll();
        
        // Get vendor queue
        $stmt = $db->query("
            SELECT vq.*, v.business_name, v.email
            FROM vendor_queue vq
            JOIN vendors v ON vq.vendor_id = v.id
            WHERE vq.status = 'waiting'
            ORDER BY vq.queue_position ASC
        ");
        $response['vendor_queue'] = $stmt->fetchAll();
    }
    
    jsonResponse(true, $response, 'Dashboard data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get dashboard data error: " . $e->getMessage());
    jsonResponse(false, null, 'Failed to retrieve dashboard data');
}
