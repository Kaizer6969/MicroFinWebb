<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For Flutter web/mobile testing

require_once 'db.php';

try {
    // We fetch the theme fields exactly as specified.
    $query = "SELECT 
                t.tenant_id,
                t.tenant_slug as slug,
                t.tenant_name as appName,
                tb.logo_path,
                tb.font_family,
                tb.theme_primary_color,
                tb.theme_secondary_color,
                tb.theme_text_main,
                tb.theme_text_muted,
                tb.theme_bg_body,
                tb.theme_bg_card,
                tb.theme_border_color,
                tb.card_border_width,
                tb.card_shadow
              FROM tenants t
              LEFT JOIN tenant_branding tb ON t.tenant_id = tb.tenant_id
              WHERE t.status = 'Active'";
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tenants = [];
    while ($row = $result->fetch_assoc()) {
        $tenants[] = [
            'id' => $row['tenant_id'],
            'slug' => $row['slug'],
            'appName' => $row['appName'],
            'logo_path' => $row['logo_path'],
            'font_family' => $row['font_family'] ?? 'Inter',
            'theme_primary_color' => $row['theme_primary_color'],
            'theme_secondary_color' => $row['theme_secondary_color'],
            'theme_text_main' => $row['theme_text_main'],
            'theme_text_muted' => $row['theme_text_muted'],
            'theme_bg_body' => $row['theme_bg_body'],
            'theme_bg_card' => $row['theme_bg_card'],
            'theme_border_color' => $row['theme_border_color'] ?? '#E2E8F0',
            'card_border_width' => $row['card_border_width'] ?? '0',
            'card_shadow' => $row['card_shadow'] ?? 'none'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $tenants
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching tenants: ' . $e->getMessage()
    ]);
}
?>
