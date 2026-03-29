<?php
/**
 * Terms and Conditions Page
 * Legal agreements and usage policy
 */

session_start();
require_once '../config/db.php';

// Access control: Allow public access
$is_logged_in = isset($_SESSION['user_id']);
$current_page = 'terms.php';

$type = $_GET['type'] ?? 'terms';
$is_modal = isset($_GET['modal']) && $_GET['modal'] == 1;

$title = ($type === 'privacy') ? 'Privacy Policy' : 'Terms and Conditions';
?>
<?php if (!$is_modal): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo $title; ?> - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <style>
        .terms-content {
            background: #ffffff;
            border-radius: var(--radius-xl);
            padding: 3rem;
            box-shadow: var(--shadow-sm);
        }
        
        .terms-section { margin-bottom: 2.5rem; }
        .terms-title { 
            font-weight: 800; 
            color: var(--color-primary); 
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.25rem;
        }
        
        .terms-card {
            background: rgba(var(--color-primary-rgb), 0.03);
            border: 1px solid rgba(var(--color-primary-rgb), 0.1);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease;
        }
        
        .terms-card:hover {
            transform: translateY(-2px);
            background: rgba(var(--color-primary-rgb), 0.05);
        }
        
        .terms-text {
            color: var(--color-text-secondary);
            line-height: 1.7;
        }

        .rate-table th {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-muted);
        }
        
        <?php if ($is_logged_in): ?>
        /* Mobile Sidebar Handling */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1025; }
            .sidebar-overlay.show { display: block; }
            .terms-content { padding: 1.5rem; }
        }
        <?php else: ?>
        .main-content { margin-left: 0 !important; }
        <?php endif; ?>
    </style>
</head>
<body class="bg-light">
    <?php if ($is_logged_in): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <?php include 'user_sidebar.php'; ?>
        
        <main class="main-content w-100 min-vh-100 d-flex flex-column">
            <?php include 'client_header.php'; ?>
            
            <div class="content-area container-fluid p-4">
    <?php else: ?>
    <!-- Public Header -->
    <nav class="navbar bg-white shadow-sm py-3 mb-5">
        <div class="container">
            <a href="index.php" class="text-decoration-none d-flex align-items-center gap-2 text-main">
                <span class="material-symbols-outlined">arrow_back</span>
                <span class="fw-bold">Back to Home</span>
            </a>
            <div class="d-flex flex-column lh-1 text-end">
                <span class="d-flex align-items-center justify-content-end" style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.25rem; letter-spacing: -1px; color: var(--color-primary);">
                    fundline
                </span>
            </div>
        </div>
    </nav>
    <main class="main-content w-100 d-flex flex-column">
        <div class="container pb-5">
    <?php endif; ?>
    
                <div style="max-width: 1000px; margin: 0 auto;">
                    
                    <div class="terms-content">
<?php endif; // End non-modal header ?>

                        <?php if (!$is_modal): ?>
                        <ul class="nav nav-pills nav-fill mb-4 p-1 rounded-pill bg-light border" id="legalTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill active fw-bold" id="terms-tab" data-bs-toggle="pill" data-bs-target="#terms-pane" type="button" role="tab" aria-selected="true">Terms and Conditions</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill fw-bold" id="privacy-tab" data-bs-toggle="pill" data-bs-target="#privacy-pane" type="button" role="tab" aria-selected="false">Privacy Policy</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="legalTabContent">
                        <?php endif; ?>

                        <!-- TERMS PANE -->
                        <?php if (!$is_modal || ($is_modal && $type === 'terms')): ?>
                        <div class="<?php echo ($is_modal) ? 'p-4' : 'tab-pane fade show active'; ?>" id="terms-pane" role="tabpanel" tabindex="0">
                            <?php if ($is_modal): ?>
                            <div class="text-center mb-4">
                                <h1 class="display-6 fw-bold text-main mb-2">Terms and Conditions</h1>
                                <p class="text-muted">Last Updated: January 25, 2026</p>
                            </div>
                            <?php endif; ?>

                            <div class="alert alert-info border-0 mb-4" role="alert" style="<?php echo $is_modal ? 'margin-left: 0; margin-right: 0;' : ''; ?>">
                                <strong>Agreement:</strong> By submitting a loan application, you acknowledge and agree to the following terms, rates, and policies of Fundline Finance Corporation.
                            </div>

                            <div class="terms-section" style="<?php echo $is_modal ? 'margin-bottom: 2rem;' : ''; ?>">
                                <h3 class="terms-title" style="<?php echo $is_modal ? 'margin-bottom: 1rem;' : ''; ?>">1. Loan Products & Interest Rates</h3>
                                <p class="terms-text" style="<?php echo $is_modal ? 'margin-bottom: 1.5rem; line-height: 1.8;' : ''; ?>">Interest rates are fixed for the duration of the loan term. Processing fees, documentary stamps, and service charges are deducted from the net proceeds.</p>
                                
                                <div class="table-responsive" style="<?php echo $is_modal ? 'margin-bottom: 1rem;' : ''; ?>">
                                    <table class="table table-bordered rate-table">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Loan Type</th>
                                                <th>Monthly Interest</th>
                                                <th>Term Duration</th>
                                                <th>Penalty Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="fw-bold">Personal Loan</td>
                                                <td>2.5% per month</td>
                                                <td>3 - 24 Months</td>
                                                <td>5% monthly on overdue</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Business Loan</td>
                                                <td>3.0% per month</td>
                                                <td>6 - 36 Months</td>
                                                <td>5% monthly on overdue</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Emergency Loan</td>
                                                <td>3.0% per month</td>
                                                <td>1 - 12 Months</td>
                                                <td>10% monthly on overdue</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted" style="<?php echo $is_modal ? 'display: block; margin-top: 0.75rem;' : ''; ?>">* Interest rates are subject to change based on credit assessment and market conditions.</small>
                            </div>

                            <div class="terms-section" style="<?php echo $is_modal ? 'margin-bottom: 2rem;' : ''; ?>">
                                <h3 class="terms-title" style="<?php echo $is_modal ? 'margin-bottom: 1.25rem;' : ''; ?>">2. Eligibility & Application</h3>
                                <ul class="list-group list-group-flush" style="<?php echo $is_modal ? 'margin-left: 0; margin-right: 0;' : ''; ?>">
                                    <li class="list-group-item px-0" style="<?php echo $is_modal ? 'padding-top: 1rem !important; padding-bottom: 1rem !important; line-height: 1.7;' : ''; ?>">
                                        <strong>Age & Residency:</strong> Applicants must be at least 18 years old and a resident of the Philippines.
                                    </li>
                                    <li class="list-group-item px-0" style="<?php echo $is_modal ? 'padding-top: 1rem !important; padding-bottom: 1rem !important; line-height: 1.7;' : ''; ?>">
                                        <strong>Co-maker Policy:</strong> A Co-maker is <u>MANDATORY</u> for all loan applications. The co-maker must be immediate family or a financially stable relative.
                                    </li>
                                    <li class="list-group-item px-0" style="<?php echo $is_modal ? 'padding-top: 1rem !important; padding-bottom: 1rem !important; line-height: 1.7;' : ''; ?>">
                                        <strong>Active Loan Limit:</strong> You may only have one (active or pending) loan per category (Personal, Business, etc.) at any given time.
                                    </li>
                                    <li class="list-group-item px-0" style="<?php echo $is_modal ? 'padding-top: 1rem !important; padding-bottom: 1rem !important; line-height: 1.7;' : ''; ?>">
                                        <strong>Credit Limit:</strong> Your maximum loanable amount is determined by your verification level and income bracket. You cannot borrow more than your assigned credit limit.
                                    </li>
                                </ul>
                            </div>

                            <div class="terms-section" style="<?php echo $is_modal ? 'margin-bottom: 2rem;' : ''; ?>">
                                <h3 class="terms-title" style="<?php echo $is_modal ? 'margin-bottom: 1.25rem;' : ''; ?>">3. Repayment & Penalties</h3>
                                <div class="row g-3" style="<?php echo $is_modal ? 'margin-bottom: 0;' : ''; ?>">
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded text-center h-100">
                                            <h6 class="fw-bold text-primary">Grace Period</h6>
                                            <p class="mb-0">You have a<br><strong class="fs-4">5 Day</strong><br>grace period after due date before penalties apply.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded text-center h-100">
                                            <h6 class="fw-bold text-danger">Default</h6>
                                            <p class="mb-0">Loans unpaid for<br><strong class="fs-4">90 Days</strong><br>are considered in default and subject to legal action.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <h6 class="fw-bold text-main mb-2">Late Payment Penalty</h6>
                                    <p class="terms-text mb-4">
                                        For loans that remain unpaid for a period of <strong>six (6) months</strong> or more past the due date, a one-time penalty charge equivalent to <strong>10%</strong> of the total outstanding balance shall be automatically applied to the account.
                                    </p>

                                    <h6 class="fw-bold text-main mb-2">Early Settlement Policy (Pre-termination)</h6>
                                    <p class="terms-text mb-2">
                                        Borrowers have the option to settle their loan obligation in full prior to the designated maturity date. The final repayment amount will be calculated as follows:
                                    </p>
                                    <ul class="list-unstyled ps-3 mb-0">
                                        <li class="mb-2">
                                            <span class="fw-bold text-dark">• Pro-rated Interest:</span> 
                                            Interest charges shall be calculated based on the actual number of months the loan was active. (e.g., A 6-month loan fully paid in 3 months will only be charged 3 months of interest).
                                        </li>
                                        <li class="mb-0">
                                            <span class="fw-bold text-dark">• Termination Fee:</span> 
                                            An early termination fee equivalent to <strong>0.06%</strong> of the remaining outstanding balance will be charged upon settlement.
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="terms-section" style="<?php echo $is_modal ? 'margin-bottom: 1rem;' : ''; ?>">
                                <h3 class="terms-title" style="<?php echo $is_modal ? 'margin-bottom: 1rem;' : ''; ?>">4. Verification & Privacy</h3>
                                <p class="terms-text" style="<?php echo $is_modal ? 'line-height: 1.8; margin-bottom: 0;' : ''; ?>">
                                    Fundline reserves the right to conduct Credit Investigations (CI), including but not limited to home visits, employment verification, and contacting references/co-makers. Providing false information (falsified documents) will result in immediate rejection and permanent blacklisting.
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- PRIVACY PANE -->
                        <?php if (!$is_modal || ($is_modal && $type === 'privacy')): ?>
                        <div class="<?php echo ($is_modal) ? 'p-4' : 'tab-pane fade'; ?>" id="privacy-pane" role="tabpanel" tabindex="0">
                            <?php if ($is_modal): ?>
                            <div class="text-center mb-4">
                                <h1 class="display-6 fw-bold text-main mb-2">Privacy Policy</h1>
                                <p class="text-muted">Last Updated: January 25, 2026</p>
                            </div>
                            <?php endif; ?>

                            <div class="alert alert-secondary border-0 mb-4" role="alert" style="<?php echo $is_modal ? 'margin-left: 0; margin-right: 0;' : ''; ?>">
                                Your privacy is our priority. This policy outlines how Fundline protects your personal and financial data.
                            </div>

                            <div class="row g-5 mb-4">
                                <div class="col-md-6">
                                    <div class="terms-section mb-0">
                                        <h3 class="terms-title">1. Data Collection</h3>
                                        <p class="terms-text mb-2">We collect the following to process your application:</p>
                                        <ul class="mb-0 ps-3 text-secondary">
                                            <li><strong>Personal Data:</strong> Name, Address, Date of Birth.</li>
                                            <li><strong>Contact Info:</strong> Email, Phone Number.</li>
                                            <li><strong>Financial Data:</strong> Income proof, Bank details.</li>
                                            <li><strong>Govt ID:</strong> Passport, UMID, Driver's License.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="terms-section mb-0">
                                        <h3 class="terms-title">2. Use of Data</h3>
                                        <p class="terms-text mb-2">Your data is primarily used for:</p>
                                        <ul class="mb-0 ps-3 text-secondary">
                                            <li>Credit Risk Assessment & Scoring.</li>
                                            <li>Identity Verification (KYC).</li>
                                            <li>Loan Disbursement & Collections.</li>
                                            <li>Regulatory Compliance (AMLA).</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="terms-section">
                                <h3 class="terms-title">3. Information Sharing</h3>
                                <p class="terms-text mb-3">
                                    We do <strong>not</strong> sell your personal data. We only share it with trusted partners necessary for our operations:
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-light text-dark border p-2">Credit Information Corp (CIC)</span>
                                    <span class="badge bg-light text-dark border p-2">Payment Gateways</span>
                                    <span class="badge bg-light text-dark border p-2">Background Check Providers</span>
                                </div>
                            </div>

                            <div class="terms-section">
                                <h3 class="terms-title">4. Security Measures</h3>
                                <p class="terms-text mb-0">
                                    We use industry-standard <strong>256-bit SSL encryption</strong> to protect your data during transmission. Our databases are secured with strict access controls and regular audits.
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!$is_modal): ?>
                        </div> <!-- End Tab Content -->
                        <?php endif; ?>
                        
                        <?php if ($is_modal): ?>
                        </div>
                        <?php endif; ?>

<?php if (!$is_modal): ?>
                    </div>

                </div>
            <?php if ($is_logged_in): ?>
            </div>
            <?php else: ?>
            </div>
            <?php endif; ?>
        </main>
    <?php if ($is_logged_in): ?>
    </div>
    <?php endif; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    </script>
</body>
</html>
<?php endif; ?>

