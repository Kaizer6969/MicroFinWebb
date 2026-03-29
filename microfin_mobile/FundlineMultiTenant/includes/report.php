<?php
/**
 * Admin Reports Page - Fundline Web Application
 * Protected page requiring authentication
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Employee') {
    header("Location: dashboard.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_name = $_SESSION['role_name'] ?? 'Employee';
$avatar_letter = strtoupper(substr($username, 0, 1));

// Get report type and filters
$report_type = $_GET['report_type'] ?? 'summary';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';

// Initialize report data
$report_data = [];
$report_columns = [];
$report_keys = [];
$report_title = '';
$detailed_report = null;

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch detailed report data based on report type
switch ($report_type) {
    case 'summary':
        $report_title = 'Summary Dashboard';
        
        // 1. Total Collections
        $stmt = $conn->prepare("SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE payment_date BETWEEN ? AND ? AND payment_status = 'Posted'");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $total_collections = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        
        // 2. Loans Disbursed
        $stmt = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(principal_amount), 0) FROM loans WHERE release_date BETWEEN ? AND ?");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $loan_res = $stmt->get_result()->fetch_row();
        $total_loans_count = $loan_res[0];
        $total_loans_amount = $loan_res[1];
        $stmt->close();
        
        // 3. Applications Submitted
        $stmt = $conn->prepare("SELECT COUNT(*) FROM loan_applications WHERE submitted_date BETWEEN ? AND ?");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $total_apps = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        
        // 4. New Clients
        $stmt = $conn->prepare("SELECT COUNT(*) FROM clients WHERE registration_date BETWEEN ? AND ?");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $new_clients = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        break;


    case 'collection':
        $report_title = 'Collection Report';
        $collection_query = "
            SELECT 
                p.payment_id,
                p.payment_date,
                l.loan_number,
                CONCAT(c.first_name, ' ', c.last_name) as client_name,
                lp.product_name,
                p.payment_amount,
                p.principal_paid,
                p.interest_paid,
                p.penalty_paid,
                p.payment_status,
                CONCAT(e.first_name, ' ', e.last_name) as collector_name,
                p.payment_method,
                p.payment_reference_number as payment_reference,
                p.official_receipt_number
            FROM payments p
            JOIN loans l ON p.loan_id = l.loan_id
            JOIN clients c ON l.client_id = c.client_id
            JOIN loan_products lp ON l.product_id = lp.product_id
            LEFT JOIN employees e ON p.received_by = e.employee_id
            WHERE p.payment_date BETWEEN ? AND ?
            AND p.payment_status = 'Posted'
        ";
        
        if ($status_filter) {
            $collection_query .= " AND l.loan_status = ?";
        }
        
        $collection_query .= " ORDER BY p.payment_date DESC";
        
        $stmt = $conn->prepare($collection_query);
        if ($status_filter) {
            $stmt->bind_param("sss", $date_from, $date_to, $status_filter);
        } else {
            $stmt->bind_param("ss", $date_from, $date_to);
        }
        $stmt->execute();
        $detailed_report = $stmt->get_result();
        $report_columns = ['Date', 'Loan #', 'Client', 'Amount', 'Status', 'Action'];
        $report_keys = ['payment_date', 'loan_number', 'client_name', 'payment_amount', 'payment_status'];
        $stmt->close();
        break;
        
    case 'payment':
        $report_title = 'Payment Report';
        $payment_query = "
            SELECT 
                p.payment_id,
                p.payment_date,
                p.payment_reference_number as payment_reference,
                l.loan_number,
                CONCAT(c.first_name, ' ', c.last_name) as client_name,
                p.payment_amount,
                p.payment_method,
                p.payment_status,
                p.posted_date,
                CONCAT(e.first_name, ' ', e.last_name) as posted_by,
                p.principal_paid,
                p.interest_paid,
                p.penalty_paid,
                p.official_receipt_number
            FROM payments p
            JOIN loans l ON p.loan_id = l.loan_id
            JOIN clients c ON l.client_id = c.client_id
            LEFT JOIN employees e ON p.posted_by = e.employee_id
            WHERE p.payment_date BETWEEN ? AND ?
        ";
        
        if ($status_filter) {
            $payment_query .= " AND p.payment_status = ?";
        }
        
        $payment_query .= " ORDER BY p.payment_date DESC";
        
        $stmt = $conn->prepare($payment_query);
        if ($status_filter) {
            $stmt->bind_param("sss", $date_from, $date_to, $status_filter);
        } else {
            $stmt->bind_param("ss", $date_from, $date_to);
        }
        $stmt->execute();
        $detailed_report = $stmt->get_result();
        $report_columns = ['Date', 'Ref #', 'Loan #', 'Client', 'Amount', 'Status', 'Action'];
        $report_keys = ['payment_date', 'payment_reference', 'loan_number', 'client_name', 'payment_amount', 'payment_status'];
        $stmt->close();
        break;
        
    case 'loan':
        $report_title = 'Loan Portfolio Report';
        $loan_query = "
            SELECT 
                l.loan_id,
                l.loan_number,
                CONCAT(c.first_name, ' ', c.last_name) as client_name,
                lp.product_name,
                l.principal_amount,
                l.remaining_balance,
                l.interest_rate,
                l.loan_term_months,
                l.release_date,
                l.maturity_date,
                l.loan_status,
                l.next_payment_due,
                l.maturity_date as next_payment_date
            FROM loans l
            JOIN clients c ON l.client_id = c.client_id
            JOIN loan_products lp ON l.product_id = lp.product_id
            WHERE l.release_date BETWEEN ? AND ?
        ";
        
        if ($status_filter) {
            $loan_query .= " AND l.loan_status = ?";
        }
        
        $loan_query .= " ORDER BY l.release_date DESC";
        
        $stmt = $conn->prepare($loan_query);
        if ($status_filter) {
            $stmt->bind_param("sss", $date_from, $date_to, $status_filter);
        } else {
            $stmt->bind_param("ss", $date_from, $date_to);
        }
        $stmt->execute();
        $detailed_report = $stmt->get_result();
        $report_columns = ['Loan #', 'Client', 'Product', 'Principal', 'Balance', 'Status', 'Action'];
        $report_keys = ['loan_number', 'client_name', 'product_name', 'principal_amount', 'remaining_balance', 'loan_status'];
        $stmt->close();
        break;
        
    case 'disbursement':
        $report_title = 'Disbursement Report';
        $disbursement_query = "
            SELECT 
                l.loan_id,
                l.loan_number,
                CONCAT(c.first_name, ' ', c.last_name) as client_name,
                lp.product_name,
                l.principal_amount,
                l.net_proceeds,
                l.release_date,
                l.disbursement_method,
                CONCAT(e.first_name, ' ', e.last_name) as released_by,
                l.loan_status
            FROM loans l
            JOIN clients c ON l.client_id = c.client_id
            JOIN loan_products lp ON l.product_id = lp.product_id
            LEFT JOIN employees e ON l.released_by = e.employee_id
            WHERE l.release_date BETWEEN ? AND ?
        ";
        
        if ($status_filter) {
            $disbursement_query .= " AND l.loan_status = ?";
        }
        
        $disbursement_query .= " ORDER BY l.release_date DESC";
        
        $stmt = $conn->prepare($disbursement_query);
        if ($status_filter) {
            $stmt->bind_param("sss", $date_from, $date_to, $status_filter);
        } else {
            $stmt->bind_param("ss", $date_from, $date_to);
        }
        $stmt->execute();
        $detailed_report = $stmt->get_result();
        $report_columns = ['Loan #', 'Client', 'Product', 'Principal', 'Net Proceeds', 'Date', 'Status', 'Action'];
        $report_keys = ['loan_number', 'client_name', 'product_name', 'principal_amount', 'net_proceeds', 'release_date', 'loan_status'];
        $stmt->close();
        break;
        
    case 'application':
        $report_title = 'Loan Application Report';
        $application_query = "
            SELECT 
                la.application_id,
                la.application_number,
                CONCAT(c.first_name, ' ', c.last_name) as client_name,
                lp.product_name,
                la.requested_amount,
                la.loan_term_months,
                la.application_status,
                la.submitted_date,
                la.approval_date,
                CONCAT(e.first_name, ' ', e.last_name) as reviewed_by
            FROM loan_applications la
            JOIN clients c ON la.client_id = c.client_id
            JOIN loan_products lp ON la.product_id = lp.product_id
            LEFT JOIN employees e ON la.reviewed_by = e.employee_id
            WHERE la.submitted_date BETWEEN ? AND ?
        ";
        
        if ($status_filter) {
            $application_query .= " AND la.application_status = ?";
        }
        
        $application_query .= " ORDER BY la.submitted_date DESC";
        
        $stmt = $conn->prepare($application_query);
        if ($status_filter) {
            $stmt->bind_param("sss", $date_from, $date_to, $status_filter);
        } else {
            $stmt->bind_param("ss", $date_from, $date_to);
        }
        $stmt->execute();
        $detailed_report = $stmt->get_result();
        $report_columns = ['App #', 'Client', 'Product', 'Amount', 'Term', 'Status', 'Action'];
        $report_keys = ['application_number', 'client_name', 'product_name', 'requested_amount', 'loan_term_months', 'application_status'];
        $stmt->close();
        break;
}

// Get status options for filter
$status_options_query = "
    SELECT DISTINCT payment_status as status FROM payments
    UNION
    SELECT DISTINCT loan_status FROM loans
    UNION
    SELECT DISTINCT application_status FROM loan_applications
    ORDER BY status
";
$status_options = $conn->query($status_options_query);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <!-- PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .stats-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
    </style>
</head>
<body class="bg-light">
    
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'admin_header.php'; ?>
            
            <div class="p-4">
                <!-- Report Type Navigation -->
                <div class="mb-4">
                    <nav class="nav nav-pills flex-column flex-sm-row gap-2">
                        <?php
                        $tabs = [
                            'summary' => ['icon' => 'dashboard', 'label' => 'Summary'],
                            'collection' => ['icon' => 'payments', 'label' => 'Collection'],
                            'payment' => ['icon' => 'receipt_long', 'label' => 'Payment'],
                            'loan' => ['icon' => 'account_balance_wallet', 'label' => 'Loan Portfolio'],
                            'disbursement' => ['icon' => 'credit_score', 'label' => 'Disbursements'],
                            'application' => ['icon' => 'description', 'label' => 'Applications']
                        ];
                        
                        foreach ($tabs as $key => $tab):
                            $isActive = ($report_type === $key);
                            $activeClass = $isActive ? 'active bg-primary' : 'bg-white text-secondary border';
                            $url = "?report_type=$key&date_from=$date_from&date_to=$date_to";
                            if (!empty($status_filter)) $url .= "&status=$status_filter";
                        ?>
                            <a class="nav-link <?php echo $activeClass; ?> d-flex align-items-center justify-content-center gap-2 shadow-sm" href="<?php echo $url; ?>">
                                <span class="material-symbols-outlined fs-5"><?php echo $tab['icon']; ?></span>
                                <?php echo $tab['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Filters Section -->
                <div class="filters-section bg-white p-4 rounded-4 shadow-sm mb-4 border">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                        
                        <div class="col-md-3">
                            <label class="form-label small text-secondary fw-bold text-uppercase tracking-wide" for="date_from">Date From</label>
                            <input type="date" name="date_from" id="date_from" class="form-control form-control-lg bg-light border-0" value="<?php echo htmlspecialchars($date_from); ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label small text-secondary fw-bold text-uppercase tracking-wide" for="date_to">Date To</label>
                            <input type="date" name="date_to" id="date_to" class="form-control form-control-lg bg-light border-0" value="<?php echo htmlspecialchars($date_to); ?>" required>
                        </div>
                        
                        <?php if ($report_type !== 'summary'): ?>
                        <div class="col-md-3">
                            <label class="form-label small text-secondary fw-bold text-uppercase tracking-wide" for="status">Status</label>
                            <select name="status" id="status" class="form-select form-select-lg bg-light border-0">
                                <option value="">All Statuses</option>
                                <?php 
                                $status_options->data_seek(0); 
                                while ($status = $status_options->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($status['status']); ?>" 
                                        <?php echo $status_filter === $status['status'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status['status']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="col-md-3"></div>
                        <?php endif; ?>
                        
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 fw-bold">
                                <span class="material-symbols-outlined fs-5 me-2" style="vertical-align: -3px;">filter_list</span>
                                Filter
                            </button>
                            
                            <a href="report.php?report_type=<?php echo $report_type; ?>" class="btn btn-light btn-lg rounded-circle d-flex align-items-center justify-content-center border" style="width: 48px; height: 48px; background-color: #f8f9fa;" title="Reset Filters">
                                <span class="material-symbols-outlined text-secondary">restart_alt</span>
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Detailed Reports -->
                <?php if ($report_type !== 'summary'): ?>
                    <div class="card border-0 shadow-sm rounded-4" id="reportCard">

                        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold mb-1 text-main detailed-report-title"><?php echo htmlspecialchars($report_title); ?></h5>
                                <p class="text-muted small mb-0 caption">
                                    <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>
                                    <?php echo $status_filter ? " | Status: " . htmlspecialchars($status_filter) : ''; ?>
                                </p>
                            </div>
                            <button type="button" id="exportPdfBtn" class="btn btn-outline-primary rounded-pill btn-sm d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined fs-6">picture_as_pdf</span>
                                export PDF
                            </button>
                        </div>
                        
                        <div class="table-container border-0 shadow-none mb-0">
                            <table class="data-table detailed-report-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($report_columns as $index => $column): ?>
                                            <th class="py-4 text-secondary text-uppercase small fw-bold tracking-wide <?php echo $index === 0 ? 'ps-4' : ''; ?> <?php echo $index === count($report_columns)-1 ? 'text-end pe-4' : ''; ?>"><?php echo htmlspecialchars($column); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($detailed_report && $detailed_report->num_rows > 0): ?>
                                        <?php while ($row = $detailed_report->fetch_assoc()): ?>
                                            <tr>
                                                <?php 
                                                // Prepare user-friendly keys for modal labels
                                                $modal_data = [];
                                                foreach ($row as $k => $v) {
                                                    // filter out IDs from display if cleaner, but keep for now.
                                                    // Format key for label: 'payment_amount' -> 'Payment Amount'
                                                    $label = ucwords(str_replace('_', ' ', $k));
                                                    
                                                    // Format values for modal
                                                    $formatted_v = $v;
                                                    if (stripos($k, 'amount') !== false || stripos($k, 'balance') !== false || stripos($k, 'paid') !== false || stripos($k, 'proceeds') !== false) {
                                                        $formatted_v = '₱' . number_format((float)$v, 2);
                                                    } elseif (stripos($k, 'date') !== false && !empty($v)) {
                                                        $formatted_v = date('M d, Y', strtotime($v));
                                                    }
                                                    
                                                    $modal_data[$label] = $formatted_v;
                                                }
                                                $json_details = htmlspecialchars(json_encode($modal_data), ENT_QUOTES, 'UTF-8');
                                                ?>
                                                
                                                <?php 
                                                // iterate over defined report keys for the table columns
                                                foreach ($report_keys as $index => $key): 
                                                    $value = $row[$key] ?? ''; 
                                                    $cellClass = ($index === 0) ? 'ps-4 fw-semibold text-dark' : 'text-secondary';
                                                ?>
                                                    <td class="align-middle py-4 <?php echo $cellClass; ?>">
                                                        <?php 
                                                        if (stripos($key, 'status') !== false) {
                                                            $badgeClass = 'bg-secondary bg-opacity-10 text-secondary';
                                                            if (stripos($value, 'active') !== false || stripos($value, 'posted') !== false || stripos($value, 'approved') !== false || stripos($value, 'fully paid') !== false || stripos($value, 'completed') !== false) $badgeClass = 'bg-success bg-opacity-10 text-success';
                                                            elseif (stripos($value, 'pending') !== false || stripos($value, 'submitted') !== false || stripos($value, 'review') !== false) $badgeClass = 'bg-warning bg-opacity-10 text-warning';
                                                            elseif (stripos($value, 'overdue') !== false || stripos($value, 'rejected') !== false || stripos($value, 'cancelled') !== false) $badgeClass = 'bg-danger bg-opacity-10 text-danger';
                                                            
                                                            echo '<span class="badge ' . $badgeClass . ' rounded-pill fw-bold px-3 py-2">' . htmlspecialchars($value) . '</span>';
                                                        } elseif (stripos($key, 'amount') !== false || stripos($key, 'balance') !== false || stripos($key, 'paid') !== false || stripos($key, 'proceeds') !== false) {
                                                            echo '<span class="fw-bold text-dark">₱' . number_format((float)$value, 2) . '</span>';
                                                        } elseif (stripos($key, 'date') !== false && !empty($value)) {
                                                            echo date('M d, Y', strtotime($value));
                                                        } elseif ($key === 'loan_term_months') {
                                                            echo htmlspecialchars($value) . ' Months';
                                                        } else {
                                                            echo htmlspecialchars($value);
                                                        }
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <!-- Action Column with View Button -->
                                                <td class="text-end pe-4 align-middle">
                                                    <button type="button" class="btn-action-view view-details-btn" 
                                                            data-details="<?php echo $json_details; ?>">
                                                        View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo count($report_columns); ?>" class="table-empty py-5">
                                                <div class="d-flex flex-column align-items-center py-5">
                                                    <div class="bg-light rounded-circle p-4 mb-3">
                                                        <span class="material-symbols-outlined fs-1 text-secondary opacity-50">search_off</span>
                                                    </div>
                                                    <h6 class="text-secondary fw-bold mb-1">No Data Found</h6>
                                                    <p class="text-muted small mb-0">Try adjusting your filters to see results.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Summary Dashboard View -->
                    <div class="mb-4">
                        <h5 class="fw-bold mb-3 text-main">Performance Summary</h5>
                        <p class="text-muted small">Period: <span class="fw-semibold text-dark"><?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></span></p>
                    </div>

                    <div class="row g-4">
                        <!-- Total Collections -->
                        <div class="col-sm-6 col-xl-3">
                            <div class="stat-card-modern card-blue h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                        <span class="material-symbols-outlined text-white">payments</span>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 display-6">₱<?php echo number_format($total_collections, 2); ?></h3>
                                <p class="text-white opacity-75 small mb-0 fw-medium">Total Collections</p>
                            </div>
                        </div>

                        <!-- Loans Disbursed -->
                        <div class="col-sm-6 col-xl-3">
                            <div class="stat-card-modern card-green h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                        <span class="material-symbols-outlined text-white">credit_score</span>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 display-6">₱<?php echo number_format($total_loans_amount, 2); ?></h3>
                                <p class="text-white opacity-75 small mb-0 fw-medium"><?php echo $total_loans_count; ?> Loans Disbursed</p>
                            </div>
                        </div>

                        <!-- Applications -->
                        <div class="col-sm-6 col-xl-3">
                            <div class="stat-card-modern card-orange h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                        <span class="material-symbols-outlined text-white">description</span>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 display-6"><?php echo number_format($total_apps); ?></h3>
                                <p class="text-white opacity-75 small mb-0 fw-medium">Applications Received</p>
                            </div>
                        </div>

                        <!-- New Clients -->
                        <div class="col-sm-6 col-xl-3">
                            <div class="stat-card-modern card-blue h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                        <span class="material-symbols-outlined text-white">group_add</span>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 display-6"><?php echo number_format($new_clients); ?></h3>
                                <p class="text-white opacity-75 small mb-0 fw-medium">New Clients Registered</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            </div> <!-- End padding wrapper -->
        </main>
    </div>
    
    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-white border-bottom-0 p-4 pb-0">
                    <div>
                        <h4 class="modal-title fw-bolder text-dark mb-1">Transaction Details</h4>
                        <p class="text-secondary small mb-0">Full record information</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-3">
                    <div class="details-container">
                        <!-- Content populated by JS -->
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-0 bg-white">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Close</button>
                    <!-- Optional Print button can remain if needed, or remove for cleaner look -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check for theme in localStorage and apply
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);

        // View Details Modal Logic
        document.addEventListener('DOMContentLoaded', function() {
            const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
            const container = document.querySelector('.details-container');

            document.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const data = JSON.parse(this.dataset.details);
                    let html = '<div class="row g-3">';
                    
                    for (const [key, value] of Object.entries(data)) {
                        // Skip if value is null or empty
                        if (value === null || value === '') continue;
                        
                        html += `
                            <div class="col-sm-6">
                                <div class="p-3 bg-light bg-opacity-50 rounded-3 border-0 h-100">
                                    <h6 class="text-secondary text-uppercase small fw-bold tracking-wide mb-1 opacity-75 mr-2" style="font-size: 0.7rem;">${key}</h6>
                                    <p class="mb-0 fw-bold text-dark fs-6 text-break">${value}</p>
                                </div>
                            </div>
                        `;
                    }
                    html += '</div>';
                    
                    container.innerHTML = html;
                    detailsModal.show();
                });
            });

            // PDF Export Logic (Enhanced)
            const exportBtn = document.getElementById('exportPdfBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    const tableContainer = document.querySelector('.table-container');
                    const titleText = document.querySelector('.detailed-report-title').innerText;
                    const subtitleText = document.querySelector('.caption').innerText;
                    
                    // visual feedback
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Generating Preview...';
                    this.disabled = true;

                    // Create print container
                    const wrapper = document.createElement('div');
                    wrapper.style.fontFamily = "'Helvetica Neue', Helvetica, Arial, sans-serif";
                    wrapper.style.color = "#333";
                    wrapper.style.backgroundColor = "#fff";
                    
                    // 1. Professional Header
                    const header = document.createElement('div');
                    header.innerHTML = `
                        <div style="padding: 30px; background: #EF4444; color: white;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="display: flex; flex-direction: column; line-height: 1.1;">
                                        <span style="font-weight: 800; font-size: 28px; color: white; letter-spacing: -1px; text-transform: lowercase;">fundline</span>
                                        <span style="font-weight: 600; font-size: 8px; color: rgba(255,255,255,0.8); letter-spacing: 3px; text-transform: uppercase; margin-left: 2px;">Finance Corporation</span>
                                    </div>
                                    <div style="margin-top: 15px; font-size: 10px; color: rgba(255,255,255,0.9);">
                                        <p style="margin: 0;">123 Financial District, Business City</p>
                                        <p style="margin: 2px 0;">support@fundline.com | (02) 8123-4567</p>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <h1 style="margin: 0 0 5px 0; font-size: 24px; color: white; font-weight: 700;">${titleText}</h1>
                                    <div style="background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 4px; display: inline-block;">
                                        <p style="margin: 0; font-size: 11px; color: white; font-weight: 600;">${subtitleText}</p>
                                    </div>
                                    <p style="margin: 8px 0 0; font-size: 10px; color: rgba(255,255,255,0.8);">Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                                </div>
                            </div>
                        </div>
                    `;
                    wrapper.appendChild(header);
                    
                    // 2. Clone Table & Enhance Styling
                    const contentPadding = document.createElement('div');
                    contentPadding.style.padding = '30px';
                    
                    const clonedTable = tableContainer.cloneNode(true);
                    const table = clonedTable.querySelector('table');
                    
                    if(table) {
                        table.style.width = '100%';
                        table.style.borderCollapse = 'collapse';
                        table.style.marginBottom = '20px';
                        
                        // Remove action columns
                        const ths = table.querySelectorAll('th');
                        const trs = table.querySelectorAll('tbody tr');
                        
                        const actionIndex = ths.length - 1; // Assuming last column is Action
                        if (ths.length > 0) ths[actionIndex].style.display = 'none';
                        
                        // Header Styling
                        table.querySelectorAll('th').forEach((th, index) => {
                            if (index !== actionIndex) {
                                th.style.backgroundColor = '#1e293b';
                                th.style.color = '#ffffff';
                                th.style.padding = '12px 10px';
                                th.style.fontSize = '10px';
                                th.style.textTransform = 'uppercase';
                                th.style.fontWeight = '600';
                                th.style.letterSpacing = '0.5px';
                                th.style.border = 'none';
                                if(index === 0) th.style.borderTopLeftRadius = '4px';
                            }
                        });

                        // Content Styling with Zebra Striping
                        trs.forEach((tr, rowIndex) => {
                            const tds = tr.querySelectorAll('td');
                            if (tds.length > 0) tds[actionIndex].style.display = 'none';
                            
                            // Zebra striping
                            if (rowIndex % 2 !== 0) {
                                tr.style.backgroundColor = '#f8fafc';
                            } else {
                                tr.style.backgroundColor = '#ffffff';
                            }
                            
                            tds.forEach((td, colIndex) => {
                                if (colIndex !== actionIndex) {
                                    td.style.padding = '10px';
                                    td.style.fontSize = '10px';
                                    td.style.borderBottom = '1px solid #e2e8f0';
                                    td.style.color = '#334155';
                                    
                                    // Heuristic: Right align amounts (usually contain '₱' or numbers)
                                    const text = td.innerText.trim();
                                    if (text.includes('₱') || /^\d{1,3}(,\d{3})*(\.\d+)?$/.test(text)) {
                                        td.style.textAlign = 'right';
                                        td.style.fontFamily = "'Courier New', monospace"; // Monospace for alignment
                                        td.style.fontWeight = '600';
                                    }
                                }
                            });
                        });
                    }
                    contentPadding.appendChild(clonedTable);
                    
                     // Add Summary/Disclaimer if needed
                    const disclaimer = document.createElement('div');
                    disclaimer.style.marginTop = '20px';
                    disclaimer.style.padding = '15px';
                    disclaimer.style.backgroundColor = '#fef2f2';
                    disclaimer.style.borderLeft = '3px solid #EF4444';
                    disclaimer.style.fontSize = '9px';
                    disclaimer.style.color = '#7f1d1d';
                    disclaimer.innerHTML = '<strong>CONFIDENTIALITY NOTICE:</strong> This report contains proprietary and confidential information of Fundline Finance Corporation. Unauthorized distribution or disclosure is strictly prohibited.';
                    contentPadding.appendChild(disclaimer);
                    
                    wrapper.appendChild(contentPadding);
                    
                    // 3. Footer
                    const footer = document.createElement('div');
                    footer.style.padding = '15px 30px';
                    footer.style.borderTop = '1px solid #e2e8f0';
                    footer.style.display = 'flex';
                    footer.style.justifyContent = 'space-between';
                    footer.style.alignItems = 'center';
                    footer.innerHTML = `
                        <span style="font-size: 9px; color: #94a3b8;">System-Generated Report</span>
                        <span style="font-size: 9px; color: #94a3b8;">Page 1 of 1</span>
                        <span style="font-size: 9px; color: #94a3b8;">© 2026 Fundline Micro Financing</span>
                    `;
                    wrapper.appendChild(footer);

                    // PDF Options
                    const date = new Date().toISOString().split('T')[0];
                    // Sanitize title: remove non-alphanumeric chars except spaces, then replace spaces with underscores
                    const cleanTitle = titleText.trim().replace(/[^a-zA-Z0-9\s]/g, '').replace(/\s+/g, '_');
                    const filename = `Fundline_${cleanTitle}_${date}.pdf`;
                    
                    const opt = {
                        margin:       0.2,
                        filename:     filename,
                        image:        { type: 'jpeg', quality: 1 },
                        html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
                        jsPDF:        { unit: 'in', format: 'legal', orientation: 'landscape' }
                    };
                    
                    // Generate Blob URL for preview with Metadata
                    html2pdf().set(opt).from(wrapper).toPdf().get('pdf').then(function(pdf) {
                        // Set PDF Metadata
                        pdf.setProperties({
                            title: filename,
                            subject: subtitleText,
                            author: 'Fundline Finance Corporation',
                            keywords: 'report, fundline, finance',
                            creator: 'Fundline System'
                        });
                        
                        const blob = pdf.output('blob');
                        const url = URL.createObjectURL(blob);
                        
                        // Open and set title
                        const win = window.open(url, '_blank');
                        if (win) {
                            // Attempt to set tab title (browser dependent)
                            win.onload = function() {
                                win.document.title = filename;
                            };
                        }
                        
                        exportBtn.innerHTML = originalContent;
                        exportBtn.disabled = false;
                    }).catch(err => {
                        console.error('PDF Error:', err);
                        alert('Error generating preview');
                        exportBtn.innerHTML = originalContent;
                        exportBtn.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html>

