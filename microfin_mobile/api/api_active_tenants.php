<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$tenantFilter = trim((string) ($_GET['tenant'] ?? ''));

$sql = "
SELECT
    t.tenant_id,
    t.tenant_name,
    t.tenant_slug,
    t.status,
    COALESCE(tb.theme_primary_color, '#1d4ed8') AS primary_color,
    COALESCE(tb.theme_secondary_color, '#1e3a8a') AS secondary_color,
    tb.logo_path
FROM tenants t
LEFT JOIN tenant_branding tb ON tb.tenant_id = t.tenant_id
WHERE t.deleted_at IS NULL
  AND t.tenant_id IS NOT NULL
  AND TRIM(t.tenant_id) <> ''
  AND LOWER(COALESCE(t.status, '')) = 'active'
";

if ($tenantFilter !== '') {
    $sql .= "
  AND (
        LOWER(t.tenant_id) = LOWER(?)
        OR LOWER(COALESCE(t.tenant_slug, '')) = LOWER(?)
  )
";
}

$sql .= "
ORDER BY t.tenant_name ASC
";

if ($tenantFilter !== '') {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to prepare tenant query: ' . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param('ss', $tenantFilter, $tenantFilter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load active tenants: ' . $conn->error
    ]);
    exit;
}

$tenants = [];
while ($row = $result->fetch_assoc()) {
    $tenants[] = $row;
}

if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'tenants' => $tenants
]);
