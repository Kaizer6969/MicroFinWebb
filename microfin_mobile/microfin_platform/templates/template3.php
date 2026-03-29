<?php
/**
 * Template 3 — Modern Fintech SaaS (Ultra-clean, high-trust, structured layout)
 * Adapted for MicroFin multi-tenant platform.
 * Failsafe version: Gracefully falls back to 'Debug Debug' if SQL/backend variables are missing.
 */

// ─── FAILSAFE / DEBUG INITIALIZATION ─────────────────────────────────────────
if (!isset($e) || !is_callable($e)) {
    $e = function($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); };
}

$headline_font = $headline_font ?? 'Inter';
$font_family   = $font_family ?? 'Inter';
$body_font     = $body_font ?? $font_family;

// Fallback palette optimized for a clean, professional SaaS look
$p = $palette ?? [
    'primary' => '#0ea5e9', // Trustworthy Blue
    'on-primary' => '#ffffff',
    'secondary' => '#10b981', // Growth Green
    'tertiary' => '#f59e0b',
    'background' => '#f8fafc',
    'on-background' => '#0f172a',
    'surface' => '#ffffff',
    'surface-container-lowest' => '#ffffff',
    'surface-container-low' => '#f1f5f9',
    'surface-container' => '#e2e8f0',
    'on-surface-variant' => '#475569',
    'outline-variant' => '#cbd5e1',
    'secondary-container' => '#d1fae5',
    'on-secondary-container' => '#064e3b'
];

// General Data
$tenant_name   = $tenant_name ?? 'Debug';
$meta_desc     = $meta_desc ?? 'Modern financial services for everyone.';
$logo          = $logo ?? '';
$site_slug     = $site_slug ?? 'debug-slug';
$custom_css    = $custom_css ?? '';

// Toggles
$show_about            = $show_about ?? true;
$show_services         = $show_services ?? true;
$show_contact          = $show_contact ?? true;
$show_download_section = $show_download_section ?? true;

// Hero Section
$hero_title    = $hero_title ?? "Smart Finance,\nSimplified.";
$hero_subtitle = $hero_subtitle ?? 'Fast & Secure';
$hero_desc     = $hero_desc ?? 'Experience the next generation of financial services. Fast approvals, transparent terms, and complete control over your growth.';
$hero_cta_url  = $hero_cta_url ?? '#';
$hero_cta_text = $hero_cta_text ?? 'Get Started';
$hero_image    = $hero_image ?? '';

// Services Array
$services_heading = $services_heading ?? 'Our Solutions';
if (empty($services)) {
    $services = [
        ['icon' => 'payments', 'title' => 'Instant Loans', 'description' => 'Access capital quickly with our streamlined digital approval process.'],
        ['icon' => 'monitoring', 'title' => 'Growth Tracking', 'description' => 'Monitor your financial health with real-time analytics and insights.'],
        ['icon' => 'shield', 'title' => 'Bank-Level Security', 'description' => 'Your data and funds are protected by enterprise-grade encryption.']
    ];
}

// About Section
$about_heading = $about_heading ?? 'Why Choose Us';
$about_body    = $about_body ?? "We believe financial empowerment should be accessible to everyone.\n\nOur platform breaks down traditional barriers, offering fair, transparent, and rapid financial solutions to help you achieve your goals.";
$about_image   = $about_image ?? '';

// Stats
$total_clients = $total_clients ?? 999;
$total_loans   = $total_loans ?? 999;

// Download Section
$download_title       = $download_title ?? 'Finance in your pocket';
$download_description = $download_description ?? 'Download our mobile app to track your loans, make payments, and manage your account anytime, anywhere.';
$download_button_text = $download_button_text ?? 'Download App';
$download_url         = $download_url ?? '#';

// Contact Data
$contact_address  = $contact_address ?? '123 Tech Boulevard, Innovation City';
$contact_phone    = $contact_phone ?? '1-800-MICROFIN';
$contact_email    = $contact_email ?? 'support@example.com';
$contact_hours    = $contact_hours ?? 'Mon-Fri: 9:00 AM - 6:00 PM';
// ─── END FAILSAFE INITIALIZATION ─────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?php echo $e($tenant_name); ?> — Official Website</title>
<?php if ($meta_desc): ?><meta name="description" content="<?php echo $e($meta_desc); ?>"><?php endif; ?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($headline_font); ?>:wght@400;500;600;700;800&family=<?php echo urlencode($body_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: <?php echo json_encode($p, JSON_UNESCAPED_SLASHES); ?>,
            fontFamily: {
                "headline": ["<?php echo $e($headline_font); ?>", "sans-serif"],
                "body": ["<?php echo $e($body_font); ?>", "sans-serif"],
                "label": ["<?php echo $e($body_font); ?>", "sans-serif"]
            },
            boxShadow: {
                'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.05)',
                'glow': '0 0 40px -10px var(--tw-shadow-color)',
            }
        },
    },
}
</script>
<style>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
}
.bg-mesh {
    background-image: 
        radial-gradient(at 0% 0%, <?php echo $e($p['primary']); ?>15 0px, transparent 50%),
        radial-gradient(at 100% 100%, <?php echo $e($p['secondary']); ?>10 0px, transparent 50%);
}
</style>
<?php if ($custom_css !== ''): ?><style><?php echo strip_tags($custom_css); ?></style><?php endif; ?>
</head>
<body class="bg-background text-on-background font-body antialiased">

<header class="fixed top-0 w-full z-50 bg-surface/80 backdrop-blur-lg border-b border-outline-variant/20 transition-all duration-300">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="flex items-center justify-between h-20">
            <a href="#" class="flex items-center gap-3 no-underline group">
                <?php if ($logo): ?>
                    <img src="<?php echo $e($logo); ?>" alt="Logo" class="h-8 w-8 object-contain">
                <?php else: ?>
                    <div class="h-8 w-8 bg-primary rounded-lg flex items-center justify-center text-on-primary">
                        <span class="material-symbols-outlined text-xl">account_balance</span>
                    </div>
                <?php endif; ?>
                <span class="text-xl font-bold font-headline text-on-background tracking-tight group-hover:text-primary transition-colors"><?php echo $e($tenant_name); ?></span>
            </a>

            <nav class="hidden md:flex items-center gap-8 font-label text-sm font-medium text-on-surface-variant">
                <a href="#" class="hover:text-primary transition-colors">Home</a>
                <?php if ($show_about): ?><a href="#about" class="hover:text-primary transition-colors">About</a><?php endif; ?>
                <?php if ($show_services && !empty($services)): ?><a href="#services" class="hover:text-primary transition-colors">Solutions</a><?php endif; ?>
                <?php if ($show_contact): ?><a href="#contact" class="hover:text-primary transition-colors">Contact</a><?php endif; ?>
            </nav>

            <div class="hidden md:flex items-center gap-4">
                <a href="tenant_login/login.php?s=<?php echo urlencode($site_slug); ?>&auth=1" class="text-sm font-semibold text-on-background hover:text-primary transition-colors px-2">Log In</a>
                <a href="<?php echo $e($hero_cta_url); ?>" class="bg-primary text-on-primary text-sm font-semibold px-5 py-2.5 rounded-lg shadow-soft hover:shadow-glow shadow-primary/30 transition-all duration-300 transform hover:-translate-y-0.5">
                    <?php echo $e($hero_cta_text); ?>
                </a>
            </div>

            <button id="mobileMenuBtn" class="md:hidden text-on-background p-2">
                <span class="material-symbols-outlined text-2xl">menu</span>
            </button>
        </div>
    </div>

    <div id="mobileMenu" class="hidden md:hidden bg-surface border-t border-outline-variant/20 absolute w-full shadow-2xl">
        <div class="flex flex-col px-6 py-6 gap-5 text-base font-medium text-on-surface-variant">
            <a href="#" class="hover:text-primary">Home</a>
            <?php if ($show_about): ?><a href="#about" class="hover:text-primary">About</a><?php endif; ?>
            <?php if ($show_services): ?><a href="#services" class="hover:text-primary">Solutions</a><?php endif; ?>
            <?php if ($show_contact): ?><a href="#contact" class="hover:text-primary">Contact</a><?php endif; ?>
            <div class="h-px w-full bg-outline-variant/20 my-2"></div>
            <a href="tenant_login/login.php?s=<?php echo urlencode($site_slug); ?>&auth=1" class="text-center py-3 w-full border border-outline-variant rounded-lg font-semibold text-on-background">Log In</a>
            <a href="<?php echo $e($hero_cta_url); ?>" class="text-center py-3 w-full bg-primary text-on-primary rounded-lg font-semibold shadow-soft">
                <?php echo $e($hero_cta_text); ?>
            </a>
        </div>
    </div>
</header>

<main class="pt-20">
    <section class="relative pt-20 pb-32 lg:pt-32 lg:pb-40 px-6 overflow-hidden bg-mesh">
        <div class="container mx-auto max-w-7xl">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-8 items-center">
                <div class="max-w-2xl">
                    <?php if ($hero_subtitle): ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary/10 text-primary font-semibold text-xs uppercase tracking-wide mb-6">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                        <?php echo $e($hero_subtitle); ?>
                    </div>
                    <?php endif; ?>
                    
                    <h1 class="text-5xl lg:text-6xl xl:text-7xl font-extrabold font-headline text-on-background tracking-tight leading-[1.1] mb-6">
                        <?php echo nl2br($e($hero_title)); ?>
                    </h1>
                    
                    <?php if ($hero_desc): ?>
                    <p class="text-lg text-on-surface-variant leading-relaxed mb-10 max-w-xl">
                        <?php echo $e($hero_desc); ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="flex flex-wrap items-center gap-4">
                        <a href="<?php echo $e($hero_cta_url); ?>" class="bg-primary text-on-primary font-semibold px-8 py-4 rounded-xl shadow-soft hover:shadow-glow shadow-primary/30 transition-all duration-300 transform hover:-translate-y-1 flex items-center gap-2">
                            <?php echo $e($hero_cta_text); ?>
                            <span class="material-symbols-outlined text-lg">arrow_forward</span>
                        </a>
                        <?php if ($show_about): ?>
                        <a href="#about" class="px-8 py-4 rounded-xl font-semibold text-on-background hover:bg-surface-container-low transition-colors flex items-center gap-2">
                            Learn more
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="relative lg:ml-auto w-full max-w-lg">
                    <div class="absolute inset-0 bg-gradient-to-tr from-primary/20 to-secondary/20 blur-3xl rounded-full transform scale-110 -z-10"></div>
                    
                    <?php if ($hero_image): ?>
                    <div class="rounded-2xl overflow-hidden shadow-2xl border border-outline-variant/20 bg-surface">
                        <img alt="Hero" class="w-full h-auto object-cover aspect-[4/3]" src="<?php echo $e($hero_image); ?>"/>
                    </div>
                    <?php else: ?>
                    <div class="rounded-2xl overflow-hidden shadow-2xl border border-outline-variant/20 bg-surface aspect-[4/3] flex items-center justify-center relative">
                        <div class="absolute inset-0 opacity-[0.03] bg-[radial-gradient(#000_1px,transparent_1px)] [background-size:16px_16px]"></div>
                        <span class="material-symbols-outlined text-primary/20" style="font-size: 140px;">dashboard</span>
                    </div>
                    <?php endif; ?>

                    <div class="absolute -bottom-6 -left-6 md:-left-10 bg-surface p-5 rounded-xl shadow-xl border border-outline-variant/10 flex items-center gap-4 animate-[bounce_4s_infinite]">
                        <div class="h-12 w-12 rounded-full bg-secondary/10 flex items-center justify-center text-secondary">
                            <span class="material-symbols-outlined">group</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold font-headline text-on-background leading-none"><?php echo is_numeric($total_clients) ? number_format($total_clients) : '999'; ?>+</p>
                            <p class="text-xs text-on-surface-variant font-medium mt-1">Trusted Users</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10 border-y border-outline-variant/10 bg-surface-container-lowest">
        <div class="container mx-auto px-6 text-center">
            <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-widest mb-6">Trusted by growing businesses</p>
            <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16 opacity-40 grayscale">
                <span class="material-symbols-outlined text-4xl">payments</span>
                <span class="material-symbols-outlined text-4xl">account_balance</span>
                <span class="material-symbols-outlined text-4xl">store</span>
                <span class="material-symbols-outlined text-4xl">insights</span>
                <span class="material-symbols-outlined text-4xl">security</span>
            </div>
        </div>
    </section>

    <?php if ($show_services && !empty($services)): ?>
    <section id="services" class="py-24 px-6 bg-background">
        <div class="container mx-auto max-w-7xl">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-primary font-bold text-sm uppercase tracking-wider"><?php echo $e($services_heading); ?></span>
                <h2 class="text-3xl md:text-4xl font-extrabold font-headline text-on-background mt-3 mb-4">Built for modern finance</h2>
                <p class="text-on-surface-variant text-lg">Everything you need to manage capital, track growth, and scale securely in one unified platform.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach ($services as $i => $svc): ?>
                <div class="bg-surface rounded-2xl p-8 shadow-soft border border-outline-variant/10 hover:shadow-xl hover:border-primary/20 transition-all duration-300 group">
                    <div class="w-14 h-14 rounded-xl bg-primary/5 text-primary flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;"><?php echo $e($svc['icon'] ?? 'check_circle'); ?></span>
                    </div>
                    <h3 class="text-xl font-bold font-headline text-on-background mb-3"><?php echo $e($svc['title'] ?? ''); ?></h3>
                    <p class="text-on-surface-variant leading-relaxed"><?php echo $e($svc['description'] ?? ''); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="py-24 px-6 bg-surface border-y border-outline-variant/10 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-surface-container-low to-transparent -z-10"></div>

        <div class="container mx-auto max-w-7xl">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div>
                    <h2 class="text-3xl md:text-4xl font-extrabold font-headline text-on-background mb-6">How it works</h2>
                    <p class="text-on-surface-variant text-lg mb-12">We've stripped away the complexity. Get funded and start growing in three simple steps.</p>
                    
                    <div class="space-y-10">
                        <div class="flex gap-6">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-lg shadow-glow shadow-primary/30">1</div>
                            <div>
                                <h4 class="text-xl font-bold text-on-background mb-2">Create your account</h4>
                                <p class="text-on-surface-variant">Sign up in minutes. No lengthy paperwork or branch visits required. Fully digital onboarding.</p>
                            </div>
                        </div>
                        <div class="flex gap-6">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-surface-container-high text-on-background flex items-center justify-center font-bold text-lg">2</div>
                            <div>
                                <h4 class="text-xl font-bold text-on-background mb-2">Select your product</h4>
                                <p class="text-on-surface-variant">Choose the financial solution that fits your exact needs. Transparent rates, zero hidden fees.</p>
                            </div>
                        </div>
                        <div class="flex gap-6">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-surface-container-high text-on-background flex items-center justify-center font-bold text-lg">3</div>
                            <div>
                                <h4 class="text-xl font-bold text-on-background mb-2">Get funded instantly</h4>
                                <p class="text-on-surface-variant">Once approved, funds are disbursed directly to your account, ready to be utilized immediately.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="relative w-full h-[500px] bg-surface-container rounded-2xl overflow-hidden border border-outline-variant/20 shadow-inner flex items-center justify-center">
                     <div class="absolute inset-0 opacity-[0.05] bg-[radial-gradient(#000_1px,transparent_1px)] [background-size:16px_16px]"></div>
                     <div class="w-3/4 bg-surface rounded-xl shadow-xl p-6 relative z-10">
                         <div class="flex items-center justify-between mb-6">
                             <div class="h-4 w-24 bg-surface-container-high rounded"></div>
                             <div class="h-8 w-8 bg-primary/20 rounded-full"></div>
                         </div>
                         <div class="h-10 w-1/2 bg-surface-container-high rounded mb-4"></div>
                         <div class="h-4 w-1/3 bg-surface-container rounded mb-8"></div>
                         <div class="grid grid-cols-2 gap-4">
                             <div class="h-24 bg-surface-container rounded-lg border border-outline-variant/20"></div>
                             <div class="h-24 bg-surface-container rounded-lg border border-outline-variant/20"></div>
                         </div>
                         <div class="mt-6 h-12 w-full bg-primary rounded-lg"></div>
                     </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($show_about): ?>
    <section id="about" class="py-24 px-6 bg-background">
        <div class="container mx-auto max-w-7xl">
            <div class="bg-surface rounded-3xl p-10 lg:p-16 shadow-soft border border-outline-variant/10">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">
                    <div>
                        <span class="text-primary font-bold text-sm uppercase tracking-wider"><?php echo $e($about_heading); ?></span>
                        <h2 class="text-3xl md:text-4xl font-extrabold font-headline text-on-background mt-3 mb-6 leading-tight">Empowering growth through accessible finance.</h2>
                        <?php if ($about_body): ?>
                        <p class="text-on-surface-variant text-lg leading-relaxed mb-8"><?php echo nl2br($e($about_body)); ?></p>
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-2 gap-8 pt-8 border-t border-outline-variant/10">
                            <div>
                                <p class="text-4xl font-extrabold text-on-background font-headline tracking-tight mb-1"><?php echo is_numeric($total_clients) ? number_format($total_clients) : '999'; ?>+</p>
                                <p class="text-sm font-semibold text-on-surface-variant">Active Members</p>
                            </div>
                            <div>
                                <p class="text-4xl font-extrabold text-on-background font-headline tracking-tight mb-1"><?php echo is_numeric($total_loans) ? number_format($total_loans) : '999'; ?>+</p>
                                <p class="text-sm font-semibold text-on-surface-variant">Loans Disbursed</p>
                            </div>
                        </div>
                    </div>
                    <div class="relative h-full min-h-[300px]">
                        <?php if ($about_image): ?>
                        <img alt="About" class="w-full h-full object-cover rounded-2xl" src="<?php echo $e($about_image); ?>"/>
                        <?php else: ?>
                        <div class="w-full h-full bg-surface-container rounded-2xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-on-surface-variant/20" style="font-size: 80px;">public</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show_download_section): ?>
    <section id="download" class="py-24 px-6 relative overflow-hidden">
        <div class="absolute inset-0 bg-on-background"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-primary/30 to-transparent"></div>
        
        <div class="container mx-auto max-w-4xl relative z-10 text-center">
            <h2 class="text-4xl md:text-5xl font-extrabold font-headline text-surface mb-6"><?php echo $e($download_title); ?></h2>
            <?php if ($download_description): ?>
            <p class="text-surface/80 text-lg md:text-xl mb-10 max-w-2xl mx-auto leading-relaxed"><?php echo nl2br($e($download_description)); ?></p>
            <?php endif; ?>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="<?php echo $e($download_url); ?>" target="_blank" rel="noopener noreferrer" class="w-full sm:w-auto bg-primary text-on-primary font-bold px-8 py-4 rounded-xl shadow-glow shadow-primary/40 hover:-translate-y-1 transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">android</span>
                    <?php echo $e($download_button_text); ?>
                </a>
                <a href="#" class="w-full sm:w-auto bg-surface/10 text-surface font-bold px-8 py-4 rounded-xl border border-surface/20 hover:bg-surface/20 transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">apple</span>
                    App Store
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<footer id="contact" class="bg-background pt-20 pb-10 px-6 border-t border-outline-variant/10">
    <div class="container mx-auto max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-12 lg:gap-8 mb-16">
            
            <div class="lg:col-span-2">
                <a href="#" class="flex items-center gap-2 no-underline mb-6">
                    <?php if ($logo): ?><img src="<?php echo $e($logo); ?>" alt="Logo" class="h-6 w-6 object-contain"><?php endif; ?>
                    <span class="font-headline text-lg font-bold text-on-background"><?php echo $e($tenant_name); ?></span>
                </a>
                <p class="text-on-surface-variant text-sm leading-relaxed max-w-sm mb-6">
                    Modern financial technology built to help you grow. Transparent, secure, and lightning fast.
                </p>
                <div class="flex items-center gap-4 text-on-surface-variant">
                    <a href="#" class="hover:text-primary transition-colors"><span class="material-symbols-outlined">public</span></a>
                    <a href="#" class="hover:text-primary transition-colors"><span class="material-symbols-outlined">share</span></a>
                </div>
            </div>

            <?php if ($show_services && !empty($services)): ?>
            <div>
                <h4 class="font-bold text-on-background mb-6 text-sm">Product</h4>
                <ul class="space-y-4 text-sm text-on-surface-variant">
                    <?php foreach (array_slice($services, 0, 4) as $svc): ?>
                    <li><a href="#services" class="hover:text-primary transition-colors"><?php echo $e($svc['title'] ?? ''); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div>
                <h4 class="font-bold text-on-background mb-6 text-sm">Company</h4>
                <ul class="space-y-4 text-sm text-on-surface-variant">
                    <li><a href="#" class="hover:text-primary transition-colors">Home</a></li>
                    <?php if ($show_about): ?><li><a href="#about" class="hover:text-primary transition-colors">About Us</a></li><?php endif; ?>
                    <?php if ($show_contact): ?><li><a href="#contact" class="hover:text-primary transition-colors">Contact</a></li><?php endif; ?>
                </ul>
            </div>

            <?php if ($show_contact): ?>
            <div>
                <h4 class="font-bold text-on-background mb-6 text-sm">Contact Support</h4>
                <ul class="space-y-4 text-sm text-on-surface-variant">
                    <?php if ($contact_email): ?>
                    <li>
                        <a href="mailto:<?php echo $e($contact_email); ?>" class="hover:text-primary transition-colors flex items-start gap-2">
                            <span class="material-symbols-outlined text-base">mail</span>
                            <?php echo $e($contact_email); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($contact_phone): ?>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-base">call</span>
                        <?php echo $e($contact_phone); ?>
                    </li>
                    <?php endif; ?>
                    <?php if ($contact_address): ?>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-base">location_on</span>
                        <span><?php echo nl2br($e($contact_address)); ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>
        
        <div class="pt-8 border-t border-outline-variant/10 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-sm text-on-surface-variant">&copy; <?php echo date('Y'); ?> <?php echo $e($tenant_name); ?>. All rights reserved.</p>
            <div class="flex items-center gap-6 text-sm text-on-surface-variant">
                <a href="#" class="hover:text-on-background transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-on-background transition-colors">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<script>
    const btn = document.getElementById('mobileMenuBtn');
    const menu = document.getElementById('mobileMenu');
    const icon = btn.querySelector('span');
    
    btn.addEventListener('click', () => {
        menu.classList.toggle('hidden');
        if(menu.classList.contains('hidden')) {
            icon.textContent = 'menu';
        } else {
            icon.textContent = 'close';
        }
    });

    // Close menu when clicking a link
    menu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            menu.classList.add('hidden');
            icon.textContent = 'menu';
        });
    });

    // Navbar blur effect on scroll
    window.addEventListener('scroll', () => {
        const header = document.querySelector('header');
        if (window.scrollY > 20) {
            header.classList.add('shadow-sm');
        } else {
            header.classList.remove('shadow-sm');
        }
    });
</script>

</body>
</html>