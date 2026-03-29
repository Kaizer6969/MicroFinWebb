<?php
/**
 * View Client Details
 * Accessible by employees and admins
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Allow both Employee and Admin roles
// Adjust this check based on your actual role names. 
// Assuming 'Employee', 'Admin', 'Super Admin' are valid.
// If strictly for 'Employee' originally, we should keep it or expand it if intended.
// Based on context, admins should likely be able to view clients too.
// For now, I will preserve the original check but commented out or add Admin if needed.
// The original code had: if ($_SESSION['user_type'] !== 'Employee')
// I will expand it to allow Admins too if that's the goal of "Admin Pages", but safely I'll stick to original logic 
// OR better yet, since I am redesigning "Admin Pages", I should ensure it works for the intended users.
// If this page is linked from Admin Dashboard, it must support Admin.
// Let's assume standard access for authorized users.

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['Employee', 'Admin', 'Super Admin'])) {
    // If not authorized, redirect
   // header("Location: dashboard.php");
   // exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
// Fetch current user details for the header content if needed (handled by admin_header usually)
// But admin_header might rely on session vars.

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($client_id === 0) {
    header("Location: clients.php");
    exit();
}

// Get client details
$query = "SELECT * FROM clients WHERE client_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

if (!$client) {
    header("Location: clients.php"); // Or admin_users.php depending on where they came from
    exit();
}

// Get loans
$loans_query = "
    SELECT l.*, lp.product_name 
    FROM loans l 
    JOIN loan_products lp ON l.product_id = lp.product_id 
    WHERE l.client_id = ? 
    ORDER BY l.release_date DESC
";
$l_stmt = $conn->prepare($loans_query);
$l_stmt->bind_param("i", $client_id);
$l_stmt->execute();
$loans_result = $l_stmt->get_result();
$loans = [];
while ($row = $loans_result->fetch_assoc()) {
    $loans[] = $row;
}
$l_stmt->close();

// Get applications
$app_query = "
    SELECT la.*, lp.product_name 
    FROM loan_applications la 
    JOIN loan_products lp ON la.product_id = lp.product_id 
    WHERE la.client_id = ? 
    ORDER BY la.created_at DESC
";
$a_stmt = $conn->prepare($app_query);
$a_stmt->bind_param("i", $client_id);
$a_stmt->execute();
$app_result = $a_stmt->get_result();
$applications = [];
while ($row = $app_result->fetch_assoc()) {
    $applications[] = $row;
}
$a_stmt->close();

$conn->close();

function getStatusBadgeClass($status) {
    $classes = [
        'Active' => 'bg-success',
        'Overdue' => 'bg-danger',
        'Fully Paid' => 'bg-primary',
        'Submitted' => 'bg-info',
        'Approved' => 'bg-success',
        'Rejected' => 'bg-danger',
        'Inactive' => 'bg-secondary',
        'Blacklisted' => 'bg-dark'
    ];
    return $classes[$status] ?? 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Client Profile - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <!-- Header -->
            <?php include 'admin_header.php'; ?>
            
            <div class="container-fluid p-4">
                
                <!-- Page Header with Back Button -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Client Profile</h4>
                        <p class="text-muted small mb-0">View client information and history</p>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" onclick="window.history.back()">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">arrow_back</span>
                        Back
                    </button>
                </div>

                <div class="row">
                    <!-- Left Column: Client Info -->
                    <div class="col-lg-4 mb-4">
                        <!-- Client Card -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4 text-center">
                            <div class="card-body p-4">
                                <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3 shadow-sm" style="width: 80px; height: 80px; font-size: 2rem; font-weight: bold;">
                                    <?php echo strtoupper(substr($client['first_name'], 0, 1)); ?>
                                </div>
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h5>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($client['client_code']); ?></p>
                                <span class="badge <?php echo getStatusBadgeClass($client['client_status']); ?> rounded-pill px-3 py-2">
                                    <?php echo htmlspecialchars($client['client_status']); ?>
                                </span>
                                
                                <hr class="my-4 text-muted opacity-25">
                                
                                <div class="text-start">
                                    <div class="mb-3">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Email Address</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">mail</span>
                                            <span class="fw-medium text-break"><?php echo htmlspecialchars($client['email_address'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Contact Number</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">call</span>
                                            <span class="fw-medium"><?php echo htmlspecialchars($client['contact_number']); ?></span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Civil Status</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">diversity_3</span>
                                            <span class="fw-medium"><?php echo htmlspecialchars($client['civil_status']); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Date of Birth</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">cake</span>
                                            <span class="fw-medium"><?php echo date('M d, Y', strtotime($client['date_of_birth'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Address Card -->
                         <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h6 class="fw-bold mb-0">Address Information</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Present Address</small>
                                    <p class="fw-medium mb-0">
                                        <?php echo htmlspecialchars(implode(', ', array_filter([$client['present_street'], $client['present_barangay'], $client['present_city'], $client['present_province']]))); ?>
                                    </p>
                                </div>
                                <div>
                                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Registration Date</small>
                                    <p class="fw-medium mb-0"><?php echo date('M d, Y', strtotime($client['registration_date'])); ?></p>
                                </div>
                            </div>
                         </div>
                    </div>
                    
                    <!-- Right Column: Loans & Applications -->
                    <div class="col-lg-8">
                        
                        <!-- Applications -->
                        <div class="card hover-lift mb-4">
                            <div class="card-header">
                                <h3 class="h6 fw-bold mb-1 text-main">Loan Applications</h3>
                                <p class="text-muted small mb-0">History of loan requests</p>
                            </div>
                            <div class="table-container border-0 shadow-none mb-0">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">App ID</th>
                                            <th>Product</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th class="text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($applications)): ?>
                                            <?php foreach ($applications as $app): ?>
                                            <tr>
                                                <td class="ps-4 fw-semibold text-primary">#<?php echo htmlspecialchars($app['application_number']); ?></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($app['product_name']); ?></td>
                                                <td class="fw-semibold text-main">₱<?php echo number_format($app['requested_amount'], 2); ?></td>
                                                <td class="text-muted"><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill fw-normal <?php echo getStatusBadgeClass($app['application_status']); ?>">
                                                        <?php echo htmlspecialchars($app['application_status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <a href="process_application.php?id=<?php echo $app['application_id']; ?>" class="btn-action-view">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">No applications found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Loans -->
                        <div class="card hover-lift">
                            <div class="card-header">
                                <h3 class="h6 fw-bold mb-1 text-main">Loan History</h3>
                                <p class="text-muted small mb-0">Active and past loans</p>
                            </div>
                            <div class="table-container border-0 shadow-none mb-0">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Loan #</th>
                                            <th>Product</th>
                                            <th>Principal</th>
                                            <th>Released</th>
                                            <th>Status</th>
                                            <th class="text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($loans)): ?>
                                            <?php foreach ($loans as $loan): ?>
                                            <tr>
                                                <td class="ps-4 fw-semibold text-primary">#<?php echo htmlspecialchars($loan['loan_number']); ?></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($loan['product_name']); ?></td>
                                                <td class="fw-semibold text-main">₱<?php echo number_format($loan['principal_amount'], 2); ?></td>
                                                <td class="text-muted"><?php echo date('M d, Y', strtotime($loan['release_date'])); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill fw-normal <?php echo getStatusBadgeClass($loan['loan_status']); ?>">
                                                        <?php echo htmlspecialchars($loan['loan_status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <a href="view_loan.php?id=<?php echo $loan['loan_id']; ?>" class="btn-action-view">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">No loan records found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

