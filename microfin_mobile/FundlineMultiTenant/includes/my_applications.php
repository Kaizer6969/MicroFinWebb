<?php
/**
 * Client My Applications Page
 * Displays all loan applications submitted by the client
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
$client_data = $result->fetch_assoc();
$client_id = $client_data['client_id'];
$stmt->close();

// Get all applications
$applications = [];
$stmt = $conn->prepare("
    SELECT la.*, lp.product_name, lp.product_type
    FROM loan_applications la
    JOIN loan_products lp ON la.product_id = lp.product_id
    WHERE la.client_id = ? AND la.application_status != 'Approved'
    ORDER BY la.created_at DESC
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}
$stmt->close();

// Calculate Stats
$stats = [
    'total' => count($applications),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

foreach ($applications as $app) {
    if (in_array($app['application_status'], ['Pending', 'Under Review', 'Document Verification', 'Credit Investigation', 'For Approval'])) {
        $stats['pending']++;
    } elseif ($app['application_status'] == 'Approved') {
        $stats['approved']++;
    } elseif ($app['application_status'] == 'Rejected') {
        $stats['rejected']++;
    }
}

// $conn->close(); // Removed to allow header to access DB

function getStatusBadgeClass($status) {
    $classes = [
        'Draft' => 'badge-secondary',
        'Submitted' => 'badge-info',
        'Under Review' => 'badge-warning',
        'Document Verification' => 'badge-warning',
        'Credit Investigation' => 'badge-warning',
        'For Approval' => 'badge-warning',
        'Approved' => 'badge-success',
        'Rejected' => 'badge-error',
        'Cancelled' => 'badge-secondary',
        'Withdrawn' => 'badge-secondary'
    ];
    return $classes[$status] ?? 'badge-secondary';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Applications - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <style>
        .applications-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }
        
        .application-card {
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
        
        .dark .application-card {
            background-color: var(--color-surface-dark);
            border-color: var(--color-border-dark);
        }
        
        .application-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--color-primary-light);
        }
        
        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
        }
        
        .dark .application-header {
            border-bottom-color: var(--color-border-dark);
        }

        .application-details {
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

    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'user_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            
            <!-- Header -->
            <?php 
            $page_title = "My Applications";
            include 'client_header.php'; 
            ?>
            
            <!-- Page Content -->
            <div class="container-fluid p-4">
                
                <!-- Stats Widgets -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-card card-blue h-100">
                            <div class="stat-header">
                                <div class="stat-icon primary">
                                    <span class="material-symbols-outlined">folder_open</span>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Total Apps</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card card-orange h-100">
                            <div class="stat-header">
                                <div class="stat-icon warning">
                                    <span class="material-symbols-outlined">pending</span>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $stats['pending']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card card-green h-100">
                            <div class="stat-header">
                                <div class="stat-icon success">
                                    <span class="material-symbols-outlined">check_circle</span>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $stats['approved']; ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card card-red h-100">
                            <div class="stat-header">
                                <div class="stat-icon danger">
                                    <span class="material-symbols-outlined">cancel</span>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $stats['rejected']; ?></div>
                            <div class="stat-label">Rejected</div>
                        </div>
                    </div>
                </div>

                <?php if (empty($applications)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <span class="material-symbols-outlined" style="font-size: inherit;">dvr</span>
                        </div>
                        <h2 class="h5 fw-bold mb-2">No Applications Yet</h2>
                        <p class="text-secondary mb-4">You haven't submitted any loan applications.</p>
                        <a href="apply_loan.php" class="btn btn-primary d-inline-flex align-items-center gap-2">
                            <span>Apply for Loan</span>
                            <span class="material-symbols-outlined fs-5">arrow_forward</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="applications-grid">
                        <?php foreach ($applications as $app): ?>
                        <div class="application-card">
                            <div class="application-header">
                                <div>
                                    <h3 class="h6 fw-bold mb-1 text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($app['product_name']); ?></h3>
                                    <p class="small text-secondary mb-0"><?php echo htmlspecialchars($app['application_number']); ?></p>
                                </div>
                                <span class="badge <?php echo getStatusBadgeClass($app['application_status']); ?>">
                                    <?php echo htmlspecialchars($app['application_status']); ?>
                                </span>
                            </div>
                            
                            <div class="application-details">
                                <div class="detail-item">
                                    <span class="detail-label">Amount</span>
                                    <span class="detail-value text-primary fw-bold">₱<?php echo number_format($app['requested_amount'], 2); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Term</span>
                                    <span class="detail-value"><?php echo $app['loan_term_months']; ?> Months</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Rate</span>
                                    <span class="detail-value"><?php echo $app['interest_rate']; ?>%</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Submitted</span>
                                    <span class="detail-value">
                                        <?php echo $app['submitted_date'] ? date('M d, Y', strtotime($app['submitted_date'])) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($app['application_status'] === 'Approved' && !empty($app['approved_amount'])): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-3 small">
                                <span class="material-symbols-outlined fs-6">check_circle</span>
                                <div>
                                    Approved: <strong>₱<?php echo number_format($app['approved_amount'], 2); ?></strong>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($app['application_status'] === 'Rejected' && !empty($app['rejection_reason'])): ?>
                            <div class="alert alert-danger d-flex align-items-start gap-2 py-2 px-3 mb-3 small">
                                <span class="material-symbols-outlined fs-6 mt-1">error</span>
                                <div>
                                    <?php echo htmlspecialchars($app['rejection_reason']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-auto pt-3 border-top border-light-subtle">
                                <a href="view_application.php?id=<?php echo $app['application_id']; ?>" class="btn btn-outline-secondary btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
                                    <span>View Details</span>
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
