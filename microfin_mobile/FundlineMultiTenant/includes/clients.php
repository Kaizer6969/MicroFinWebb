<?php
/**
 * Admin Clients Management Page - Multi-Tenant Web Application
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

$user_id   = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$username  = $_SESSION['username'];
$role_name = $_SESSION['role_name'] ?? 'Employee';
$avatar_letter = strtoupper(substr($username, 0, 1));

$status_filter = $_GET['status'] ?? 'all';
$search        = $_GET['search'] ?? '';

// Build WHERE (always includes tenant_id)
$where_conditions = ["c.tenant_id = ?"];
$params = [$tenant_id];
$types  = 'i';

if ($status_filter !== 'all') {
    $where_conditions[] = "c.client_status = ?";
    $params[] = $status_filter;
    $types   .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(c.client_code LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR c.contact_number LIKE ?)";
    $sp = "%$search%";
    $params = array_merge($params, [$sp, $sp, $sp, $sp, $sp]);
    $types .= 'sssss';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset   = ($page - 1) * $per_page;

// Count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM clients c $where_clause");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_clients = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_clients / $per_page);

// Fetch clients list (add LIMIT/OFFSET params)
$list_params  = array_merge($params, [$per_page, $offset]);
$list_types   = $types . 'ii';

$query = "
    SELECT
        c.client_id,
        c.client_code,
        c.first_name,
        c.middle_name,
        c.last_name,
        c.contact_number,
        c.email_address,
        c.present_city,
        c.present_province,
        c.employment_status,
        c.occupation,
        c.monthly_income,
        c.client_status,
        c.registration_date,
        COUNT(DISTINCT la.application_id) as total_applications,
        COUNT(DISTINCT l.loan_id) as total_loans,
        COALESCE(SUM(CASE WHEN l.loan_status IN ('Active', 'Overdue') THEN l.remaining_balance ELSE 0 END), 0) as outstanding_balance
    FROM clients c
    LEFT JOIN loan_applications la ON c.client_id = la.client_id
    LEFT JOIN loans l ON c.client_id = l.client_id
    $where_clause
    GROUP BY c.client_id
    ORDER BY c.registration_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$clients_result = $stmt->get_result();
$stmt->close();

// Stats (scoped to tenant)
$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*) as total_clients,
        SUM(CASE WHEN client_status = 'Active'      THEN 1 ELSE 0 END) as active_clients,
        SUM(CASE WHEN client_status = 'Inactive'    THEN 1 ELSE 0 END) as inactive_clients,
        SUM(CASE WHEN client_status = 'Blacklisted' THEN 1 ELSE 0 END) as blacklisted_clients
    FROM clients
    WHERE tenant_id = ?
");
$stats_stmt->bind_param("i", $tenant_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Clients - <?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Fundline'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>.stats-card { transition: transform 0.2s; } .stats-card:hover { transform: translateY(-5px); }</style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include 'admin_header.php'; ?>
            <div class="content-area">
                <div class="row g-4 mb-5">
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-blue h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">people</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['total_clients']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Total Clients</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-green h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">check_circle</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['active_clients']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Active Clients</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-orange h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">person_off</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['inactive_clients']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Inactive Clients</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card-modern card-red h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">block</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['blacklisted_clients']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Blacklisted</p>
                        </div>
                    </div>
                </div>

                <div class="filters-section">
                    <form method="GET" action="" class="filters-row">
                        <div class="filter-group">
                            <label class="form-label small text-secondary fw-bold" for="status">Filter by Status</label>
                            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                                <option value="all"         <?php echo $status_filter === 'all'         ? 'selected' : ''; ?>>All Status</option>
                                <option value="Active"      <?php echo $status_filter === 'Active'      ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive"    <?php echo $status_filter === 'Inactive'    ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Blacklisted" <?php echo $status_filter === 'Blacklisted' ? 'selected' : ''; ?>>Blacklisted</option>
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
                                       placeholder="Name, code, or contact number">
                            </div>
                        </div>
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">Apply Filter</button>
                        </div>
                        <?php if ($status_filter !== 'all' || !empty($search)): ?>
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <a href="clients.php" class="btn btn-outline-primary rounded-pill px-4">Clear</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-main">Client Directory</h5>
                            <p class="text-muted small mb-0">Manage client profiles and information</p>
                        </div>
                    </div>
                    <div class="table-container border-0 shadow-none mb-0">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">Client</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Financials</th>
                                    <th>History</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($clients_result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="7" class="table-empty">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="material-symbols-outlined fs-1 opacity-25 mb-2">people</span>
                                            <p class="mb-0">No clients found matching your criteria</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php while ($client = $clients_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-main"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($client['client_code']); ?></div>
                                            <div class="small text-secondary fst-italic"><?php echo htmlspecialchars($client['occupation'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td>
                                            <div class="small text-dark"><?php echo htmlspecialchars($client['contact_number']); ?></div>
                                            <?php if ($client['email_address']): ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($client['email_address']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $location = [];
                                            if ($client['present_city'])     $location[] = $client['present_city'];
                                            if ($client['present_province']) $location[] = $client['present_province'];
                                            ?>
                                            <div class="small text-dark text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars(implode(', ', $location)); ?>">
                                                <?php echo htmlspecialchars(implode(', ', $location) ?: 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small text-muted">Income: ₱<?php echo number_format($client['monthly_income'] ?? 0, 2); ?></div>
                                            <?php if ($client['outstanding_balance'] > 0): ?>
                                                <div class="small text-danger fw-bold">Due: ₱<?php echo number_format($client['outstanding_balance'], 2); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <span class="badge badge-secondary" title="Applications">
                                                    <span class="material-symbols-outlined" style="font-size: 14px;">assignment</span>
                                                    <?php echo $client['total_applications']; ?>
                                                </span>
                                                <span class="badge badge-secondary" title="Loans">
                                                    <span class="material-symbols-outlined" style="font-size: 14px;">credit_card</span>
                                                    <?php echo $client['total_loans']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = 'badge-secondary';
                                            if ($client['client_status'] === 'Active')      $statusClass = 'badge-success';
                                            if ($client['client_status'] === 'Inactive')    $statusClass = 'badge-warning';
                                            if ($client['client_status'] === 'Blacklisted') $statusClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?> rounded-pill fw-normal px-3">
                                                <?php echo htmlspecialchars($client['client_status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="action-buttons justify-content-end">
                                                <a href="view_client.php?id=<?php echo $client['client_id']; ?>" class="btn-action-view">View</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center py-4">
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
