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

$loanNum = $_GET['loan_number'] ?? '';
$tenantId = $_GET['tenant_id'] ?? '';

if ($loanNum === '' || $tenantId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing parameter.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            l.*, 
            COALESCE(lp.product_name, 'Term Loan') AS product_name
        FROM loans l
        LEFT JOIN loan_products lp ON l.product_id = lp.product_id
        WHERE l.loan_number = ? AND l.tenant_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $loanNum, $tenantId);
    $stmt->execute();
    $res = $stmt->get_result();
    $loan = $res->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        echo json_encode(['success' => false, 'message' => 'Loan not found.']);
        exit;
    }

    $total = floatval($loan['total_loan_amount']);
    $paid = floatval($loan['total_paid']);
    $progress = $total > 0 ? min(1, $paid / $total) : 0;

    // Fetch schedules
    $schedStmt = $conn->prepare("SELECT schedule_id, due_date, payment_status AS status, total_payment AS total_due, amount_paid, IF(payment_status='Overdue', 1, 0) AS is_overdue FROM amortization_schedule WHERE loan_id = ? ORDER BY due_date ASC");
    $schedStmt->bind_param('i', $loan['loan_id']);
    $schedStmt->execute();
    $schedRes = $schedStmt->get_result();
    $schedules = [];
    while ($r = $schedRes->fetch_assoc()) {
        $schedules[] = $r;
    }
    $schedStmt->close();

    // Fetch transactions
    $txStmt = $conn->prepare("SELECT payment_id AS transaction_id, payment_date, payment_amount AS amount_paid, payment_method, payment_status AS status FROM payments WHERE loan_id = ? AND tenant_id = ? ORDER BY payment_date DESC, payment_id DESC");
    $txStmt->bind_param('is', $loan['loan_id'], $tenantId);
    $txStmt->execute();
    $txRes = $txStmt->get_result();
    $transactions = [];
    while ($r = $txRes->fetch_assoc()) {
        $transactions[] = $r;
    }
    $txStmt->close();

    $response = [
        'success' => true,
        'loan' => [
            'loan_number' => $loan['loan_number'],
            'status' => $loan['loan_status'],
            'product_name' => $loan['product_name'],
            'applied_date' => $loan['created_at'],
            'approved_date' => $loan['approved_at'],
            'total_amount' => $loan['principal_amount'],
            'total_interest' => $loan['total_interest'],
            'duration' => $loan['loan_term_months'] . ' Months',
            'monthly_amortization' => $loan['monthly_amortization'],
            'total_paid' => $loan['total_paid'],
            'remaining_balance' => $loan['remaining_balance'],
            'progress' => round($progress, 4),
            'next_due_date' => $loan['next_payment_due'],
            'schedules' => $schedules,
            'transactions' => $transactions
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
