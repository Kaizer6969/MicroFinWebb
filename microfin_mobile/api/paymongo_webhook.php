<?php
/**
 * Paymongo Webhook Handler
 * URL: http://yoursite.com/api/paymongo_webhook.php
 * Register this URL in your Paymongo Dashboard under Webhooks
 */
require_once 'db.php';

function logWebhook($message, $data = null) {
    $logFile  = __DIR__ . '/webhook_logs.txt';
    $ts       = date('Y-m-d H:i:s');
    $entry    = "[{$ts}] {$message}";
    if ($data) $entry .= "\n" . print_r($data, true);
    $entry .= "\n" . str_repeat('-', 80) . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

logWebhook("Webhook received", ['payload' => $payload, 'signature' => $signature]);

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

$data = json_decode($payload, true);
if (!$data) {
    http_response_code(400);
    logWebhook("Invalid JSON payload");
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$eventType = $data['data']['attributes']['type'] ?? '';
logWebhook("Event type: $eventType");

// ── source.chargeable: Customer completed payment in GCash/Maya app ──────────
if ($eventType === 'source.chargeable') {
    $sourceData = $data['data']['attributes']['data'];
    $sourceId   = $sourceData['id'] ?? '';
    $amount     = ($sourceData['attributes']['amount'] ?? 0) / 100;

    logWebhook("Processing source.chargeable", ['source_id' => $sourceId, 'amount' => $amount]);

    if (empty($sourceId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing source ID']);
        exit;
    }

    try {
        // Find pending transaction
        $stmt = $conn->prepare("
            SELECT * FROM payment_transactions
            WHERE source_id = ? AND status = 'pending' LIMIT 1
        ");
        $stmt->bind_param("s", $sourceId);
        $stmt->execute();
        $tx = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tx) {
            logWebhook("Transaction not found", ['source_id' => $sourceId]);
            http_response_code(404);
            echo json_encode(['error' => 'Transaction not found']);
            exit;
        }

        logWebhook("Found transaction", $tx);

        // Create payment in Paymongo to capture the funds
        $apiSecretKey   = 'YOUR_SECRET_KEY';
        $pmPayload = [
            'data' => [
                'attributes' => [
                    'amount'      => round($amount * 100),
                    'source'      => ['id' => $sourceId, 'type' => 'source'],
                    'currency'    => 'PHP',
                    'description' => "Loan Payment for Loan ID: {$tx['loan_id']}",
                ],
            ],
        ];

        $ch = curl_init('https://api.paymongo.com/v1/payments');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode($apiSecretKey . ':')],
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($pmPayload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $pmResp     = curl_exec($ch);
        $pmHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logWebhook("Paymongo payment response", ['code' => $pmHttpCode, 'body' => $pmResp]);

        if ($pmHttpCode >= 200 && $pmHttpCode < 300) {
            // ── Mark transaction completed ──
            $conn->prepare("UPDATE payment_transactions SET status='completed', updated_at=NOW() WHERE transaction_id=?")
                 ->bind_param("i", $tx['transaction_id']);
            // Use variable
            $upTx = $conn->prepare("UPDATE payment_transactions SET status='completed', updated_at=NOW() WHERE transaction_id=?");
            $upTx->bind_param("i", $tx['transaction_id']);
            $upTx->execute();
            $upTx->close();

            // ── Record payment using existing api_pay_loan logic ──
            _processLoanPayment($conn, $tx['loan_id'], $tx['client_id'], $tx['user_id'], $tx['tenant_id'], $amount, $tx['payment_method'], $sourceId);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Payment processed']);
        } else {
            logWebhook("Failed to create Paymongo payment", ['code' => $pmHttpCode, 'body' => $pmResp]);
            http_response_code(500);
            echo json_encode(['error' => 'Payment capture failed']);
        }

    } catch (Exception $e) {
        logWebhook("Exception", ['error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }

// ── payment.paid ─────────────────────────────────────────────────────────────
} elseif ($eventType === 'payment.paid') {
    logWebhook("payment.paid acknowledged");
    http_response_code(200);
    echo json_encode(['success' => true]);

// ── payment.failed ────────────────────────────────────────────────────────────
} elseif ($eventType === 'payment.failed') {
    $sourceId = $data['data']['attributes']['data']['attributes']['source']['id'] ?? '';
    if (!empty($sourceId)) {
        $upFail = $conn->prepare("UPDATE payment_transactions SET status='failed', updated_at=NOW() WHERE source_id=?");
        $upFail->bind_param("s", $sourceId);
        $upFail->execute();
        $upFail->close();
    }
    http_response_code(200);
    echo json_encode(['success' => true]);

} else {
    logWebhook("Unhandled event: $eventType");
    http_response_code(200);
    echo json_encode(['message' => 'Event not processed']);
}

$conn->close();

// ── Helper: process loan payment ─────────────────────────────────────────────
function _processLoanPayment($conn, $loan_id, $client_id, $user_id, $tenant_id, $amount, $method, $sourceId) {
    $conn->begin_transaction();
    try {
        // Get loan
        $stmt = $conn->prepare("SELECT * FROM loans WHERE loan_id = ?");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $loan = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$loan) throw new Exception("Loan not found");

        // Apply to unpaid installments
        $remaining   = $amount;
        $p_paid = $i_paid = $pen_paid = 0;

        $sStmt = $conn->prepare("SELECT * FROM amortization_schedule WHERE loan_id = ? AND payment_status != 'Paid' ORDER BY payment_number ASC");
        $sStmt->bind_param("i", $loan_id);
        $sStmt->execute();
        $schedule = $sStmt->get_result();
        $sStmt->close();

        while ($remaining > 0 && ($inst = $schedule->fetch_assoc())) {
            $due_now  = floatval($inst['total_payment']) - floatval($inst['amount_paid'] ?? 0);
            $pay_now  = min($remaining, $due_now);
            $status   = ($pay_now >= $due_now) ? 'Paid' : 'Partially Paid';
            $remaining -= $pay_now;

            $ratio    = floatval($inst['principal_amount']) / floatval($inst['total_payment']);
            $p_paid  += $pay_now * $ratio;
            $i_paid  += $pay_now * (1 - $ratio);

            $up = $conn->prepare("UPDATE amortization_schedule SET amount_paid = amount_paid + ?, payment_status = ?, payment_date = CURDATE() WHERE schedule_id = ?");
            $up->bind_param("dsi", $pay_now, $status, $inst['schedule_id']);
            $up->execute();
            $up->close();
        }

        // Update loan
        $newBal = floatval($loan['remaining_balance']) - $amount;
        $lUp = $conn->prepare("UPDATE loans SET total_paid = total_paid + ?, principal_paid = principal_paid + ?, interest_paid = interest_paid + ?, remaining_balance = ?, last_payment_date = CURDATE(), loan_status = IF(? <= 0, 'Fully Paid', loan_status), updated_at = NOW() WHERE loan_id = ?");
        $lUp->bind_param("ddddddi", $amount, $p_paid, $i_paid, $newBal, $newBal, $newBal, $loan_id);
        $lUp->bind_param("dddddi", $amount, $p_paid, $i_paid, $newBal, $newBal, $loan_id);

        // Redo correctly
        $lUp->close();
        $lUp2 = $conn->prepare("UPDATE loans SET total_paid = total_paid + ?, principal_paid = principal_paid + ?, interest_paid = interest_paid + ?, remaining_balance = remaining_balance - ?, last_payment_date = CURDATE(), loan_status = IF(remaining_balance - ? <= 0, 'Fully Paid', loan_status), updated_at = NOW() WHERE loan_id = ?");
        $lUp2->bind_param("dddddi", $amount, $p_paid, $i_paid, $amount, $amount, $loan_id);
        $lUp2->execute();
        $lUp2->close();

        // Insert payment record
        $ref   = 'PAY-' . strtoupper(substr(uniqid(), -8));
        $pStmt = $conn->prepare("INSERT INTO payments (payment_reference, loan_id, client_id, tenant_id, payment_date, payment_amount, principal_paid, interest_paid, payment_method, payment_reference_number, payment_status, received_by) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'Posted', NULL)");
        $pStmt->bind_param("siisdddss", $ref, $loan_id, $client_id, $tenant_id, $amount, $p_paid, $i_paid, $method, $sourceId);
        $pStmt->execute();
        $pStmt->close();

        // Notification
        $notifTitle = "Payment Received";
        $notifMsg   = "We received your payment of ₱" . number_format($amount, 2) . " via $method. Thank you!";
        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, tenant_id, notification_type, title, message) VALUES (?, ?, 'Payment', ?, ?)");
        $nStmt->bind_param("isss", $user_id, $tenant_id, $notifTitle, $notifMsg);
        $nStmt->execute();
        $nStmt->close();

        $conn->commit();
        logWebhook("Loan payment processed", ['loan_id' => $loan_id, 'amount' => $amount]);
    } catch (Exception $e) {
        $conn->rollback();
        logWebhook("Loan payment failed", ['error' => $e->getMessage()]);
    }
}
?>
