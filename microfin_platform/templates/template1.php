<?php
/**
 * Template 1 — Bootstrap 5 Premium Modern Design
 * All variables passed from site.php via include scope.
 */
$headline_font = 'Manrope';
$body_font = $font_family ?: 'Public Sans';
$p = $palette;

$hero_badge_text   = $hero_badge_text ?? '';
$stats_heading     = $stats_heading ?? 'Building Trust Through Numbers';
$stats_subheading  = $stats_subheading ?? '';
$stats_image       = $stats_image ?? '';
$stats             = $stats ?? [];
$loan_products     = $loan_products ?? [];
$partners          = $partners ?? [];
$show_partners     = $show_partners ?? false;
$show_stats        = $show_stats ?? true;
$show_loan_calc    = $show_loan_calc ?? true;
$footer_description = $footer_description ?? '';
$hero_bg_path      = $hero_bg_path ?? '';
$show_download_section = $show_download_section ?? false;
$download_title    = $download_title ?? 'Download Our App';
$download_description = $download_description ?? 'Track your loans, get updates, and manage your account on the go.';
$download_button_text = $download_button_text ?? 'Download App';
$download_url      = $download_url ?? '';
$mobile_app_web_url = $mobile_app_web_url ?? '';

// Build the effective Flutter link: prefer web app URL + tenant slug, else fall back to store URL
$_flutter_slug       = $slug ?? '';
$_has_flutter_web    = ($mobile_app_web_url !== '');
$_flutter_web_link   = $_has_flutter_web
    ? rtrim($mobile_app_web_url, '/')
      . (str_contains($mobile_app_web_url, '?') ? '&' : '?')
      . 'tenant=' . urlencode($_flutter_slug)
    : '';
// The best link to show on the download button: Flutter web first, then store URL, then empty
$_download_href      = $_flutter_web_link ?: $download_url;

$primary   = $p['primary'];
$onPrimary = $p['on-primary'];
$secondary = $p['secondary'];
$primaryRgb = hexToRgb($primary);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?php echo $e($tenant_name); ?> — Official Website</title>
<?php if ($meta_desc): ?><meta name="description" content="<?php echo $e($meta_desc); ?>"><?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($headline_font); ?>:wght@400;600;700;800&family=<?php echo urlencode($body_font); ?>:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0&display=swap" rel="stylesheet"/>
<style>
/* ─── Design Tokens ─────────────────────────────── */
:root {
    --brand:      <?php echo $primary; ?>;
    --brand-rgb:  <?php echo $primaryRgb; ?>;
    --brand-dark: <?php echo adjustColor($primary, -20); ?>;
    --brand-light:<?php echo adjustColor($primary, 75); ?>;
    --brand-text: <?php echo $onPrimary; ?>;
    --accent:     <?php echo $secondary; ?>;
    --bs-body-font-family: '<?php echo $e($body_font); ?>', sans-serif;
    --bs-body-bg: #f8fafc;
    --bs-body-color: #1e293b;
}
h1,h2,h3,h4,h5,.headline { font-family: '<?php echo $e($headline_font); ?>', sans-serif; }

/* ─── Overrides ──────────────────────────────────── */
.btn-brand        { background: var(--brand); color: var(--brand-text); border: none; }
.btn-brand:hover  { background: var(--brand-dark); color: var(--brand-text); }
.btn-brand-outline{ border: 2px solid var(--brand); color: var(--brand); background: transparent; }
.btn-brand-outline:hover { background: var(--brand); color: var(--brand-text); }
.text-brand       { color: var(--brand) !important; }
.bg-brand         { background-color: var(--brand) !important; }
.border-brand     { border-color: var(--brand) !important; }
.material-symbols-rounded { vertical-align: middle; }

/* ─── Navbar ─────────────────────────────────────── */
.site-nav {
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(var(--brand-rgb),.08);
    transition: box-shadow .3s;
}
.site-nav.scrolled { box-shadow: 0 4px 24px rgba(0,0,0,.08); }
.nav-link { font-weight: 600; font-size: .9rem; color: #475569; transition: color .2s; }
.nav-link:hover, .nav-link.active { color: var(--brand) !important; }
.nav-link.active { position: relative; }
.nav-link.active::after { content:''; position:absolute; bottom:0; left:0; right:0; height:2px; border-radius:2px; background:var(--brand); }

/* ─── Hero ───────────────────────────────────────── */
.hero-wrap {
    min-height: 88vh;
    display: flex;
    align-items: center;
    padding: 100px 0 60px;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 50%, #f0fdf4 100%);
}

/* ─── Hero Visual Card (offline-safe illustration) ── */
.hero-illus {
    width: 100%; height: 100%;
    background: linear-gradient(145deg, var(--brand-light) 0%, #f1f5f9 100%);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 16px; position: relative; overflow: hidden; padding: 28px 24px;
}
.hero-illus::before {
    content: '';
    position: absolute; width: 220px; height: 220px; border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--brand-rgb),.12), transparent 70%);
    top: -60px; right: -60px;
}
.hi-card {
    background: #fff; border-radius: 16px; padding: 14px 18px;
    box-shadow: 0 4px 20px rgba(0,0,0,.08); width: 100%; display: flex;
    align-items: center; gap: 12px; position: relative; z-index: 1;
}
.hi-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink:0; }
.hi-primary { background: var(--brand); }
.hi-green   { background: #10b981; }
.hi-amber   { background: #f59e0b; }
.hi-label { font-size: .7rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.hi-value { font-size: 1.1rem; font-weight: 800; color: #0f172a; font-family: var(--bs-body-font-family); }
.hi-badge {
    background: rgba(var(--brand-rgb),.08); border: 1px solid rgba(var(--brand-rgb),.15);
    border-radius: 999px; padding: 5px 14px; font-size: .75rem; font-weight: 700;
    color: var(--brand); display: inline-flex; align-items: center; gap: 6px;
}
.hi-bar-wrap { width: 100%; position: relative; z-index: 1; }
.hi-bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.hi-bar-label { font-size: .68rem; color: #64748b; width: 58px; flex-shrink: 0; }
.hi-bar-track { flex: 1; height: 8px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.hi-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--brand), var(--accent)); }

/* ─── Stats Band Visual (offline-safe) ─────────────── */
.stats-illus {
    width: 100%; min-height: 360px;
    background: linear-gradient(145deg, rgba(var(--brand-rgb),.18) 0%, rgba(var(--brand-rgb),.08) 100%);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 18px; padding: 32px 28px;
    position: relative; overflow: hidden;
}
.stats-illus::after {
    content: '';
    position: absolute; width: 260px; height: 260px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,.15), transparent 70%);
    bottom: -80px; left: -80px;
}
.si-row { display: flex; gap: 12px; width: 100%; z-index: 1; }
.si-col { flex: 1; background: rgba(255,255,255,.15); border-radius: 14px; padding: 14px 12px; text-align: center; backdrop-filter: blur(6px); }
.si-num { font-size: 1.6rem; font-weight: 800; color: #fff; line-height: 1; }
.si-lbl { font-size: .65rem; color: rgba(255,255,255,.65); text-transform: uppercase; letter-spacing: .08em; margin-top: 4px; font-weight: 600; }
.si-chart { width: 100%; background: rgba(255,255,255,.1); border-radius: 14px; padding: 14px 16px; z-index: 1; }
.si-chart-bars { display: flex; align-items: flex-end; gap: 6px; height: 60px; }
.si-bar { flex: 1; border-radius: 6px 6px 0 0; background: rgba(255,255,255,.3); transition: height .3s; }

/* ─── About Visual (offline-safe) ───────────────────── */
.about-illus {
    width: 100%; height: 100%;
    background: linear-gradient(145deg, var(--brand-light) 0%, #e0f2fe 100%);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 14px;
    padding: 36px 28px; position: relative; overflow: hidden;
}
.about-illus::before {
    content: ''; position: absolute; width: 200px; height: 200px; border-radius: 50%;
    background: rgba(var(--brand-rgb),.08);
    top: -50px; right: -50px;
}
.ai-avatar-row { display: flex; gap: -8px; z-index: 1; }
.ai-avatar {
    width: 52px; height: 52px; border-radius: 50%;
    border: 3px solid #fff; display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; margin-left: -10px; box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.ai-avatar:first-child { margin-left: 0; }
.ai-stat {
    background: #fff; border-radius: 16px; padding: 18px 22px; width: 100%;
    box-shadow: 0 4px 20px rgba(0,0,0,.06); z-index: 1; text-align: center;
}
.ai-stat-num { font-size: 2rem; font-weight: 800; color: var(--brand); line-height: 1; }
.ai-stat-lbl { font-size: .72rem; font-weight: 600; text-transform: uppercase; color: #94a3b8; letter-spacing: .08em; margin-top: 4px; }
.ai-pill {
    background: #fff; border-radius: 999px; padding: 8px 16px;
    font-size: .78rem; font-weight: 700; color: var(--brand);
    box-shadow: 0 2px 12px rgba(0,0,0,.06); z-index: 1;
    display: flex; align-items: center; gap: 6px;
}
.hero-wrap::before {
    content:'';
    position: absolute;
    width: 600px; height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--brand-rgb),.12) 0%, transparent 70%);
    top: -100px; right: -100px;
    pointer-events: none;
}
.hero-wrap::after {
    content:'';
    position: absolute;
    width: 400px; height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--brand-rgb),.07) 0%, transparent 70%);
    bottom: -80px; left: -80px;
    pointer-events: none;
}
.hero-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(var(--brand-rgb),.1);
    color: var(--brand);
    border: 1px solid rgba(var(--brand-rgb),.2);
    border-radius: 999px;
    padding: 6px 16px;
    font-size: .78rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    margin-bottom: 20px;
}
.hero-title {
    font-size: clamp(2.4rem, 5vw, 3.8rem);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -.03em;
    color: #0f172a;
}
.hero-title span { color: var(--brand); }
.hero-subtitle { font-size: 1.15rem; color: #64748b; max-width: 500px; line-height: 1.7; }
.hero-img-wrap {
    position: relative;
}
.hero-img-card {
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 32px 64px rgba(0,0,0,.16);
    aspect-ratio: 4/5;
    background: var(--brand-light);
}
.hero-img-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
.hero-float-badge {
    position: absolute;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
    padding: 14px 18px;
    display: flex; align-items: center; gap: 12px;
    animation: floatUp 3s ease-in-out infinite;
}
.hero-float-badge.badge-bl { bottom: -20px; left: -20px; }
.hero-float-badge.badge-tr { top: 40px; right: -24px; animation-delay: 1.5s; }
.hero-float-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--brand); display: flex; align-items: center; justify-content: center; flex-shrink:0; }
.hero-float-icon .material-symbols-rounded { color: var(--brand-text); font-size: 1.2rem; }
.hero-float-label { font-size: .7rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
.hero-float-value { font-size: 1rem; font-weight: 800; color: #0f172a; font-family: '<?php echo $e($headline_font); ?>'; }
@keyframes floatUp { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }

/* ─── Section Labels ─────────────────────────────── */
.section-label {
    display: inline-block;
    font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
    color: var(--brand);
    background: rgba(var(--brand-rgb),.08);
    padding: 5px 14px; border-radius: 999px;
    margin-bottom: 14px;
}

/* ─── How It Works ───────────────────────────────── */
.step-card {
    position: relative;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 36px 28px;
    transition: box-shadow .3s, transform .3s;
}
.step-card:hover { box-shadow: 0 16px 48px rgba(0,0,0,.08); transform: translateY(-4px); }
.step-num {
    width: 52px; height: 52px; border-radius: 14px;
    background: rgba(var(--brand-rgb),.1);
    color: var(--brand);
    font-size: 1.25rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    font-family: '<?php echo $e($headline_font); ?>';
    margin-bottom: 20px;
}
.step-connector {
    position: absolute; top: 52px; right: -24px;
    width: 48px; height: 2px;
    background: linear-gradient(to right, rgba(var(--brand-rgb),.3), rgba(var(--brand-rgb),.05));
    z-index: 1;
}

/* ─── Services ───────────────────────────────────── */
.service-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 32px 28px;
    transition: all .3s;
    height: 100%;
}
.service-card:hover {
    border-color: var(--brand);
    box-shadow: 0 16px 40px rgba(var(--brand-rgb),.12);
    transform: translateY(-4px);
}
.service-icon {
    width: 60px; height: 60px; border-radius: 16px;
    background: rgba(var(--brand-rgb),.1);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 20px;
}
.service-icon .material-symbols-rounded { color: var(--brand); font-size: 1.8rem; }

/* ─── Stats Banner ───────────────────────────────── */
.stats-band {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(var(--brand-rgb),.3);
}
.stats-band .stat-num { font-size: 2.8rem; font-weight: 800; color: #fff; line-height: 1; }
.stats-band .stat-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.65); margin-top: 4px; }
.stats-band img { width: 100%; height: 100%; object-fit: cover; min-height: 360px; }
.stats-divider { width: 1px; background: rgba(255,255,255,.2); align-self: stretch; margin: 0 8px; }

/* ─── Calculator ─────────────────────────────────── */
.calc-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px rgba(0,0,0,.06); padding: 3rem; }
.calc-result-box { border-radius: 16px; padding: 1.25rem 1rem; text-align: center; }
.calc-result-box.highlight { background: rgba(var(--brand-rgb),.08); border: 1.5px solid rgba(var(--brand-rgb),.2); }
.calc-result-box.normal { background: #f8fafc; border: 1px solid #e2e8f0; }
.calc-result-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #94a3b8; margin-bottom: 6px; }
.calc-result-value { font-size: 1.6rem; font-weight: 800; font-family: '<?php echo $e($headline_font); ?>'; color: var(--brand); }
.calc-result-value.muted { color: #64748b; }
.calc-result-value.accent { color: var(--accent); }
.form-range::-webkit-slider-thumb { background: var(--brand); }
.form-range::-moz-range-thumb { background: var(--brand); }
.product-btn {
    border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 16px;
    background: #fff; text-align: left; width: 100%; transition: all .2s; cursor: pointer;
}
.product-btn:hover, .product-btn.active { border-color: var(--brand); background: rgba(var(--brand-rgb),.05); }
.product-btn.active .product-btn-name { color: var(--brand); }

/* ─── Download Section ───────────────────────────── */
.download-section {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    position: relative; overflow: hidden;
}
.download-section::before {
    content:'';
    position: absolute;
    width: 500px; height: 500px; border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--brand-rgb),.2), transparent 70%);
    top: -150px; right: -100px; pointer-events:none;
}
.app-store-btn {
    display: inline-flex; align-items: center; gap: 12px;
    background: rgba(255,255,255,.1);
    border: 1.5px solid rgba(255,255,255,.2);
    border-radius: 14px; padding: 12px 22px;
    color: #fff; text-decoration: none;
    transition: all .3s; font-size: .9rem;
}
.app-store-btn:hover { background: rgba(255,255,255,.18); border-color: rgba(255,255,255,.4); color: #fff; transform: translateY(-2px); }
.app-store-btn .store-icon { font-size: 1.8rem; }
.app-store-btn .store-sub { font-size: .65rem; text-transform: uppercase; letter-spacing: .08em; opacity: .7; }
.app-store-btn .store-name { font-size: 1rem; font-weight: 700; line-height: 1.1; }
.app-feature-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
    border-radius: 999px; padding: 8px 16px; color: rgba(255,255,255,.8); font-size: .82rem;
}

/* ─── About Section ──────────────────────────────── */
.about-img-wrap { border-radius: 24px; overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,.12); aspect-ratio: 4/5; }
.about-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.about-fact { border-left: 3px solid var(--brand); padding-left: 16px; }

/* ─── Footer ─────────────────────────────────────── */
.site-footer { background: #0f172a; }
.footer-link { color: rgba(255,255,255,.5); text-decoration: none; font-size: .88rem; transition: color .2s; }
.footer-link:hover { color: #fff; }

/* ─── Scroll animations ──────────────────────────── */
.fade-up { opacity: 0; transform: translateY(30px); transition: opacity .6s ease, transform .6s ease; }
.fade-up.visible { opacity: 1; transform: translateY(0); }

/* ─── Responsive tweaks ──────────────────────────── */
@media (max-width:768px) {
    .hero-wrap { padding: 90px 0 60px; min-height: auto; }
    .hero-float-badge.badge-tr { display: none; }
    .hero-float-badge.badge-bl { left: 0; bottom: -10px; }
    .stat-num { font-size: 2rem !important; }
    .calc-card { padding: 1.5rem; }
    .step-connector { display:none; }
}
</style>
<?php if ($custom_css !== ''): ?><style><?php echo strip_tags($custom_css); ?></style><?php endif; ?>
</head>
<body>

<!-- ═══ NAVBAR ════════════════════════════════════════ -->
<nav class="site-nav sticky-top py-2" id="siteNav">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center" style="height:60px;">
      <!-- Brand -->
      <a class="d-flex align-items-center gap-2 text-decoration-none" href="#">
        <?php if ($logo): ?>
        <img src="<?php echo $e($logo); ?>" alt="Logo" height="36" class="rounded-2" style="object-fit:cover;">
        <?php endif; ?>
        <span class="fw-800 headline fs-5 text-brand"><?php echo $e($tenant_name); ?></span>
      </a>
      <!-- Desktop links -->
      <div class="d-none d-lg-flex align-items-center gap-1">
        <?php if ($show_services && !empty($services)): ?>
        <a class="nav-link px-3" href="#services">Services</a>
        <?php endif; ?>
        <?php if ($show_about): ?>
        <a class="nav-link px-3" href="#about">About Us</a>
        <?php endif; ?>
        <?php if ($show_stats && !empty($stats)): ?>
        <a class="nav-link px-3" href="#numbers">Numbers</a>
        <?php endif; ?>
        <?php if ($show_loan_calc && !empty($loan_products)): ?>
        <a class="nav-link px-3" href="#calculator">Calculator</a>
        <?php endif; ?>
        <?php if ($show_contact): ?>
        <a class="nav-link px-3" href="#contact">Contact</a>
        <?php endif; ?>
        <?php if ($show_download_section): ?>
        <a class="nav-link px-3" href="#download">Download App</a>
        <?php endif; ?>
      </div>
      <!-- CTA buttons -->
      <div class="d-flex gap-2 align-items-center">
        <a href="../tenant_login/login.php?s=<?php echo urlencode($site_slug); ?>&auth=1" class="d-none d-md-inline-flex btn btn-brand-outline rounded-pill px-4 fw-600">Log In</a>
        <?php if ($show_download_section && $_download_href): ?>
        <a href="<?php echo $e($_download_href); ?>" target="_blank" class="btn btn-brand rounded-pill px-4 fw-700 shadow-sm">
          <span class="material-symbols-rounded me-1" style="font-size:1rem;">phone_android</span><?php echo $e($download_button_text ?: 'Get App'); ?>
        </a>
        <?php else: ?>
        <a href="<?php echo $e($hero_cta_url); ?>" class="btn btn-brand rounded-pill px-4 fw-700 shadow-sm"><?php echo $e($hero_cta_text); ?></a>
        <?php endif; ?>
        <!-- Mobile toggler -->
        <button class="btn p-2 d-lg-none border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
          <span class="material-symbols-rounded">menu</span>
        </button>
      </div>
    </div>
  </div>
</nav>

<!-- Mobile Offcanvas -->
<div class="offcanvas offcanvas-end" id="mobileMenu">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title fw-bold text-brand"><?php echo $e($tenant_name); ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column gap-1 pt-4">
    <?php if ($show_services && !empty($services)): ?><a class="nav-link py-2" href="#services" data-bs-dismiss="offcanvas">Services</a><?php endif; ?>
    <?php if ($show_about): ?><a class="nav-link py-2" href="#about" data-bs-dismiss="offcanvas">About Us</a><?php endif; ?>
    <?php if ($show_loan_calc && !empty($loan_products)): ?><a class="nav-link py-2" href="#calculator" data-bs-dismiss="offcanvas">Loan Calculator</a><?php endif; ?>
    <?php if ($show_contact): ?><a class="nav-link py-2" href="#contact" data-bs-dismiss="offcanvas">Contact</a><?php endif; ?>
    <?php if ($show_download_section): ?><a class="nav-link py-2" href="#download" data-bs-dismiss="offcanvas">📱 Download App</a><?php endif; ?>
    <hr>
    <a href="tenant_login/login.php?s=<?php echo urlencode($slug ?? ''); ?>" class="btn btn-brand-outline rounded-pill fw-700">Log In</a>
  </div>
</div>


<!-- ═══ HERO ══════════════════════════════════════════ -->
<section class="hero-wrap" id="home">
  <div class="container position-relative" style="z-index:2;">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <?php if ($hero_badge_text): ?>
        <div class="hero-badge mb-3">
          <span class="material-symbols-rounded" style="font-size:.95rem;">verified</span>
          <?php echo $e($hero_badge_text); ?>
        </div>
        <?php endif; ?>
        <h1 class="hero-title mb-4">
          <?php
            // Color the first word in brand color
            $parts = explode(' ', $hero_title, 3);
            if (count($parts) >= 2) {
                echo '<span>' . $e($parts[0]) . '</span> ' . $e(implode(' ', array_slice($parts, 1)));
            } else {
                echo $e($hero_title);
            }
          ?>
        </h1>
        <?php if ($hero_subtitle): ?>
        <p class="hero-subtitle mb-2"><?php echo $e($hero_subtitle); ?></p>
        <?php endif; ?>
        <?php if ($hero_desc): ?>
        <p class="text-muted mb-5" style="max-width:480px;line-height:1.75;"><?php echo $e($hero_desc); ?></p>
        <?php else: ?>
        <div class="mb-5"></div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-3 align-items-center">
          <a href="<?php echo $e($hero_cta_url); ?>" class="btn btn-brand btn-lg rounded-pill px-5 fw-700 shadow">
            <?php echo $e($hero_cta_text); ?>
            <span class="material-symbols-rounded ms-2" style="font-size:1rem;vertical-align:middle;">arrow_forward</span>
          </a>
          <?php if ($show_loan_calc && !empty($loan_products)): ?>
          <a href="#calculator" class="btn btn-brand-outline btn-lg rounded-pill px-5 fw-700">Calculate Loan</a>
          <?php endif; ?>
          <?php if ($show_download_section && $_download_href): ?>
          <a href="<?php echo $e($_download_href); ?>" target="_blank" class="btn btn-brand-outline btn-lg rounded-pill px-4 fw-700">
            <span class="material-symbols-rounded me-1" style="font-size:1rem;">phone_android</span> Get App
          </a>
          <?php endif; ?>
        </div>
        <!-- Trust indicators -->
        <div class="d-flex align-items-center gap-4 mt-5 flex-wrap">
          <div class="d-flex align-items-center gap-2">
            <span class="material-symbols-rounded text-brand">shield</span>
            <span class="small text-muted fw-600">Secure & Trusted</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="material-symbols-rounded text-brand">support_agent</span>
            <span class="small text-muted fw-600">24/7 Support</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="material-symbols-rounded text-brand">speed</span>
            <span class="small text-muted fw-600">Fast Approval</span>
          </div>
        </div>
      </div>
      <div class="col-lg-6 d-none d-lg-block">
        <div class="hero-img-wrap position-relative">
          <div class="hero-img-card">
            <?php if ($hero_image): ?>
            <img src="<?php echo $e($hero_image); ?>" alt="<?php echo $e($tenant_name); ?>">
            <?php else: ?>
            <!-- Offline-safe branded illustration -->
            <div class="hero-illus">
              <div class="hi-badge">
                <span class="material-symbols-rounded" style="font-size:.9rem;">verified</span>
                Trusted Microfinance
              </div>
              <div class="hi-card">
                <div class="hi-icon hi-primary">
                  <span class="material-symbols-rounded" style="color:#fff;font-size:1.2rem;">account_balance_wallet</span>
                </div>
                <div>
                  <div class="hi-label">Current Balance</div>
                  <div class="hi-value">₱ <?php echo number_format(rand(12000,98000)); ?></div>
                </div>
              </div>
              <div class="hi-bar-wrap">
                <div class="hi-bar-row">
                  <span class="hi-bar-label">Personal</span>
                  <div class="hi-bar-track"><div class="hi-bar-fill" style="width:78%"></div></div>
                </div>
                <div class="hi-bar-row">
                  <span class="hi-bar-label">Business</span>
                  <div class="hi-bar-track"><div class="hi-bar-fill" style="width:55%"></div></div>
                </div>
                <div class="hi-bar-row">
                  <span class="hi-bar-label">Emergency</span>
                  <div class="hi-bar-track"><div class="hi-bar-fill" style="width:34%"></div></div>
                </div>
              </div>
              <div class="hi-card">
                <div class="hi-icon hi-green">
                  <span class="material-symbols-rounded" style="color:#fff;font-size:1.2rem;">trending_up</span>
                </div>
                <div>
                  <div class="hi-label">Approval Rate</div>
                  <div class="hi-value">94.8%</div>
                </div>
              </div>
              <div class="hi-card">
                <div class="hi-icon hi-amber">
                  <span class="material-symbols-rounded" style="color:#fff;font-size:1.2rem;">payments</span>
                </div>
                <div>
                  <div class="hi-label">Fast Release</div>
                  <div class="hi-value">24 hrs</div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <!-- Floating badge bottom-left -->
          <div class="hero-float-badge badge-bl">
            <div class="hero-float-icon"><span class="material-symbols-rounded">group</span></div>
            <div>
              <div class="hero-float-label">Happy Members</div>
              <div class="hero-float-value"><?php echo number_format($total_clients); ?>+ Clients</div>
            </div>
          </div>
          <!-- Floating badge top-right -->
          <div class="hero-float-badge badge-tr">
            <div class="hero-float-icon"><span class="material-symbols-rounded">payments</span></div>
            <div>
              <div class="hero-float-label">Loans Funded</div>
              <div class="hero-float-value"><?php echo number_format($total_loans); ?>+ Active</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ═══ PARTNERS STRIP ═════════════════════════════════ -->
<?php if ($show_partners && !empty($partners)): ?>
<section class="py-4 bg-white border-top border-bottom">
  <div class="container">
    <p class="text-center small text-uppercase fw-700 text-muted mb-4" style="letter-spacing:.15em;">Trusted By Our Partners</p>
    <div class="d-flex flex-wrap justify-content-center align-items-center gap-5">
      <?php foreach ($partners as $pu): ?>
      <img src="<?php echo $e($pu); ?>" alt="Partner" height="36" style="filter:grayscale(1);opacity:.5;object-fit:contain;">
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ═══ HOW IT WORKS ════════════════════════════════════ -->
<section class="py-5 my-4" id="how">
  <div class="container py-4">
    <div class="text-center mb-5 fade-up">
      <div class="section-label">How It Works</div>
      <h2 class="headline fw-800 display-6">Simple. Fast. Reliable.</h2>
      <p class="text-muted mt-3 mx-auto" style="max-width:520px;">Get a loan in three easy steps — no complicated paperwork, no hidden fees.</p>
    </div>
    <div class="row g-4 position-relative">
      <div class="col-md-4 fade-up">
        <div class="step-card h-100">
          <?php if (!empty($services) && count($services) >= 3): ?>
          <div class="step-connector d-none d-md-block"></div>
          <?php endif; ?>
          <div class="step-num">01</div>
          <h4 class="headline fw-700 mb-2">Apply Online</h4>
          <p class="text-muted mb-0">Fill out our quick application form. It only takes a few minutes to get started.</p>
        </div>
      </div>
      <div class="col-md-4 fade-up" style="transition-delay:.1s;">
        <div class="step-card h-100">
          <div class="step-connector d-none d-md-block"></div>
          <div class="step-num">02</div>
          <h4 class="headline fw-700 mb-2">Get Approved</h4>
          <p class="text-muted mb-0">Our team reviews your application and gives you a fast, transparent decision.</p>
        </div>
      </div>
      <div class="col-md-4 fade-up" style="transition-delay:.2s;">
        <div class="step-card h-100">
          <div class="step-num">03</div>
          <h4 class="headline fw-700 mb-2">Receive Funds</h4>
          <p class="text-muted mb-0">Funds are released quickly so you can focus on what matters most to you.</p>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ═══ SERVICES ════════════════════════════════════════ -->
<?php if ($show_services && !empty($services)): ?>
<section id="services" class="py-5 bg-white border-top">
  <div class="container py-4">
    <div class="text-center mb-5 fade-up">
      <div class="section-label"><?php echo $e($services_heading); ?></div>
      <h2 class="headline fw-800 display-6">What We Offer</h2>
      <p class="text-muted mt-2 mx-auto" style="max-width:480px;">Flexible financial solutions tailored to your needs.</p>
    </div>
    <div class="row g-4">
      <?php foreach ($services as $svc): ?>
      <div class="col-md-6 col-lg-4 fade-up">
        <div class="service-card">
          <div class="service-icon">
            <span class="material-symbols-rounded"><?php echo $e($svc['icon'] ?? 'star'); ?></span>
          </div>
          <h4 class="headline fw-700 h5 mb-3"><?php echo $e($svc['title'] ?? ''); ?></h4>
          <p class="text-muted mb-0 lh-lg"><?php echo $e($svc['description'] ?? ''); ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ═══ TRUST STATS ══════════════════════════════════════ -->
<?php if ($show_stats && !empty($stats)): ?>
<section id="numbers" class="py-5">
  <div class="container py-4 fade-up">
    <div class="stats-band">
      <div class="row g-0 align-items-center">
        <div class="col-lg-5 d-none d-lg-block">
          <?php if ($stats_image): ?>
          <img src="<?php echo $e($stats_image); ?>" alt="Team" style="width:100%;height:100%;object-fit:cover;min-height:360px;">
          <?php else: ?>
          <!-- Offline-safe stats visual -->
          <div class="stats-illus">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.6);z-index:1;">Live Performance</div>
            <div class="si-row">
              <div class="si-col">
                <div class="si-num"><?php echo number_format($total_clients); ?>+</div>
                <div class="si-lbl">Clients</div>
              </div>
              <div class="si-col">
                <div class="si-num"><?php echo number_format($total_loans); ?>+</div>
                <div class="si-lbl">Loans</div>
              </div>
              <div class="si-col">
                <div class="si-num"><?php echo date('Y') - 2019; ?>+</div>
                <div class="si-lbl">Years</div>
              </div>
            </div>
            <div class="si-chart">
              <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.6);margin-bottom:10px;">Monthly Disbursements</div>
              <div class="si-chart-bars">
                <?php
                $bar_heights = [38, 52, 44, 70, 58, 82, 66, 91, 75, 88, 94, 100];
                foreach($bar_heights as $h): ?>
                <div class="si-bar" style="height:<?php echo $h; ?>%"></div>
                <?php endforeach; ?>
              </div>
            </div>
            <div style="font-size:.72rem;color:rgba(255,255,255,.5);z-index:1;">Empowering communities since <?php echo date('Y') - rand(3,8); ?></div>
          </div>
          <?php endif; ?>
        </div>
        <div class="col-lg-7 p-5 p-md-5">
          <div class="text-white opacity-75 small text-uppercase fw-700 mb-3" style="letter-spacing:.1em;">By The Numbers</div>
          <h2 class="headline fw-800 text-white mb-2" style="font-size:1.9rem;"><?php echo $e($stats_heading); ?></h2>
          <?php if ($stats_subheading): ?>
          <p class="text-white mb-5" style="opacity:.65;"><?php echo $e($stats_subheading); ?></p>
          <?php else: ?><div class="mb-5"></div><?php endif; ?>
          <div class="row g-4">
            <?php foreach (array_slice($stats, 0, 4) as $i => $stat): ?>
            <div class="col-6">
              <?php if ($i > 0): ?>
              <?php endif; ?>
              <div class="stat-num headline"><?php echo $e($stat['value'] ?? ''); ?></div>
              <div class="stat-label"><?php echo $e($stat['label'] ?? ''); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ═══ LOAN CALCULATOR ══════════════════════════════════ -->
<?php if ($show_loan_calc && !empty($loan_products)): ?>
<section id="calculator" class="py-5 bg-white border-top">
  <div class="container py-4">
    <div class="text-center mb-5 fade-up">
      <div class="section-label">Loan Calculator</div>
      <h2 class="headline fw-800 display-6">Estimate Your Payment</h2>
      <p class="text-muted mt-2 mx-auto" style="max-width:500px;">Select a loan product, set your amount and term, and see your estimated monthly payment instantly.</p>
    </div>
    <div class="calc-card mx-auto fade-up" style="max-width:820px;">
      <!-- Product picker -->
      <div class="mb-4">
        <label class="small fw-700 text-uppercase text-muted d-block mb-3" style="letter-spacing:.08em;">Select Loan Product</label>
        <div class="row g-3" id="lc-product-list">
          <?php foreach ($loan_products as $i => $prod): ?>
          <div class="col-md-4">
            <button type="button" class="product-btn lc-product-btn <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="material-symbols-rounded text-brand" style="font-size:1.1rem;">
                  <?php
                    $icon_map = ['Personal Loan'=>'person','Business Loan'=>'store','Emergency Loan'=>'emergency_home'];
                    echo $icon_map[$prod['product_type']] ?? 'payments';
                  ?>
                </span>
                <span class="fw-700 small product-btn-name text-dark"><?php echo $e($prod['product_name']); ?></span>
              </div>
              <div class="small text-muted product-btn-rate"><?php echo number_format($prod['interest_rate'],1); ?>% / mo · <?php echo $e($prod['interest_type']); ?></div>
            </button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Amount slider -->
      <div class="mb-4 p-4 rounded-4 bg-light">
        <div class="d-flex justify-content-between mb-2">
          <label class="small fw-700 text-uppercase text-muted" style="letter-spacing:.08em;">Loan Amount</label>
          <span id="lc-amount-display" class="fw-800 fs-4 text-brand headline">₱0</span>
        </div>
        <input type="range" class="form-range" id="lc-amount-slider" min="0" max="100000" step="1000" value="0">
        <div class="d-flex justify-content-between small text-muted fw-600 mt-1">
          <span id="lc-min-amount">₱0</span><span id="lc-max-amount">₱0</span>
        </div>
      </div>
      <!-- Term slider -->
      <div class="mb-5 p-4 rounded-4 bg-light">
        <div class="d-flex justify-content-between mb-2">
          <label class="small fw-700 text-uppercase text-muted" style="letter-spacing:.08em;">Loan Term</label>
          <span id="lc-term-display" class="fw-800 fs-4 text-brand headline">0 mo</span>
        </div>
        <input type="range" class="form-range" id="lc-term-slider" min="1" max="36" step="1" value="1">
        <div class="d-flex justify-content-between small text-muted fw-600 mt-1">
          <span id="lc-min-term">1 mo</span><span id="lc-max-term">36 mo</span>
        </div>
      </div>
      <!-- Results -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="calc-result-box highlight"><div class="calc-result-label">Monthly</div><div class="calc-result-value" id="lc-monthly">₱0</div></div></div>
        <div class="col-6 col-md-3"><div class="calc-result-box normal"><div class="calc-result-label">Interest</div><div class="calc-result-value accent" id="lc-interest">₱0</div></div></div>
        <div class="col-6 col-md-3"><div class="calc-result-box normal"><div class="calc-result-label">Fee</div><div class="calc-result-value muted" id="lc-fee">₱0</div></div></div>
        <div class="col-6 col-md-3"><div class="calc-result-box highlight"><div class="calc-result-label">Total</div><div class="calc-result-value" id="lc-total">₱0</div></div></div>
      </div>
      <p class="text-center text-muted small mb-0">
        <span class="material-symbols-rounded me-1" style="font-size:.95rem;vertical-align:middle;">info</span>
        Estimates only. Actual amounts depend on approval and applicable fees.
      </p>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ═══ ABOUT SECTION ════════════════════════════════════ -->
<?php if ($show_about && $about_body): ?>
<section id="about" class="py-5 border-top">
  <div class="container py-4">
    <div class="row align-items-center g-5">
      <div class="col-lg-5 fade-up">
        <div class="about-img-wrap">
          <?php if ($about_image): ?>
          <img src="<?php echo $e($about_image); ?>" alt="<?php echo $e($tenant_name); ?>">
          <?php else: ?>
          <!-- Offline-safe about illustration -->
          <div class="about-illus">
            <div class="ai-pill">
              <span class="material-symbols-rounded" style="font-size:1rem;">groups</span>
              Our People &amp; Mission
            </div>
            <div class="ai-avatar-row">
              <?php
              $avatar_colors = ['#dc2626','#2563eb','#059669','#d97706','#7c3aed'];
              $avatar_emojis = ['👩🏽','👨🏻','👩🏾','👨🏼','👩🏿'];
              $count = min(5, max(3, (int)$total_clients / 10));
              for ($ai = 0; $ai < 5; $ai++): ?>
              <div class="ai-avatar" style="background:<?php echo $avatar_colors[$ai]; ?>">
                <?php echo $avatar_emojis[$ai]; ?>
              </div>
              <?php endfor; ?>
            </div>
            <div class="ai-stat">
              <div class="ai-stat-num"><?php echo number_format($total_clients); ?>+</div>
              <div class="ai-stat-lbl">Happy Members Served</div>
            </div>
            <div class="ai-stat">
              <div class="ai-stat-num"><?php echo number_format($total_loans); ?>+</div>
              <div class="ai-stat-lbl">Loans Funded</div>
            </div>
            <div class="ai-pill">
              <span class="material-symbols-rounded" style="font-size:1rem;">volunteer_activism</span>
              Community-Driven Finance
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-7 fade-up" style="transition-delay:.15s;">
        <div class="section-label"><?php echo $e($about_heading); ?></div>
        <h2 class="headline fw-800 display-6 mb-4">Who We Are</h2>
        <p class="text-muted lh-lg fs-5 mb-5"><?php echo nl2br($e($about_body)); ?></p>
        <div class="row g-4">
          <div class="col-6">
            <div class="about-fact">
              <div class="fw-800 headline" style="font-size:1.8rem;color:var(--brand);"><?php echo number_format($total_clients); ?>+</div>
              <div class="small text-muted fw-600 text-uppercase" style="letter-spacing:.06em;">Active Members</div>
            </div>
          </div>
          <div class="col-6">
            <div class="about-fact">
              <div class="fw-800 headline" style="font-size:1.8rem;color:var(--brand);"><?php echo number_format($total_loans); ?>+</div>
              <div class="small text-muted fw-600 text-uppercase" style="letter-spacing:.06em;">Loans Funded</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ═══ MOBILE APP DOWNLOAD ══════════════════════════════ -->
<?php if ($show_download_section): ?>
<section id="download" class="download-section py-5">
  <div class="container py-5 position-relative" style="z-index:2;">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <div class="small text-white opacity-60 text-uppercase fw-700 mb-3" style="letter-spacing:.15em;">Mobile App</div>
        <h2 class="headline fw-800 text-white mb-4" style="font-size:2.6rem;line-height:1.15;">
          <?php echo $e($download_title); ?>
        </h2>
        <p class="text-white mb-5 lh-lg" style="opacity:.7;max-width:500px;font-size:1.1rem;">
          <?php echo $e($download_description ?: 'Track your loans, submit applications, receive notifications, and manage your account — all from your phone.'); ?>
        </p>
        <!-- Feature pills -->
        <div class="d-flex flex-wrap gap-3 mb-5">
          <span class="app-feature-pill"><span class="material-symbols-rounded" style="font-size:1rem;">notifications</span> Real-time Alerts</span>
          <span class="app-feature-pill"><span class="material-symbols-rounded" style="font-size:1rem;">account_balance_wallet</span> Loan Tracking</span>
          <span class="app-feature-pill"><span class="material-symbols-rounded" style="font-size:1rem;">lock</span> Secure Access</span>
          <span class="app-feature-pill"><span class="material-symbols-rounded" style="font-size:1rem;">speed</span> Fast & Easy</span>
        </div>
        <!-- Download buttons -->
        <div class="d-flex flex-wrap gap-3">
          <?php if ($_has_flutter_web): ?>
          <!-- PRIMARY: Open Flutter web app directed at this company -->
          <a href="<?php echo $e($_flutter_web_link); ?>" target="_blank" class="app-store-btn" style="background:rgba(var(--brand-rgb),.18);border-color:rgba(var(--brand-rgb),.4);">
            <span class="material-symbols-rounded store-icon">phone_android</span>
            <div>
              <div class="store-sub">Open Now in</div>
              <div class="store-name"><?php echo $e($tenant_name); ?> App</div>
            </div>
          </a>
          <?php if ($download_url): ?>
          <a href="<?php echo $e($download_url); ?>" target="_blank" class="app-store-btn">
            <span class="material-symbols-rounded store-icon">android</span>
            <div>
              <div class="store-sub">Get it on</div>
              <div class="store-name">Google Play</div>
            </div>
          </a>
          <?php endif; ?>
          <?php elseif ($download_url): ?>
          <a href="<?php echo $e($download_url); ?>" target="_blank" class="app-store-btn">
            <span class="material-symbols-rounded store-icon">android</span>
            <div>
              <div class="store-sub">Get it on</div>
              <div class="store-name">Google Play</div>
            </div>
          </a>
          <a href="<?php echo $e($download_url); ?>" target="_blank" class="app-store-btn">
            <span class="material-symbols-rounded store-icon">phone_iphone</span>
            <div>
              <div class="store-sub">Download on the</div>
              <div class="store-name">App Store</div>
            </div>
          </a>
          <?php else: ?>
          <!-- Show coming soon if no URL -->
          <div class="app-store-btn opacity-75">
            <span class="material-symbols-rounded store-icon">android</span>
            <div>
              <div class="store-sub">Coming Soon on</div>
              <div class="store-name">Google Play</div>
            </div>
          </div>
          <div class="app-store-btn opacity-75">
            <span class="material-symbols-rounded store-icon">phone_iphone</span>
            <div>
              <div class="store-sub">Coming Soon on</div>
              <div class="store-name">App Store</div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-5 text-center d-none d-lg-block">
        <!-- Visual phone mockup placeholder -->
        <div class="mx-auto position-relative" style="width:240px;">
          <div class="rounded-4 shadow-lg overflow-hidden" style="background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.15);padding:12px;">
            <div class="rounded-3 overflow-hidden" style="background:rgba(255,255,255,.05);aspect-ratio:9/16;display:flex;flex-direction:column;justify-content:center;align-items:center;gap:12px;">
              <div style="width:48px;height:48px;background:rgba(255,255,255,.15);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                <span class="material-symbols-rounded text-white" style="font-size:1.6rem;">account_balance</span>
              </div>
              <div class="text-white fw-700 headline"><?php echo $e($tenant_name); ?></div>
              <div class="text-white small" style="opacity:.6;">Your Finance App</div>
              <div style="background:var(--brand);border-radius:999px;padding:8px 24px;" class="text-white small fw-700">Open App</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ═══ FOOTER ════════════════════════════════════════════ -->
<footer id="contact" class="site-footer py-5">
  <div class="container py-4">
    <div class="row g-5">
      <!-- Brand column -->
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-2 mb-4">
          <?php if ($logo): ?>
          <img src="<?php echo $e($logo); ?>" alt="Logo" height="36" class="rounded-2 bg-white p-1">
          <?php endif; ?>
          <span class="headline fw-800 text-white fs-5"><?php echo $e($tenant_name); ?></span>
        </div>
        <?php if ($footer_description): ?>
        <p class="footer-link lh-lg" style="font-size:.88rem;"><?php echo nl2br($e($footer_description)); ?></p>
        <?php else: ?>
        <p class="footer-link lh-lg" style="font-size:.88rem;">Your trusted microfinance partner — empowering communities through accessible financial services.</p>
        <?php endif; ?>
      </div>
      <!-- Quick links -->
      <div class="col-6 col-lg-2">
        <h6 class="text-white fw-700 mb-4" style="letter-spacing:.08em;text-transform:uppercase;font-size:.8rem;">Navigate</h6>
        <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
          <li><a href="#home" class="footer-link">Home</a></li>
          <?php if ($show_services && !empty($services)): ?><li><a href="#services" class="footer-link">Services</a></li><?php endif; ?>
          <?php if ($show_about): ?><li><a href="#about" class="footer-link">About Us</a></li><?php endif; ?>
          <?php if ($show_loan_calc && !empty($loan_products)): ?><li><a href="#calculator" class="footer-link">Calculator</a></li><?php endif; ?>
          <?php if ($show_download_section): ?><li><a href="#download" class="footer-link">Get App</a></li><?php endif; ?>
        </ul>
      </div>
      <!-- Contact -->
      <?php if ($show_contact): ?>
      <div class="col-6 col-lg-3">
        <h6 class="text-white fw-700 mb-4" style="letter-spacing:.08em;text-transform:uppercase;font-size:.8rem;">Contact</h6>
        <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
          <?php if ($contact_address): ?>
          <li class="d-flex gap-3">
            <span class="material-symbols-rounded text-brand mt-1" style="font-size:1rem;flex-shrink:0;">location_on</span>
            <span class="footer-link"><?php echo nl2br($e($contact_address)); ?></span>
          </li>
          <?php endif; ?>
          <?php if ($contact_phone): ?>
          <li class="d-flex gap-3 align-items-center">
            <span class="material-symbols-rounded text-brand" style="font-size:1rem;flex-shrink:0;">phone</span>
            <span class="footer-link"><?php echo $e($contact_phone); ?></span>
          </li>
          <?php endif; ?>
          <?php if ($contact_email): ?>
          <li class="d-flex gap-3 align-items-center">
            <span class="material-symbols-rounded text-brand" style="font-size:1rem;flex-shrink:0;">email</span>
            <a href="mailto:<?php echo $e($contact_email); ?>" class="footer-link"><?php echo $e($contact_email); ?></a>
          </li>
          <?php endif; ?>
          <?php if ($contact_hours): ?>
          <li class="d-flex gap-3 align-items-center">
            <span class="material-symbols-rounded text-brand" style="font-size:1rem;flex-shrink:0;">schedule</span>
            <span class="footer-link"><?php echo nl2br($e($contact_hours)); ?></span>
          </li>
          <?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <!-- App CTA in footer -->
      <?php if ($show_download_section): ?>
      <div class="col-lg-3">
        <h6 class="text-white fw-700 mb-4" style="letter-spacing:.08em;text-transform:uppercase;font-size:.8rem;">Get Our App</h6>
        <p class="footer-link small mb-3">Manage your loans on the go with our mobile app.</p>
        <?php if ($_download_href): ?>
        <a href="<?php echo $e($_download_href); ?>" target="_blank" class="btn btn-brand rounded-pill px-4 fw-700 w-100">
          <span class="material-symbols-rounded me-2" style="font-size:1rem;"><?php echo $_has_flutter_web ? 'phone_android' : 'download'; ?></span>
          <?php echo $e($_has_flutter_web ? ('Open ' . $tenant_name . ' App') : ($download_button_text ?: 'Download Now')); ?>
        </a>
        <?php else: ?>
        <div class="small text-white opacity-50 fst-italic">App coming soon!</div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <hr class="border-secondary opacity-10 mt-5 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <div class="footer-link small">&copy; <?php echo date('Y'); ?> <?php echo $e($tenant_name); ?>. All rights reserved.</div>
      <div class="footer-link small">Powered by <span class="text-brand fw-600">MicroFin</span></div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Navbar shadow on scroll
window.addEventListener('scroll', function() {
    var nav = document.getElementById('siteNav');
    if (nav) nav.classList.toggle('scrolled', window.scrollY > 20);
});

// Scroll fade-in
var obs = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }});
}, { threshold: 0.12 });
document.querySelectorAll('.fade-up').forEach(function(el) { obs.observe(el); });
</script>

<?php if ($show_loan_calc && !empty($loan_products)): ?>
<script>
(function() {
    var products = <?php echo json_encode(array_map(function($p) {
        return [
            'name'       => $p['product_name'],
            'min'        => (float)$p['min_amount'],
            'max'        => (float)$p['max_amount'],
            'rate'       => (float)$p['interest_rate'],
            'type'       => $p['interest_type'],
            'minTerm'    => (int)$p['min_term_months'],
            'maxTerm'    => (int)$p['max_term_months'],
            'processing' => (float)$p['processing_fee_percentage'],
        ];
    }, $loan_products), JSON_UNESCAPED_UNICODE); ?>;

    var amtSlider = document.getElementById('lc-amount-slider');
    var trmSlider = document.getElementById('lc-term-slider');
    var btns      = document.querySelectorAll('.lc-product-btn');
    var selected  = 0;
    var php       = new Intl.NumberFormat('en-PH', {minimumFractionDigits:0, maximumFractionDigits:0});
    var fmt       = function(n){ return '₱' + php.format(Math.round(n)); };

    function step(min, max){
        var r = max - min;
        return r <= 20000 ? 50 : r <= 100000 ? 100 : r <= 500000 ? 500 : 1000;
    }

    function selectProduct(idx) {
        selected = idx;
        var p = products[idx];
        btns.forEach(function(b, i){ b.classList.toggle('active', i === idx); });
        amtSlider.min  = p.min; amtSlider.max  = p.max; amtSlider.step = step(p.min, p.max);
        amtSlider.value = Math.round(((p.min + p.max) / 2) / step(p.min, p.max)) * step(p.min, p.max);
        trmSlider.min  = p.minTerm; trmSlider.max = p.maxTerm;
        trmSlider.value = Math.round((p.minTerm + p.maxTerm) / 2);
        document.getElementById('lc-min-amount').textContent = fmt(p.min);
        document.getElementById('lc-max-amount').textContent = fmt(p.max);
        document.getElementById('lc-min-term').textContent   = p.minTerm + ' mo';
        document.getElementById('lc-max-term').textContent   = p.maxTerm + ' mo';
        calc();
    }

    function calc() {
        var p = products[selected];
        var amt  = parseFloat(amtSlider.value);
        var term = parseInt(trmSlider.value, 10);
        var rate = p.rate / 100;
        document.getElementById('lc-amount-display').textContent = fmt(amt);
        document.getElementById('lc-term-display').textContent   = term + ' mo';
        var monthly = 0, interest = 0, total = 0;
        if (p.type === 'Flat' || p.type === 'Fixed') {
            interest = amt * rate * term; total = amt + interest; monthly = total / term;
        } else if (p.type === 'Diminishing') {
            monthly  = rate > 0 ? amt * (rate * Math.pow(1+rate,term)) / (Math.pow(1+rate,term)-1) : amt/term;
            total    = monthly * term; interest = total - amt;
        }
        var fee = amt * (p.processing / 100);
        document.getElementById('lc-monthly').textContent  = fmt(monthly);
        document.getElementById('lc-interest').textContent = fmt(interest);
        document.getElementById('lc-fee').textContent      = fmt(fee);
        document.getElementById('lc-total').textContent    = fmt(total);
    }

    btns.forEach(function(b, i){ b.addEventListener('click', function(){ selectProduct(i); }); });
    amtSlider.addEventListener('input', calc);
    trmSlider.addEventListener('input', calc);
    if (products.length > 0) selectProduct(0);
})();
</script>
<?php endif; ?>
</body>
</html>
