<?php
/**
 * Template 2 — Editorial / Minimalist (Magazine-style layout)
 * Adapted for MicroFin multi-tenant platform.
 * Failsafe version: Gracefully falls back to 'Debug Debug' if SQL/backend variables are missing.
 */

// ─── FAILSAFE / DEBUG INITIALIZATION ─────────────────────────────────────────
if (!isset($e) || !is_callable($e)) {
    $e = function($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); };
}

$headline_font = $headline_font ?? 'Newsreader';
$font_family   = $font_family ?? 'Manrope';
$body_font     = $body_font ?? $font_family;

// Fallback palette mapped for Tailwind CSS
$p = $palette ?? [
    'primary' => '#1a1a1a',
    'on-primary' => '#ffffff',
    'secondary' => '#4a4a4a',
    'background' => '#fafafa',
    'on-background' => '#1a1a1a',
    'surface' => '#ffffff',
    'surface-container-lowest' => '#ffffff',
    'surface-container-low' => '#f5f5f5',
    'surface-container' => '#eeeeee',
    'surface-container-high' => '#e0e0e0',
    'on-surface-variant' => '#5e5e5e',
    'outline-variant' => '#cccccc',
    'secondary-container' => '#e8e8e8',
    'on-secondary-container' => '#1a1a1a'
];

// General Data
$tenant_name   = $tenant_name ?? 'Debug Debug Name';
$meta_desc     = $meta_desc ?? 'Debug Debug Meta Description';
$logo          = $logo ?? '';
$site_slug     = $site_slug ?? 'debug-slug';
$custom_css    = $custom_css ?? '';

// Toggles
$show_about            = $show_about ?? true;
$show_services         = $show_services ?? true;
$show_contact          = $show_contact ?? true;
$show_download_section = $show_download_section ?? true;

// Hero Section
$hero_title    = $hero_title ?? "Debug Debug\nHero Title";
$hero_subtitle = $hero_subtitle ?? 'Debug Subtitle';
$hero_desc     = $hero_desc ?? 'Debug debug description text here for the hero section. This introduces the editorial narrative.';
$hero_cta_url  = $hero_cta_url ?? '#';
$hero_cta_text = $hero_cta_text ?? 'Debug CTA';
$hero_image    = $hero_image ?? '';

// Services Array
$services_heading = $services_heading ?? 'Debug Services';
if (empty($services)) {
    $services = [
        ['title' => 'Debug Service 1', 'description' => 'Debug debug debug debug description. Editorial style insights go here.'],
        ['title' => 'Debug Service 2', 'description' => 'Debug debug debug debug description. Editorial style insights go here.'],
        ['title' => 'Debug Service 3', 'description' => 'Debug debug debug debug description. Editorial style insights go here.'],
        ['title' => 'Debug Service 4', 'description' => 'Debug debug debug debug description. Editorial style insights go here.']
    ];
}

// About Section
$about_heading = $about_heading ?? 'Debug About';
$about_body    = $about_body ?? "Debug debug debug debug.\nDebug debug debug debug.";
$about_image   = $about_image ?? '';

// Stats
$total_clients = $total_clients ?? 999;
$total_loans   = $total_loans ?? 999;

// Download Section
$download_title       = $download_title ?? 'Debug Download Title';
$download_description = $download_description ?? 'Debug download description text here. Manage your account seamlessly.';
$download_button_text = $download_button_text ?? 'Debug App';
$download_url         = $download_url ?? '#';

// Contact Data
$contact_address  = $contact_address ?? 'Debug Address Placeholder';
$contact_phone    = $contact_phone ?? '000-DEBUG-000';
$contact_email    = $contact_email ?? 'debug@debug.com';
$contact_hours    = $contact_hours ?? 'Mon-Fri: Debug Hours';
// ─── END FAILSAFE INITIALIZATION ─────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?php echo $e($tenant_name); ?> — Official Website</title>
<?php if ($meta_desc): ?><meta name="description" content="<?php echo $e($meta_desc); ?>"><?php endif; ?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($headline_font); ?>:ital,wght@0,400;0,700;1,400&family=<?php echo urlencode($body_font); ?>:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: <?php echo json_encode($p, JSON_UNESCAPED_SLASHES); ?>,
            fontFamily: {
                "headline": ["<?php echo $e($headline_font); ?>", "Georgia", "serif"],
                "body": ["<?php echo $e($body_font); ?>", "sans-serif"],
                "label": ["<?php echo $e($body_font); ?>", "sans-serif"]
            },
            borderRadius: {"DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem"},
        },
    },
}
</script>
<style>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
}
.editorial-gradient {
    background: linear-gradient(180deg, transparent 0%, <?php echo $e($p['surface']); ?> 100%);
}
html { scroll-behavior: smooth; }
</style>
<?php if ($custom_css !== ''): ?><style><?php echo strip_tags($custom_css); ?></style><?php endif; ?>
</head>
<body class="bg-background text-on-background font-body selection:bg-secondary-container selection:text-on-secondary-container">

<header class="fixed top-0 w-full z-50 bg-background/90 backdrop-blur-xl border-b border-outline-variant/20 shadow-sm">
    <div class="container mx-auto flex justify-between items-center px-6 h-16 md:h-20">
        <div class="flex items-center gap-8">
            <a href="#" class="font-headline text-xl font-bold text-primary flex items-center gap-3 no-underline">
                <?php if ($logo): ?><img src="<?php echo $e($logo); ?>" alt="Logo" class="h-8 w-8 rounded-md object-cover"><?php endif; ?>
                <?php echo $e($tenant_name); ?>
            </a>
            <nav class="hidden md:flex gap-8 text-sm font-label uppercase tracking-widest mt-1">
                <a class="text-on-surface-variant hover:text-primary transition-colors" href="#">Home</a>
                <?php if ($show_about): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#about">About</a><?php endif; ?>
                <?php if ($show_services && !empty($services)): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#services">Services</a><?php endif; ?>
                <?php if ($show_contact): ?><a class="text-on-surface-variant hover:text-primary transition-colors" href="#contact">Contact</a><?php endif; ?>
            </nav>
        </div>
        
        <div class="hidden md:flex items-center gap-4">
            <a href="tenant_login/login.php?s=<?php echo urlencode($site_slug); ?>&auth=1" class="px-5 py-2.5 border border-primary text-primary text-sm font-bold rounded hover:bg-primary/5 transition-all no-underline">Log In</a>
            <a href="<?php echo $e($hero_cta_url); ?>" class="px-5 py-2.5 bg-primary text-on-primary text-sm font-bold rounded hover:opacity-90 transition-all no-underline shadow-md">
                <?php echo $e($hero_cta_text); ?>
            </a>
        </div>

        <button id="mobileMenuBtn" class="md:hidden text-primary p-2 focus:outline-none">
            <span class="material-symbols-outlined text-3xl">menu</span>
        </button>
    </div>

    <div id="mobileMenu" class="hidden md:hidden bg-background border-b border-outline-variant/20 absolute w-full shadow-xl">
        <nav class="flex flex-col px-6 py-4 gap-4 text-sm font-label font-bold uppercase tracking-wider">
            <a class="text-primary" href="#">Home</a>
            <?php if ($show_about): ?><a class="text-on-surface-variant" href="#about">About</a><?php endif; ?>
            <?php if ($show_services && !empty($services)): ?><a class="text-on-surface-variant" href="#services">Services</a><?php endif; ?>
            <?php if ($show_contact): ?><a class="text-on-surface-variant" href="#contact">Contact</a><?php endif; ?>
            <hr class="border-outline-variant/20 my-2">
            <a href="tenant_login/login.php?s=<?php echo urlencode($site_slug); ?>&auth=1" class="w-full text-center px-4 py-3 border border-primary text-primary rounded no-underline">Log In</a>
            <a href="<?php echo $e($hero_cta_url); ?>" class="w-full text-center px-4 py-3 bg-primary text-on-primary rounded no-underline">
                <?php echo $e($hero_cta_text); ?>
            </a>
        </nav>
    </div>
</header>

<main class="pt-16 md:pt-20">
    <section class="py-20 md:py-32 px-6 border-b border-outline-variant/10">
        <div class="container mx-auto max-w-5xl">
            <div class="grid md:grid-cols-12 gap-8 md:gap-12">
                <div class="md:col-span-8">
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-headline font-bold text-primary leading-tight tracking-tight mb-6">
                        <?php echo nl2br($e($hero_title)); ?>
                    </h1>
                </div>
                <div class="md:col-span-4 flex flex-col justify-end">
                    <?php if ($hero_desc): ?>
                    <p class="text-on-surface-variant text-lg leading-relaxed mb-8 font-body">
                        <?php echo $e($hero_desc); ?>
                    </p>
                    <?php endif; ?>
                    <a href="<?php echo $e($hero_cta_url); ?>" class="inline-flex items-center gap-3 text-primary font-bold text-base group no-underline">
                        <?php echo $e($hero_cta_text); ?>
                        <span class="material-symbols-outlined group-hover:translate-x-2 transition-transform">east</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="relative">
        <?php if ($hero_image): ?>
        <div class="aspect-[16/9] md:aspect-[21/9] max-h-[600px] w-full overflow-hidden">
            <img alt="<?php echo $e($tenant_name); ?>" class="w-full h-full object-cover" src="<?php echo $e($hero_image); ?>"/>
        </div>
        <?php else: ?>
        <div class="aspect-[16/9] md:aspect-[21/9] max-h-[600px] w-full overflow-hidden bg-gradient-to-br from-surface-container-high to-surface-container flex items-center justify-center">
            <span class="material-symbols-outlined text-on-surface-variant/20" style="font-size: 160px;">landscape</span>
        </div>
        <?php endif; ?>
        
        <div class="absolute bottom-0 left-0 w-full h-1/2 editorial-gradient"></div>
        
        <?php if ($hero_subtitle): ?>
        <div class="absolute bottom-0 left-0 p-6 md:p-12">
            <div class="bg-primary text-on-primary px-6 py-3 inline-block shadow-lg">
                <span class="text-xs md:text-sm font-bold font-label uppercase tracking-[0.2em]"><?php echo $e($hero_subtitle); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($show_services && !empty($services)): ?>
    <section id="services" class="py-24 px-6 border-b border-outline-variant/10">
        <div class="container mx-auto max-w-5xl">
            <div class="flex flex-col md:flex-row items-start md:items-end justify-between mb-12 md:mb-16 gap-6">
                <div>
                    <span class="text-xs font-bold text-secondary uppercase tracking-[0.2em] mb-3 block font-label"><?php echo $e($services_heading); ?></span>
                    <h2 class="text-4xl md:text-5xl font-headline font-bold text-primary leading-tight">What We Offer</h2>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <?php foreach ($services as $i => $svc): ?>
                <?php
                    // Alternate card sizes for editorial bento layout
                    $span = ($i === 0) ? 'md:col-span-7' : (($i === 1) ? 'md:col-span-5' : 'md:col-span-4');
                    if ($i > 2 && ($i % 3) === 0) $span = 'md:col-span-5';
                    elseif ($i > 2 && ($i % 3) === 1) $span = 'md:col-span-7';
                    elseif ($i > 2) $span = 'md:col-span-4';
                ?>
                <div class="<?php echo $span; ?> bg-surface-container-lowest border border-outline-variant/20 p-8 md:p-10 rounded-xl group hover:border-primary/40 hover:shadow-xl transition-all duration-300">
                    <div>
                        <span class="text-xs font-bold font-label text-secondary uppercase tracking-[0.15em]"><?php echo str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <h3 class="text-2xl font-headline font-bold text-primary mt-5 mb-4"><?php echo $e($svc['title'] ?? ''); ?></h3>
                    <p class="text-on-surface-variant text-base leading-relaxed"><?php echo $e($svc['description'] ?? ''); ?></p>
                    <div class="mt-8 flex items-center gap-2 text-primary text-sm font-bold group-hover:gap-4 transition-all duration-300">
                        <span class="uppercase tracking-wider">Explore</span>
                        <span class="material-symbols-outlined text-base">east</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show_about && $about_body): ?>
    <section id="about" class="py-24 md:py-32 px-6 bg-surface-container-low">
        <div class="container mx-auto max-w-4xl text-center">
            <span class="material-symbols-outlined text-primary/10" style="font-size: 80px;">format_quote</span>
            <blockquote class="text-3xl md:text-5xl font-headline font-bold text-primary leading-tight tracking-tight mt-6 mb-10">
                <?php echo nl2br($e($about_body)); ?>
            </blockquote>
            <div class="flex items-center justify-center gap-5 mt-12">
                <?php if ($about_image): ?>
                <div class="h-16 w-16 rounded-full overflow-hidden shadow-md">
                    <img alt="About" class="w-full h-full object-cover" src="<?php echo $e($about_image); ?>"/>
                </div>
                <?php endif; ?>
                <div class="text-left">
                    <p class="font-bold text-primary text-base"><?php echo $e($about_heading); ?></p>
                    <p class="text-sm text-on-surface-variant tracking-wide"><?php echo $e($tenant_name); ?></p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="py-24 px-6 border-b border-outline-variant/10">
        <div class="container mx-auto max-w-5xl">
            <div class="grid md:grid-cols-3 gap-16 items-center">
                <div class="md:col-span-2">
                    <h2 class="text-4xl md:text-5xl font-headline font-bold text-primary mb-10 leading-tight">Our Impact</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-10">
                        <div class="border-l-4 border-primary pl-6">
                            <p class="text-5xl md:text-6xl font-headline font-bold text-primary tracking-tighter"><?php echo is_numeric($total_clients) ? number_format($total_clients) : '999'; ?>+</p>
                            <p class="text-sm text-on-surface-variant mt-3 font-label uppercase tracking-widest font-bold">Active Members</p>
                        </div>
                        <div class="border-l-4 border-secondary pl-6">
                            <p class="text-5xl md:text-6xl font-headline font-bold text-secondary tracking-tighter"><?php echo is_numeric($total_loans) ? number_format($total_loans) : '999'; ?>+</p>
                            <p class="text-sm text-on-surface-variant mt-3 font-label uppercase tracking-widest font-bold">Loans Funded</p>
                        </div>
                    </div>
                </div>
                <div class="order-first md:order-last">
                    <?php if ($about_image): ?>
                    <div class="aspect-square rounded-xl overflow-hidden shadow-xl">
                        <img alt="Impact" class="w-full h-full object-cover" src="<?php echo $e($about_image); ?>"/>
                    </div>
                    <?php else: ?>
                    <div class="aspect-square rounded-xl overflow-hidden bg-gradient-to-br from-primary/5 to-secondary/10 flex items-center justify-center shadow-inner">
                        <span class="material-symbols-outlined text-primary/20" style="font-size: 100px;">trending_up</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($show_download_section): ?>
    <section id="download" class="py-24 px-6 bg-surface-container">
        <div class="container mx-auto max-w-4xl text-center">
            <div class="bg-primary p-12 md:p-20 rounded-2xl shadow-2xl">
                <h2 class="text-4xl md:text-5xl font-headline font-bold text-on-primary mb-6 leading-tight"><?php echo $e($download_title); ?></h2>
                <?php if ($download_description): ?>
                <p class="text-on-primary/80 mb-12 text-lg max-w-2xl mx-auto leading-relaxed"><?php echo nl2br($e($download_description)); ?></p>
                <?php endif; ?>
                <a href="<?php echo $e($download_url); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-3 px-10 py-5 bg-background text-on-background font-bold text-base rounded hover:scale-105 transition-transform no-underline shadow-lg">
                    <?php echo $e($download_button_text); ?>
                    <span class="material-symbols-outlined text-xl">download</span>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<footer id="contact" class="bg-surface-container-lowest border-t border-outline-variant/20 pt-20 pb-12 px-6">
    <div class="container mx-auto max-w-5xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
            <div class="md:col-span-1">
                <span class="font-headline text-2xl font-bold text-primary block mb-6"><?php echo $e($tenant_name); ?></span>
                <?php if ($contact_address): ?>
                <p class="text-sm text-on-surface-variant leading-relaxed"><?php echo nl2br($e($contact_address)); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($show_services && !empty($services)): ?>
            <div>
                <h5 class="text-sm font-bold text-primary mb-6 font-label uppercase tracking-widest"><?php echo $e($services_heading); ?></h5>
                <ul class="space-y-4 text-sm text-on-surface-variant list-none p-0">
                    <?php foreach (array_slice($services, 0, 4) as $svc): ?>
                    <li><?php echo $e($svc['title'] ?? ''); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <div>
                <h5 class="text-sm font-bold text-primary mb-6 font-label uppercase tracking-widest">Navigation</h5>
                <ul class="space-y-4 text-sm text-on-surface-variant list-none p-0">
                    <li><a class="hover:text-primary transition-colors no-underline" href="#">Home</a></li>
                    <?php if ($show_about): ?><li><a class="hover:text-primary transition-colors no-underline" href="#about">About</a></li><?php endif; ?>
                    <?php if ($show_services): ?><li><a class="hover:text-primary transition-colors no-underline" href="#services">Services</a></li><?php endif; ?>
                    <?php if ($show_contact): ?><li><a class="hover:text-primary transition-colors no-underline" href="#contact">Contact</a></li><?php endif; ?>
                </ul>
            </div>
            <?php if ($show_contact): ?>
            <div>
                <h5 class="text-sm font-bold text-primary mb-6 font-label uppercase tracking-widest">Get in Touch</h5>
                <ul class="space-y-4 text-sm text-on-surface-variant list-none p-0">
                    <?php if ($contact_phone): ?><li><?php echo $e($contact_phone); ?></li><?php endif; ?>
                    <?php if ($contact_email): ?><li><a class="hover:text-primary transition-colors no-underline" href="mailto:<?php echo $e($contact_email); ?>"><?php echo $e($contact_email); ?></a></li><?php endif; ?>
                    <?php if ($contact_hours): ?><li><?php echo $e($contact_hours); ?></li><?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <div class="pt-8 border-t border-outline-variant/10 flex justify-between items-center">
            <p class="text-sm text-on-surface-variant font-label">&copy; <?php echo date('Y'); ?> <?php echo $e($tenant_name); ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<script>
    const btn = document.getElementById('mobileMenuBtn');
    const menu = document.getElementById('mobileMenu');
    
    btn.addEventListener('click', () => {
        menu.classList.toggle('hidden');
    });

    // Close menu when clicking a link
    menu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            menu.classList.add('hidden');
        });
    });
</script>

</body>
</html>