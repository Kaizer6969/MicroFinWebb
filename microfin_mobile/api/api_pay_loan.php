<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

$user_id    = $data['user_id']    ?? null;
$tenant_id  = $data['tenant_id']  ?? null;
$loan_id    = $data['loan_id']    ?? null;
$amount     = floatval($data['amount'] ?? 0);
$method     = $data['payment_method'] ?? 'Online Payment';
$reference  = $data['reference_number'] ?? '';

if (!$user_id || !$tenant_id || !$loan_id || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required payment data.']);
    exit;
}

// Check if reference already processed (to prevent double logging from webhook + app)
if (!empty($reference)) {
    $cStmt = $conn->prepare("SELECT payment_id FROM payments WHERE payment_reference_number = ? LIMIT 1");
    $cStmt->bind_param("s", $reference);
    $cStmt->execute();
    $existing = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();
    
    if ($existing) {
        // Already processed, just return success
        echo json_encode(['success' => true, 'message' => 'Payment already recorded.']);
        exit;
    }
}

$conn->begin_transaction();

try {
    // 1. Get Loan and Client info
    $stmt = $conn->prepare("SELECT l.*, c.client_id FROM loans l JOIN clients c ON l.client_id = c.client_id WHERE l.loan_id = ? AND l.tenant_id = ? AND c.user_id = ?");
    $stmt->bind_param("isi", $loan_id, $tenant_id, $user_id);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    if (!$loan) throw new Exception("Loan not found or access denied.");
    $stmt->close();

    $loan_id = $loan['loan_id'];
    $client_id = $loan['client_id'];

    // 2. Process payment - find the first unpaid installment(s)
    $remaining_to_apply = $amount;
    
    // Get all unpaid or partially paid installments
    $sStmt = $conn->prepare("SELECT * FROM amortization_schedule WHERE loan_id = ? AND payment_status != 'Paid' ORDER BY payment_number ASC");
    $sStmt->bind_param("i", $loan_id);
    $sStmt->execute();
    $schedule = $sStmt->get_result();
    $sStmt->close();

    $principal_paid = 0;
    $interest_paid = 0;
    $penalty_paid = 0;

    while ($remaining_to_apply > 0 && ($inst = $schedule->fetch_assoc())) {
        $inst_id = $inst['schedule_id'];
        $inst_total = floatval($inst['total_payment']);
        $already_paid = floatval($inst['amount_paid'] ?? 0);
        $due_now = $inst_total - $already_paid;

        if ($remaining_to_apply >= $due_now) {
            // Pay fully
            $pay_now = $due_now;
            $status = 'Paid';
            $remaining_to_apply -= $due_now;
        } else {
            // Pay partially
            $pay_now = $remaining_to_apply;
            $status = 'Partially Paid';
            $remaining_to_apply = 0;
        }

        // Logic to split pay_now into principal and interest based on installment ratio
        // In a real system this would be more complex, but here we just proportionally split the paid amount
        $ratio = floatval($inst['principal_amount']) / $inst_total;
        $p_part = $pay_now * $ratio;
        $i_part = $pay_now * (1 - $ratio);

        $principal_paid += $p_part;
        $interest_paid += $i_part;

        $upStmt = $conn->prepare("UPDATE amortization_schedule SET amount_paid = amount_paid + ?, payment_status = ?, payment_date = CURDATE() WHERE schedule_id = ?");
        $upStmt->bind_param("dsi", $pay_now, $status, $inst_id);
        $upStmt->execute();
        $upStmt->close();
    }

    // 3. Update Loan totals
    $lUpStmt = $conn->prepare("UPDATE loans SET total_paid = total_paid + ?, principal_paid = principal_paid + ?, interest_paid = interest_paid + ?, remaining_balance = remaining_balance - ?, last_payment_date = CURDATE(), loan_status = IF(remaining_balance <= 0, 'Fully Paid', loan_status) WHERE loan_id = ?");
    $lUpStmt->bind_param("ddddi", $amount, $principal_paid, $interest_paid, $amount, $loan_id);
    $lUpStmt->execute();
    $lUpStmt->close();

    // 4. Create Payment Record
    $ref_gen = 'PAY-' . strtoupper(substr(uniqid(), -8));
    $payStmt = $conn->prepare("INSERT INTO payments (payment_reference, loan_id, client_id, tenant_id, payment_date, payment_amount, principal_paid, interest_paid, payment_method, payment_reference_number, payment_status, received_by) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'Paid', NULL)");
    // received_by = NULL for auto/system
    $payStmt->bind_param("siisdddss", $ref_gen, $loan_id, $client_id, $tenant_id, $amount, $principal_paid, $interest_paid, $method, $reference);
    $payStmt->execute();
    $payStmt->close();

    // 5. Notification
    $notif_title = "Payment Received - " . $loan['loan_number'];
    $notif_msg = "We have received your payment of PHP " . number_format($amount, 2) . ". Thank you!";
    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, tenant_id, notification_type, title, message) VALUES (?, ?, 'Payment', ?, ?)");
    $nStmt->bind_param("isss", $user_id, $tenant_id, $notif_title, $notif_msg);
    $nStmt->execute();
    $nStmt->close();

    $conn->commit();

    // Fetch client email for receipt
    $emailStmt = $conn->prepare("SELECT u.email, c.email_address, c.first_name, c.last_name FROM clients c JOIN users u ON c.user_id = u.user_id WHERE c.client_id = ?");
    $emailStmt->bind_param("i", $client_id);
    $emailStmt->execute();
    $clientInfo = $emailStmt->get_result()->fetch_assoc();
    $emailStmt->close();

    $client_email = $clientInfo['email_address'] ?: $clientInfo['email'] ?? '';
    $client_name  = trim(($clientInfo['first_name'] ?? '') . ' ' . ($clientInfo['last_name'] ?? ''));

    echo json_encode([
        'success'            => true,
        'message'            => 'Payment processed successfully!',
        'payment_reference'  => $ref_gen,
        'client_email'       => $client_email,
        'client_name'        => $client_name,
        'loan_number'        => $loan['loan_number'],
        'amount'             => $amount,
        'payment_method'     => $method,
        'payment_date'       => date('Y-m-d'),
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
