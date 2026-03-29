<?php
/**
 * Manual Webhook Test Script
 * Use this to manually complete payments on localhost
 * Access: http://localhost/Fundline/includes/test_webhook_manually.php
 */

require_once '../config/db.php';

// Get all pending payment transactions
$query = "
    SELECT 
        pt.*,
        l.loan_number,
        l.remaining_balance,
        CONCAT(c.first_name, ' ', c.last_name) as client_name
    FROM payment_transactions pt
    JOIN loans l ON pt.loan_id = l.loan_id
    JOIN clients c ON pt.client_id = c.client_id
    WHERE pt.status = 'pending'
    ORDER BY pt.created_at DESC
";

$result = $conn->query($query);
$pending_transactions = [];
while ($row = $result->fetch_assoc()) {
    $pending_transactions[] = $row;
}

// Process payment if form is submitted
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'])) {
    $transaction_id = intval($_POST['transaction_id']);
    
    // Get transaction details
    $stmt = $conn->prepare("
        SELECT pt.*, l.remaining_balance, l.outstanding_principal, l.outstanding_interest, l.outstanding_penalty
        FROM payment_transactions pt
        JOIN loans l ON pt.loan_id = l.loan_id
        WHERE pt.transaction_id = ? AND pt.status = 'pending'
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $transaction = $result->fetch_assoc();
        $stmt->close();
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Update transaction status
            $stmt = $conn->prepare("
                UPDATE payment_transactions 
                SET payment_id = ?, status = 'paid', updated_at = NOW()
                WHERE transaction_id = ?
            ");
            $fake_payment_id = 'pay_manual_' . time();
            $stmt->bind_param("si", $fake_payment_id, $transaction_id);
            $stmt->execute();
            $stmt->close();
            
            // Create payment record
            $payment_ref = 'PAY' . date('YmdHis') . rand(1000, 9999);
            $current_date = date('Y-m-d');
            $payment_amount = floatval($transaction['amount']);
            
            // Debug output
            error_log("DEBUG - Current Date: " . $current_date);
            error_log("DEBUG - Payment Amount: " . $payment_amount);
            
            // Allocate payment (Penalty first, then Interest, then Principal)
            $penalty_paid = min($payment_amount, $transaction['outstanding_penalty'] ?? 0);
            $remaining = $payment_amount - $penalty_paid;
            
            $interest_paid = min($remaining, $transaction['outstanding_interest'] ?? 0);
            $remaining -= $interest_paid;
            
            $principal_paid = $remaining;
            
            // Insert payment record
            error_log("DEBUG - About to insert payment with date: " . $current_date);
            error_log("DEBUG - Bind params: ref=$payment_ref, loan={$transaction['loan_id']}, client={$transaction['client_id']}, date=$current_date, amount=$payment_amount");
            
            $stmt = $conn->prepare("
                INSERT INTO payments 
                (payment_reference, loan_id, client_id, payment_date, payment_amount, 
                 principal_paid, interest_paid, penalty_paid, payment_method, 
                 payment_reference_number, payment_status, received_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'GCash', ?, 'Posted', 1)
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $bind_result = $stmt->bind_param("siisdddds", 
                $payment_ref,
                $transaction['loan_id'],
                $transaction['client_id'],
                $current_date,
                $payment_amount,
                $principal_paid,
                $interest_paid,
                $penalty_paid,
                $transaction['source_id']
            );
            
            if (!$bind_result) {
                throw new Exception("Bind failed: " . $stmt->error);
            }
            
            error_log("DEBUG - Executing insert...");
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $payment_id = $stmt->insert_id;
            $stmt->close();
            
            // Get current loan data
            $stmt = $conn->prepare("
                SELECT total_paid, principal_paid, interest_paid, penalty_paid,
                       remaining_balance, outstanding_principal, outstanding_interest, outstanding_penalty
                FROM loans WHERE loan_id = ?
            ");
            $stmt->bind_param("i", $transaction['loan_id']);
            $stmt->execute();
            $loan = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Update loan balances
            $new_total_paid = ($loan['total_paid'] ?? 0) + $payment_amount;
            $new_principal_paid = ($loan['principal_paid'] ?? 0) + $principal_paid;
            $new_interest_paid = ($loan['interest_paid'] ?? 0) + $interest_paid;
            $new_penalty_paid = ($loan['penalty_paid'] ?? 0) + $penalty_paid;
            $new_remaining_balance = $loan['remaining_balance'] - $payment_amount;
            $new_outstanding_principal = $loan['outstanding_principal'] - $principal_paid;
            $new_outstanding_interest = $loan['outstanding_interest'] - $interest_paid;
            $new_outstanding_penalty = ($loan['outstanding_penalty'] ?? 0) - $penalty_paid;
            
            // Determine new loan status
            $new_status = 'Active';
            if ($new_remaining_balance <= 0) {
                $new_status = 'Fully Paid';
                $new_remaining_balance = 0;
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
                    loan_status = ?,
                    updated_at = NOW()
                WHERE loan_id = ?
            ");
            $stmt->bind_param("ddddddddssi",
                $new_total_paid,
                $new_principal_paid,
                $new_interest_paid,
                $new_penalty_paid,
                $new_remaining_balance,
                $new_outstanding_principal,
                $new_outstanding_interest,
                $new_outstanding_penalty,
                $current_date,
                $new_status,
                $transaction['loan_id']
            );
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $message = "✅ Payment processed successfully!<br>
                       Payment Reference: <strong>$payment_ref</strong><br>
                       Amount: <strong>₱" . number_format($payment_amount, 2) . "</strong><br>
                       New Balance: <strong>₱" . number_format($new_remaining_balance, 2) . "</strong>";
            $message_type = 'success';
            
            // Refresh the pending transactions list
            $result = $conn->query($query);
            $pending_transactions = [];
            while ($row = $result->fetch_assoc()) {
                $pending_transactions[] = $row;
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Error processing payment: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ Transaction not found or already processed";
        $message_type = 'error';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Webhook Test - Fundline</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 2rem;
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        .alert-success {
            background: #d1fae5;
            border: 2px solid #6ee7b7;
            color: #065f46;
        }
        .alert-error {
            background: #fee2e2;
            border: 2px solid #fca5a5;
            color: #991b1b;
        }
        .alert-info {
            background: #dbeafe;
            border: 2px solid #93c5fd;
            color: #1e40af;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        thead {
            background: #f8fafc;
        }
        th {
            padding: 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        tbody tr:hover {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.875rem;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        .info-box {
            background: #f0f9ff;
            border: 2px solid #bfdbfe;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-box h3 {
            color: #1e40af;
            margin-bottom: 0.5rem;
        }
        .info-box p {
            color: #1e40af;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Manual Webhook Test</h1>
        <p class="subtitle">Complete pending payments manually (for localhost testing)</p>
        
        <div class="info-box">
            <h3>ℹ️ Why do I need this?</h3>
            <p>Paymongo webhooks cannot reach <code>localhost</code>. This tool lets you manually complete payments during local development. In production with a real domain, the automatic webhook will work.</p>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($pending_transactions)): ?>
        <div class="empty-state">
            <div style="font-size: 4rem;">✅</div>
            <h2 style="margin-top: 1rem; color: #64748b;">No Pending Payments</h2>
            <p>All payments have been processed!</p>
        </div>
        <?php else: ?>
        <h2 style="margin-top: 2rem; margin-bottom: 1rem; color: #1e293b;">Pending Payments</h2>
        <table>
            <thead>
                <tr>
                    <th>Transaction Ref</th>
                    <th>Client</th>
                    <th>Loan</th>
                    <th>Amount</th>
                    <th>Current Balance</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_transactions as $txn): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($txn['transaction_ref']); ?></strong></td>
                    <td><?php echo htmlspecialchars($txn['client_name']); ?></td>
                    <td><?php echo htmlspecialchars($txn['loan_number']); ?></td>
                    <td><strong style="color: #10b981;">₱<?php echo number_format($txn['amount'], 2); ?></strong></td>
                    <td>₱<?php echo number_format($txn['remaining_balance'], 2); ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                    <td><span class="badge badge-pending"><?php echo ucfirst($txn['status']); ?></span></td>
                    <td>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Complete this payment?');">
                            <input type="hidden" name="transaction_id" value="<?php echo $txn['transaction_id']; ?>">
                            <button type="submit" class="btn btn-success">✓ Complete Payment</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
            <h3 style="color: #1e293b; margin-bottom: 1rem;">📋 Instructions</h3>
            <ol style="color: #64748b; line-height: 2;">
                <li>Make a payment from the client portal</li>
                <li>Complete the payment in GCash test environment</li>
                <li>Come back to this page and click "Complete Payment"</li>
                <li>The loan balance will be updated immediately</li>
            </ol>
        </div>
        
        <div style="margin-top: 2rem; text-align: center;">
            <a href="../client/my_loans.php" style="display: inline-block; padding: 0.75rem 2rem; background: #6366f1; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                ← Back to My Loans
            </a>
        </div>
    </div>
</body>
</html>
