<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get client_id
$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$client_data = $result->fetch_assoc();
$client_id = $client_data['client_id'];
$stmt->close();

// Get filter parameters
$loan_filter = $_GET['loan_id'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Get all loans for filter dropdown
$loans_query = $conn->prepare("
    SELECT loan_id, loan_number 
    FROM loans 
    WHERE client_id = ?
    ORDER BY release_date DESC
");
$loans_query->bind_param("i", $client_id);
$loans_query->execute();
$loans_result = $loans_query->get_result();
$loans_list = [];
while ($row = $loans_result->fetch_assoc()) {
    $loans_list[] = $row;
}
$loans_query->close();

// Build query with filters
$where_conditions = ["p.client_id = ?"];
$params = [$client_id];
$types = 'i';

if ($loan_filter !== 'all') {
    $where_conditions[] = "p.loan_id = ?";
    $params[] = intval($loan_filter);
    $types .= 'i';
}

if ($status_filter !== 'all') {
    $where_conditions[] = "p.payment_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = implode(' AND ', $where_conditions);

// Get payment history
$payments_query = "
    SELECT 
        p.*,
        l.loan_number,
        l.product_id,
        lp.product_name
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    JOIN loan_products lp ON l.product_id = lp.product_id
    WHERE $where_clause
    ORDER BY p.payment_date DESC, p.created_at DESC
";

$stmt = $conn->prepare($payments_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments_result = $stmt->get_result();
$payments = [];
while ($row = $payments_result->fetch_assoc()) {
    $payments[] = $row;
}
$stmt->close();

// Get payment statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM(payment_amount), 0) as total_amount_paid,
        COALESCE(SUM(principal_paid), 0) as total_principal_paid,
        COALESCE(SUM(interest_paid), 0) as total_interest_paid,
        COALESCE(SUM(penalty_paid), 0) as total_penalty_paid
    FROM payments
    WHERE client_id = ? AND payment_status = 'Posted'
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $client_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payment History - Fundline</title>
    
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
            grid-template-columns: repeat(4, 1fr); /* 4 columns for stats */
            gap: 1.5rem;
        }
        
        .info-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .info-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .info-value { font-size: 1.5rem; font-weight: var(--font-weight-semibold); color: var(--color-text-main); }
        .dark .info-value { color: var(--color-text-dark); }
        
        .badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: var(--font-weight-semibold);
            display: inline-block;
        }
        .badge-success { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-warning { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .badge-error { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .badge-secondary { background-color: rgba(107, 114, 128, 0.1); color: #6b7280; }

        .payment-table { width: 100%; border-collapse: collapse; }
        .payment-table th { text-align: left; padding: 1rem; border-bottom: 2px solid var(--color-border-subtle); color: var(--color-text-muted); font-size: 0.875rem; }
        .payment-table td { padding: 1rem; border-bottom: 1px solid var(--color-border-subtle); color: var(--color-text-main); }
        .dark .payment-table th { border-bottom-color: var(--color-border-dark); }
        .dark .payment-table td { border-bottom-color: var(--color-border-dark); color: var(--color-text-dark); }

        /* Filter Styles */
        .filters { display: flex; gap: 1rem; margin-bottom: 0; }
        .filter-group { flex: 1; }
        .filter-group label { display: block; font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-select { width: 100%; padding: 0.75rem; border: 1px solid var(--color-border-subtle); border-radius: var(--radius-lg); background: var(--color-background-light); color: var(--color-text-main); }

        @media (max-width: 1024px) {
            .info-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .filters { flex-direction: column; }
        }
    </style>
</head>
<body class="light">
    <div class="dashboard-layout">
        <main class="main-content" style="margin-left: 0;">
            <header class="top-bar">
                <div>
                    <h1 class="heading-3 text-main">Payment History</h1>
                    <p class="caption text-muted">View all transactions</p>
                </div>
                <button class="btn btn-secondary btn-md" onclick="window.history.back()">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <span>Back</span>
                </button>
            </header>

            <div class="content-area">
                <div class="container" style="max-width: 1200px; margin: 0 auto;">
                    
                    <!-- Stats Section -->
                    <div class="info-section">
                        <h2 class="heading-3 text-main mb-4">Overview</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Total Payments</span>
                                <span class="info-value"><?php echo number_format($stats['total_payments']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Amount Paid</span>
                                <span class="info-value" style="color: #10b981;">₱<?php echo number_format($stats['total_amount_paid'], 2); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Principal Paid</span>
                                <span class="info-value">₱<?php echo number_format($stats['total_principal_paid'], 2); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Interest Paid</span>
                                <span class="info-value">₱<?php echo number_format($stats['total_interest_paid'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="info-section">
                        <form method="GET" class="filters" style="align-items: flex-end;">
                            <div class="filter-group">
                                <label>Loan Account</label>
                                <select name="loan_id" class="filter-select">
                                    <option value="all">All Loans</option>
                                    <?php foreach ($loans_list as $loan): ?>
                                        <option value="<?php echo $loan['loan_id']; ?>" <?php echo $loan_filter == $loan['loan_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loan['loan_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status" class="filter-select">
                                    <option value="all">All Status</option>
                                    <option value="Posted" <?php echo $status_filter === 'Posted' ? 'selected' : ''; ?>>Posted</option>
                                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Verified" <?php echo $status_filter === 'Verified' ? 'selected' : ''; ?>>Verified</option>
                                    <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg" style="height: 46px;">Apply Filters</button>
                        </form>
                    </div>

                    <!-- Table Section -->
                    <div class="info-section">
                        <h2 class="heading-3 text-main mb-4">Transaction Records</h2>
                        <div class="table-container">
                            <?php if (empty($payments)): ?>
                            <div class="empty-state" style="text-align: center; padding: 4rem 2rem;">
                                <div style="font-size: 4rem; margin-bottom: 1rem;">📝</div>
                                <h2 class="heading-4 text-muted mb-2">No Payment Records</h2>
                                <p class="body-medium text-muted">You haven't made any payments yet</p>
                            </div>
                            <?php else: ?>
                            <div class="table-wrapper" style="overflow-x: auto;">
                                <table class="payment-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Reference</th>
                                            <th>Loan Account</th>
                                            <th>Amount</th>
                                            <th>Principal</th>
                                            <th>Interest</th>
                                            <th>Penalty</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($payment['payment_reference']); ?></strong>
                                                <?php if ($payment['official_receipt_number']): ?>
                                                    <br><small style="color: var(--color-text-muted);">OR: <?php echo htmlspecialchars($payment['official_receipt_number']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($payment['loan_number']); ?></div>
                                                <small style="color: var(--color-text-muted);"><?php echo htmlspecialchars($payment['product_name']); ?></small>
                                            </td>
                                            <td>
                                                <span style="color: #10b981; font-weight: 600;">₱<?php echo number_format($payment['payment_amount'], 2); ?></span>
                                            </td>
                                            <td>₱<?php echo number_format($payment['principal_paid'], 2); ?></td>
                                            <td>₱<?php echo number_format($payment['interest_paid'], 2); ?></td>
                                            <td>₱<?php echo number_format($payment['penalty_paid'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'badge-secondary';
                                                if ($payment['payment_status'] === 'Posted') $badge_class = 'badge-success';
                                                elseif ($payment['payment_status'] === 'Pending') $badge_class = 'badge-warning';
                                                elseif ($payment['payment_status'] === 'Cancelled') $badge_class = 'badge-error';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($payment['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Theme Toggle Logic
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = themeToggle.querySelector('.material-symbols-outlined');
        
        function updateTheme(theme) {
            const html = document.documentElement;
            if (theme === 'dark') {
                html.classList.remove('light');
                html.classList.add('dark');
                themeIcon.textContent = 'light_mode';
                localStorage.setItem('theme', 'dark');
            } else {
                html.classList.remove('dark');
                html.classList.add('light');
                themeIcon.textContent = 'dark_mode';
                localStorage.setItem('theme', 'light');
            }
        }

        themeToggle.addEventListener('click', function() {
            const isDark = document.documentElement.classList.contains('dark');
            updateTheme(isDark ? 'light' : 'dark');
        });
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            updateTheme('dark');
        }
    </script>
</body>
</html>
