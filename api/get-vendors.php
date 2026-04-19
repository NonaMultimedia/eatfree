<?php
/**
 * Get Vendors API
 * Returns list of approved vendors
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, 'Invalid request method');
}

$province = $_GET['province'] ?? null;
$city = $_GET['city'] ?? null;
$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

try {
    $db = getDB();
    
    // Build query
    $where = ["v.status = 'approved'", "v.is_active = 1", "v.subscription_status = 'active'"];
    $params = [];
    
    if ($province) {
        $where[] = "v.province = ?";
        $params[] = $province;
    }
    
    if ($city) {
        $where[] = "v.city = ?";
        $params[] = $city;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get vendors
    $sql = "
        SELECT 
            v.id,
            v.business_name,
            v.address,
            v.city,
            v.province,
            v.phone,
            v.logo_path,
            v.meals_remaining,
            v.total_meals_served,
            v.latitude,
            v.longitude
        FROM vendors v
        WHERE $whereClause
        ORDER BY v.meals_remaining DESC, v.business_name ASC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $vendors = $stmt->fetchAll();
    
    // Group vendors by province
    $grouped = [];
    foreach ($vendors as $vendor) {
        $prov = $vendor['province'];
        if (!isset($grouped[$prov])) {
            $grouped[$prov] = [];
        }
        $grouped[$prov][] = $vendor;
    }
    
    // Get unique provinces and cities for filters
    $stmt = $db->query("
        SELECT DISTINCT province FROM vendors 
        WHERE status = 'approved' AND is_active = 1 
        ORDER BY province
    ");
    $provinces = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $db->query("
        SELECT DISTINCT city FROM vendors 
        WHERE status = 'approved' AND is_active = 1 
        ORDER BY city
    ");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    jsonResponse(true, [
        'vendors' => $vendors,
        'grouped' => $grouped,
        'provinces' => $provinces,
        'cities' => $cities,
        'total' => count($vendors),
        'limit' => $limit,
        'offset' => $offset
    ], 'Vendors retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get vendors error: " . $e->getMessage());
    jsonResponse(false, null, 'Failed to retrieve vendors');
}
