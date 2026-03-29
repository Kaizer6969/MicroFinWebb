<?php
/**
 * Payment Failed Handler
 * This page is shown when a payment fails or is cancelled
 */
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

$loan_id = isset($_GET['loan_id']) ? intval($_GET['loan_id']) : 0;
$user_id = $_SESSION['user_id'];
$error_message = $_GET['message'] ?? 'Payment was cancelled or failed to process.';

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

// Get loan details
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
    <title>Payment Failed - Fundline</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link href="../assets/css/main_style.css" rel="stylesheet">
</head>
<body class="light">
    <div class="container" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--spacing-2xl);">
        <div class="card card-padding-2xl" style="max-width: 600px; width: 100%; text-align: center;">
            <!-- Error Icon -->
            <div style="width: 100px; height: 100px; margin: 0 auto var(--spacing-2xl); background: linear-gradient(135deg, var(--color-danger) 0%, #dc2626 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 40px rgba(239, 68, 68, 0.3);">
                <span class="material-symbols-outlined" style="font-size: 4rem; color: white;">cancel</span>
            </div>
            
            <h1 class="heading-1 text-danger mb-2">Payment Failed</h1>
            <p class="body-large text-muted mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </p>
            
            <?php if ($loan): ?>
            <div class="card card-surface-alt card-padding-lg mb-6" style="text-align: left;">
                <div class="flex justify-between mb-3 pb-3" style="border-bottom: 1px solid var(--color-border-subtle);">
                    <span class="body-medium text-muted">Loan Number</span>
                    <span class="body-medium-bold text-main"><?php echo htmlspecialchars($loan['loan_number']); ?></span>
                </div>
                <div class="flex justify-between mb-3 pb-3" style="border-bottom: 1px solid var(--color-border-subtle);">
                    <span class="body-medium text-muted">Product</span>
                    <span class="body-medium-bold text-main"><?php echo htmlspecialchars($loan['product_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="body-medium text-muted">Outstanding Balance</span>
                    <span class="heading-4 text-danger">₱<?php echo number_format($loan['remaining_balance'], 2); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card card-border-subtle card-padding-lg mb-6">
                <div class="flex gap-md align-start">
                    <span class="material-symbols-outlined text-warning" style="font-size: 1.5rem;">info</span>
                    <div style="text-align: left;">
                        <h3 class="body-large-bold text-main mb-1">What happened?</h3>
                        <p class="body-medium text-muted mb-2">Your payment could not be completed. This may happen if:</p>
                        <ul class="body-small text-muted" style="margin-left: var(--spacing-lg); line-height: 1.6;">
                            <li>You cancelled the payment</li>
                            <li>There were insufficient funds</li>
                            <li>The payment gateway timed out</li>
                            <li>Network connection was lost</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col gap-md">
                <a href="make_payment.php?loan_id=<?php echo $loan_id; ?>" class="btn btn-primary btn-lg">
                    <span>Try Again</span>
                    <span class="material-symbols-outlined">refresh</span>
                </a>
                <a href="my_loans.php" class="btn btn-secondary btn-md">
                    <span>View My Loans</span>
                </a>
                <a href="dashboard.php" class="btn btn-ghost btn-md">
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
