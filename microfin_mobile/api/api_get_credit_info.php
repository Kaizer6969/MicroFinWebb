<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'db.php';

$user_id   = $_GET['user_id']   ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($user_id) || empty($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'user_id and tenant_id are required']);
    exit;
}

$user_id_int = (int)$user_id;

// 1. Get client & credit_limit
$stmt = $conn->prepare(
    "SELECT client_id, credit_limit, verification_status 
     FROM clients 
     WHERE user_id = ? AND tenant_id = ?"
);
$stmt->bind_param("is", $user_id_int, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        'success'          => true,
        'credit_limit'     => 0,
        'used_credit'      => 0,
        'remaining_credit' => 0,
        'verification_status' => 'Unverified',
    ]);
    exit;
}

$client = $res->fetch_assoc();
$client_id   = (int)$client['client_id'];
$credit_limit = (float)($client['credit_limit'] ?? 0);
$verification_status = $client['verification_status'] ?? 'Unverified';
$stmt->close();

// 2. Sum active / overdue / restructured loan principal amounts
$used_credit = 0.0;

$lStmt = $conn->prepare(
    "SELECT COALESCE(SUM(principal_amount), 0) AS total 
     FROM loans 
     WHERE client_id = ? AND loan_status IN ('Active', 'Overdue', 'Restructured')"
);
$lStmt->bind_param("i", $client_id);
$lStmt->execute();
$lRes = $lStmt->get_result();
if ($lRow = $lRes->fetch_assoc()) {
    $used_credit += (float)$lRow['total'];
}
$lStmt->close();

// 3. Sum pending / in-review loan application amounts
$aStmt = $conn->prepare(
    "SELECT COALESCE(SUM(requested_amount), 0) AS total 
     FROM loan_applications 
     WHERE client_id = ? 
       AND application_status IN ('Submitted','Pending','Under Review','Document Verification','Credit Investigation','For Approval')"
);
$aStmt->bind_param("i", $client_id);
$aStmt->execute();
$aRes = $aStmt->get_result();
if ($aRow = $aRes->fetch_assoc()) {
    $used_credit += (float)$aRow['total'];
}
$aStmt->close();

$remaining_credit = max(0.0, $credit_limit - $used_credit);

echo json_encode([
    'success'             => true,
    'credit_limit'        => $credit_limit,
    'used_credit'         => $used_credit,
    'remaining_credit'    => $remaining_credit,
    'verification_status' => $verification_status,
]);
?>
