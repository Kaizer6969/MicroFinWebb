<?php
/**
 * Super Admin Operations Page - Fundline Web Application
 * System operations dashboard for Super Admin
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Redirect if not Super Admin
if ($_SESSION['role_name'] !== 'Super Admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Include database connection
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$avatar_letter = strtoupper(substr($username, 0, 1));

// Get system statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Active users (logged in within last 30 days)
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['active_users'] = $result->fetch_assoc()['total'];

// Total employees
$result = $conn->query("SELECT COUNT(*) as total FROM employees");
$stats['total_employees'] = $result->fetch_assoc()['total'];

// Total clients
$result = $conn->query("SELECT COUNT(*) as total FROM clients");
$stats['total_clients'] = $result->fetch_assoc()['total'];

// Total loan applications
$result = $conn->query("SELECT COUNT(*) as total FROM loan_applications");
$stats['total_applications'] = $result->fetch_assoc()['total'];

// Active loans
$result = $conn->query("SELECT COUNT(*) as total FROM loans WHERE loan_status = 'Active'");
$stats['active_loans'] = $result->fetch_assoc()['total'];

// Database size (approximate)
$result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE()");
$stats['db_size'] = $result->fetch_assoc()['size_mb'] ?? 0;

// Recent audit logs
$recent_logs = [];
$stmt = $conn->prepare("
    SELECT l.*, u.username 
    FROM audit_logs l 
    LEFT JOIN users u ON l.user_id = u.user_id 
    ORDER BY l.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_logs[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>System Operations - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <!-- Inline styles removed to rely on main_style.css for consistency -->
</head>
<body class="light">
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="d-flex">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'admin_header.php'; ?>
            
            <div class="content-area">
                <!-- Title section removed -->
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['active_users']); ?></div>
                        <div class="stat-label">Active Users (30 days)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_employees']); ?></div>
                        <div class="stat-label">Total Employees</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_clients']); ?></div>
                        <div class="stat-label">Total Clients</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_applications']); ?></div>
                        <div class="stat-label">Loan Applications</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['active_loans']); ?></div>
                        <div class="stat-label">Active Loans</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['db_size']; ?> MB</div>
                        <div class="stat-label">Database Size</div>
                    </div>
                </div>

                <div class="section-card mb-4">
                    <h3 class="h5 fw-bold text-main mb-3">Recent System Activity</h3>
                    
                    <div class="table-container mb-0">
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
                                <?php if (count($recent_logs) > 0): ?>
                                    <?php foreach ($recent_logs as $log): ?>
                                        <tr>
                                            <td class="ps-4 text-nowrap text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle text-muted" style="width: 24px; height: 24px; font-size: 12px; font-weight: bold;">
                                                        <?php echo strtoupper(substr($log['username'] ?? '?', 0, 1)); ?>
                                                    </div>
                                                    <span class="fw-semibold text-main">
                                                        <?php echo htmlspecialchars($log['username'] ?? 'System/Unknown'); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $action = $log['action_type'];
                                                $badgeClass = 'badge-secondary';
                                                if ($action == 'LOGIN') $badgeClass = 'badge-info';
                                                elseif ($action == 'CREATE_ADMIN') $badgeClass = 'badge-success';
                                                elseif ($action == 'UPDATE_STATUS') $badgeClass = 'badge-warning';
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?> rounded-pill fw-normal px-3">
                                                    <?php echo htmlspecialchars($action); ?>
                                                </span>
                                            </td>
                                            <td class="pe-4 text-muted">
                                                <?php echo htmlspecialchars($log['description']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="table-empty">
                                            No recent activity found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="section-card">
                    <h3 class="h5 fw-bold text-main mb-3">Quick Actions</h3>
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <a href="admin_dashboard.php" class="btn btn-light w-100 py-3 d-flex align-items-center justify-content-center gap-2 shadow-sm border">
                                <span class="material-symbols-outlined text-primary">launch</span>
                                Operations Dashboard
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a href="admin_audit_trail.php" class="btn btn-light w-100 py-3 d-flex align-items-center justify-content-center gap-2 shadow-sm border">
                                <span class="material-symbols-outlined text-primary">history</span>
                                View Full Audit Trail
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a href="admin_manage_admins.php" class="btn btn-light w-100 py-3 d-flex align-items-center justify-content-center gap-2 shadow-sm border">
                                <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
                                Manage Administrators
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>


</body>
</html>

