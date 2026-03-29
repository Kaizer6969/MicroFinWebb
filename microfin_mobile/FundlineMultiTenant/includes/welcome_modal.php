<!-- Welcome Onboarding Modal -->
<div class="modal fade" id="welcomeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden">
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Image Side (Desktop) -->
                    <div class="col-lg-5 d-none d-lg-block position-relative bg-primary">
                        <img src="../assets/image/login-banner.png" alt="Welcome" class="w-100 h-100 object-fit-cover opacity-75" style="mix-blend-mode: overlay;">
                        <div class="position-absolute bottom-0 start-0 w-100 p-4 text-white bg-gradient-to-t from-black">
                            <h4 class="fw-bold mb-1">Fundline</h4>
                            <p class="small opacity-75 mb-0">Smart. Fast. Secure.</p>
                        </div>
                    </div>
                    
                    <!-- Content Side -->
                    <div class="col-lg-7">
                        <div class="p-4 p-lg-5 h-100 d-flex flex-column justify-content-center">
                            
                            <div id="onboardingCarousel" class="carousel slide" data-bs-interval="false">
                                <!-- Indicators -->
                                <div class="carousel-indicators position-relative justify-content-start mx-0 mb-4 gap-2">
                                    <button type="button" data-bs-target="#onboardingCarousel" data-bs-slide-to="0" class="active rounded-circle bg-primary" style="width: 10px; height: 10px; border: none;"></button>
                                    <button type="button" data-bs-target="#onboardingCarousel" data-bs-slide-to="1" class="rounded-circle bg-primary" style="width: 10px; height: 10px; border: none; opacity: 0.3;"></button>
                                    <button type="button" data-bs-target="#onboardingCarousel" data-bs-slide-to="2" class="rounded-circle bg-primary" style="width: 10px; height: 10px; border: none; opacity: 0.3;"></button>
                                    <button type="button" data-bs-target="#onboardingCarousel" data-bs-slide-to="3" class="rounded-circle bg-primary" style="width: 10px; height: 10px; border: none; opacity: 0.3;"></button>
                                    <button type="button" data-bs-target="#onboardingCarousel" data-bs-slide-to="4" class="rounded-circle bg-primary" style="width: 10px; height: 10px; border: none; opacity: 0.3;"></button>
                                </div>

                                <div class="carousel-inner">
                                    <!-- Slide 1: Welcome -->
                                    <div class="carousel-item active">
                                        <div class="mb-4">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-inline-flex mb-3">
                                                <span class="material-symbols-outlined fs-2">waving_hand</span>
                                            </div>
                                            <h2 class="fw-bold text-main mb-3">Welcome to Fundline!</h2>
                                            <p class="text-muted lead mb-3">Your trusted microfinancing partner in Marilao, Bulacan. We provide fast, secure, and flexible loan solutions tailored to your needs.</p>
                                            <div class="bg-light rounded-3 p-3">
                                                <h6 class="fw-bold text-main mb-2"><span class="material-symbols-outlined fs-6 align-middle me-1">info</span> Quick Facts</h6>
                                                <ul class="small text-muted mb-0 ps-3">
                                                    <li><strong>3 Loan Types:</strong> Personal, Business, and Emergency loans</li>
                                                    <li><strong>Loan Range:</strong> ₱3,000 - ₱200,000</li>
                                                    <li><strong>Flexible Terms:</strong> 1 to 36 months</li>
                                                    <li><strong>Competitive Rates:</strong> Starting at 2% monthly</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Slide 2: Credit System -->
                                    <div class="carousel-item">
                                        <div class="mb-4">
                                            <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 d-inline-flex mb-3">
                                                <span class="material-symbols-outlined fs-2">account_balance_wallet</span>
                                            </div>
                                            <h2 class="fw-bold text-main mb-3">Your Credit System</h2>
                                            <p class="text-muted lead mb-3">Earn and grow your credit limit through responsible borrowing and timely payments.</p>
                                            <div class="bg-light rounded-3 p-3 mb-3">
                                                <h6 class="fw-bold text-main mb-2"><span class="material-symbols-outlined fs-6 align-middle me-1">trending_up</span> Credit Tiers</h6>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="small"><strong>Tier 1:</strong> ₱5,000</span>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary">Starter</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="small"><strong>Tier 2:</strong> ₱15,000</span>
                                                    <span class="badge bg-success bg-opacity-10 text-success">Bronze</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="small"><strong>Tier 3:</strong> ₱50,000</span>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning">Silver</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="small"><strong>Tier 4:</strong> ₱100,000+</span>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger">Gold</span>
                                                </div>
                                            </div>
                                            <p class="small text-muted mb-0"><span class="material-symbols-outlined fs-6 align-middle me-1">info</span> Your credit score is based on income, employment, payment history, and character assessment.</p>
                                        </div>
                                    </div>

                                    <!-- Slide 3: Dashboard Features -->
                                    <div class="carousel-item">
                                        <div class="mb-4">
                                            <div class="bg-info bg-opacity-10 text-info rounded-circle p-3 d-inline-flex mb-3">
                                                <span class="material-symbols-outlined fs-2">dashboard</span>
                                            </div>
                                            <h2 class="fw-bold text-main mb-3">Your Dashboard</h2>
                                            <p class="text-muted lead mb-3">Monitor everything in real-time from your personalized dashboard.</p>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <div class="bg-light rounded-3 p-3 text-center">
                                                        <span class="material-symbols-outlined text-primary fs-3 d-block mb-1">account_balance</span>
                                                        <small class="fw-bold d-block">Credit Limit</small>
                                                        <small class="text-muted">Track available credit</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="bg-light rounded-3 p-3 text-center">
                                                        <span class="material-symbols-outlined text-success fs-3 d-block mb-1">payments</span>
                                                        <small class="fw-bold d-block">Payments</small>
                                                        <small class="text-muted">View transaction history</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="bg-light rounded-3 p-3 text-center">
                                                        <span class="material-symbols-outlined text-warning fs-3 d-block mb-1">description</span>
                                                        <small class="fw-bold d-block">Active Loans</small>
                                                        <small class="text-muted">Manage your loans</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="bg-light rounded-3 p-3 text-center">
                                                        <span class="material-symbols-outlined text-danger fs-3 d-block mb-1">event</span>
                                                        <small class="fw-bold d-block">Next Payment</small>
                                                        <small class="text-muted">Stay on schedule</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Slide 4: Payment & Security -->
                                    <div class="carousel-item">
                                        <div class="mb-4">
                                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 d-inline-flex mb-3">
                                                <span class="material-symbols-outlined fs-2">shield</span>
                                            </div>
                                            <h2 class="fw-bold text-main mb-3">Payment & Security</h2>
                                            <p class="text-muted lead mb-3">Multiple payment options and bank-level security for your peace of mind.</p>
                                            <div class="bg-light rounded-3 p-3 mb-3">
                                                <h6 class="fw-bold text-main mb-2"><span class="material-symbols-outlined fs-6 align-middle me-1">payment</span> Payment Methods</h6>
                                                <ul class="small text-muted mb-0 ps-3">
                                                    <li><strong>GCash:</strong> Instant online payments</li>
                                                    
                                                </ul>
                                            </div>
                                            <div class="bg-light rounded-3 p-3">
                                                <h6 class="fw-bold text-main mb-2"><span class="material-symbols-outlined fs-6 align-middle me-1">lock</span> Security Features</h6>
                                                <ul class="small text-muted mb-0 ps-3">
                                                    <li>Encrypted data transmission</li>
                                                    <li>Secure document storage</li>
                                                    <li>Audit trail for all transactions</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Slide 5: Get Started -->
                                    <div class="carousel-item">
                                        <div class="mb-4">
                                            <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3 d-inline-flex mb-3">
                                                <span class="material-symbols-outlined fs-2">rocket_launch</span>
                                            </div>
                                            <h2 class="fw-bold text-main mb-3">Ready to Start?</h2>
                                            <p class="text-muted lead mb-3">Apply for your first loan in just a few simple steps!</p>
                                            <div class="bg-light rounded-3 p-3 mb-3">
                                                <h6 class="fw-bold text-main mb-3"><span class="material-symbols-outlined fs-6 align-middle me-1">checklist</span> Application Process</h6>
                                                <div class="d-flex align-items-start gap-3 mb-2">
                                                    <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">1</span>
                                                    <div class="flex-grow-1">
                                                        <small class="fw-bold d-block">Complete Your Profile</small>
                                                        <small class="text-muted">Provide personal and employment details</small>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-start gap-3 mb-2">
                                                    <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">2</span>
                                                    <div class="flex-grow-1">
                                                        <small class="fw-bold d-block">Upload Documents</small>
                                                        <small class="text-muted">Valid ID, proof of income, and address</small>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-start gap-3 mb-2">
                                                    <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">3</span>
                                                    <div class="flex-grow-1">
                                                        <small class="fw-bold d-block">Choose Your Loan</small>
                                                        <small class="text-muted">Select amount, term, and purpose</small>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-start gap-3">
                                                    <span class="badge bg-success rounded-circle" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">✓</span>
                                                    <div class="flex-grow-1">
                                                        <small class="fw-bold d-block">Get Approved & Funded</small>
                                                        <small class="text-muted">Receive funds within 1-3 business days</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="alert alert-info border-0 mb-0">
                                                <small><span class="material-symbols-outlined fs-6 align-middle me-1">support_agent</span> <strong>Need help?</strong> Our support team is available Mon-Sat, 8AM-5PM at our Marilao branch.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Controls -->
                                <div class="d-flex align-items-center gap-3 mt-4">
                                    <button class="btn btn-light rounded-pill px-4 fw-bold" id="skipTour" data-bs-dismiss="modal">Skip</button>
                                    <button class="btn btn-primary rounded-pill px-5 fw-bold ms-auto d-flex align-items-center gap-2" id="nextSlide">
                                        Next <span class="material-symbols-outlined fs-5">arrow_forward</span>
                                    </button>
                                    <button class="btn btn-primary rounded-pill px-5 fw-bold ms-auto d-none align-items-center gap-2" id="finishTour">
                                        Get Started <span class="material-symbols-outlined fs-5">check</span>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const carousel = document.getElementById('onboardingCarousel');
        const bsCarousel = new bootstrap.Carousel(carousel, { wrap: false });
        const nextBtn = document.getElementById('nextSlide');
        const finishBtn = document.getElementById('finishTour');
        const skipBtn = document.getElementById('skipTour');
        
        let slideIndex = 0;
        const totalSlides = 5;

        // Custom Next Logic
        nextBtn.addEventListener('click', () => {
            bsCarousel.next();
        });

        carousel.addEventListener('slid.bs.carousel', function (e) {
            slideIndex = e.to;
            // Update buttons
            if (slideIndex === totalSlides - 1) {
                nextBtn.classList.add('d-none');
                nextBtn.classList.remove('d-flex');
                finishBtn.classList.remove('d-none');
                finishBtn.classList.add('d-flex');
                skipBtn.classList.add('d-none');
            } else {
                nextBtn.classList.remove('d-none');
                nextBtn.classList.add('d-flex');
                finishBtn.classList.add('d-none');
                finishBtn.classList.remove('d-flex');
                skipBtn.classList.remove('d-none');
            }
        });

        // Finish Action
        const completeTour = () => {
            fetch('update_tour_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ seen: true })
            }).then(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('welcomeModal'));
                modal.hide();
            });
        };

        finishBtn.addEventListener('click', completeTour);
        skipBtn.addEventListener('click', completeTour); // Skip also marks as seen? Yes, usually.
    });
</script>

