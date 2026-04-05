<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/db.php';

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$userId = (int)($data['user_id'] ?? 0);
$tenantId = trim((string)($data['tenant_id'] ?? ''));
$loanId = (int)($data['loan_id'] ?? 0);
$amount = (float)($data['amount'] ?? 0);
$method = $data['payment_method'] ?? 'Online';
$refNum = $data['reference_number'] ?? 'REF-'.time();

if ($userId <= 0 || $tenantId === '' || $loanId <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment details.']);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Verify loan exists
    $lStmt = $conn->prepare("SELECT client_id, remaining_balance, principal_amount, total_paid FROM loans WHERE loan_id = ? AND tenant_id = ? FOR UPDATE");
    $lStmt->bind_param('is', $loanId, $tenantId);
    $lStmt->execute();
    $lRes = $lStmt->get_result();
    $loan = $lRes->fetch_assoc();
    $lStmt->close();

    if (!$loan) {
        throw new Exception("Loan not found.");
    }

    $newBalance = max(0, $loan['remaining_balance'] - $amount);
    $newPaid = $loan['total_paid'] + $amount;
    $newStatus = ($newBalance <= 0) ? 'Fully Paid' : 'Active';

    // 2. Update loan
    $uStmt = $conn->prepare("UPDATE loans SET remaining_balance = ?, total_paid = ?, loan_status = ? WHERE loan_id = ?");
    $uStmt->bind_param('ddsi', $newBalance, $newPaid, $newStatus, $loanId);
    $uStmt->execute();
    $uStmt->close();

    // 3. Insert into payments (principal/interest parsed loosely for simplicity here)
    $pStmt = $conn->prepare("INSERT INTO payments (loan_id, client_id, tenant_id, payment_amount, principal_paid, interest_paid, payment_date, payment_method, payment_reference, payment_status, received_by) VALUES (?, ?, ?, ?, ?, 0, NOW(), ?, ?, 'Verified', ?)");
    $pStmt->bind_param('iisddssi', $loanId, $loan['client_id'], $tenantId, $amount, $amount, $method, $refNum, $userId);
    $pStmt->execute();
    $pStmt->close();

    // 4. Update schedules (naive FIFO allocation for simplicity)
    $sStmt = $conn->prepare("SELECT schedule_id, total_payment AS total_due, amount_paid FROM amortization_schedule WHERE loan_id = ? AND payment_status != 'Paid' ORDER BY due_date ASC");
    $sStmt->bind_param('i', $loanId);
    $sStmt->execute();
    $sRes = $sStmt->get_result();
    
    $remainingPayment = $amount;
    $schedUpdates = [];
    while ($r = $sRes->fetch_assoc()) {
        if ($remainingPayment <= 0) break;
        
        $due = floatval($r['total_due']);
        $paid = floatval($r['amount_paid']);
        $unpaid = $due - $paid;
        
        if ($unpaid > 0) {
            $allocate = min($remainingPayment, $unpaid);
            $newSchedPaid = $paid + $allocate;
            $remainingPayment -= $allocate;
            $schedStatus = ($newSchedPaid >= $due) ? 'Paid' : 'Partially Paid';
            
            $schedUpdates[] = [
                'id' => $r['schedule_id'],
                'paid' => $newSchedPaid,
                'status' => $schedStatus
            ];
        }
    }
    $sStmt->close();

    $suStmt = $conn->prepare("UPDATE amortization_schedule SET amount_paid = ?, payment_status = ? WHERE schedule_id = ?");
    foreach ($schedUpdates as $su) {
        $suStmt->bind_param('dsi', $su['paid'], $su['status'], $su['id']);
        $suStmt->execute();
    }
    $suStmt->close();

    $conn->commit();

    // Fetch user details for email payload
    $uStmt = $conn->prepare("SELECT email_address, first_name, last_name FROM clients WHERE client_id = ? LIMIT 1");
    $uStmt->bind_param('i', $loan['client_id']);
    $uStmt->execute();
    $userRow = $uStmt->get_result()->fetch_assoc();
    $clientEmail = $userRow['email_address'] ?? '';
    $clientName = trim(($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? ''));
    $uStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Payment posted successfully.',
        'payment_reference' => $refNum,
        'client_email' => $clientEmail,
        'client_name' => $clientName,
        'payment_date' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
