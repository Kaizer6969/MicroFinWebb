<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$user_id = $_GET['user_id'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($user_id) || empty($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'user_id and tenant_id are required']);
    exit;
}

$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ? AND tenant_id = ?");
$stmt->bind_param("is", $user_id, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['success' => true, 'loans' => []]);
    exit;
}
$client = $res->fetch_assoc();
$client_id = $client['client_id'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT l.loan_number, l.remaining_balance, l.monthly_amortization, l.next_payment_due, l.loan_status, l.total_loan_amount, l.total_paid, p.product_name 
    FROM loans l 
    JOIN loan_products p ON l.product_id = p.product_id 
    WHERE l.client_id = ? AND l.tenant_id = ?
    ORDER BY l.created_at DESC
");
$stmt->bind_param("is", $client_id, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();
$loans = [];
while($row = $res->fetch_assoc()) {
    $row['remaining_balance'] = (float)$row['remaining_balance'];
    $row['monthly_amortization'] = (float)$row['monthly_amortization'];
    $row['total_loan_amount'] = (float)$row['total_loan_amount'];
    $row['total_paid'] = (float)$row['total_paid'];
    $row['progress'] = $row['total_loan_amount'] > 0 ? (float)($row['total_paid'] / $row['total_loan_amount']) : 0;
    $loans[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'loans' => $loans]);
?>
