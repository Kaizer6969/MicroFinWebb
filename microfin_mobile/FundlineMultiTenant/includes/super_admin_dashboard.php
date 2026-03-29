<?php
/**
 * Super Admin Dashboard - Multi-Tenant Web Application
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role_name'] !== 'Super Admin') {
    header("Location: admin_dashboard.php");
    exit();
}

require_once '../config/db.php';

$username  = $_SESSION['username'];
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$avatar_letter = strtoupper(substr($username, 0, 1));

// System Stats (scoped to tenant)
$stats = [];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$stats['total_users'] = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE user_type = 'Employee' AND tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$stats['admin_users'] = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM audit_logs WHERE DATE(created_at) = CURDATE() AND tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$stats['today_logs'] = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Super Admin Dashboard - <?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Fundline'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include 'admin_header.php'; ?>
            <div class="content-area">
                <div class="mb-4">
                    <h2 class="h3 fw-bold text-main mb-1">Welcome Back, <?php echo htmlspecialchars($username); ?></h2>
                    <p class="text-muted">System Status &amp; Overview &mdash; <?php echo htmlspecialchars($_SESSION['tenant_name'] ?? ''); ?></p>
                </div>

                <h3 class="h6 fw-bold text-muted text-uppercase mb-3 ls-1">Overview</h3>
                <div class="row g-4 mb-5">
                    <div class="col-sm-6 col-xl-4">
                        <div class="stat-card-modern card-blue h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">group</span>
                                </div>
                                <span class="material-symbols-outlined text-white opacity-50">arrow_outward</span>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['total_users']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Total Users</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="stat-card-modern card-green h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">admin_panel_settings</span>
                                </div>
                                <span class="material-symbols-outlined text-white opacity-50">arrow_outward</span>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['admin_users']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Admin Users</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="stat-card-modern card-orange h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="stat-icon-box" style="background: rgba(255,255,255,0.2);">
                                    <span class="material-symbols-outlined text-white">event</span>
                                </div>
                                <span class="material-symbols-outlined text-white opacity-50">trending_up</span>
                            </div>
                            <h3 class="fw-bold mb-1 display-6"><?php echo number_format($stats['today_logs']); ?></h3>
                            <p class="text-white opacity-75 small mb-0 fw-medium">Today's Events</p>
                        </div>
                    </div>
                </div>

                <h3 class="h6 fw-bold text-muted text-uppercase mb-3 ls-1">Quick Actions</h3>
                <div class="row g-4">
                    <div class="col-md-6 col-lg-4">
                        <a href="create_admin.php" class="text-decoration-none">
                            <div class="card border-0 shadow-sm rounded-4 h-100 hover-lift" style="background: linear-gradient(135deg, #ec1313 0%, #b30f0f 100%);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="bg-white bg-opacity-25 rounded-3 p-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                            <span class="material-symbols-outlined text-white fs-2">person_add</span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold text-white mb-2">Add New Admin</h5>
                                            <p class="text-white opacity-75 mb-0 small">Create a new system administrator account</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="admin_audit_trail.php" class="text-decoration-none">
                            <div class="card border-0 shadow-sm rounded-4 h-100 hover-lift" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="bg-white bg-opacity-25 rounded-3 p-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                            <span class="material-symbols-outlined text-white fs-2">history</span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold text-white mb-2">Audit Logs</h5>
                                            <p class="text-white opacity-75 mb-0 small">View complete system activity history</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="admin_manage_admins.php" class="text-decoration-none">
                            <div class="card border-0 shadow-sm rounded-4 h-100 hover-lift" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="bg-white bg-opacity-25 rounded-3 p-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                            <span class="material-symbols-outlined text-white fs-2">manage_accounts</span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold text-white mb-2">Manage Admins</h5>
                                            <p class="text-white opacity-75 mb-0 small">Update admin accounts and statuses</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
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
