<?php
/**
 * Admin Loan Applications Management - Multi-Tenant
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Employee') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

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

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query (scoped to tenant)
$query = "
    SELECT la.*,
           lp.product_name, lp.product_type,
           c.first_name, c.last_name, c.client_code, c.contact_number,
           u.email
    FROM loan_applications la
    JOIN loan_products lp ON la.product_id = lp.product_id
    JOIN clients c ON la.client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    WHERE la.tenant_id = ?
      AND la.application_id NOT IN (SELECT application_id FROM loans WHERE application_id IS NOT NULL)
";

$params = [$tenant_id];
$types  = 'i';

if ($status_filter !== 'all') {
    $query .= " AND la.application_status = ?";
    $params[] = $status_filter;
    $types   .= 's';
}

if (!empty($search)) {
    $query .= " AND (la.application_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.client_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types   .= 'ssss';
}

$query .= " ORDER BY la.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}
$stmt->close();

// Get statistics (scoped to tenant)
$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN application_status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN application_status IN ('Under Review', 'Document Verification', 'Credit Investigation', 'For Approval') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN application_status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM loan_applications
    WHERE tenant_id = ?
");
$stats_stmt->bind_param("i", $tenant_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$conn->close();

function getStatusBadgeClass($status) {
    $classes = [
        'Draft'                  => 'badge-secondary',
        'Submitted'              => 'badge-info',
        'Under Review'           => 'badge-warning',
        'Document Verification'  => 'badge-warning',
        'Credit Investigation'   => 'badge-warning',
        'For Approval'           => 'badge-warning',
        'Approved'               => 'badge-success',
        'Rejected'               => 'badge-error',
        'Cancelled'              => 'badge-secondary',
        'Withdrawn'              => 'badge-secondary'
    ];
    return $classes[$status] ?? 'badge-secondary';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Manage Applications - <?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Fundline'); ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include 'admin_header.php'; ?>
            <div class="content-area">
                <!-- Stats Grid -->
                <div class="row g-4 mb-5">
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-blue h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">description</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo $stats['total']; ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Total Applications</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-orange h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">inbox</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo $stats['submitted']; ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">New Submissions</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-orange h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">pending</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo $stats['pending']; ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Under Review</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-green h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">check_circle</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo $stats['approved']; ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Approved</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="" class="filters-row">
                        <div class="filter-group">
                            <label class="form-label small text-secondary fw-bold" for="status">Filter by Status</label>
                            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="Submitted" <?php echo $status_filter === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="Under Review" <?php echo $status_filter === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="Document Verification" <?php echo $status_filter === 'Document Verification' ? 'selected' : ''; ?>>Document Verification</option>
                                <option value="Credit Investigation" <?php echo $status_filter === 'Credit Investigation' ? 'selected' : ''; ?>>Credit Investigation</option>
                                <option value="For Approval" <?php echo $status_filter === 'For Approval' ? 'selected' : ''; ?>>For Approval</option>
                                <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                                       placeholder="Search by name, code, or app number">
                            </div>
                        </div>
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">Apply Filter</button>
                        </div>
                        <?php if ($status_filter !== 'all' || !empty($search)): ?>
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <a href="admin_applications.php" class="btn btn-outline-primary rounded-pill px-4">Clear</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Applications Table -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-main">Applications List</h5>
                            <p class="text-muted small mb-0">Review user applications</p>
                        </div>
                    </div>
                    <div class="table-container border-0 shadow-none mb-0">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">Application #</th>
                                    <th>Client</th>
                                    <th>Product</th>
                                    <th>Amount</th>
                                    <th>Term</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="8" class="table-empty">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="material-symbols-outlined fs-1 opacity-25 mb-2">inbox</span>
                                            <p class="mb-0">No applications found matching your criteria</p>
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
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold text-main"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></span>
                                            <span class="small text-muted"><?php echo htmlspecialchars($app['client_code']); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="small text-dark"><?php echo htmlspecialchars($app['product_name']); ?></span></td>
                                    <td><span class="fw-bold text-dark">₱<?php echo number_format($app['requested_amount'], 2); ?></span></td>
                                    <td class="text-muted small"><?php echo $app['loan_term_months']; ?> mos</td>
                                    <td class="text-muted small"><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo getStatusBadgeClass($app['application_status']); ?> rounded-pill fw-normal px-3">
                                            <?php echo htmlspecialchars($app['application_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="action-buttons justify-content-end">
                                            <a href="view_application.php?id=<?php echo $app['application_id']; ?>" class="btn-action-view" title="View Details">View</a>
                                            <?php if (!in_array($app['application_status'], ['Approved', 'Rejected'])): ?>
                                            <a href="process_application.php?id=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-1" title="Process Application">
                                                <span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span>
                                                Process
                                            </a>
                                            <?php endif; ?>
                                        </div>
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
