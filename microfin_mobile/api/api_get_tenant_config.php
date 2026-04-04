<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/db.php';
$tenantId = trim((string) ($_GET['tenant_id'] ?? ''));

if ($tenantId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing tenant_id.']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4", $dbConfig['username'], $dbConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    require_once __DIR__ . '/../../microfin_platform/backend/credit_policy.php';
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'credit_policy_settings'");
    $stmt->execute([$tenantId]);
    $policyBlob = json_decode($stmt->fetchColumn() ?: '{}', true) ?: [];
    $policy = mf_credit_policy_normalize($policyBlob);
    $allowedEmployment = $policy['eligibility']['allowed_employment_statuses'] ?? ['Employed', 'Self-Employed'];
    
    echo json_encode([
        'success' => true,
        'allowed_employment_statuses' => $allowedEmployment
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
