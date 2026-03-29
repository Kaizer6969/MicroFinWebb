<?php
/**
 * Manage Admins Page - Multi-Tenant Web Application
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

$user_id   = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// Handle Status Toggle (scoped to tenant)
if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $target_user_id = (int)$_POST['user_id'];
    $current_status = $_POST['current_status'];
    $new_status = ($current_status === 'Active') ? 'Suspended' : 'Active';

    if ($target_user_id === $user_id) {
        $error_message = "You cannot suspend your own account.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ? AND tenant_id = ?");
        $stmt->bind_param("sii", $new_status, $target_user_id, $tenant_id);

        if ($stmt->execute()) {
            $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, description, ip_address) VALUES (?, ?, 'UPDATE_STATUS', ?, ?)");
            $desc = "Changed status of user #$target_user_id to $new_status";
            $ip   = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iiss", $user_id, $tenant_id, $desc, $ip);
            $log_stmt->execute();
            $success_message = "User status updated successfully.";
        } else {
            $error_message = "Failed to update status.";
        }
    }
}

// Fetch Admins (scoped to tenant)
$stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.email, u.status, u.last_login,
           e.first_name, e.last_name, e.department, e.position
    FROM users u
    JOIN employees e ON u.user_id = e.user_id
    WHERE u.user_type = 'Employee'
      AND u.tenant_id = ?
    ORDER BY u.created_at DESC
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Manage Admins - <?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Fundline'); ?></title>
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
        <?php include 'admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include 'admin_header.php'; ?>
            <div class="content-area">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined filled">check_circle</span>
                            <?php echo $success_message; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined filled">error</span>
                            <?php echo $error_message; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="filters-section">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-main">Admin Accounts</h5>
                            <p class="text-muted small mb-0">Manage system access and privileges</p>
                        </div>
                        <a href="create_admin.php" class="btn btn-primary d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined">add</span>
                            Add New Admin
                        </a>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Admin</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($admin = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 40px; height: 40px; font-weight: bold; flex-shrink: 0; font-size: 0.9rem;">
                                                    <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-main"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                                                    <div class="small text-muted">@<?php echo htmlspecialchars($admin['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-muted"><?php echo htmlspecialchars($admin['department']); ?></td>
                                        <td class="text-muted"><?php echo htmlspecialchars($admin['position']); ?></td>
                                        <td class="text-muted text-nowrap"><?php echo $admin['last_login'] ? date('M d, Y', strtotime($admin['last_login'])) : 'Never'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $admin['status'] === 'Active' ? 'badge-success' : 'badge-danger'; ?> rounded-pill px-3 py-1 fw-normal">
                                                <?php echo $admin['status']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to change this user\'s status?');">
                                                <input type="hidden" name="user_id" value="<?php echo $admin['user_id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $admin['status']; ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                    <span class="material-symbols-outlined" style="font-size: 1.1rem; vertical-align: middle;">
                                                        <?php echo $admin['status'] == 'Active' ? 'block' : 'check_circle'; ?>
                                                    </span>
                                                    <?php echo $admin['status'] == 'Active' ? 'Suspend' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="table-empty">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="material-symbols-outlined fs-1 opacity-25 mb-2">person_off</span>
                                            <p class="mb-0">No administrators found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
