<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db.php';

try {
    // We fetch the theme fields exactly as specified.
    $query = "SELECT 
                t.tenant_id,
                t.tenant_slug,
                t.tenant_name,
                tb.logo_path,
                tb.theme_primary_color,
                tb.theme_secondary_color
              FROM tenants t
              LEFT JOIN tenant_branding tb ON t.tenant_id = tb.tenant_id
              WHERE t.status = 'Active'";
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tenants = [];
    while ($row = $result->fetch_assoc()) {
        $tenants[] = [
            'tenant_id' => $row['tenant_id'],
            'tenant_slug' => $row['tenant_slug'],
            'tenant_name' => $row['tenant_name'],
            'primary_color' => $row['theme_primary_color'] ?? '#2563EB',
            'secondary_color' => $row['theme_secondary_color'] ?? '#1E3A8A',
            'logo_path' => $row['logo_path'] ?? ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tenants' => $tenants
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching tenants: ' . $e->getMessage()
    ]);
}
?>
