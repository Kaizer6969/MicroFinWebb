<?php
/**
 * Admin Document Verification Page
 * Allows admins to approve or reject client document submissions
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Employee') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';
require_once 'calculate_credit_limit.php';
require_once 'email_helper.php';

$user_id = $_SESSION['user_id'];
// Username and avatar usually handled by admin_header logic or session

// Handle approval/rejection
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $client_id = intval($_POST['client_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Get client's monthly income and email
        $income_stmt = $conn->prepare("SELECT monthly_income, email_address, first_name, last_name FROM clients WHERE client_id = ?");
        $income_stmt->bind_param("i", $client_id);
        $income_stmt->execute();
        $income_result = $income_stmt->get_result();
        
        if ($income_row = $income_result->fetch_assoc()) {
            $monthly_income = floatval($income_row['monthly_income']);
            $client_email = $income_row['email_address'];
            $client_name = $income_row['first_name'] . ' ' . $income_row['last_name'];
            
            // Automatically calculate initial credit limit
            $auto_credit_limit = getInitialCreditLimit($monthly_income);
            
            // Update client status and set automatic credit limit
            $stmt = $conn->prepare("UPDATE clients SET document_verification_status = 'Approved', verification_rejection_reason = NULL, credit_limit = ?, credit_limit_tier = 0 WHERE client_id = ?");
            $stmt->bind_param("di", $auto_credit_limit, $client_id);
            $stmt->execute();
            $stmt->close();
            
            // Send approval email notification
            $emailResult = sendVerificationApprovalEmail($client_email, $client_name, $auto_credit_limit);
            
            $success_message = "Client documents approved! Credit limit automatically set to ₱" . number_format($auto_credit_limit, 2) . " based on monthly income of ₱" . number_format($monthly_income, 2);
            if ($emailResult['success']) {
                $success_message .= " Email notification sent to client.";
            }
        } else {
            $error_message = "Error: Could not retrieve client income information.";
        }
        $income_stmt->close();
    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason']);
        
        // Get client email and name
        $client_stmt = $conn->prepare("SELECT email_address, first_name, last_name FROM clients WHERE client_id = ?");
        $client_stmt->bind_param("i", $client_id);
        $client_stmt->execute();
        $client_result = $client_stmt->get_result();
        $client_row = $client_result->fetch_assoc();
        $client_email = $client_row['email_address'];
        $client_name = $client_row['first_name'] . ' ' . $client_row['last_name'];
        $client_stmt->close();
        
        // Update client status
        $stmt = $conn->prepare("UPDATE clients SET document_verification_status = 'Rejected', verification_rejection_reason = ?, seen_rejection_modal = 0 WHERE client_id = ?");
        $stmt->bind_param("si", $reason, $client_id);
        $stmt->execute();
        $stmt->close();
        
        // Send rejection email notification
        $emailResult = sendVerificationRejectionEmail($client_email, $client_name, $reason);
        
        $success_message = "Client documents rejected.";
        if ($emailResult['success']) {
            $success_message .= " Email notification sent to client.";
        }
    }
}

// Get clients with pending verification
$pending_clients = [];
$stmt = $conn->prepare("
    SELECT c.client_id, c.client_code, c.first_name, c.last_name, c.email_address, 
           c.contact_number, c.registration_date, c.document_verification_status,
           COUNT(cd.client_document_id) as document_count
    FROM clients c
    LEFT JOIN client_documents cd ON c.client_id = cd.client_id
    WHERE c.document_verification_status = 'Pending'
    GROUP BY c.client_id
    ORDER BY c.registration_date DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pending_clients[] = $row;
}
$stmt->close();

// Get all clients for reference
$all_clients = [];
$stmt = $conn->prepare("
    SELECT c.client_id, c.client_code, c.first_name, c.last_name, c.email_address, 
           c.document_verification_status, c.registration_date,
           COUNT(cd.client_document_id) as document_count
    FROM clients c
    LEFT JOIN client_documents cd ON c.client_id = cd.client_id
    GROUP BY c.client_id
    ORDER BY c.registration_date DESC
    LIMIT 50
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_clients[] = $row;
}
$stmt->close();

$conn->close();

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Approved': return 'bg-success';
        case 'Pending': return 'bg-warning';
        case 'Rejected': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Document Verification - Fundline</title>
    
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
            
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">Document Verification</h4>
                        <p class="text-secondary small mb-0">Review and approve client requirements</p>
                    </div>
                    <!-- Optional: Add filters or actions here if needed -->
                </div>
                
           
                
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

                <!-- Pending Verifications -->
                <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
                     <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-main">Pending Verifications</h5>
                            <p class="text-muted small mb-0">Clients waiting for review</p>
                        </div>
                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2 fw-bold">
                            <?php echo count($pending_clients); ?> Pending
                        </span>
                    </div>
                    <div class="table-container border-0 shadow-none mb-0">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold tracking-wide">Client</th>
                                    <th class="py-3 text-secondary text-uppercase small fw-bold tracking-wide">Email</th>
                                    <th class="py-3 text-secondary text-uppercase small fw-bold tracking-wide">Documents</th>
                                    <th class="py-3 text-secondary text-uppercase small fw-bold tracking-wide">Registered</th>
                                    <th class="text-end pe-4 py-3 text-secondary text-uppercase small fw-bold tracking-wide">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_clients)): ?>
                                <tr>
                                    <td colspan="5" class="table-empty py-5">
                                        <div class="d-flex flex-column align-items-center py-4">
                                            <div class="bg-light rounded-circle p-4 mb-3">
                                                <span class="material-symbols-outlined fs-1 text-secondary opacity-50">check_circle</span>
                                            </div>
                                            <h6 class="text-secondary fw-bold mb-1">All Caught Up!</h6>
                                            <p class="text-muted small mb-0">No pending verifications at the moment.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($pending_clients as $client): 
                                        $initials = strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1));
                                        $bgColors = ['primary', 'success', 'danger', 'warning', 'info', 'secondary'];
                                        $randomBg = $bgColors[array_rand($bgColors)];
                                    ?>
                                    <tr>
                                        <td class="ps-4 align-middle py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-circle bg-<?php echo $randomBg; ?> bg-opacity-10 text-<?php echo $randomBg; ?> rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; font-size: 14px;">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></div>
                                                    <div class="small text-secondary"><?php echo htmlspecialchars($client['client_code']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-secondary align-middle"><?php echo htmlspecialchars($client['email_address']); ?></td>
                                        <td class="align-middle">
                                            <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-2 fw-medium border border-info border-opacity-10">
                                                <span class="material-symbols-outlined me-1" style="font-size: 14px; vertical-align: -3px;">attach_file</span>
                                                <?php echo $client['document_count']; ?> files
                                            </span>
                                        </td>
                                        <td class="text-secondary small align-middle"><?php echo date('M d, Y', strtotime($client['registration_date'])); ?></td>
                                        <td class="text-end pe-4 align-middle">
                                            <button class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="viewDocuments(<?php echo $client['client_id']; ?>, '<?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>')">
                                                Review
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                        <h5 class="fw-bold mb-1 text-main">Recent Activity</h5>
                        <p class="text-muted small mb-0">Status of recent registrations</p>
                    </div>
                    <div class="table-container border-0 shadow-none mb-0">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold tracking-wide">Client</th>
                                    <th class="py-3 text-secondary text-uppercase small fw-bold tracking-wide">Email</th>
                                    <th class="py-3 text-secondary text-uppercase small fw-bold tracking-wide">Documents</th>
                                    <th class="py-3 text-secondary text-uppercase small fw-bold tracking-wide">Status</th>
                                    <th class="pe-4 py-3 text-secondary text-uppercase small fw-bold tracking-wide">Registered</th>
                                </tr>
                            </thead>
                                <tbody>
                                <?php foreach ($all_clients as $client): 
                                    $initials = strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1));
                                    // Consistent color based on first char of name to keep it semi-stable
                                    $bgColors = ['primary', 'success', 'danger', 'warning', 'info', 'secondary'];
                                    $colorIndex = ord($initials[0]) % count($bgColors);
                                    $bgClass = $bgColors[$colorIndex];
                                ?>
                                <tr>
                                    <td class="ps-4 align-middle py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-circle bg-<?php echo $bgClass; ?> bg-opacity-10 text-<?php echo $bgClass; ?> rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 13px;">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></div>
                                                <div class="small text-secondary"><?php echo htmlspecialchars($client['client_code']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-secondary align-middle"><?php echo htmlspecialchars($client['email_address']); ?></td>
                                    <td class="text-secondary small align-middle"><?php echo $client['document_count']; ?> files</td>
                                        <td class="align-middle">
                                            <?php 
                                            // Mapping status to new badge classes
                                            $status = $client['document_verification_status'];
                                            $badgeClass = 'bg-secondary bg-opacity-10 text-secondary';
                                            if ($status === 'Approved') $badgeClass = 'bg-success bg-opacity-10 text-success';
                                            elseif ($status === 'Rejected') $badgeClass = 'bg-danger bg-opacity-10 text-danger';
                                            elseif ($status === 'Pending') $badgeClass = 'bg-warning bg-opacity-10 text-warning';
                                            
                                            $displayStatus = $status ?: 'Not Submitted';
                                            ?>
                                            <span class="badge rounded-pill <?php echo $badgeClass; ?> fw-bold px-3 py-2">
                                                <?php echo $displayStatus; ?>
                                            </span>
                                        </td>
                                    <td class="pe-4 text-secondary small align-middle"><?php echo date('M d, Y', strtotime($client['registration_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            </div> <!-- End padding wrapper -->
        </div>
    </div>
    
    <!-- Document Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="modal-header bg-white border-bottom-0 p-4 pb-0">
                    <div>
                        <h4 class="modal-title fw-bolder text-dark mb-1" id="modalClientName">Review Documents</h4>
                        <p class="text-secondary small mb-0">Verify client submission and automate credit check</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-3" id="modalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading documents...</p>
                    </div>
                </div>
                <div class="modal-footer border-top-0 d-block px-4 pb-4 pt-0 bg-white">
                    <form method="POST">
                        <input type="hidden" name="client_id" id="modalClientId">
                        
                        <div class="bg-success bg-opacity-10 border border-success border-opacity-10 rounded-4 p-4 mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <div class="d-flex align-items-center gap-2 text-success mb-2">
                                        <span class="material-symbols-outlined filled">verified</span>
                                        <h6 class="fw-bold mb-0 text-uppercase tracking-wide small">Recommended Credit Limit</h6>
                                    </div>
                                    <h2 class="fw-bolder text-success mb-0 display-6">₱<span id="calculatedLimit">0.00</span></h2>
                                    <p class="text-muted small mb-0 mt-1">Based on income of <strong class="text-dark">₱<span id="clientIncome">0</span></strong></p>
                                </div>
                                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                    <div class="d-inline-block text-start bg-white bg-opacity-50 rounded-3 p-2 px-3">
                                        <p class="text-xs text-uppercase fw-bold text-secondary mb-1">Tier Range</p>
                                        <p class="fw-bold text-dark mb-0">₱<span id="creditRange">0 - 0</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="actionButtons" class="d-flex gap-2">
                             <button type="button" class="btn btn-outline-danger btn-lg flex-grow-0 rounded-pill px-4 fw-bold" onclick="showRejectForm()">
                                <span class="material-symbols-outlined align-middle me-1">close</span>
                                Reject
                            </button>
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-lg flex-grow-1 rounded-pill fw-bold shadow-sm">
                                <span class="material-symbols-outlined align-middle me-1">check_circle</span>
                                Approve Verification
                            </button>
                        </div>
                        
                        <!-- Rejection Form -->
                        <div id="rejectForm" class="mt-0 bg-danger bg-opacity-10 rounded-4 p-4 border border-danger border-opacity-10" style="display: none;">
                            <label class="form-label fw-bold text-danger">Reason for Rejection</label>
                            <textarea name="rejection_reason" class="form-control border-0 bg-white mb-3" rows="3" placeholder="Please explain why the documents are being rejected..."></textarea>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" onclick="hideRejectForm()">Cancel</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger rounded-pill px-4 fw-bold">Confirm Rejection</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
        
        function dateString(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        // Credit limit calculation logic
        function calculateInitialCreditLimit(monthlyIncome) {
             const brackets = [
                {min: 10000, max: 19999, creditMin: 5000, creditMax: 15000},
                {min: 20000, max: 29999, creditMin: 10000, creditMax: 30000},
                {min: 30000, max: 39999, creditMin: 20000, creditMax: 50000},
                {min: 40000, max: 49999, creditMin: 30000, creditMax: 70000},
                {min: 50000, max: 59999, creditMin: 40000, creditMax: 90000},
                {min: 60000, max: 69999, creditMin: 50000, creditMax: 110000},
                {min: 70000, max: 79999, creditMin: 60000, creditMax: 130000},
                {min: 80000, max: 89999, creditMin: 70000, creditMax: 150000},
                {min: 90000, max: 99999, creditMin: 80000, creditMax: 170000},
                {min: 100000, max: Infinity, creditMin: 90000, creditMax: 200000}
            ];
            
            if (monthlyIncome < 10000) return {creditMin: 3000, creditMax: 10000, initial: 3000};
            
            for (let bracket of brackets) {
                if (monthlyIncome >= bracket.min && monthlyIncome <= bracket.max) {
                    return {creditMin: bracket.creditMin, creditMax: bracket.creditMax, initial: bracket.creditMin};
                }
            }
            return {creditMin: 0, creditMax: 0, initial: 0};
        }
        
        async function viewDocuments(clientId, clientName) {
            document.getElementById('modalClientName').textContent = 'Review Documents - ' + clientName;
            document.getElementById('modalClientId').value = clientId;
            hideRejectForm();
            
            reviewModal.show();
            
            // Loading state
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Fetching documents...</p>
                </div>
            `;
            
            try {
                const response = await fetch('get_client_documents.php?client_id=' + clientId);
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Check for error in response
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Debug logging
                console.log('=== DOCUMENT RETRIEVAL DEBUG ===');
                console.log('Client ID:', clientId);
                console.log('Response data:', data);
                console.log('Documents array:', data.documents);
                console.log('Document count:', data.documents ? data.documents.length : 0);
                
                if (data.documents && data.documents.length > 0) {
                    console.log('Document names:');
                    data.documents.forEach((doc, index) => {
                        console.log(`  ${index + 1}. "${doc.document_name}" - ${doc.file_path}`);
                    });
                }
                
                const profile = data.profile || {};
                const documents = data.documents || [];
                
                // Calculations
                const monthlyIncome = Number(profile.monthly_income || 0);
                const creditInfo = calculateInitialCreditLimit(monthlyIncome);
                
                // Update Badge Area
                document.getElementById('calculatedLimit').textContent = creditInfo.initial.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('clientIncome').textContent = monthlyIncome.toLocaleString('en-PH');
                document.getElementById('creditRange').textContent = creditInfo.creditMin.toLocaleString('en-PH') + ' - ' + creditInfo.creditMax.toLocaleString('en-PH');
                
                // Prepare HTML
                let profileHtml = `
                    <div class="row g-4">
                        <div class="col-lg-5">
                             <div class="bg-light bg-opacity-50 p-4 rounded-4 h-100">
                                <h6 class="text-secondary fw-bold text-uppercase small mb-4 tracking-wide">Applicant Profile</h6>
                                
                                <div class="mb-4">
                                     <label class="text-secondary small fw-bold text-uppercase d-block mb-1 opacity-75">Full Name</label>
                                    <div class="fw-bold text-dark fs-5">${profile.first_name} ${profile.middle_name || ''} ${profile.last_name} ${profile.suffix || ''}</div>
                                </div>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <label class="text-secondary small fw-bold text-uppercase d-block mb-1 opacity-75">Birth Date</label>
                                        <div class="fw-bold text-dark">${profile.date_of_birth || 'N/A'}</div>
                                    </div>
                                    <div class="col-6">
                                        <label class="text-secondary small fw-bold text-uppercase d-block mb-1 opacity-75">Civil Status</label>
                                        <div class="fw-bold text-dark">${profile.civil_status || 'N/A'}</div>
                                    </div>
                                </div>

                                ${profile.id_type ? `
                                <div class="mb-4">
                                    <label class="text-secondary small fw-bold text-uppercase d-block mb-1 opacity-75">ID Type Presented</label>
                                    <span class="badge bg-white border border-secondary border-opacity-25 text-dark px-3 py-2 rounded-pill">${profile.id_type}</span>
                                </div>
                                ` : ''}
                                
                                <div class="border-top my-4 opacity-50"></div>
                                
                                <h6 class="text-secondary fw-bold text-uppercase small mb-3 tracking-wide">Employment & Address</h6>
                                <div class="mb-3">
                                    <div class="mb-2">
                                        <label class="text-secondary small fw-bold text-uppercase d-block mb-0 opacity-75" style="font-size: 0.7rem;">Employer / Business</label>
                                        <div class="fw-medium text-dark">${profile.employer_name || 'N/A'} (${profile.employment_status || 'N/A'})</div>
                                    </div>
                                    <div>
                                        <label class="text-secondary small fw-bold text-uppercase d-block mb-0 opacity-75" style="font-size: 0.7rem;">Present Address</label>
                                        <div class="fw-medium text-dark small text-wrap">
                                            ${[profile.present_house_no, profile.present_street, profile.present_barangay, profile.present_city, profile.present_province].filter(Boolean).join(', ')}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="border-top my-4 opacity-50"></div>
                                
                                <h6 class="text-secondary fw-bold text-uppercase small mb-3 tracking-wide">Co-Maker Information</h6>
                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-12 mb-2">
                                             <div class="d-flex align-items-center gap-2">
                                                <span class="material-symbols-outlined fs-6 text-secondary">person</span>
                                                <span class="fw-bold text-dark">${profile.comaker_name || 'N/A'}</span>
                                            </div>
                                            <div class="small text-secondary ps-4">${profile.comaker_relationship || 'Relationship N/A'}</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="material-symbols-outlined fs-6 text-secondary">phone</span>
                                                <span class="fw-medium text-dark">${profile.comaker_contact || 'N/A'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-white p-3 rounded-3 border border-light shadow-sm mt-3">
                                    <label class="text-secondary small fw-bold text-uppercase d-block mb-1 opacity-75">Monthly Income</label>
                                    <div class="fw-bolder text-dark fs-4">₱${Number(profile.monthly_income || 0).toLocaleString()}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-7">
                            <h6 class="text-secondary fw-bold text-uppercase small mb-3 tracking-wide ps-1">Submitted Documents</h6>
                `;
                
                if (documents.length === 0) {
                     profileHtml += `
                        <div class="text-center text-muted py-5 bg-light rounded-4 border border-dashed">
                            <span class="material-symbols-outlined fs-1 opacity-25">folder_off</span>
                            <p class="mt-2 mb-0">No documents uploaded</p>
                        </div>
                     `;
                } else {
                    // Separate ID documents from others
                    // Improved logic: Match ANY valid ID type for the main display
                    const idFront = documents.find(doc => doc.document_name === 'Valid ID Front');
                    const idBack = documents.find(doc => doc.document_name === 'Valid ID Back');
                    
                    // Fallback for legacy ID uploads - treat "Valid ID (Government-issued)" as FRONT if no explicit front exists
                    const legacyId = documents.find(doc => 
                        doc.document_name === 'Valid ID' || 
                        doc.document_name === 'Valid ID (Government-issued)'
                    );
                    
                    // Use legacy ID as front if no explicit front is uploaded
                    const effectiveFront = idFront || legacyId;
                    
                    const otherDocs = documents.filter(doc => 
                        doc.document_name !== 'Valid ID Front' && 
                        doc.document_name !== 'Valid ID Back' &&
                        doc.document_name !== 'Valid ID' &&
                        doc.document_name !== 'Valid ID (Government-issued)'
                    );
                    
                    console.log('ID Detection:', {
                        idFront: idFront ? 'Found' : 'Missing',
                        idBack: idBack ? 'Found' : 'Missing',
                        legacyId: legacyId ? 'Found' : 'Missing',
                        effectiveFront: effectiveFront ? 'Using: ' + effectiveFront.document_name : 'Missing'
                    });
                    
                    profileHtml += `<div class="vstack gap-3">`;
                    
                    // Display ID Front and Back side-by-side
                    if (effectiveFront || idBack) {
                        profileHtml += `
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                                <div class="card-header bg-gradient p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <h6 class="fw-bold mb-0 small d-flex align-items-center gap-2 text-white">
                                        <span class="material-symbols-outlined" style="font-size: 1.2rem;">badge</span>
                                        Valid ID Verification ${profile.id_type ? '(' + profile.id_type + ')' : ''}
                                    </h6>
                                </div>
                                <div class="card-body p-4 bg-light bg-opacity-50">
                                    <div class="row g-3">
                        `;
                        
                        // Front Side (or legacy ID)
                        if (effectiveFront) {
                            const isLegacy = effectiveFront.document_name === 'Valid ID (Government-issued)' || effectiveFront.document_name === 'Valid ID';
                            profileHtml += `
                                <div class="col-md-6">
                                    <div class="bg-white p-3 rounded-4 border border-${isLegacy ? 'warning' : 'success'} border-2 text-center h-100 shadow-sm position-relative">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                            <p class="text-${isLegacy ? 'warning' : 'success'} text-uppercase small fw-bold mb-0" style="font-size: 0.7rem; letter-spacing: 1.5px;">
                                                <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">${isLegacy ? 'warning' : 'check_circle'}</span>
                                                ${isLegacy ? 'ID (Legacy Upload)' : 'Front Side'}
                                            </p>
                                        </div>
                                        <a href="${effectiveFront.file_path}" target="_blank" class="d-block mb-3 position-relative">
                                            <img src="${effectiveFront.file_path}" class="img-fluid rounded-3 shadow" style="height: 160px; object-fit: cover; width: 100%; border: 2px solid #e9ecef;" alt="ID Front" onerror="this.parentElement.innerHTML='<div class=\\'text-danger p-4\\'><span class=\\'material-symbols-outlined\\' style=\\'font-size:48px;\\'>broken_image</span><p class=\\'small mt-2\\'>Image not found</p></div>'">
                                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0); transition: all 0.3s;">
                                                <span class="material-symbols-outlined text-white" style="font-size: 48px; opacity: 0;">visibility</span>
                                            </div>
                                        </a>
                                        <a href="${effectiveFront.file_path}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold">
                                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 16px;">open_in_new</span>
                                            View Full Size
                                        </a>
                                    </div>
                                </div>
                            `;
                        } else {
                            // Show placeholder for missing front
                            profileHtml += `
                                <div class="col-md-6">
                                    <div class="bg-white p-3 rounded-4 border border-danger border-2 border-opacity-50 text-center h-100 shadow-sm">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                            <p class="text-danger text-uppercase small fw-bold mb-0" style="font-size: 0.7rem; letter-spacing: 1.5px;">
                                                <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">error</span>
                                                Front Side
                                            </p>
                                        </div>
                                        <div class="d-flex flex-column align-items-center justify-content-center py-5 my-3">
                                            <span class="material-symbols-outlined text-danger mb-2" style="font-size: 48px; opacity: 0.3;">image_not_supported</span>
                                            <p class="text-muted small mb-0">Not Uploaded</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        
                        // Back Side
                        if (idBack) {
                            profileHtml += `
                                <div class="col-md-6">
                                    <div class="bg-white p-3 rounded-4 border border-success border-2 text-center h-100 shadow-sm position-relative">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                            <p class="text-success text-uppercase small fw-bold mb-0" style="font-size: 0.7rem; letter-spacing: 1.5px;">
                                                <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">check_circle</span>
                                                Back Side
                                            </p>
                                        </div>
                                        <a href="${idBack.file_path}" target="_blank" class="d-block mb-3 position-relative">
                                            <img src="${idBack.file_path}" class="img-fluid rounded-3 shadow" style="height: 160px; object-fit: cover; width: 100%; border: 2px solid #e9ecef;" alt="ID Back" onerror="this.parentElement.innerHTML='<div class=\\'text-danger p-4\\'><span class=\\'material-symbols-outlined\\' style=\\'font-size:48px;\\'>broken_image</span><p class=\\'small mt-2\\'>Image not found</p></div>'">
                                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0); transition: all 0.3s;">
                                                <span class="material-symbols-outlined text-white" style="font-size: 48px; opacity: 0;">visibility</span>
                                            </div>
                                        </a>
                                        <a href="${idBack.file_path}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold">
                                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 16px;">open_in_new</span>
                                            View Full Size
                                        </a>
                                    </div>
                                </div>
                            `;
                        } else {
                            // Show placeholder for missing back
                            profileHtml += `
                                <div class="col-md-6">
                                    <div class="bg-white p-3 rounded-4 border border-danger border-2 border-opacity-50 text-center h-100 shadow-sm">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                            <p class="text-danger text-uppercase small fw-bold mb-0" style="font-size: 0.7rem; letter-spacing: 1.5px;">
                                                <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">error</span>
                                                Back Side
                                            </p>
                                        </div>
                                        <div class="d-flex flex-column align-items-center justify-content-center py-5 my-3">
                                            <span class="material-symbols-outlined text-danger mb-2" style="font-size: 48px; opacity: 0.3;">image_not_supported</span>
                                            <p class="text-muted small mb-0">Not Uploaded</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }

                        const uploadDate = effectiveFront ? effectiveFront.upload_date : (idBack ? idBack.upload_date : '');
                        if (uploadDate) {
                            profileHtml += `
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-3 pt-2 ps-1 border-top">
                                         <span class="material-symbols-outlined text-primary" style="font-size: 18px;">calendar_today</span>
                                        <p class="text-muted small mb-0 fw-medium">Uploaded on ${dateString(uploadDate)}</p>
                                    </div>
                            `;
                        } else {
                            profileHtml += `</div>`;
                        }
                        
                        profileHtml += `
                                </div>
                            </div>
                        `;
                    }
                    
                    // Display other documents
                    if (otherDocs.length > 0) {
                        profileHtml += `
                            <div class="mt-2">
                                <h6 class="text-secondary fw-bold text-uppercase small mb-3 tracking-wide ps-1 d-flex align-items-center gap-2">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">folder_open</span>
                                    Supporting Documents
                                </h6>
                                <div class="vstack gap-2">
                        `;
                        otherDocs.forEach(doc => {
                            profileHtml += `
                                <div class="card border-0 shadow-sm rounded-4 overflow-hidden hover-lift" style="transition: transform 0.2s;">
                                     <div class="card-body p-3 d-flex align-items-center gap-3">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 d-flex align-items-center justify-content-center" style="min-width: 56px; height: 56px;">
                                            <span class="material-symbols-outlined fs-4">description</span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold mb-1 text-dark">${doc.document_name}</h6>
                                            <p class="text-muted small mb-0">
                                                <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">schedule</span>
                                                Uploaded ${dateString(doc.upload_date)}
                                            </p>
                                        </div>
                                        <a href="${doc.file_path}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold">
                                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 16px;">visibility</span>
                                            View
                                        </a>
                                     </div>
                                </div>
                            `;
                        });
                        profileHtml += `
                                </div>
                            </div>
                        `;
                    }
                     profileHtml += `</div>`;
                }
                
                profileHtml += `</div></div>`; // Close col and row
                
                document.getElementById('modalContent').innerHTML = profileHtml;
                
            } catch (err) {
                console.error('Error loading documents:', err);
                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-5">
                        <div class="text-danger mb-3">
                            <span class="material-symbols-outlined" style="font-size: 48px;">error</span>
                        </div>
                        <h5 class="text-danger mb-2">Failed to Load Documents</h5>
                        <p class="text-muted mb-3">Error: ` + err.message + `</p>
                        <button class="btn btn-primary" onclick="viewDocuments(` + clientId + `, '` + clientName.replace(/'/g, "\\'") + `')">Try Again</button>
                    </div>
                `;
            }
        }
        
        function showRejectForm() {
            document.getElementById('actionButtons').style.display = 'none';
            document.getElementById('rejectForm').style.display = 'block';
        }
        
        function hideRejectForm() {
            document.getElementById('rejectForm').style.display = 'none';
             document.getElementById('actionButtons').style.display = 'flex';
        }
    </script>
</body>
</html>

