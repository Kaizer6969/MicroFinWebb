<?php
/**
 * Super Admin Settings Page - Fundline Web Application
 * System settings and configuration for Super Admin
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

$message = '';
$message_type = '';

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (password_verify($current_password, $user['password_hash'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) {
                $new_password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_password_hash, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Password updated successfully";
                    $message_type = "success";
                } else {
                    $message = "Failed to update password";
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "New password must be at least 8 characters";
                $message_type = "error";
            }
        } else {
            $message = "New passwords do not match";
            $message_type = "error";
        }
    } else {
        $message = "Current password is incorrect";
        $message_type = "error";
    }
}

// Get user details
$stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.email, u.user_type, u.status, u.created_at,
           e.first_name, e.last_name, e.department, e.position, e.contact_number
    FROM users u
    LEFT JOIN employees e ON u.user_id = e.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>System Settings - Fundline</title>
    
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
            
            <div class="container-fluid p-4">
                
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-xl-8">
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="material-symbols-outlined filled">
                                        <?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?>
                                    </span>
                                    <?php echo htmlspecialchars($message); ?>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- System Config and Admin Links -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary text-white overflow-hidden">
                            <div class="card-body p-4 position-relative">
                                <div class="position-absolute top-0 end-0 p-3 opacity-25">
                                    <span class="material-symbols-outlined" style="font-size: 8rem;">settings_applications</span>
                                </div>
                                <h4 class="fw-bold mb-2 text-white">System Configuration</h4>
                                <p class="text-white-50 mb-4">Advanced settings and user management</p>
                                <div class="d-flex gap-3 position-relative" style="z-index: 2;">
                                    <a href="admin_manage_admins.php" class="btn btn-light text-primary fw-bold px-4 py-2 border-0 shadow-sm">
                                        <span class="material-symbols-outlined align-middle me-1">admin_panel_settings</span>
                                        Manage Admins
                                    </a>
                                    <a href="admin_audit_trail.php" class="btn btn-outline-light px-4 py-2 fw-bold">
                                        <span class="material-symbols-outlined align-middle me-1">history</span>
                                        Audit Logs
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Information -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h5 class="fw-bold mb-1">Super Admin Profile</h5>
                                <p class="text-muted small">Your account details and information</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-0">
                                    <div class="col-md-3 text-center mb-4 mb-md-0 border-end border-light-subtle pe-md-4">
                                        <div class="d-inline-flex align-items-center justify-content-center bg-danger text-white rounded-circle mb-3 shadow-sm" style="width: 100px; height: 100px; font-size: 2.5rem; font-weight: bold;">
                                            <?php echo $avatar_letter; ?>
                                        </div>
                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? '')); ?></h5>
                                        <p class="text-muted small mb-2 text-uppercase fw-bold"><?php echo htmlspecialchars($user_data['position'] ?? 'Super Admin'); ?></p>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><?php echo htmlspecialchars($user_data['status']); ?></span>
                                    </div>
                                    
                                    <div class="col-md-9 ps-md-4">
                                        <div class="row g-3">
                                            <div class="col-sm-6">
                                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Username</small>
                                                <p class="fw-medium mb-0"><?php echo htmlspecialchars($user_data['username']); ?></p>
                                            </div>
                                            <div class="col-sm-6">
                                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Email Address</small>
                                                <p class="fw-medium mb-0 text-break"><?php echo htmlspecialchars($user_data['email']); ?></p>
                                            </div>
                                            <div class="col-sm-6">
                                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Department</small>
                                                <p class="fw-medium mb-0"><?php echo htmlspecialchars($user_data['department'] ?? 'System Admin'); ?></p>
                                            </div>
                                            <div class="col-sm-6">
                                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Contact Number</small>
                                                <p class="fw-medium mb-0"><?php echo htmlspecialchars($user_data['contact_number'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div class="col-sm-6">
                                                <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Member Since</small>
                                                <p class="fw-medium mb-0"><?php echo date('F d, Y', strtotime($user_data['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-4">
                            <!-- Change Password -->
                            <div class="col-lg-7">
                                <div class="card border-0 shadow-sm rounded-4 h-100">
                                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                        <h5 class="fw-bold mb-1">Security</h5>
                                        <p class="text-muted small">Update your account password</p>
                                    </div>
                                    <div class="card-body p-4">
                                        <form method="POST" action="">
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-muted">Current Password</label>
                                                <input type="password" name="current_password" class="form-control" required>
                                            </div>
                                            
                                            <div class="row g-3 mb-3">
                                                <div class="col-sm-6">
                                                    <label class="form-label small fw-bold text-muted">New Password</label>
                                                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                                                </div>
                                                <div class="col-sm-6">
                                                    <label class="form-label small fw-bold text-muted">Confirm Password</label>
                                                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" name="update_password" class="btn btn-primary">
                                                    <span class="material-symbols-outlined align-middle me-1">lock_reset</span>
                                                    Update Password
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sync theme switch with global theme
        document.addEventListener('DOMContentLoaded', () => {
            const themeSwitch = document.getElementById('themeSwitch');
            const currentTheme = localStorage.getItem('theme') || 'light';
            
            if (themeSwitch) {
                themeSwitch.checked = currentTheme === 'dark';
                
                themeSwitch.addEventListener('change', function() {
                    const newTheme = this.checked ? 'dark' : 'light';
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    
                    // Update global toggle icon if it exists
                    const globalIcon = document.querySelector('#themeToggle .material-symbols-outlined');
                    if (globalIcon) {
                        globalIcon.textContent = newTheme === 'dark' ? 'light_mode' : 'dark_mode';
                    }
                });
            }
            
            // Listen for global theme changes to update switch
            const themeObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === 'data-bs-theme') {
                        const newTheme = document.documentElement.getAttribute('data-bs-theme');
                        if (themeSwitch) themeSwitch.checked = newTheme === 'dark';
                    }
                });
            });
            
            themeObserver.observe(document.documentElement, { attributes: true });
        });
    </script>
</body>
</html>

