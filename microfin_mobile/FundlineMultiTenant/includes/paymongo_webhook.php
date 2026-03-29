<?php
/**
 * Paymongo Webhook Handler
 * Place this file in your includes folder
 * URL: http://yoursite.com/includes/paymongo_webhook.php
 * Configure this URL in your Paymongo Dashboard under Webhooks
 */

// Include database connection
require_once '../config/db.php';

// Log function
function logWebhook($message, $data = null) {
    $logFile = __DIR__ . '/webhook_logs.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";
    if ($data) {
        $logMessage .= "\n" . print_r($data, true);
    }
    $logMessage .= "\n" . str_repeat('-', 80) . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Get the payload
$payload = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_PAYMONGO_SIGNATURE']) ? $_SERVER['HTTP_PAYMONGO_SIGNATURE'] : '';

logWebhook("Webhook received", [
    'payload' => $payload,
    'signature' => $signature
]);

// For testing without signature verification (REMOVE IN PRODUCTION)
// Comment out these lines once you set up webhook signature verification
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

// Parse the payload
$data = json_decode($payload, true);
if (!$data) {
    http_response_code(400);
    logWebhook("Invalid JSON payload");
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

logWebhook("Parsed webhook data", $data);

// Get event type
$eventType = $data['data']['attributes']['type'] ?? '';
logWebhook("Event type: " . $eventType);

// Handle source.chargeable event (when customer completes GCash payment)
if ($eventType === 'source.chargeable') {
    $sourceData = $data['data']['attributes']['data'];
    $sourceId = $sourceData['id'] ?? '';
    $amount = ($sourceData['attributes']['amount'] ?? 0) / 100; // Convert from cents
    
    logWebhook("Processing source.chargeable", [
        'source_id' => $sourceId,
        'amount' => $amount
    ]);
    
    if (empty($sourceId)) {
        http_response_code(400);
        logWebhook("Missing source ID");
        echo json_encode(['error' => 'Missing source ID']);
        exit;
    }
    
    try {
        // Find the transaction
        // Added payment_type to selection
        $stmt = $conn->prepare("
            SELECT transaction_id, client_id, loan_id, amount, status, payment_type
            FROM payment_transactions
            WHERE source_id = ? AND status = 'pending'
            LIMIT 1
        ");
        $stmt->bind_param("s", $sourceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logWebhook("Transaction not found or already processed", ['source_id' => $sourceId]);
            http_response_code(404);
            echo json_encode(['error' => 'Transaction not found']);
            exit;
        }
        
        $transaction = $result->fetch_assoc();
        $stmt->close();
        
        logWebhook("Found transaction", $transaction);
        
        // Create payment in Paymongo to capture the funds
        $apiSecretKey = 'YOUR_SECRET_KEY';
        $url = 'https://api.paymongo.com/v1/payments';
        
        $paymentPayload = [
            'data' => [
                'attributes' => [
                    'amount' => round($amount * 100),
                    'source' => [
                        'id' => $sourceId,
                        'type' => 'source'
                    ],
                    'currency' => 'PHP',
                    'description' => "Loan Payment for Loan ID: {$transaction['loan_id']}"
                ]
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($apiSecretKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentPayload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        logWebhook("Payment creation response", [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $paymentResponse = json_decode($response, true);
            $paymentId = $paymentResponse['data']['id'];
            $paymentStatus = $paymentResponse['data']['attributes']['status'];
            
            // Update transaction status
            $stmt = $conn->prepare("
                UPDATE payment_transactions 
                SET status = 'completed', payment_date = NOW(), updated_at = NOW()
                WHERE transaction_id = ?
            ");
            $stmt->bind_param("i", $transaction['transaction_id']);
            $stmt->execute();
            $stmt->close();
            
            // Create payment record
            $payment_ref = 'PAY' . date('YmdHis') . rand(1000, 9999);
            $payment_date = date('Y-m-d');
            $payment_amount = $transaction['amount'];
            
            // Get loan details to calculate allocation
            $stmt = $conn->prepare("
                SELECT remaining_balance, outstanding_principal, outstanding_interest, outstanding_penalty
                FROM loans WHERE loan_id = ?
            ");
            $stmt->bind_param("i", $transaction['loan_id']);
            $stmt->execute();
            $loan_result = $stmt->get_result();
            $loan = $loan_result->fetch_assoc();
            $stmt->close();
            
            // Allocate payment (Penalty first, then Interest, then Principal)
            $penalty_paid = min($payment_amount, $loan['outstanding_penalty'] ?? 0);
            $remaining = $payment_amount - $penalty_paid;
            
            // Check early settlement logic
            $is_early_settlement = ($transaction['payment_type'] === 'early_settlement');
            
            // Interest Allocation
            $interest_paid = min($remaining, $loan['outstanding_interest'] ?? 0);
            $remaining -= $interest_paid;
            
            $principal_paid = $remaining;
            
            // Insert payment record
            $stmt = $conn->prepare("
                INSERT INTO payments 
                (payment_reference, loan_id, client_id, payment_date, payment_amount, 
                 principal_paid, interest_paid, penalty_paid, payment_method, 
                 payment_reference_number, payment_status, received_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'GCash', ?, 'Posted', 1, NOW())
            ");
            $stmt->bind_param("siisddds", 
                $payment_ref,
                $transaction['loan_id'],
                $transaction['client_id'],
                $payment_date,
                $payment_amount,
                $principal_paid,
                $interest_paid,
                $penalty_paid,
                $sourceId
            );
            $stmt->execute();
            $stmt->close();
            
            // Update loan balances
            $new_total_paid = ($loan['total_paid'] ?? 0) + $payment_amount;
            $new_principal_paid = ($loan['principal_paid'] ?? 0) + $principal_paid;
            $new_interest_paid = ($loan['interest_paid'] ?? 0) + $interest_paid;
            $new_penalty_paid = ($loan['penalty_paid'] ?? 0) + $penalty_paid;
            
            // Calculate new balances
            $new_remaining_balance = $loan['remaining_balance'] - $payment_amount;
            $new_outstanding_principal = $loan['outstanding_principal'] - $principal_paid;
            $new_outstanding_interest = $loan['outstanding_interest'] - $interest_paid;
            $new_outstanding_penalty = ($loan['outstanding_penalty'] ?? 0) - $penalty_paid;
            
            // Special Logic for Early Settlement: Waive remaining amounts if paid
            if ($is_early_settlement) {
                // Ideally we verify if principal is cleared.
                // Assuming the calculated amount was correct and paid in full.
                // If the principal is close to zero (allowing for float variance), we close it.
                if ($new_outstanding_principal <= 1) { // 1 peso tolerance
                    $new_outstanding_principal = 0;
                    $new_outstanding_interest = 0; // Waive future interest
                    $new_remaining_balance = 0; // Clear balance
                }
            }

            $stmt = $conn->prepare("
                UPDATE loans 
                SET total_paid = ?,
                    principal_paid = ?,
                    interest_paid = ?,
                    penalty_paid = ?,
                    remaining_balance = ?,
                    outstanding_principal = ?,
                    outstanding_interest = ?,
                    outstanding_penalty = ?,
                    last_payment_date = ?,
                    loan_status = CASE 
                        WHEN ? <= 0 THEN 'Fully Paid'
                        ELSE loan_status 
                    END,
                    updated_at = NOW()
                WHERE loan_id = ?
            ");
            $stmt->bind_param("dddddddsdi",
                $new_total_paid,
                $new_principal_paid,
                $new_interest_paid,
                $new_penalty_paid,
                $new_remaining_balance,
                $new_outstanding_principal,
                $new_outstanding_interest,
                $new_outstanding_penalty,
                $payment_date,
                $new_remaining_balance, // Check value for loan_status update
                $transaction['loan_id']
            );
            $stmt->execute();
            $stmt->close();
            
            logWebhook("Payment processed successfully", [
                'payment_ref' => $payment_ref,
                'amount' => $payment_amount,
                'new_balance' => $new_remaining_balance
            ]);
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Payment processed']);
        } else {
            logWebhook("Failed to create payment", [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Payment creation failed']);
        }
        
    } catch (Exception $e) {
        logWebhook("Exception occurred", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}
// Handle payment.paid event (confirmation)
elseif ($eventType === 'payment.paid') {
    logWebhook("Payment paid event received (already processed via source.chargeable)");
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Event acknowledged']);
}
// Handle payment.failed event
elseif ($eventType === 'payment.failed') {
    $paymentData = $data['data']['attributes']['data'];
    $sourceId = $paymentData['attributes']['source']['id'] ?? '';
    
    if (!empty($sourceId)) {
        $stmt = $conn->prepare("
            UPDATE payment_transactions 
            SET status = 'failed', updated_at = NOW()
            WHERE source_id = ?
        ");
        $stmt->bind_param("s", $sourceId);
        $stmt->execute();
        $stmt->close();
        
        logWebhook("Payment failed", ['source_id' => $sourceId]);
    }
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Failure recorded']);
}
else {
    logWebhook("Unhandled event type: " . $eventType);
    http_response_code(200);
    echo json_encode(['message' => 'Event type not processed']);
}

$conn->close();
?>
