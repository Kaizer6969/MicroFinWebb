<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

require_once __DIR__ . '/db.php';

$userId = (int)($_GET['user_id'] ?? 0);
$tenantId = trim((string)($_GET['tenant_id'] ?? ''));

if ($userId <= 0 || $tenantId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing user or tenant context.']);
    exit;
}

try {
    $cStmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
    $cStmt->bind_param('is', $userId, $tenantId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    $client = $cRes->fetch_assoc();
    $cStmt->close();

    if (!$client) {
        echo json_encode(['success' => true, 'transactions' => []]);
        exit;
    }
    
    $clientId = $client['client_id'];

    $tStmt = $conn->prepare("
        SELECT 
            payment_id AS transaction_id, loan_id, amount_paid AS amount, payment_date AS date,
            payment_method AS type, payment_status AS status, payment_reference AS reference_number
        FROM payments
        WHERE client_id = ? AND tenant_id = ?
        ORDER BY payment_date DESC, payment_id DESC
    ");
    $tStmt->bind_param('is', $clientId, $tenantId);
    $tStmt->execute();
    $res = $tStmt->get_result();
    
    $transactions = [];
    while ($row = $res->fetch_assoc()) {
        $transactions[] = $row;
    }
    $tStmt->close();

    echo json_encode(['success' => true, 'transactions' => $transactions]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
