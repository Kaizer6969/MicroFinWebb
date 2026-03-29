<?php
/**
 * Audit Trail Page - Multi-Tenant Web Application
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['user_type'] !== 'Employee' || $_SESSION['role_name'] !== 'Super Admin') {
    header("Location: admin_dashboard.php");
    exit();
}

require_once '../config/db.php';

$tenant_id = $_SESSION['tenant_id'] ?? 1;

$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 20;
$offset = ($page - 1) * $limit;

$filter_user   = isset($_GET['user'])   ? trim($_GET['user'])   : '';
$filter_action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Count total (scoped to tenant)
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM audit_logs l
    LEFT JOIN users u ON l.user_id = u.user_id
    WHERE l.tenant_id = ?
      AND (? = '' OR u.username LIKE ?)
      AND (? = '' OR l.action_type = ?)
");
$user_like = "%$filter_user%";
$count_stmt->bind_param("issss", $tenant_id, $filter_user, $user_like, $filter_action, $filter_action);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages   = ceil($total_records / $limit);
$count_stmt->close();

// Fetch logs (scoped to tenant)
$stmt = $conn->prepare("
    SELECT l.*, u.username
    FROM audit_logs l
    LEFT JOIN users u ON l.user_id = u.user_id
    WHERE l.tenant_id = ?
      AND (? = '' OR u.username LIKE ?)
      AND (? = '' OR l.action_type = ?)
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("issssi i", $tenant_id, $filter_user, $user_like, $filter_action, $filter_action, $limit, $offset);
$stmt->bind_param("issssii", $tenant_id, $filter_user, $user_like, $filter_action, $filter_action, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Audit Trail - <?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Fundline'); ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <?php include 'admin_sidebar.php'; ?>
        <div class="main-content flex-grow-1">
            <?php include 'admin_header.php'; ?>
            <div class="container-fluid p-4">
                <!-- Filters -->
                <div class="filters-section">
                    <form class="filters-row" method="GET">
                        <div class="filter-group">
                            <label class="form-label small text-secondary fw-bold">Search User</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <span class="material-symbols-outlined fs-6">search</span>
                                </span>
                                <input type="text" name="user" class="form-control border-start-0 ps-0" placeholder="Enter username" value="<?php echo htmlspecialchars($filter_user); ?>">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label class="form-label small text-secondary fw-bold">Action Type</label>
                            <select name="action" class="form-select">
                                <option value="">All Actions</option>
                                <option value="LOGIN"         <?php echo $filter_action == 'LOGIN'         ? 'selected' : ''; ?>>Login</option>
                                <option value="LOGOUT"        <?php echo $filter_action == 'LOGOUT'        ? 'selected' : ''; ?>>Logout</option>
                                <option value="REGISTRATION"  <?php echo $filter_action == 'REGISTRATION'  ? 'selected' : ''; ?>>Registration</option>
                                <option value="CREATE_ADMIN"  <?php echo $filter_action == 'CREATE_ADMIN'  ? 'selected' : ''; ?>>Create Admin</option>
                                <option value="UPDATE_STATUS" <?php echo $filter_action == 'UPDATE_STATUS' ? 'selected' : ''; ?>>Update Status</option>
                            </select>
                        </div>
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">Filter</button>
                        </div>
                        <div class="filter-group" style="flex: 0 0 auto;">
                            <a href="admin_audit_trail.php" class="btn btn-outline-primary rounded-pill px-4">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th class="pe-4">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 text-nowrap text-muted"><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle text-muted" style="width: 24px; height: 24px; font-size: 12px; font-weight: bold;">
                                                    <?php echo strtoupper(substr($row['username'] ?? '?', 0, 1)); ?>
                                                </div>
                                                <span class="fw-semibold text-main">
                                                    <?php echo htmlspecialchars($row['username'] ?? 'System/Unknown'); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $action = $row['action_type'];
                                            $badgeClass = 'badge-secondary';
                                            if ($action == 'LOGIN')         $badgeClass = 'badge-info';
                                            elseif ($action == 'CREATE_ADMIN')  $badgeClass = 'badge-success';
                                            elseif ($action == 'UPDATE_STATUS') $badgeClass = 'badge-warning';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> rounded-pill fw-normal px-3">
                                                <?php echo htmlspecialchars($action); ?>
                                            </span>
                                        </td>
                                        <td class="pe-4 text-muted"><?php echo htmlspecialchars($row['description']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="table-empty">No logs found matching your criteria.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
