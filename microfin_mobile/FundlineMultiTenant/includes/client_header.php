<?php
/**
 * Client Header Component
 * Reusable header for all client pages
 */

// Get current page name for title
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Dashboard';
$page_subtitle = 'Welcome back';

switch ($current_page) {
    case 'dashboard.php':
        $page_title = 'Dashboard';
        $page_subtitle = 'Overview of your account';
        break;
    case 'apply_loan.php':
        $page_title = 'Apply for Loan';
        $page_subtitle = 'Submit a new loan application';
        break;
    case 'my_loans.php':
        $page_title = 'My Loans';
        $page_subtitle = 'Track your active and past loans';
        break;
    case 'my_payments.php':
        $page_title = 'My Payments';
        $page_subtitle = 'View your payment history';
        break;
    case 'manage_profile.php':
        $page_title = 'My Profile';
        $page_subtitle = 'Manage your personal information';
        break;
    case 'help.php':
        $page_title = 'Help & Support';
        $page_subtitle = 'Frequently asked questions';
        break;
        $page_title = 'Fundline';
        $page_subtitle = 'Micro Financing Services';
}

require_once 'notification_helper.php';
$notif_count = 0;
$notifications = [];
if(isset($_SESSION['user_id'])) {
    $notif_count = getUnreadNotificationCount($conn, $_SESSION['user_id']);
    // Fetch top 5
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $notifications[] = $r;
    $stmt->close();
}
?>

<header class="top-bar">
    <div class="top-bar-left">
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

        
        <!-- Help / Tour Button -->
        <button class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center border-0 text-muted me-2" type="button" onclick="startPageTour()" title="Start Tour" style="width: 40px; height: 40px;">
            <span class="material-symbols-outlined">help</span>
        </button>

        <!-- Notifications -->
        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center border-0 text-muted position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
                <span class="material-symbols-outlined">notifications</span>
                <?php if($notif_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 18px; height: 18px; font-size: 10px; top: 5px !important; left: 35px !important;">
                    <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2 p-0 overflow-hidden" style="width: 320px; background-color: white !important;">
                <li class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Notifications</h6>
                     <?php if($notif_count > 0): ?>
                    <span class="badge bg-primary rounded-pill"><?php echo $notif_count; ?> New</span>
                    <?php endif; ?>
                </li>
                <div style="max-height: 350px; overflow-y: auto;">
                <?php if(empty($notifications)): ?>
                    <li class="p-4 text-center text-muted">
                        <span class="material-symbols-outlined fs-2 mb-2 text-secondary opacity-50">notifications_off</span>
                        <p class="mb-0 small">No notifications yet</p>
                    </li>
                <?php else: ?>
                    <?php foreach($notifications as $notif): ?>
                        <li>
                            <a class="dropdown-item d-flex gap-3 p-3 border-bottom <?php echo $notif['is_read'] ? '' : 'bg-body-secondary bg-opacity-10'; ?>" href="<?php echo $notif['link'] ? htmlspecialchars($notif['link']) : '#'; ?>">
                                <?php
                                    $icon = 'info';
                                    $bg_class = 'bg-primary text-primary';
                                    if(strpos($notif['notification_type'], 'Approved') !== false) { $icon = 'check_circle'; $bg_class = 'bg-success text-success'; }
                                    if(strpos($notif['notification_type'], 'Rejected') !== false) { $icon = 'cancel'; $bg_class = 'bg-danger text-danger'; }
                                    if(strpos($notif['notification_type'], 'Payment') !== false) { $icon = 'payments'; $bg_class = 'bg-success text-success'; }
                                    if(strpos($notif['notification_type'], 'Document') !== false) { $icon = 'description'; $bg_class = 'bg-warning text-warning'; }
                                ?>
                                <div class="<?php echo $bg_class; ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                    <span class="material-symbols-outlined fs-6"><?php echo $icon; ?></span>
                                </div>
                                <div>
                                    <strong class="d-block small mb-0 text-dark"><?php echo htmlspecialchars($notif['title']); ?></strong>
                                    <p class="mb-1 small text-muted text-wrap" style="line-height: 1.3; margin-top: 2px;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <span class="text-xs text-muted" style="font-size: 0.65rem;"><?php echo time_elapsed_string($notif['created_at']); ?></span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
                <li><a class="dropdown-item text-center small text-primary fw-bold py-2 bg-light" href="notifications.php">View all notifications</a></li>
            </ul>
        </div>

        <!-- User Profile (Mobile/Tablet only, Desktop is in sidebar) -->
        <div class="d-md-none ms-2">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                        <?php echo isset($avatar_letter) ? $avatar_letter : 'U'; ?>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2" style="background-color: white !important;">
                    <li><a class="dropdown-item" href="manage_profile.php">Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Sign out</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>

<script>
    // Include the theme toggle logic if not already present in main script
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

