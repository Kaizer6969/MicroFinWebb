<?php
session_start();
require_once '../config/db.php';

require_once 'notification_helper.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_name = $_SESSION['role_name'];
$avatar_letter = strtoupper(substr($username, 0, 1));

// Get employee_id for the current user (required for actions)
$emp_stmt = $conn->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
$emp_stmt->bind_param("i", $user_id);
$emp_stmt->execute();
$emp_res = $emp_stmt->get_result();
$emp_data = $emp_res->fetch_assoc();
$employee_id = $emp_data['employee_id'];
$emp_stmt->close();

// Get application ID from URL
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch application details
$app_query = "SELECT la.*, c.user_id as client_user_id, c.first_name, c.middle_name, c.last_name, c.contact_number, c.email_address,
              lp.product_name, lp.product_type, lp.interest_rate, lp.min_term_months, lp.max_term_months,
              e.first_name as employee_fname, e.last_name as employee_lname
              FROM loan_applications la
              JOIN clients c ON la.client_id = c.client_id
              JOIN loan_products lp ON la.product_id = lp.product_id
              LEFT JOIN employees e ON la.reviewed_by = e.employee_id
              WHERE la.application_id = ?";
$stmt = $conn->prepare($app_query);
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();

if (!$application) {
    header("Location: admin_applications.php");
    exit();
}



// Fetch credit investigation
$ci_query = "SELECT ci.*, e.first_name, e.last_name 
             FROM credit_investigations ci
             JOIN employees e ON ci.conducted_by = e.employee_id
             WHERE ci.client_id = ?
             ORDER BY ci.investigation_date DESC LIMIT 1";
$ci_stmt = $conn->prepare($ci_query);
$ci_stmt->bind_param("i", $application['client_id']);
$ci_stmt->execute();
$ci_result = $ci_stmt->get_result();
$credit_investigation = $ci_result->fetch_assoc();

// Fetch credit score
$cs_query = "SELECT * FROM credit_scores WHERE client_id = ? ORDER BY computation_date DESC LIMIT 1";
$cs_stmt = $conn->prepare($cs_query);
$cs_stmt->bind_param("i", $application['client_id']);
$cs_stmt->execute();
$cs_result = $cs_stmt->get_result();
$credit_score = $cs_result->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $new_status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';
        
        $update_query = "UPDATE loan_applications SET application_status = ?, review_notes = ?, 
                        reviewed_by = ?, review_date = NOW() WHERE application_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssii", $new_status, $notes, $employee_id, $application_id);
        
        if ($update_stmt->execute()) {
             // Notify Client
            if ($new_status == 'Under Review' || $new_status == 'Document Verification') {
                 createNotification(
                    $conn, 
                    $application['client_user_id'], 
                    'System Alert', 
                    'Application Update', 
                    "Your loan application #{$application['application_number']} status updated to: $new_status.",
                    "view_application.php?id=$application_id"
                );
            }
            $_SESSION['success_message'] = "Application status updated successfully";
            header("Location: process_application.php?id=" . $application_id);
            exit();
        }
    }
    
    if ($action === 'approve') {
        $approved_amount = $_POST['approved_amount'];
        $approval_notes = $_POST['approval_notes'] ?? '';
        
        $approve_query = "UPDATE loan_applications SET application_status = 'Approved', 
                         approved_amount = ?, approval_notes = ?, approved_by = ?, approval_date = NOW()
                         WHERE application_id = ?";
        $approve_stmt = $conn->prepare($approve_query);
        $approve_stmt->bind_param("dsii", $approved_amount, $approval_notes, $employee_id, $application_id);
        
        if ($approve_stmt->execute()) {
            // Notify Client
            createNotification(
                $conn, 
                $application['client_user_id'], 
                'Loan Approved', 
                'Congratulations! Loan Approved', 
                "Your application #{$application['application_number']} for ₱" . number_format($approved_amount) . " has been approved!",
                "view_application.php?id=$application_id",
                'High'
            );
            
            $_SESSION['success_message'] = "Application approved successfully";
            header("Location: process_application.php?id=" . $application_id);
            exit();
        }
    }
    
    if ($action === 'reject') {
        $rejection_reason = $_POST['rejection_reason'];
        
        $reject_query = "UPDATE loan_applications SET application_status = 'Rejected', 
                        rejection_reason = ?, rejected_by = ?, rejection_date = NOW()
                        WHERE application_id = ?";
        $reject_stmt = $conn->prepare($reject_query);
        $reject_stmt->bind_param("sii", $rejection_reason, $employee_id, $application_id);
        
        if ($reject_stmt->execute()) {
             // Notify Client
             createNotification(
                $conn, 
                $application['client_user_id'], 
                'Loan Rejected', 
                'Application Update', 
                "Your application #{$application['application_number']} has been rejected. Reason: $rejection_reason",
                "view_application.php?id=$application_id",
                'High'
            );
            
            $_SESSION['success_message'] = "Application rejected";
            header("Location: process_application.php?id=" . $application_id);
            exit();
        }
    }
}

// Ensure page title matches header expectation if needed, or let header handle it
// $page_title = "Process Application"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Application - Fundline</title>
    
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
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined filled">check_circle</span>
                            <?php 
                            echo htmlspecialchars($_SESSION['success_message']); 
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <a href="admin_applications.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 rounded-pill px-3">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">arrow_back</span>
                        Back to Applications
                    </a>
                </div>

                <!-- Application Header -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h3 class="fw-bold mb-1">Application #<?php echo htmlspecialchars($application['application_number']); ?></h3>
                                <p class="text-muted small mb-0">Submitted: <?php echo date('M d, Y', strtotime($application['submitted_date'])); ?></p>
                            </div>
                            <?php 
                                $status = $application['application_status'];
                                $badgeClass = 'bg-secondary text-secondary';
                                if ($status == 'Approved') $badgeClass = 'bg-success text-success';
                                elseif ($status == 'Rejected') $badgeClass = 'bg-danger text-danger';
                                elseif ($status == 'Under Review') $badgeClass = 'bg-warning text-warning';
                                elseif ($status == 'Submitted') $badgeClass = 'bg-info text-info';
                            ?>
                            <span class="badge <?php echo $badgeClass; ?> bg-opacity-10 px-3 py-2 rounded-pill fs-6 fw-normal">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-4 col-lg-2">
                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Client Name</small>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['middle_name'] . ' ' . $application['last_name']); ?></p>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Loan Product</small>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($application['product_name']); ?></p>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Requested Amount</small>
                                <p class="mb-0 fw-bold text-primary fs-5">₱<?php echo number_format($application['requested_amount'], 2); ?></p>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Loan Term</small>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($application['loan_term_months']); ?> months</p>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Interest Rate</small>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($application['interest_rate']); ?>%</p>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Contact</small>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($application['contact_number']); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($application['loan_purpose']): ?>
                        <div class="mt-4 pt-3 border-top">
                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Loan Purpose</small>
                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($application['loan_purpose']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Credit Investigation -->
                    <?php if ($credit_investigation): ?>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-0">Credit Investigation Report</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block mb-1">Conducted By</small>
                                        <span class="fw-medium"><?php echo htmlspecialchars($credit_investigation['first_name'] . ' ' . $credit_investigation['last_name']); ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block mb-1">Date</small>
                                        <span class="fw-medium"><?php echo date('M d, Y', strtotime($credit_investigation['investigation_date'])); ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block mb-1">Character Rating</small>
                                        <span class="fw-medium"><?php echo htmlspecialchars($credit_investigation['character_rating']); ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block mb-1">Recommendation</small>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($credit_investigation['recommendation']); ?></span>
                                    </div>
                                    <?php if ($credit_investigation['investigation_remarks']): ?>
                                    <div class="col-12 mt-3">
                                        <small class="text-muted d-block mb-1">Remarks</small>
                                        <div class="p-3 bg-light rounded-3 text-secondary small">
                                            <?php echo htmlspecialchars($credit_investigation['investigation_remarks']); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Credit Score -->
                    <?php if ($credit_score): ?>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-0">Credit Score</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="d-flex flex-column flex-sm-row align-items-center gap-4">
                                    <div class="position-relative d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                        <svg class="w-100 h-100" viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="45" fill="none" stroke="#f0f0f0" stroke-width="8" />
                                            <!-- Simple calculation for circle stroke-dasharray based on score/max (assuming 1000 max) -->
                                            <?php $percentage = min(100, max(0, ($credit_score['total_score'] / 1000) * 100)); ?>
                                            <circle cx="50" cy="50" r="45" fill="none" stroke="var(--bs-primary)" stroke-width="8" 
                                                    stroke-dasharray="<?php echo ($percentage * 2.83); ?> 283" transform="rotate(-90 50 50)" stroke-linecap="round" />
                                        </svg>
                                        <div class="position-absolute text-center">
                                            <span class="fs-2 fw-bold text-primary d-block lh-1"><?php echo htmlspecialchars($credit_score['total_score']); ?></span>
                                        </div>
                                    </div>
                                    <div class="text-center text-sm-start">
                                        <h5 class="fw-bold mb-1">Credit Rating: <span class="text-primary"><?php echo htmlspecialchars($credit_score['credit_rating']); ?></span></h5>
                                        <p class="text-muted small mb-2">Based on system evaluation</p>
                                        <div class="p-2 bg-success bg-opacity-10 text-success rounded-3 d-inline-block">
                                            <small class="fw-bold">Max Loan Amount: ₱<?php echo number_format($credit_score['max_loan_amount'], 2); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Action Forms -->
                <?php if (in_array($application['application_status'], ['Submitted', 'Under Review', 'Document Verification', 'Credit Investigation', 'For Approval'])): ?>
                <div class="card border-0 shadow-sm rounded-4 mt-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h5 class="fw-bold mb-0">Process Application</h5>
                    </div>
                    <div class="card-body p-4">
                        
                        <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active rounded-pill px-4" id="pills-status-tab" data-bs-toggle="pill" data-bs-target="#pills-status" type="button" role="tab">Update Status</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill px-4" id="pills-approve-tab" data-bs-toggle="pill" data-bs-target="#pills-approve" type="button" role="tab">Approve</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill px-4" id="pills-reject-tab" data-bs-toggle="pill" data-bs-target="#pills-reject" type="button" role="tab">Reject</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="pills-tabContent">
                            <!-- Update Status Form -->
                            <div class="tab-pane fade show active" id="pills-status" role="tabpanel">
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="update_status">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">New Status</label>
                                        <select name="status" class="form-select" required>
                                            <option value="">Select Status</option>
                                            <option value="Under Review">Under Review</option>
                                            <option value="Document Verification">Document Verification</option>
                                            <option value="Credit Investigation">Credit Investigation</option>
                                            <option value="For Approval">For Approval</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted">Review Notes</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="Add notes about this status update..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <span class="material-symbols-outlined align-middle me-1">update</span>
                                            Update Status
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Approve Form -->
                            <div class="tab-pane fade" id="pills-approve" role="tabpanel">
                                <div class="alert alert-info border-0 rounded-3 d-flex align-items-center gap-2 mb-3">
                                    <span class="material-symbols-outlined">info</span>
                                    <small>Approving this application will generate a loan record.</small>
                                </div>
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="approve">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">Approved Amount (₱)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">₱</span>
                                            <input type="number" name="approved_amount" class="form-control border-start-0 ps-0" 
                                                   value="<?php echo $application['requested_amount']; ?>" step="0.01" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted">Approval Notes</label>
                                        <textarea name="approval_notes" class="form-control" rows="3" placeholder="Reason for approval or specific terms..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-success text-white">
                                            <span class="material-symbols-outlined align-middle me-1">check_circle</span>
                                            Approve Application
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Reject Form -->
                            <div class="tab-pane fade" id="pills-reject" role="tabpanel">
                                <div class="alert alert-warning border-0 rounded-3 d-flex align-items-center gap-2 mb-3">
                                    <span class="material-symbols-outlined">warning</span>
                                    <small>Rejection cannot be undone. The client will be notified.</small>
                                </div>
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="reject">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted">Rejection Reason</label>
                                        <textarea name="rejection_reason" class="form-control border-danger" rows="3" required placeholder="Please provide a reason for rejection..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-danger">
                                            <span class="material-symbols-outlined align-middle me-1">cancel</span>
                                            Reject Application
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
