<?php
require_once '../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_type = $_SESSION['user_type'];
$role_name = $_SESSION['role_name'] ?? 'User';

$loan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($loan_id === 0) {
    header("Location: " . ($user_type === 'Employee' || $user_type === 'Admin' || $user_type === 'Super Admin' ? 'admin_loans.php' : 'my_loans.php'));
    exit();
}

require_once 'loan_helper.php';
// Check and apply penalty if needed
checkAndApply6MonthPenalty($conn, $loan_id);

// Get loan details with access control
$query = "
    SELECT l.*, p.product_name, p.interest_rate, p.product_type,
           c.first_name, c.last_name, c.client_code, c.contact_number, 
           c.present_city, c.present_province,
           u.email,
           la.application_id, la.application_number
    FROM loans l
    JOIN loan_products p ON l.product_id = p.product_id
    JOIN clients c ON l.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    LEFT JOIN loan_applications la ON l.application_id = la.application_id
    WHERE l.loan_id = ?
";

// Add access control for clients
if ($user_type === 'Client') {
    $query .= " AND c.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $loan_id, $user_id);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $loan_id);
}

$stmt->execute();
$result = $stmt->get_result();
$loan = $result->fetch_assoc();
$stmt->close();

if (!$loan) {
    header("Location: " . ($user_type === 'Client' ? 'my_loans.php' : 'admin_loans.php'));
    exit();
}

// Get payment history
$pay_stmt = $conn->prepare("
    SELECT * FROM payments 
    WHERE loan_id = ? 
    ORDER BY payment_date DESC
");
$pay_stmt->bind_param("i", $loan_id);
$pay_stmt->execute();
$pay_result = $pay_stmt->get_result();
$payments = [];
while ($row = $pay_result->fetch_assoc()) {
    $payments[] = $row;
}
$pay_stmt->close();

// Get documents (linked to application)
// If no application_id linked directly to loan, try to find by loan->application_id
$documents = [];
if (!empty($loan['application_id'])) {
    $docs_stmt = $conn->prepare("
        SELECT ad.*, dt.document_name, dt.description
        FROM application_documents ad
        JOIN document_types dt ON ad.document_type_id = dt.document_type_id
        WHERE ad.application_id = ?
    ");
    $docs_stmt->bind_param("i", $loan['application_id']);
    $docs_stmt->execute();
    $docs_result = $docs_stmt->get_result();
    while ($doc = $docs_result->fetch_assoc()) {
        $documents[] = $doc;
    }
    $docs_stmt->close();
}

// $conn->close(); // Removed to allow header to access DB

function getStatusBadgeClass($status) {
    $classes = [
        'Active' => 'bg-success',
        'Overdue' => 'bg-danger',
        'Fully Paid' => 'bg-primary',
        'Restructured' => 'bg-warning',
        'Written Off' => 'bg-secondary',
        'Cancelled' => 'bg-secondary'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

$is_client = ($user_type === 'Client');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Loan Details - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    <style>
        .sidebar {
            /* Ensure sidebar behaves correctly if main_style is slightly different */
        }
    </style>
</head>
<body class="bg-light">

    <div class="d-flex">
        <!-- Sidebar -->
        <?php 
        if ($is_client) {
            include 'user_sidebar.php'; 
        } else {
            include 'admin_sidebar.php'; 
        }
        ?>
        
        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <!-- Header -->
            <?php 
            if ($is_client) {
                include 'client_header.php'; 
            } else {
                include 'admin_header.php'; 
            }
            ?>
            
            <div class="container-fluid p-4">
                
                <!-- Page Header with Back Button -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Loan #<?php echo htmlspecialchars($loan['loan_number']); ?></h4>
                        <p class="text-muted small mb-0">Product: <?php echo htmlspecialchars($loan['product_name']); ?></p>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" onclick="window.history.back()">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">arrow_back</span>
                        Back
                    </button>
                </div>

                <div class="row g-4">
                    <!-- Left Column: Loan Info & Financials -->
                    <div class="col-lg-8">
                        <!-- Loan Overview Card -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="fw-bold mb-1">Overview</h5>
                                    <p class="text-muted small mb-0">Loan status and details</p>
                                </div>
                                <span class="badge <?php echo getStatusBadgeClass($loan['loan_status']); ?> rounded-pill px-3 py-2">
                                    <?php echo htmlspecialchars($loan['loan_status']); ?>
                                </span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <div class="col-sm-6 col-md-4">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Released Date</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">calendar_today</span>
                                            <span class="fw-medium"><?php echo date('M d, Y', strtotime($loan['release_date'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Maturity Date</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">event</span>
                                            <span class="fw-medium"><?php echo date('M d, Y', strtotime($loan['maturity_date'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Application #</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">description</span>
                                            <span class="fw-medium"><?php echo htmlspecialchars($loan['application_number'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <hr class="text-muted opacity-10 my-4">
                                <div class="row g-4">
                                    <div class="col-sm-6 col-md-4">
                                        <div class="p-3 bg-light rounded-3">
                                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Principal Amount</small>
                                            <p class="h5 fw-bold text-primary mb-0">₱<?php echo number_format($loan['principal_amount'], 2); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <div class="p-3 bg-light rounded-3">
                                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Total Loan Amount</small>
                                            <p class="h5 fw-bold text-primary mb-0">₱<?php echo number_format($loan['total_loan_amount'], 2); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <div class="p-3 bg-danger bg-opacity-10 rounded-3">
                                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Remaining Balance</small>
                                            <p class="h5 fw-bold text-danger mb-0">₱<?php echo number_format($loan['remaining_balance'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="row g-4">
                                        <div class="col-6">
                                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Interest Rate</small>
                                            <p class="fw-medium mb-0"><?php echo htmlspecialchars($loan['interest_rate']); ?>% / Month</p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Term</small>
                                            <p class="fw-medium mb-0"><?php echo htmlspecialchars($loan['loan_term_months']); ?> Months</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment History -->
                        <div class="card hover-lift">
                            <div class="card-header">
                                <h3 class="h6 fw-bold mb-1 text-main">Payment History</h3>
                                <p class="text-muted small mb-0">Record of payments made</p>
                            </div>
                            <div class="table-container border-0 shadow-none mb-0">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Receipt #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Penalty</th>
                                            <th class="pe-4">Total</th>
                                        </tr>
                                    </thead>
                                        <tbody>
                                            <?php if (!empty($payments)): ?>
                                                <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                <td class="ps-4 fw-semibold text-primary"><?php echo htmlspecialchars($payment['official_receipt_number'] ?: $payment['payment_reference']); ?></td>
                                                <td class="text-muted"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                                <td class="text-muted">₱<?php echo number_format($payment['payment_amount'], 2); ?></td>
                                                <td class="text-danger">+₱<?php echo number_format($payment['penalty_paid'], 2); ?></td>
                                                <td class="pe-4 fw-semibold text-main">₱<?php echo number_format($payment['payment_amount'] + $payment['penalty_paid'], 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-5 text-muted">No payments recorded yet</td>
                                                </tr>
                                            <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Client & Docs -->
                    <div class="col-lg-4">
                        <!-- Client Info (Only for Admin/Employee view, although client sees their own info too, but maybe redundant if they are logged in. 
                             Let's show it or hide if Client? 
                             Clients usually know who they are. Admins need to see client info.
                             Let's show it for both but contextually appropriate.) -->
                        <?php if (!$is_client): ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-1">Borrower</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; font-weight: bold; font-size: 1.2rem;">
                                        <?php echo strtoupper(substr($loan['first_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></h6>
                                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($loan['client_code']); ?></p>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Contact</small>
                                    <p class="fw-medium mb-0"><?php echo htmlspecialchars($loan['contact_number']); ?></p>
                                </div>
                                <div class="mb-2">
                                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Email</small>
                                    <p class="fw-medium mb-0 text-break"><?php echo htmlspecialchars($loan['email']); ?></p>
                                </div>
                                <div>
                                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Address</small>
                                    <p class="fw-medium mb-0"><?php echo htmlspecialchars($loan['present_city'] . ', ' . $loan['present_province']); ?></p>
                                </div>
                                <div class="mt-3">
                                    <a href="view_client.php?id=<?php echo $loan['client_id']; ?>" class="btn-action-view w-100 text-center">View Client Profile</a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Next Payment Due (Highlighted) -->
                         <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary text-white">
                            <div class="card-body p-4 text-center">
                                <p class="text-white-50 mb-1 small text-uppercase fw-bold">Next Payment Due</p>
                                <h3 class="fw-bold mb-2">
                                    <?php echo $loan['next_payment_due'] ? date('M d, Y', strtotime($loan['next_payment_due'])) : 'N/A'; ?>
                                </h3>
                                <p class="text-white-50 small mb-0">
                                    Monthly: ₱<?php echo number_format($loan['monthly_amortization'], 2); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Documents -->
                        <?php if (!empty($documents)): ?>
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-1">Documents</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="vstack gap-3">
                                    <?php foreach ($documents as $doc): ?>
                                    <div class="d-flex align-items-center justify-content-between p-3 border rounded-3 bg-light">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="material-symbols-outlined text-primary">description</span>
                                            <div>
                                                <h6 class="fw-bold mb-0 small"><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                                                <p class="text-muted small mb-0" style="font-size: 0.75rem;"><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></p>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn-action-view">
                                            View
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check for theme in localStorage if not handled by CSS variables
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        } else {
             document.documentElement.setAttribute('data-bs-theme', 'light');
        }
    </script>
</body>
</html>

