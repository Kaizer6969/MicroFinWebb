<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Fundline - Smart Micro Financing</title>
   
    <!-- Google Fonts: Manrope & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
   
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
   
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fundline Design System -->
    <link href="main_style.css" rel="stylesheet">

    <style>
        /* Landing Page Specific Overrides */
        body {
            overflow-x: hidden;
            background-color: var(--color-background-light);
        }

        .hero-section {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 80px;
            background: linear-gradient(to right, var(--color-surface-light) 0%, rgba(255,255,255,0.95) 40%, transparent 70%);
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background-image: url('landing_banner.png');
            background-size: cover;
            background-position: center;
            mask-image: linear-gradient(to right, transparent, black);
            -webkit-mask-image: linear-gradient(to right, transparent, black);
            opacity: 0.8;
            z-index: 0;
        }
        
        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 1.5rem;
            background: rgba(236, 19, 19, 0.1);
            color: var(--color-primary);
            transition: all 0.3s ease;
        }

        .card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
            background: var(--color-primary);
            color: white;
        }
        
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Custom Scrollbar for Modal */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: var(--color-primary);
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: var(--color-primary-dark);
        }

        /* Mobile Responsive Styles */
        @media (max-width: 991.98px) {
            .hero-section {
                background: linear-gradient(to bottom, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
                padding-bottom: 60px;
            }

            .hero-bg {
                display: block !important;
                width: 100%;
                height: 100%;
                opacity: 0.15;
                mask-image: none;
                -webkit-mask-image: none;
                background-position: center;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top bg-surface shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand d-flex flex-column lh-1" href="#">
                <span class="d-flex align-items-center" style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2rem; letter-spacing: -2px; color: var(--color-primary);">
                    fundline
                </span>
                <span style="font-family: 'Outfit', sans-serif; font-weight: 400; font-size: 0.55rem; letter-spacing: 2px; color: var(--color-primary); text-transform: uppercase; margin-left: 2px;">
                    Finance Corporation
                </span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <li class="nav-item">
                        <a class="nav-link fw-medium text-main" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-medium text-main" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-medium text-main" href="#about">About</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <button class="btn btn-outline-primary px-4 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#loginModal">
                            Log In
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#registerModal">
                            Get Started
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="container position-relative z-1">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="pe-lg-5">
                        <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 rounded-pill fw-bold fade-in-up">
                            🚀 Official Lending Partner
                        </span>
                        <h1 class="display-3 fw-bolder mb-4 text-main lh-sm fade-in-up delay-100">
                            Business & Personal Loans, <span class="text-primary">Approved in 24 Hours.</span>
                        </h1>
                        <p class="lead text-muted mb-5 fade-in-up delay-200">
                            Apply for <strong>Business, Education, Housing, or Emergency</strong> loans completely online. Track your credit limit, manage payments, and get funded without leaving your home.
                        </p>
                        <div class="d-flex gap-3 fade-in-up delay-300">
                            <button class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow-lg hover-lift d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#registerModal">
                                Apply Now
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </button>
                            <button class="btn btn-light btn-lg rounded-pill px-5 py-3 fw-bold text-main border shadow-sm hover-lift" data-bs-toggle="modal" data-bs-target="#loginModal">
                                Client Login
                            </button>
                        </div>
                        
                        <div class="mt-5 pt-4 border-top fade-in-up delay-300">
                            <div class="d-flex gap-5">
                                <div>
                                    <h3 class="fw-bold mb-0 text-main">Credit Limit</h3>
                                    <p class="text-muted small">Real-time Tracking</p>
                                </div>
                                <div>
                                    <h3 class="fw-bold mb-0 text-main">6+</h3>
                                    <p class="text-muted small">Loan Products</p>
                                </div>
                                <div>
                                    <h3 class="fw-bold mb-0 text-main">100%</h3>
                                    <p class="text-muted small">Digital Process</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Dashboard Preview Section (The "Marketing Bait") -->
    <section class="py-5 bg-surface overflow-hidden">
        <div class="container py-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-5 order-lg-2">
                    <span class="text-primary fw-bold text-uppercase ls-1">Powerful System</span>
                    <h2 class="display-5 fw-bold mb-4 text-main">Complete Financial Control Dashboard</h2>
                    <p class="text-muted lead mb-4">See exactly what our clients see. A powerful, intuitive dashboard to manage your financial life.</p>
                    <ul class="list-unstyled d-flex flex-column gap-3 mb-4">
                        <li class="d-flex align-items-center gap-3">
                            <span class="material-symbols-outlined text-success fs-4">check_circle</span>
                            <span class="fs-5 text-main">Real-time Credit Limit Monitoring</span>
                        </li>
                        <li class="d-flex align-items-center gap-3">
                            <span class="material-symbols-outlined text-success fs-4">check_circle</span>
                            <span class="fs-5 text-main">Track Active Loans & Payment History</span>
                        </li>
                        <li class="d-flex align-items-center gap-3">
                            <span class="material-symbols-outlined text-success fs-4">check_circle</span>
                            <span class="fs-5 text-main">Instant Loan Calculator</span>
                        </li>
                        <li class="d-flex align-items-center gap-3">
                            <span class="material-symbols-outlined text-success fs-4">check_circle</span>
                            <span class="fs-5 text-main">Document Verification Status</span>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-7 order-lg-1">
                    <!-- Dashboard Mockup Card -->
                    <div class="card border-0 shadow-xl rounded-4 overflow-hidden bg-body-tertiary" style="transform: perspective(1000px) rotateY(10deg) rotateX(2deg); transition: transform 0.5s ease;">
                        <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary rounded-circle" style="width: 12px; height: 12px;"></div>
                                <div class="bg-warning rounded-circle" style="width: 12px; height: 12px;"></div>
                                <div class="bg-success rounded-circle" style="width: 12px; height: 12px;"></div>
                            </div>
                            <div class="small text-muted bg-light px-3 py-1 rounded-pill">fundline.com/dashboard</div>
                        </div>
                        <div class="card-body p-4 bg-light">
                            <!-- Welcome Card Mockup -->
                            <div class="card border-0 text-white mb-4 rounded-4" style="background: linear-gradient(135deg, var(--color-primary) 0%, #b91c1c 100%);">
                                <div class="card-body p-4 position-relative overflow-hidden">
                                    <h4 class="fw-bold mb-1">Welcome back, Maria!</h4>
                                    <p class="mb-0 opacity-75">Your remaining credit limit is ₱150,000.00</p>
                                    <span class="material-symbols-outlined position-absolute opacity-25" style="font-size: 6rem; right: -20px; bottom: -20px;">account_balance</span>
                                </div>
                            </div>
                            <!-- Stats Grid Mockup -->
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="card border-0 shadow-sm rounded-4">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <div class="bg-success bg-opacity-10 text-success p-2 rounded-3">
                                                    <span class="material-symbols-outlined">payments</span>
                                                </div>
                                            </div>
                                            <h3 class="fw-bold mb-0">₱45,200</h3>
                                            <small class="text-muted">Total Paid</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card border-0 shadow-sm rounded-4">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                                                    <span class="material-symbols-outlined">pending</span>
                                                </div>
                                            </div>
                                            <h3 class="fw-bold mb-0">Active</h3>
                                            <small class="text-muted">Loan Status</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Loan Products Section -->
    <section class="py-5" id="services">
        <div class="container py-5">
            <div class="text-center mb-5 mw-lg mx-auto" style="max-width: 700px;">
                <span class="text-primary fw-bold text-uppercase ls-1">Tailored For You</span>
                <h2 class="display-5 fw-bold mb-3 text-main">Our Loan Products</h2>
                <p class="text-muted lead">Flexible financing solutions designed for every stage of your life and business.</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm hover-lift">
                        <div class="feature-icon">
                            <span class="material-symbols-outlined">storefront</span>
                        </div>
                        <h4 class="fw-bold mb-3 text-main">Business Loans</h4>
                        <p class="text-muted mb-0">Expand your enterprise with capital for inventory, equipment, or renovation. Requires Business Permit.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm hover-lift">
                        <div class="feature-icon">
                            <span class="material-symbols-outlined">school</span>
                        </div>
                        <h4 class="fw-bold mb-3 text-main">Education Loans</h4>
                        <p class="text-muted mb-0">Invest in knowledge. Tuition fee assistance for you or your dependents with flexible terms.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm hover-lift">
                        <div class="feature-icon">
                            <span class="material-symbols-outlined">home</span>
                        </div>
                        <h4 class="fw-bold mb-3 text-main">Housing Loans</h4>
                        <p class="text-muted mb-0">Build your dream home or fund renovations. Low interest rates for long-term comfort.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm hover-lift">
                        <div class="feature-icon">
                            <span class="material-symbols-outlined">healing</span>
                        </div>
                        <h4 class="fw-bold mb-3 text-main">Medical Emergency</h4>
                        <p class="text-muted mb-0">Quick access to funds for unexpected health expenses. Prioritized processing.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm hover-lift">
                        <div class="feature-icon">
                            <span class="material-symbols-outlined">agriculture</span>
                        </div>
                        <h4 class="fw-bold mb-3 text-main">Agricultural</h4>
                        <p class="text-muted mb-0">Support for farmers and agribusiness. Crop cycles matched with repayment terms.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm hover-lift">
                        <div class="feature-icon bg-success bg-opacity-10 text-success">
                            <span class="material-symbols-outlined">person</span>
                        </div>
                        <h4 class="fw-bold mb-3 text-main">Personal Loans</h4>
                        <p class="text-muted mb-0">Multi-purpose cash for travel, gadgets, or debt consolidation. fast approval.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Loan Calculator Section -->
    <section class="py-5 bg-surface" id="calculator">
        <div class="container py-5">
            <div class="text-center mb-5 mw-lg mx-auto" style="max-width: 700px;">
                <span class="text-primary fw-bold text-uppercase ls-1">Plan Ahead</span>
                <h2 class="display-5 fw-bold mb-3 text-main">Loan Calculator</h2>
                <p class="text-muted lead">Estimate your monthly payments and see how much you can afford.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                        <div class="row g-4">
                            <!-- Calculator Inputs -->
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-main mb-3">Loan Amount</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light border-end-0">₱</span>
                                        <input type="number" class="form-control border-start-0" id="loanAmount" value="50000" min="1000" max="500000" step="1000">
                                    </div>
                                    <input type="range" class="form-range mt-3" id="loanAmountRange" min="1000" max="500000" step="1000" value="50000">
                                    <div class="d-flex justify-content-between small text-muted mt-1">
                                        <span>₱1,000</span>
                                        <span>₱500,000</span>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold text-main mb-3">Interest Rate (%)</label>
                                    <div class="input-group input-group-lg">
                                        <input type="number" class="form-control" id="interestRate" value="3" min="1" max="20" step="0.1">
                                        <span class="input-group-text bg-light">%</span>
                                    </div>
                                    <input type="range" class="form-range mt-3" id="interestRateRange" min="1" max="20" step="0.1" value="3">
                                    <div class="d-flex justify-content-between small text-muted mt-1">
                                        <span>1%</span>
                                        <span>20%</span>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold text-main mb-3">Loan Term (Months)</label>
                                    <div class="input-group input-group-lg">
                                        <input type="number" class="form-control" id="loanTerm" value="12" min="6" max="60" step="1">
                                        <span class="input-group-text bg-light">months</span>
                                    </div>
                                    <input type="range" class="form-range mt-3" id="loanTermRange" min="6" max="60" step="1" value="12">
                                    <div class="d-flex justify-content-between small text-muted mt-1">
                                        <span>6 months</span>
                                        <span>60 months</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Calculator Results -->
                            <div class="col-md-6">
                                <div class="card border-0 bg-primary bg-opacity-10 rounded-4 p-4 h-100">
                                    <h5 class="fw-bold text-primary mb-4">Payment Summary</h5>
                                    
                                    <div class="mb-4">
                                        <p class="text-muted small mb-2">Monthly Payment</p>
                                        <h2 class="fw-bold text-primary mb-0" id="monthlyPayment">₱4,303.45</h2>
                                    </div>

                                    <hr class="my-4">

                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted">Principal Amount:</span>
                                        <span class="fw-bold text-main" id="displayPrincipal">₱50,000.00</span>
                                    </div>

                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted">Total Interest:</span>
                                        <span class="fw-bold text-main" id="totalInterest">₱1,641.40</span>
                                    </div>

                                    <div class="d-flex justify-content-between mb-4">
                                        <span class="text-muted">Total Repayment:</span>
                                        <span class="fw-bold text-main" id="totalRepayment">₱51,641.40</span>
                                    </div>

                                    <div class="alert alert-info mb-0 d-flex align-items-start gap-2">
                                        <span class="material-symbols-outlined">info</span>
                                        <small>This is an estimate. Actual rates may vary based on your credit profile.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button class="btn btn-primary btn-lg rounded-pill px-5 fw-bold" data-bs-toggle="modal" data-bs-target="#registerModal">
                                Apply for This Loan
                                <span class="material-symbols-outlined align-middle ms-1">arrow_forward</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5 position-relative overflow-hidden">
        <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary opacity-10"></div>
        <div class="container py-5 position-relative z-1">
            <div class="card border-0 shadow-lg p-5 text-center bg-surface">
                <h2 class="display-6 fw-bold mb-3 text-main">Ready to get funded?</h2>
                <p class="lead text-muted mb-4 mx-auto" style="max-width: 600px;">
                    Join thousands of satisfied Filipinos who have trusted Fundline for their financial goals. 
                    Experience the fastest, most secure, and transparent lending platform in the country completely online.
                </p>
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="d-flex flex-wrap justify-content-center gap-3 gap-md-5 mb-5 text-muted">
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-success">check_circle</span>
                                <span>No Hidden Fees</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-success">check_circle</span>
                                <span>Bank-Grade Security</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-success">check_circle</span>
                                <span>Digital Signatures</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-success">check_circle</span>
                                <span>24/7 Support</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-center gap-3">
                    <button class="btn btn-primary btn-lg rounded-pill px-5 fw-bold" data-bs-toggle="modal" data-bs-target="#registerModal">
                        Create Free Account
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-4">
                         <!-- Footer Logo also updated to new font style, kept white for contrast -->
                        <div class="d-flex flex-column lh-1">
                            <span class="d-flex align-items-center" style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.75rem; letter-spacing: -2px; color: white;">
                                fundline
                            </span>
                             <span style="font-family: 'Outfit', sans-serif; font-weight: 400; font-size: 0.5rem; letter-spacing: 2px; color: rgba(255,255,255,0.7); text-transform: uppercase; margin-left: 2px;">
                                Finance Corporation
                            </span>
                        </div>
                    </div>
                    <p class="text-white-50">Empowering individuals and small businesses with accessible, fair, and transparent financial solutions.</p>
                </div>
                <div class="col-6 col-lg-2 offset-lg-2">
                    <h6 class="fw-bold mb-3 text-primary">Company</h6>
                    <ul class="list-unstyled text-white-50 d-flex flex-column gap-2">
                        <li><a href="#" class="text-reset text-decoration-none hover-text-white">About Us</a></li>
                        <li><a href="#" class="text-reset text-decoration-none hover-text-white">Careers</a></li>
                        <li><a href="#" class="text-reset text-decoration-none hover-text-white">Press</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6 class="fw-bold mb-3 text-primary">Support</h6>
                    <ul class="list-unstyled text-white-50 d-flex flex-column gap-2">
                        <li><a href="#" class="text-reset text-decoration-none hover-text-white">Help Center</a></li>
                        <li><a href="terms.php" class="text-reset text-decoration-none hover-text-white">Terms of Service</a></li>
                        <li><a href="#" class="text-reset text-decoration-none hover-text-white">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-2">
                    <h6 class="fw-bold mb-3 text-primary">Contact</h6>
                    <ul class="list-unstyled text-white-50 d-flex flex-column gap-2">
                        <li class="d-flex align-items-center gap-2"><span class="material-symbols-outlined fs-6">mail</span> hello@fundline.com</li>
                        <li class="d-flex align-items-center gap-2"><span class="material-symbols-outlined fs-6">call</span> +63 912 345 6789</li>
                    </ul>
                </div>
            </div>
            <div class="border-top border-secondary mt-5 pt-4 text-center text-white-50 small">
                <p class="mb-0">© 2026 Fundline Micro Financing Services. All rights reserved.</p>
            </div>
        </div>
    </footer>


    <!-- LOGIN MODAL -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h3 class="fw-bold mb-0 text-main">Welcome Back! 👋</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4">
                    <div id="loginAlert" class="alert alert-danger d-none d-flex align-items-center" role="alert">
                        <span class="material-symbols-outlined me-2">error</span>
                        <div id="loginAlertText"></div>
                    </div>

                    <form id="ajaxLoginForm" novalidate>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username or Email</label>
                            <input type="text" class="form-control form-control-lg" name="username" placeholder="Enter username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg border-end-0" id="loginPassword" name="password" placeholder="••••••••" required>
                                <button class="input-group-text bg-transparent border-start-0" type="button" onclick="togglePassword('loginPassword')">
                                    <span class="material-symbols-outlined text-secondary">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
                                <label class="form-check-label text-secondary" for="rememberMe">Remember me</label>
                            </div>
                            <a href="#" class="text-decoration-none fw-bold small text-primary" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold" id="loginBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            Sign In
                        </button>
                    </form>
                </div>
                <div class="modal-footer border-0 justify-content-center pt-0">
                    <p class="text-secondary small mb-0">Don't have an account? <a href="#" class="text-primary fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#registerModal">Create Account</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- REGISTER MODAL -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h3 class="fw-bold mb-1 text-main">Create Account 🚀</h3>
                        <p class="text-muted mb-0">Join Fundline today and start your journey.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4">
                    <div id="registerAlert" class="alert alert-danger d-none d-flex align-items-center" role="alert">
                        <span class="material-symbols-outlined me-2">error</span>
                        <div id="registerAlertText"></div>
                    </div>
                     <div id="registerSuccess" class="alert alert-success d-none d-flex align-items-center" role="alert">
                        <span class="material-symbols-outlined me-2">check_circle</span>
                        <div id="registerSuccessText"></div>
                    </div>

                    <form id="ajaxRegisterForm" novalidate>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">First Name</label>
                                <input type="text" class="form-control" name="first_name" placeholder="First" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold">Middle</label>
                                <input type="text" class="form-control" name="middle_name" placeholder="Middle">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold">Last Name</label>
                                <input type="text" class="form-control" name="last_name" placeholder="Last" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-semibold">Suffix</label>
                                <input type="text" class="form-control" name="suffix" placeholder="Jr.">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Username</label>
                                <input type="text" class="form-control" name="username" placeholder="Choose username" required minlength="3">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" class="form-control" name="email" placeholder="Enter email" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control border-end-0" id="regPassword" name="password" placeholder="Create password" required minlength="12">
                                    <button class="input-group-text bg-transparent border-start-0" type="button" onclick="togglePassword('regPassword')">
                                        <span class="material-symbols-outlined text-secondary">visibility</span>
                                    </button>
                                </div>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar" id="passwordStrengthBar" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">At least 12 chars, mixed case, number & symbol.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control border-end-0" id="regConfirmPassword" name="confirm_password" placeholder="Confirm password" required>
                                    <button class="input-group-text bg-transparent border-start-0" type="button" onclick="togglePassword('regConfirmPassword')">
                                        <span class="material-symbols-outlined text-secondary">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="termsCheck" required>
                            <label class="form-check-label text-secondary small" for="termsCheck">
                                I agree to the <a href="terms.php" target="_blank" class="text-primary text-decoration-none">Terms of Service</a> and <a href="#" class="text-primary text-decoration-none">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold" id="registerBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            Create Account
                        </button>
                    </form>
                </div>
                <div class="modal-footer border-0 justify-content-center pt-0">
                    <p class="text-secondary small mb-0">Already have an account? <a href="#" class="text-primary fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#loginModal">Log In</a></p>
                </div>
            </div>
        </div>
    </div>


    <!-- OTP VERIFICATION MODAL -->
    <div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <div>
                         <h3 class="fw-bold mb-1 text-main">Verify Account 📧</h3>
                        <p class="text-muted mb-0">Enter the 6-digit code sent to your email.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4">
                    <div id="otpAlert" class="alert alert-danger d-none d-flex align-items-center" role="alert">
                        <span class="material-symbols-outlined me-2">error</span>
                        <div id="otpAlertText"></div>
                    </div>
                    <div id="otpSuccess" class="alert alert-success d-none d-flex align-items-center" role="alert">
                        <span class="material-symbols-outlined me-2">check_circle</span>
                        <div id="otpSuccessText"></div>
                    </div>

                    <form id="ajaxOtpForm">
                        <input type="hidden" name="action" value="verify_otp">
                        <div class="mb-4 text-center">
                            <label class="form-label fw-bold small text-uppercase text-muted">Verification Code</label>
                            <input type="text" class="form-control form-control-lg text-center fw-bold fs-2" name="otp" maxlength="6" style="letter-spacing: 0.5rem;" placeholder="000000" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold" id="verifyOtpBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            Verify Code
                        </button>
                    </form>
                </div>
                <div class="modal-footer border-0 justify-content-center pt-0 flex-column">
                    <p class="text-secondary small mb-1">Didn't receive code? <a href="#" id="resendOtpLink" class="text-primary fw-bold text-decoration-none" onclick="resendOtp(event)">Resend Code</a> <span id="resendTimer" class="text-muted d-none">(Wait <span id="timerCount">60</span>s)</span></p>
                    <p class="text-muted small fst-italic mb-0" style="font-size: 0.75rem;">(Please check your spam or junk folder)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem; overflow: hidden;">
                <div class="modal-header border-0 pb-2 pt-4 px-4" style="background: linear-gradient(135deg, #EF4444 0%, #B91C1C 100%);">
                    <div class="w-100 text-center">
                        <div class="mb-3">
                            <span class="material-symbols-outlined" style="font-size: 3.5rem; color: white; font-variation-settings: 'FILL' 1, 'wght' 300;">lock_reset</span>
                        </div>
                        <h3 class="fw-bold mb-2 text-white">Reset Password</h3>
                        <p class="text-white-50 mb-0 small">Enter your email to receive a reset link</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-4">
                    <div id="forgotPasswordAlert" class="alert alert-danger d-none d-flex align-items-start border-0 shadow-sm" role="alert" style="border-radius: 0.75rem;">
                        <span class="material-symbols-outlined me-2 mt-1" style="font-size: 1.25rem;">error</span>
                        <div id="forgotPasswordAlertText" class="flex-grow-1"></div>
                    </div>
                    <div id="forgotPasswordSuccess" class="alert alert-success d-none d-flex align-items-start border-0 shadow-sm" role="alert" style="border-radius: 0.75rem;">
                        <span class="material-symbols-outlined me-2 mt-1" style="font-size: 1.25rem;">check_circle</span>
                        <div id="forgotPasswordSuccessText" class="flex-grow-1"></div>
                    </div>

                    <form id="ajaxForgotPasswordForm" novalidate>
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark mb-2">
                                <span class="material-symbols-outlined align-middle me-1" style="font-size: 1.1rem;">mail</span>
                                Email Address
                            </label>
                            <input type="email" class="form-control form-control-lg shadow-sm" name="email" placeholder="your.email@example.com" required style="border-radius: 0.75rem; border: 2px solid #e9ecef; transition: all 0.3s;">
                        </div>
                        <button type="submit" class="btn btn-lg w-100 fw-bold shadow" id="forgotPasswordBtn" style="background: linear-gradient(135deg, #EF4444 0%, #B91C1C 100%); border: none; border-radius: 0.75rem; color: white; padding: 0.875rem; transition: transform 0.2s, box-shadow 0.2s;">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            <span class="material-symbols-outlined align-middle me-2" style="font-size: 1.25rem;">send</span>
                            Send Reset Link
                        </button>
                    </form>
                </div>
                <div class="modal-footer border-0 justify-content-center pt-0 pb-4 px-4" style="background-color: #f8f9fa;">
                    <p class="text-secondary small mb-0">Remember your password? <a href="#" class="text-primary fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#loginModal" style="color: #EF4444 !important;">Log In</a></p>
                </div>
            </div>
        </div>
    </div>

    <style>
        #forgotPasswordBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4) !important;
        }
        
        #ajaxForgotPasswordForm input[type="email"]:focus {
            border-color: #EF4444 !important;
            box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.15) !important;
            outline: none;
        }
    </style>

    <!-- Legal Content Modal (Terms & Privacy) -->
    <div class="modal fade" id="legalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem; overflow: hidden; height: 85vh;">
                <div class="modal-header border-bottom bg-surface py-3 px-4">
                    <h5 class="modal-title fw-bold text-main" id="legalModalTitle">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 custom-scrollbar" id="legalModalBody" style="overflow-y: auto; overflow-x: hidden;">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-surface py-2 px-4 justify-content-between">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <div>
                        <button type="button" class="btn btn-primary rounded-pill px-4" id="legalNextBtn" disabled>
                            Next <span class="material-symbols-outlined fs-6 align-middle ms-1">arrow_forward</span>
                        </button>
                        <button type="button" class="btn btn-success rounded-pill px-4 d-none" id="legalAgreeBtn" disabled>
                            I Accept <span class="material-symbols-outlined fs-6 align-middle ms-1">check</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password Visibility Toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('span');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // AJAX Login Handler
        document.getElementById('ajaxLoginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('loginBtn');
            const alert = document.getElementById('loginAlert');
            const alertText = document.getElementById('loginAlertText');
            const spinner = btn.querySelector('.spinner-border');

            // Simple validation
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Loading state
            btn.disabled = true;
            spinner.classList.remove('d-none');
            alert.classList.add('d-none');

            const formData = new FormData(form);

            fetch('login.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Login successful, redirect
                    window.location.href = data.redirect;
                } else {
                    // Show error
                    alertText.textContent = data.message;
                    alert.classList.remove('d-none');
                }
            })
            .catch(error => {
                alertText.textContent = "An error occurred. Please try again.";
                alert.classList.remove('d-none');
                console.error('Error:', error);
            })
            .finally(() => {
                btn.disabled = false;
                spinner.classList.add('d-none');
            });
        });

        document.getElementById('ajaxRegisterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('registerBtn');
            const alert = document.getElementById('registerAlert');
            const alertText = document.getElementById('registerAlertText');
            const successAlert = document.getElementById('registerSuccess'); // Not strictly used for step 1 but kept just in case
            const spinner = btn.querySelector('.spinner-border');
            const password = document.getElementById('regPassword').value;
            const confirm = document.getElementById('regConfirmPassword').value;

            // Client-side validations
            if (password !== confirm) {
                alertText.textContent = "Passwords do not match.";
                alert.classList.remove('d-none');
                return;
            }

            if (!document.getElementById('termsCheck').checked) {
                 alertText.textContent = "You must agree to the terms.";
                 alert.classList.remove('d-none');
                 return;
            }

            // Loading state
            btn.disabled = true;
            spinner.classList.remove('d-none');
            alert.classList.add('d-none');
            
            const formData = new FormData(form);
            formData.append('action', 'send_otp'); // Explicitly set action

            fetch('register.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.step === 'verify_otp') {
                    // Hide Register Modal
                    const registerModalEl = document.getElementById('registerModal');
                    const registerModal = bootstrap.Modal.getInstance(registerModalEl);
                    registerModal.hide();
                    
                    // Show OTP Modal
                    const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
                    otpModal.show();
                    
                    // Optional: Show success toast or message in OTP modal saying "Code sent"
                } else {
                    alertText.textContent = data.message;
                    alert.classList.remove('d-none');
                }
            })
            .catch(error => {
                alertText.textContent = "An error occurred. Please try again.";
                alert.classList.remove('d-none');
                console.error('Error:', error);
            })
            .finally(() => {
                btn.disabled = false;
                spinner.classList.add('d-none');
            });
        });

        // AJAX OTP Form Handler
        document.getElementById('ajaxOtpForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('verifyOtpBtn');
            const alert = document.getElementById('otpAlert');
            const alertText = document.getElementById('otpAlertText');
            const successAlert = document.getElementById('otpSuccess');
            const successText = document.getElementById('otpSuccessText');
            const spinner = btn.querySelector('.spinner-border');
            
            btn.disabled = true;
            spinner.classList.remove('d-none');
            alert.classList.add('d-none');
            successAlert.classList.add('d-none');
            
            const formData = new FormData(form);
            
            fetch('register.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successText.textContent = data.message;
                    successAlert.classList.remove('d-none');
                    form.reset();
                    
                    // Redirect to login
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else {
                    alertText.textContent = data.message;
                    alert.classList.remove('d-none');
                }
            })
            .catch(error => {
                alertText.textContent = "Verification failed. Please try again.";
                alert.classList.remove('d-none');
            })
            .finally(() => {
                btn.disabled = false;
                spinner.classList.add('d-none');
            });
        });

        // AJAX Forgot Password Handler
        document.getElementById('ajaxForgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('forgotPasswordBtn');
            const alert = document.getElementById('forgotPasswordAlert');
            const alertText = document.getElementById('forgotPasswordAlertText');
            const successAlert = document.getElementById('forgotPasswordSuccess');
            const successText = document.getElementById('forgotPasswordSuccessText');
            const spinner = btn.querySelector('.spinner-border');

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            btn.disabled = true;
            spinner.classList.remove('d-none');
            alert.classList.add('d-none');
            successAlert.classList.add('d-none');

            const formData = new FormData(form);

            fetch('forgot_password.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successText.textContent = data.message;
                    successAlert.classList.remove('d-none');
                    form.reset();
                } else {
                    alertText.textContent = data.message;
                    alert.classList.remove('d-none');
                }
            })
            .catch(error => {
                alertText.textContent = "An error occurred. Please try again.";
                alert.classList.remove('d-none');
                console.error('Error:', error);
            })
            .finally(() => {
                btn.disabled = false;
                spinner.classList.add('d-none');
            });
        });

        // Password Strength Meter (Simplified for client)
        document.getElementById('regPassword').addEventListener('input', function() {
            const val = this.value;
            const bar = document.getElementById('passwordStrengthBar');
            let strength = 0;
            if(val.length >= 12) strength += 25;
            if(/[A-Z]/.test(val)) strength += 25;
            if(/[0-9]/.test(val)) strength += 25;
            if(/[^a-zA-Z0-9]/.test(val)) strength += 25;
            
            bar.style.width = strength + '%';
            if(strength < 50) bar.className = 'progress-bar bg-danger';
            else if(strength < 75) bar.className = 'progress-bar bg-warning';
            else bar.className = 'progress-bar bg-success';
        });

        // Legal Modal Handler
        const legalModal = new bootstrap.Modal(document.getElementById('legalModal'));
        const legalModalEl = document.getElementById('legalModal');
        const legalModalTitle = document.getElementById('legalModalTitle');
        const legalModalBody = document.getElementById('legalModalBody');
        const legalNextBtn = document.getElementById('legalNextBtn');
        const legalAgreeBtn = document.getElementById('legalAgreeBtn');
        const termsCheck = document.getElementById('termsCheck');
        
        let currentLegalStep = 'terms'; // 'terms' or 'privacy'

        function checkScroll() {
            // Allow 5px buffer for float calculation differences
            if (legalModalBody.scrollTop + legalModalBody.clientHeight >= legalModalBody.scrollHeight - 5) {
                if (currentLegalStep === 'terms') {
                    legalNextBtn.disabled = false;
                } else {
                    legalAgreeBtn.disabled = false;
                }
            }
        }

        function loadLegalContent(type) {
            currentLegalStep = type;
            const title = type === 'privacy' ? 'Privacy Policy' : 'Terms and Conditions';
            legalModalTitle.textContent = title;
            
            // Reset state
            legalModalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            // Buttons state
            if (type === 'terms') {
                legalNextBtn.classList.remove('d-none');
                legalAgreeBtn.classList.add('d-none');
                legalNextBtn.disabled = true;
            } else {
                legalNextBtn.classList.add('d-none');
                legalAgreeBtn.classList.remove('d-none');
                legalAgreeBtn.disabled = true;
            }

            // Fetch content
            fetch(`terms.php?type=${type}&modal=1`)
                .then(response => response.text())
                .then(html => {
                    legalModalBody.innerHTML = html;
                    // Check if content is short enough to not need scroll
                    setTimeout(checkScroll, 100);
                })
                .catch(err => {
                    legalModalBody.innerHTML = `<div class="text-center py-5 text-danger">Error loading content.</div>`;
                });
        }

        // Event Listeners
        legalModalBody.addEventListener('scroll', checkScroll);

        legalNextBtn.addEventListener('click', () => {
            loadLegalContent('privacy');
            legalModalBody.scrollTop = 0; // Reset scroll
        });

        legalAgreeBtn.addEventListener('click', () => {
            termsCheck.checked = true;
            bootstrap.Modal.getInstance(legalModalEl).hide();
            // Optional visual feedback
            termsCheck.classList.add('is-valid');
            setTimeout(() => termsCheck.classList.remove('is-valid'), 2000);
        });

        // Trigger from Registration Form link
        document.addEventListener('DOMContentLoaded', () => {
            // Hijack the specific links in the registration form
            const regTermsLink = document.querySelector('label[for="termsCheck"] a[href="terms.php"]');
            
            if (regTermsLink) {
                regTermsLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    loadLegalContent('terms');
                    legalModal.show();
                });
            }
            
            
            // Handle footer links
            document.querySelectorAll('footer a[href="terms.php"], footer a[href="privacy.php"]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault(); 
                    loadLegalContent('terms');
                    legalModal.show();
                });
            });

            // Trigger modal when clicking the checkbox directly
            if (termsCheck) {
                termsCheck.addEventListener('click', (e) => {
                    if (!termsCheck.checked) return; // Allow unchecking if needed (though logic suggests it starts unchecked)
                    
                    // If trying to check it, intercept
                    e.preventDefault();
                    termsCheck.checked = false; // visual reset just in case
                    loadLegalContent('terms');
                    legalModal.show();
                });
            }
        });

        // Resend OTP Logic with Timer
        let resendTimerInterval;
        
        function resendOtp(e) {
            if (e) e.preventDefault();
            
            const link = document.getElementById('resendOtpLink');
            const timerSpan = document.getElementById('resendTimer');
            const timerCount = document.getElementById('timerCount');
            const alertText = document.getElementById('otpAlertText'); // Re-use OTP alert
            const alert = document.getElementById('otpAlert');
            
            // Disable link
            link.classList.add('disabled', 'text-muted');
            link.style.pointerEvents = 'none';
            
            // Re-trigger send_otp action using data from original form (stored in session on backend anyway, 
            // but we need to trigger the email resend). 
            // Actually, because we don't have the password anymore (frontend security), 
            // the backend session 'registration_temp' already has the data. 
            // We'll create a special action 'resend_otp' or just re-use 'send_otp' if we had the data.
            // Since we don't have the password, we should make a new endpoint or modify 'register.php' to support RESEND via session.
            // Let's modify register.php logic slightly or just send a 'resend_action' request.
            // WAIT - 'register.php' send_otp requires all fields.
            // We should add a 'resend_otp_code' action to register.php that uses the session data.
            
            // Let's assume we'll update register.php to handle 'resend_otp_code'.
            
            const formData = new FormData();
            formData.append('action', 'resend_otp_code');

            fetch('register.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Start Timer
                    startResendTimer();
                    alertText.textContent = "New code sent! Check your inbox (and spam).";
                    alert.classList.remove('d-none', 'alert-danger');
                    alert.classList.add('alert-success');
                    // Change icon
                    alert.querySelector('.material-symbols-outlined').textContent = 'check_circle';
                } else {
                    alertText.textContent = data.message;
                    alert.classList.remove('d-none', 'alert-success');
                    alert.classList.add('alert-danger');
                    alert.querySelector('.material-symbols-outlined').textContent = 'error';
                    // Re-enable if failed
                    link.classList.remove('disabled', 'text-muted');
                    link.style.pointerEvents = 'auto';
                }
            })
            .catch(error => {
                console.error(error);
                link.classList.remove('disabled', 'text-muted');
                link.style.pointerEvents = 'auto';
            });
        }

        function startResendTimer() {
            const link = document.getElementById('resendOtpLink');
            const timerSpan = document.getElementById('resendTimer');
            const timerCount = document.getElementById('timerCount');
            
            let seconds = 60;
            timerSpan.classList.remove('d-none');
            timerCount.textContent = seconds;
            
            clearInterval(resendTimerInterval);
            resendTimerInterval = setInterval(() => {
                seconds--;
                timerCount.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(resendTimerInterval);
                    timerSpan.classList.add('d-none');
                    link.classList.remove('disabled', 'text-muted');
                    link.style.pointerEvents = 'auto';
                }
            }, 1000);
        }

        // Loan Calculator Logic
        function calculateLoan() {
            const principal = parseFloat(document.getElementById('loanAmount').value) || 0;
            const annualRate = parseFloat(document.getElementById('interestRate').value) || 0;
            const months = parseInt(document.getElementById('loanTerm').value) || 1;

            // Monthly interest rate
            const monthlyRate = (annualRate / 100) / 12;

            // Calculate monthly payment using amortization formula
            // M = P * [r(1+r)^n] / [(1+r)^n - 1]
            let monthlyPayment = 0;
            if (monthlyRate === 0) {
                monthlyPayment = principal / months;
            } else {
                monthlyPayment = principal * (monthlyRate * Math.pow(1 + monthlyRate, months)) / 
                                (Math.pow(1 + monthlyRate, months) - 1);
            }

            const totalRepayment = monthlyPayment * months;
            const totalInterest = totalRepayment - principal;

            // Update display
            document.getElementById('monthlyPayment').textContent = '₱' + monthlyPayment.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('displayPrincipal').textContent = '₱' + principal.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('totalInterest').textContent = '₱' + totalInterest.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('totalRepayment').textContent = '₱' + totalRepayment.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Sync inputs with range sliders
        const loanAmountInput = document.getElementById('loanAmount');
        const loanAmountRange = document.getElementById('loanAmountRange');
        const interestRateInput = document.getElementById('interestRate');
        const interestRateRange = document.getElementById('interestRateRange');
        const loanTermInput = document.getElementById('loanTerm');
        const loanTermRange = document.getElementById('loanTermRange');

        // Loan Amount sync
        loanAmountInput.addEventListener('input', function() {
            loanAmountRange.value = this.value;
            calculateLoan();
        });
        loanAmountRange.addEventListener('input', function() {
            loanAmountInput.value = this.value;
            calculateLoan();
        });

        // Interest Rate sync
        interestRateInput.addEventListener('input', function() {
            interestRateRange.value = this.value;
            calculateLoan();
        });
        interestRateRange.addEventListener('input', function() {
            interestRateInput.value = this.value;
            calculateLoan();
        });

        // Loan Term sync
        loanTermInput.addEventListener('input', function() {
            loanTermRange.value = this.value;
            calculateLoan();
        });
        loanTermRange.addEventListener('input', function() {
            loanTermInput.value = this.value;
            calculateLoan();
        });

        // Calculate on page load
        document.addEventListener('DOMContentLoaded', calculateLoan);
    </script>
</body>
</html>

