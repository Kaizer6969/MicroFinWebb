<?php
/**
 * Client/User Sidebar Component
 * Reusable sidebar for client users
 */

// Get user info if not already set
if (!isset($username)) {
    $username = $_SESSION['username'] ?? 'User';
}
if (!isset($avatar_letter)) {
    $avatar_letter = strtoupper(substr($username, 0, 1));
}
if (!isset($user_type)) {
    $user_type = $_SESSION['user_type'] ?? 'Client';
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar d-flex flex-column border-end position-fixed h-100 start-0 top-0 z-3" id="sidebar" style="width: 280px;">
    <div class="sidebar-header">
        <a href="dashboard.php" class="text-decoration-none d-flex flex-column lh-1">
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
                <a href="dashboard.php" class="sidebar-menu-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">dashboard</span>
                    Dashboard
                </a>
            </li>
            <li class="mb-1">
                <a href="apply_loan.php" class="sidebar-menu-link <?php echo $current_page === 'apply_loan.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">description</span>
                    Apply for Loan
                </a>
            </li>
            <li class="mb-1">
                <a href="my_applications.php" class="sidebar-menu-link <?php echo in_array($current_page, ['my_applications.php', 'view_application.php']) ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">folder_open</span>
                    My Applications
                </a>
            </li>
            <li class="mb-1">
                <a href="my_loans.php" class="sidebar-menu-link <?php echo in_array($current_page, ['my_loans.php', 'view_loan.php', 'make_payment.php', 'payment_success.php', 'payment_failed.php']) ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                    My Loans
                </a>
            </li>
            <li class="mb-1">
                <a href="my_payments.php" class="sidebar-menu-link <?php echo $current_page === 'my_payments.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">payments</span>
                    My Payments
                </a>
            </li>
            
            <li class="menu-header small text-uppercase text-muted fw-bold px-4 mb-2 mt-4" style="font-size: 0.7rem; letter-spacing: 1px;">Settings</li>
            <li class="mb-1">
                <a href="manage_profile.php" class="sidebar-menu-link <?php echo $current_page === 'manage_profile.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">person</span>
                    Manage Profile
                </a>
            </li>
            <li class="mb-1">
                <a href="terms.php" class="sidebar-menu-link <?php echo $current_page === 'terms.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">gavel</span>
                    Terms & Conditions
                </a>
            </li>
            <li class="mb-1">
                <a href="help.php" class="sidebar-menu-link <?php echo $current_page === 'help.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">help</span>
                    Help & Support
                </a>
            </li>
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
                    <div class="user-role">Client</div>
                </div>
            </a>
            <ul class="dropdown-menu shadow-sm border-0 rounded-4 mb-2" style="background-color: white !important;">
                <li><a class="dropdown-item" href="manage_profile.php">Profile</a></li>
                <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php">Sign out</a></li>
            </ul>
        </div>
    </div>
</aside>

