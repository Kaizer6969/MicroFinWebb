<?php
/**
 * Client Dashboard Page - Fundline Multi-Tenant Web Application
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['user_type'] !== 'Client') {
    header("Location: admin_dashboard.php");
    exit();
}

require_once '../config/db.php';

$user_id   = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$username  = $_SESSION['username'];
$email     = $_SESSION['email'];
$role_name = $_SESSION['role_name'] ?? 'User';
$user_type = $_SESSION['user_type'];

// Fetch user details
$stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.email, u.role_id, u.user_type, u.last_login, ur.role_name
    FROM users u
    LEFT JOIN user_roles ur ON u.role_id = ur.role_id
    WHERE u.user_id = ? AND u.tenant_id = ?
");
$stmt->bind_param("ii", $user_id, $tenant_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get client_id and details (scoped to tenant)
$stmt = $conn->prepare("
    SELECT client_id, client_code, first_name, middle_name, last_name, suffix,
           credit_limit, has_seen_tour
    FROM clients
    WHERE user_id = ? AND tenant_id = ?
");
$stmt->bind_param("ii", $user_id, $tenant_id);
$stmt->execute();
$client_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$client_id        = $client_data['client_id']  ?? null;
$client_full_name = ($client_data['first_name'] ?? '') . ' ' . ($client_data['last_name'] ?? '');
$has_seen_tour    = $client_data['has_seen_tour'] ?? false;
$credit_limit     = $client_data['credit_limit']  ?? 0.00;

// Total applications (scoped to tenant)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM loan_applications WHERE client_id = ? AND tenant_id = ?");
$stmt->bind_param("ii", $client_id, $tenant_id);
$stmt->execute();
$total_applications = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total paid (scoped to tenant)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(p.payment_amount), 0) as total_paid
    FROM payments p
    JOIN loans l ON p.loan_id = l.loan_id
    WHERE l.client_id = ?
      AND l.tenant_id = ?
      AND p.payment_status = 'Posted'
");
$stmt->bind_param("ii", $client_id, $tenant_id);
$stmt->execute();
$total_paid = $stmt->get_result()->fetch_assoc()['total_paid'] ?? 0;
$stmt->close();

// Used credit (scoped to tenant)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(outstanding_principal), 0) as used_credit
    FROM loans
    WHERE client_id = ?
      AND tenant_id = ?
      AND loan_status IN ('Active', 'Overdue', 'Restructured')
");
$stmt->bind_param("ii", $client_id, $tenant_id);
$stmt->execute();
$used_credit = $stmt->get_result()->fetch_assoc()['used_credit'] ?? 0;
$stmt->close();

// Active loans count (scoped to tenant)
$active_loans_count = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM loans
    WHERE client_id = ? AND tenant_id = ? AND loan_status IN ('Active', 'Overdue', 'Restructured')
");
$stmt->bind_param("ii", $client_id, $tenant_id);
$stmt->execute();
$active_loans_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();

$remaining_limit = max(0, $credit_limit - $used_credit);

// Loan status display
$loan_status_display = "No Active Loan";
$loan_status_color   = "text-muted";
$loan_status_bg      = "bg-light";
$loan_status_icon    = "check_circle";

$stmt = $conn->prepare("
    SELECT loan_status FROM loans
    WHERE client_id = ? AND tenant_id = ? AND loan_status = 'Active'
    ORDER BY loan_id DESC LIMIT 1
");
$stmt->bind_param("ii", $client_id, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $loan_status_display = "Active";
    $loan_status_color   = "text-success";
    $loan_status_bg      = "bg-success";
    $loan_status_icon    = "pending";
} else {
    $stmt2 = $conn->prepare("
        SELECT application_status FROM loan_applications
        WHERE client_id = ? AND tenant_id = ?
          AND application_status NOT IN ('Rejected', 'Cancelled', 'Withdrawn', 'Approved')
        ORDER BY application_id DESC LIMIT 1
    ");
    $stmt2->bind_param("ii", $client_id, $tenant_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2->num_rows > 0) {
        $row2 = $res2->fetch_assoc();
        $loan_status_display = $row2['application_status'];
        $loan_status_color   = "text-warning";
        $loan_status_bg      = "bg-warning";
        $loan_status_icon    = "hourglass_top";
    }
    $stmt2->close();
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard - <?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Fundline'); ?></title>
    <!-- Google Fonts: Manrope -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css" rel="stylesheet">
    <link href="../assets/css/tour.css" rel="stylesheet">
    <style>
        .welcome-card {
            background: linear-gradient(135deg, #ec1313 0%, #b30f0f 100%);
            color: white;
            border: none;
        }
        .stat-card-modern h2, .stat-card-modern p { color: white !important; }
        .stat-icon-box {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }
        .marketing-splash-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff5f5 100%);
            color: #333; border: none; position: relative;
        }
        .marketing-splash-card h2 { color: #ec1313; }
        .marketing-splash-card .material-symbols-outlined { color: #ec1313; }
        .marketing-splash-card .badge { background: #ec1313 !important; color: white !important; }
        .feature-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(236,19,19,0.08); padding: 12px 20px; border-radius: 30px;
            font-size: 14px; font-weight: 600; color: #ec1313;
            border: 1px solid rgba(236,19,19,0.15); transition: all 0.2s ease;
        }
        .feature-badge:hover { background: rgba(236,19,19,0.12); border-color: #ec1313; }
        .feature-badge .material-symbols-outlined { font-size: 20px; color: #ec1313; }
        .marketing-decoration { right: -30px; bottom: -30px; opacity: 0.05; font-size: 18rem !important; color: #ec1313; pointer-events: none; }
        .alternating-card { position: absolute; top: 0; left: 0; width: 100%; opacity: 0; transform: translateY(20px); transition: opacity 0.8s ease, transform 0.8s ease; pointer-events: none; }
        .alternating-card.active { position: relative; opacity: 1; transform: translateY(0); pointer-events: auto; }
        .carousel-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; transition: all 0.3s ease; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .carousel-nav:hover { background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transform: translateY(-50%) scale(1.1); }
        .carousel-nav-prev { left: 15px; }
        .carousel-nav-next { right: 15px; }
        .carousel-nav .material-symbols-outlined { font-size: 24px; color: #dc3545; }
        .carousel-indicators { position: absolute; bottom: 20px; left: 0; right: 0; display: flex; justify-content: center; align-items: center; gap: 10px; z-index: 10; }
        .carousel-dot { width: 12px; height: 12px; border-radius: 50%; background: rgba(255,255,255,0.5); border: 2px solid rgba(255,255,255,0.8); cursor: pointer; transition: all 0.3s ease; padding: 0; }
        .carousel-dot:hover { background: rgba(255,255,255,0.8); transform: scale(1.2); }
        .carousel-dot.active { background: white; width: 30px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <?php include 'user_sidebar.php'; ?>
        <main class="main-content">
            <?php include 'client_header.php'; ?>
            <div class="content-area">
                <!-- Alternating Welcome/Marketing Cards -->
                <div class="position-relative mb-4" id="cardCarousel">
                    <div class="card welcome-card shadow-lg alternating-card active" id="welcomeCard">
                        <div class="card-body p-4 p-md-5 d-flex align-items-center justify-content-between position-relative overflow-hidden">
                            <div class="position-relative z-1">
                                <h2 class="h3 fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($client_data['first_name'] ?? $username); ?>!</h2>
                                <p class="text-white-50 mb-0" style="max-width: 500px;">
                                    Here's what's happening today in the system.
                                </p>
                            </div>
                            <div class="d-none d-md-block ms-4 opacity-50">
                                <span class="material-symbols-outlined" style="font-size: 8rem;">account_balance</span>
                            </div>
                        </div>
                    </div>
                    <div class="card marketing-splash-card shadow-lg alternating-card" id="marketingCard">
                        <div class="card-body p-4 p-md-5 position-relative overflow-hidden">
                            <div class="position-relative z-1">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="material-symbols-outlined fs-2">campaign</span>
                                    <span class="badge px-3 py-2 rounded-pill fw-bold">Limited Time</span>
                                </div>
                                <h2 class="h3 fw-bold mb-3">Special Offer Just For You!</h2>
                                <p class="text-dark mb-4 fs-6" style="max-width: 600px;">
                                    Get approved in <strong>minutes</strong>! Apply for a loan today and enjoy flexible payment terms with competitive rates starting at just 3%.
                                </p>
                                <div class="d-flex flex-wrap gap-3 mb-3">
                                    <div class="feature-badge"><span class="material-symbols-outlined">verified</span><span>Fast Approval</span></div>
                                    <div class="feature-badge"><span class="material-symbols-outlined">shield</span><span>Secure Process</span></div>
                                    <div class="feature-badge"><span class="material-symbols-outlined">trending_up</span><span>Low Rates</span></div>
                                </div>
                                <a href="apply_loan.php" class="btn btn-danger btn-lg rounded-pill px-4 py-3 fw-bold shadow-lg mt-2">
                                    Apply Now <span class="material-symbols-outlined align-middle ms-1">arrow_forward</span>
                                </a>
                            </div>
                            <div class="position-absolute marketing-decoration">
                                <span class="material-symbols-outlined">stars</span>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-indicators">
                        <button class="carousel-dot active" data-card="0" aria-label="Go to card 1"></button>
                        <button class="carousel-dot" data-card="1" aria-label="Go to card 2"></button>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="row g-4 mb-5" id="tour-stats">
                    <div class="col-md-6 mb-4">
                        <div class="stat-card-modern card-green">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="stat-icon-box"><span class="material-symbols-outlined">payments</span></div>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Lifetime</span>
                            </div>
                            <h2 class="display-6 fw-bold mb-1">₱<?php echo number_format($total_paid, 2); ?></h2>
                            <p class="text-secondary mb-0 fw-medium">Total Paid (Lifetime)</p>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="stat-card-modern card-blue h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="stat-icon-box"><span class="material-symbols-outlined">analytics</span></div>
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Current</span>
                            </div>
                            <h2 class="display-6 fw-bold mb-1"><?php echo $active_loans_count; ?></h2>
                            <p class="text-secondary mb-0 fw-medium">Active Loan Status (Open)</p>
                        </div>
                    </div>
                </div>

                <!-- Marketing Banners -->
                <div class="row g-4 mb-5">
                    <div class="col-md-12"><h5 class="fw-bold text-main mb-3">Exclusive Offers</h5></div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden hover-lift">
                            <div class="card-body p-4 bg-light position-relative">
                                <div class="position-absolute top-0 end-0 p-3"><span class="badge bg-danger rounded-pill">Hot</span></div>
                                <div class="mb-3 text-primary"><span class="material-symbols-outlined fs-1">monitoring</span></div>
                                <h5 class="fw-bold text-dark">Business Expansion</h5>
                                <p class="text-secondary small mb-3">Grow your business with loans up to ₱100,000 at 3% interest.</p>
                                <a href="apply_loan.php?type=business" class="btn btn-outline-primary rounded-pill btn-sm fw-bold stretched-link">Learn More</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden hover-lift">
                            <div class="card-body p-4 bg-light position-relative">
                                <div class="mb-3 text-warning"><span class="material-symbols-outlined fs-1">school</span></div>
                                <h5 class="fw-bold text-dark">Education Loan</h5>
                                <p class="text-secondary small mb-3">Invest in your future with flexible payment terms for tuition.</p>
                                <a href="apply_loan.php?type=education" class="btn btn-outline-warning text-warning rounded-pill btn-sm fw-bold stretched-link">Learn More</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden hover-lift">
                            <div class="card-body p-4 text-white position-relative" style="background: linear-gradient(45deg, var(--color-primary), #ff4d4d);">
                                <div class="mb-3 text-white"><span class="material-symbols-outlined fs-1">support_agent</span></div>
                                <h5 class="fw-bold">Further Questions</h5>
                                <p class="text-white text-opacity-75 small mb-3">Have questions? Chat with our support team for assistance.</p>
                                <a href="help.php" class="btn btn-white text-primary rounded-pill btn-sm fw-bold stretched-link">Chat Now</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & Recent Transactions -->
                <div class="row g-4">
                    <div class="col-lg-8" id="tour-transactions">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold text-main mb-0">Recent Transactions</h5>
                                <a href="my_payments.php" class="btn btn-sm btn-light text-primary fw-bold rounded-pill px-3">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php
                                    // Fetch recent 5 payments (scoped to tenant)
                                    $stmt = $conn->prepare("
                                        SELECT p.payment_date, p.payment_amount, p.payment_status, lp.product_name
                                        FROM payments p
                                        JOIN loans l ON p.loan_id = l.loan_id
                                        JOIN loan_products lp ON l.product_id = lp.product_id
                                        WHERE l.client_id = ? AND l.tenant_id = ?
                                        ORDER BY p.payment_date DESC
                                        LIMIT 5
                                    ");
                                    $stmt->bind_param("ii", $client_id, $tenant_id);
                                    $stmt->execute();
                                    $recent_payments = $stmt->get_result();
                                    if ($recent_payments->num_rows > 0):
                                        while($pay = $recent_payments->fetch_assoc()):
                                            $icon = 'payments';
                                            $bg   = 'bg-primary';
                                            if ($pay['payment_status'] == 'Pending')  $bg = 'bg-warning';
                                            elseif ($pay['payment_status'] == 'Rejected') $bg = 'bg-danger';
                                    ?>
                                    <div class="list-group-item border-0 px-4 py-3 d-flex align-items-center gap-3 hover-bg-light transition-base">
                                        <div class="rounded-circle <?php echo $bg; ?> bg-opacity-10 p-2 d-flex align-items-center justify-content-center text-<?php echo str_replace('bg-', '', $bg); ?>" style="width: 40px; height: 40px;">
                                            <span class="material-symbols-outlined fs-5"><?php echo $icon; ?></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0 fw-bold text-main"><?php echo htmlspecialchars($pay['product_name']); ?></h6>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($pay['payment_date'])); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <h6 class="mb-0 fw-bold text-main">-₱<?php echo number_format($pay['payment_amount'], 2); ?></h6>
                                        </div>
                                    </div>
                                    <?php endwhile; else: ?>
                                    <div class="p-5 text-center text-muted">
                                        <span class="material-symbols-outlined fs-1 opacity-25 mb-2">receipt_long</span>
                                        <p class="mb-0">No recent transactions found.</p>
                                    </div>
                                    <?php endif; $stmt->close(); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4" id="tour-actions">
                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface">
                            <div class="card-header bg-transparent border-0 p-4 pb-2">
                                <h5 class="fw-bold text-main mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body p-4 pt-2">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <a href="apply_loan.php" class="card border-0 shadow-sm text-decoration-none h-100 hover-lift text-center p-3 bg-light">
                                            <div class="mx-auto bg-primary bg-opacity-10 text-primary rounded-circle p-2 d-flex justify-content-center align-items-center mb-2" style="width: 50px; height: 50px;"><span class="material-symbols-outlined">add_circle</span></div>
                                            <h6 class="fw-bold text-dark small mb-1">Apply Loan</h6>
                                            <span class="text-muted d-block" style="font-size: 0.7rem;">Get Cash</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="my_payments.php" class="card border-0 shadow-sm text-decoration-none h-100 hover-lift text-center p-3 bg-light">
                                            <div class="mx-auto bg-success bg-opacity-10 text-success rounded-circle p-2 d-flex justify-content-center align-items-center mb-2" style="width: 50px; height: 50px;"><span class="material-symbols-outlined">payment</span></div>
                                            <h6 class="fw-bold text-dark small mb-1">Pay Bills</h6>
                                            <span class="text-muted d-block" style="font-size: 0.7rem;">Settle Due</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="my_loans.php" class="card border-0 shadow-sm text-decoration-none h-100 hover-lift text-center p-3 bg-light">
                                            <div class="mx-auto bg-info bg-opacity-10 text-info rounded-circle p-2 d-flex justify-content-center align-items-center mb-2" style="width: 50px; height: 50px;"><span class="material-symbols-outlined">account_balance_wallet</span></div>
                                            <h6 class="fw-bold text-dark small mb-1">My Loans</h6>
                                            <span class="text-muted d-block" style="font-size: 0.7rem;">View Active</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="profile.php" class="card border-0 shadow-sm text-decoration-none h-100 hover-lift text-center p-3 bg-light">
                                            <div class="mx-auto bg-warning bg-opacity-10 text-warning rounded-circle p-2 d-flex justify-content-center align-items-center mb-2" style="width: 50px; height: 50px;"><span class="material-symbols-outlined">person</span></div>
                                            <h6 class="fw-bold text-dark small mb-1">Profile</h6>
                                            <span class="text-muted d-block" style="font-size: 0.7rem;">Update Info</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <?php include 'welcome_modal.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const welcomeModal = new bootstrap.Modal(document.getElementById('welcomeModal'));
            <?php if (!$has_seen_tour): ?>
            setTimeout(() => { welcomeModal.show(); }, 500);
            <?php endif; ?>
            window.startPageTour = () => { welcomeModal.show(); };

            const welcomeCard  = document.getElementById('welcomeCard');
            const marketingCard = document.getElementById('marketingCard');
            const dots  = document.querySelectorAll('.carousel-dot');
            const cards = [welcomeCard, marketingCard];
            let currentCard = 0, autoRotateInterval;

            function updateCarousel(newIndex) {
                cards.forEach(c => c.classList.remove('active'));
                dots.forEach(d => d.classList.remove('active'));
                cards[newIndex].classList.add('active');
                dots[newIndex].classList.add('active');
                currentCard = newIndex;
            }
            function nextCard() { updateCarousel((currentCard + 1) % cards.length); }
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => { updateCarousel(index); resetAutoRotate(); });
            });
            function startAutoRotate() { autoRotateInterval = setInterval(nextCard, 4000); }
            function resetAutoRotate() { clearInterval(autoRotateInterval); startAutoRotate(); }
            startAutoRotate();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
