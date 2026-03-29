<?php
/**
 * Payment Success Handler
 * This page handles successful payments and automatically completes them
 * Works for both localhost AND production
 */
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';
require_once 'email_helper.php';

$loan_id = isset($_GET['loan_id']) ? intval($_GET['loan_id']) : 0;
$user_id = $_SESSION['user_id'];

// Get client_id
$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("ERROR: Client not found");
}
$client_data = $result->fetch_assoc();
$client_id = $client_data['client_id'];
$stmt->close();

$payment_processed = false;
$payment_ref = '';
$payment_amount = 0;
$new_balance = 0;
$error_message = '';

// Check for pending transaction and auto-complete it
$stmt = $conn->prepare("
    SELECT pt.*, l.remaining_balance, l.outstanding_principal, 
           l.outstanding_interest, l.outstanding_penalty,
           l.total_paid, l.principal_paid, l.interest_paid, l.penalty_paid
    FROM payment_transactions pt
    JOIN loans l ON pt.loan_id = l.loan_id
    WHERE pt.loan_id = ? 
    AND pt.client_id = ? 
    AND pt.status = 'pending'
    ORDER BY pt.created_at DESC
    LIMIT 1
");
$stmt->bind_param("ii", $loan_id, $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $transaction = $result->fetch_assoc();
    $stmt->close();
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Generate payment reference
        $payment_ref = 'PAY' . date('YmdHis') . rand(1000, 9999);
        $fake_payment_id = 'pay_auto_' . time();
        $current_date = date('Y-m-d');
        $payment_amount = floatval($transaction['amount']);
        
        // Update transaction status
        $stmt = $conn->prepare("
            UPDATE payment_transactions 
            SET status = 'paid', payment_date = NOW(), updated_at = NOW()
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("i", $transaction['transaction_id']);
        $stmt->execute();
        $stmt->close();
        
        // Allocate payment (Penalty → Interest → Principal)
        $penalty_paid = min($payment_amount, floatval($transaction['outstanding_penalty'] ?? 0));
        $remaining = $payment_amount - $penalty_paid;
        
        $interest_paid = min($remaining, floatval($transaction['outstanding_interest'] ?? 0));
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
        $stmt->bind_param("siisdddds", 
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
        $stmt->execute();
        $stmt->close();
        
        // Calculate new loan balances
        $new_total_paid = floatval($transaction['total_paid']) + $payment_amount;
        $new_principal_paid = floatval($transaction['principal_paid']) + $principal_paid;
        $new_interest_paid = floatval($transaction['interest_paid']) + $interest_paid;
        $new_penalty_paid = floatval($transaction['penalty_paid']) + $penalty_paid;
        $new_balance = floatval($transaction['remaining_balance']) - $payment_amount;
        $new_outstanding_principal = floatval($transaction['outstanding_principal']) - $principal_paid;
        $new_outstanding_interest = floatval($transaction['outstanding_interest']) - $interest_paid;
        $new_outstanding_penalty = floatval($transaction['outstanding_penalty']) - $penalty_paid;
        
        // Handling Early Settlement (Special Reconciliation)
        $payment_type = $transaction['payment_type'] ?? 'regular';
        
        // Determine new loan status
        $new_status = 'Active';
        
        if ($payment_type === 'early_settlement' || $new_balance <= 0) {
            $new_status = 'Fully Paid';
            $new_balance = 0;
            $new_outstanding_principal = 0;
            $new_outstanding_interest = 0;
            $new_outstanding_penalty = 0;
            // Note: We might want to record 'Waived Interest' somewhere, but for now just zeroing out balance is sufficient.
        }
        
        // Update loan
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
            $new_balance,
            $new_outstanding_principal,
            $new_outstanding_interest,
            $new_outstanding_penalty,
            $current_date,
            $new_status,
            $transaction['loan_id']
        );
        $stmt->execute();
        $stmt->close();
        
        // If loan is fully paid, update credit limit
        if ($new_status === 'Fully Paid') {
            require_once 'calculate_credit_limit.php';
            
            // Check if there were penalties
            $had_penalties = $new_penalty_paid > 0;
            
            // Update credit limit based on payment behavior
            updateCreditLimitAfterLoanCompletion($transaction['client_id'], $had_penalties, $conn);
        }
        
        // Commit transaction
        $conn->commit();
        $payment_processed = true;
        
        // Send payment receipt email
        $client_stmt = $conn->prepare("SELECT u.email, c.first_name, c.last_name FROM clients c JOIN users u ON c.user_id = u.user_id WHERE c.client_id = ?");
        $client_stmt->bind_param("i", $transaction['client_id']);
        $client_stmt->execute();
        $client_result = $client_stmt->get_result();
        $client_row = $client_result->fetch_assoc();
        $client_stmt->close();
        
        if ($client_row) {
            $client_email = $client_row['email'];
            $client_name = $client_row['first_name'] . ' ' . $client_row['last_name'];
            
            // Get loan details for email
            $loan_stmt = $conn->prepare("SELECT loan_number, next_payment_due FROM loans WHERE loan_id = ?");
            $loan_stmt->bind_param("i", $transaction['loan_id']);
            $loan_stmt->execute();
            $loan_result = $loan_stmt->get_result();
            $loan_row = $loan_result->fetch_assoc();
            $loan_stmt->close();
            
            // Get product name
            $product_stmt = $conn->prepare("SELECT lp.product_name FROM loans l JOIN loan_products lp ON l.product_id = lp.product_id WHERE l.loan_id = ?");
            $product_stmt->bind_param("i", $transaction['loan_id']);
            $product_stmt->execute();
            $product_result = $product_stmt->get_result();
            $product_row = $product_result->fetch_assoc();
            $product_stmt->close();
            
            $paymentEmailData = [
                'amount' => $payment_amount,
                'principal_paid' => $principal_paid,
                'interest_paid' => $interest_paid,
                'penalty_paid' => $penalty_paid,
                'remaining_balance' => $new_balance,
                'loan_number' => $loan_row['loan_number'],
                'product_name' => $product_row['product_name'],
                'payment_reference' => $payment_ref,
                'payment_date' => $current_date,
                'next_payment_due' => $loan_row['next_payment_due'] ?? ''
            ];
            
            sendPaymentReceiptEmail($client_email, $client_name, $paymentEmailData);

            // Create System Notification
            require_once 'notification_helper.php';
            createNotification(
                $conn, 
                $user_id, 
                'Payment Received', 
                'Payment Successful', 
                "Your payment of ₱" . number_format($payment_amount, 2) . " has been received. Ref: $payment_ref",
                "my_payments.php",
                'Medium'
            );
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
} else {
    $stmt->close();
    // Check if payment was already processed
    $stmt = $conn->prepare("
        SELECT pt.*, p.payment_reference, p.payment_amount
        FROM payment_transactions pt
        LEFT JOIN payments p ON pt.loan_id = p.loan_id 
            AND pt.source_id = p.payment_reference_number
        WHERE pt.loan_id = ? 
        AND pt.client_id = ? 
        AND pt.status IN ('paid')
        ORDER BY pt.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("ii", $loan_id, $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment_data = $result->fetch_assoc();
        $payment_processed = true;
        $payment_ref = $payment_data['payment_reference'] ?? 'Already Processed';
        $payment_amount = floatval($payment_data['payment_amount'] ?? $payment_data['amount']);
    }
    $stmt->close();
}

// Get updated loan details
$stmt = $conn->prepare("
    SELECT l.*, lp.product_name
    FROM loans l
    JOIN loan_products lp ON l.product_id = lp.product_id
    WHERE l.loan_id = ? AND l.client_id = ?
");
$stmt->bind_param("ii", $loan_id, $client_id);
$stmt->execute();
$result = $stmt->get_result();
$loan = $result->fetch_assoc();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - Fundline</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .dashboard-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 0; display: flex; flex-direction: column; }
        
        .top-bar {
            background-color: var(--color-surface-light);
            border-bottom: 1px solid var(--color-border-subtle);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: var(--z-sticky);
        }
        .dark .top-bar { background-color: var(--color-surface-dark); border-bottom-color: var(--color-border-dark); }
        .content-area { flex: 1; padding: 2rem; background-color: var(--color-background-light); }
        .dark .content-area { background-color: var(--color-background-dark); }
        
        .status-header {
            background-color: var(--color-surface-light);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dark .status-header { background-color: var(--color-surface-dark); }
        
        .info-section {
            background-color: var(--color-surface-light);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
        }
        .dark .info-section { background-color: var(--color-surface-dark); }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .info-item { display: flex; flex-direction: column; gap: 0.25rem; }
        
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .status-header { flex-direction: column; align-items: start; gap: 1rem; }
        }
    </style>
</head>
<body class="light">
    <div class="dashboard-layout" style="justify-content: center;">
        <div class="main-content" style="max-width: 1000px; width: 100%; margin: 0 auto;">
            
            <div class="content-area" style="background: transparent;">
                <div style="max-width: 800px; margin: 0 auto;">
                    
                    <?php if ($payment_processed && empty($error_message)): ?>
                        <!-- SUCCESS STATE -->
                        <div class="status-header" style="flex-direction: column; text-align: center; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <div style="width: 100px; height: 100px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: var(--spacing-xl);">
                                <span class="material-symbols-outlined" style="font-size: 4rem; color: #10b981;">check_circle</span>
                            </div>
                            <h1 class="heading-1 mb-2" style="color: white;">Payment Successful!</h1>
                            <p class="body-large" style="color: rgba(255,255,255,0.9);">Your payment has been processed successfully</p>
                        </div>
                        
                        <!-- Amount Card -->
                        <div class="info-section" style="text-align: center;">
                            <div class="caption text-muted mb-2" style="text-transform: uppercase; letter-spacing: 1px;">Amount Paid</div>
                            <div class="display-1 text-success" style="font-size: 3rem; font-weight: 800;">
                                ₱<?php echo number_format($payment_amount, 2); ?>
                            </div>
                        </div>
                        
                        <!-- Payment Details -->
                        <div class="info-section">
                            <h2 class="heading-3 text-main mb-4">Payment Details</h2>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="caption text-muted">Payment Reference</span>
                                    <span class="body-medium-bold text-main"><?php echo htmlspecialchars($payment_ref); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="caption text-muted">Loan Number</span>
                                    <span class="body-medium-bold text-main"><?php echo htmlspecialchars($loan['loan_number']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="caption text-muted">Payment Date</span>
                                    <span class="body-medium-bold text-main"><?php echo date('F d, Y'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="caption text-muted">Payment Method</span>
                                    <span class="body-medium-bold text-main">GCash</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loan Balance -->
                        <div class="info-section">
                            <h2 class="heading-3 text-main mb-4">Updated Loan Balance</h2>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="caption text-muted">Product</span>
                                    <span class="body-medium-bold text-main"><?php echo htmlspecialchars($loan['product_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="caption text-muted">Remaining Balance</span>
                                    <span class="heading-4 text-success">₱<?php echo number_format($loan['remaining_balance'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex gap-md justify-center mt-6">
                            <a href="my_loans.php" class="btn btn-primary btn-lg">
                                <span>View My Loans</span>
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </a>
                            <a href="dashboard.php" class="btn btn-ghost btn-lg">
                                <span>Back to Dashboard</span>
                            </a>
                        </div>
                        
                    <?php else: ?>
                        <!-- ERROR STATE -->
                        <div class="status-header" style="flex-direction: column; text-align: center; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <div style="width: 100px; height: 100px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: var(--spacing-xl);">
                                <span class="material-symbols-outlined" style="font-size: 4rem; color: #ef4444;">error</span>
                            </div>
                            <h1 class="heading-1 mb-2" style="color: white;">Payment Failed</h1>
                            <p class="body-large" style="color: rgba(255,255,255,0.9);">
                                <?php echo !empty($error_message) ? htmlspecialchars($error_message) : 'We couldn\'t process your payment'; ?>
                            </p>
                        </div>
                        
                        <!-- Info Box -->
                        <div class="info-section" style="background: rgba(251, 191, 36, 0.1); border: 1px solid #fbbf24;">
                            <div class="flex gap-md align-start">
                                <span class="material-symbols-outlined text-warning" style="font-size: 2rem;">info</span>
                                <div>
                                    <h3 class="body-large-bold text-main mb-2">What happened?</h3>
                                    <p class="body-medium text-muted mb-3">Your payment could not be completed. Common reasons:</p>
                                    <ul class="body-small text-muted" style="margin-left: var(--spacing-lg); line-height: 1.8;">
                                        <li>Payment was cancelled</li>
                                        <li>Insufficient funds in your account</li>
                                        <li>Connection timeout or network error</li>
                                        <li>Payment gateway is temporarily unavailable</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex gap-md justify-center mt-6">
                            <a href="make_payment.php?loan_id=<?php echo $loan_id; ?>" class="btn btn-primary btn-lg">
                                <span>Try Again</span>
                                <span class="material-symbols-outlined">refresh</span>
                            </a>
                            <a href="my_loans.php" class="btn btn-secondary btn-lg">
                                <span>View My Loans</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
</body>
</html>
