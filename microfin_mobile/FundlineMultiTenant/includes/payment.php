<?php
/**
 * Admin Payments Management Page - Fundline Web Application
 * Protected page requiring authentication
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['user_type'] !== 'Employee') {
    header("Location: dashboard.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_name = $_SESSION['role_name'] ?? 'Employee';
$avatar_letter = strtoupper(substr($username, 0, 1));

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "p.payment_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "p.payment_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "p.payment_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(p.payment_reference LIKE ? OR p.official_receipt_number LIKE ? OR l.loan_number LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_query = "
    SELECT COUNT(*) as total
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    JOIN clients c ON p.client_id = c.client_id
    $where_clause
";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    if (!empty($types)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_payments = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_payments = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($total_payments / $per_page);

$query = "
    SELECT 
        p.payment_id,
        p.payment_reference,
        p.payment_date,
        p.payment_amount,
        p.principal_paid,
        p.interest_paid,
        p.penalty_paid,
        p.payment_method,
        p.payment_status,
        p.official_receipt_number,
        l.loan_number,
        CONCAT(c.first_name, ' ', c.last_name) as client_name,
        c.client_code,
        CONCAT(e.first_name, ' ', e.last_name) as received_by_name
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    JOIN clients c ON p.client_id = c.client_id
    LEFT JOIN employees e ON p.received_by = e.employee_id
    $where_clause
    ORDER BY p.payment_date DESC, p.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments_result = $stmt->get_result();
$stmt->close();

$stats_query = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN payment_status = 'Posted' THEN 1 ELSE 0 END) as posted_payments,
        SUM(CASE WHEN payment_status = 'Pending' THEN 1 ELSE 0 END) as pending_payments,
        COALESCE(SUM(CASE WHEN payment_status = 'Posted' THEN payment_amount ELSE 0 END), 0) as total_collected,
        COALESCE(SUM(CASE WHEN payment_status = 'Posted' AND DATE(payment_date) = CURDATE() THEN payment_amount ELSE 0 END), 0) as today_collection
    FROM payments
";
$stats = $conn->query($stats_query)->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payments - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <style>
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-body-tertiary">
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <!-- Header -->
            <?php include 'admin_header.php'; ?>
            
            <div class="content-area">
                <!-- Title section removed -->
                
                <!-- Stats Grid -->
                <div class="row g-4 mb-5">
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-blue h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">payments</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['total_payments']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Total Payments</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-green h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">check_circle</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['posted_payments']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Posted Payments</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-orange h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">pending</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['pending_payments']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Pending Payments</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-blue h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">today</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 h3">₱<?php echo number_format($stats['today_collection'], 2); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Today's Collection</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters and Search -->
                <div class="filters-section">
                    <form method="GET" action="" class="filters-row">
                        <div class="filter-group">
                            <label class="form-label small text-secondary fw-bold" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="Posted" <?php echo $status_filter === 'Posted' ? 'selected' : ''; ?>>Posted</option>
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="form-label small text-secondary fw-bold" for="date_from">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>

                        <div class="filter-group">
                            <label class="form-label small text-secondary fw-bold" for="date_to">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="filter-group flex-grow-1">
                            <label class="form-label small text-secondary fw-bold" for="search">Search</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <span class="material-symbols-outlined fs-6">search</span>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Ref #, Client, Loan #...">
                            </div>
                        </div>
                        
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                Apply Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Payments Table -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-main">Payment History</h5>
                            <p class="text-muted small mb-0">Record of all transactions</p>
                        </div>
                    </div>
                
                    <div class="table-container border-0 shadow-none mb-0">
                        <table class="data-table">
                             <thead>
                                <tr>
                                    <th class="ps-4">Reference / Loan</th>
                                    <th>Client</th>
                                    <th>Amount</th>
                                    <th>Breakdown</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments_result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="7" class="table-empty">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="material-symbols-outlined fs-1 opacity-25 mb-2">payments</span>
                                            <p class="mb-0">No payments found matching your criteria</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-main"><?php echo htmlspecialchars($payment['payment_reference']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($payment['loan_number']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-main"><?php echo htmlspecialchars($payment['client_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($payment['client_code']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">₱<?php echo number_format($payment['payment_amount'], 2); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($payment['payment_method']); ?></div>
                                        </td>
                                        <td class="small">
                                            <div class="text-muted">P: <?php echo number_format($payment['principal_paid'], 2); ?></div>
                                            <div class="text-muted">I: <?php echo number_format($payment['interest_paid'], 2); ?></div>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                        </td>
                                        <td>
                                            <?php
                                                $statusClass = 'badge-secondary';
                                                if ($payment['payment_status'] === 'Posted') $statusClass = 'badge-success';
                                                if ($payment['payment_status'] === 'Pending') $statusClass = 'badge-warning';
                                                if ($payment['payment_status'] === 'Cancelled') $statusClass = 'badge-danger';
                                            ?>
                                            <span class="badge rounded-pill <?php echo $statusClass; ?> px-3 py-1 fw-normal">
                                                <?php echo htmlspecialchars($payment['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="action-buttons justify-content-end">
                                                <button type="button" class="btn-action-view view-payment-btn" data-id="<?php echo $payment['payment_id']; ?>">
                                                    View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center py-4">
                    <nav aria-label="Payment pagination">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>
    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" id="paymentModalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Verify Bootstrap availability
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap is not loaded. Modal cannot work.');
                return;
            }

            const modalElement = document.getElementById('paymentModal');
            if (!modalElement) {
                console.error('Payment Modal element not found in DOM.');
                return;
            }

            const paymentModal = new bootstrap.Modal(modalElement);
            const modalBody = document.getElementById('paymentModalBody');

            // Use Event Delegation for better reliability
            document.body.addEventListener('click', function(event) {
                const btn = event.target.closest('.view-payment-btn');
                
                if (btn) {
                    event.preventDefault();
                    const paymentId = btn.dataset.id;
                    
                    if (!paymentId) {
                        console.error('No payment ID found on button');
                        return;
                    }

                    // Show spinner
                    modalBody.innerHTML = `
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted small">Loading receipt...</p>
                        </div>
                    `;
                    
                    paymentModal.show();

                    // Fetch details
                    fetch(`view_payment.php?id=${paymentId}&modal=1`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(html => {
                            modalBody.innerHTML = html;
                        })
                        .catch(error => {
                            modalBody.innerHTML = `
                                <div class="text-center py-5">
                                    <span class="material-symbols-outlined text-danger fs-1">error</span>
                                    <p class="text-danger mt-2">Failed to load payment details.</p>
                                    <p class="small text-muted">${error.message}</p>
                                </div>
                            `;
                            console.error('Fetch Error:', error);
                        });
                }
            });
        });
    </script>
</body>
</html>
