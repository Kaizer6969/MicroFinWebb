<?php
require_once '../backend/db_connect.php';
require_once '../backend/mobile_app_build.php';
require_once __DIR__ . '/install_attribution.php';

$renderUnavailable = static function (string $title, string $message): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</title></head><body style="font-family: \'Plus Jakarta Sans\', Arial, sans-serif; background:#f7f3e8; color:#1f2d25; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0;"><div style="max-width:420px; background:#fffdf7; border:transparent; border-radius:18px; padding:32px; text-align:center; box-shadow:0 18px 40px rgba(54,43,12,0.12);"><h1 style="margin:0 0 12px; font-size:1.5rem;">'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</h1><p style="margin:0; color:#475569; line-height:1.6;">'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p></div></body></html>';
    exit;
};

$requestedRoute = mf_install_requested_route();
if ($requestedRoute === 'get-app') {
    $tenantIdentifier = trim((string)($_GET['bank_id'] ?? $_GET['tenant'] ?? $_GET['tenant_slug'] ?? $_GET['site'] ?? ''));
    $tenant = mf_install_resolve_tenant($pdo, $tenantIdentifier);

    if (!is_array($tenant)) {
        $renderUnavailable(
            'App download unavailable',
            'We could not find the requested tenant download link. Please return to the bank website and try again.'
        );
    }

    $apkAsset = mf_install_resolve_apk_asset($tenant, false);
    if (empty($apkAsset['path']) && empty($apkAsset['url'])) {
        $buildState = mf_mobile_app_get_build_state($pdo, (string) ($tenant['tenant_id'] ?? ''));
        $status = trim((string) ($buildState['status'] ?? ''));
        $message = 'This tenant-specific app has not been published yet. Please contact the institution or try again after their mobile build is uploaded.';

        if ($status === 'queued') {
            $message = 'This tenant-specific app is being built right now. Please try again in a few minutes.';
        } elseif ($status === 'configuration_required') {
            $message = 'This tenant-specific app could not be built automatically yet because the mobile build integration is not configured on the server.';
        } elseif ($status === 'failed') {
            $message = trim((string) ($buildState['message'] ?? ''));
            if ($message === '') {
                $message = 'This tenant-specific app build failed to start. Please contact the institution and try again later.';
            }
        }

        $renderUnavailable(
            'Tenant app not ready',
            $message
        );
    }

    $download = mf_install_record_download($pdo, $tenant, $apkAsset);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!empty($apkAsset['path'])) {
        mf_install_stream_apk((string)$apkAsset['path'], (string)$apkAsset['filename']);
        exit;
    }

    header('Location: ' . (string)($apkAsset['url'] ?? $download['apk_url']), true, 302);
    exit;
}

if ($requestedRoute === 'get-generic-app') {
    $apkAsset = mf_install_resolve_generic_apk_asset();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!empty($apkAsset['path'])) {
        mf_install_stream_apk((string)$apkAsset['path'], (string)$apkAsset['filename']);
        exit;
    }

    header('Location: ' . (string)($apkAsset['url'] ?? mf_install_generic_apk_url()), true, 302);
    exit;
}

// Fetch active tenants to display in the "Trusted By" section
$stmt = $pdo->query("SELECT tenant_name FROM tenants WHERE status = 'Active' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5");
$active_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_count = $pdo->query("SELECT COUNT(*) as count FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
$tenant_count = $stmt_count->fetch(PDO::FETCH_ASSOC)['count'];
$powered_by_count = $tenant_count > 0 ? $tenant_count : "leading";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin | The Cloud Banking Platform for Modern MFIs</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/style.css')); ?>">
    <link rel="stylesheet" href="sarah-chatbot.css?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/sarah-chatbot.css')); ?>">
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">
                <span class="material-symbols-rounded">account_balance</span>
                <span class="logo-text">MicroFin</span>
            </div>
            
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#how-it-works">How it Works</a>
                <a href="#security">Security</a>
            </div>
            
            <div class="nav-cta">
                <a href="javascript:void(0)" id="darkModeToggle" class="nav-btn-link material-symbols-rounded" aria-label="Toggle Dark Mode">dark_mode</a>
                <a href="../super_admin/login.php" class="btn btn-login">Platform Login</a>
                <a href="demo.php" class="btn btn-primary">Apply Now</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="container hero-container">
            <div class="hero-content">
                <div class="badge-pill">SaaS for Microfinance</div>
                <h1>Empower your institution with a true cloud core banking system.</h1>
                <p>MicroFin is a fully isolated, multi-tenant cloud banking platform designed specifically for Microfinance Institutions, SACCOs, and Cooperatives.</p>
                
                <div class="hero-actions">
                    <a href="demo.php" class="btn btn-primary btn-lg">Apply Now</a>
                    <button type="button" class="btn btn-outline btn-lg js-open-sarah-chat">Chat with Sarah</button>
                </div>
                <div class="trust-marks">
                    <span>Trusted by <?php echo $powered_by_count; ?> microfinance institutions <?php if($powered_by_count > 0) echo "including:"; ?></span>
                    <?php if (!empty($active_tenants)): ?>
                    <div class="trusted-tenants-list">
                        <?php foreach($active_tenants as $tenant): ?>
                            <span class="trusted-tenant-badge">
                                <span class="material-symbols-rounded">corporate_fare</span>
                                <?php echo htmlspecialchars($tenant['tenant_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-image">
                <div class="mockup-window">
                    <div class="mockup-header">
                        <div class="dot red"></div><div class="dot yellow"></div><div class="dot green"></div>
                    </div>
                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&q=80&w=800&h=500" alt="Dashboard Preview" class="mockup-img">
                </div>
            </div>
        </div>
    </header>

    <!-- Features Grid -->
    <section id="features" class="section bg-light showcase-features-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Built for Scale, Designed for Security</h2>
                <p>Everything your cooperative needs to operate digitally, out of the box.</p>
            </div>
            
            <div class="grid-3 showcase-feature-grid">
                <!-- Feature 1 -->
                <div class="feature-card feature-card-cosmos">
                    <div class="feature-icon"><span class="material-symbols-rounded">dns</span></div>
                    <h3>Multi-Tenant Architecture</h3>
                    <p>Your data is perfectly isolated. Experience enterprise-grade security where your institution's records are completely separated from others.</p>
                </div>
                <!-- Feature 2 -->
                <div class="feature-card feature-card-guidance">
                    <div class="feature-icon"><span class="material-symbols-rounded">palette</span></div>
                    <h3>Fully Whitelabeled</h3>
                    <p>It's your brand. Upload your logo, change your color themes, and instantly transform the dashboard to look like your own proprietary software.</p>
                </div>
                <!-- Feature 3 -->
                <div class="feature-card feature-card-vault">
                    <div class="feature-icon"><span class="material-symbols-rounded">account_balance</span></div>
                    <h3>Core Banking Engine</h3>
                    <p>Automated loan origination, savings management, and real-time interest calculation baked directly into the platform core.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Extended Capabilities -->
    <section id="capabilities" class="section bg-white">
        <div class="container">
            <div class="section-header text-center">
                <h2>Beyond Core Banking</h2>
                <p>Advanced tools completely integrated into your ecosystem to drive growth and efficiency.</p>
            </div>
            
            <div class="grid-3">
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">monitoring</span></div>
                    <h3>Advanced Analytics</h3>
                    <p>Generate real-time PAR (Portfolio at Risk) reports, balance sheets, and income statements with one click.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">sms</span></div>
                    <h3>Automated Notifications</h3>
                    <p>Send automated SMS and email reminders to borrowers for upcoming dues, reducing default rates automatically.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">api</span></div>
                    <h3>API-Ready & Integrations</h3>
                    <p>Connect seamlessly with payment gateways, credit bureaus, and external accounting tools via secure APIs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="section bg-white text-dark">
        <div class="container">
            <div class="section-header text-center">
                <h2>Simple, Transparent Pricing</h2>
                <p>Scale your financial institution with plans designed for growth. No hidden fees.</p>
            </div>
            
            <div class="pricing-grid">
                <!-- Starter -->
                <div class="pricing-card pricing-card-starter">
                    <div class="pricing-header">
                        <h3>Starter</h3>
                        <div class="price">&#8369;4,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>1,000</strong> Max Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>250</strong> Max Staffs</li>
                    </ul>
                </div>
                
                <!-- Pro -->
                <div class="pricing-card pricing-card-pro popular">
                    <div class="popular-badge">Most Chosen</div>
                    <div class="pricing-header">
                        <h3>Pro</h3>
                        <div class="price">&#8369;14,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>5,000</strong> Max Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>2,000</strong> Max Staffs</li>
                    </ul>
                </div>

                <!-- Enterprise -->
                <div class="pricing-card pricing-card-enterprise">
                    <div class="pricing-header">
                        <h3>Enterprise</h3>
                        <div class="price">&#8369;19,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>10,000</strong> Max Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>5,000</strong> Max Staffs</li>
                    </ul>
                </div>

                <!-- Unlimited -->
                <div class="pricing-card pricing-card-unlimited">
                    <div class="pricing-header">
                        <h3>Unlimited</h3>
                        <div class="price">&#8369;29,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>Unlimited</strong> Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>Unlimited</strong> Max Staffs</li>
                    </ul>
                </div>
            </div>
            
            
        </div>
    </section>

    <!-- How it Works Flow -->
    <section id="how-it-works" class="section bg-light">
        <div class="container">
            <div class="section-header">
                <h2>Go live in days, not months.</h2>
                <p>Because it's a SaaS platform, we handle the infrastructure. You just run your business.</p>
            </div>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot">1</div>
                    <div class="timeline-content">
                        <h3>Book a Discovery Call</h3>
                        <p>We meet to understand your current loan volume, data migration needs, and compliance requirements.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot">2</div>
                    <div class="timeline-content">
                        <h3>Instant Provisioning</h3>
                        <p>Once approved, our Super Admins spin up your isolated database environment in seconds.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot">3</div>
                    <div class="timeline-content">
                        <h3>Your Custom Dashboard</h3>
                        <p>You receive an invite to your brand new Admin Panel. Change the colors, add your staff, and start issuing loans immediately.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Section -->
    <section id="security" class="section bg-white security-trust-section">
        <div class="container container-flex security-shell">
            <div class="security-content security-panel">
                <span class="badge-pill badge-pill-accent">Bank-Grade Security</span>
                <h2 class="security-title">Your data is encrypted, isolated, and continuously backed up.</h2>
                <ul class="security-list">
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <div class="security-copy">
                            <strong>Strict Tenant Isolation</strong>
                            <span>Every institution has its own dedicated database schema. Commingling of records is impossible.</span>
                        </div>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <div class="security-copy">
                            <strong>End-to-End Encryption</strong>
                            <span>All data in transit and at rest is secured using AES-256 and TLS 1.3 standards.</span>
                        </div>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <div class="security-copy">
                            <strong>Automated Backups & Redundancy</strong>
                            <span>Multi-region data replication ensures you never lose a single transaction record, even in hardware failure events.</span>
                        </div>
                    </li>
                </ul>
                <a href="#contact" class="btn btn-outline security-cta">Read our Security Whitepaper</a>
            </div>
            <div class="security-image security-vault-card">
                <span class="security-image-badge">Always-on resilience</span>
                <span class="material-symbols-rounded">gpp_good</span>
                <div>ISO 27001 & PCI-DSS Compliant Infrastructure</div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="contact" class="section text-white contact-cta-section">
        <div class="contact-cta-glow"></div>
        <div class="container contact-cta-container">
            <span class="contact-cta-badge">MicroFin Cloud Onboarding</span>
            <h2>Ready to modernize your cooperative?</h2>
            <p class="contact-cta-subtitle">Leave legacy desktop software behind. Let our team migrate your data to the cloud seamlessly.</p>
            <div class="contact-cta-buttons">
                <a href="demo.php" class="btn btn-primary btn-lg">
                    <span class="material-symbols-rounded" style="font-size: 20px; margin-right: 8px; vertical-align: middle;">calendar_month</span>
                    Apply Now
                </a>
                <button type="button" class="btn btn-outline btn-lg js-open-sarah-chat">
                    <span class="material-symbols-rounded" style="font-size: 20px; margin-right: 8px; vertical-align: middle;">smart_toy</span>
                    Chat with Sarah
                </button>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer footer-galaxy">
        <div class="footer-top-line"></div>
        <div class="container footer-grid">
            <div class="footer-brand">
                <div class="logo">
                    <span class="material-symbols-rounded">account_balance</span>
                    <span class="logo-text">MicroFin</span>
                </div>
                <p>The developer-first banking platform enabling financial inclusion across the globe.</p>
            </div>
            <div class="footer-links">
                <h4>Product</h4>
                <a href="#">Core Banking</a>
                <a href="#">Security</a>
                <a href="#">Pricing</a>
            </div>
            <div class="footer-links">
                <h4>Company</h4>
                <a href="#">About Us</a>
                <a href="#">Careers</a>
                <a href="javascript:void(0)" class="js-open-sarah-chat">Chat with Sarah</a>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; 2026 MicroFin Platform. All rights reserved.</p>
        </div>
    </footer>

    <div class="sarah-chatbot" data-sarah-chatbot>
        <section class="sarah-chatbot-window" id="sarah-chatbot-window" hidden aria-label="Sarah chatbot">
            <div class="sarah-chatbot-header">
                <div class="sarah-chatbot-header-copy">
                    <div class="sarah-chatbot-avatar">S</div>
                    <div>
                        <strong>Sarah</strong>
                        <span>MicroFin virtual assistant</span>
                    </div>
                </div>
                <button type="button" class="sarah-chatbot-close" aria-label="Close Sarah chat">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="sarah-chatbot-messages" aria-live="polite"></div>
            <div class="sarah-chatbot-actions">
                <button type="button" class="sarah-chatbot-chip" data-prompt="Pricing">Pricing</button>
                <button type="button" class="sarah-chatbot-chip" data-prompt="Security">Security</button>
                <button type="button" class="sarah-chatbot-chip" data-prompt="Setup time">Setup time</button>
                <button type="button" class="sarah-chatbot-chip" data-prompt="Talk to an agent">Talk to an Agent</button>
                <button type="button" class="sarah-chatbot-chip" data-prompt="Apply now">Apply now</button>
            </div>
            <form class="sarah-chatbot-form">
                <input type="text" class="sarah-chatbot-input" aria-label="Message Sarah" placeholder="Ask Sarah a question" autocomplete="off">
                <button type="submit" class="sarah-chatbot-send" aria-label="Send message">
                    <span class="material-symbols-rounded">send</span>
                </button>
            </form>
        </section>
        <button type="button" class="sarah-chatbot-launcher" aria-controls="sarah-chatbot-window" aria-expanded="false">
            <span class="material-symbols-rounded">smart_toy</span>
            <span class="sarah-chatbot-launcher-copy">
                <strong>Sarah</strong>
                <span>Need help?</span>
            </span>
        </button>
    </div>

    <script src="script.js"></script>
    <script src="sarah-chatbot.js?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/sarah-chatbot.js')); ?>"></script>
</body>
</html>


