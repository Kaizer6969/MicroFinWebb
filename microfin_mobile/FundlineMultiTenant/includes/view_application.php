<?php
/**
 * View Loan Application Details
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
$avatar_letter = strtoupper(substr($username, 0, 1));

$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($application_id === 0) {
    header("Location: " . ($user_type === 'Employee' ? 'admin_applications.php' : 'my_applications.php'));
    exit();
}

// Get application details with access control
$query = "
    SELECT la.*, 
           lp.product_name, lp.product_type,
           c.first_name, c.last_name, c.client_code, c.contact_number,
           c.email_address, c.present_street, c.present_barangay,
           c.present_city, c.present_province,
           c.employment_status, c.occupation, c.monthly_income,
           u.email,
           rev.first_name as reviewer_fname, rev.last_name as reviewer_lname,
           apr.first_name as approver_fname, apr.last_name as approver_lname,
           rej.first_name as rejecter_fname, rej.last_name as rejecter_lname
    FROM loan_applications la
    JOIN loan_products lp ON la.product_id = lp.product_id
    JOIN clients c ON la.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    LEFT JOIN employees rev ON la.reviewed_by = rev.employee_id
    LEFT JOIN employees apr ON la.approved_by = apr.employee_id
    LEFT JOIN employees rej ON la.rejected_by = rej.employee_id
    WHERE la.application_id = ?
";

// Add access control for clients
if ($user_type === 'Client') {
    $query .= " AND c.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $application_id, $user_id);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $application_id);
}

$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();
$stmt->close();

if (!$application) {
    header("Location: " . ($user_type === 'Employee' ? 'admin_applications.php' : 'my_applications.php'));
    exit();
}

// Get application documents
$docs_stmt = $conn->prepare("
    SELECT ad.*, dt.document_name, dt.description
    FROM application_documents ad
    JOIN document_types dt ON ad.document_type_id = dt.document_type_id
    WHERE ad.application_id = ?
");
$docs_stmt->bind_param("i", $application_id);
$docs_stmt->execute();
$docs_result = $docs_stmt->get_result();
$documents = [];
while ($doc = $docs_result->fetch_assoc()) {
    $documents[] = $doc;
}
$docs_stmt->close();

// $conn->close(); // Removed to allow header to access DB

function getStatusBadgeClass($status) {
    $classes = [
        'Draft' => 'bg-secondary',
        'Submitted' => 'bg-info',
        'Under Review' => 'bg-warning',
        'Document Verification' => 'bg-warning',
        'Credit Investigation' => 'bg-warning',
        'For Approval' => 'bg-warning',
        'Approved' => 'bg-success',
        'Rejected' => 'bg-danger',
        'Cancelled' => 'bg-secondary',
        'Withdrawn' => 'bg-secondary'
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
    <title>Application Details - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <style>
        .timeline { position: relative; padding-left: 2rem; }
        .timeline::before {
            content: '';
            position: absolute; left: 0.5rem; top: 0.5rem; bottom: 0.5rem;
            width: 2px; background-color: var(--color-border-subtle);
        }
        .timeline-item { position: relative; padding-bottom: 2rem; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-item::before {
            content: '';
            position: absolute; left: -1.625rem; top: 0.25rem;
            width: 0.75rem; height: 0.75rem;
            border-radius: 50%; background-color: var(--color-primary);
            border: 2px solid var(--color-surface-light);
            z-index: 1;
        }
        
        .timeline-item.approved::before { background-color: var(--color-success); }
        .timeline-item.rejected::before { background-color: var(--color-danger); }
        .timeline-item.review::before { background-color: var(--color-warning); }
        
        .document-card {
            transition: all var(--transition-fast);
        }
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md) !important;
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
                // For client header, specific variable might be needed or it just works
                $page_title = "Application Details";
                include 'client_header.php'; 
            } else {
                include 'admin_header.php'; 
            }
            ?>
            
            <div class="container-fluid p-4">
                
                <!-- Page Header with Back Button -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Application #<?php echo htmlspecialchars($application['application_number']); ?></h4>
                        <p class="text-muted small mb-0">For <?php echo htmlspecialchars($application['product_name']); ?></p>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" onclick="window.history.back()">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">arrow_back</span>
                        Back
                    </button>
                </div>
                
                <div class="row g-4">
                    <!-- Left Column: Application Info -->
                    <div class="col-lg-8">
                        <!-- Overview Card -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                             <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="fw-bold mb-1">Application Overview</h5>
                                    <p class="text-muted small mb-0">Details and financial request</p>
                                </div>
                                <span class="badge <?php echo getStatusBadgeClass($application['application_status']); ?> rounded-pill px-3 py-2">
                                    <?php echo htmlspecialchars($application['application_status']); ?>
                                </span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <div class="col-sm-6 col-md-4">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Date Submitted</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">calendar_today</span>
                                            <span class="fw-medium"><?php echo date('M d, Y', strtotime($application['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Desired Term</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">schedule</span>
                                            <span class="fw-medium"><?php echo $application['loan_term_months']; ?> Months</span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Product Type</small>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined text-muted" style="font-size: 1.1rem;">category</span>
                                            <span class="fw-medium"><?php echo htmlspecialchars($application['product_name']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="text-muted opacity-10 my-4">
                                
                                <div class="row g-4">
                                    <div class="col-sm-6">
                                        <div class="p-3 bg-light rounded-3 h-100">
                                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Requested Amount</small>
                                            <p class="h4 fw-bold text-primary mb-0">₱<?php echo number_format($application['requested_amount'], 2); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="p-3 bg-light rounded-3 h-100">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Interest Rate</small>
                                                <?php if($application['application_status'] === 'Approved'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success" style="font-size: 0.6rem;">APPROVED RATE</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="h4 fw-bold text-dark mb-0"><?php echo $application['interest_rate']; ?>% <span class="fs-6 text-muted fw-normal">/ month</span></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Loan Purpose</small>
                                    <p class="fw-medium text-dark bg-light bg-opacity-50 p-3 rounded-3 border border-light mb-0">
                                        <?php echo !empty($application['loan_purpose']) ? htmlspecialchars($application['loan_purpose']) : 'No purpose specified.'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Co-maker Info (if exists) -->
                        <?php if ($application['has_comaker']): ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                             <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-1">Co-Maker Details</h5>
                            </div>
                             <div class="card-body p-4">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                                                <span class="material-symbols-outlined">group</span>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($application['comaker_name']); ?></h6>
                                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($application['comaker_relationship']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 border-start border-light ps-md-4">
                                        <div class="mb-2">
                                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Contact</small>
                                            <div class="fw-medium"><?php echo htmlspecialchars($application['comaker_contact']); ?></div>
                                        </div>
                                        <div>
                                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Income</small>
                                            <div class="fw-medium">₱<?php echo number_format($application['comaker_income'], 2); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Application Timeline -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                             <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                                <h5 class="fw-bold mb-1">Application History</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="fw-bold text-dark">Application Submitted</div>
                                        <div class="small text-secondary mb-1">
                                            <?php echo date('M d, Y h:i A', strtotime($application['created_at'])); ?>
                                        </div>
                                        <div class="small text-muted">Application received and pending review.</div>
                                    </div>
                                    
                                    <?php if ($application['review_date']): ?>
                                    <div class="timeline-item review">
                                        <div class="fw-bold text-warning">Under Review</div>
                                        <div class="small text-secondary mb-1">
                                            <?php echo date('M d, Y h:i A', strtotime($application['review_date'])); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php if ($application['reviewer_fname']): ?>
                                                Reviewed by <?php echo htmlspecialchars($application['reviewer_fname'] . ' ' . $application['reviewer_lname']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($application['review_notes']): ?>
                                        <div class="small bg-warning bg-opacity-10 p-2 rounded mt-2 text-warning-emphasis">
                                            "<?php echo htmlspecialchars($application['review_notes']); ?>"
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($application['approval_date']): ?>
                                    <div class="timeline-item approved">
                                        <div class="fw-bold text-success">Application Approved</div>
                                        <div class="small text-secondary mb-1">
                                            <?php echo date('M d, Y h:i A', strtotime($application['approval_date'])); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php if ($application['approver_fname']): ?>
                                                Approved by <?php echo htmlspecialchars($application['approver_fname'] . ' ' . $application['approver_lname']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($application['approval_notes']): ?>
                                        <div class="small bg-success bg-opacity-10 p-2 rounded mt-2 text-success-emphasis">
                                            Notes: <?php echo htmlspecialchars($application['approval_notes']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($application['rejection_date']): ?>
                                    <div class="timeline-item rejected">
                                        <div class="fw-bold text-danger">Application Rejected</div>
                                        <div class="small text-secondary mb-1">
                                            <?php echo date('M d, Y h:i A', strtotime($application['rejection_date'])); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php if ($application['rejecter_fname']): ?>
                                                Rejected by <?php echo htmlspecialchars($application['rejecter_fname'] . ' ' . $application['rejecter_lname']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($application['rejection_reason']): ?>
                                        <div class="small bg-danger bg-opacity-10 p-2 rounded mt-2 text-danger-emphasis">
                                            Reason: <?php echo htmlspecialchars($application['rejection_reason']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Applicant & Docs -->
                    <div class="col-lg-4">
                        <!-- Applicant Profile (Hidden for Client to avoid redundancy, consistent with view_loan logic) -->
                        <?php if (!$is_client): ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-1">Applicant</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 56px; height: 56px; font-weight: bold; font-size: 1.5rem;">
                                        <?php echo strtoupper(substr($application['first_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></h6>
                                        <p class="text-secondary small mb-0"><?php echo htmlspecialchars($application['client_code']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="vstack gap-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="material-symbols-outlined text-secondary fs-6 mt-1">mail</span>
                                        <div>
                                            <span class="d-block small text-secondary fw-bold text-uppercase">Email</span>
                                            <span class="text-dark small text-break"><?php echo htmlspecialchars($application['email']); ?></span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="material-symbols-outlined text-secondary fs-6 mt-1">call</span>
                                        <div>
                                            <span class="d-block small text-secondary fw-bold text-uppercase">Contact</span>
                                            <span class="text-dark small"><?php echo htmlspecialchars($application['contact_number']); ?></span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="material-symbols-outlined text-secondary fs-6 mt-1">location_on</span>
                                        <div>
                                            <span class="d-block small text-secondary fw-bold text-uppercase">Address</span>
                                            <span class="text-dark small"><?php echo htmlspecialchars($application['present_city'] . ', ' . $application['present_province']); ?></span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start gap-3">
                                         <span class="material-symbols-outlined text-secondary fs-6 mt-1">work</span>
                                        <div>
                                            <span class="d-block small text-secondary fw-bold text-uppercase">Employment</span>
                                            <span class="text-dark small"><?php echo htmlspecialchars($application['occupation']); ?> (<?php echo htmlspecialchars($application['employment_status']); ?>)</span>
                                            <div class="small text-success fw-bold">₱<?php echo number_format($application['monthly_income'], 2); ?>/mo</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-top">
                                    <a href="view_client.php?id=<?php echo $application['client_id']; ?>" class="btn-action-view w-100 text-center">
                                        View Full Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Documents -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-1">Documents</h5>
                                <span class="badge bg-light text-dark rounded-pill border"><?php echo count($documents); ?></span>
                            </div>
                            <div class="card-body p-4">
                                <?php if (!empty($documents)): ?>
                                    <div class="vstack gap-3">
                                        <?php foreach ($documents as $doc): ?>
                                        <div class="document-card d-flex align-items-center justify-content-between p-3 border rounded-3 bg-white">
                                            <div class="d-flex align-items-center gap-3 overflow-hidden">
                                                <div class="bg-light p-2 rounded-2 text-primary d-flex align-items-center justify-content-center">
                                                     <span class="material-symbols-outlined">description</span>
                                                </div>
                                                <div class="overflow-hidden">
                                                    <h6 class="fw-bold mb-0 small text-truncate text-dark"><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                                                    <p class="text-muted small mb-0 one-line" style="font-size: 0.75rem;">
                                                        Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn-action-view" title="View Document">
                                                View
                                            </a>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <span class="material-symbols-outlined text-muted opacity-25 fs-1">folder_off</span>
                                        <p class="small text-muted mb-0 mt-2">No documents attached.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <?php if (!$is_client && !in_array($application['application_status'], ['Approved', 'Rejected', 'Cancelled'])): ?>
                        <div class="card border-0 shadow-sm rounded-4 bg-primary text-white overflow-hidden position-relative">
                             <div class="card-body p-4 position-relative z-1 text-center">
                                <h5 class="fw-bold mb-2">Process Application</h5>
                                <p class="text-white-50 small mb-3">Review details and verify documents.</p>
                                <a href="process_application.php?id=<?php echo $application_id; ?>" class="btn btn-light text-primary fw-bold w-100 rounded-pill shadow-sm">
                                    Start Processing
                                    <span class="material-symbols-outlined align-middle ms-1 fs-6">arrow_forward</span>
                                </a>
                            </div>
                            <!-- Decorative Circle -->
                            <div class="position-absolute top-0 end-0 translate-middle p-5 bg-white opacity-10 rounded-circle" style="margin-top: -1rem; margin-right: -2rem;"></div>
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
