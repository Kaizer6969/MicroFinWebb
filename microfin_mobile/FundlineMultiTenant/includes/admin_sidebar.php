<?php
/**
 * Admin/Employee Sidebar Component
 * Reusable sidebar for admin/employee users
 */

// Get user info if not already set
if (!isset($username)) {
    $username = $_SESSION['username'] ?? 'Admin';
}
if (!isset($avatar_letter)) {
    $avatar_letter = strtoupper(substr($username, 0, 1));
}
if (!isset($role_name)) {
    $role_name = $_SESSION['role_name'] ?? 'Employee';
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar d-flex flex-column" id="sidebar">
    <div class="sidebar-header">
        <a href="admin_dashboard.php" class="text-decoration-none d-flex flex-column lh-1">
            <span class="d-flex align-items-center" style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.75rem; letter-spacing: -1.5px; color: var(--color-primary);">
                fundline
            </span>
            <span style="font-family: 'Outfit', sans-serif; font-weight: 400; font-size: 0.5rem; letter-spacing: 1.5px; color: var(--color-text-muted); text-transform: uppercase; margin-left: 2px;">
                Finance Corporation
            </span>
        </a>
    </div>
    
    <div class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="menu-header small text-uppercase text-muted fw-bold px-4 mb-2 mt-2" style="font-size: 0.7rem; letter-spacing: 1px;">Menu</li>
            <li class="mb-1">
                <a href="<?php echo $role_name === 'Super Admin' ? 'super_admin_dashboard.php' : 'admin_dashboard.php'; ?>" class="sidebar-menu-link <?php echo ($current_page === 'dashboard.php' || $current_page === 'admin_dashboard.php' || $current_page === 'super_admin_dashboard.php') ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">dashboard</span>
                    Dashboard
                </a>
            </li>
            <li class="mb-1">
                <a href="admin_applications.php" class="sidebar-menu-link <?php echo ($current_page === 'admin_applications.php' || $current_page === 'process_application.php' || $current_page === 'view_application.php') ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">description</span>
                    Applications
                </a>
            </li>
            <li class="mb-1">
                <a href="disbursement.php" class="sidebar-menu-link <?php echo $current_page === 'disbursement.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">account_balance</span>
                    Disbursement
                </a>
            </li>
            <li class="mb-1">
                <a href="admin_loans.php" class="sidebar-menu-link <?php echo ($current_page === 'admin_loans.php' || $current_page === 'view_loan.php') ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                    Loans
                </a>
            </li>
            <li class="mb-1">
                <a href="clients.php" class="sidebar-menu-link <?php echo ($current_page === 'clients.php' || $current_page === 'view_client.php') ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">group</span>
                    Clients
                </a>
            </li>
            <li class="mb-1">
                <a href="payment.php" class="sidebar-menu-link <?php echo ($current_page === 'payment.php' || $current_page === 'admin_payments.php') ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">payments</span>
                    Payments
                </a>
            </li>
            <li class="mb-1">
                <a href="verify_documents.php" class="sidebar-menu-link <?php echo $current_page === 'verify_documents.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">verified_user</span>
                    Verification
                </a>
            </li>
            <li class="mb-1">
                <a href="admin_messages.php" class="sidebar-menu-link <?php echo $current_page === 'admin_messages.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">support_agent</span>
                    Customer Support
                </a>
            </li>

            <li class="mb-1">
                <a href="report.php" class="sidebar-menu-link <?php echo ($current_page === 'report.php' || $current_page === 'admin_reports.php') ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">assessment</span>
                    Reports
                </a>
            </li>
            
            <?php if ($role_name === 'Super Admin'): ?>
            <li class="menu-header small text-uppercase text-muted fw-bold px-4 mb-2 mt-4" style="font-size: 0.7rem; letter-spacing: 1px;">Administration</li>
            <li class="mb-1">
                <a href="admin_manage_admins.php" class="sidebar-menu-link <?php echo ($current_page === 'admin_manage_admins.php' || $current_page === 'create_admin.php') ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                    Admins
                </a>
            </li>
            <li class="mb-1">
                <a href="admin_audit_trail.php" class="sidebar-menu-link <?php echo $current_page === 'admin_audit_trail.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">history</span>
                    Audit Trail
                </a>
            </li>
            <li class="mb-1">
                <a href="super_admin_settings.php" class="sidebar-menu-link <?php echo $current_page === 'super_admin_settings.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">settings_suggest</span>
                    System Settings
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <div class="dropdown w-100">
            <a href="#" class="user-profile text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar text-primary bg-primary bg-opacity-10">
                    <?php echo $avatar_letter; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($role_name); ?></div>
                </div>
            </a>
            <ul class="dropdown-menu shadow-sm border-0 rounded-4 mb-2" style="background-color: white !important;">
                <li><a class="dropdown-item" href="settings.php">Profile</a></li>
                <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php">Sign out</a></li>
            </ul>
        </div>
    </div>
</aside>

