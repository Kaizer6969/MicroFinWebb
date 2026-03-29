<?php
/**
 * Admin/Employee Dashboard Page - Fundline Multi-Tenant Web Application
 * Protected page requiring authentication for admin/employee users
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

// Get user & tenant information
$user_id   = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$username  = $_SESSION['username'];
$email     = $_SESSION['email'];
$role_name = $_SESSION['role_name'] ?? 'User';
$user_type = $_SESSION['user_type'];

$avatar_letter = strtoupper(substr($username, 0, 1));

// Get employee_id (scoped to tenant)
$stmt = $conn->prepare("SELECT employee_id, first_name, last_name FROM employees WHERE user_id = ? AND tenant_id = ?");
$stmt->bind_param("ii", $user_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$employee_data = $result->fetch_assoc();
$stmt->close();

if (!$employee_data) {
    $employee_id = null;
    $employee_full_name = $username;
} else {
    $employee_id = $employee_data['employee_id'];
    $employee_full_name = $employee_data['first_name'] . ' ' . $employee_data['last_name'];
}

// Get statistics (scoped to tenant)
$stmt = $conn->prepare("
    SELECT
        (SELECT COUNT(*) FROM loan_applications WHERE application_status = 'Submitted' AND tenant_id = ?) as pending_apps,
        (SELECT COUNT(*) FROM loans WHERE loan_status = 'Active' AND tenant_id = ?) as active_loans,
        (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE() AND tenant_id = ?) as today_collections,
        (SELECT COUNT(*) FROM clients WHERE client_status = 'Active' AND tenant_id = ?) as active_clients
");
$stmt->bind_param("iiii", $tenant_id, $tenant_id, $tenant_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

$pending_applications = $stats['pending_apps']      ?? 0;
$active_loans         = $stats['active_loans']       ?? 0;
$today_collections    = number_format($stats['today_collections'] ?? 0, 2);
$active_clients       = $stats['active_clients']     ?? 0;

// Get recent applications (scoped to tenant)
$recent_apps = [];
$stmt = $conn->prepare("
    SELECT la.application_id, la.application_number, la.application_status, la.created_at,
           c.first_name, c.last_name, c.client_code,
           lp.product_name
    FROM loan_applications la
    JOIN clients c ON la.client_id = c.client_id
    JOIN loan_products lp ON la.product_id = lp.product_id
    WHERE la.application_status = 'Submitted'
      AND la.tenant_id = ?
    ORDER BY la.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_apps[] = $row;
}
$stmt->close();

$conn->close();

function getStatusBadgeClass($status) {
    $classes = [
        'Submitted'    => 'bg-info bg-opacity-10 text-info',
        'Under Review' => 'bg-warning bg-opacity-10 text-warning',
        'Approved'     => 'bg-success bg-opacity-10 text-success',
        'Rejected'     => 'bg-danger bg-opacity-10 text-danger',
    ];
    return $classes[$status] ?? 'bg-secondary bg-opacity-10 text-secondary';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Dashboard - <?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Fundline'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    <style>
        .welcome-card {
            background: linear-gradient(135deg, #ec1313 0%, #b30f0f 100%);
            color: white;
            border: none;
        }
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
                <!-- Welcome Card -->
                <div class="card welcome-card mb-4 shadow-lg">
                    <div class="card-body p-4 p-md-5 d-flex align-items-center justify-content-between position-relative overflow-hidden">
                        <div class="position-relative z-1">
                            <h2 class="h3 fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($employee_full_name); ?>!</h2>
                            <p class="text-white-50 mb-0" style="max-width: 500px;">
                                Here's what's happening today in the system.
                            </p>
                        </div>
                        <div class="d-none d-md-block ms-4 opacity-50">
                            <span class="material-symbols-outlined" style="font-size: 8rem;">admin_panel_settings</span>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <h3 class="h6 fw-bold text-muted text-uppercase mb-3 ls-1">Overview</h3>
                <div class="row g-4 mb-5">
                    <!-- Pending Applications -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-orange h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">description</span>
                                </div>
                                <span class="material-symbols-outlined text-white opacity-50">arrow_outward</span>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo $pending_applications; ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Pending Applications</p>
                        </div>
                    </div>
                    
                    <!-- Active Loans -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-blue h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">account_balance_wallet</span>
                                </div>
                                <span class="material-symbols-outlined text-white opacity-50">arrow_outward</span>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo $active_loans; ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Active Loans</p>
                        </div>
                    </div>
                    
                    <!-- Today's Collections -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-green h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">payments</span>
                                </div>
                                <span class="material-symbols-outlined text-white opacity-50">trending_up</span>
                            </div>
                            <h3 class="fw-bold mb-1 h3">₱<?php echo $today_collections; ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Today's Collections</p>
                        </div>
                    </div>
                    
                    <!-- Active Clients -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-red h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">group</span>
                                </div>
                                <span class="material-symbols-outlined text-white opacity-50">arrow_outward</span>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo $active_clients; ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Active Clients</p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Applications -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-main">Recent Applications</h5>
                        <a href="admin_applications.php" class="action-button">
                            View All <span class="material-symbols-outlined fs-6">arrow_forward</span>
                        </a>
                    </div>
                    
                    <div class="table-container border-0 shadow-none mb-0">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">Applicant</th>
                                    <th>Application No.</th>
                                    <th>Product</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_apps)): ?>
                                    <tr>
                                        <td colspan="6" class="table-empty">
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="material-symbols-outlined fs-1 opacity-25 mb-2">inbox</span>
                                                <p class="mb-0">No recent applications found.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_apps as $app): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex flex-column">
                                                    <span class="fw-semibold text-main"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></span>
                                                    <span class="small text-muted"><?php echo htmlspecialchars($app['client_code']); ?></span>
                                                </div>
                                            </td>
                                            <td class="fw-medium text-main"><?php echo htmlspecialchars($app['application_number']); ?></td>
                                            <td><?php echo htmlspecialchars($app['product_name']); ?></td>
                                            <td class="text-muted"><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                    $statusClass = 'badge-secondary';
                                                    switch($app['application_status']) {
                                                        case 'Submitted':   $statusClass = 'badge-info'; break;
                                                        case 'Under Review':$statusClass = 'badge-warning'; break;
                                                        case 'Approved':    $statusClass = 'badge-success'; break;
                                                        case 'Rejected':    $statusClass = 'badge-danger'; break;
                                                    }
                                                ?>
                                                <span class="badge rounded-pill <?php echo $statusClass; ?> px-3 py-1 fw-normal">
                                                    <?php echo htmlspecialchars($app['application_status']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="view_application.php?id=<?php echo $app['application_id']; ?>" class="btn-action-view">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</body>
</html>
