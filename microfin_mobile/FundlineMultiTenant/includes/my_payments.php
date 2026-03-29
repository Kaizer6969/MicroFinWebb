<?php
/**
 * Client My Payments Page
 * Displays payment history for the client
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
$client = $result->fetch_assoc();
$client_id = $client['client_id'];
$stmt->close();

// Get pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    WHERE l.client_id = ?
");
$count_stmt->bind_param("i", $client_id);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Get payments
$payments = [];
$stmt = $conn->prepare("
    SELECT p.*, l.loan_number, lp.product_name
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    JOIN loan_products lp ON l.product_id = lp.product_id
    WHERE l.client_id = ?
    ORDER BY p.payment_date DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $client_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}
$stmt->close();

// Get Total Stats (Lifetime)
$stats = [
    'total_spent' => 0,
    'total_count' => $total_rows,
    'last_payment_amount' => 0,
    'last_payment_date' => null
];

// Get total spent
$sum_stmt = $conn->prepare("
    SELECT SUM(payment_amount) as total 
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    WHERE l.client_id = ? AND p.payment_status = 'Posted'
");
$sum_stmt->bind_param("i", $client_id);
$sum_stmt->execute();
$stats['total_spent'] = $sum_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$sum_stmt->close();

// Get last payment
$last_stmt = $conn->prepare("
    SELECT payment_amount, payment_date
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    WHERE l.client_id = ? AND p.payment_status = 'Posted'
    ORDER BY payment_date DESC LIMIT 1
");
$last_stmt->bind_param("i", $client_id);
$last_stmt->execute();
$last_res = $last_stmt->get_result();
if ($last_row = $last_res->fetch_assoc()) {
    $stats['last_payment_amount'] = $last_row['payment_amount'];
    $stats['last_payment_date'] = $last_row['payment_date'];
}
$last_stmt->close();

// $conn->close(); // Removed to allow header to access DB

function getStatusBadgeClass($status) {
    $classes = [
        'Posted' => 'badge-success',
        'Pending' => 'badge-warning',
        'Rejected' => 'badge-error',
        'Voided' => 'badge-secondary'
    ];
    return $classes[$status] ?? 'badge-secondary';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Payments - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css" rel="stylesheet">

    
    <style>
        .payments-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }

        .payment-card {
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
        
        .dark .payment-card {
            background-color: var(--color-surface-dark);
            border-color: var(--color-border-dark);
        }
        
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--color-primary-light);
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
        }
        
        .dark .payment-header {
            border-bottom-color: var(--color-border-dark);
        }
        
        .payment-details {
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
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <?php include 'user_sidebar.php'; ?>
        
        <main class="main-content w-100 bg-body-tertiary min-vh-100">
            <?php include 'client_header.php'; ?>
            
            <!-- Page Content -->
            <div class="container-fluid p-4">
                <!-- Stats Widgets -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4">
                        <div class="stat-card card-red h-100">
                            <div class="stat-header">
                                <div class="stat-icon primary">
                                    <span class="material-symbols-outlined">payments</span>
                                </div>
                            </div>
                            <div class="stat-value">₱<?php echo number_format($stats['total_spent'], 2); ?></div>
                            <div class="stat-label">Total Spent</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card card-green h-100">
                            <div class="stat-header">
                                <div class="stat-icon success">
                                    <span class="material-symbols-outlined">history</span>
                                </div>
                            </div>
                            <div class="stat-value">₱<?php echo number_format($stats['last_payment_amount'], 2); ?></div>
                            <div class="stat-label">
                                Last Payment
                                <?php if($stats['last_payment_date']): ?>
                                <small class="d-block text-white opacity-75 fw-normal" style="font-size: 0.75rem;"><?php echo date('M d', strtotime($stats['last_payment_date'])); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card card-blue h-100">
                            <div class="stat-header">
                                <div class="stat-icon info">
                                    <span class="material-symbols-outlined">receipt_long</span>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $stats['total_count']; ?></div>
                            <div class="stat-label">Transactions</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4">
                   
                    <div class="text-muted small">
                         <?php echo $total_rows; ?> transactions
                    </div>
                </div>

                <?php if (empty($payments)): ?>
                <div class="empty-state">
                     <div class="empty-icon">
                        <span class="material-symbols-outlined" style="font-size: inherit;">receipt_long</span>
                    </div>
                    <h3 class="h5 fw-bold text-main">No Payments Yet</h3>
                    <p class="text-muted mb-4">You haven't made any payments yet.</p>
                </div>
                <?php else: ?>
                
                <div class="payments-grid">
                    <?php foreach ($payments as $payment): ?>
                    <div class="payment-card">
                        <div class="payment-header">
                            <div>
                                <h3 class="h6 fw-bold mb-1 text-truncate" style="max-width: 200px;">
                                    <?php echo htmlspecialchars($payment['official_receipt_number'] ?: $payment['payment_reference']); ?>
                                </h3>
                                <p class="small text-secondary mb-0"><?php echo date('M d, Y • h:i A', strtotime($payment['payment_date'])); ?></p>
                            </div>
                            <span class="badge <?php echo getStatusBadgeClass($payment['payment_status']); ?>">
                                <?php echo htmlspecialchars($payment['payment_status']); ?>
                            </span>
                        </div>
                        
                        <div class="payment-details">
                            <div class="detail-item">
                                <span class="detail-label">Amount Paid</span>
                                <span class="detail-value text-primary fw-bold">₱<?php echo number_format($payment['payment_amount'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Method</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Loan Account</span>
                                <span class="detail-value text-wrap"><?php echo htmlspecialchars($payment['loan_number']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Product</span>
                                <span class="detail-value text-truncate"><?php echo htmlspecialchars($payment['product_name']); ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-auto pt-3 border-top border-light-subtle">
                             <a href="#" class="btn btn-outline-secondary btn-sm w-100 d-flex align-items-center justify-content-center gap-2 view-payment-btn" data-id="<?php echo $payment['payment_id']; ?>">
                                <span>View Receipt</span>
                                <span class="material-symbols-outlined fs-6">receipt</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4 d-flex justify-content-center">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="paymentModalBody">
                    <!-- Loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Use standard modal logic
         document.addEventListener('DOMContentLoaded', function() {
            const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            const modalBody = document.getElementById('paymentModalBody');
            
            document.querySelectorAll('.view-payment-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const paymentId = this.dataset.id;
                    const url = `view_payment.php?id=${paymentId}&modal=1`;
                    
                    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
                    paymentModal.show();
                    
                    fetch(url)
                    .then(r => r.text())
                    .then(html => modalBody.innerHTML = html)
                    .catch(() => modalBody.innerHTML = '<p class="text-danger text-center">Error loading</p>');
                });
            });
         });
    </script>
    <!-- Tour Removed per request -->
</body>
</html>

