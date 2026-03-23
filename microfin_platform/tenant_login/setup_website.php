<?php
session_start();
require_once "../backend/db_connect.php";

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$tenant_name = $_SESSION['tenant_name'] ?? 'Your Organization';

if ($tenant_id === '') {
    header('Location: login.php');
    exit;
}

// Branding must be completed before website setup.
$branding_check = $pdo->prepare('SELECT branding_id, theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family FROM tenant_branding WHERE tenant_id = ?');
$branding_check->execute([$tenant_id]);
$branding = $branding_check->fetch(PDO::FETCH_ASSOC);

$accent = ($branding['theme_primary_color'] ?? '#0284c7');
$t_text = ($branding['theme_text_main'] ?? '#0f172a');
$t_muted = ($branding['theme_text_muted'] ?? '#64748b');
$t_bg = ($branding['theme_bg_body'] ?? '#f8fafc');
$t_card = ($branding['theme_bg_card'] ?? '#ffffff');
$t_font = ($branding['font_family'] ?? 'Inter');
$error = '';

// Check current setup step — this page is step 3 (website)
$step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed FROM tenants WHERE tenant_id = ?');
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 3) {
    if (in_array($current_step, [1, 2])) {
        // Upgrade any tenants stuck on removed steps 1 or 2 up to step 3
        $pdo->prepare('UPDATE tenants SET setup_current_step = 3 WHERE tenant_id = ?')->execute([$tenant_id]);
        $current_step = 3;
    } else {
        $setup_routes = [0 => 'force_change_password.php', 4 => 'setup_branding.php', 5 => 'setup_billing.php'];
        if (isset($setup_routes[$current_step])) {
            header('Location: ' . $setup_routes[$current_step]);
        } else {
            header('Location: ../admin_panel/admin.php');
        }
        exit;
    }
}

$download_url_setting_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'website_download_url' LIMIT 1");
$download_url_setting_stmt->execute([$tenant_id]);
$download_url_setting = $download_url_setting_stmt->fetchColumn();
$system_download_url = trim((string) ($download_url_setting ?: ''));



$bg_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'website_hero_background' LIMIT 1");
$bg_stmt->execute([$tenant_id]);
$hero_bg_setting = $bg_stmt->fetchColumn();
$system_hero_background = trim((string) ($hero_bg_setting ?: ''));

$defaults = [
    'layout_template' => 'template1',
    'hero_title' => 'Welcome to ' . $tenant_name,
    'hero_subtitle' => 'Your trusted microfinance partner',
    'hero_description' => '',
    'about_body' => '',
    'contact_phone' => '',
    'contact_email' => '',
    'contact_address' => '',
    'contact_hours' => '',
    'website_show_about' => '1',
    'website_show_services' => '1',
    'website_show_contact' => '1',
    'website_show_download' => '1',
    'website_download_url' => $system_download_url
];

$form = $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $layout = 'template1'; // Template 2/3 are temporarily unavailable.

    $hero_title = trim($_POST['hero_title'] ?? '');
    $hero_subtitle = trim($_POST['hero_subtitle'] ?? '');
    $hero_description = trim($_POST['hero_description'] ?? '');
    $about_body = trim($_POST['about_body'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_address = trim($_POST['contact_address'] ?? '');
    $contact_hours = trim($_POST['contact_hours'] ?? '');

    $show_about = isset($_POST['website_show_about']) ? '1' : '0';
    $show_services = isset($_POST['website_show_services']) ? '1' : '0';
    $show_contact = isset($_POST['website_show_contact']) ? '1' : '0';
    $show_download = isset($_POST['website_show_download']) ? '1' : '0';

    $download_url = $system_download_url;
    if ($download_url !== '' && filter_var($download_url, FILTER_VALIDATE_URL) === false) {
        $download_url = '';
    }


    $hero_background_path = $system_hero_background;
    if (isset($_FILES['hero_background']) && (int) $_FILES['hero_background']['error'] === UPLOAD_ERR_OK) {
        $original_name = $_FILES['hero_background']['name'];
        $tmp_name = $_FILES['hero_background']['tmp_name'];
        $size_bytes = (int) $_FILES['hero_background']['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($extension, $allowed, true) && $size_bytes <= (3 * 1024 * 1024)) {
            $upload_dir = __DIR__ . '/../uploads/tenant_logos';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
            $safe_tenant = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenant_id);
            $new_name = $safe_tenant . '_bg_' . time() . '.' . $extension;
            $dest = rtrim($upload_dir, '/') . '/' . $new_name;
            if (move_uploaded_file($tmp_name, $dest)) {
                $app_path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
                $hero_background_path = ($app_path === '' ? '/' : $app_path) . '/uploads/tenant_logos/' . $new_name;
            }
        }
    }

    $form = [
        'layout_template' => $layout,
        'hero_title' => $hero_title,
        'hero_subtitle' => $hero_subtitle,
        'hero_description' => $hero_description,
        'about_body' => $about_body,
        'contact_phone' => $contact_phone,
        'contact_email' => $contact_email,
        'contact_address' => $contact_address,
        'contact_hours' => $contact_hours,
        'website_show_about' => $show_about,
        'website_show_services' => $show_services,
        'website_show_contact' => $show_contact,
        'website_show_download' => $show_download,
        'website_download_url' => $download_url
    ];

    if ($hero_title === '') {
        $error = 'Hero title is required.';
    } else {
        $about_heading = 'About Us';
        $services_heading = 'Our Services';
        $services_json = json_encode([], JSON_UNESCAPED_UNICODE);
        $hero_cta_text = ($show_download === '1' && $download_url !== '') ? 'Download App' : 'Learn More';
        $hero_cta_url = ($show_download === '1' && $download_url !== '') ? '#download' : '#about';

        $upsert_website = $pdo->prepare('
            INSERT INTO tenant_website_content
                (tenant_id, layout_template, hero_title, hero_subtitle, hero_description,
                 hero_cta_text, hero_cta_url, hero_image_path,
                 about_heading, about_body, about_image_path,
                 services_heading, services_json,
                 contact_address, contact_phone, contact_email, contact_hours,
                 custom_css, meta_description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                layout_template = VALUES(layout_template),
                hero_title = VALUES(hero_title),
                hero_subtitle = VALUES(hero_subtitle),
                hero_description = VALUES(hero_description),
                hero_cta_text = VALUES(hero_cta_text),
                hero_cta_url = VALUES(hero_cta_url),
                hero_image_path = VALUES(hero_image_path),
                about_heading = VALUES(about_heading),
                about_body = VALUES(about_body),
                about_image_path = VALUES(about_image_path),
                services_heading = VALUES(services_heading),
                services_json = VALUES(services_json),
                contact_address = VALUES(contact_address),
                contact_phone = VALUES(contact_phone),
                contact_email = VALUES(contact_email),
                contact_hours = VALUES(contact_hours),
                custom_css = VALUES(custom_css),
                meta_description = VALUES(meta_description)
        ');

        $upsert_website->execute([
            $tenant_id,
            $layout,
            $hero_title,
            $hero_subtitle,
            $hero_description,
            $hero_cta_text,
            $hero_cta_url,
            $hero_background_path,
            $about_heading,
            $about_body,
            '',
            $services_heading,
            $services_json,
            $contact_address,
            $contact_phone,
            $contact_email,
            $contact_hours,
            '',
            ''
        ]);

        $website_config = [
            'website_show_about' => $show_about,
            'website_show_services' => $show_services,
            'website_show_contact' => $show_contact,
            'website_show_download' => $show_download,
            'website_download_url' => $download_url,
            'website_hero_background' => $hero_background_path
        ];

        $boolean_setting_keys = [
            'website_show_about',
            'website_show_services',
            'website_show_contact',
            'website_show_download'
        ];

        $setting_upsert = $pdo->prepare('
            INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_category = VALUES(setting_category),
                data_type = VALUES(data_type),
                updated_at = CURRENT_TIMESTAMP
        ');

        foreach ($website_config as $key => $value) {
            $data_type = in_array($key, $boolean_setting_keys, true) ? 'Boolean' : 'String';
            $setting_upsert->execute([$tenant_id, $key, $value, 'Website', $data_type]);
        }

        $pdo->prepare('INSERT INTO tenant_feature_toggles (tenant_id, toggle_key, is_enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_enabled = 1')
            ->execute([$tenant_id, 'public_website_enabled']);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'WEBSITE_SETUP', 'tenant', 'Public website configured during onboarding', ?)");
        $log->execute([$user_id, $tenant_id]);

        $pdo->prepare('UPDATE tenants SET setup_current_step = 4 WHERE tenant_id = ? AND setup_current_step = 3')->execute([$tenant_id]);

        header('Location: setup_branding.php');
        exit;
    }
}

$e = function ($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Website - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '<?php echo htmlspecialchars($t_font); ?>', sans-serif; background: <?php echo htmlspecialchars($t_bg); ?>; min-height: 100vh; padding: 20px; }
        .wizard-card { background: <?php echo htmlspecialchars($t_card); ?>; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.05); width: 95%; max-width: 1600px; margin: 0 auto; overflow: hidden; }
        .wizard-header { background: linear-gradient(135deg, <?php echo $e($accent); ?>, #0ea5e9); padding: 28px 32px; color: #ffffff; }
        .wizard-header h1 { font-size: 1.45rem; font-weight: 700; margin-bottom: 6px; }
        .wizard-header p { opacity: 0.9; font-size: 0.9rem; }
        .step-indicator { display: flex; gap: 8px; margin-top: 14px; }
        .step { width: 44px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.35); }
        .step.active { background: #ffffff; }
        .wizard-body { padding: 30px 32px 34px; }
        .error { color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; padding: 10px 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .section { border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
        .section h3 { font-size: 1rem; margin-bottom: 4px; color: #0f172a; }
        .section p { color: #64748b; font-size: 0.85rem; margin-bottom: 14px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 0.84rem; font-weight: 500; color: #475569; }
        .form-control, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.92rem; font-family: inherit; color: #0f172a; }
        textarea { min-height: 90px; resize: vertical; }
        .template-option { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; display: flex; gap: 8px; align-items: center; font-size: 0.88rem; cursor: pointer; }
        .template-option input { margin-top: 1px; }
        .template-option:has(input:checked) { background: #dbeafe; border-color: #0ea5e9; }
        .template-option.unavailable { cursor: not-allowed; opacity: 0.6; border-style: dashed; }
        .template-option.unavailable span { color: #64748b; }
        .check-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .check-item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 0.88rem; color: #0f172a; }
        .check-item input { margin-right: 6px; }
        .actions { margin-top: 18px; }
        .btn-primary { width: 100%; border: 0; border-radius: 10px; padding: 12px 14px; font-size: 0.95rem; font-weight: 600; color: #ffffff; background: <?php echo $e($accent); ?>; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { filter: brightness(0.93); }
        .setup-layout { display: grid; grid-template-columns: 450px 1fr; gap: 32px; align-items: start; }
        .live-preview-panel { border: 1px solid #dbeafe; border-radius: 12px; overflow: hidden; background: #f8fafc; position: sticky; top: 18px; }
        .preview-panel-header { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; background: #ffffff; }
        .preview-panel-header h3 { font-size: 0.95rem; color: #0f172a; margin-bottom: 4px; }
        .preview-panel-header p { font-size: 0.8rem; color: #64748b; margin-bottom: 0; }
        .preview-canvas { padding: 14px; background: linear-gradient(180deg, #f1f5f9, #eef2ff); }
        
        /* ========== Media Queries ========== */
        @media (max-width: 1020px) {
            .setup-layout { grid-template-columns: 1fr; }
            .live-preview-panel { position: static; }
        }
        @media (max-width: 760px) {
            .grid-2, .grid-3, .check-row { grid-template-columns: 1fr; }
            .wizard-header { padding: 22px 20px; }
            .wizard-body { padding: 20px; }
            .template-template1 .preview-hero { grid-template-columns: 1fr; }
            .template-template1 .preview-hero-image { display: none; }
            .template-template1 .preview-steps { grid-template-columns: 1fr; }
            .template-template1 .preview-sections { grid-template-columns: 1fr; }
            .template-template2 .preview-sections { grid-template-columns: 1fr; }
            .template-template2 .preview-stats { grid-template-columns: 1fr; }
            .template-template3 .preview-hero { grid-template-columns: 1fr; }
            .template-template3 .preview-hero-image { display: none; }
            .template-template3 .preview-sections { grid-template-columns: 1fr; }
            .template-template3 .preview-section-card:first-child { grid-column: span 1; }
            .template-template3 .preview-journey { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wizard-card">
        <div class="wizard-header">
            <h1>Setup Public Website</h1>
            <p>Choose a design template and set the information your visitors should see for <?php echo $e($tenant_name); ?>.</p>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step active"></div>
                <div class="step"></div>
                <div class="step"></div>
            </div>
        </div>

        <div class="wizard-body">
            <?php if ($error): ?>
                <div class="error"><?php echo $e($error); ?></div>
            <?php endif; ?>

            <div class="setup-layout">
                <form method="POST" id="websiteSetupForm" enctype="multipart/form-data">
                    <input type="hidden" name="layout_template" value="template1">

                    <div class="section">
                        <h3>Hero Information</h3>
                        <p>This appears prominently when users first visit your website.</p>
                        <div class="form-group">
                            <label>Hero Title *</label>
                            <input class="form-control" type="text" name="hero_title" value="<?php echo $e($form['hero_title']); ?>" required oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Hero Subtitle</label>
                            <input class="form-control" type="text" name="hero_subtitle" value="<?php echo $e($form['hero_subtitle']); ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Hero Description</label>
                            <textarea name="hero_description" oninput="updatePreview()"><?php echo $e($form['hero_description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Hero Background Image</label>
                            <input class="form-control" type="file" name="hero_background" accept=".jpg,.jpeg,.png,.webp">
                            <p style="margin-top: 6px; font-size: 0.78rem; color: #64748b;">Max 3MB. Recommended: 1920x1080px</p>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Public Information</h3>
                        <p>Contact details and company information for visitors.</p>
                        <div class="form-group">
                            <label>About Description</label>
                            <textarea name="about_body" oninput="updatePreview()"><?php echo $e($form['about_body']); ?></textarea>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input class="form-control" type="text" name="contact_phone" value="<?php echo $e($form['contact_phone']); ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>Contact Email</label>
                                <input class="form-control" type="email" name="contact_email" value="<?php echo $e($form['contact_email']); ?>" oninput="updatePreview()">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Company Address</label>
                            <textarea name="contact_address" oninput="updatePreview()"><?php echo $e($form['contact_address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Business Hours</label>
                            <input class="form-control" type="text" name="contact_hours" value="<?php echo $e($form['contact_hours']); ?>" placeholder="Mon-Fri 8:00 AM - 5:00 PM" oninput="updatePreview()">
                        </div>
                    </div>

                    <div class="section">
                        <h3>Sections & Downloads</h3>
                        <p>Control which sections appear and app download options.</p>
                        <div class="check-row">
                            <label class="check-item"><input type="checkbox" name="website_show_about" value="1" <?php echo $form['website_show_about'] === '1' ? 'checked' : ''; ?> oninput="updatePreview()"> Show About</label>
                            <label class="check-item"><input type="checkbox" name="website_show_services" value="1" <?php echo $form['website_show_services'] === '1' ? 'checked' : ''; ?> oninput="updatePreview()"> Show Services</label>
                            <label class="check-item"><input type="checkbox" name="website_show_contact" value="1" <?php echo $form['website_show_contact'] === '1' ? 'checked' : ''; ?> oninput="updatePreview()"> Show Contact</label>
                            <label class="check-item"><input type="checkbox" name="website_show_download" value="1" <?php echo $form['website_show_download'] === '1' ? 'checked' : ''; ?> oninput="updatePreview()"> Show Download</label>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn-primary" type="submit">Complete Setup</button>
                    </div>
                </form>

                <aside class="live-preview-panel" aria-label="Live Website Preview">
                    <div class="preview-panel-header">
                        <h3>Live Preview</h3>
                        <p>See updates as you make changes to your content.</p>
                    </div>
                    <div style="padding:0; height:600px; background:#f1f5f9;">
                        <iframe id="previewContainer" style="width:100%; height:100%; border:none;"></iframe>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <script>
        var accentColor = '<?php echo $e($accent); ?>';
        var tenantName = '<?php echo $e($tenant_name); ?>';

        function updatePreview() {
            var form = document.getElementById('websiteSetupForm');
            if (!form) return;
            var templateEl = form.querySelector('input[name="layout_template"]:checked') || form.querySelector('input[name="layout_template"]');
            var template = templateEl ? templateEl.value : 'template1';
            var heroTitle = form.querySelector('input[name="hero_title"]')?.value ?? ('Welcome to ' + tenantName);
            var heroSubtitle = form.querySelector('input[name="hero_subtitle"]')?.value ?? 'Your trusted microfinance partner';
            var heroDesc = form.querySelector('textarea[name="hero_description"]')?.value ?? '';
            var aboutBody = form.querySelector('textarea[name="about_body"]')?.value ?? '';
            var contactPhone = form.querySelector('input[name="contact_phone"]')?.value ?? '';
            var contactEmail = form.querySelector('input[name="contact_email"]')?.value ?? '';
            var contactAddr = form.querySelector('textarea[name="contact_address"]')?.value ?? '';
            var contactHours = form.querySelector('input[name="contact_hours"]')?.value ?? '';

            var showAbout = form.querySelector('input[name="website_show_about"]')?.checked ?? false;
            var showServices = form.querySelector('input[name="website_show_services"]')?.checked ?? false;
            var showContact = form.querySelector('input[name="website_show_contact"]')?.checked ?? false;
            var showDownload = form.querySelector('input[name="website_show_download"]')?.checked ?? false;

            var downloadTitle = 'Download Our App';
            var downloadDesc = 'Get the app for faster loan tracking and updates.';
            var downloadBtn = 'Download App';

            var html = '';
            if (template === 'template1') {
                html = generateTemplate1(heroTitle, heroSubtitle, heroDesc, aboutBody, contactPhone, contactEmail, contactAddr, contactHours, downloadTitle, downloadDesc, downloadBtn, showAbout, showServices, showContact, showDownload);
            }

            var iframe = document.getElementById('previewContainer');
            if (iframe && iframe.contentDocument) {
                var doc = iframe.contentDocument;
                
                if (!doc.getElementById('preview-body-content')) {
                    doc.open();
                    doc.write(`
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Public+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0&display=swap" rel="stylesheet"/>
<style>
    :root {
        --brand: ${accentColor};
        --brand-text: #ffffff;
        --bs-body-font-family: 'Public Sans', sans-serif;
        --bs-body-bg: #f8fafc;
        --bs-body-color: #1e293b;
    }
    h1,h2,h3,h4,h5,.headline { font-family: 'Manrope', sans-serif; }
    body { font-family: 'Public Sans', sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 0; font-size: 14px; }
    .material-symbols-rounded { vertical-align: middle; font-variation-settings: 'FILL' 1; }
    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .text-brand { color: var(--brand) !important; }
    .bg-brand { background-color: var(--brand) !important; }
    .btn-brand { background: var(--brand); color: var(--brand-text); border:none; }
    .btn-brand:hover { filter: brightness(0.9); color: var(--brand-text); }
    .btn-brand-outline { border: 2px solid var(--brand); color: var(--brand); background: transparent; }
    .btn-brand-outline:hover { background: var(--brand); color: var(--brand-text); }
    .site-nav { background: rgba(255,255,255,0.85); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(0,0,0,0.06); }
    .nav-link { font-weight: 600; font-size: .78rem; color: #475569; }
    .nav-link:hover { color: var(--brand) !important; }
    .hero-wrap { min-height: 300px; display: flex; align-items: center; padding: 40px 0 30px; background: linear-gradient(135deg, #f8fafc, #eef2ff, #f0fdf4); position: relative; overflow: hidden; }
    .hero-title { font-size: 1.6rem; font-weight: 800; line-height: 1.15; letter-spacing: -0.02em; color: #0f172a; }
    .hero-title span { color: var(--brand); }
    .hero-subtitle { font-size: .78rem; color: #64748b; font-weight: 500; }
    .hero-badge { display: inline-flex; align-items: center; gap: 4px; background: rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.06); border-radius: 999px; padding: 4px 12px; font-size: .65rem; font-weight: 700; color: var(--brand); }
    .hero-illus { background: linear-gradient(145deg, rgba(0,0,0,0.03), #f1f5f9); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 16px 12px; border-radius: 16px; position: relative; overflow: hidden; }
    .hi-card { background: #fff; border-radius: 10px; padding: 8px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 8px; width: 100%; }
    .hi-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .hi-icon .material-symbols-rounded { font-size: 14px; color: #fff; }
    .hi-primary { background: var(--brand); }
    .hi-green { background: #10b981; }
    .hi-amber { background: #f59e0b; }
    .hi-label { font-size: .52rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .hi-value { font-size: .78rem; font-weight: 800; color: #0f172a; }
    .service-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; transition: box-shadow 0.2s; background: #fff; }
    .service-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
    .service-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
    .about-wrap { background: var(--brand); color: #fff; border-radius: 16px; padding: 24px; position: relative; overflow: hidden; }
    .about-wrap::before { content:''; position:absolute; width:200px; height:200px; border-radius:50%; background:rgba(255,255,255,0.06); top:-60px; right:-60px; }
    .download-section { background: linear-gradient(135deg, var(--brand), #0ea5e9); border-radius: 16px; padding: 24px; color: #fff; text-align: center; position: relative; overflow: hidden; }
    .download-section::before { content:''; position:absolute; width:180px; height:180px; border-radius:50%; background:rgba(255,255,255,0.08); bottom:-50px; left:-50px; }
    .footer-section { background: #fff; border-top: 1px solid #e2e8f0; padding: 20px 0; }
</style>
</head>
<body>
<div id="preview-body-content"></div>
</body>
</html>
                    `);
                    doc.close();
                }

                var container = doc.getElementById('preview-body-content');
                if (container) {
                    container.innerHTML = html;
                }
            }
        }

        function escH(str) { var d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
        function colorFirstWord(title) {
            var safe = escH(title);
            var parts = safe.split(' ');
            if (parts.length >= 2) return '<span>' + parts[0] + '</span> ' + parts.slice(1).join(' ');
            return safe;
        }

        function generateTemplate1(title, subtitle, desc, about, phone, email, addr, hours, dlTitle, dlDesc, dlBtn, showAbout, showServices, showContact, showDownload) {
            var html = `
            <!-- Navbar -->
            <nav class="site-nav sticky-top py-2">
              <div class="container">
                <div class="d-flex justify-content-between align-items-center" style="height:44px;">
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold headline text-brand" style="font-size:.9rem;">${escH(tenantName)}</span>
                  </div>
                  <div class="d-flex align-items-center gap-1" style="font-size:.72rem;">
                    ${showServices ? '<a class="nav-link px-2">Services</a>' : ''}
                    ${showAbout ? '<a class="nav-link px-2">About Us</a>' : ''}
                    ${showContact ? '<a class="nav-link px-2">Contact</a>' : ''}
                    ${showDownload ? '<a class="nav-link px-2">Download</a>' : ''}
                  </div>
                  <div class="d-flex gap-2 align-items-center">
                    <a class="btn btn-brand-outline rounded-pill px-3 fw-bold" style="font-size:.68rem;">Log In</a>
                    <a class="btn btn-brand rounded-pill px-3 fw-bold shadow-sm" style="font-size:.68rem;">Get Started</a>
                  </div>
                </div>
              </div>
            </nav>

            <!-- Hero -->
            <section class="hero-wrap">
              <div class="container position-relative" style="z-index:2;">
                <div class="row align-items-center g-4">
                  <div class="col-7">
                    ${subtitle ? '<div class="hero-badge mb-2"><span class="material-symbols-rounded" style="font-size:.7rem;">verified</span>' + escH(subtitle) + '</div>' : ''}
                    <h1 class="hero-title mb-2">${colorFirstWord(title)}</h1>
                    ${desc ? '<p class="hero-subtitle mb-3">' + escH(desc) + '</p>' : ''}
                    <div class="d-flex gap-2">
                      <a class="btn btn-brand rounded-pill px-3 fw-bold shadow-sm" style="font-size:.72rem;">Get Started</a>
                      <a class="btn btn-brand-outline rounded-pill px-3 fw-bold" style="font-size:.72rem;">Learn More</a>
                    </div>
                  </div>
                  <div class="col-5">
                    <div class="hero-illus">
                      <div class="hi-card">
                        <div class="hi-icon hi-primary"><span class="material-symbols-rounded">check_circle</span></div>
                        <div><div class="hi-label">Approved Loans</div><div class="hi-value">1,240</div></div>
                      </div>
                      <div class="hi-card">
                        <div class="hi-icon hi-green"><span class="material-symbols-rounded">group</span></div>
                        <div><div class="hi-label">Active Members</div><div class="hi-value">856</div></div>
                      </div>
                      <div class="hi-card">
                        <div class="hi-icon hi-amber"><span class="material-symbols-rounded">trending_up</span></div>
                        <div><div class="hi-label">Growth Rate</div><div class="hi-value">+24%</div></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- Services -->
            ${showServices ? `
            <section class="py-4">
              <div class="container">
                <div class="text-center mb-3">
                  <span class="text-brand fw-bold" style="font-size:.6rem; letter-spacing:.1em; text-transform:uppercase;">What We Offer</span>
                  <h2 class="headline fw-bold mt-1" style="font-size:1.1rem;">Our Services</h2>
                </div>
                <div class="row g-3">
                  <div class="col-4">
                    <div class="service-card">
                      <div class="service-icon bg-brand bg-opacity-10"><span class="material-symbols-rounded text-brand" style="font-size:18px;">account_balance_wallet</span></div>
                      <h5 class="fw-bold text-brand" style="font-size:.72rem;">Personal Loans</h5>
                      <p style="font-size:.58rem; color:#64748b;">Flexible terms for your personal needs and goals.</p>
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="service-card">
                      <div class="service-icon" style="background:rgba(16,185,129,0.1);"><span class="material-symbols-rounded" style="font-size:18px; color:#10b981;">store</span></div>
                      <h5 class="fw-bold text-brand" style="font-size:.72rem;">Business Loans</h5>
                      <p style="font-size:.58rem; color:#64748b;">Grow your enterprise with competitive rates.</p>
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="service-card">
                      <div class="service-icon" style="background:rgba(245,158,11,0.1);"><span class="material-symbols-rounded" style="font-size:18px; color:#f59e0b;">emergency</span></div>
                      <h5 class="fw-bold text-brand" style="font-size:.72rem;">Emergency Loans</h5>
                      <p style="font-size:.58rem; color:#64748b;">Quick access when you need it most.</p>
                    </div>
                  </div>
                </div>
              </div>
            </section>
            ` : ''}

            <!-- About -->
            ${showAbout ? `
            <section class="py-4">
              <div class="container">
                <div class="about-wrap">
                  <div class="row align-items-center g-3">
                    <div class="col-7">
                      <span style="font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; opacity:.7;">About Us</span>
                      <h3 class="headline fw-bold mt-1 mb-2" style="font-size:1rem;">Who We Are</h3>
                      <p style="font-size:.68rem; opacity:.85; line-height:1.6;">${about ? escH(about) : 'We are committed to empowering communities through accessible financial services and sustainable growth.'}</p>
                    </div>
                    <div class="col-5 text-center">
                      <div style="display:flex; justify-content:center; margin-bottom:8px;">
                        <div style="width:32px; height:32px; border-radius:50%; background:#10b981; border:2px solid #fff; display:flex; align-items:center; justify-content:center; font-size:.8rem;">👤</div>
                        <div style="width:32px; height:32px; border-radius:50%; background:#f59e0b; border:2px solid #fff; margin-left:-8px; display:flex; align-items:center; justify-content:center; font-size:.8rem;">👤</div>
                        <div style="width:32px; height:32px; border-radius:50%; background:#8b5cf6; border:2px solid #fff; margin-left:-8px; display:flex; align-items:center; justify-content:center; font-size:.8rem;">👤</div>
                      </div>
                      <div style="background:rgba(255,255,255,0.15); border-radius:12px; padding:10px; backdrop-filter:blur(6px);">
                        <div style="font-size:1.3rem; font-weight:800;">500+</div>
                        <div style="font-size:.52rem; text-transform:uppercase; letter-spacing:.08em; opacity:.7;">Happy Members</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>
            ` : ''}

            <!-- Download -->
            ${showDownload ? `
            <section class="py-4">
              <div class="container">
                <div class="download-section">
                  <span class="material-symbols-rounded mb-2" style="font-size:2rem; display:block;">phone_android</span>
                  <h3 class="headline fw-bold mb-1" style="font-size:1rem;">${escH(dlTitle)}</h3>
                  <p style="font-size:.68rem; opacity:.85; margin-bottom:12px;">${escH(dlDesc)}</p>
                  <a class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" style="font-size:.72rem; color:var(--brand);">
                    <span class="material-symbols-rounded me-1" style="font-size:.85rem;">download</span>${escH(dlBtn)}
                  </a>
                </div>
              </div>
            </section>
            ` : ''}

            <!-- Footer -->
            ${showContact ? `
            <footer class="footer-section">
              <div class="container">
                <div class="row g-3">
                  <div class="col-6">
                    <div class="fw-bold text-brand mb-1" style="font-size:.82rem;">${escH(tenantName)}</div>
                    <p style="font-size:.58rem; color:#64748b;">Your trusted partner in financial growth and community empowerment.</p>
                  </div>
                  <div class="col-6">
                    <div class="fw-bold text-brand mb-1" style="font-size:.72rem;">Contact</div>
                    <div style="font-size:.58rem; color:#64748b; line-height:1.8;">
                      ${phone ? '<div><span class="material-symbols-rounded me-1" style="font-size:.7rem;">call</span>' + escH(phone) + '</div>' : ''}
                      ${email ? '<div><span class="material-symbols-rounded me-1" style="font-size:.7rem;">mail</span>' + escH(email) + '</div>' : ''}
                      ${addr ? '<div><span class="material-symbols-rounded me-1" style="font-size:.7rem;">location_on</span>' + escH(addr) + '</div>' : ''}
                      ${hours ? '<div><span class="material-symbols-rounded me-1" style="font-size:.7rem;">schedule</span>' + escH(hours) + '</div>' : ''}
                    </div>
                  </div>
                </div>
                <hr style="margin:12px 0 8px; border-color:#e2e8f0;">
                <p class="text-center" style="font-size:.52rem; color:#94a3b8;">© ${new Date().getFullYear()} ${escH(tenantName)}. All rights reserved.</p>
              </div>
            </footer>
            `;
            return html;
        }

        // Initialize preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(updatePreview, 100);
        });
    </script>
</body>
</html>
