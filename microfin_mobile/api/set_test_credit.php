<?php
/**
 * DEVELOPMENT ONLY — sets a test credit limit for a client by user_id.
 * Usage: set_test_credit.php?user_id=7&tenant_id=fundline&limit=50000
 */
require_once 'db.php';
$user_id   = (int)($_GET['user_id']   ?? 0);
$tenant_id = $_GET['tenant_id']        ?? '';
$limit     = (float)($_GET['limit']   ?? 50000);

if (!$user_id || !$tenant_id) {
    echo json_encode(['error' => 'user_id and tenant_id required']); exit;
}

$stmt = $conn->prepare("UPDATE clients SET credit_limit = ? WHERE user_id = ? AND tenant_id = ?");
$stmt->bind_param("dis", $limit, $user_id, $tenant_id);
$stmt->execute();
echo json_encode([
    'updated_rows' => $stmt->affected_rows,
    'user_id'      => $user_id,
    'tenant_id'    => $tenant_id,
    'credit_limit' => $limit,
]);
$stmt->close();
?>
