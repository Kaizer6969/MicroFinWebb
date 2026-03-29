<?php
/**
 * Admin Loans Management Page - Fundline Web Application
 * Protected page requiring authentication
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Redirect if not an employee
if ($_SESSION['user_type'] !== 'Employee') {
    header("Location: dashboard.php");
    exit();
}

// Include database connection
require_once '../config/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_name = $_SESSION['role_name'] ?? 'Employee';

// Get first letter of username for avatar
$avatar_letter = strtoupper(substr($username, 0, 1));

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "l.loan_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(l.loan_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get loans with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Count total loans
$count_query = "
    SELECT COUNT(*) as total
    FROM loans l
    JOIN clients c ON l.client_id = c.client_id
    JOIN loan_products lp ON l.product_id = lp.product_id
    $where_clause
";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    if (!empty($types)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_loans = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_loans = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($total_loans / $per_page);

// Get loans
$query = "
    SELECT 
        l.loan_id,
        l.loan_number,
        l.principal_amount,
        l.total_loan_amount,
        l.interest_rate,
        l.loan_term_months,
        l.monthly_amortization,
        l.release_date,
        l.maturity_date,
        l.loan_status,
        l.total_paid,
        l.remaining_balance,
        l.days_overdue,
        l.next_payment_due,
        CONCAT(c.first_name, ' ', c.last_name) as client_name,
        c.client_code,
        lp.product_name,
        CONCAT(e.first_name, ' ', e.last_name) as released_by_name
    FROM loans l
    JOIN clients c ON l.client_id = c.client_id
    JOIN loan_products lp ON l.product_id = lp.product_id
    LEFT JOIN employees e ON l.released_by = e.employee_id
    $where_clause
    ORDER BY l.release_date DESC
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
$loans_result = $stmt->get_result();
$stmt->close();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_loans,
        SUM(CASE WHEN loan_status = 'Active' THEN 1 ELSE 0 END) as active_loans,
        SUM(CASE WHEN loan_status = 'Overdue' THEN 1 ELSE 0 END) as overdue_loans,
        SUM(CASE WHEN loan_status = 'Fully Paid' THEN 1 ELSE 0 END) as paid_loans,
        COALESCE(SUM(CASE WHEN loan_status IN ('Active', 'Overdue') THEN remaining_balance ELSE 0 END), 0) as total_outstanding,
        COALESCE(SUM(total_paid), 0) as total_collected
    FROM loans
";
$stats = $conn->query($stats_query)->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Loans - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
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
                                    <span class="material-symbols-outlined text-white">account_balance_wallet</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['total_loans']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Total Loans</p>
                        </div>
                    </div>
                    
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-green h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">check_circle</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['active_loans']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Active Loans</p>
                        </div>
                    </div>
                    
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-red h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">warning</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['overdue_loans']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Overdue Loans</p>
                        </div>
                    </div>
                    
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-orange h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">payments</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 h3">₱<?php echo number_format($stats['total_outstanding'], 2); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Total Outstanding</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters and Search -->
                <div class="filters-section">
                    <form method="GET" action="" class="filters-row">
                        <div class="filter-group">
                            <label class="form-label small text-secondary fw-bold" for="status">Filter by Status</label>
                            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Fully Paid" <?php echo $status_filter === 'Fully Paid' ? 'selected' : ''; ?>>Fully Paid</option>
                                <option value="Overdue" <?php echo $status_filter === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="Defaulted" <?php echo $status_filter === 'Defaulted' ? 'selected' : ''; ?>>Defaulted</option>
                            </select>
                        </div>
                        
                        <div class="filter-group flex-grow-1">
                            <label class="form-label small text-secondary fw-bold" for="search">Search</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <span class="material-symbols-outlined fs-6">search</span>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Search by loan #, client name...">
                            </div>
                        </div>
                        
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                Apply Filter
                            </button>
                        </div>
                        
                        <?php if ($status_filter !== 'all' || !empty($search)): ?>
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <a href="admin_loans.php" class="btn btn-outline-primary rounded-pill px-4">
                                Clear
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Loans Table -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-main">Loans List</h5>
                            <p class="text-muted small mb-0">Manage and monitor all loans</p>
                        </div>
                    </div>
                
                    <div class="table-container border-0 shadow-none mb-0">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">Loan #</th>
                                    <th>Client</th>
                                    <th>Amount</th>
                                    <th>Release Date</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($loans_result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="8" class="table-empty">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="material-symbols-outlined fs-1 opacity-25 mb-2">account_balance_wallet</span>
                                            <p class="mb-0">No loans found matching your criteria</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php while ($loan = $loans_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-semibold text-main"><?php echo htmlspecialchars($loan['loan_number']); ?></span>
                                            <div class="small text-muted"><?php echo htmlspecialchars($loan['product_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-main"><?php echo htmlspecialchars($loan['client_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($loan['client_code']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">₱<?php echo number_format($loan['principal_amount'], 2); ?></div>
                                            <div class="small text-muted"><?php echo $loan['loan_term_months']; ?> mos @ <?php echo $loan['interest_rate']; ?>%</div>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo date('M d, Y', strtotime($loan['release_date'])); ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold <?php echo $loan['remaining_balance'] > 0 ? 'text-dark' : 'text-success'; ?>">
                                                ₱<?php echo number_format($loan['remaining_balance'], 2); ?>
                                            </div>
                                            <div class="small text-muted">of ₱<?php echo number_format($loan['total_loan_amount'], 2); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($loan['remaining_balance'] > 0): ?>
                                                <div class="<?php echo ($loan['days_overdue'] > 0) ? 'text-danger fw-bold' : 'text-muted'; ?> small">
                                                    <?php echo $loan['next_payment_due'] ? date('M d, Y', strtotime($loan['next_payment_due'])) : '-'; ?>
                                                </div>
                                                <?php if ($loan['days_overdue'] > 0): ?>
                                                    <div class="text-danger small" style="font-size: 0.7rem;">
                                                        Overdue: <?php echo $loan['days_overdue']; ?> days
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-success small">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $statusClass = 'badge-secondary';
                                                if ($loan['loan_status'] === 'Active') $statusClass = 'badge-success';
                                                if ($loan['loan_status'] === 'Overdue') $statusClass = 'badge-danger';
                                                if ($loan['loan_status'] === 'Fully Paid') $statusClass = 'badge-primary';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?> rounded-pill fw-normal px-3">
                                                <?php echo htmlspecialchars($loan['loan_status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="view_loan.php?id=<?php echo $loan['loan_id']; ?>" class="btn-action-view">
                                                View
                                            </a>
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
                    <nav aria-label="Loan pagination">
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
        </main>
    </div>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check for theme in localStorage and apply
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</body>
</html>
