<?php
/**
 * Client My Loans Page
 * Displays all active loans for the client
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$avatar_letter = strtoupper(substr($username, 0, 1));

// Get client_id
$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$client_data = $result->fetch_assoc();
$client_id = $client_data['client_id'];
$stmt->close();

// Get all active loans for the client (exclude Fully Paid)
$loans = [];
$stmt = $conn->prepare("
    SELECT l.*, lp.product_name, lp.product_type,
           la.application_number
    FROM loans l
    JOIN loan_products lp ON l.product_id = lp.product_id
    JOIN loan_applications la ON l.application_id = la.application_id
    WHERE l.client_id = ? AND l.loan_status != 'Fully Paid'
    ORDER BY l.release_date DESC
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$seen_loan_numbers = [];
while ($row = $result->fetch_assoc()) {
    // Normalize loan number (trim whitespace)
    $ln = trim($row['loan_number']);
    
    // Check if we've seen this loan number already
    if (!isset($seen_loan_numbers[$ln])) {
        $loans[] = $row;
        $seen_loan_numbers[$ln] = true;
    }
}
$stmt->close();

// Get payment history for each loan
foreach ($loans as &$ref_loan) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_payments, 
               SUM(payment_amount) as total_paid,
               MAX(payment_date) as last_payment_date
        FROM payments 
        WHERE loan_id = ? AND payment_status = 'Posted'
    ");
    $stmt->bind_param("i", $ref_loan['loan_id']);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    $payment_data = $payment_result->fetch_assoc();
    $stmt->close();
    
    $ref_loan['total_payments'] = $payment_data['total_payments'] ?? 0;
    $ref_loan['total_paid'] = $payment_data['total_paid'] ?? 0;
    $ref_loan['last_payment_date'] = $payment_data['last_payment_date'] ?? null;
}
unset($ref_loan); // Break the reference with the last element

// Calculate Stats
$stats = [
    'active_count' => 0,
    'total_principal' => 0,
    'total_balance' => 0,
    'total_paid' => 0
];

foreach ($loans as $stat_loan) {
    if (in_array($stat_loan['loan_status'], ['Active', 'Overdue', 'Restructured'])) {
        $stats['active_count']++;
        $stats['total_principal'] += $stat_loan['principal_amount'];
        $stats['total_balance'] += $stat_loan['remaining_balance'];
        $stats['total_paid'] += $stat_loan['total_paid'];
    }
}

// $conn->close(); // Removed to allow header to access DB

function getStatusBadgeClass($status) {
    $classes = [
        'Active' => 'badge-primary',
        'Fully Paid' => 'badge-success',
        'Overdue' => 'badge-error',
        'Restructured' => 'badge-warning',
        'Written Off' => 'badge-secondary',
        'Cancelled' => 'badge-secondary'
    ];
    return $classes[$status] ?? 'badge-secondary';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Loans - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <style>
        .loans-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }
        
        .loan-card {
            background-color: var(--color-surface-light);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border-subtle);
            transition: all var(--transition-fast);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .dark .loan-card {
            background-color: var(--color-surface-dark);
            border-color: var(--color-border-dark);
        }
        
        .loan-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--color-primary-light);
        }
        
        .loan-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
        }
        
        .dark .loan-header {
            border-bottom-color: var(--color-border-dark);
        }
        
        .loan-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 0.95rem;
            font-weight: var(--font-weight-medium);
            color: var(--color-text-main);
        }
        
        .dark .detail-value {
            color: var(--color-text-dark);
        }
        
        .progress-thin {
            height: 6px;
            border-radius: 3px;
            background-color: var(--color-border-subtle);
            margin-top: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--color-surface-light);
            border-radius: var(--radius-2xl);
            border: 1px dashed var(--color-border-subtle);
        }
        
        .dark .empty-state {
            background: var(--color-surface-dark);
            border-color: var(--color-border-dark);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: var(--color-text-muted);
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body class="bg-body-tertiary">
    <div class="d-flex">
        <?php include 'user_sidebar.php'; ?>
        
        <main class="main-content flex-grow-1">
            <?php 
            $page_title = "My Loans";
            include 'client_header.php'; 
            ?>
            
            <div class="container-fluid p-4">


                <!-- Stats Widgets -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-card card-blue h-100">
                            <div class="stat-header">
                                <div class="stat-icon primary">
                                    <span class="material-symbols-outlined">account_balance_wallet</span>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $stats['active_count']; ?></div>
                            <div class="stat-label">Active Loans</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card card-dark h-100">
                            <div class="stat-header">
                                <div class="stat-icon info">
                                    <span class="material-symbols-outlined">payments</span>
                                </div>
                            </div>
                            <div class="stat-value">₱<?php echo number_format($stats['total_principal'] / 1000, 1); ?>k</div>
                            <div class="stat-label">Total Principal</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card card-orange h-100">
                            <div class="stat-header">
                                <div class="stat-icon warning">
                                    <span class="material-symbols-outlined">pending_actions</span>
                                </div>
                            </div>
                            <div class="stat-value">₱<?php echo number_format($stats['total_balance'] / 1000, 1); ?>k</div>
                            <div class="stat-label">Remaining Balance</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card card-green h-100">
                            <div class="stat-header">
                                <div class="stat-icon success">
                                    <span class="material-symbols-outlined">check_circle</span>
                                </div>
                            </div>
                            <div class="stat-value">₱<?php echo number_format($stats['total_paid'] / 1000, 1); ?>k</div>
                            <div class="stat-label">Total Paid</div>
                        </div>
                    </div>
                </div>  
                
                <?php if (empty($loans)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <span class="material-symbols-outlined" style="font-size: inherit;">account_balance_wallet</span>
                    </div>
                    <h3 class="h5 fw-bold text-main">No Active Loans</h3>
                    <p class="text-secondary mb-4">You don't have any active loan accounts at the moment.</p>
                    <a href="apply_loan.php" class="btn btn-primary px-4 d-inline-flex align-items-center gap-2">
                        <span>Apply for Loan</span>
                        <span class="material-symbols-outlined fs-5">arrow_forward</span>
                    </a>
                </div>
                <?php else: ?>
                
                <div class="loans-grid">
                    <?php foreach ($loans as $loan): ?>
                    <?php 
                        $payment_progress = 0;
                        if ($loan['total_loan_amount'] > 0) {
                            $payment_progress = ($loan['total_paid'] / $loan['total_loan_amount']) * 100;
                        }
                    ?>
                    <div class="loan-card">
                        <div class="loan-header">
                            <div>
                                <h3 class="h6 fw-bold mb-1 text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($loan['product_name']); ?></h3>
                                <p class="small text-secondary mb-0"><?php echo htmlspecialchars($loan['loan_number']); ?></p>
                            </div>
                            <span class="badge <?php echo getStatusBadgeClass($loan['loan_status']); ?>">
                                <?php echo htmlspecialchars($loan['loan_status']); ?>
                            </span>
                        </div>
                        
                        <div class="loan-details">
                            <div class="detail-item">
                                <span class="detail-label">Principal</span>
                                <span class="detail-value">₱<?php echo number_format($loan['principal_amount'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Next Payment</span>
                                <span class="detail-value text-danger fw-bold">
                                    <?php echo $loan['next_payment_due'] ? date('M d, Y', strtotime($loan['next_payment_due'])) : 'N/A'; ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Balance</span>
                                <span class="detail-value text-primary fw-bold">₱<?php echo number_format($loan['remaining_balance'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Total Paid</span>
                                <span class="detail-value">₱<?php echo number_format($loan['total_paid'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                             <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="detail-label mb-0">Repayment</span>
                                <span class="small fw-bold text-primary"><?php echo number_format($payment_progress, 0); ?>%</span>
                            </div>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min($payment_progress, 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mt-auto pt-3 border-top border-light-subtle d-flex gap-2">
                             <?php if (in_array($loan['loan_status'], ['Active', 'Overdue'])): ?>
                            <a href="make_payment.php?loan_id=<?php echo $loan['loan_id']; ?>" class="btn btn-primary btn-sm flex-grow-1 d-flex align-items-center justify-content-center gap-2">
                                <span>Pay Now</span>
                            </a>
                            <?php endif; ?>
                            <a href="view_loan.php?id=<?php echo $loan['loan_id']; ?>" class="btn-action-view flex-grow-1 text-center">
                                View
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
