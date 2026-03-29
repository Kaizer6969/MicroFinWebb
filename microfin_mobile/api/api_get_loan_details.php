<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$loan_number = $_GET['loan_number'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($loan_number) || empty($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'loan_number and tenant_id are required']);
    exit;
}

// Get loan details
$stmt = $conn->prepare("
    SELECT l.*, p.product_name, p.product_type, p.interest_rate as product_rate
    FROM loans l
    JOIN loan_products p ON l.product_id = p.product_id
    WHERE l.loan_number = ? AND l.tenant_id = ?
    LIMIT 1
");
$stmt->bind_param("ss", $loan_number, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Loan not found']);
    exit;
}

$loan = $res->fetch_assoc();
$stmt->close();

// Cast numeric fields
$numericFields = ['principal_amount','interest_amount','total_loan_amount','processing_fee','service_charge','documentary_stamp','insurance_fee','other_charges','total_deductions','net_proceeds','monthly_amortization','total_paid','principal_paid','interest_paid','penalty_paid','remaining_balance','outstanding_principal','outstanding_interest','outstanding_penalty','days_overdue'];
foreach ($numericFields as $f) {
    if (isset($loan[$f])) $loan[$f] = (float)$loan[$f];
}
$loan['loan_term_months'] = (int)$loan['loan_term_months'];
$loan['number_of_payments'] = (int)$loan['number_of_payments'];
$loan['progress'] = $loan['total_loan_amount'] > 0 ? (float)($loan['total_paid'] / $loan['total_loan_amount']) : 0;

// Count on-time payments (Paid on or before due date)
$stmt = $conn->prepare("
    SELECT COUNT(*) as on_time FROM amortization_schedule 
    WHERE loan_id = ? AND payment_status = 'Paid' AND (days_late = 0 OR days_late IS NULL)
");
$stmt->bind_param("i", $loan['loan_id']);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$loan['on_time_payments'] = (int)$row['on_time'];
$stmt->close();

// Get amortization schedule
$stmt = $conn->prepare("
    SELECT payment_number, due_date, principal_amount, interest_amount, total_payment, ending_balance, payment_status, amount_paid, days_late, penalty_amount
    FROM amortization_schedule 
    WHERE loan_id = ? 
    ORDER BY payment_number ASC
");
$stmt->bind_param("i", $loan['loan_id']);
$stmt->execute();
$res = $stmt->get_result();
$schedule = [];
while($row = $res->fetch_assoc()) {
    $row['principal_amount'] = (float)$row['principal_amount'];
    $row['interest_amount'] = (float)$row['interest_amount'];
    $row['total_payment'] = (float)$row['total_payment'];
    $row['ending_balance'] = (float)$row['ending_balance'];
    $row['amount_paid'] = (float)$row['amount_paid'];
    $row['penalty_amount'] = (float)$row['penalty_amount'];
    $schedule[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'loan' => $loan, 'schedule' => $schedule]);
?>
