<?php
/**
 * Admin Header Component
 * Reusable header for all Admin and Super Admin pages
 */

// Get current page name for dynamic title
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Administration'; // Default
$page_subtitle = 'Fundline Management System'; // Default

// Map pages to titles
switch ($current_page) {
    // Admin Pages
    case 'admin_dashboard.php':
    case 'dashboard.php':
        $page_title = 'Dashboard';
        $page_subtitle = 'Admin Overview';
        break;
    case 'admin_applications.php':
    case 'process_application.php':
        $page_title = 'Loan Applications';
        $page_subtitle = 'Review and process applications';
        break;
    case 'admin_loans.php':
    case 'view_loan.php':
        $page_title = 'Loan Management';
        $page_subtitle = 'Active and past loans';
        break;
    case 'clients.php':
    case 'view_client.php':
        $page_title = 'Client Management';
        $page_subtitle = 'Manage registered clients';
        break;
    case 'admin_payments.php':
    case 'payment.php':
        $page_title = 'Payments';
        $page_subtitle = 'Track and verify payments';
        break;
    case 'disbursement.php':
        $page_title = 'Disbursement';
        $page_subtitle = 'Manage loan releases';
        break;
    case 'report.php':
    case 'admin_reports.php':
        $page_title = 'Reports';
        $page_subtitle = 'System analytics and reports';
        break;
    case 'verify_documents.php':
        $page_title = 'Document Verification';
        $page_subtitle = 'Verify client documents';
        break;
    
    // Super Admin Pages
    case 'super_admin_dashboard.php':
        $page_title = 'Super Admin Dashboard';
        $page_subtitle = 'System Overview';
        break;
    case 'super_admin_operations.php':
    case 'admin_manage_admins.php':
    case 'create_admin.php':
        $page_title = 'System Operations';
        $page_subtitle = 'Monitor and manage system operations';
        break;
    case 'admin_audit_trail.php':
        $page_title = 'Audit Trail';
        $page_subtitle = 'Track system activities';
        break;
    case 'super_admin_settings.php':
        $page_title = 'System Settings';
        $page_subtitle = 'Global configuration';
        break;
        
    case 'settings.php':
        $page_title = 'Preferences';
        $page_subtitle = 'Personal settings';
        break;
}
?>

<header class="top-bar">
    <div class="top-bar-left">
        <!-- Mobile Menu Toggle -->
        <button class="menu-toggle d-lg-none me-2" id="menuToggle">
            <span class="material-symbols-outlined text-dark">menu</span>
        </button>
        
        <div class="d-flex flex-column">
            <h1 class="h6 fw-bold mb-0 text-main"><?php echo htmlspecialchars($page_title); ?></h1>
            <span class="small text-muted"><?php echo htmlspecialchars($page_subtitle); ?></span>
        </div>
    </div>

    <div class="top-bar-right">
        <!-- Theme Toggle -->
        <!-- Theme Toggle removed per user request -->

        
        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center border-0 text-muted" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
                <span class="material-symbols-outlined">notifications</span>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 10px; height: 10px; top: 10px !important; left: 30px !important;">
                    <span class="visually-hidden">New alerts</span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2 p-0 overflow-hidden" style="width: 320px; background-color: white !important;">
                <li class="p-3 bg-light border-bottom">
                    <h6 class="mb-0 fw-bold">System Alerts</h6>
                </li>
                <li>
                    <a class="dropdown-item d-flex gap-3 p-3 border-bottom bg-white" href="admin_applications.php" style="background-color: white !important;">
                        <div class="bg-light text-dark rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 border" style="width: 36px; height: 36px;">
                            <span class="material-symbols-outlined fs-6">description</span>
                        </div>
                        <div>
                            <p class="mb-1 small fw-medium text-wrap text-dark">New loan application submitted</p>
                            <span class="text-xs text-muted">10 mins ago</span>
                        </div>
                    </a>
                </li>
                <li><a class="dropdown-item text-center small text-danger fw-bold py-2" href="#">View all alerts</a></li>
            </ul>
        </div>
        
        <!-- User Profile (Mobile/Tablet only, Desktop is in sidebar) -->
        <div class="d-md-none ms-2">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
                    <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                        <?php echo isset($avatar_letter) ? $avatar_letter : 'A'; ?>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2" style="background-color: white !important;">
                    <li><a class="dropdown-item" href="settings.php">Preferences</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Sign out</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>

<script>
    // Reuse the same script logic for themes and sidebar toggling
    if (typeof themeToggleScript === 'undefined') {
        const themeToggleScript = true;
        
        document.addEventListener('DOMContentLoaded', () => {
            // Mobile Sidebar Toggle
            
             // Mobile Sidebar Toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('show');
                    if(overlay) overlay.classList.toggle('show');
                });
                
                if(overlay) {
                    overlay.addEventListener('click', () => {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                    });
                }
            }
        });
    }
</script>

