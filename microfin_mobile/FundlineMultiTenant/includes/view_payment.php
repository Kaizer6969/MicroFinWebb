<?php
/**
 * View Payment Details
 * Accessible by both clients and employees
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_type = $_SESSION['user_type'];
$role_name = $_SESSION['role_name'] ?? 'User';

$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($payment_id === 0) {
    header("Location: " . ($user_type === 'Employee' ? 'payment.php' : 'my_loans.php'));
    exit();
}

// Get payment details with access control
$query = "
    SELECT p.*, l.loan_number, l.client_id, 
           c.first_name, c.last_name, c.client_code,
           u.email
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    JOIN clients c ON l.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    WHERE p.payment_id = ?
";

// Add access control for clients
if ($user_type === 'Client') {
    $query .= " AND c.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $payment_id, $user_id);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $payment_id);
}

$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    header("Location: " . ($user_type === 'Employee' ? 'payment.php' : 'my_loans.php'));
    exit();
}

$conn->close();
?>
<?php
$is_modal = isset($_GET['modal']) && $_GET['modal'] == 1;

if ($is_modal) {
    // --- MODAL CONTENT VIEW ---
    ?>
    <style>
        .modal-info-card {
            background-color: var(--color-surface-light-alt);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--color-border-subtle);
        }
        
        .modal-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }
        
        .modal-info-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .modal-info-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .modal-info-value { font-size: 0.95rem; font-weight: 600; color: var(--color-text-main); word-break: break-word; }
        
        .amount-display-modal {
            font-size: 2rem;
            font-weight: 800;
            color: var(--color-primary);
            margin: 0.5rem 0;
            line-height: 1;
        }
    </style>

    <div class="p-4">
        <div class="text-center mb-4">
            <h4 class="fw-bold mb-1">Payment Details</h4>
            <p class="text-muted small">Transaction Receipt</p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4 text-center p-4 bg-light">
            <div class="caption text-uppercase text-muted fw-bold small">Total Paid Amount</div>
            <div class="amount-display-modal">₱<?php echo number_format($payment['payment_amount'], 2); ?></div>
            <div class="d-inline-block px-3 py-1 rounded-pill bg-success bg-opacity-10 text-success fw-bold small mt-2">
                <?php echo htmlspecialchars($payment['payment_status']); ?>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <?php 
                    $date = new DateTime($payment['created_at']);
                    $date->setTimezone(new DateTimeZone('Asia/Manila'));
                    echo $date->format('F d, Y h:i A'); 
                ?>
            </p>
        </div>

        <div class="mb-4">
            <h6 class="fw-bold text-main mb-3">Transaction Details</h6>
            <div class="modal-info-card">
                <div class="modal-info-grid">
                    <div class="modal-info-item">
                        <span class="modal-info-label">Reference No.</span>
                        <span class="modal-info-value"><?php echo htmlspecialchars($payment['official_receipt_number'] ?: $payment['payment_reference'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">Payment Method</span>
                        <span class="modal-info-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">Amount Paid</span>
                        <span class="modal-info-value">₱<?php echo number_format($payment['payment_amount'], 2); ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">Penalty Included</span>
                        <span class="modal-info-value text-danger">₱<?php echo number_format($payment['penalty_paid'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <h6 class="fw-bold text-main mb-3">Loan Information</h6>
            <div class="modal-info-card">
                <div class="modal-info-grid">
                    <div class="modal-info-item">
                        <span class="modal-info-label">Loan Number</span>
                        <span class="modal-info-value"><?php echo htmlspecialchars($payment['loan_number']); ?></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">Client Name</span>
                        <span class="modal-info-value"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center pt-2">
            <button class="btn btn-dark rounded-pill px-4 shadow-sm" onclick="window.print()">
                <span class="material-symbols-outlined fs-6 align-middle me-1">print</span>
                Print Receipt
            </button>
        </div>
    </div>
    <?php
} else {
    // --- FULL PAGE VIEW ---
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payment Details - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
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
        
        .info-card {
            background-color: var(--color-surface-light);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        .dark .info-card { background-color: var(--color-surface-dark); }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .info-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .info-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .info-value { font-size: 1rem; font-weight: var(--font-weight-semibold); color: var(--color-text-main); }
        .dark .info-value { color: var(--color-text-dark); }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px dashed var(--color-border-subtle);
        }

        .amount-display {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--color-primary);
            margin: 0.5rem 0;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .status-header { flex-direction: column; align-items: start; gap: 1rem; }
        }
    </style>
</head>
<body class="light">
    <div class="dashboard-layout">
        <main class="main-content">
            <header class="top-bar">
                 <div>
                    <h1 class="heading-3 text-main">Payment Details</h1>
                    <p class="caption text-muted">Transaction Receipt</p>
                </div>
                <button class="btn btn-secondary btn-md" onclick="window.history.back()">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <span>Back</span>
                </button>
            </header>

            <div class="content-area">
                <div class="info-card" style="max-width: 600px; margin: 0 auto;">
                    <div class="receipt-header">
                        <div class="caption text-muted">Total Paid Amount</div>
                        <div class="amount-display">₱<?php echo number_format($payment['payment_amount'], 2); ?></div>
                        <div class="status-badge">Payment Successful</div>
                        <p class="body-small text-muted mt-2">
                            <?php 
                                $date = new DateTime($payment['created_at']);
                                $date->setTimezone(new DateTimeZone('Asia/Manila'));
                                echo $date->format('F d, Y h:i A'); 
                            ?>
                        </p>
                    </div>

                    <h3 class="heading-4 text-main mb-4">Transaction Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Reference / OR Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['official_receipt_number'] ?: $payment['payment_reference'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Payment Method</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Amount</span>
                            <span class="info-value">₱<?php echo number_format($payment['payment_amount'], 2); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Penalty Included</span>
                            <span class="info-value">₱<?php echo number_format($payment['penalty_paid'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <div class="info-card" style="max-width: 600px; margin: 0 auto;">
                    <h3 class="heading-4 text-main mb-4">Loan Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Loan Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['loan_number']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Client Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></span>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 2rem;">
                    <button class="btn btn-secondary btn-lg" onclick="window.print()">
                        <span class="material-symbols-outlined">print</span>
                        <span>Print Receipt</span>
                    </button>
                </div>
            </div>
        </main>
    </div>
    <script>
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.remove('light');
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>
<?php } ?>

