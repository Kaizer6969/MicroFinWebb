<?php
/**
 * Loan Disbursement Page - Fundline Web Application
 * For disbursing approved loans
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Employee') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_name = $_SESSION['role_name'] ?? 'Employee';
$avatar_letter = strtoupper(substr($username, 0, 1));

// Get employee_id - with better error handling
$stmt = $conn->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$emp_result = $stmt->get_result();

if ($emp_result->num_rows === 0) {
    // Create employee record if it doesn't exist
    $insert_stmt = $conn->prepare("
        INSERT INTO employees (
            user_id, employee_code, first_name, last_name, department, 
            position, contact_number, hire_date, employment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $employee_code = 'EMP' . date('Y') . '-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
    
    // Extract name from username
    $username_parts = explode('.', $username);
    $first_name = !empty($username_parts[0]) ? ucfirst($username_parts[0]) : 'System';
    $last_name = !empty($username_parts[1]) ? ucfirst($username_parts[1]) : 'User';
    
    // Determine department based on role
    $department = 'Admin';
    if (stripos($role_name, 'loan') !== false) {
        $department = 'Loan Processing';
    } elseif (stripos($role_name, 'collect') !== false) {
        $department = 'Collections';
    }
    
    $position = $role_name;
    $contact_number = 'N/A';
    $hire_date = date('Y-m-d');
    $employment_status = 'Active';
    
    $insert_stmt->bind_param(
        "issssssss",
        $user_id,
        $employee_code,
        $first_name,
        $last_name,
        $department,
        $position,
        $contact_number,
        $hire_date,
        $employment_status
    );
    
    if ($insert_stmt->execute()) {
        $employee_id = $insert_stmt->insert_id;
        $insert_stmt->close();
    } else {
        $insert_stmt->close();
        die("Employee record not found and could not be created. Please contact administrator. Error: " . $conn->error);
    }
} else {
    $employee = $emp_result->fetch_assoc();
    $employee_id = $employee['employee_id'];
}

$stmt->close();

// Get approved applications ready for disbursement
$query = "
    SELECT la.*, 
           lp.product_name, lp.product_type, lp.processing_fee_percentage,
           lp.service_charge, lp.documentary_stamp, lp.insurance_fee_percentage,
           c.first_name, c.last_name, c.client_code, c.contact_number
    FROM loan_applications la
    JOIN loan_products lp ON la.product_id = lp.product_id
    JOIN clients c ON la.client_id = c.client_id
    WHERE la.application_status = 'Approved'
    AND la.application_id NOT IN (SELECT application_id FROM loans WHERE application_id IS NOT NULL)
    ORDER BY la.approval_date ASC
";

$result = $conn->query($query);
$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}

// Handle disbursement form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['disburse_loan'])) {
    $application_id = intval($_POST['application_id']);
    $release_date = $_POST['release_date'];
    $disbursement_method = $_POST['disbursement_method'];
    $disbursement_reference = trim($_POST['disbursement_reference']);
    $notes = trim($_POST['notes']);
    
    // Get application details
    $stmt = $conn->prepare("
        SELECT la.*, lp.*, c.client_id
        FROM loan_applications la
        JOIN loan_products lp ON la.product_id = lp.product_id
        JOIN clients c ON la.client_id = c.client_id
        WHERE la.application_id = ?
    ");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $app_result = $stmt->get_result();
    $application = $app_result->fetch_assoc();
    $stmt->close();
    
    if (!$application) {
        $error_message = "Application not found!";
    } else {
        // Check if loan already exists for this application
        $check_stmt = $conn->prepare("SELECT loan_id FROM loans WHERE application_id = ?");
        $check_stmt->bind_param("i", $application_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error_message = "Error: A loan has already been disbursed for this application.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            // Calculate loan details
        $principal_amount = $application['approved_amount'] ?? $application['requested_amount'];
        $processing_fee = ($application['processing_fee_percentage'] / 100) * $principal_amount;
        $service_charge = $application['service_charge'] ?? 0;
        $documentary_stamp = $application['documentary_stamp'] ?? 0;
        $insurance_fee = ($application['insurance_fee_percentage'] / 100) * $principal_amount;
        $other_charges = 0;
        
        $total_deductions = $processing_fee + $service_charge + $documentary_stamp + $insurance_fee + $other_charges;
        $net_proceeds = $principal_amount - $total_deductions;
        
        // Calculate monthly amortization (add-on/flat interest)
        $monthly_interest = ($application['interest_rate'] / 100) * $principal_amount;
        $monthly_amortization = $principal_amount / $application['loan_term_months'] + $monthly_interest;
        
        // Generate loan number
        $loan_number = 'LN' . date('Y') . '-' . str_pad($application['client_id'], 6, '0', STR_PAD_LEFT) . '-' . time();
        
        // Calculate dates
        $first_payment_date = date('Y-m-d', strtotime($release_date . ' +1 month'));
        $maturity_date = date('Y-m-d', strtotime($release_date . ' +' . $application['loan_term_months'] . ' months'));
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into loans table
            $stmt = $conn->prepare("
                INSERT INTO loans (
                    loan_number, application_id, client_id, product_id,
                    principal_amount, interest_amount, total_loan_amount,
                    processing_fee, service_charge, documentary_stamp, insurance_fee,
                    other_charges, total_deductions, net_proceeds,
                    interest_rate, loan_term_months, monthly_amortization,
                    number_of_payments, release_date, first_payment_date,
                    maturity_date, released_by, disbursement_method,
                    disbursement_reference, remaining_balance, outstanding_principal,
                    outstanding_interest, next_payment_due, notes
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $total_loan_amount = $principal_amount + ($monthly_interest * $application['loan_term_months']);
            $interest_amount = $monthly_interest * $application['loan_term_months'];
            
            $stmt->bind_param(
                "siiidddddddddddidisssissdddss",
                $loan_number, 
                $application_id, 
                $application['client_id'], 
                $application['product_id'],
                $principal_amount, 
                $interest_amount, 
                $total_loan_amount,
                $processing_fee, 
                $service_charge, 
                $documentary_stamp, 
                $insurance_fee,
                $other_charges, 
                $total_deductions, 
                $net_proceeds,
                $application['interest_rate'], 
                $application['loan_term_months'], 
                $monthly_amortization,
                $application['loan_term_months'], 
                $release_date, 
                $first_payment_date,
                $maturity_date, 
                $employee_id, 
                $disbursement_method,
                $disbursement_reference, 
                $total_loan_amount, 
                $principal_amount,
                $interest_amount, 
                $first_payment_date, 
                $notes
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create loan record: " . $stmt->error);
            }
            
            $loan_id = $stmt->insert_id;
            
            // Generate amortization schedule (flat/add-on interest)
            $balance = $total_loan_amount;
            
            for ($i = 1; $i <= $application['loan_term_months']; $i++) {
                $due_date = date('Y-m-d', strtotime($release_date . ' +' . $i . ' months'));
                $principal_payment = $principal_amount / $application['loan_term_months'];
                $interest_payment = $monthly_interest;
                $total_payment = $principal_payment + $interest_payment;
                
                $beginning_balance = $balance;
                $balance -= $principal_payment;  // Only principal reduces balance in flat interest
                $ending_balance = $balance;
                
                $schedule_stmt = $conn->prepare("
                    INSERT INTO amortization_schedule (
                        loan_id, payment_number, due_date, beginning_balance,
                        principal_amount, interest_amount, total_payment, ending_balance
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $schedule_stmt->bind_param(
                    "iisddddd",
                    $loan_id, $i, $due_date, $beginning_balance,
                    $principal_payment, $interest_payment, $total_payment, $ending_balance
                );
                $schedule_stmt->execute();
                $schedule_stmt->close();
            }
            
            // Create notification for client
            $notif_stmt = $conn->prepare("
                INSERT INTO notifications (user_id, notification_type, title, message, link)
                SELECT u.user_id, 'Loan Approved', 'Loan Disbursed', 
                       CONCAT('Your loan (', ?, ') has been disbursed. Net Proceeds: ₱', FORMAT(?, 2)), 
                       'my_loans.php'
                FROM clients c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.client_id = ?
            ");
            $notif_stmt->bind_param("sdi", $loan_number, $net_proceeds, $application['client_id']);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Log audit
            $audit_stmt = $conn->prepare("
                INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, description, ip_address)
                VALUES (?, 'CREATE', 'LOAN', ?, 'Disbursed loan to client', ?)
            ");
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $audit_stmt->bind_param("iis", $user_id, $loan_id, $ip_address);
            $audit_stmt->execute();
            $audit_stmt->close();
            
            $conn->commit();
            $success_message = "Loan disbursed successfully! Loan Number: " . $loan_number . " (Net Proceeds: ₱" . number_format($net_proceeds, 2) . ")";
            
            // Refresh page after 3 seconds
            echo "<script>setTimeout(function(){ window.location.href = 'disbursement.php'; }, 3000);</script>";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Disbursement failed: " . $e->getMessage();
        }
        
        // Clean up statement
        if (isset($stmt)) {
            $stmt->close();
        }
    }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Loan Disbursement - Fundline</title>
    
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
                
                <!-- Title section removed -->
                
                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined filled">check_circle</span>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined filled">error</span>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Pending Disbursements Table -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-main">Pending Disbursements</h5>
                            <p class="text-muted small mb-0">Approved loans ready for release</p>
                        </div>
                    </div>
                    
                    <div class="table-container border-0 shadow-none mb-0">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">Application</th>
                                    <th>Client</th>
                                    <th>Product</th>
                                    <th>Amount</th>
                                    <th>Term</th>
                                    <th>Date Approved</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="7" class="table-empty">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="material-symbols-outlined fs-1 opacity-25 mb-2">check_circle</span>
                                            <p class="mb-0">No pending disbursements found</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-semibold text-main"><?php echo htmlspecialchars($app['application_number']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($app['client_code']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 small">
                                                <?php echo htmlspecialchars($app['product_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">₱<?php echo number_format($app['approved_amount'] ?? $app['requested_amount'], 2); ?></span>
                                        </td>
                                        <td class="text-muted small"><?php echo $app['loan_term_months']; ?> Months</td>
                                        <td class="text-muted small">
                                            <?php echo $app['approval_date'] ? date('M d, Y', strtotime($app['approval_date'])) : 'N/A'; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button class="action-button border-0 bg-transparent text-primary" onclick="showDisbursementForm(<?php echo $app['application_id']; ?>)">
                                                <span class="material-symbols-outlined fs-6">payments</span>
                                                Disburse
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Disbursement Modal -->
    <div class="modal fade" id="disbursementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Confirm Disbursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" action="" id="disbursementForm">
                        <input type="hidden" name="disburse_loan" value="1">
                        <input type="hidden" id="selected_application_id" name="application_id">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Release Date *</label>
                            <input type="date" class="form-control" name="release_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Disbursement Method *</label>
                            <select class="form-select" name="disbursement_method" required>
                                <option value="">Select Method</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="GCash">GCash</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Reference Number</label>
                            <input type="text" class="form-control" name="disbursement_reference" placeholder="Check No., Reference No.">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional details..."></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="disburse_loan" class="btn btn-primary px-4">
                                <span class="material-symbols-outlined align-middle me-1">check_circle</span>
                                Confirm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const disbursementModal = new bootstrap.Modal(document.getElementById('disbursementModal'));
        
        function showDisbursementForm(applicationId) {
            document.getElementById('selected_application_id').value = applicationId;
            disbursementModal.show();
        }
        
        function hideDisbursementForm() {
            disbursementModal.hide();
        }

        // Prevent double submission
        const disburseForm = document.getElementById('disbursementForm');
        if (disburseForm) {
            disburseForm.addEventListener('submit', function(e) {
                const btn = this.querySelector('button[name="disburse_loan"]');
                if (btn) {
                    if (btn.disabled) {
                        e.preventDefault();
                        return;
                    }
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
                }
            });
        }
    </script>
</body>
</html>
