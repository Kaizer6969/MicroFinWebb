<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_utils.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    microfin_json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$tenantFilter = microfin_clean_string($_GET['tenant_id'] ?? $_GET['tenant'] ?? '');
if ($tenantFilter === '') {
    microfin_json_response(['success' => false, 'message' => 'tenant_id is required.'], 422);
}

$tenantSql = "
    SELECT tenant_id
    FROM tenants
    WHERE deleted_at IS NULL
      AND (
            LOWER(tenant_id) = LOWER(?)
            OR LOWER(COALESCE(tenant_slug, '')) = LOWER(?)
      )
    LIMIT 1
";

$tenantStmt = $conn->prepare($tenantSql);
if (!$tenantStmt) {
    microfin_json_response([
        'success' => false,
        'message' => 'Failed to prepare tenant lookup: ' . $conn->error,
    ], 500);
}

$tenantStmt->bind_param('ss', $tenantFilter, $tenantFilter);
$tenantStmt->execute();
$tenantRow = $tenantStmt->get_result()->fetch_assoc() ?: null;
$tenantStmt->close();

if (!$tenantRow || trim((string) ($tenantRow['tenant_id'] ?? '')) === '') {
    microfin_json_response(['success' => false, 'message' => 'Tenant not found.'], 404);
}

$tenantId = trim((string) $tenantRow['tenant_id']);

$productSql = "
    SELECT
        product_id,
        product_id AS id,
        product_name,
        product_name AS name,
        product_type,
        product_type AS type,
        COALESCE(description, '') AS description,
        min_amount,
        min_amount AS min,
        max_amount,
        max_amount AS max,
        interest_rate,
        interest_rate AS rate,
        COALESCE(interest_type, '') AS interest_type,
        min_term_months,
        min_term_months AS min_term,
        max_term_months,
        max_term_months AS max_term,
        COALESCE(processing_fee_percentage, 0) AS processing_fee_percentage,
        COALESCE(service_charge, 0) AS service_charge,
        COALESCE(documentary_stamp, 0) AS documentary_stamp,
        COALESCE(insurance_fee_percentage, 0) AS insurance_fee_percentage,
        COALESCE(penalty_rate, 0) AS penalty_rate,
        COALESCE(penalty_type, '') AS penalty_type,
        COALESCE(grace_period_days, 0) AS grace_period_days,
        CAST(COALESCE(is_active, 1) AS CHAR) AS is_active
    FROM loan_products
    WHERE tenant_id = ?
      AND COALESCE(is_active, 1) = 1
    ORDER BY product_name ASC, product_id DESC
";

$productStmt = $conn->prepare($productSql);
if (!$productStmt) {
    microfin_json_response([
        'success' => false,
        'message' => 'Failed to prepare product lookup: ' . $conn->error,
    ], 500);
}

$productStmt->bind_param('s', $tenantId);
$productStmt->execute();
$result = $productStmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$productStmt->close();

microfin_json_response([
    'success' => true,
    'tenant_id' => $tenantId,
    'products' => $products,
]);
