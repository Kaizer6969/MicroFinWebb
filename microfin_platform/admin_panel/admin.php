<?php
require_once '../backend/session_auth.php';
mf_start_backend_session();
require_once '../backend/db_connect.php';
require_once '../backend/credit_policy.php';
require_once '../backend/mobile_app_build.php';
mf_require_tenant_session($pdo, [
    'response' => 'die',
    'status' => 403,
    'message' => "<h1>403 Forbidden - Access Denied</h1><p>No valid tenant session could be identified. Please log in using your company's designated login link.</p>",
]);
require_once '../backend/billing_access.php';
require_once '../backend/login_activity.php';
require_once '../backend/lazy_billing_resolver.php';
require_once __DIR__ . '/receipt_helpers.php';

// Resolve any pending tenant subscriptions automagically!
resolve_tenant_billing($pdo);

function admin_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_column}'");
    $stmt->execute();
    $cache[$key] = (bool)$stmt->fetch();
    return $cache[$key];
}

function admin_get_system_setting(PDO $pdo, string $tenantId, string $settingKey, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
    $stmt->execute([$tenantId, $settingKey]);
    $value = $stmt->fetchColumn();
    return $value !== false ? trim((string)$value) : $default;
}

function admin_get_next_billing_date(PDO $pdo, string $tenantId, bool $tenantsHasNextBillingDate): string
{
    if ($tenantsHasNextBillingDate) {
        try {
            $stmt = $pdo->prepare('SELECT next_billing_date FROM tenants WHERE tenant_id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $value = trim((string)$stmt->fetchColumn());
            if ($value !== '') {
                return $value;
            }
        } catch (Throwable $ignore) {}
    }

    return admin_get_system_setting($pdo, $tenantId, 'next_billing_date', '');
}

function admin_role_supports_billing_toggle(PDO $pdo, string $tenantId, int $roleId): bool
{
    if ($roleId <= 0 || $tenantId === '') {
        return false;
    }

    $stmt = $pdo->prepare('SELECT role_name FROM user_roles WHERE tenant_id = ? AND role_id = ? LIMIT 1');
    $stmt->execute([$tenantId, $roleId]);
    $roleName = trim((string)$stmt->fetchColumn());

    return strcasecmp($roleName, 'Admin') === 0;
}

function admin_generate_temporary_password(int $length = 12): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $symbols = '!@#$%^&*';
    $all = $upper . $lower . $digits . $symbols;

    $passwordChars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $symbols[random_int(0, strlen($symbols) - 1)],
    ];

    while (count($passwordChars) < $length) {
        $passwordChars[] = $all[random_int(0, strlen($all) - 1)];
    }

    for ($i = count($passwordChars) - 1; $i > 0; $i--) {
        $swapIndex = random_int(0, $i);
        $temp = $passwordChars[$i];
        $passwordChars[$i] = $passwordChars[$swapIndex];
        $passwordChars[$swapIndex] = $temp;
    }

    return implode('', $passwordChars);
}

function admin_is_railway_runtime(): bool
{
    $keys = [
        'RAILWAY_ENVIRONMENT',
        'RAILWAY_PROJECT_ID',
        'RAILWAY_SERVICE_ID',
        'RAILWAY_PUBLIC_DOMAIN',
        'RAILWAY_STATIC_URL',
    ];
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && trim((string) $value) !== '') {
            return true;
        }
    }
    return false;
}

function admin_normalize_app_base_url(string $baseUrl): string
{
    $baseUrl = rtrim(trim($baseUrl), '/');
    if ($baseUrl === '') {
        return '';
    }

    $path = trim((string) (parse_url($baseUrl, PHP_URL_PATH) ?? ''));
    if ($path === '' || $path === '/') {
        return $baseUrl . '/microfin_platform';
    }

    if (!preg_match('~(?:^|/)microfin_platform/?$~i', $path)) {
        return $baseUrl . '/microfin_platform';
    }

    return $baseUrl;
}

function admin_build_tenant_login_url(string $tenantSlug): string
{
    $safeSlug = urlencode(trim($tenantSlug));
    $explicitBase = trim((string) (getenv('APP_BASE_URL') ?: getenv('PUBLIC_BASE_URL') ?: ''));

    if (admin_is_railway_runtime()) {
        $railwayBase = trim((string) (getenv('RAILWAY_STATIC_URL') ?: getenv('RAILWAY_PUBLIC_DOMAIN') ?: ''));
        if ($railwayBase !== '') {
            if (!preg_match('~^https?://~i', $railwayBase)) {
                $railwayBase = 'https://' . $railwayBase;
            }
            return admin_normalize_app_base_url($railwayBase) . '/tenant_login/login.php?s=' . $safeSlug;
        }

        return 'https://microfinwebb-production.up.railway.app/microfin_platform/tenant_login/login.php?s=' . $safeSlug;
    }

    if ($explicitBase !== '') {
        return admin_normalize_app_base_url($explicitBase) . '/tenant_login/login.php?s=' . $safeSlug;
    }

    $requestHost = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $defaultScript = '/admin-draft-withmobile/admin-draft/microfin_platform/admin_panel/admin.php';
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['PHP_SELF'] ?? $defaultScript))), '/\\');
    return 'http://' . $requestHost . $basePath . '/tenant_login/login.php?s=' . $safeSlug;
}

function admin_safe_fetch_value(PDO $pdo, string $sql, array $params = [], $default = 0)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (Throwable $ignore) {
        return $default;
    }
}

function admin_safe_fetch_row(PDO $pdo, string $sql, array $params = [], array $default = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : $default;
    } catch (Throwable $ignore) {
        return $default;
    }
}

function admin_store_brand_logo(array $file, string $tenantId): array
{
    if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => '', 'error' => ''];
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => 'Logo upload failed. Please try again.'];
    }

    $original_name = (string)($file['name'] ?? '');
    $tmp_name = (string)($file['tmp_name'] ?? '');
    $size_bytes = (int)($file['size'] ?? 0);
    $extension = strtolower((string)pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

    if (!in_array($extension, $allowed_extensions, true)) {
        return ['path' => '', 'error' => 'Invalid logo format. Allowed formats: PNG, JPG, JPEG, WEBP, SVG.'];
    }

    if ($size_bytes <= 0 || $size_bytes > (3 * 1024 * 1024)) {
        return ['path' => '', 'error' => 'Logo size must be between 1 byte and 3MB.'];
    }

    if (!is_uploaded_file($tmp_name)) {
        return ['path' => '', 'error' => 'Uploaded logo file is invalid.'];
    }

    $upload_dir = __DIR__ . '/../uploads/tenant_logos';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
        return ['path' => '', 'error' => 'Unable to create logo upload directory.'];
    }

    $safe_tenant_id = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenantId);
    $file_name = $safe_tenant_id . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extension;
    $destination = rtrim($upload_dir, '/\\') . DIRECTORY_SEPARATOR . $file_name;

    if (!move_uploaded_file($tmp_name, $destination)) {
        return ['path' => '', 'error' => 'Failed to save uploaded logo.'];
    }

    $app_base_path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($app_base_path === '') {
        $app_base_path = '/';
    }

    return ['path' => $app_base_path . '/uploads/tenant_logos/' . $file_name, 'error' => ''];
}

function admin_sync_stats_section_branding(PDO $pdo, string $tenantId, string $primaryColor): void
{
    if ($tenantId === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT layout_template, website_data FROM tenant_website_content WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $websiteData = [];
        if (!empty($existing['website_data'])) {
            $decoded = json_decode((string)$existing['website_data'], true);
            if (is_array($decoded)) {
                $websiteData = $decoded;
            }
        }

        if (!isset($websiteData['section_styles']) || !is_array($websiteData['section_styles'])) {
            $websiteData['section_styles'] = [];
        }

        $statsStyle = $websiteData['section_styles']['sec_stats'] ?? [];
        if (!is_array($statsStyle)) {
            $statsStyle = [];
        }

        $statsStyle['bg'] = $primaryColor;
        $statsStyle['gradient'] = false;
        unset($statsStyle['grad_color2'], $statsStyle['grad_dir']);
        $websiteData['section_styles']['sec_stats'] = $statsStyle;

        $layoutTemplate = trim((string)($existing['layout_template'] ?? ''));
        if ($layoutTemplate === '') {
            $layoutTemplate = 'template1.php';
        }

        $upsert = $pdo->prepare('
            INSERT INTO tenant_website_content (tenant_id, layout_template, website_data)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                layout_template = VALUES(layout_template),
                website_data = VALUES(website_data),
                updated_at = CURRENT_TIMESTAMP
        ');
        $upsert->execute([
            $tenantId,
            $layoutTemplate,
            json_encode($websiteData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
    } catch (Throwable $ignore) {
    }
}

$tenant_id = $_SESSION['tenant_id'];
$tenant_name = $_SESSION['tenant_name'] ?? 'Company Admin';
$role_name = $_SESSION['role_name'] ?? ($_SESSION['role'] ?? 'User');
$ui_theme = (($_SESSION['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';
$tenants_has_next_billing_date = admin_column_exists($pdo, 'tenants', 'next_billing_date');
$tenants_has_cancel_at_period_end = admin_column_exists($pdo, 'tenants', 'cancel_at_period_end');
$tenants_has_scheduled_plan_tier = admin_column_exists($pdo, 'tenants', 'scheduled_plan_tier');
$tenants_has_scheduled_plan_effective_date = admin_column_exists($pdo, 'tenants', 'scheduled_plan_effective_date');
$users_has_can_manage_billing = admin_column_exists($pdo, 'users', 'can_manage_billing');

// Check if user still needs to change their password (e.g. closed browser during force change)
$can_manage_billing = false;
$user_id_check = $_SESSION['user_id'] ?? 0;
if ($user_id_check > 0) {
    $fpc_stmt = $pdo->prepare('SELECT force_password_change, ui_theme, role_id FROM users WHERE user_id = ?');
    $fpc_stmt->execute([$user_id_check]);
    $fpc_row = $fpc_stmt->fetch(PDO::FETCH_ASSOC);
    if ($fpc_row && isset($fpc_row['ui_theme'])) {
        $ui_theme = ($fpc_row['ui_theme'] === 'dark') ? 'dark' : 'light';
        $_SESSION['ui_theme'] = $ui_theme;
    }
    $roleId = (int)($fpc_row['role_id'] ?? 0);
    if (admin_role_supports_billing_toggle($pdo, (string)$tenant_id, $roleId)) {
        if ($users_has_can_manage_billing) {
            $billing_stmt = $pdo->prepare('SELECT can_manage_billing FROM users WHERE user_id = ? LIMIT 1');
            $billing_stmt->execute([$user_id_check]);
            $can_manage_billing = (bool)$billing_stmt->fetchColumn();
        } else {
            $can_manage_billing = mf_user_can_manage_billing($pdo, (string)$tenant_id, $user_id_check);
        }
    }
    $_SESSION['can_manage_billing'] = $can_manage_billing;
    if ($fpc_row && (bool)$fpc_row['force_password_change']) {
        header('Location: ../tenant_login/force_change_password.php');
        exit;
    }
}

// Check if setup is completed. Billing is the only required onboarding gate before dashboard access.
$tenant_stmt = $pdo->prepare('SELECT setup_completed, setup_current_step FROM tenants WHERE tenant_id = ?');
$tenant_stmt->execute([$tenant_id]);
$tenant_data = $tenant_stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant_data && !(bool)$tenant_data['setup_completed']) {
    $setup_step = (int)($tenant_data['setup_current_step'] ?? 0);
    if ($setup_step < 5) {
        $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant_id]);
    }
    header('Location: ../tenant_login/setup_billing.php');
    exit;
}

$default_settings = [
    'company_name' => $tenant_name,
    'primary_color' => '#4f46e5',
    'secondary_color' => '#991b1b',
    'text_main' => '#0f172a',
    'text_muted' => '#64748b',
    'bg_body' => '#f8fafc',
    'bg_card' => '#ffffff',
    'border_color' => '#e2e8f0',
    'card_border_width' => '1',
    'card_shadow' => 'sm',
    'font_family' => 'Inter',
    'logo_path' => '',
    'support_email' => '',
    'support_phone' => ''
];

$default_toggles = [
    'booking_system' => 0,
    'user_registration' => 0,
    'maintenance_mode' => 0,
    'email_notifications' => 0,
    'public_website_enabled' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $settings = [
        'company_name' => trim($_POST['company_name'] ?? $default_settings['company_name']),
        'primary_color' => trim($_POST['primary_color'] ?? $default_settings['primary_color']),
        'secondary_color' => trim($_POST['secondary_color'] ?? $default_settings['secondary_color']),
        'text_main' => trim($_POST['text_main'] ?? $default_settings['text_main']),
        'text_muted' => trim($_POST['text_muted'] ?? $default_settings['text_muted']),
        'bg_body' => trim($_POST['bg_body'] ?? $default_settings['bg_body']),
        'bg_card' => trim($_POST['bg_card'] ?? $default_settings['bg_card']),
        'border_color' => trim($_POST['border_color'] ?? $default_settings['border_color']),
        'card_border_width' => trim($_POST['card_border_width'] ?? $default_settings['card_border_width']),
        'card_shadow' => trim($_POST['card_shadow'] ?? $default_settings['card_shadow']),
        'font_family' => trim($_POST['font_family'] ?? $default_settings['font_family']),
        'logo_path' => trim($_POST['existing_logo_path'] ?? ''),
        'support_email' => trim($_POST['support_email'] ?? admin_get_system_setting($pdo, (string)$tenant_id, 'support_email', '')),
        'support_phone' => trim($_POST['support_phone'] ?? admin_get_system_setting($pdo, (string)$tenant_id, 'support_phone', ''))
    ];

    $hex_pattern = '/^#[0-9a-fA-F]{6}$/';
    foreach (['primary_color', 'secondary_color', 'text_main', 'text_muted', 'bg_body', 'bg_card', 'border_color'] as $ck) {
        if (!preg_match($hex_pattern, $settings[$ck])) {
            $settings[$ck] = $default_settings[$ck];
        }
    }

    $allowed_fonts = ['Inter', 'Poppins', 'Outfit', 'Roboto', 'Open Sans', 'Lato', 'Nunito', 'Montserrat', 'DM Sans', 'Plus Jakarta Sans'];
    if (!in_array($settings['font_family'], $allowed_fonts, true)) {
        $settings['font_family'] = 'Inter';
    }

    if ($settings['company_name'] === '') {
        $settings['company_name'] = $default_settings['company_name'];
    }

    $settings['card_border_width'] = (string)max(0, min(3, round((float)$settings['card_border_width'] * 10) / 10));
    if (!in_array($settings['card_shadow'], ['none', 'sm', 'md', 'lg'], true)) {
        $settings['card_shadow'] = $default_settings['card_shadow'];
    }

    if (!empty($_FILES['logo_file']) && (int)($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $logo_upload = admin_store_brand_logo($_FILES['logo_file'], (string)$tenant_id);
        if ($logo_upload['error'] !== '') {
            $_SESSION['admin_error'] = $logo_upload['error'];
            header('Location: admin.php?tab=settings');
            exit;
        }
        if ($logo_upload['path'] !== '') {
            $settings['logo_path'] = $logo_upload['path'];
        }
    }

    $toggles = [
        'booking_system' => isset($_POST['toggle_booking_system']) ? 1 : 0,
        'user_registration' => isset($_POST['toggle_user_registration']) ? 1 : 0,
        'maintenance_mode' => isset($_POST['toggle_maintenance_mode']) ? 1 : 0,
        'email_notifications' => isset($_POST['toggle_email_notifications']) ? 1 : 0,
        'public_website_enabled' => isset($_POST['toggle_public_website_enabled']) ? 1 : 0
    ];

    $upsert_setting = $pdo->prepare('INSERT INTO system_settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($settings as $key => $value) {
        // Skip keys that belong in the tenants table
        if (in_array($key, ['company_name', 'primary_color', 'secondary_color', 'text_main', 'text_muted', 'bg_body', 'bg_card', 'border_color', 'card_border_width', 'card_shadow', 'font_family', 'logo_path'])) {
            continue;
        }
        $upsert_setting->execute([$tenant_id, $key, $value]);
    }
    
    // Update tenants table for name, and tenant_branding for colors/logo/font
    $update_tenant = $pdo->prepare('UPDATE tenants SET tenant_name = ? WHERE tenant_id = ?');
    $update_tenant->execute([
        $settings['company_name'],
        $tenant_id
    ]);

    $upsert_branding = $pdo->prepare('INSERT INTO tenant_branding (tenant_id, theme_primary_color, theme_secondary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, theme_border_color, card_border_width, card_shadow, font_family, logo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE theme_primary_color = VALUES(theme_primary_color), theme_secondary_color = VALUES(theme_secondary_color), theme_text_main = VALUES(theme_text_main), theme_text_muted = VALUES(theme_text_muted), theme_bg_body = VALUES(theme_bg_body), theme_bg_card = VALUES(theme_bg_card), theme_border_color = VALUES(theme_border_color), card_border_width = VALUES(card_border_width), card_shadow = VALUES(card_shadow), font_family = VALUES(font_family), logo_path = VALUES(logo_path)');
    $upsert_branding->execute([
        $tenant_id,
        $settings['primary_color'],
        $settings['secondary_color'],
        $settings['text_main'],
        $settings['text_muted'],
        $settings['bg_body'],
        $settings['bg_card'],
        $settings['border_color'],
        $settings['card_border_width'],
        $settings['card_shadow'],
        $settings['font_family'],
        $settings['logo_path']
    ]);

    admin_sync_stats_section_branding($pdo, (string)$tenant_id, $settings['primary_color']);

    $upsert_toggle = $pdo->prepare('INSERT INTO tenant_feature_toggles (tenant_id, toggle_key, is_enabled) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)');
    foreach ($toggles as $key => $value) {
        $upsert_toggle->execute([$tenant_id, $key, $value]);
    }

    $_SESSION['tenant_name'] = $settings['company_name'];
    $_SESSION['theme'] = $settings['primary_color'];
    $_SESSION['admin_flash'] = 'Branding saved successfully.';

    header('Location: admin.php?tab=settings');
    exit;
}

// ==========================================
// POST Handler — Update Subscription Plan
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_subscription_plan') {
    if ($can_manage_billing) {
        $new_plan = trim($_POST['new_plan'] ?? '');
        $plan_aliases = [
            'Professional' => 'Pro',
            'Elite' => 'Enterprise'
        ];
        if (isset($plan_aliases[$new_plan])) {
            $new_plan = $plan_aliases[$new_plan];
        }
        $valid_plans = [
            'Starter' => ['max_clients' => 1000, 'max_users' => 250],
            'Pro' => ['max_clients' => 5000, 'max_users' => 2000],
            'Enterprise' => ['max_clients' => 10000, 'max_users' => 5000],
            'Unlimited' => ['max_clients' => -1, 'max_users' => -1]
        ];
        
        if (array_key_exists($new_plan, $valid_plans)) {
            $change_timing = $_POST['change_timing'] ?? 'next_cycle';

            $info_stmt = $pdo->prepare('SELECT plan_tier FROM tenants WHERE tenant_id = ?');
            $info_stmt->execute([$tenant_id]);
            $tenant_info = $info_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $tenant_info['next_billing_date'] = admin_get_next_billing_date($pdo, (string)$tenant_id, $tenants_has_next_billing_date);
            
            $plan_prices = [
                'Starter' => 4999,
                'Pro' => 14999,
                'Enterprise' => 19999,
                'Unlimited' => 29999
            ];
            $mrr = $plan_prices[$new_plan] ?? 0;

            if ($change_timing === 'immediate') {
                $max_clients = (int)$valid_plans[$new_plan]['max_clients'];
                $max_users = (int)$valid_plans[$new_plan]['max_users'];

                $update_parts = ['plan_tier = ?', 'mrr = ?', 'max_clients = ?', 'max_users = ?'];
                if ($tenants_has_scheduled_plan_tier) {
                    $update_parts[] = 'scheduled_plan_tier = NULL';
                }
                if ($tenants_has_scheduled_plan_effective_date) {
                    $update_parts[] = 'scheduled_plan_effective_date = NULL';
                }

                $upd = $pdo->prepare('UPDATE tenants SET ' . implode(', ', $update_parts) . ' WHERE tenant_id = ?');
                $upd->execute([$new_plan, $mrr, $max_clients, $max_users, $tenant_id]);
                
                $log_stmt = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, description, ip_address) VALUES (?, ?, 'SUBSCRIPTION_UPDATE', ?, ?)");
                $log_stmt->execute([$tenant_id, $_SESSION['user_id'], "Subscription plan updated instantly to $new_plan", $_SERVER['REMOTE_ADDR'] ?? '']);
                
                $_SESSION['admin_flash'] = "Subscription plan successfully applied instantly to $new_plan.";
            } else {
                $next = $tenant_info['next_billing_date'] ?? date('Y-m-d', strtotime('+30 days'));
                if (empty($next) || $next < date('Y-m-d')) {
                    $next = date('Y-m-d', strtotime('+30 days'));
                }

                if ($tenants_has_scheduled_plan_tier && $tenants_has_scheduled_plan_effective_date) {
                    $upd = $pdo->prepare("UPDATE tenants SET scheduled_plan_tier = ?, scheduled_plan_effective_date = ? WHERE tenant_id = ?");
                    $upd->execute([$new_plan, $next, $tenant_id]);

                    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, description, ip_address) VALUES (?, ?, 'SUBSCRIPTION_SCHEDULED', ?, ?)");
                    $log_stmt->execute([$tenant_id, $_SESSION['user_id'], "Subscription plan scheduled to $new_plan on $next", $_SERVER['REMOTE_ADDR'] ?? '']);

                    $_SESSION['admin_flash'] = "Subscription plan update to $new_plan is scheduled for your next billing cycle ($next).";
                } else {
                    $max_clients = (int)$valid_plans[$new_plan]['max_clients'];
                    $max_users = (int)$valid_plans[$new_plan]['max_users'];
                    $upd = $pdo->prepare("UPDATE tenants SET plan_tier = ?, mrr = ?, max_clients = ?, max_users = ? WHERE tenant_id = ?");
                    $upd->execute([$new_plan, $mrr, $max_clients, $max_users, $tenant_id]);

                    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, description, ip_address) VALUES (?, ?, 'SUBSCRIPTION_UPDATE', ?, ?)");
                    $log_stmt->execute([$tenant_id, $_SESSION['user_id'], "Subscription plan updated instantly to $new_plan because scheduled plan columns are unavailable", $_SERVER['REMOTE_ADDR'] ?? '']);

                    $_SESSION['admin_flash'] = "Scheduled plan changes are unavailable on this installation, so $new_plan was applied immediately.";
                }
            }
        } else {
            $_SESSION['admin_flash'] = "Invalid plan selected.";
        }
    } else {
        $_SESSION['admin_flash'] = "You do not have permission to change the billing plan.";
    }
    header("Location: admin.php?tab=billing");
    exit;
}

// ==========================================
// POST Handler — Update Payment Method
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_payment_method') {
    if (!$can_manage_billing) {
        $_SESSION['admin_flash'] = 'You do not have permission to edit payment methods.';
        header('Location: admin.php?tab=billing');
        exit;
    }

    $method_id = (int)($_POST['method_id'] ?? 0);
    $cardholder_name = trim($_POST['cardholder_name'] ?? '');
    $exp_month = (int)($_POST['exp_month'] ?? 0);
    $exp_year = (int)($_POST['exp_year'] ?? 0);
    $set_default = isset($_POST['is_default']) ? 1 : 0;

    if ($method_id <= 0 || $cardholder_name === '' || $exp_month < 1 || $exp_month > 12 || $exp_year < (int)date('Y')) {
        $_SESSION['admin_flash'] = 'Please provide valid payment method details.';
        header('Location: admin.php?tab=billing');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $existing_stmt = $pdo->prepare('SELECT method_id FROM tenant_billing_payment_methods WHERE method_id = ? AND tenant_id = ? LIMIT 1');
        $existing_stmt->execute([$method_id, $tenant_id]);
        if (!$existing_stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Payment method not found.');
        }

        $update_stmt = $pdo->prepare('UPDATE tenant_billing_payment_methods SET cardholder_name = ?, exp_month = ?, exp_year = ? WHERE method_id = ? AND tenant_id = ?');
        $update_stmt->execute([$cardholder_name, $exp_month, $exp_year, $method_id, $tenant_id]);

        if ($set_default === 1) {
            $pdo->prepare('UPDATE tenant_billing_payment_methods SET is_default = 0 WHERE tenant_id = ?')->execute([$tenant_id]);
            $pdo->prepare('UPDATE tenant_billing_payment_methods SET is_default = 1 WHERE method_id = ? AND tenant_id = ?')->execute([$method_id, $tenant_id]);
        } else {
            $default_count_stmt = $pdo->prepare('SELECT COUNT(*) FROM tenant_billing_payment_methods WHERE tenant_id = ? AND is_default = 1');
            $default_count_stmt->execute([$tenant_id]);
            if ((int)$default_count_stmt->fetchColumn() === 0) {
                $pdo->prepare('UPDATE tenant_billing_payment_methods SET is_default = 1 WHERE method_id = ? AND tenant_id = ?')->execute([$method_id, $tenant_id]);
            }
        }

        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, description, ip_address) VALUES (?, ?, 'PAYMENT_METHOD_UPDATED', ?, ?)");
        $log_stmt->execute([$tenant_id, $_SESSION['user_id'] ?? null, 'Payment method details were updated.', $_SERVER['REMOTE_ADDR'] ?? '']);

        $pdo->commit();
        $_SESSION['admin_flash'] = 'Payment method updated successfully.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['admin_flash'] = $e->getMessage();
    }

    header('Location: admin.php?tab=billing');
    exit;
}

// ==========================================
// POST Handler — Delete Payment Method
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_payment_method') {
    if (!$can_manage_billing) {
        $_SESSION['admin_flash'] = 'You do not have permission to edit payment methods.';
        header('Location: admin.php?tab=billing');
        exit;
    }

    $method_id = (int)($_POST['method_id'] ?? 0);
    if ($method_id <= 0) {
        $_SESSION['admin_flash'] = 'Invalid payment method selected.';
        header('Location: admin.php?tab=billing');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $count_stmt = $pdo->prepare('SELECT COUNT(*) FROM tenant_billing_payment_methods WHERE tenant_id = ?');
        $count_stmt->execute([$tenant_id]);
        $method_count = (int)$count_stmt->fetchColumn();
        if ($method_count <= 1) {
            throw new Exception('At least one payment method is required. You cannot remove the last one.');
        }

        $delete_stmt = $pdo->prepare('DELETE FROM tenant_billing_payment_methods WHERE method_id = ? AND tenant_id = ?');
        $delete_stmt->execute([$method_id, $tenant_id]);
        if ($delete_stmt->rowCount() === 0) {
            throw new Exception('Payment method not found.');
        }

        $default_count_stmt = $pdo->prepare('SELECT COUNT(*) FROM tenant_billing_payment_methods WHERE tenant_id = ? AND is_default = 1');
        $default_count_stmt->execute([$tenant_id]);
        if ((int)$default_count_stmt->fetchColumn() === 0) {
            $fallback_stmt = $pdo->prepare('SELECT method_id FROM tenant_billing_payment_methods WHERE tenant_id = ? ORDER BY created_at ASC LIMIT 1');
            $fallback_stmt->execute([$tenant_id]);
            $fallback_id = (int)$fallback_stmt->fetchColumn();
            if ($fallback_id > 0) {
                $pdo->prepare('UPDATE tenant_billing_payment_methods SET is_default = 1 WHERE method_id = ? AND tenant_id = ?')->execute([$fallback_id, $tenant_id]);
            }
        }

        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, description, ip_address) VALUES (?, ?, 'PAYMENT_METHOD_DELETED', ?, ?)");
        $log_stmt->execute([$tenant_id, $_SESSION['user_id'] ?? null, 'A payment method was removed from billing settings.', $_SERVER['REMOTE_ADDR'] ?? '']);

        $pdo->commit();
        $_SESSION['admin_flash'] = 'Payment method removed successfully.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['admin_flash'] = $e->getMessage();
    }

    header('Location: admin.php?tab=billing');
    exit;
}

// ==========================================
// POST Handler — Cancel Subscription
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_subscription') {
    if (!$can_manage_billing) {
        $_SESSION['admin_flash'] = 'You do not have permission to cancel the subscription.';
        header('Location: admin.php?tab=billing');
        exit;
    }

    if (!$tenants_has_cancel_at_period_end) {
        $_SESSION['admin_flash'] = 'Subscription cancellation scheduling is not available on this installation yet.';
        header('Location: admin.php?tab=billing');
        exit;
    }

    try {
        $pdo->prepare('UPDATE tenants SET cancel_at_period_end = 1 WHERE tenant_id = ?')->execute([$tenant_id]);
        
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, description, ip_address) VALUES (?, ?, 'SUBSCRIPTION_CANCELLED', ?, ?)");
        $log_stmt->execute([$tenant_id, $_SESSION['user_id'] ?? null, 'Subscription was marked for cancellation at the end of the billing period.', $_SERVER['REMOTE_ADDR'] ?? '']);
        
        $_SESSION['admin_flash'] = 'Subscription successfully cancelled. You will have access until your next billing date.';
    } catch (Exception $e) {
        $_SESSION['admin_flash'] = 'Failed to cancel subscription: ' . $e->getMessage();
    }
    
    header('Location: admin.php?tab=billing');
    exit;
}

// ==========================================
// POST Handler — Update Personal Profile
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_personal_profile') {
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        $pf = trim($_POST['personal_first_name'] ?? '');
        $pl = trim($_POST['personal_last_name'] ?? '');
        $pe = trim($_POST['personal_email'] ?? '');
        $pp = $_POST['personal_password'] ?? '';
        
        $pdo->prepare('UPDATE employees SET first_name = ?, last_name = ? WHERE user_id = ? AND tenant_id = ?')->execute([$pf, $pl, $uid, $tenant_id]);
        $pdo->prepare('UPDATE users SET email = ? WHERE user_id = ?')->execute([$pe, $uid]);
        
        if ($pp !== '') {
            $hash = password_hash($pp, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([$hash, $uid]);
        }
        
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, description, ip_address) VALUES (?, ?, 'PROFILE_UPDATE', ?, ?)");
        $log_stmt->execute([$tenant_id, $uid, "User updated their personal profile.", $_SERVER['REMOTE_ADDR'] ?? '']);
        
        $_SESSION['admin_flash'] = "Personal profile updated successfully.";
    }
    header("Location: admin.php?tab=personal");
    exit;
}

// ==========================================
// POST Handler — Save Website Content
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_website_content') {
    $layout = 'template1'; // Template 2/3 are temporarily unavailable.

    $hero_title       = trim($_POST['hero_title'] ?? '');
    $hero_subtitle    = trim($_POST['hero_subtitle'] ?? '');
    $hero_description = trim($_POST['hero_description'] ?? '');
    $hero_cta_text    = trim($_POST['hero_cta_text'] ?? 'Learn More');
    $hero_cta_url     = trim($_POST['hero_cta_url'] ?? '#about');
    // Fetch existing image path to fall back on if no new image is uploaded
    $existing_h_stmt = $pdo->prepare("SELECT hero_image_path FROM tenant_website_content WHERE tenant_id = ?");
    $existing_h_stmt->execute([$tenant_id]);
    $hero_image_path = $existing_h_stmt->fetchColumn() ?: '';

    if (isset($_FILES['hero_background']) && (int) $_FILES['hero_background']['error'] === UPLOAD_ERR_OK) {
        $original_name = $_FILES['hero_background']['name'];
        $tmp_name = $_FILES['hero_background']['tmp_name'];
        $size_bytes = (int) $_FILES['hero_background']['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($extension, $allowed, true) && $size_bytes <= (3 * 1024 * 1024)) {
            $upload_dir = __DIR__ . '/../uploads/hero';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
            $safe_tenant = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenant_id);
            $new_name = $safe_tenant . '_bg_' . time() . '.' . $extension;
            if (move_uploaded_file($tmp_name, $upload_dir . '/' . $new_name)) {
                $app_path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
                $hero_image_path = ($app_path === '' ? '/' : $app_path) . '/uploads/hero/' . $new_name;
            }
        }
    }

    $hero_badge_text  = trim($_POST['hero_badge_text'] ?? '');

    $about_heading    = trim($_POST['about_heading'] ?? 'About Us');
    $about_body       = trim($_POST['about_body'] ?? '');
    $about_image_path = trim($_POST['about_image_path'] ?? '');

    $services_heading = trim($_POST['services_heading'] ?? 'Our Services');
    $svc_titles = $_POST['service_title'] ?? [];
    $svc_descs  = $_POST['service_description'] ?? [];
    $svc_icons  = $_POST['service_icon'] ?? [];
    $services_arr = [];
    if (is_array($svc_titles)) {
        for ($i = 0; $i < count($svc_titles); $i++) {
            if (trim($svc_titles[$i]) !== '') {
                $services_arr[] = [
                    'title'       => trim($svc_titles[$i]),
                    'description' => trim($svc_descs[$i] ?? ''),
                    'icon'        => trim($svc_icons[$i] ?? 'star')
                ];
            }
        }
    }
    $services_json = json_encode($services_arr, JSON_UNESCAPED_UNICODE);

    // Stats section
    $stats_heading = trim($_POST['stats_heading'] ?? '');
    $stats_subheading = trim($_POST['stats_subheading'] ?? '');
    $stats_image_path = trim($_POST['stats_image_path'] ?? '');
    $stats_auto_mode = isset($_POST['website_stats_auto']) ? '1' : '0';
    $stat_values = $_POST['stat_value'] ?? [];
    $stat_labels = $_POST['stat_label'] ?? [];
    $stats_arr = [];
    if (is_array($stat_values)) {
        for ($i = 0; $i < count($stat_values); $i++) {
            if (trim($stat_values[$i] ?? '') !== '' || trim($stat_labels[$i] ?? '') !== '') {
                $stats_arr[] = [
                    'value' => trim($stat_values[$i] ?? ''),
                    'label' => trim($stat_labels[$i] ?? '')
                ];
            }
        }
    }

    if ($stats_auto_mode === '1') {
        $active_clients_stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND client_status = 'Active'");
        $active_clients_stmt->execute([$tenant_id]);
        $active_clients = (int)$active_clients_stmt->fetchColumn();

        $active_loans_stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND loan_status = 'Active'");
        $active_loans_stmt->execute([$tenant_id]);
        $active_loans = (int)$active_loans_stmt->fetchColumn();

        $active_staff_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND user_type = 'Employee' AND status = 'Active'");
        $active_staff_stmt->execute([$tenant_id]);
        $active_staff = (int)$active_staff_stmt->fetchColumn();

        $stats_arr = [
            ['value' => number_format($active_clients) . '+', 'label' => 'Active Clients'],
            ['value' => number_format($active_loans) . '+', 'label' => 'Active Loans'],
            ['value' => number_format($active_staff) . '+', 'label' => 'Active Staff'],
            ['value' => date('Y'), 'label' => 'Serving Since']
        ];
    }

    $stats_json = json_encode($stats_arr, JSON_UNESCAPED_UNICODE);

    $contact_address  = trim($_POST['contact_address'] ?? '');
    $contact_phone    = trim($_POST['contact_phone'] ?? '');
    $contact_email    = trim($_POST['contact_email'] ?? '');
    $contact_hours    = trim($_POST['contact_hours'] ?? '');
    $footer_description = trim($_POST['footer_description'] ?? '');
    $custom_css       = trim($_POST['custom_css'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');

    $website_config_post = [
        'website_show_about' => isset($_POST['website_show_about']) ? '1' : '0',
        'website_show_services' => isset($_POST['website_show_services']) ? '1' : '0',
        'website_show_contact' => isset($_POST['website_show_contact']) ? '1' : '0',
        'website_show_download' => isset($_POST['website_show_download']) ? '1' : '0',
        'website_show_stats' => isset($_POST['website_show_stats']) ? '1' : '0',
        'website_stats_auto' => $stats_auto_mode,
        'website_show_loan_calc' => isset($_POST['website_show_loan_calc']) ? '1' : '0',
        'website_show_partners' => isset($_POST['website_show_partners']) ? '1' : '0',
        'website_partners_json' => trim($_POST['website_partners_json'] ?? '[]'),
        'website_download_title' => trim($_POST['website_download_title'] ?? 'Download Our App'),
        'website_download_description' => trim($_POST['website_download_description'] ?? ''),
        'website_download_button_text' => trim($_POST['website_download_button_text'] ?? 'Download App'),
        'website_download_url' => trim($_POST['website_download_url'] ?? '')
    ];

    $upsert_wc = $pdo->prepare('
        INSERT INTO tenant_website_content
            (tenant_id, layout_template, hero_title, hero_subtitle, hero_description,
             hero_cta_text, hero_cta_url, hero_image_path, hero_badge_text,
             about_heading, about_body, about_image_path,
             services_heading, services_json,
             stats_json, stats_heading, stats_subheading, stats_image_path,
             contact_address, contact_phone, contact_email, contact_hours,
             footer_description, custom_css, meta_description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            layout_template = VALUES(layout_template),
            hero_title = VALUES(hero_title), hero_subtitle = VALUES(hero_subtitle),
            hero_description = VALUES(hero_description),
            hero_cta_text = VALUES(hero_cta_text), hero_cta_url = VALUES(hero_cta_url),
            hero_image_path = VALUES(hero_image_path), hero_badge_text = VALUES(hero_badge_text),
            about_heading = VALUES(about_heading), about_body = VALUES(about_body),
            about_image_path = VALUES(about_image_path),
            services_heading = VALUES(services_heading), services_json = VALUES(services_json),
            stats_json = VALUES(stats_json), stats_heading = VALUES(stats_heading),
            stats_subheading = VALUES(stats_subheading), stats_image_path = VALUES(stats_image_path),
            contact_address = VALUES(contact_address), contact_phone = VALUES(contact_phone),
            contact_email = VALUES(contact_email), contact_hours = VALUES(contact_hours),
            footer_description = VALUES(footer_description),
            custom_css = VALUES(custom_css), meta_description = VALUES(meta_description)
    ');
    $upsert_wc->execute([
        $tenant_id, $layout, $hero_title, $hero_subtitle, $hero_description,
        $hero_cta_text, $hero_cta_url, $hero_image_path, $hero_badge_text,
        $about_heading, $about_body, $about_image_path,
        $services_heading, $services_json,
        $stats_json, $stats_heading, $stats_subheading, $stats_image_path,
        $contact_address, $contact_phone, $contact_email, $contact_hours,
        $footer_description, $custom_css, $meta_description
    ]);

    $boolean_setting_keys = ['website_show_about', 'website_show_services', 'website_show_contact', 'website_show_download', 'website_show_stats', 'website_stats_auto', 'website_show_loan_calc', 'website_show_partners'];
    $setting_upsert = $pdo->prepare('
        INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_category = VALUES(setting_category), data_type = VALUES(data_type), updated_at = CURRENT_TIMESTAMP
    ');
    foreach ($website_config_post as $key => $value) {
        $data_type = in_array($key, $boolean_setting_keys, true) ? 'Boolean' : 'String';
        $setting_upsert->execute([$tenant_id, $key, $value, 'Website', $data_type]);
    }

    $pdo->prepare('INSERT INTO tenant_feature_toggles (tenant_id, toggle_key, is_enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_enabled = 1')
        ->execute([$tenant_id, 'public_website_enabled']);

    $buildResult = mf_mobile_app_dispatch_tenant_build(
        $pdo,
        (string) $tenant_id,
        (string) ($_SESSION['tenant_slug'] ?? ''),
        trim($tenant_name !== '' ? $tenant_name : ($hero_title !== '' ? $hero_title : ''))
    );

    $flashMessage = 'Website saved and published successfully!';
    if (!empty($buildResult['message'])) {
        $flashMessage .= ' ' . trim((string) $buildResult['message']);
    }

    $_SESSION['admin_flash'] = $flashMessage;
    header('Location: admin.php?tab=website');
    exit;
}

// ==========================================
// POST Handler — Save Loan Products
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_loan_products') {
    $loan_product_type_options = ['Personal Loan', 'Business Loan', 'Emergency Loan'];
    $target_product_id = (int)($_POST['loan_product_id'] ?? 0);
    $existing_product = null;
    if ($target_product_id > 0) {
        $product_stmt = $pdo->prepare('SELECT * FROM loan_products WHERE tenant_id = ? AND product_id = ? LIMIT 1');
        $product_stmt->execute([$tenant_id, $target_product_id]);
        $existing_product = $product_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $form = [
        'product_name' => trim($_POST['product_name'] ?? ''),
        'product_type' => trim($_POST['product_type'] ?? 'Personal Loan'),
        'custom_product_type' => trim($_POST['custom_product_type'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'min_amount' => trim($_POST['min_amount'] ?? '5000'),
        'max_amount' => trim($_POST['max_amount'] ?? '100000'),
        'interest_rate' => trim($_POST['interest_rate'] ?? '3.00'),
        'interest_type' => trim($_POST['interest_type'] ?? 'Diminishing'),
        'min_term_months' => trim($_POST['min_term_months'] ?? '3'),
        'max_term_months' => trim($_POST['max_term_months'] ?? '24'),
        'processing_fee_percentage' => trim($_POST['processing_fee_percentage'] ?? '2.00'),
        'service_charge' => trim($_POST['service_charge'] ?? '0.00'),
        'documentary_stamp' => trim($_POST['documentary_stamp'] ?? '0.00'),
        'insurance_fee_percentage' => trim($_POST['insurance_fee_percentage'] ?? '0.00'),
        'penalty_rate' => trim($_POST['penalty_rate'] ?? '0.50'),
        'penalty_type' => trim($_POST['penalty_type'] ?? 'Daily'),
        'grace_period_days' => trim($_POST['grace_period_days'] ?? '3'),
    ];
    $resolved_product_type = $form['product_type'] === 'Others' ? $form['custom_product_type'] : $form['product_type'];

    if ($form['product_name'] === '') {
        $_SESSION['admin_error'] = 'Product name is required.';
    } elseif (!in_array($form['product_type'], array_merge($loan_product_type_options, ['Others']), true)) {
        $_SESSION['admin_error'] = 'Invalid product type.';
    } elseif ($form['product_type'] === 'Others' && $form['custom_product_type'] === '') {
        $_SESSION['admin_error'] = 'Please provide a custom product type name.';
    } elseif (!in_array($form['interest_type'], ['Fixed', 'Diminishing', 'Flat'], true)) {
        $_SESSION['admin_error'] = 'Invalid interest type.';
    } elseif (!in_array($form['penalty_type'], ['Daily', 'Monthly', 'Flat'], true)) {
        $_SESSION['admin_error'] = 'Invalid penalty type.';
    } elseif ((float)$form['min_amount'] <= 0 || (float)$form['max_amount'] <= 0) {
        $_SESSION['admin_error'] = 'Loan amounts must be greater than zero.';
    } elseif ((float)$form['min_amount'] > (float)$form['max_amount']) {
        $_SESSION['admin_error'] = 'Minimum amount cannot be greater than maximum amount.';
    } elseif ((int)$form['min_term_months'] < 1 || (int)$form['max_term_months'] < 1) {
        $_SESSION['admin_error'] = 'Loan terms must be at least 1 month.';
    } elseif ((int)$form['min_term_months'] > (int)$form['max_term_months']) {
        $_SESSION['admin_error'] = 'Minimum term cannot exceed maximum term.';
    } elseif ((float)$form['interest_rate'] < 0 || (float)$form['interest_rate'] > 100) {
        $_SESSION['admin_error'] = 'Interest rate must be between 0 and 100.';
    } else {
        $original_product_type = trim((string)($existing_product['product_type'] ?? ''));
        $is_changing_product_type = !$existing_product || strcasecmp($original_product_type, $resolved_product_type) !== 0;

        if ($is_changing_product_type) {
            $duplicate_type_stmt = $pdo->prepare('SELECT product_id FROM loan_products WHERE tenant_id = ? AND product_type = ? AND (? = 0 OR product_id <> ?) LIMIT 1');
            $duplicate_type_stmt->execute([$tenant_id, $resolved_product_type, $target_product_id, $target_product_id]);
            $duplicate_type_exists = $duplicate_type_stmt->fetchColumn();

            if ($duplicate_type_exists) {
                $_SESSION['admin_error'] = 'This product type is already being used in your workspace. Please choose another one.';
            }
        }
    }

    if (!isset($_SESSION['admin_error'])) {
        if ($existing_product) {
            $stmt = $pdo->prepare('UPDATE loan_products SET product_name=?, product_type=?, description=?, min_amount=?, max_amount=?, interest_rate=?, interest_type=?, min_term_months=?, max_term_months=?, processing_fee_percentage=?, service_charge=?, documentary_stamp=?, insurance_fee_percentage=?, penalty_rate=?, penalty_type=?, grace_period_days=? WHERE tenant_id=? AND product_id=?');
            $stmt->execute([
                $form['product_name'], $resolved_product_type, $form['description'],
                (float)$form['min_amount'], (float)$form['max_amount'], (float)$form['interest_rate'],
                $form['interest_type'], (int)$form['min_term_months'], (int)$form['max_term_months'],
                (float)$form['processing_fee_percentage'], (float)$form['service_charge'], (float)$form['documentary_stamp'],
                (float)$form['insurance_fee_percentage'], (float)$form['penalty_rate'], $form['penalty_type'],
                (int)$form['grace_period_days'], $tenant_id, $existing_product['product_id']
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO loan_products (tenant_id, product_name, product_type, description, min_amount, max_amount, interest_rate, interest_type, min_term_months, max_term_months, processing_fee_percentage, service_charge, documentary_stamp, insurance_fee_percentage, penalty_rate, penalty_type, grace_period_days, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)');
            $stmt->execute([
                $tenant_id, $form['product_name'], $resolved_product_type, $form['description'],
                (float)$form['min_amount'], (float)$form['max_amount'], (float)$form['interest_rate'],
                $form['interest_type'], (int)$form['min_term_months'], (int)$form['max_term_months'],
                (float)$form['processing_fee_percentage'], (float)$form['service_charge'], (float)$form['documentary_stamp'],
                (float)$form['insurance_fee_percentage'], (float)$form['penalty_rate'], $form['penalty_type'],
                (int)$form['grace_period_days'],
            ]);
        }

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'LOAN_PRODUCT_UPDATED', 'loan_product', 'Loan product settings updated', ?)");
        $log->execute([$_SESSION['user_id'] ?? null, $tenant_id]);
        
        $_SESSION['admin_flash'] = "Loan product settings saved successfully.";
        unset($_SESSION['loan_products_form']);
    } else {
        $_SESSION['loan_products_form'] = $form;
    }
    $redirect_product_id = $existing_product['product_id'] ?? (int)$pdo->lastInsertId();
    $redirect_url = 'admin.php?tab=loan_products';
    if ($redirect_product_id > 0) {
        $redirect_url .= '&loan_product_id=' . $redirect_product_id;
    } elseif ($target_product_id === 0) {
        $redirect_url .= '&loan_product_mode=new';
    }
    header('Location: ' . $redirect_url);
    exit;
}

function credit_limit_rule_defaults(): array
{
    return [
        'workflow' => [
            'approval_mode' => 'semi',
        ],
        'initial_limits' => [
            'base_limit_default' => 5000,
            'custom_categories' => [],
        ],
        'upgrade_eligibility' => [
            'min_completed_loans' => 2,
            'max_allowed_late_payments' => 0,
        ],
        'increase_rules' => [
            'increase_type' => 'percentage',
            'increase_value' => 20,
            'absolute_max_limit' => 50000,
        ],
    ];
}

function normalize_credit_limit_rules($payload): array
{
    $defaults = credit_limit_rule_defaults();
    $rules = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;

    $base_limit_default = max(0, (float)($rules['initial_limits']['base_limit_default'] ?? $defaults['initial_limits']['base_limit_default']));
    $base_limit_default = round($base_limit_default, 2);

    $custom_categories = [];
    if (!empty($rules['initial_limits']['custom_categories']) && is_array($rules['initial_limits']['custom_categories'])) {
        foreach ($rules['initial_limits']['custom_categories'] as $categoryRule) {
            if (!is_array($categoryRule)) {
                continue;
            }

            $category_name = trim((string)($categoryRule['category_name'] ?? ''));
            $limit_type = (string)($categoryRule['limit_type'] ?? 'fixed');
            $value = round(max(0, (float)($categoryRule['value'] ?? 0)), 2);

            if ($category_name === '') {
                continue;
            }
            if ($limit_type === 'multiplier') {
                $value = round($base_limit_default * $value, 2);
                $limit_type = 'fixed';
            }
            if (!in_array($limit_type, ['fixed', 'income_percent'], true)) {
                $limit_type = 'fixed';
            }

            $custom_categories[] = [
                'category_name' => substr($category_name, 0, 80),
                'limit_type' => $limit_type,
                'value' => $value,
            ];
        }
    }

    $min_completed_loans = max(0, (int)($rules['upgrade_eligibility']['min_completed_loans'] ?? $defaults['upgrade_eligibility']['min_completed_loans']));
    $max_allowed_late_payments = max(0, (int)($rules['upgrade_eligibility']['max_allowed_late_payments'] ?? $defaults['upgrade_eligibility']['max_allowed_late_payments']));

    $increase_type = (string)($rules['increase_rules']['increase_type'] ?? $defaults['increase_rules']['increase_type']);
    if (!in_array($increase_type, ['percentage', 'fixed'], true)) {
        $increase_type = $defaults['increase_rules']['increase_type'];
    }

    $increase_value = round(max(0, (float)($rules['increase_rules']['increase_value'] ?? $defaults['increase_rules']['increase_value'])), 2);
    $absolute_max_limit = round(max(0, (float)($rules['increase_rules']['absolute_max_limit'] ?? $defaults['increase_rules']['absolute_max_limit'])), 2);

    return [
        'workflow' => [
            // Upgrade processing is fixed to semi-auto so staff always confirms increases.
            'approval_mode' => 'semi',
        ],
        'initial_limits' => [
            'base_limit_default' => $base_limit_default,
            'custom_categories' => $custom_categories,
        ],
        'upgrade_eligibility' => [
            'min_completed_loans' => $min_completed_loans,
            'max_allowed_late_payments' => $max_allowed_late_payments,
        ],
        'increase_rules' => [
            'increase_type' => $increase_type,
            'increase_value' => $increase_value,
            'absolute_max_limit' => $absolute_max_limit,
        ],
    ];
}

function build_credit_limit_rules_from_post(array $source): array
{
    $defaults = credit_limit_rule_defaults();

    $rules = [
        'workflow' => [
            'approval_mode' => 'semi',
        ],
        'initial_limits' => [
            'base_limit_default' => (float)($source['credit_base_limit'] ?? $defaults['initial_limits']['base_limit_default']),
            'custom_categories' => [],
        ],
        'upgrade_eligibility' => [
            'min_completed_loans' => (int)($source['credit_min_completed_loans'] ?? $defaults['upgrade_eligibility']['min_completed_loans']),
            'max_allowed_late_payments' => (int)($source['credit_max_late_payments'] ?? $defaults['upgrade_eligibility']['max_allowed_late_payments']),
        ],
        'increase_rules' => [
            'increase_type' => (string)($source['credit_increase_type'] ?? $defaults['increase_rules']['increase_type']),
            'increase_value' => (float)($source['credit_increase_value'] ?? $defaults['increase_rules']['increase_value']),
            'absolute_max_limit' => (float)($source['credit_absolute_max_limit'] ?? $defaults['increase_rules']['absolute_max_limit']),
        ],
    ];

    $selectedCategories = isset($source['credit_category_select']) && is_array($source['credit_category_select'])
        ? $source['credit_category_select']
        : [];
    $customCategories = isset($source['credit_category_custom']) && is_array($source['credit_category_custom'])
        ? $source['credit_category_custom']
        : [];
    $limitTypes = isset($source['credit_category_type']) && is_array($source['credit_category_type'])
        ? $source['credit_category_type']
        : [];
    $limitValues = isset($source['credit_category_value']) && is_array($source['credit_category_value'])
        ? $source['credit_category_value']
        : [];

    $rowCount = max(count($selectedCategories), count($customCategories), count($limitTypes), count($limitValues));
    for ($index = 0; $index < $rowCount; $index++) {
        $selectedCategory = trim((string)($selectedCategories[$index] ?? ''));
        $categoryName = $selectedCategory === 'Others'
            ? trim((string)($customCategories[$index] ?? ''))
            : $selectedCategory;

        if ($categoryName === '') {
            continue;
        }

        $rules['initial_limits']['custom_categories'][] = [
            'category_name' => $categoryName,
            'limit_type' => (string)($limitTypes[$index] ?? 'fixed'),
            'value' => (float)($limitValues[$index] ?? 0),
        ];
    }

    return $rules;
}

// ==========================================
// POST Handler — Save Credit Assessment Settings
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_credit_settings') {
    $creditScoreCeiling = mf_credit_policy_score_ceiling();
    $form = [
        'minimum_credit_score' => trim($_POST['minimum_credit_score'] ?? '500'),
        'income_weight' => trim($_POST['income_weight'] ?? '25'),
        'employment_weight' => trim($_POST['employment_weight'] ?? '20'),
        'credit_history_weight' => trim($_POST['credit_history_weight'] ?? '20'),
        'collateral_weight' => trim($_POST['collateral_weight'] ?? '10'),
        'character_weight' => trim($_POST['character_weight'] ?? '15'),
        'business_weight' => trim($_POST['business_weight'] ?? '10'),
        'require_ci' => trim($_POST['require_ci'] ?? '0'),
        'auto_reject_below' => trim($_POST['auto_reject_below'] ?? '300'),
    ];

    $total_weight = (int)$form['income_weight'] + (int)$form['employment_weight']
                  + (int)$form['credit_history_weight'] + (int)$form['collateral_weight']
                  + (int)$form['character_weight'] + (int)$form['business_weight'];

    if ($total_weight !== 100) {
        $_SESSION['admin_error'] = "Scoring weights must total exactly 100%. Currently: {$total_weight}%.";
    } elseif ((int)$form['minimum_credit_score'] < 0 || (int)$form['minimum_credit_score'] > $creditScoreCeiling) {
        $_SESSION['admin_error'] = 'Minimum credit score must be between 0 and ' . number_format($creditScoreCeiling) . '.';
    } elseif ((int)$form['auto_reject_below'] < 0 || (int)$form['auto_reject_below'] > (int)$form['minimum_credit_score']) {
        $_SESSION['admin_error'] = 'Auto-reject score must be between 0 and the minimum credit score.';
    } else {
        $settings = [
            'minimum_credit_score' => ['Credit', $form['minimum_credit_score'], 'Number'],
            'credit_weight_income' => ['Credit', $form['income_weight'], 'Number'],
            'credit_weight_employment' => ['Credit', $form['employment_weight'], 'Number'],
            'credit_weight_credit_history' => ['Credit', $form['credit_history_weight'], 'Number'],
            'credit_weight_collateral' => ['Credit', $form['collateral_weight'], 'Number'],
            'credit_weight_character' => ['Credit', $form['character_weight'], 'Number'],
            'credit_weight_business' => ['Credit', $form['business_weight'], 'Number'],
            'require_credit_investigation' => ['Credit', $form['require_ci'], 'Boolean'],
            'auto_reject_below_score' => ['Credit', $form['auto_reject_below'], 'Number'],
        ];

        $upsert = $pdo->prepare('INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), data_type = VALUES(data_type), updated_at = CURRENT_TIMESTAMP');

        foreach ($settings as $key => [$category, $value, $type]) {
            $upsert->execute([$tenant_id, $key, $value, $category, $type]);
        }

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'CREDIT_CONFIG_UPDATED', 'system_settings', 'Credit scoring settings updated', ?)");
        $log->execute([$_SESSION['user_id'] ?? null, $tenant_id]);

        $_SESSION['admin_flash'] = "Credit assessment settings saved successfully.";
    }
    header('Location: admin.php?tab=credit_settings');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_credit_limit_rules') {
    $normalized_rules = normalize_credit_limit_rules(build_credit_limit_rules_from_post($_POST));
    $encoded_rules = json_encode($normalized_rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($encoded_rules === false) {
        $_SESSION['admin_error'] = 'Unable to save credit limit rules right now.';
    } else {
        $upsert = $pdo->prepare(
            'INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type) '
            . 'VALUES (?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_category = VALUES(setting_category), data_type = VALUES(data_type), updated_at = CURRENT_TIMESTAMP'
        );
        $upsert->execute([$tenant_id, 'credit_limit_rules', $encoded_rules, 'Credit', 'JSON']);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'CREDIT_LIMIT_RULES_UPDATED', 'system_settings', 'Credit limit rule settings updated', ?)");
        $log->execute([$_SESSION['user_id'] ?? null, $tenant_id]);

        $_SESSION['admin_flash'] = 'Credit limit rules saved successfully.';
    }

    header('Location: admin.php?tab=credit_settings');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_credit_policy') {
    $policy_input = [
        'eligibility' => [
            'min_monthly_income' => $_POST['cp_min_monthly_income'] ?? 0,
            'allowed_employment_statuses' => $_POST['cp_allowed_employment_statuses'] ?? [],
        ],
        'score_thresholds' => [
            'not_recommended_min_score' => $_POST['cp_not_recommended_min_score'] ?? 200,
            'conditional_min_score' => $_POST['cp_conditional_min_score'] ?? 400,
            'recommended_min_score' => $_POST['cp_recommended_min_score'] ?? 600,
            'highly_recommended_min_score' => $_POST['cp_highly_recommended_min_score'] ?? 800,
            'new_client_default_score' => $_POST['cp_new_client_default_score'] ?? 500,
        ],
        'ci_rules' => [
            'require_ci' => $_POST['cp_require_ci'] ?? 0,
            'ci_required_above_amount' => $_POST['cp_ci_required_above_amount'] ?? 0,
            'auto_approve_ci_values' => $_POST['cp_auto_approve_ci_values'] ?? [],
            'review_ci_values' => $_POST['cp_review_ci_values'] ?? [],
        ],
        'credit_limit' => [
            'income_multiplier' => $_POST['cp_income_multiplier'] ?? 0,
            'approve_band_multiplier' => $_POST['cp_approve_band_multiplier'] ?? 0,
            'review_band_multiplier' => $_POST['cp_review_band_multiplier'] ?? 0,
            'reject_band_multiplier' => $_POST['cp_reject_band_multiplier'] ?? 0,
            'max_credit_limit_cap' => $_POST['cp_max_credit_limit_cap'] ?? 0,
            'round_to_nearest' => $_POST['cp_round_to_nearest'] ?? 0,
        ],
        'product_checks' => [
            'use_product_minimum_credit_score' => $_POST['cp_use_product_minimum_credit_score'] ?? 0,
            'use_product_min_amount' => $_POST['cp_use_product_min_amount'] ?? 0,
            'use_product_max_amount' => $_POST['cp_use_product_max_amount'] ?? 0,
        ],
    ];

    $normalized_policy = mf_credit_policy_normalize($policy_input);
    $encoded_policy = json_encode($normalized_policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($encoded_policy === false) {
        $_SESSION['admin_error'] = 'Unable to save credit policy right now.';
    } else {
        $upsert = $pdo->prepare(
            'INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type) '
            . 'VALUES (?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_category = VALUES(setting_category), data_type = VALUES(data_type), updated_at = CURRENT_TIMESTAMP'
        );
        $upsert->execute([$tenant_id, 'credit_policy', $encoded_policy, 'Credit', 'JSON']);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'CREDIT_POLICY_UPDATED', 'system_settings', 'Credit policy settings updated', ?)");
        $log->execute([$_SESSION['user_id'] ?? null, $tenant_id]);

        $_SESSION['admin_flash'] = 'Credit policy saved successfully.';
    }

    header('Location: admin.php?tab=credit_settings');
    exit;
}

// ==========================================
// Helper function for duplicate role checking
// ==========================================
function check_duplicate_permissions($pdo, $tenant_id, $incoming_perms, $exclude_role_id = null) {
    if (empty($incoming_perms)) {
        $incoming_perms = [];
    }
    // Normalize and sort incoming perms
    $incoming_perms_sorted = array_unique($incoming_perms);
    sort($incoming_perms_sorted);
    $incoming_str = implode(',', $incoming_perms_sorted);

    // Fetch existing roles for tenant
    $roles_stmt = $pdo->prepare('SELECT role_id, role_name FROM user_roles WHERE tenant_id = ?');
    $roles_stmt->execute([$tenant_id]);
    $existing_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($existing_roles as $r) {
        if ($exclude_role_id && $r['role_id'] == $exclude_role_id) {
            continue;
        }

        // Fetch perms for this role
        $perms_stmt = $pdo->prepare('SELECT p.permission_code FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.permission_id WHERE rp.role_id = ?');
        $perms_stmt->execute([$r['role_id']]);
        $existing_perms = $perms_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $existing_perms_sorted = array_unique($existing_perms);
        sort($existing_perms_sorted);
        $existing_str = implode(',', $existing_perms_sorted);

        if ($incoming_str === $existing_str) {
            return $r['role_name']; // Found duplicate
        }
    }
    return false; // No duplicate
}

// ==========================================
// Form Handlers for Roles & Permissions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'create_role') {
            $role_name = trim($_POST['role_name'] ?? '');
            $initial_perms = $_POST['initial_permissions'] ?? [];
            if (is_array($initial_perms)) {
                $initial_perms = array_values(array_filter($initial_perms, function ($code) {
                    return $code !== 'EDIT_BILLING';
                }));
            }

            if (empty($role_name)) {
                throw new Exception('Role name is required.');
            }

            // Check for duplicate permissions first
            $duplicate_role_name = check_duplicate_permissions($pdo, $tenant_id, $initial_perms);
            if ($duplicate_role_name) {
                throw new Exception("Cannot create role. The exact same set of permissions already exists in the role: '{$duplicate_role_name}'.");
            }
            
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, ?, ?, FALSE)');
            $stmt->execute([$tenant_id, $role_name, 'Custom role']);
            $new_role_id = $pdo->lastInsertId();

            if (!empty($initial_perms)) {
                $in_placeholders = str_repeat('?,', count($initial_perms) - 1) . '?';
                $lookup_stmt = $pdo->prepare("SELECT permission_id FROM permissions WHERE permission_code IN ($in_placeholders)");
                $lookup_stmt->execute($initial_perms);
                $perm_ids = $lookup_stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($perm_ids)) {
                    $insert_values = [];
                    $insert_params = [];
                    foreach ($perm_ids as $pid) {
                        $insert_values[] = '(?, ?)';
                        $insert_params[] = $new_role_id;
                        $insert_params[] = $pid;
                    }
                    $map_stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES " . implode(',', $insert_values));
                    $map_stmt->execute($insert_params);
                }
            }

            $pdo->commit();
            $_SESSION['admin_flash'] = 'Role created successfully.';
            header('Location: admin.php?tab=roles-list&role_id=' . $new_role_id);
            exit;
        }

        if ($action === 'delete_role') {
            $role_id = (int)($_POST['role_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM user_roles WHERE role_id = ? AND tenant_id = ? AND is_system_role = FALSE');
            $stmt->execute([$role_id, $tenant_id]);
            $_SESSION['admin_flash'] = 'Role deleted successfully.';
            header('Location: admin.php?tab=roles-list');
            exit;
        }

        if ($action === 'save_permissions') {
            $role_id = (int)($_POST['role_id'] ?? 0);
            $permissions = $_POST['permissions'] ?? [];
            
            $check = $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = ? AND tenant_id = ?');
            $check->execute([$role_id, $tenant_id]);
            if ($check->fetchColumn() == 0) throw new Exception('Invalid role.');

            $role_meta_stmt = $pdo->prepare('SELECT role_name, is_system_role FROM user_roles WHERE role_id = ? AND tenant_id = ? LIMIT 1');
            $role_meta_stmt->execute([$role_id, $tenant_id]);
            $role_meta = $role_meta_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $is_target_admin_role = ((int)($role_meta['is_system_role'] ?? 0) === 1 && (($role_meta['role_name'] ?? '') === 'Admin'));
            if (!$is_target_admin_role && is_array($permissions)) {
                $permissions = array_values(array_filter($permissions, function ($code) {
                    return $code !== 'EDIT_BILLING';
                }));
            }

            // Check for duplicate permissions before updating
            $duplicate_role_name = check_duplicate_permissions($pdo, $tenant_id, $permissions, $role_id);
            if ($duplicate_role_name) {
                throw new Exception("Cannot save permissions. The exact same set of permissions already exists in the role: '{$duplicate_role_name}'.");
            }

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$role_id]);
            
            if (!empty($permissions) && is_array($permissions)) {
                $insert_stmt = $pdo->prepare('
                    INSERT INTO role_permissions (role_id, permission_id) 
                    SELECT ?, permission_id FROM permissions WHERE permission_code = ?
                ');
                foreach ($permissions as $code) {
                    $insert_stmt->execute([$role_id, $code]);
                }
            }
            
            $pdo->commit();
            $_SESSION['admin_flash'] = 'Permissions saved successfully.';
            header('Location: admin.php?tab=roles-list&role_id=' . $role_id);
            exit;
        }

    // ─── Toggle Staff Status ─────────────────────────────────
        if ($action === 'toggle_staff_status') {
            $target_user_id = trim($_POST['user_id'] ?? '');
            $new_status = ($_POST['new_status'] ?? 'Active') === 'Active' ? 'Active' : 'Suspended';
            if (empty($target_user_id)) throw new Exception('Invalid user.');
            $s = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ? AND tenant_id = ? AND user_type = \'Employee\'');
            $s->execute([$new_status, $target_user_id, $tenant_id]);
            
            $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'STAFF_STATUS_CHANGE', 'user', ?, ?)")->execute([$_SESSION['user_id'] ?? null, "Staff account status changed to $new_status", $tenant_id]);
            
            $_SESSION['admin_flash'] = "Staff status updated to $new_status.";
            header('Location: admin.php?tab=staff-list');
            exit;
        }

        // ─── Edit Staff ──────────────────────────────────────────
        if ($action === 'edit_staff') {
            $target_user_id = trim($_POST['user_id'] ?? '');
            $first_name     = trim($_POST['first_name'] ?? '');
            $last_name      = trim($_POST['last_name'] ?? '');
            $email          = trim($_POST['email'] ?? '');
            $role_id        = (int)($_POST['role_id'] ?? 0);
            $status         = in_array($_POST['status'] ?? '', ['Active','Inactive','Suspended']) ? $_POST['status'] : 'Active';

            if (empty($target_user_id) || empty($first_name) || empty($last_name) || empty($email) || !$role_id) {
                throw new Exception('All fields are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address.');
            }
            // Check email uniqueness (excluding themselves)
            $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND email = ? AND user_id != ?');
            $dup->execute([$tenant_id, $email, $target_user_id]);
            if ($dup->fetchColumn() > 0) {
                throw new Exception('That email is already in use by another account.');
            }

            $billing_access_enabled = admin_role_supports_billing_toggle($pdo, (string)$tenant_id, $role_id)
                && isset($_POST['can_manage_billing'])
                && $_POST['can_manage_billing'] === '1';

            $pdo->beginTransaction();
            if ($users_has_can_manage_billing) {
                $pdo->prepare('UPDATE users SET email = ?, role_id = ?, status = ?, can_manage_billing = ? WHERE user_id = ? AND tenant_id = ? AND user_type = \'Employee\'')
                    ->execute([$email, $role_id, $status, $billing_access_enabled ? 1 : 0, $target_user_id, $tenant_id]);
            } else {
                $pdo->prepare('UPDATE users SET email = ?, role_id = ?, status = ? WHERE user_id = ? AND tenant_id = ? AND user_type = \'Employee\'')
                    ->execute([$email, $role_id, $status, $target_user_id, $tenant_id]);
            }
            $pdo->prepare('UPDATE employees SET first_name = ?, last_name = ? WHERE user_id = ? AND tenant_id = ?')
                ->execute([$first_name, $last_name, $target_user_id, $tenant_id]);
            if (!$users_has_can_manage_billing) {
                mf_set_user_billing_access($pdo, (string)$tenant_id, $target_user_id, $billing_access_enabled);
            }
            
            $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'STAFF_UPDATED', 'user', ?, ?)")->execute([$_SESSION['user_id'] ?? null, "Staff account updated for $first_name $last_name", $tenant_id]);
            
            $pdo->commit();

            $_SESSION['admin_flash'] = 'Staff account updated successfully.';
            header('Location: admin.php?tab=staff-list');
            exit;
        }

        if ($action === 'create_staff') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $status = 'Active'; // Automatically active
            $create_as_admin = isset($_POST['create_as_admin']) && $_POST['create_as_admin'] === '1';

            $can_manage_billing_input = false;

            if ($create_as_admin) {
                $admin_role_stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE tenant_id = ? AND role_name = 'Admin' LIMIT 1");
                $admin_role_stmt->execute([$tenant_id]);
                $admin_role_id = (int)$admin_role_stmt->fetchColumn();
                if ($admin_role_id <= 0) {
                    throw new Exception('Admin role could not be found for this tenant.');
                }
                $role_id = $admin_role_id;
            } else {
                $role_id = (int)($_POST['role_id'] ?? 0);
            }

            if (empty($first_name) || empty($last_name) || empty($email) || empty($role_id)) {
                throw new Exception('All fields are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address.');
            }

            $can_manage_billing_input = admin_role_supports_billing_toggle($pdo, (string)$tenant_id, $role_id)
                && isset($_POST['can_manage_billing'])
                && $_POST['can_manage_billing'] === '1';

            // Enforce max_users limit for Staff Accounts
            $plan_stmt = $pdo->prepare('SELECT max_users FROM tenants WHERE tenant_id = ? LIMIT 1');
            $plan_stmt->execute([$tenant_id]);
            $max_users = (int) $plan_stmt->fetchColumn();

            if ($max_users > 0 && $status === 'Active') {
                $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND user_type = 'Employee'");
                $count_stmt->execute([$tenant_id]);
                $current_staff = (int) $count_stmt->fetchColumn();

                if ($current_staff >= $max_users) {
                    throw new Exception('Your organization has reached the maximum number of staff accounts allowed by your subscription plan. Please upgrade to add more staff.');
                }
            }

            // Generate an initial username and password
            $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . '.' . $last_name));
            $username = $base_username;
            $counter = 1;
            
            // Ensure unique username in tenant
            while (true) {
                $check_username = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND username = ?');
                $check_username->execute([$tenant_id, $username]);
                if ($check_username->fetchColumn() == 0) break;
                $username = $base_username . $counter++;
            }

            // Check if email exists in tenant
            $check_email = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND email = ?');
            $check_email->execute([$tenant_id, $email]);
            if ($check_email->fetchColumn() > 0) {
                throw new Exception('A user with this email already exists in your organization.');
            }

            // Generate a secure random temporary password
            $temp_password = admin_generate_temporary_password(12);
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            if ($users_has_can_manage_billing) {
                $user_stmt = $pdo->prepare('INSERT INTO users (tenant_id, username, email, password_hash, force_password_change, role_id, user_type, status, can_manage_billing) VALUES (?, ?, ?, ?, TRUE, ?, \'Employee\', ?, ?)');
                $user_stmt->execute([$tenant_id, $username, $email, $password_hash, $role_id, $status, $can_manage_billing_input ? 1 : 0]);
            } else {
                $user_stmt = $pdo->prepare('INSERT INTO users (tenant_id, username, email, password_hash, force_password_change, role_id, user_type, status) VALUES (?, ?, ?, ?, TRUE, ?, \'Employee\', ?)');
                $user_stmt->execute([$tenant_id, $username, $email, $password_hash, $role_id, $status]);
            }
            $new_user_id = $pdo->lastInsertId();

            $emp_stmt = $pdo->prepare('INSERT INTO employees (user_id, tenant_id, first_name, last_name, department, hire_date) VALUES (?, ?, ?, ?, \'Admin\', CURDATE())');
            $emp_stmt->execute([$new_user_id, $tenant_id, $first_name, $last_name]);
            if (!$users_has_can_manage_billing) {
                mf_set_user_billing_access($pdo, (string)$tenant_id, $new_user_id, $can_manage_billing_input);
            }

            $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'STAFF_ADDED', 'user', ?, ?)")->execute([$_SESSION['user_id'] ?? null, "New staff account created for $first_name $last_name", $tenant_id]);

            $pdo->commit();
            // Get the tenant slug for the login link
            $slug_stmt = $pdo->prepare('SELECT tenant_slug FROM tenants WHERE tenant_id = ?');
            $slug_stmt->execute([$tenant_id]);
            $tenant_slug = $slug_stmt->fetchColumn();
            $login_url = admin_build_tenant_login_url((string)$tenant_slug);
            
            $subject = "Welcome to " . $_SESSION['tenant_name'] . " - Employee Logins";
            $message = "Hello $first_name,\n\n"
                     . "An employee portal account has been created for you at " . $_SESSION['tenant_name'] . ".\n\n"
                     . "Please log in and set up your permanent password using the following details:\n\n"
                     . "Login URL: $login_url\n"
                     . "Temporary Password: $temp_password\n\n"
                     . "Note: You will be required to change this password on your first login.\n\n"
                     . "Best Regards,\n"
                     . $_SESSION['tenant_name'] . " Administration";
                     
            $result_msg = mf_send_brevo_email($email, $subject, nl2br($message));
            if ($result_msg === 'Email sent successfully.') {
                $_SESSION['admin_flash'] = "Staff account created! An email has been sent to them with instructions.";
            } else {
                $_SESSION['admin_flash'] = "Staff account created! (Note: Email failed to send: $result_msg. Please manually distribute this Temporary Password: <strong>$temp_password</strong> and instruct them to log into your portal at: <strong>../tenant_login/login.php?s=$tenant_slug</strong>)";
            }

            header('Location: admin.php?tab=staff-list');
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() == 23000) {
            $_SESSION['admin_error'] = 'A role with this name already exists.';
        } else {
            $_SESSION['admin_error'] = $e->getMessage();
        }
        header('Location: admin.php?tab=roles-list');
        exit;
    }
}

// ==========================================
// Fetch recent staff movement for dashboard
$staff_audit_stmt = $pdo->prepare("
    SELECT al.action_type, al.description, al.created_at,
           CASE
               WHEN u.user_id IS NULL OR NULLIF(TRIM(u.username), '') IS NULL THEN 'System'
               WHEN u.user_type = 'Super Admin' THEN CONCAT(u.username, ' (Super Admin)')
               WHEN NULLIF(TRIM(ur.role_name), '') IS NOT NULL THEN CONCAT(u.username, ' (', ur.role_name, ')')
               ELSE u.username
           END AS actor_name
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    LEFT JOIN user_roles ur ON u.role_id = ur.role_id
    WHERE al.tenant_id = ? AND al.action_type IN ('STAFF_ADDED', 'STAFF_UPDATED', 'STAFF_LOGIN', 'STAFF_LOGOUT', 'STAFF_STATUS_CHANGE', 'IMPERSONATION') 
    ORDER BY al.created_at DESC LIMIT 5
");
$staff_audit_stmt->execute([$tenant_id]);
$staff_audit_logs = $staff_audit_stmt->fetchAll(PDO::FETCH_ASSOC);

$all_audit_logs_stmt = $pdo->prepare("SELECT a.*, CASE WHEN u.user_id IS NULL OR NULLIF(TRIM(u.username), '') IS NULL THEN 'System' WHEN u.user_type = 'Super Admin' THEN CONCAT(u.username, ' (Super Admin)') WHEN NULLIF(TRIM(ur.role_name), '') IS NOT NULL THEN CONCAT(u.username, ' (', ur.role_name, ')') ELSE u.username END AS actor_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id LEFT JOIN user_roles ur ON u.role_id = ur.role_id WHERE a.tenant_id = ? ORDER BY a.created_at DESC LIMIT 100");
$all_audit_logs_stmt->execute([$tenant_id]);
$all_audit_logs = $all_audit_logs_stmt->fetchAll(PDO::FETCH_ASSOC);

$dashboard_total_clients_stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ?");
$dashboard_total_clients_stmt->execute([$tenant_id]);
$dashboard_total_clients = (int)$dashboard_total_clients_stmt->fetchColumn();

$dashboard_active_staff_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND user_type = 'Employee' AND status = 'Active'");
$dashboard_active_staff_stmt->execute([$tenant_id]);
$dashboard_active_staff = (int)$dashboard_active_staff_stmt->fetchColumn();

$dashboard_alerts_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status IN ('Suspended', 'Inactive')");
$dashboard_alerts_stmt->execute([$tenant_id]);
$dashboard_system_alerts = (int)$dashboard_alerts_stmt->fetchColumn();

$dashboard_active_clients = (int)admin_safe_fetch_value(
    $pdo,
    "SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND client_status = 'Active' AND deleted_at IS NULL",
    [$tenant_id],
    0
);
$dashboard_pending_applications = (int)admin_safe_fetch_value(
    $pdo,
    "SELECT COUNT(*) FROM loan_applications WHERE tenant_id = ? AND application_status NOT IN ('Approved','Rejected','Cancelled','Withdrawn')",
    [$tenant_id],
    0
);
$dashboard_active_loans = (int)admin_safe_fetch_value(
    $pdo,
    "SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND loan_status IN ('Active', 'Overdue')",
    [$tenant_id],
    0
);
$dashboard_overdue_loans = (int)admin_safe_fetch_value(
    $pdo,
    "SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND loan_status = 'Overdue'",
    [$tenant_id],
    0
);
$dashboard_total_portfolio = (float)admin_safe_fetch_value(
    $pdo,
    "SELECT COALESCE(SUM(remaining_balance), 0) FROM loans WHERE tenant_id = ? AND loan_status IN ('Active', 'Overdue')",
    [$tenant_id],
    0
);
$dashboard_todays_collections = (float)admin_safe_fetch_value(
    $pdo,
    "SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND payment_status != 'Cancelled'",
    [$tenant_id],
    0
);
$dashboard_plan_snapshot = admin_safe_fetch_row(
    $pdo,
    "SELECT plan_tier, max_clients, max_users, mrr FROM tenants WHERE tenant_id = ? LIMIT 1",
    [$tenant_id],
    ['plan_tier' => 'Starter', 'max_clients' => 0, 'max_users' => 0, 'mrr' => 0]
);
$dashboard_plan_name = trim((string)($dashboard_plan_snapshot['plan_tier'] ?? 'Starter'));
$dashboard_plan_price = (float)($dashboard_plan_snapshot['mrr'] ?? 0);
$dashboard_client_limit = (int)($dashboard_plan_snapshot['max_clients'] ?? 0);
$dashboard_staff_limit = (int)($dashboard_plan_snapshot['max_users'] ?? 0);
$dashboard_client_utilization = $dashboard_client_limit > 0 ? min(100, (int)round(($dashboard_active_clients / $dashboard_client_limit) * 100)) : 0;
$dashboard_staff_utilization = $dashboard_staff_limit > 0 ? min(100, (int)round(($dashboard_active_staff / $dashboard_staff_limit) * 100)) : 0;
$dashboard_utilization_peak = max($dashboard_client_utilization, $dashboard_staff_utilization);
$dashboard_health_tone = 'stable';
$dashboard_health_title = 'Operations look healthy';
$dashboard_health_detail = 'Your workspace is within normal operating ranges today.';
if ($dashboard_overdue_loans > 0 || $dashboard_utilization_peak >= 90 || $dashboard_system_alerts > 0) {
    $dashboard_health_tone = 'warning';
    $dashboard_health_title = 'Needs attention';
    $dashboard_health_detail = 'A few areas need review so daily operations stay on track.';
}
if ($dashboard_overdue_loans >= 10 || $dashboard_utilization_peak >= 100 || $dashboard_system_alerts >= 5) {
    $dashboard_health_tone = 'critical';
    $dashboard_health_title = 'Immediate action recommended';
    $dashboard_health_detail = 'Critical alerts, high utilization, or overdue accounts are stacking up.';
}

// Pre-fetch Data for UI Rendering
// ==========================================
// 1. Fetch Roles
$roles_stmt = $pdo->prepare('SELECT * FROM user_roles WHERE tenant_id = ? ORDER BY is_system_role DESC, created_at ASC');
$roles_stmt->execute([$tenant_id]);
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
$staff_assignable_roles = array_values(array_filter($roles, function ($role) {
    $role_name = (string)($role['role_name'] ?? '');
    return strcasecmp($role_name, 'Admin') !== 0
        && strcasecmp($role_name, 'Client') !== 0;
}));
$role_management_roles = $staff_assignable_roles;

$active_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : ($role_management_roles[0]['role_id'] ?? null);
$active_role = null;
foreach ($role_management_roles as $r) {
    if ($r['role_id'] == $active_role_id) {
        $active_role = $r;
        break;
    }
}
if ($active_role === null && !empty($role_management_roles)) {
    $active_role = $role_management_roles[0];
    $active_role_id = (int)$active_role['role_id'];
}

// 1.5 Fetch Staff/Employees
$staff_select_fields = 'u.user_id, u.role_id, u.email, u.status, u.last_login, e.first_name, e.last_name, r.role_name';
if ($users_has_can_manage_billing) {
    $staff_select_fields .= ', u.can_manage_billing';
}
$staff_stmt = $pdo->prepare('
    SELECT ' . $staff_select_fields . '
    FROM users u 
    JOIN employees e ON u.user_id = e.user_id 
    JOIN user_roles r ON u.role_id = r.role_id 
    WHERE u.tenant_id = ? AND u.user_type = ?
    ORDER BY e.created_at DESC
');
$staff_stmt->execute([$tenant_id, 'Employee']);
$staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
if ($users_has_can_manage_billing) {
    foreach ($staff_list as &$staff_row) {
        $staff_row['can_manage_billing'] = !empty($staff_row['can_manage_billing']);
    }
} else {
    $staff_billing_access_map = mf_get_billing_access_map($pdo, (string)$tenant_id, array_column($staff_list, 'user_id'));
    foreach ($staff_list as &$staff_row) {
        $staff_row['can_manage_billing'] = !empty($staff_billing_access_map[(int)$staff_row['user_id']]);
    }
}
unset($staff_row);

// 2. Fetch Global Permissions
try {
    $pdo->prepare("INSERT INTO permissions (permission_code, module, description) VALUES ('EDIT_BILLING', 'System', 'Can edit billing and subscription settings') ON DUPLICATE KEY UPDATE module = VALUES(module), description = VALUES(description)")->execute();
} catch (Exception $e) {
    // Permission seed is best-effort and should not block the page.
}

$perm_stmt = $pdo->query('SELECT * FROM permissions ORDER BY module ASC, permission_code ASC');
$all_permissions = $perm_stmt->fetchAll(PDO::FETCH_ASSOC);

$permission_description_map = [
    'VIEW_USERS' => 'View user and employee accounts',
    'CREATE_USERS' => 'Create and invite employee accounts',
    'MANAGE_ROLES' => 'Create roles and assign permissions',
    'VIEW_CLIENTS' => 'View client profiles and records',
    'CREATE_CLIENTS' => 'Register and onboard new clients',
    'VIEW_LOANS' => 'View loan applications and active loans',
    'CREATE_LOANS' => 'Create and draft loan applications',
    'APPROVE_LOANS' => 'Approve or reject loan applications',
    'PROCESS_PAYMENTS' => 'Post and process loan payments',
    'VIEW_REPORTS' => 'View and generate business reports',
    'VIEW_APPLICATIONS' => 'View submitted applications',
    'MANAGE_APPLICATIONS' => 'Review and manage application workflow',
    'EDIT_BILLING' => 'Edit subscription plan, billing, and payment settings'
];

$permission_capability_map = [
    'VIEW_USERS' => 'Members can open the users list and view account details for employees and staff.',
    'CREATE_USERS' => 'Members can add new employee accounts and send account invitations.',
    'MANAGE_ROLES' => 'Members can create custom roles and update role permission assignments.',
    'VIEW_CLIENTS' => 'Members can access client profiles, records, and client-related details.',
    'CREATE_CLIENTS' => 'Members can register new clients and complete onboarding entries.',
    'VIEW_LOANS' => 'Members can see loan applications, loan records, and current loan statuses.',
    'CREATE_LOANS' => 'Members can draft and submit new loan applications.',
    'APPROVE_LOANS' => 'Members can approve, reject, and finalize loan application decisions.',
    'PROCESS_PAYMENTS' => 'Members can post and process borrower payment transactions.',
    'VIEW_REPORTS' => 'Members can access and generate business and financial reports.',
    'VIEW_APPLICATIONS' => 'Members can view incoming and existing application entries.',
    'MANAGE_APPLICATIONS' => 'Members can move applications through review and processing workflows.',
    'EDIT_BILLING' => 'Members can change subscription plan settings, billing options, and payment settings.'
];

foreach ($all_permissions as &$permission_row) {
    $perm_code = $permission_row['permission_code'] ?? '';
    if (isset($permission_description_map[$perm_code])) {
        $permission_row['description'] = $permission_description_map[$perm_code];
    }
}
unset($permission_row);

// 3. Prepare Active Codes for ALL Roles
$active_codes_by_role = [];
$active_stmt = $pdo->query('SELECT rp.role_id, p.permission_code FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.permission_id');
while ($row = $active_stmt->fetch(PDO::FETCH_ASSOC)) {
    $r_id = $row['role_id'];
    if (!isset($active_codes_by_role[$r_id])) {
        $active_codes_by_role[$r_id] = [];
    }
    $active_codes_by_role[$r_id][] = $row['permission_code'];
}

// 4. Group Permissions for UI Output
$grouped_permissions = [];
$visible_permission_codes = [];
foreach ($all_permissions as $p) {
    $mod = $p['module'];
    $code = $p['permission_code'] ?? '';
    
    // Explicitly hide the "Roles" module so it cannot be toggled by admins for custom roles
    if ($mod === 'Roles' || $code === 'EDIT_BILLING') {
        continue;
    }
    
    if (!isset($grouped_permissions[$mod])) {
        $grouped_permissions[$mod] = [];
    }
    $grouped_permissions[$mod][] = $p;
    $visible_permission_codes[] = $code;
}

$visible_permission_codes = array_values(array_unique(array_filter($visible_permission_codes)));
$visible_permission_lookup = array_fill_keys($visible_permission_codes, true);
$total_visible_permissions = count($visible_permission_codes);
$role_permission_totals = [];

foreach ($role_management_roles as $role_meta) {
    $role_id = (int)($role_meta['role_id'] ?? 0);
    $active_codes = array_unique($active_codes_by_role[$role_id] ?? []);
    $visible_active_count = 0;

    foreach ($active_codes as $active_code) {
        if (isset($visible_permission_lookup[$active_code])) {
            $visible_active_count++;
        }
    }

    $role_permission_totals[$role_id] = $visible_active_count;
}

$settings = $default_settings;
$toggles = $default_toggles;

// Get direct columns from tenants + branding
$tenant_settings_stmt = $pdo->prepare('SELECT t.tenant_name as company_name, b.theme_primary_color as primary_color, b.theme_secondary_color as secondary_color, b.theme_text_main as text_main, b.theme_text_muted as text_muted, b.theme_bg_body as bg_body, b.theme_bg_card as bg_card, b.theme_border_color as border_color, b.card_border_width, b.card_shadow, b.font_family, b.logo_path FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_id = ?');
$tenant_settings_stmt->execute([$tenant_id]);
if ($t = $tenant_settings_stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings = array_merge($settings, array_filter($t, function($v) { return $v !== null && $v !== ''; }));
}

$support_settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = ? AND setting_key IN ('support_email', 'support_phone')");
$support_settings_stmt->execute([$tenant_id]);
foreach ($support_settings_stmt->fetchAll(PDO::FETCH_ASSOC) as $support_row) {
    $key = (string)($support_row['setting_key'] ?? '');
    $value = trim((string)($support_row['setting_value'] ?? ''));
    if ($key !== '' && array_key_exists($key, $settings) && $value !== '') {
        $settings[$key] = $value;
    }
}

$toggles_stmt = $pdo->prepare('SELECT toggle_key, is_enabled FROM tenant_feature_toggles WHERE tenant_id = ?');
$toggles_stmt->execute([$tenant_id]);
foreach ($toggles_stmt->fetchAll() as $row) {
    $toggles[$row['toggle_key']] = (int) $row['is_enabled'];
}

// ── Loan Products Data ──
$loan_products_stmt = $pdo->prepare('SELECT * FROM loan_products WHERE tenant_id = ? ORDER BY is_active DESC, updated_at DESC, product_id DESC');
$loan_products_stmt->execute([$tenant_id]);
$loan_product_records = $loan_products_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$selected_loan_product_id = (int)($_GET['loan_product_id'] ?? 0);
$existing_product = [];
if ($selected_loan_product_id > 0) {
    foreach ($loan_product_records as $loan_product_record) {
        if ((int)($loan_product_record['product_id'] ?? 0) === $selected_loan_product_id) {
            $existing_product = $loan_product_record;
            break;
        }
    }
}
$loan_products_mode = (string)($_GET['loan_product_mode'] ?? '');
if ($loan_products_mode === 'new') {
    $existing_product = [];
    $selected_loan_product_id = 0;
}
$loan_products_form_open = $loan_products_mode === 'new' || $selected_loan_product_id > 0;
$loan_product_type_options = ['Personal Loan', 'Business Loan', 'Emergency Loan'];

$lp_form = [
    'product_name' => $existing_product['product_name'] ?? '',
    'product_type' => $existing_product['product_type'] ?? 'Personal Loan',
    'custom_product_type' => '',
    'description' => $existing_product['description'] ?? '',
    'min_amount' => $existing_product['min_amount'] ?? '5000',
    'max_amount' => $existing_product['max_amount'] ?? '100000',
    'interest_rate' => $existing_product['interest_rate'] ?? '3.00',
    'interest_type' => $existing_product['interest_type'] ?? 'Diminishing',
    'min_term_months' => $existing_product['min_term_months'] ?? '3',
    'max_term_months' => $existing_product['max_term_months'] ?? '24',
    'processing_fee_percentage' => $existing_product['processing_fee_percentage'] ?? '2.00',
    'service_charge' => $existing_product['service_charge'] ?? '0.00',
    'documentary_stamp' => $existing_product['documentary_stamp'] ?? '0.00',
    'insurance_fee_percentage' => $existing_product['insurance_fee_percentage'] ?? '0.00',
    'penalty_rate' => $existing_product['penalty_rate'] ?? '0.50',
    'penalty_type' => $existing_product['penalty_type'] ?? 'Daily',
    'grace_period_days' => $existing_product['grace_period_days'] ?? '3',
];
$loan_products_session_form = $_SESSION['loan_products_form'] ?? null;
unset($_SESSION['loan_products_form']);
if (is_array($loan_products_session_form)) {
    $lp_form = array_merge($lp_form, $loan_products_session_form);
}
$lp_form_product_type = trim((string)($lp_form['product_type'] ?? ''));
if ($lp_form_product_type !== '' && !in_array($lp_form_product_type, $loan_product_type_options, true) && $lp_form_product_type !== 'Others') {
    $lp_form['custom_product_type'] = $lp_form_product_type;
    $lp_form['product_type'] = 'Others';
} elseif (($lp_form['product_type'] ?? '') !== 'Others') {
    $lp_form['custom_product_type'] = trim((string)($lp_form['custom_product_type'] ?? ''));
}

// ── Credit Settings Data ──
// TODO: Keep the current storage as-is for now, but split the Credit Assessment UI later into:
// 1. Scoring Model
// 2. Limit Rules
// 3. Preview / Simulator
// 4. Presets
// This should stay UI-only first and avoid introducing new database changes unless truly needed.
$credit_policy = mf_get_tenant_credit_policy($pdo, $tenant_id);
$credit_policy_defaults = mf_credit_policy_defaults();
$credit_policy_score_ceiling = mf_credit_policy_score_ceiling();
$credit_policy_employment_options = mf_credit_policy_employment_options();
$credit_policy_ci_options = mf_credit_policy_ci_recommendation_options();
$credit_policy_ci_configurable_options = array_values(array_filter(
    $credit_policy_ci_options,
    static fn($option) => $option !== 'Not Recommended'
));
$credit_policy_not_recommended_from = (int)($credit_policy['score_thresholds']['not_recommended_min_score'] ?? 200);
$credit_policy_conditional_from = (int)($credit_policy['score_thresholds']['conditional_min_score'] ?? ($credit_policy['decision_routing']['manual_review_from_score'] ?? 400));
$credit_policy_recommended_from = (int)($credit_policy['score_thresholds']['recommended_min_score'] ?? ($credit_policy['decision_routing']['approval_candidate_from_score'] ?? 600));
$credit_policy_highly_recommended_from = (int)($credit_policy['score_thresholds']['highly_recommended_min_score'] ?? 800);
$credit_policy_at_risk_end = max(0, $credit_policy_not_recommended_from - 1);
$credit_policy_not_recommended_end = max($credit_policy_not_recommended_from, $credit_policy_conditional_from - 1);
$credit_policy_conditional_end = max($credit_policy_conditional_from, $credit_policy_recommended_from - 1);
$credit_policy_recommended_end = max($credit_policy_recommended_from, $credit_policy_highly_recommended_from - 1);
$credit_policy_reject_below = $credit_policy_conditional_from;
$credit_policy_review_band_end = $credit_policy_conditional_end;
$credit_policy_approve_from = $credit_policy_recommended_from;
$credit_policy_auto_ci_values = array_values(array_filter(
    $credit_policy_ci_configurable_options,
    static fn($option) => in_array($option, (array)($credit_policy['ci_rules']['auto_approve_ci_values'] ?? []), true)
));
$credit_policy_review_ci_values = array_values(array_filter(
    $credit_policy_ci_configurable_options,
    static fn($option) => in_array($option, (array)($credit_policy['ci_rules']['review_ci_values'] ?? []), true)
));
$credit_policy_allowed_employment_values = (array)($credit_policy['eligibility']['allowed_employment_statuses'] ?? []);
$credit_policy_allowed_employment_count = count($credit_policy_allowed_employment_values);
$credit_policy_auto_ci_count = count($credit_policy_auto_ci_values);
$credit_policy_review_ci_count = count($credit_policy_review_ci_values);
$credit_policy_product_checks_enabled = (int)!empty($credit_policy['product_checks']['use_product_minimum_credit_score'])
    + (int)!empty($credit_policy['product_checks']['use_product_min_amount'])
    + (int)!empty($credit_policy['product_checks']['use_product_max_amount']);
$credit_policy_ci_required_above_amount = (float)($credit_policy['ci_rules']['ci_required_above_amount'] ?? 0);
$credit_policy_ci_mode_label = !empty($credit_policy['ci_rules']['require_ci'])
    ? 'Always required'
    : ($credit_policy_ci_required_above_amount > 0
        ? 'Required above ₱' . number_format($credit_policy_ci_required_above_amount, 0)
        : 'Optional');
$credit_policy_defaults_json = htmlspecialchars(
    (string)json_encode($credit_policy_defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);

// ── Website Editor Data ──
$ws_stmt = $pdo->prepare('SELECT * FROM tenant_website_content WHERE tenant_id = ?');
$ws_stmt->execute([$tenant_id]);
$ws = $ws_stmt->fetch(PDO::FETCH_ASSOC);
$website_record_exists = is_array($ws);
if (!$ws) {
    $ws = [
        'layout_template' => 'template1',
        'hero_title' => '', 'hero_subtitle' => '', 'hero_description' => '',
        'hero_cta_text' => 'Learn More', 'hero_cta_url' => '#about', 'hero_image_path' => '',
        'hero_badge_text' => '',
        'about_heading' => 'About Us', 'about_body' => '', 'about_image_path' => '',
        'services_heading' => 'Our Services', 'services_json' => '[]',
        'stats_json' => '[]', 'stats_heading' => '', 'stats_subheading' => '', 'stats_image_path' => '',
        'contact_address' => '', 'contact_phone' => '', 'contact_email' => '', 'contact_hours' => '',
        'footer_description' => '',
        'custom_css' => '', 'meta_description' => ''
    ];
}
if (($ws['layout_template'] ?? '') !== 'template1') {
    $ws['layout_template'] = 'template1';
}
$website_config = [
    'website_show_about' => '1', 'website_show_services' => '1',
    'website_show_contact' => '1', 'website_show_download' => '1',
    'website_show_stats' => '1', 'website_stats_auto' => '1', 'website_show_loan_calc' => '1',
    'website_show_partners' => '0', 'website_partners_json' => '[]',
    'website_download_title' => 'Download Our App',
    'website_download_description' => 'Get the app for faster loan tracking and updates.',
    'website_download_button_text' => 'Download App',
    'website_download_url' => ''
];
$ws_settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = ? AND setting_key IN ('website_show_about','website_show_services','website_show_contact','website_show_download','website_show_stats','website_stats_auto','website_show_loan_calc','website_show_partners','website_partners_json','website_download_title','website_download_description','website_download_button_text','website_download_url')");
$ws_settings_stmt->execute([$tenant_id]);
foreach ($ws_settings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists($row['setting_key'], $website_config)) {
        $website_config[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }
}
$ws_services = json_decode($ws['services_json'] ?? '[]', true) ?: [];
$ws_stats = json_decode($ws['stats_json'] ?? '[]', true) ?: [];
// Pad stats to 4 slots
while (count($ws_stats) < 4) { $ws_stats[] = ['value' => '', 'label' => '']; }
$e = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };
$tenant_slug = $_SESSION['tenant_slug'] ?? '';
$site_url = '../site.php?site=' . urlencode($tenant_slug);

$website_has_homepage_copy = $website_record_exists && (
    trim((string)($ws['hero_title'] ?? '')) !== ''
    || trim((string)($ws['hero_subtitle'] ?? '')) !== ''
    || trim((string)($ws['about_body'] ?? '')) !== ''
);
$website_has_services = !empty($ws_services);
$website_has_contact_details = $website_record_exists && (
    trim((string)($ws['contact_address'] ?? '')) !== ''
    || trim((string)($ws['contact_phone'] ?? '')) !== ''
    || trim((string)($ws['contact_email'] ?? '')) !== ''
);
$website_content_complete = $website_has_homepage_copy && $website_has_services && $website_has_contact_details;
$website_published = !empty($toggles['public_website_enabled']);
$website_step_description = !$website_content_complete
    ? 'Add homepage copy, services, and contact details for your public site.'
    : 'Your website content is ready. Publish it to make your public tenant page live.';
$website_step_action = !$website_content_complete
    ? 'Click here to finish website'
    : 'Click here to publish your site';
$website_step_href = 'admin.php?tab=website';
$workspace_setup_checklist = !$website_published ? [[
    'title' => 'Set up your public website',
    'description' => $website_step_description,
    'icon' => 'language',
    'complete' => false,
    'href' => $website_step_href,
    'action_label' => $website_step_action,
]] : [];
$workspace_setup_completed_items = 0;
$workspace_setup_pending_items = [];
foreach ($workspace_setup_checklist as $checklist_item) {
    if (!empty($checklist_item['complete'])) {
        $workspace_setup_completed_items++;
    } else {
        $workspace_setup_pending_items[] = $checklist_item;
    }
}
$workspace_setup_total_items = count($workspace_setup_checklist);
$workspace_setup_pending_count = count($workspace_setup_pending_items);
$workspace_setup_progress = $workspace_setup_total_items > 0
    ? (int)round(($workspace_setup_completed_items / $workspace_setup_total_items) * 100)
    : 100;
$workspace_setup_pending = $workspace_setup_completed_items < $workspace_setup_total_items;

$receipt_filters = admin_receipt_collect_filters($_GET);
$receipt_period_options = admin_receipt_period_options();
$receipt_month_options = admin_receipt_month_options();
$receipt_join_sql = ' FROM tenant_billing_invoices i ';
[$receipt_where_sql, $receipt_where_params] = admin_receipt_build_query_parts((string)$tenant_id, $receipt_filters);

$receipt_count_stmt = $pdo->prepare('SELECT COUNT(*)' . $receipt_join_sql . ' WHERE ' . $receipt_where_sql);
$receipt_count_stmt->execute($receipt_where_params);
$receipt_total_count = (int)$receipt_count_stmt->fetchColumn();

$receipt_limit = 100;
$receipt_list_stmt = $pdo->prepare(
    'SELECT i.*, DATE(COALESCE(i.paid_at, i.created_at)) AS transaction_date'
    . $receipt_join_sql
    . ' WHERE ' . $receipt_where_sql
    . ' ORDER BY COALESCE(i.paid_at, i.created_at) DESC, i.invoice_id DESC LIMIT ' . $receipt_limit
);
$receipt_list_stmt->execute($receipt_where_params);
$receipts = $receipt_list_stmt->fetchAll(PDO::FETCH_ASSOC);

$receipt_year_stmt = $pdo->prepare(
    'SELECT DISTINCT YEAR(COALESCE(i.paid_at, i.created_at)) AS receipt_year'
    . $receipt_join_sql
    . ' WHERE i.tenant_id = ? AND i.status = ?'
    . ' ORDER BY receipt_year DESC'
);
$receipt_year_stmt->execute([(string)$tenant_id, 'Paid']);
$receipt_year_options = array_values(array_filter(array_map(
    static fn($value) => trim((string)$value),
    $receipt_year_stmt->fetchAll(PDO::FETCH_COLUMN)
), static fn($value) => $value !== ''));

if (!empty($receipt_filters['receipt_year']) && !in_array($receipt_filters['receipt_year'], $receipt_year_options, true)) {
    array_unshift($receipt_year_options, $receipt_filters['receipt_year']);
}

$receipt_period_badge = 'All paid receipts';
if ($receipt_filters['receipt_period'] === 'month' && $receipt_filters['receipt_month'] !== '') {
    $receipt_period_badge = 'Filtered by ' . ($receipt_month_options[$receipt_filters['receipt_month']] ?? 'Selected month');
} elseif ($receipt_filters['receipt_period'] === 'year' && $receipt_filters['receipt_year'] !== '') {
    $receipt_period_badge = 'Filtered by ' . $receipt_filters['receipt_year'];
}

$receipt_reset_url = 'admin.php?tab=statements';
$receipt_export_query = admin_receipt_build_query_string($receipt_filters, ['export' => 'all']);
$receipt_export_url = 'generate_receipt.php' . ($receipt_export_query !== '' ? '?' . $receipt_export_query : '');
$receipt_has_filters = admin_receipt_has_filters($receipt_filters);
$receipt_showing_count = count($receipts);
$receipt_showing_label = $receipt_total_count > $receipt_limit
    ? 'Showing the latest ' . number_format($receipt_showing_count) . ' of ' . number_format($receipt_total_count) . ' matching paid receipts.'
    : 'Showing ' . number_format($receipt_showing_count) . ' paid receipt' . ($receipt_showing_count === 1 ? '' : 's') . '.';

$flash_message = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

// Helper to convert HEX to RGB for CSS rgba() values
function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return "$r, $g, $b";
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo htmlspecialchars($settings['company_name']); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($settings['font_family']); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Material Symbols -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="admin.css?v=<?php echo filemtime(__DIR__ . '/admin.css'); ?>">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($settings['primary_color']); ?>;
            --primary-rgb: <?php echo hexToRgb($settings['primary_color']); ?>;
            --sidebar-bg: <?php echo htmlspecialchars($settings['bg_card']); ?>;
            --text-main: <?php echo htmlspecialchars($settings['text_main']); ?>;
            --text-muted: <?php echo htmlspecialchars($settings['text_muted']); ?>;
            --bg-body: <?php echo htmlspecialchars($settings['bg_body']); ?>;
            --bg-card: <?php echo htmlspecialchars($settings['bg_card']); ?>;
            --font-family: '<?php echo htmlspecialchars($settings['font_family']); ?>', sans-serif;
        }

        html[data-theme="dark"] {
            --bg-body: #0b1220;
            --bg-card: #111827;
            --sidebar-bg: #111827;
            --text-main: #e5e7eb;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --sidebar-text: #cbd5e1;
            --sidebar-active-bg: rgba(var(--primary-rgb), 0.24);
        }
        
        <?php if (!empty($settings['logo_path'])): ?>
        .logo-circle {
            background-image: url('<?php echo htmlspecialchars($settings['logo_path']); ?>');
            background-size: cover;
            background-position: center;
        }
        .logo-circle span {
            display: none;
        }
        <?php endif; ?>

        /* ── Website Editor ── */
        .we-template-picker { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .we-template-option { cursor: pointer; }
        .we-template-option input[type="radio"] { display: none; }
        .we-template-card { border: 2px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 20px; text-align: center; transition: all 0.2s; }
        .we-template-option input:checked + .we-template-card { border-color: var(--primary-color); background: rgba(var(--primary-rgb), 0.04); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15); }
        .we-template-card:hover { border-color: var(--primary-color); }
        .we-template-option.is-disabled { cursor: not-allowed; }
        .we-template-option.is-disabled .we-template-card { opacity: 0.55; border-style: dashed; }
        .we-template-option.is-disabled .we-template-card:hover { border-color: var(--border-color, #e2e8f0); }
        .we-template-coming-soon { width: 100%; height: 100%; border: 1px dashed var(--border-color, #cbd5e1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; color: var(--text-muted); background: rgba(148, 163, 184, 0.08); }
        .we-template-card h4 { margin: 12px 0 4px; font-size: 0.95rem; font-weight: 600; }
        .we-template-card p { font-size: 0.8rem; color: var(--text-muted); }
        .we-template-thumb { width: 100%; height: 140px; border-radius: 8px; background: var(--bg-body); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .we-template-thumb svg { width: 90%; height: 90%; }

        .we-editor-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--border-color, #e2e8f0); margin-bottom: 24px; }
        .we-editor-tab { padding: 10px 20px; border: none; background: none; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s; font-family: inherit; }
        .we-editor-tab:hover { color: var(--text-main); }
        .we-editor-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); font-weight: 600; }
        .we-tab-content { display: none; }
        .we-tab-content.active { display: block; }

        .we-editor-card { background: var(--bg-card); border-radius: 12px; padding: 28px; border: 1px solid var(--border-color, #e2e8f0); margin-bottom: 20px; }
        .we-editor-card h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 4px; }
        .we-editor-card .we-card-desc { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 24px; }

        .we-form-group { margin-bottom: 20px; }
        .we-form-group label { display: block; font-size: 0.85rem; font-weight: 500; margin-bottom: 6px; }
        .we-form-group .we-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }
        .we-form-group .we-hint a { color: var(--primary-color); }
        .we-form-group .we-hint code { background: var(--bg-body); padding: 1px 5px; border-radius: 3px; font-size: 0.8rem; }
        .we-form-input, .we-form-textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; font-size: 0.9rem; font-family: var(--font-family); background: var(--bg-card); color: var(--text-main); transition: border-color 0.15s; }
        .we-form-input:focus, .we-form-textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }
        .we-form-textarea { resize: vertical; min-height: 100px; }
        .we-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .we-service-row { display: grid; grid-template-columns: 1fr 2fr 120px 40px; gap: 10px; align-items: start; margin-bottom: 12px; padding: 14px; border-radius: 8px; background: var(--bg-body); border: 1px solid var(--border-color, #e2e8f0); }
        .we-service-row .we-form-input, .we-service-row .we-form-textarea { font-size: 0.85rem; }
        .we-service-row .we-form-textarea { min-height: 60px; }
        .we-btn-remove { width: 36px; height: 36px; border: none; background: none; cursor: pointer; color: #ef4444; border-radius: 6px; display: flex; align-items: center; justify-content: center; }
        .we-btn-remove:hover { background: #fee2e2; }
        .we-btn-add { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px dashed var(--border-color, #cbd5e1); border-radius: 8px; background: none; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: var(--primary-color); font-family: inherit; margin-top: 8px; }
        .we-btn-add:hover { background: rgba(var(--primary-rgb), 0.04); border-color: var(--primary-color); }

        .we-section-nav { display: flex; gap: 8px; margin-bottom: 24px; }
        .we-section-nav .we-nav-link { display: flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; color: var(--text-muted); text-decoration: none; cursor: pointer; transition: all 0.15s; border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-card); }
        .we-section-nav .we-nav-link:hover { border-color: var(--primary-color); color: var(--text-main); }
        .we-section-nav .we-nav-link.active { background: rgba(var(--primary-rgb), 0.08); border-color: var(--primary-color); color: var(--primary-color); font-weight: 600; }
        .we-section-nav .we-nav-link .material-symbols-rounded { font-size: 20px; }
        .we-editor-section { display: none; }
        .we-editor-section.active { display: block; }
        .we-preview-frame { width: 100%; height: 600px; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; background: #fff; }
        .we-save-bar { margin-top: 24px; padding: 16px 0; display: flex; justify-content: flex-end; gap: 10px; }
        .we-save-bar .btn { display: inline-flex; align-items: center; gap: 6px; }

        .credit-policy-shell {
            display: grid;
            gap: 24px;
        }

        .credit-policy-hero {
            padding: 24px 28px;
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 20px;
            background:
                radial-gradient(circle at top right, rgba(var(--primary-rgb), 0.16), transparent 32%),
                linear-gradient(180deg, rgba(var(--primary-rgb), 0.06), rgba(var(--primary-rgb), 0.01)),
                var(--bg-card);
        }

        .credit-policy-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary-color);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .credit-policy-hero-copy {
            display: grid;
            gap: 0;
            max-width: 760px;
        }

        .credit-policy-header-preview {
            margin-top: 18px;
            max-width: 760px;
        }

        .credit-policy-header-note {
            max-width: 760px;
        }

        #credit_settings .credit-limit-preview-pane {
            width: min(100%, 360px);
        }

        .credit-policy-toolbar {
            display: grid;
            gap: 14px;
            justify-items: end;
        }

        .credit-policy-badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }

        .credit-policy-badges .receipt-filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 12px;
            border: 1px solid rgba(var(--primary-rgb), 0.14);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        html[data-theme="dark"] .credit-policy-badges .receipt-filter-pill {
            background: rgba(15, 23, 42, 0.84);
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.24);
        }

        .credit-policy-primary-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }

        .credit-policy-primary-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .credit-policy-form-card {
            position: relative;
            padding: 24px;
            border: 1px solid rgba(var(--primary-rgb), 0.14);
            border-radius: 24px;
            background:
                radial-gradient(circle at top right, rgba(var(--primary-rgb), 0.08), transparent 30%),
                linear-gradient(180deg, rgba(var(--primary-rgb), 0.04), rgba(var(--primary-rgb), 0.01)),
                var(--bg-card);
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
        }

        html[data-theme="dark"] .credit-policy-form-card {
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.28);
        }

        .credit-policy-glance-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .credit-policy-glance-card {
            display: grid;
            gap: 8px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(var(--primary-rgb), 0.16);
            background:
                linear-gradient(180deg, rgba(var(--primary-rgb), 0.08), rgba(var(--primary-rgb), 0.02)),
                var(--bg-card);
        }

        .credit-policy-glance-card span {
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .credit-policy-glance-card strong {
            color: var(--text-main);
            font-size: 1rem;
            line-height: 1.35;
        }

        .credit-policy-glance-card small {
            color: var(--text-muted);
            font-size: 0.79rem;
            line-height: 1.45;
        }

        .credit-policy-tab-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
            padding: 14px;
            border: 1px solid rgba(var(--primary-rgb), 0.16);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
        }

        html[data-theme="dark"] .credit-policy-tab-nav {
            background: rgba(17, 24, 39, 0.92);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.28);
        }

        .credit-policy-tab-btn {
            position: relative;
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            flex: 1 1 150px;
            min-width: 0;
            padding: 14px 16px 16px;
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 16px;
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.24);
        }

        .credit-policy-tab-btn small {
            color: var(--text-muted);
            font-size: 0.74rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .credit-policy-tab-btn .material-symbols-rounded {
            font-size: 18px;
        }

        .credit-policy-tab-btn::after {
            content: '';
            position: absolute;
            left: 16px;
            right: 16px;
            bottom: 9px;
            height: 3px;
            border-radius: 999px;
            background: transparent;
            transition: background 0.18s ease;
        }

        .credit-policy-tab-btn:hover,
        .credit-policy-tab-btn.is-active {
            border-color: rgba(var(--primary-rgb), 0.32);
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
        }

        .credit-policy-tab-btn:hover small,
        .credit-policy-tab-btn.is-active small {
            color: inherit;
        }

        .credit-policy-tab-btn.is-active::after {
            background: currentColor;
        }

        .credit-policy-tab-panels {
            display: grid;
            gap: 18px;
        }

        .credit-policy-tab-panel[hidden] {
            display: none !important;
        }

        .credit-policy-engine-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(var(--primary-rgb), 0.12);
        }

        .credit-policy-panel {
            padding: 22px;
            border: 1px solid rgba(var(--primary-rgb), 0.12);
            border-radius: 22px;
            background:
                linear-gradient(180deg, rgba(var(--primary-rgb), 0.03), transparent 28%),
                var(--bg-card);
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.06);
            scroll-margin-top: 110px;
        }

        .credit-policy-panel-headline {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 18px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(var(--primary-rgb), 0.1);
        }

        .credit-policy-head-main {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .credit-policy-head-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: rgba(var(--primary-rgb), 0.12);
            color: var(--primary-color);
            flex-shrink: 0;
        }

        .credit-policy-head-icon .material-symbols-rounded {
            font-size: 21px;
        }

        .credit-policy-panel-title {
            display: grid;
            gap: 4px;
        }

        .credit-policy-section-step {
            display: inline-block;
            margin-bottom: 4px;
            color: var(--primary-color);
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .credit-policy-section-meta {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .credit-policy-mini-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr);
            gap: 18px;
            align-items: start;
        }

        .credit-policy-field {
            display: grid;
            gap: 8px;
        }

        .credit-policy-form-card .form-control {
            min-height: 46px;
            border-radius: 12px;
        }

        .credit-policy-field-hint {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.79rem;
            line-height: 1.5;
        }

        .credit-policy-field-hint.is-warning {
            color: #b45309;
        }

        .credit-policy-form-card .form-control.is-warning {
            border-color: rgba(245, 158, 11, 0.55);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
        }

        .credit-policy-form-card .credit-engine-inline-grid,
        .credit-policy-form-card .credit-engine-inline-grid-tight {
            gap: 14px;
        }

        .credit-policy-threshold-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .credit-policy-threshold-card {
            display: grid;
            gap: 10px;
            padding: 16px 18px;
            border: 1px solid rgba(var(--primary-rgb), 0.14);
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(var(--primary-rgb), 0.04), rgba(var(--primary-rgb), 0.015));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
            min-width: 0;
        }

        .credit-policy-threshold-card .form-group {
            min-width: 0;
        }

        .credit-policy-threshold-support-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
            gap: 16px;
            align-items: start;
            margin-top: 16px;
        }

        .credit-policy-form-card .credit-toggle-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 14px;
            min-height: 100%;
            padding: 14px 15px;
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 16px;
            background: var(--bg-card);
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .credit-policy-form-card .credit-toggle-row:hover {
            border-color: rgba(var(--primary-rgb), 0.28);
            background: rgba(var(--primary-rgb), 0.04);
        }

        .credit-toggle-copy,
        .credit-policy-employment-row-copy,
        .credit-policy-ci-row-copy {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .credit-toggle-copy strong,
        .credit-policy-employment-row-copy strong,
        .credit-policy-ci-row-copy strong {
            margin: 0;
        }

        .credit-toggle-copy small,
        .credit-policy-employment-row-copy span,
        .credit-policy-ci-row-copy span {
            display: block;
            margin: 0;
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .credit-policy-form-card .credit-toggle-row input[type="checkbox"],
        .credit-policy-employment-row input[type="checkbox"],
        .credit-policy-ci-row input[type="checkbox"] {
            justify-self: end;
            align-self: start;
            margin: 2px 0 0;
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }

        .credit-policy-note-card {
            display: grid;
            gap: 10px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(var(--primary-rgb), 0.2);
            background: linear-gradient(180deg, rgba(var(--primary-rgb), 0.08), rgba(var(--primary-rgb), 0.03));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
        }

        .credit-policy-note-card strong {
            color: var(--text-main);
            font-size: 0.93rem;
        }

        .credit-policy-note-card ul {
            margin: 0;
            padding-left: 18px;
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.5;
            display: grid;
            gap: 6px;
        }

        .credit-policy-note-card ol {
            margin: 0;
            padding-left: 20px;
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.55;
            display: grid;
            gap: 8px;
        }

        .credit-policy-note-card li code {
            font-size: 0.78rem;
        }

        .credit-policy-overview-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .credit-policy-choice-block {
            display: grid;
            gap: 10px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px dashed rgba(var(--primary-rgb), 0.16);
        }

        .credit-policy-subhead {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .credit-policy-subhead strong {
            color: var(--text-main);
            font-size: 0.92rem;
        }

        .credit-policy-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .credit-policy-stat {
            padding: 14px;
            border-radius: 14px;
            border: 1px solid var(--border-color, #e2e8f0);
            background: var(--bg-card);
        }

        .credit-policy-stat span {
            display: block;
            margin-bottom: 6px;
            color: var(--text-muted);
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .credit-policy-stat strong {
            color: var(--text-main);
            font-size: 1rem;
            line-height: 1.45;
        }

        .credit-policy-action-bar {
            position: sticky;
            bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-top: 24px;
            padding: 16px 18px;
            border: 1px solid rgba(var(--primary-rgb), 0.2);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        html[data-theme="dark"] .credit-policy-action-bar {
            background: rgba(17, 24, 39, 0.95);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
        }

        .credit-policy-action-copy {
            display: grid;
            gap: 4px;
        }

        .credit-policy-action-copy strong {
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .credit-policy-action-copy span {
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .credit-policy-sticky-card {
            position: sticky;
            top: 20px;
            display: grid;
            gap: 16px;
            padding: 18px;
            border: 1px solid rgba(var(--primary-rgb), 0.14);
            border-radius: 24px;
            background:
                radial-gradient(circle at top left, rgba(var(--primary-rgb), 0.08), transparent 34%),
                linear-gradient(180deg, rgba(var(--primary-rgb), 0.04), rgba(var(--primary-rgb), 0.01)),
                var(--bg-card);
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
        }

        html[data-theme="dark"] .credit-policy-sticky-card {
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.28);
        }

        .credit-policy-preview-hero {
            display: grid;
            gap: 8px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(var(--primary-rgb), 0.18);
            background: linear-gradient(180deg, rgba(var(--primary-rgb), 0.12), rgba(var(--primary-rgb), 0.04));
        }

        .credit-policy-preview-label {
            color: var(--primary-color);
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .credit-policy-preview-hero strong {
            color: var(--text-main);
            font-size: 1.04rem;
            line-height: 1.4;
        }

        .credit-policy-preview-hero p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.83rem;
            line-height: 1.55;
        }

        .credit-policy-output-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .credit-policy-output-card {
            padding: 14px;
            border-radius: 14px;
            border: 1px solid var(--border-color, #e2e8f0);
            background: var(--bg-card);
        }

        .credit-policy-output-card span {
            display: block;
            margin-bottom: 6px;
            color: var(--text-muted);
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .credit-policy-output-card strong {
            color: var(--text-main);
            font-size: 0.98rem;
            line-height: 1.4;
        }

        .credit-policy-output-card.is-approve {
            border-color: rgba(34, 197, 94, 0.3);
            background: rgba(34, 197, 94, 0.08);
        }

        .credit-policy-output-card.is-approve strong {
            color: #15803d;
        }

        .credit-policy-output-card.is-review {
            border-color: rgba(245, 158, 11, 0.34);
            background: rgba(245, 158, 11, 0.08);
        }

        .credit-policy-output-card.is-review strong {
            color: #b45309;
        }

        .credit-policy-output-card.is-reject {
            border-color: rgba(239, 68, 68, 0.28);
            background: rgba(239, 68, 68, 0.08);
        }

        .credit-policy-output-card.is-reject strong {
            color: #b91c1c;
        }

        .credit-policy-note {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border-color, #e2e8f0);
            background: var(--bg-card);
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .credit-policy-note.is-warning {
            border-color: rgba(245, 158, 11, 0.35);
            background: rgba(245, 158, 11, 0.08);
            color: #b45309;
        }

        .credit-policy-note.is-good {
            border-color: rgba(34, 197, 94, 0.28);
            background: rgba(34, 197, 94, 0.08);
            color: #15803d;
        }

        .credit-policy-simulator-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .credit-policy-simulator-grid .form-group {
            margin-bottom: 0;
        }

        .credit-policy-simulator-shell {
            display: grid;
            gap: 14px;
        }

        .credit-policy-simulator-inputs {
            padding: 16px;
            border: 1px solid rgba(var(--primary-rgb), 0.14);
            border-radius: 18px;
            background:
                linear-gradient(180deg, rgba(var(--primary-rgb), 0.08), rgba(var(--primary-rgb), 0.02)),
                var(--bg-card);
        }

        .credit-policy-simulator-inputs .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-muted);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .credit-policy-simulator-hero {
            display: grid;
            gap: 6px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(var(--border-color-rgb, 148, 163, 184), 0.24);
            background: var(--bg-card);
        }

        .credit-policy-simulator-hero span {
            color: var(--text-muted);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .credit-policy-simulator-hero strong {
            color: var(--text-main);
            font-size: 1.28rem;
            line-height: 1.15;
        }

        .credit-policy-simulator-hero p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .credit-policy-simulator-hero.is-approve {
            border-color: rgba(34, 197, 94, 0.3);
            background: rgba(34, 197, 94, 0.08);
        }

        .credit-policy-simulator-hero.is-approve strong {
            color: #15803d;
        }

        .credit-policy-simulator-hero.is-review {
            border-color: rgba(245, 158, 11, 0.34);
            background: rgba(245, 158, 11, 0.08);
        }

        .credit-policy-simulator-hero.is-review strong {
            color: #b45309;
        }

        .credit-policy-simulator-hero.is-reject {
            border-color: rgba(239, 68, 68, 0.28);
            background: rgba(239, 68, 68, 0.08);
        }

        .credit-policy-simulator-hero.is-reject strong {
            color: #b91c1c;
        }

        .credit-policy-simulator-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .credit-policy-simulator-formula {
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px dashed rgba(var(--primary-rgb), 0.24);
            background: rgba(var(--primary-rgb), 0.04);
        }

        .credit-policy-simulator-formula span {
            display: block;
            margin-bottom: 6px;
            color: var(--text-muted);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .credit-policy-simulator-formula strong {
            color: var(--text-main);
            font-size: 0.93rem;
            line-height: 1.45;
        }

        .credit-policy-table-map {
            display: grid;
            gap: 10px;
        }

        .credit-policy-table-row {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border-color, #e2e8f0);
            background: var(--bg-card);
        }

        .credit-policy-table-row strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text-main);
            font-size: 0.87rem;
        }

        .credit-policy-table-row span {
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .credit-policy-static-rule {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-top: 16px;
            border-style: dashed;
            border-color: rgba(239, 68, 68, 0.26);
            background: rgba(239, 68, 68, 0.04);
        }

        .credit-policy-static-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
            font-size: 0.76rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .credit-policy-employment-list {
            margin-top: 16px;
        }

        .credit-policy-ci-columns {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }

        .credit-policy-ci-column {
            display: grid;
            gap: 10px;
            min-width: 0;
        }

        .credit-policy-employment-row,
        .credit-policy-ci-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 14px;
            cursor: pointer;
        }

        .credit-policy-employment-row-copy,
        .credit-policy-ci-row-copy {
            flex: 1 1 auto;
        }

        #credit-policy-eligibility .credit-policy-employment-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 12px;
            margin-top: 12px;
        }

        #credit-policy-eligibility .credit-policy-employment-row,
        #credit-policy-eligibility .credit-toggle-row {
            gap: 12px;
            padding: 10px 12px;
            border-radius: 14px;
        }

        #credit-policy-eligibility .credit-policy-employment-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            width: 100%;
            min-height: 50px;
        }

        #credit-policy-eligibility .credit-policy-employment-row-copy,
        #credit-policy-eligibility .credit-toggle-copy {
            gap: 2px;
        }

        #credit-policy-eligibility .credit-policy-employment-row-copy {
            display: block;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #credit-policy-eligibility .credit-policy-employment-row-copy strong,
        #credit-policy-eligibility .credit-toggle-copy strong {
            font-size: 0.84rem;
            line-height: 1.3;
        }

        #credit-policy-eligibility .credit-policy-employment-row-copy strong {
            display: inline;
            margin: 0;
        }

        #credit-policy-eligibility .credit-policy-employment-row-copy span,
        #credit-policy-eligibility .credit-toggle-copy small {
            font-size: 0.74rem;
            line-height: 1.25;
        }

        #credit-policy-eligibility .credit-policy-employment-row-copy span {
            display: inline;
        }

        #credit-policy-eligibility .credit-policy-employment-row-copy span::before {
            content: "-";
            margin-right: 6px;
            color: var(--text-muted);
        }

        #credit-policy-eligibility .credit-policy-employment-row input[type="checkbox"],
        #credit-policy-eligibility .credit-toggle-row input[type="checkbox"] {
            margin-top: 0;
        }

        #credit-policy-eligibility .credit-policy-employment-row input[type="checkbox"] {
            justify-self: start;
            align-self: center;
        }

        @media (max-width: 1100px) {
            .credit-policy-glance-grid,
            .credit-policy-ci-columns,
            .credit-policy-mini-grid,
            .credit-policy-overview-grid,
            .credit-policy-output-grid,
            .credit-policy-simulator-grid,
            .credit-policy-simulator-metrics,
            .credit-policy-threshold-grid,
            .credit-policy-threshold-support-grid {
                grid-template-columns: 1fr;
            }

            #credit-policy-eligibility .credit-policy-employment-list {
                grid-template-columns: 1fr;
            }

            .credit-policy-toolbar {
                justify-items: start;
            }

            .credit-policy-primary-actions {
                justify-content: flex-start;
            }

            .credit-policy-action-bar {
                position: static;
                flex-direction: column;
                align-items: stretch;
            }

            .credit-policy-employment-row,
            .credit-policy-ci-row {
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: start;
            }
        }

        @media (max-width: 720px) {
            .credit-policy-hero,
            .credit-policy-form-card {
                padding: 18px;
            }

            .credit-policy-engine-header,
            .credit-policy-tab-nav {
                flex-direction: column;
                align-items: stretch;
                overflow-x: auto;
                padding: 10px;
            }

            .credit-policy-tab-btn {
                white-space: normal;
            }

            .credit-policy-head-main,
            .credit-policy-panel-headline,
            .credit-policy-subhead {
                flex-direction: column;
                align-items: stretch;
            }

            .credit-policy-stat-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .we-template-picker { grid-template-columns: 1fr; }
            .we-form-row { grid-template-columns: 1fr; }
            .we-service-row { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body class="<?php echo $loan_products_form_open ? 'loan-products-modal-open' : ''; ?>">

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="background-color: transparent;">
                <div class="logo-circle">
                    <span class="material-symbols-rounded">diamond</span>
                </div>
                <h2 class="company-name-display"><?php echo htmlspecialchars($settings['company_name']); ?></h2>
            </div>

            <?php
            $active_view = 'dashboard';
            if (isset($_GET['tab'])) {
                if (in_array($_GET['tab'], ['staff-list', 'roles-list'])) {
                    $active_view = 'staff';
                } elseif ($_GET['tab'] === 'billing') {
                    $active_view = 'billing';
                } elseif ($_GET['tab'] === 'payment_info') {
                    $active_view = 'payment_info';
                } elseif ($_GET['tab'] === 'statements') {
                    $active_view = 'statements';
                } elseif ($_GET['tab'] === 'website') {
                    $active_view = 'website';
                } elseif ($_GET['tab'] === 'settings') {
                    $active_view = 'settings';
                } elseif ($_GET['tab'] === 'personal') {
                    $active_view = 'personal';
                } elseif ($_GET['tab'] === 'loan_products') {
                    $active_view = 'loan_products';
                } elseif ($_GET['tab'] === 'credit_settings') {
                    $active_view = 'credit_settings';
                }
            }
            if (!$can_manage_billing && in_array((string)($_GET['tab'] ?? ''), ['billing', 'payment_info', 'statements'], true)) {
                $active_view = 'dashboard';
            }
            $page_titles = [
                'dashboard' => 'Dashboard',
                'staff' => 'Staff & Roles',
                'website' => 'Website Editor',
                'settings' => 'Branding',
                'personal' => 'Personal Profile',
                'billing' => 'Plan & Billing',
                'payment_info' => 'Payment Info',
                'statements' => 'Receipts',
                'loan_products' => 'Loan Products Settings',
                'credit_settings' => 'Credit Policy'
            ];
            $page_title = $page_titles[$active_view] ?? 'Dashboard';
            ?>
            <nav class="sidebar-nav">
                <span class="sidebar-section-title">Overview</span>
                <a href="admin.php" class="nav-item <?php echo $active_view === 'dashboard' ? 'active' : ''; ?>" data-target="dashboard" data-title="Dashboard">
                    <span class="material-symbols-rounded">dashboard</span>
                    <span>Dashboard</span>
                </a>

                <span class="sidebar-section-title">User Management</span>
                <a href="admin.php?tab=staff-list" class="nav-item <?php echo $active_view === 'staff' ? 'active' : ''; ?>" data-target="staff" data-title="Staff Accounts">
                    <span class="material-symbols-rounded">groups</span>
                    <span>Staff Accounts</span>
                </a>

                <span class="sidebar-section-title">Workspace</span>
                <a href="admin.php?tab=loan_products" class="nav-item <?php echo $active_view === 'loan_products' ? 'active' : ''; ?>" data-target="loan_products" data-title="Loan Products">
                    <span class="material-symbols-rounded">payments</span>
                    <span>Loan Products</span>
                </a>
                <a href="admin.php?tab=credit_settings" class="nav-item <?php echo $active_view === 'credit_settings' ? 'active' : ''; ?>" data-target="credit_settings" data-title="Credit Policy">
                    <span class="material-symbols-rounded">speed</span>
                    <span>Credit Policy</span>
                </a>
                <a href="admin.php?tab=website" class="nav-item <?php echo $active_view === 'website' ? 'active' : ''; ?>" data-target="website" data-title="Website Editor">
                    <span class="material-symbols-rounded">language</span>
                    <span>Website</span>
                </a>
                <a href="admin.php?tab=settings" class="nav-item <?php echo $active_view === 'settings' ? 'active' : ''; ?>" data-target="settings" data-title="Branding">
                    <span class="material-symbols-rounded">palette</span>
                    <span>Branding</span>
                </a>

                <?php if ($can_manage_billing): ?>
                <span class="sidebar-section-title">Billing & Subscription</span>
                <a href="admin.php?tab=billing" class="nav-item <?php echo $active_view === 'billing' ? 'active' : ''; ?>" data-target="billing" data-title="Billing &amp; Subscription">
                    <span class="material-symbols-rounded">receipt_long</span>
                    <span>Plan & Billing</span>
                </a>
                <a href="admin.php?tab=payment_info" class="nav-item <?php echo $active_view === 'payment_info' ? 'active' : ''; ?>" data-target="payment_info" data-title="Payment Info">
                    <span class="material-symbols-rounded">credit_card</span>
                    <span>Payment Info</span>
                </a>
                <a href="admin.php?tab=statements" class="nav-item <?php echo $active_view === 'statements' ? 'active' : ''; ?>" data-target="statements" data-title="Receipts">
                    <span class="material-symbols-rounded">account_balance_wallet</span>
                    <span>Receipts</span>
                </a>
                <?php endif; ?>


                <span class="sidebar-section-title">Account</span>
                <a href="admin.php?tab=personal" class="nav-item <?php echo $active_view === 'personal' ? 'active' : ''; ?>" data-target="personal" data-title="Personal Profile">
                    <span class="material-symbols-rounded">person</span>
                    <span>Personal Profile</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="../tenant_login/logout.php" class="nav-item">
                    <span class="material-symbols-rounded">logout</span>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="top-header">
                <div class="header-left">
                    <h1 id="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
                <div class="header-right">
                    <button id="theme-toggle" class="icon-btn" title="Toggle Light/Dark Mode">
                        <span class="material-symbols-rounded"><?php echo $ui_theme === 'dark' ? 'light_mode' : 'dark_mode'; ?></span>
                    </button>
                    <div class="admin-profile" style="cursor:pointer;" onclick="window.location.href='admin.php?tab=personal';" title="Manage Profile">
                        <img src="https://ui-avatars.com/api/?name=Super+Admin&background=random" alt="Admin Avatar"
                            class="avatar">
                        <div class="admin-info">
                            <span class="admin-name"><?php echo htmlspecialchars($settings['company_name']); ?></span>
                            <span class="admin-role"><?php echo htmlspecialchars($role_name); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <?php if ($flash_message !== ''): ?>
            <div class="site-alert site-alert-success" style="margin: 1rem 2rem 0; padding: 0.75rem 1rem; border-radius: 8px; background: #dcfce7; color: #166534; font-weight: 500;">
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_error'])): ?>
            <div class="site-alert site-alert-error" style="margin: 1rem 2rem 0; padding: 0.75rem 1rem; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-weight: 500;">
                <?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?>
            </div>
            <?php endif; ?>

            <!-- Views Container -->
            <div class="views-container">

                <!-- Dashboard View -->
                <section id="dashboard" class="view-section <?php echo $active_view === 'dashboard' ? 'active' : ''; ?>">
                    <div class="dashboard-hero dashboard-hero-<?php echo htmlspecialchars($dashboard_health_tone); ?>">
                        <div class="dashboard-hero-copy">
                            <span class="dashboard-eyebrow">Workspace Overview</span>
                            <h2><?php echo htmlspecialchars($settings['company_name']); ?> at a glance</h2>
                            <p>Track daily operations, borrower activity, staff capacity, and collection momentum from one place.</p>
                            <div class="dashboard-pill-row">
                                <span class="dashboard-pill">Plan: <?php echo htmlspecialchars($dashboard_plan_name); ?></span>
                                <span class="dashboard-pill">Monthly Payment: &#8369;<?php echo number_format($dashboard_plan_price, 2); ?></span>
                                <span class="dashboard-pill">Active Clients: <?php echo number_format($dashboard_active_clients); ?></span>
                                <span class="dashboard-pill">Alerts: <?php echo number_format($dashboard_system_alerts + $dashboard_overdue_loans); ?></span>
                            </div>
                        </div>
                        <div class="dashboard-hero-status">
                            <span class="dashboard-health-label"><?php echo htmlspecialchars($dashboard_health_title); ?></span>
                            <p><?php echo htmlspecialchars($dashboard_health_detail); ?></p>
                            <div class="dashboard-health-meta">
                                <span><strong><?php echo number_format($dashboard_pending_applications); ?></strong> pending applications</span>
                                <span><strong><?php echo number_format($dashboard_overdue_loans); ?></strong> overdue loans</span>
                            </div>
                        </div>
                    </div>

                    <div class="stats-grid dashboard-stats-grid">
                        <div class="stat-card stat-card-compact">
                            <div class="stat-icon" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color);">
                                <span class="material-symbols-rounded">groups</span>
                            </div>
                            <div class="stat-details">
                                <p>Active Clients</p>
                                <h3><?php echo number_format($dashboard_active_clients); ?></h3>
                                <small><?php echo number_format($dashboard_total_clients); ?> total records</small>
                            </div>
                        </div>
                        <div class="stat-card stat-card-compact">
                            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.12); color: #2563eb;">
                                <span class="material-symbols-rounded">assignment</span>
                            </div>
                            <div class="stat-details">
                                <p>Pending Applications</p>
                                <h3><?php echo number_format($dashboard_pending_applications); ?></h3>
                                <small>Awaiting review or decision</small>
                            </div>
                        </div>
                        <div class="stat-card stat-card-compact">
                            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">
                                <span class="material-symbols-rounded">payments</span>
                            </div>
                            <div class="stat-details">
                                <p>Today's Collections</p>
                                <h3>&#8369;<?php echo number_format($dashboard_todays_collections, 2); ?></h3>
                                <small>Posted collections today</small>
                            </div>
                        </div>
                        <div class="stat-card stat-card-compact">
                            <div class="stat-icon" style="background: rgba(139, 92, 246, 0.12); color: #7c3aed;">
                                <span class="material-symbols-rounded">badge</span>
                            </div>
                            <div class="stat-details">
                                <p>Active Staff</p>
                                <h3><?php echo number_format($dashboard_active_staff); ?></h3>
                                <small><?php echo $dashboard_staff_limit > 0 ? number_format($dashboard_staff_utilization) . '% of plan capacity' : 'No staff cap'; ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-panel-grid">
                        <div class="card dashboard-panel">
                            <div class="card-header-flex">
                                <div>
                                    <h3>Capacity Snapshot</h3>
                                    <p class="text-muted">See how close your workspace is to plan limits and operating thresholds.</p>
                                </div>
                                <span class="badge <?php echo $dashboard_utilization_peak >= 90 ? 'badge-yellow' : 'badge-green'; ?>">
                                    <?php echo $dashboard_utilization_peak >= 90 ? 'Near Limit' : 'Healthy'; ?>
                                </span>
                            </div>
                            <div class="dashboard-capacity-list">
                                <div class="dashboard-capacity-item">
                                    <div class="dashboard-capacity-head">
                                        <span>Client Capacity</span>
                                        <strong><?php echo number_format($dashboard_active_clients); ?> / <?php echo $dashboard_client_limit > 0 ? number_format($dashboard_client_limit) : 'Unlimited'; ?></strong>
                                    </div>
                                    <div class="dashboard-progress-track">
                                        <div class="dashboard-progress-fill" style="width: <?php echo $dashboard_client_limit > 0 ? $dashboard_client_utilization : 8; ?>%;"></div>
                                    </div>
                                </div>
                                <div class="dashboard-capacity-item">
                                    <div class="dashboard-capacity-head">
                                        <span>Staff Capacity</span>
                                        <strong><?php echo number_format($dashboard_active_staff); ?> / <?php echo $dashboard_staff_limit > 0 ? number_format($dashboard_staff_limit) : 'Unlimited'; ?></strong>
                                    </div>
                                    <div class="dashboard-progress-track">
                                        <div class="dashboard-progress-fill dashboard-progress-fill-green" style="width: <?php echo $dashboard_staff_limit > 0 ? $dashboard_staff_utilization : 8; ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="dashboard-meta-grid">
                                <div class="dashboard-meta-card">
                                    <span class="dashboard-meta-label">Loan Portfolio</span>
                                    <strong><?php echo number_format($dashboard_active_loans); ?> active loans</strong>
                                    <small>&#8369;<?php echo number_format($dashboard_total_portfolio, 2); ?> remaining balance</small>
                                </div>
                                <div class="dashboard-meta-card">
                                    <span class="dashboard-meta-label">Risk Watch</span>
                                    <strong><?php echo number_format($dashboard_overdue_loans); ?> overdue loans</strong>
                                    <small><?php echo number_format($dashboard_system_alerts); ?> user account alerts</small>
                                </div>
                                <div class="dashboard-meta-card">
                                    <span class="dashboard-meta-label">Workspace Plan</span>
                                    <strong><?php echo htmlspecialchars($dashboard_plan_name); ?></strong>
                                    <small>&#8369;<?php echo number_format($dashboard_plan_price, 2); ?> monthly payment</small>
                                </div>
                                <div class="dashboard-meta-card">
                                    <span class="dashboard-meta-label">Support Email</span>
                                    <strong><?php echo htmlspecialchars(($settings['support_email'] ?? '') !== '' ? $settings['support_email'] : 'Not configured'); ?></strong>
                                    <small><?php echo htmlspecialchars(($settings['support_phone'] ?? '') !== '' ? $settings['support_phone'] : 'No support phone set'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="card dashboard-panel">
                            <div class="card-header-flex">
                                <div>
                                    <h3>Quick Actions</h3>
                                    <p class="text-muted">Jump straight into the settings and admin tasks that matter most.</p>
                                </div>
                            </div>
                            <div class="dashboard-action-grid">
                                <a href="admin.php?tab=staff-list" class="dashboard-action-card">
                                    <span class="material-symbols-rounded">manage_accounts</span>
                                    <div>
                                        <strong>Manage Staff</strong>
                                        <small>Create accounts and update roles.</small>
                                    </div>
                                </a>
                                <a href="admin.php?tab=loan_products" class="dashboard-action-card">
                                    <span class="material-symbols-rounded">payments</span>
                                    <div>
                                        <strong>Loan Products</strong>
                                        <small>Adjust lending options and charges.</small>
                                    </div>
                                </a>
                                <a href="admin.php?tab=credit_settings" class="dashboard-action-card">
                                    <span class="material-symbols-rounded">speed</span>
                                    <div>
                                        <strong>Credit Policy</strong>
                                        <small>Control approval rules, investigation checks, and limit estimates.</small>
                                    </div>
                                </a>
                                <a href="admin.php?tab=website" class="dashboard-action-card">
                                    <span class="material-symbols-rounded">language</span>
                                    <div>
                                        <strong>Website Editor</strong>
                                        <small>Update your tenant-facing website.</small>
                                    </div>
                                </a>
                                <?php if ($can_manage_billing): ?>
                                <a href="admin.php?tab=billing" class="dashboard-action-card">
                                    <span class="material-symbols-rounded">receipt_long</span>
                                    <div>
                                        <strong>Plan & Billing</strong>
                                        <small>Review plan usage and payment details.</small>
                                    </div>
                                </a>
                                <?php endif; ?>
                                <a href="admin.php?tab=settings" class="dashboard-action-card">
                                    <span class="material-symbols-rounded">palette</span>
                                    <div>
                                        <strong>Branding</strong>
                                        <small>Keep company info and branding current.</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-panel-grid dashboard-panel-grid-secondary">
                        <div class="card dashboard-panel">
                            <div class="card-header-flex">
                                <div>
                                    <h3>Recent Staff Activity</h3>
                                    <p class="text-muted">Latest account access and staff-management changes.</p>
                                </div>
                            </div>
                            <ul class="activity-list">
                                <?php if (empty($staff_audit_logs)): ?>
                                <li>
                                    <div class="activity-icon" style="background: rgba(100, 116, 139, 0.1);"><span class="material-symbols-rounded">info</span></div>
                                    <div class="activity-text">
                                        <p>No recent staff activity recorded.</p>
                                    </div>
                                </li>
                                <?php else: ?>
                                    <?php foreach ($staff_audit_logs as $sal): ?>
                                    <li>
                                        <div class="activity-icon" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color);">
                                            <span class="material-symbols-rounded"><?php echo $sal['action_type'] === 'STAFF_LOGIN' ? 'login' : ($sal['action_type'] === 'STAFF_LOGOUT' ? 'logout' : 'manage_accounts'); ?></span>
                                        </div>
                                        <div class="activity-text">
                                            <p><?php echo htmlspecialchars($sal['description'] . ' (' . ($sal['actor_name'] ?? 'System') . ')'); ?></p>
                                            <span><?php echo date('M j, Y, g:i a', strtotime($sal['created_at'])); ?></span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="card dashboard-panel">
                            <div class="card-header-flex">
                                <div>
                                    <h3>Recent Audit Trail</h3>
                                    <p class="text-muted">A compact view of the latest tracked system actions.</p>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="admin-table dashboard-log-table">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>Actor</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($all_audit_logs)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                                No audit logs recorded yet.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach (array_slice($all_audit_logs, 0, 12) as $log): ?>
                                            <?php
                                                $logBadgeClass = 'badge-blue';
                                                if (stripos((string)$log['action_type'], 'DELETE') !== false || stripos((string)$log['action_type'], 'SUSPEND') !== false || stripos((string)$log['action_type'], 'CANCEL') !== false) {
                                                    $logBadgeClass = 'badge-red';
                                                } elseif (stripos((string)$log['action_type'], 'LOGIN') !== false || stripos((string)$log['action_type'], 'ADDED') !== false || stripos((string)$log['action_type'], 'UPDATED') !== false) {
                                                    $logBadgeClass = 'badge-green';
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo date('M j, Y, g:i a', strtotime($log['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></td>
                                                <td><span class="badge <?php echo $logBadgeClass; ?>"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if ($workspace_setup_pending && $active_view === 'dashboard'): ?>
                <aside class="dashboard-setup-alert" aria-live="polite">
                    <div class="dashboard-setup-alert-head">
                        <div>
                            <span class="dashboard-setup-alert-kicker">Setup Alert</span>
                            <h3>Finish your workspace</h3>
                            <p>Your subscription is active. Click any step below whenever you're ready.</p>
                        </div>
                        <div class="dashboard-setup-alert-controls">
                            <div class="dashboard-setup-alert-progress">
                                <strong><?php echo $workspace_setup_completed_items; ?>/<?php echo $workspace_setup_total_items; ?></strong>
                                <span><?php echo $workspace_setup_pending_count; ?> left</span>
                            </div>
                            <button
                                type="button"
                                class="dashboard-setup-alert-toggle"
                                data-setup-alert-toggle
                                aria-expanded="true"
                                title="Minimize setup alert"
                            >
                                <span class="material-symbols-rounded">remove</span>
                            </button>
                        </div>
                    </div>
                        <div class="dashboard-setup-alert-list">
                        <?php foreach ($workspace_setup_pending_items as $item): ?>
                        <div class="dashboard-setup-alert-item">
                            <div class="dashboard-setup-alert-icon">
                                <span class="material-symbols-rounded"><?php echo htmlspecialchars($item['icon']); ?></span>
                            </div>
                            <div class="dashboard-setup-alert-copy">
                                <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                <p><?php echo htmlspecialchars($item['description']); ?></p>
                                <a href="<?php echo htmlspecialchars((string)($item['href'] ?? 'admin.php?tab=website')); ?>" class="dashboard-setup-alert-link">
                                    <?php echo htmlspecialchars($item['action_label']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </aside>
                <?php endif; ?>

                <!-- Branding View -->
                <section id="settings" class="view-section <?php echo $active_view === 'settings' ? 'active' : ''; ?>">
                    <div class="section-intro">
                        <h2>Branding</h2>
                        <p class="text-muted">Adjust your workspace identity, colors, typography, and brand presentation across the admin and public experience.</p>
                    </div>
                    <div class="settings-panel">
                        <form id="settings-form" method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="save_settings">
                                <input type="hidden" name="existing_logo_path" id="existing-logo-path" value="<?php echo htmlspecialchars($settings['logo_path'] ?? ''); ?>">
                                <input type="hidden" id="hidden-toggle-booking" name="toggle_booking_system" value="1" <?php echo ((int) $toggles['booking_system'] === 1) ? '' : 'disabled'; ?>>
                                <input type="hidden" id="hidden-toggle-registration" name="toggle_user_registration" value="1" <?php echo ((int) $toggles['user_registration'] === 1) ? '' : 'disabled'; ?>>
                                <input type="hidden" id="hidden-toggle-maintenance" name="toggle_maintenance_mode" value="1" <?php echo ((int) $toggles['maintenance_mode'] === 1) ? '' : 'disabled'; ?>>
                                <input type="hidden" id="hidden-toggle-emails" name="toggle_email_notifications" value="1" <?php echo ((int) $toggles['email_notifications'] === 1) ? '' : 'disabled'; ?>>
                                <input type="hidden" id="hidden-toggle-website" name="toggle_public_website_enabled" value="1" <?php echo ((int) ($toggles['public_website_enabled'] ?? 0) === 1) ? '' : 'disabled'; ?>>

                            <div class="card">
                                <h3>Brand Identity</h3>
                                <p class="text-muted">Set the company name and logo that power your dashboard, staff portal, client app, and website branding.</p>
                                <div class="config-two-col">
                                    <div class="form-group">
                                        <label for="company-name">Company Name</label>
                                        <input type="text" id="company-name" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Logo Upload</label>
                                        <div class="config-logo-upload">
                                            <input type="file" class="form-control" id="logo_file" name="logo_file" accept=".png,.jpg,.jpeg,.webp,.svg">
                                            <span class="text-muted">Upload a new logo to replace the current one. PNG, JPG, WEBP, or SVG, up to 3MB.</span>
                                            <?php if (!empty($settings['logo_path'])): ?>
                                            <div class="config-current-logo">
                                                <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Current logo" class="config-current-logo-image">
                                                <div class="config-current-logo-copy">
                                                    <strong>Current logo already uploaded</strong>
                                                    <span>Upload a new file only if you want to replace the current logo.</span>
                                                    <a href="<?php echo htmlspecialchars($settings['logo_path']); ?>" target="_blank" rel="noopener">Open current logo</a>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" id="extract-palette-btn" class="btn-extract-palette" style="display:none;">
                                            <span class="material-symbols-rounded">palette</span>
                                            Match Colors from Logo
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header-flex">
                                    <div>
                                        <h3>Branding Studio</h3>
                                        <p class="text-muted">This mirrors the onboarding branding flow, but you can fine-tune it here any time without setup restrictions.</p>
                                    </div>
                                    <button type="button" id="sync-btn" class="sync-btn" title="Automatically adjusts text colors for readability">
                                        <span class="material-symbols-rounded">contrast</span>
                                        Smart Contrast Sync: Off
                                    </button>
                                </div>

                                <div class="config-studio-grid">
                                    <div>
                                        <div class="form-group">
                                            <label>Font Style</label>
                                            <select class="form-control" id="font_family" name="font_family">
                                                <?php foreach (['Inter', 'Poppins', 'Outfit', 'Roboto', 'Open Sans', 'Lato', 'Nunito', 'Montserrat', 'DM Sans', 'Plus Jakarta Sans'] as $fnt): ?>
                                                <option value="<?php echo htmlspecialchars($fnt); ?>" <?php echo ($settings['font_family'] ?? '') === $fnt ? 'selected' : ''; ?> style="font-family:'<?php echo htmlspecialchars($fnt); ?>',sans-serif"><?php echo htmlspecialchars($fnt); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="color-item">
                                            <div class="color-item-info">
                                                <label>Brand Color</label>
                                                <span class="color-item-desc">Buttons, links, active highlights</span>
                                            </div>
                                            <div class="color-input-group">
                                                <input type="color" id="picker-primary" value="<?php echo htmlspecialchars($settings['primary_color']); ?>">
                                                <input type="text" class="form-control" id="primary_color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" maxlength="7">
                                            </div>
                                        </div>

                                        <div class="color-item">
                                            <div class="color-item-info">
                                                <label>Page Background</label>
                                                <span class="color-item-desc">Main area behind cards and content</span>
                                            </div>
                                            <div class="color-input-group">
                                                <input type="color" id="picker-bg-body" value="<?php echo htmlspecialchars($settings['bg_body']); ?>">
                                                <input type="text" class="form-control" id="bg_body" name="bg_body" value="<?php echo htmlspecialchars($settings['bg_body']); ?>" maxlength="7">
                                            </div>
                                        </div>

                                        <div class="color-item">
                                            <div class="color-item-info">
                                                <label>Card & Sidebar</label>
                                                <span class="color-item-desc">Panels, cards, sidebar background</span>
                                            </div>
                                            <div class="color-input-group">
                                                <input type="color" id="picker-bg-card" value="<?php echo htmlspecialchars($settings['bg_card']); ?>">
                                                <input type="text" class="form-control" id="bg_card" name="bg_card" value="<?php echo htmlspecialchars($settings['bg_card']); ?>" maxlength="7">
                                            </div>
                                        </div>

                                        <div class="color-item">
                                            <div class="color-item-info">
                                                <label>Heading Text</label>
                                                <span class="color-item-desc">Titles, nav labels, sidebar items</span>
                                            </div>
                                            <div class="color-input-group">
                                                <input type="color" id="picker-text-main" value="<?php echo htmlspecialchars($settings['text_main']); ?>">
                                                <input type="text" class="form-control" id="text_main" name="text_main" value="<?php echo htmlspecialchars($settings['text_main']); ?>" maxlength="7">
                                            </div>
                                        </div>

                                        <div class="color-item">
                                            <div class="color-item-info">
                                                <label>Body Text</label>
                                                <span class="color-item-desc">Paragraphs, descriptions, timestamps</span>
                                            </div>
                                            <div class="color-input-group">
                                                <input type="color" id="picker-text-muted" value="<?php echo htmlspecialchars($settings['text_muted']); ?>">
                                                <input type="text" class="form-control" id="text_muted" name="text_muted" value="<?php echo htmlspecialchars($settings['text_muted']); ?>" maxlength="7">
                                            </div>
                                        </div>

                                        <input type="hidden" name="secondary_color" id="secondary_color" value="<?php echo htmlspecialchars($settings['secondary_color']); ?>">

                                        <div class="config-subsection-title">Card & Sidebar Style</div>

                                        <div class="color-item">
                                            <div class="color-item-info">
                                                <label>Border Color</label>
                                                <span class="color-item-desc">Card edges, dividers, and separators</span>
                                            </div>
                                            <div class="color-input-group">
                                                <input type="color" id="picker-border-color" value="<?php echo htmlspecialchars($settings['border_color']); ?>">
                                                <input type="text" class="form-control" id="border_color" name="border_color" value="<?php echo htmlspecialchars($settings['border_color']); ?>" maxlength="7">
                                            </div>
                                        </div>

                                        <div class="color-item">
                                            <div class="color-item-info">
                                                <label>Border Width</label>
                                                <span class="color-item-desc">Thickness of card and sidebar outlines</span>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <input type="range" id="card_border_width" name="card_border_width" min="0" max="3" step="0.1" value="<?php echo htmlspecialchars($settings['card_border_width']); ?>" style="width:120px; accent-color:var(--primary-color);">
                                                <span id="border-width-label" class="config-inline-value"><?php echo htmlspecialchars($settings['card_border_width']); ?>px</span>
                                            </div>
                                        </div>

                                        <div class="color-item">
                                            <div class="color-item-info">
                                                <label>Card Shadow</label>
                                                <span class="color-item-desc">Depth and elevation of workspace panels</span>
                                            </div>
                                            <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                                <?php foreach (['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $val => $label): ?>
                                                <button type="button" class="shadow-opt<?php echo ($settings['card_shadow'] ?? 'sm') === $val ? ' active' : ''; ?>" data-shadow="<?php echo $val; ?>"><?php echo $label; ?></button>
                                                <?php endforeach; ?>
                                                <input type="hidden" id="card_shadow" name="card_shadow" value="<?php echo htmlspecialchars($settings['card_shadow'] ?? 'sm'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="preview-switch">
                                            <button type="button" class="preview-btn active" data-view="admin">Admin View</button>
                                            <button type="button" class="preview-btn" data-view="staff">Staff View</button>
                                            <button type="button" class="preview-btn" data-view="mobile">Client App View</button>
                                        </div>
                                        <div class="preview-stage" id="preview-stage">
                                            <div class="preview-screen active" data-preview="admin"><div class="preview-shell admin-layout"><div class="admin-sidebar"><div class="admin-sidebar-header"><div class="admin-sidebar-logo"><span class="material-symbols-rounded">diamond</span><img class="preview-logo-image" alt="Logo"></div><div class="admin-sidebar-name preview-company-name"><?php echo htmlspecialchars($settings['company_name']); ?></div></div><div class="admin-sidebar-nav"><div class="admin-nav-item active"><span class="material-symbols-rounded">dashboard</span>Dashboard</div><div class="admin-nav-item"><span class="material-symbols-rounded">groups</span>Staff & Roles</div><div class="admin-nav-item"><span class="material-symbols-rounded">language</span>Website</div><div class="admin-nav-item"><span class="material-symbols-rounded">receipt_long</span>Billing</div><div class="admin-nav-item"><span class="material-symbols-rounded">palette</span>Branding</div></div><div class="admin-sidebar-footer"><div class="admin-nav-item logout"><span class="material-symbols-rounded">logout</span>Logout</div></div></div><div class="admin-main"><div class="admin-topbar"><div class="admin-topbar-title">Dashboard</div><div class="admin-topbar-right"><span class="material-symbols-rounded" style="font-size:15px; color:var(--theme-text-muted);">dark_mode</span><div class="admin-topbar-avatar">A</div><div class="admin-topbar-info"><strong class="preview-company-name"><?php echo htmlspecialchars($settings['company_name']); ?></strong>Admin</div></div></div><div class="admin-dashboard-content"><div class="admin-stat-grid"><div class="admin-stat-card"><div class="admin-stat-icon" style="background:rgba(var(--primary-r),var(--primary-g),var(--primary-b),0.1);"><span class="material-symbols-rounded" style="color:var(--theme-primary);">book</span></div><div><div class="admin-stat-label">Total Clients</div><div class="admin-stat-value">1,240</div></div></div><div class="admin-stat-card"><div class="admin-stat-icon" style="background:rgba(34,197,94,0.1);"><span class="material-symbols-rounded" style="color:#22c55e;">group</span></div><div><div class="admin-stat-label">Active Staff</div><div class="admin-stat-value">24</div></div></div><div class="admin-stat-card"><div class="admin-stat-icon" style="background:rgba(245,158,11,0.14);"><span class="material-symbols-rounded" style="color:#f59e0b;">warning</span></div><div><div class="admin-stat-label">Alerts</div><div class="admin-stat-value">3</div></div></div></div><div class="admin-activity-card"><div class="admin-activity-title">Recent Activity</div><div class="admin-activity-item"><div class="admin-activity-dot" style="background:var(--theme-primary);"></div><span class="admin-activity-text">New staff account created</span><span class="admin-activity-time">2m ago</span></div><div class="admin-activity-item"><div class="admin-activity-dot" style="background:#22c55e;"></div><span class="admin-activity-text">Loan product updated</span><span class="admin-activity-time">15m ago</span></div><div class="admin-activity-item"><div class="admin-activity-dot" style="background:#f59e0b;"></div><span class="admin-activity-text">Payment recorded</span><span class="admin-activity-time">1h ago</span></div></div></div></div></div></div>
                                            <div class="preview-screen" data-preview="staff"><div class="preview-shell staff-layout"><div class="staff-sidebar"><div class="staff-sidebar-header"><div class="staff-sidebar-logo"><span class="material-symbols-rounded">account_balance</span><img class="preview-logo-image" alt="Logo"></div><div class="staff-sidebar-name preview-company-name"><?php echo htmlspecialchars($settings['company_name']); ?></div><div class="staff-sidebar-sub">Employee Portal</div></div><div class="staff-nav-item active"><span class="material-symbols-rounded">home</span>Home</div><div class="staff-nav-item"><span class="material-symbols-rounded">group</span>Clients</div><div class="staff-nav-item"><span class="material-symbols-rounded">real_estate_agent</span>Loans</div><div class="staff-nav-item"><span class="material-symbols-rounded">description</span>Applications</div><div class="staff-nav-item"><span class="material-symbols-rounded">payments</span>Payments</div><div class="staff-nav-item"><span class="material-symbols-rounded">bar_chart</span>Reports</div><div class="staff-nav-spacer"></div><div class="staff-nav-item logout"><span class="material-symbols-rounded">logout</span>Sign Out</div></div><div class="staff-main"><div class="staff-topbar"><div class="staff-topbar-title">Home</div><div class="staff-topbar-right"><div class="staff-walkin-btn"><span class="material-symbols-rounded">person_add</span>Walk-In</div><div class="staff-avatar-pill"><div class="staff-avatar-circle">J</div><div class="staff-avatar-info"><strong>Juan</strong>Loan Officer</div></div></div></div><div class="staff-dashboard-content"><div class="staff-welcome-card"><div class="staff-welcome-icon"><span class="material-symbols-rounded">waving_hand</span></div><div class="staff-welcome-text"><h4>Welcome back, Juan!</h4><p>Here is your daily overview and pending tasks.</p></div></div><div class="staff-widget-grid"><div class="staff-widget-card"><div class="staff-widget-header"><span class="material-symbols-rounded">task</span><span>Pending Loans</span></div><div class="staff-widget-value">8</div><div class="staff-widget-sub">Awaiting review</div></div><div class="staff-widget-card"><div class="staff-widget-header"><span class="material-symbols-rounded">receipt_long</span><span>Today's Collections</span></div><div class="staff-widget-value">&#8369;48,200</div><div class="staff-widget-sub">12 payments received</div></div></div></div></div></div></div>
                                            <div class="preview-screen" data-preview="mobile"><div class="phone-shell"><div class="phone-notch"></div><div class="phone-statusbar"><span>9:41</span><span style="display:flex;gap:3px;align-items:center;"><span class="material-symbols-rounded" style="font-size:10px;">signal_cellular_alt</span><span class="material-symbols-rounded" style="font-size:10px;">wifi</span><span class="material-symbols-rounded" style="font-size:10px;">battery_full</span></span></div><div class="client-home-header"><div class="client-greeting">Good morning,</div><div class="client-name" style="color:var(--theme-text-main);">Maria Santos</div></div><div class="client-balance-card"><div class="client-balance-label">Outstanding Balance</div><div class="client-balance-amount">&#8369;24,500.00</div><div class="client-balance-sub">Next payment: Apr 29, 2026</div><div class="client-balance-row"><div class="client-balance-stat"><div class="client-balance-stat-val">&#8369;2,450</div><div class="client-balance-stat-lbl">Monthly Due</div></div><div class="client-balance-stat"><div class="client-balance-stat-val">6 / 12</div><div class="client-balance-stat-lbl">Payments Made</div></div><div class="client-balance-stat"><div class="client-balance-stat-val">On Time</div><div class="client-balance-stat-lbl">Status</div></div></div></div><div class="client-quick-actions"><div class="client-action-btn"><span class="material-symbols-rounded">payments</span>Pay Now</div><div class="client-action-btn"><span class="material-symbols-rounded">add_circle</span>Apply</div><div class="client-action-btn"><span class="material-symbols-rounded">calendar_month</span>Schedule</div><div class="client-action-btn"><span class="material-symbols-rounded">chat</span>Support</div></div><div class="client-section-title">Active Loan</div><div class="client-loan-card"><div class="client-loan-top"><span class="client-loan-name">Personal Loan</span><span class="client-loan-badge">Active</span></div><div class="client-loan-progress"><div class="client-loan-progress-fill" style="width:50%;"></div></div><div class="client-loan-details"><span>&#8369;24,500 remaining</span><span>&#8369;49,000 total</span></div></div><div class="phone-bottom-nav"><div class="phone-nav-item active"><span class="material-symbols-rounded">home</span>Home</div><div class="phone-nav-item"><span class="material-symbols-rounded">receipt_long</span>Loans</div><div class="phone-nav-item"><span class="material-symbols-rounded">payments</span>Payments</div><div class="phone-nav-item"><span class="material-symbols-rounded">person</span>Profile</div></div></div></div>
                                            <div class="preview-screen" data-preview="website"><div class="website-preview-shell"><div class="wp-nav"><div class="wp-nav-brand preview-company-name"><?php echo htmlspecialchars($settings['company_name']); ?></div><div class="wp-nav-links"><a href="#">Home</a><a href="#">About</a><a href="#">Services</a><a href="#">Contact</a></div><div class="wp-nav-btns"><button type="button" class="btn-login">Login</button><button type="button" class="btn-cta">Apply Now</button></div></div><div class="wp-hero"><div><div class="wp-badge"><span class="material-symbols-rounded">verified</span>Trusted Financial Services</div><div class="wp-hero-title">Build your future with <span class="preview-company-name"><?php echo htmlspecialchars($settings['company_name']); ?></span></div><div class="wp-hero-sub">Flexible loan products, reliable service, and a digital experience shaped by your brand.</div><div class="wp-hero-actions"><button type="button" class="wp-btn-p">Get Started</button><button type="button" class="wp-btn-s">Learn More</button></div></div><div class="wp-illus"><div class="wp-stat-card"><div class="wp-stat-icon" style="background:var(--theme-primary);"><span class="material-symbols-rounded">check_circle</span></div><div><div class="wp-stat-label">Approved</div><div class="wp-stat-value">1,240</div></div></div><div class="wp-stat-card"><div class="wp-stat-icon" style="background:#10b981;"><span class="material-symbols-rounded">group</span></div><div><div class="wp-stat-label">Members</div><div class="wp-stat-value">856</div></div></div><div class="wp-stat-card"><div class="wp-stat-icon" style="background:#f59e0b;"><span class="material-symbols-rounded">trending_up</span></div><div><div class="wp-stat-label">Growth</div><div class="wp-stat-value">+24%</div></div></div></div></div><div class="wp-services"><div class="wp-section-label">What We Offer</div><div class="wp-section-heading">Our Services</div><div class="wp-svc-grid"><div class="wp-svc-card"><span class="material-symbols-rounded" style="color:var(--theme-primary);">account_balance_wallet</span><h6>Personal Loans</h6><p>Flexible terms for your needs.</p></div><div class="wp-svc-card"><span class="material-symbols-rounded" style="color:#10b981;">store</span><h6>Business Loans</h6><p>Grow your enterprise.</p></div><div class="wp-svc-card"><span class="material-symbols-rounded" style="color:#f59e0b;">emergency</span><h6>Emergency Loans</h6><p>Quick access when needed.</p></div></div></div><div class="wp-about"><div class="wp-about-label">About Us</div><div class="wp-about-title">Who We Are</div><div class="wp-about-body">We are committed to empowering communities through accessible financial services and sustainable growth.</div></div><div class="wp-footer"><div><div class="wp-footer-brand preview-company-name"><?php echo htmlspecialchars($settings['company_name']); ?></div><div class="wp-footer-desc">Your trusted partner in financial growth.</div></div><div><div class="wp-footer-contact-label">Contact</div><div class="wp-footer-contact-item"><span class="material-symbols-rounded">call</span><?php echo htmlspecialchars(($settings['support_phone'] ?? '') !== '' ? $settings['support_phone'] : '(02) 1234-5678'); ?></div><div class="wp-footer-contact-item"><span class="material-symbols-rounded">mail</span><?php echo htmlspecialchars(($settings['support_email'] ?? '') !== '' ? $settings['support_email'] : 'info@example.com'); ?></div><div class="wp-footer-contact-item"><span class="material-symbols-rounded">schedule</span>Mon-Fri 8am-5pm</div></div></div></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="action-bar">
                                <button class="btn btn-primary" id="save-settings" type="submit">Save Branding</button>
                            </div>
                        </form>
                </section>

                <section id="personal" class="view-section <?php echo $active_view === 'personal' ? 'active' : ''; ?>">
                        <div class="settings-panel">
                            <?php
                                $personal_data = ['first_name' => '', 'last_name' => '', 'email' => ''];
                                if (isset($_SESSION['user_id'])) {
                                    $pd_stmt = $pdo->prepare('SELECT u.email, e.first_name, e.last_name FROM users u JOIN employees e ON u.user_id = e.user_id WHERE u.user_id = ?');
                                    $pd_stmt->execute([$_SESSION['user_id']]);
                                    $pd_res = $pd_stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($pd_res) $personal_data = $pd_res;
                                }
                            ?>
                            <form method="POST" action="admin.php">
                                <input type="hidden" name="action" value="update_personal_profile">
                                <div class="card">
                                    <h3>Personal Details</h3>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label>First Name</label>
                                            <input type="text" name="personal_first_name" class="form-control" value="<?php echo htmlspecialchars($personal_data['first_name']); ?>" required>
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label>Last Name</label>
                                            <input type="text" name="personal_last_name" class="form-control" value="<?php echo htmlspecialchars($personal_data['last_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Log-in Email Address</label>
                                        <input type="email" name="personal_email" class="form-control" value="<?php echo htmlspecialchars($personal_data['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="card">
                                    <h3>Security</h3>
                                    <p class="text-muted" style="margin-bottom: 12px;">Leave blank if you do not want to change your password.</p>
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <input type="password" name="personal_password" class="form-control" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                                    </div>
                                </div>
                                <div class="action-bar">
                                    <button class="btn btn-primary" type="submit">Update Personal Profile</button>
                                </div>
                            </form>
                        </div>
                </section>

                <!-- Feature Toggles View -->
                <section id="features" class="view-section <?php echo $active_view === 'features' ? 'active' : ''; ?>">
                    <div class="card">
                        <h3>Core Modules & Toggles</h3>
                        <p class="text-muted">Instantly enable or disable core functionality across the entire platform.
                        </p>

                        <div class="toggle-list">
                            <div class="toggle-item">
                                <div class="toggle-info">
                                    <h4>Booking System</h4>
                                    <p>Allow users to create new bookings and reservations.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="toggle-booking_system" <?php echo ((int) $toggles['booking_system'] === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="toggle-item">
                                <div class="toggle-info">
                                    <h4>User Registration</h4>
                                    <p>Allow new clients to sign up for accounts on the frontend.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="toggle-user_registration" <?php echo ((int) $toggles['user_registration'] === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="toggle-item warning-toggle">
                                <div class="toggle-info">
                                    <h4>Maintenance Mode</h4>
                                    <p>Take the system offline for clients. Only admins can log in.</p>
                                </div>
                                <label class="switch warning">
                                    <input type="checkbox" id="toggle-maintenance_mode" <?php echo ((int) $toggles['maintenance_mode'] === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="toggle-item">
                                <div class="toggle-info">
                                    <h4>Email Notifications</h4>
                                    <p>Send automated emails for bookings, OTPs, and alerts.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="toggle-email_notifications" <?php echo ((int) $toggles['email_notifications'] === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="toggle-item">
                                <div class="toggle-info">
                                    <h4>Public Website</h4>
                                    <p>Enable a public-facing informational website for your organization.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="toggle-public_website_enabled" <?php echo ((int) ($toggles['public_website_enabled'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if ($can_manage_billing): ?>
                <!-- Billing & Subscription View -->
                <section id="billing" class="view-section <?php echo $active_view === 'billing' ? 'active' : ''; ?>">
                    <div class="header-desc" style="margin-bottom: 24px;">
                        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 4px;">Plan & Billing</h2>
                        <p class="text-muted">Manage your subscription plan and track usage limits.</p>
                    </div>

                    <!-- Plan Overview -->
                    <div id="billing-overview">
                            <?php
                                $plan_fields = ['plan_tier', 'max_clients', 'max_users'];
                                if ($tenants_has_cancel_at_period_end) {
                                    $plan_fields[] = 'cancel_at_period_end';
                                }
                                if ($tenants_has_next_billing_date) {
                                    $plan_fields[] = 'next_billing_date';
                                }

                                $plan_stmt = $pdo->prepare('SELECT ' . implode(', ', $plan_fields) . ' FROM tenants WHERE tenant_id = ?');
                                $plan_stmt->execute([$tenant_id]);
                                $tenant_plan = $plan_stmt->fetch(PDO::FETCH_ASSOC) ?: ['plan_tier' => 'Starter', 'max_clients' => 1000, 'max_users' => 250];

                                $cancel_at_period_end = (bool)($tenant_plan['cancel_at_period_end'] ?? 0);
                                $next_billing_date_value = trim((string)($tenant_plan['next_billing_date'] ?? ''));
                                if ($next_billing_date_value === '') {
                                    $next_billing_date_value = admin_get_next_billing_date($pdo, (string)$tenant_id, $tenants_has_next_billing_date);
                                }
                                $next_billing_date_formatted = $next_billing_date_value !== '' ? date('M j, Y', strtotime($next_billing_date_value)) : 'N/A';
                                
                                $current_plan = $tenant_plan['plan_tier'];
                                $plan_aliases = ['Professional' => 'Pro', 'Elite' => 'Enterprise'];
                                if (isset($plan_aliases[$current_plan])) {
                                    $current_plan = $plan_aliases[$current_plan];
                                }
                                $plan_catalog = [
                                    'Starter' => ['label' => 'Starter', 'price' => 4999],
                                    'Pro' => ['label' => 'Pro', 'price' => 14999],
                                    'Enterprise' => ['label' => 'Enterprise', 'price' => 19999],
                                    'Unlimited' => ['label' => 'Unlimited', 'price' => 29999]
                                ];
                                if (!isset($plan_catalog[$current_plan])) {
                                    if ($current_plan === 'Growth') {
                                        $plan_catalog[$current_plan] = ['label' => 'Growth (Legacy)', 'price' => 9999];
                                    } else {
                                        $plan_catalog[$current_plan] = ['label' => $current_plan, 'price' => 0];
                                    }
                                }

                                $max_clients = (int)$tenant_plan['max_clients'];
                                $max_users = (int)$tenant_plan['max_users'];
                                
                                $client_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND client_status = 'Active'");
                                $client_count_stmt->execute([$tenant_id]);
                                $current_total_clients = (int)$client_count_stmt->fetchColumn();
                                
                                $staff_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND user_type = 'Employee' AND status = 'Active'");
                                $staff_count_stmt->execute([$tenant_id]);
                                $current_active_staff = (int)$staff_count_stmt->fetchColumn();
                                
                                $client_pct = $max_clients > 0 ? min(100, round(($current_total_clients / $max_clients) * 100)) : 0;
                                $staff_pct = $max_users > 0 ? min(100, round(($current_active_staff / $max_users) * 100)) : 0;
                            ?>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px;">
                                <!-- Premium Plan Card -->
                                <div class="card" style="background: linear-gradient(135deg, var(--primary-color) 0%, #3b82f6 100%); color: white; border: none; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.25); display: flex; flex-direction: column; position: relative; overflow: hidden; padding: 32px;">
                                    <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%); border-radius: 50%;"></div>
                                    <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%); border-radius: 50%;"></div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; z-index: 1;">
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                                <span class="material-symbols-rounded" style="font-size: 18px; color: #fbbf24;">workspace_premium</span>
                                                <span style="text-transform: uppercase; font-size: 0.75rem; font-weight: 700; opacity: 0.9; letter-spacing: 0.05em;">Active Subscription</span>
                                            </div>
                                            <h3 style="font-size: 2.25rem; font-weight: 800; margin: 0; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.1);"><?php echo htmlspecialchars($current_plan); ?></h3>
                                            <div style="font-size: 0.85rem; margin-top: 4px; opacity: 0.9;">
                                                <?php if ($cancel_at_period_end): ?>
                                                    <span style="color: #fca5a5; font-weight: 600;">Cancels on <?php echo $next_billing_date_formatted; ?></span>
                                                <?php else: ?>
                                                    Next Due: <strong><?php echo $next_billing_date_formatted; ?></strong>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); padding: 8px 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.3); text-align: right;">
                                            <div style="font-weight: 800; font-size: 1.25rem;">₱<?php echo number_format((float)$plan_catalog[$current_plan]['price'], 0); ?></div>
                                            <div style="font-size: 0.75rem; opacity: 0.9;">per month</div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 32px; z-index: 1; display: flex; flex-direction: column; gap: 12px;">
                                        <div style="display: flex; align-items: center; gap: 10px; font-size: 0.95rem;">
                                            <div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                                <span class="material-symbols-rounded" style="font-size: 14px;">check</span>
                                            </div>
                                            <span><strong><?php echo $max_clients > 0 ? number_format($max_clients) : 'Unlimited'; ?></strong> Client Accounts</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px; font-size: 0.95rem;">
                                            <div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                                <span class="material-symbols-rounded" style="font-size: 14px;">check</span>
                                            </div>
                                            <span><strong><?php echo $max_users > 0 ? number_format($max_users) : 'Unlimited'; ?></strong> Staff Accounts</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px; font-size: 0.95rem;">
                                            <div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                                <span class="material-symbols-rounded" style="font-size: 14px;">check</span>
                                            </div>
                                            <span>Premium Technical Support</span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($can_manage_billing): ?>
                                    <div style="margin-top: auto; padding-top: 32px; z-index: 1;">
                                        <div style="background: rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.1); padding: 16px; border-radius: 12px;">
                                            <label style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; opacity: 0.9;">Manage Plan</label>
                                            <form method="POST" action="admin.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                                                <input type="hidden" name="action" value="update_subscription_plan">
                                                <select name="new_plan" id="new-plan-select" style="flex: 1; min-width: 140px; background: rgba(255,255,255,0.9); border: none; padding: 10px 14px; border-radius: 8px; font-size: 0.95rem; font-weight: 500; color: #1e293b; outline: none; cursor: pointer;">
                                                    <option value="Starter" <?php echo $current_plan === 'Starter' ? 'selected' : ''; ?>>Starter - ₱4,999/mo</option>
                                                    <option value="Pro" <?php echo $current_plan === 'Pro' ? 'selected' : ''; ?>>Pro - ₱14,999/mo</option>
                                                    <option value="Enterprise" <?php echo $current_plan === 'Enterprise' ? 'selected' : ''; ?>>Enterprise - ₱19,999/mo</option>
                                                    <option value="Unlimited" <?php echo $current_plan === 'Unlimited' ? 'selected' : ''; ?>>Unlimited - ₱29,999/mo</option>
                                                </select>
                                                <select name="change_timing" style="min-width: 130px; background: rgba(255,255,255,0.9); border: none; padding: 10px 14px; border-radius: 8px; font-size: 0.95rem; font-weight: 500; color: #1e293b; outline: none; cursor: pointer;">
                                                    <option value="immediate">Instantly Now</option>
                                                    <option value="next_cycle" selected>Next Billing</option>
                                                </select>
                                                <button type="submit" id="plan-action-btn" style="background: white; color: var(--primary-color); border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">Upgrade</button>
                                            </form>
                                            <script>
                                                document.addEventListener('DOMContentLoaded', () => {
                                                    const planSelect = document.getElementById('new-plan-select');
                                                    const actionBtn = document.getElementById('plan-action-btn');
                                                    if (planSelect && actionBtn) {
                                                        const ranks = { 'Starter': 1, 'Growth': 2, 'Pro': 3, 'Enterprise': 4, 'Unlimited': 5 };
                                                        const currentPlan = '<?php echo addslashes($current_plan); ?>';
                                                        const currentRank = ranks[currentPlan] || 0;
                                                        planSelect.addEventListener('change', (e) => {
                                                            const selectedRank = ranks[e.target.value] || 0;
                                                            if (selectedRank < currentRank) {
                                                                actionBtn.textContent = 'Downgrade';
                                                            } else if (selectedRank > currentRank) {
                                                                actionBtn.textContent = 'Upgrade';
                                                            } else {
                                                                actionBtn.textContent = 'Same Plan';
                                                            }
                                                        });
                                                        planSelect.dispatchEvent(new Event('change'));
                                                    }
                                                });
                                            </script>
                                        </div>
                                        <?php if ($tenants_has_cancel_at_period_end && !$cancel_at_period_end): ?>
                                        <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
                                            <form method="POST" action="admin.php" data-confirm-title="Cancel Subscription" data-confirm-message="Are you sure you want to cancel your subscription? You will still retain access to the system until your next billing due date, after which your account will be suspended." data-confirm-button="Yes, Cancel Subscription">
                                                <input type="hidden" name="action" value="cancel_subscription">
                                                <button type="submit" class="btn btn-outline" style="border-color: rgba(255,255,255,0.3); color: white; padding: 6px 12px; font-size: 0.85rem; border-radius: 6px;">
                                                    Cancel Subscription
                                                </button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Usage Limits Card -->
                                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 32px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <div style="background: #e2e8f0; color: #475569; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <span class="material-symbols-rounded" style="font-size: 18px;">analytics</span>
                                            </div>
                                            <h4 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: #1e293b;">Usage & Limits</h4>
                                        </div>
                                        <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 32px; line-height: 1.5;">Monitor your system capacity against your current plan constraints.</p>
                                        
                                        <div style="margin-bottom: 32px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: flex-end;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color); display: flex; align-items: center; justify-content: center;">
                                                        <span class="material-symbols-rounded" style="font-size: 20px;">group</span>
                                                    </div>
                                                    <div>
                                                        <span style="font-weight: 700; font-size: 1rem; display: block; color: var(--text-color);">Clients</span>
                                                        <span class="text-muted" style="font-size: 0.8rem;">Active borrower accounts</span>
                                                    </div>
                                                </div>
                                                <div style="text-align: right;">
                                                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--text-color);"><?php echo number_format($current_total_clients); ?></span>
                                                    <span class="text-muted" style="font-weight: 500; font-size: 0.9rem;"> / <?php echo $max_clients > 0 ? number_format($max_clients) : '∞'; ?></span>
                                                </div>
                                            </div>
                                            <div style="width: 100%; background: #f1f5f9; border-radius: 8px; height: 12px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                                <div style="height: 100%; width: <?php echo $client_pct; ?>%; background: <?php echo $client_pct >= 90 ? 'linear-gradient(90deg, #f87171, #ef4444)' : 'linear-gradient(90deg, #93c5fd, var(--primary-color))'; ?>; border-radius: 8px; transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: flex-end;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(34, 197, 94, 0.1); color: #22c55e; display: flex; align-items: center; justify-content: center;">
                                                        <span class="material-symbols-rounded" style="font-size: 20px;">admin_panel_settings</span>
                                                    </div>
                                                    <div>
                                                        <span style="font-weight: 700; font-size: 1rem; display: block; color: var(--text-color);">Staff Users</span>
                                                        <span class="text-muted" style="font-size: 0.8rem;">Active employee accounts</span>
                                                    </div>
                                                </div>
                                                <div style="text-align: right;">
                                                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--text-color);"><?php echo number_format($current_active_staff); ?></span>
                                                    <span class="text-muted" style="font-weight: 500; font-size: 0.9rem;"> / <?php echo $max_users > 0 ? number_format($max_users) : '∞'; ?></span>
                                                </div>
                                            </div>
                                            <div style="width: 100%; background: #f1f5f9; border-radius: 8px; height: 12px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                                <div style="height: 100%; width: <?php echo $staff_pct; ?>%; background: <?php echo $staff_pct >= 90 ? 'linear-gradient(90deg, #f87171, #ef4444)' : 'linear-gradient(90deg, #86efac, #22c55e)'; ?>; border-radius: 8px; transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 32px; padding: 16px; border-radius: 12px; background: <?php echo ($client_pct >= 90 || $staff_pct >= 90) ? '#fef2f2' : '#f8fafc'; ?>; border: 1px solid <?php echo ($client_pct >= 90 || $staff_pct >= 90) ? '#fecaca' : '#e2e8f0'; ?>;">
                                        <div style="display: flex; gap: 12px; align-items: flex-start;">
                                            <span class="material-symbols-rounded" style="color: <?php echo ($client_pct >= 90 || $staff_pct >= 90) ? '#ef4444' : '#64748b'; ?>; font-size: 24px; margin-top: 2px;">info</span>
                                            <p style="margin: 0; font-size: 0.85rem; line-height: 1.5; color: <?php echo ($client_pct >= 90 || $staff_pct >= 90) ? '#b91c1c' : '#475569'; ?>;">
                                                <?php if ($client_pct >= 90 || $staff_pct >= 90): ?>
                                                    <strong style="display: block; font-size: 0.95rem; margin-bottom: 4px;">Approaching Limits!</strong> 
                                                    You are nearing your plan's maximum capacity limit. To continue adding new accounts without interruption, consider upgrading your subscription via the plan manage panel.
                                                <?php else: ?>
                                                    Your organization is well within the current active plan limits. You can freely upgrade or downgrade your plan anytime to adjust to market needs.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>

                </section>

                <!-- Payment Info View -->
                <section id="payment_info" class="view-section <?php echo $active_view === 'payment_info' ? 'active' : ''; ?>">
                    <div class="section-intro">
                        <h2>Payment Information</h2>
                        <p class="text-muted">Manage your securely saved credit cards for automated subscription billing.</p>
                    </div>
<div class="card" style="padding: 32px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                        <div style="background: rgba(34, 197, 94, 0.1); color: #22c55e; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                            <span class="material-symbols-rounded" style="font-size: 20px;">credit_card</span>
                                        </div>
                                        <h3 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #1e293b;">Payment Methods</h3>
                                    </div>
                                    <p class="text-muted" style="margin: 0; font-size: 0.95rem;">Manage your securely saved credit cards for automated subscription billing.</p>
                                </div>
                                <!-- In real app, this would open a modal to add Stripe/card -->
                                <button type="button" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 6px rgba(var(--primary-rgb), 0.2);">
                                    <span class="material-symbols-rounded" style="font-size: 20px;">add_card</span> Add New Card
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Card</th>
                                            <th>Cardholder</th>
                                            <th>Expiry</th>
                                            <th>Default</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $payment_methods_stmt = $pdo->prepare('SELECT method_id, last_four_digits, cardholder_name, exp_month, exp_year, is_default, created_at FROM tenant_billing_payment_methods WHERE tenant_id = ? ORDER BY is_default DESC, created_at ASC');
                                        $payment_methods_stmt->execute([$tenant_id]);
                                        $payment_methods = $payment_methods_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        if (empty($payment_methods)):
                                        ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                                <span class="material-symbols-rounded" style="font-size: 36px; display: block; margin-bottom: 0.5rem;">credit_card_off</span>
                                                No payment methods found. Add one to keep your subscription active.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($payment_methods as $pm): ?>
                                            <tr>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap: 8px;">
                                                        <span class="material-symbols-rounded text-muted">credit_card</span>
                                                        <span class="text-muted" style="font-weight: 500;">•••• <?php echo htmlspecialchars($pm['last_four_digits']); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($pm['cardholder_name']); ?></td>
                                                <td><?php echo str_pad((string)((int)$pm['exp_month']), 2, '0', STR_PAD_LEFT); ?> / <?php echo (int)$pm['exp_year']; ?></td>
                                                <td>
                                                    <?php if ((int)$pm['is_default'] === 1): ?>
                                                        <span class="badge badge-green">Default</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-gray">Backup</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display:flex; gap:8px;">
                                                        <form method="POST" action="admin.php" data-confirm-title="Remove Payment Method" data-confirm-message="Are you sure you want to remove this payment method?" data-confirm-button="Remove">
                                                            <input type="hidden" name="action" value="delete_payment_method">
                                                            <input type="hidden" name="method_id" value="<?php echo (int)$pm['method_id']; ?>">
                                                            <button type="submit" class="btn btn-sm" style="color:#ef4444; background: rgba(239,68,68,0.1);">Remove</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                </section>

                <!-- Receipts View -->
                <section id="statements" class="view-section <?php echo $active_view === 'statements' ? 'active' : ''; ?>">
                    <div class="section-intro">
                        <h2>Receipts</h2>
                        <p class="text-muted">Review completed subscription receipts between your workspace and the platform, then export the filtered history to PDF.</p>
                    </div>

                    <div class="card receipts-toolbar-card">
                        <div class="receipts-toolbar-head">
                            <div>
                                <h3>Receipt History</h3>
                                <p class="text-muted">Browse paid platform receipts by month, by year, or across your full receipt history.</p>
                            </div>
                            <a href="<?php echo htmlspecialchars($receipt_export_url); ?>" target="_blank" class="btn btn-primary">
                                <span class="material-symbols-rounded">download</span>
                                Export All
                            </a>
                        </div>

                        <div class="receipt-filter-pills">
                            <span class="receipt-filter-pill receipt-filter-pill-primary"><?php echo htmlspecialchars($receipt_period_badge); ?></span>
                            <span class="receipt-filter-pill"><?php echo number_format($receipt_total_count); ?> paid receipt<?php echo $receipt_total_count === 1 ? '' : 's'; ?></span>
                        </div>

                        <form method="GET" action="admin.php" class="receipts-filter-form">
                            <input type="hidden" name="tab" value="statements">
                            <div class="receipts-filter-grid">
                                <div class="form-group receipt-filter-slot">
                                    <label for="receipt-period">View</label>
                                    <select id="receipt-period" name="receipt_period" class="form-control">
                                        <?php foreach ($receipt_period_options as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $receipt_filters['receipt_period'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group receipt-filter-slot <?php echo $receipt_filters['receipt_period'] === 'month' ? '' : 'is-hidden'; ?>" data-receipt-period-field="month">
                                    <label for="receipt-month">Month</label>
                                    <select id="receipt-month" name="receipt_month" class="form-control" <?php echo $receipt_filters['receipt_period'] === 'month' ? '' : 'disabled'; ?>>
                                        <option value="">Select month</option>
                                        <?php foreach ($receipt_month_options as $monthValue => $monthLabel): ?>
                                            <option value="<?php echo htmlspecialchars($monthValue); ?>" <?php echo $receipt_filters['receipt_month'] === $monthValue ? 'selected' : ''; ?>><?php echo htmlspecialchars($monthLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group receipt-filter-slot <?php echo $receipt_filters['receipt_period'] === 'year' ? '' : 'is-hidden'; ?>" data-receipt-period-field="year">
                                    <label for="receipt-year">Year</label>
                                    <select id="receipt-year" name="receipt_year" class="form-control" <?php echo $receipt_filters['receipt_period'] === 'year' ? '' : 'disabled'; ?>>
                                        <option value="">Select year</option>
                                        <?php foreach ($receipt_year_options as $yearOption): ?>
                                            <option value="<?php echo htmlspecialchars($yearOption); ?>" <?php echo $receipt_filters['receipt_year'] === $yearOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($yearOption); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="receipts-toolbar-actions">
                                <span class="receipts-toolbar-note"><?php echo htmlspecialchars($receipt_showing_label); ?></span>
                                <div class="receipts-toolbar-buttons">
                                    <?php if ($receipt_has_filters): ?>
                                        <a href="<?php echo htmlspecialchars($receipt_reset_url); ?>" class="btn btn-outline">Reset</a>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary">
                                        <span class="material-symbols-rounded">filter_alt</span>
                                        Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($receipts)): ?>
                        <div class="card receipts-empty-state">
                            <span class="material-symbols-rounded">receipt_long</span>
                            <h3>No receipts found</h3>
                            <p class="text-muted"><?php echo $receipt_has_filters ? 'Try adjusting your filters to widen the results.' : 'Subscription receipts will appear here after platform billing transactions are recorded.'; ?></p>
                        </div>
                    <?php else: ?>
                        <div class="receipts-grid">
                            <?php foreach ($receipts as $rcpt):
                                $invoiceNumber = trim((string)($rcpt['invoice_number'] ?? ''));
                                if ($invoiceNumber === '') {
                                    $invoiceNumber = 'INV-' . str_pad((string)((int)$rcpt['invoice_id']), 6, '0', STR_PAD_LEFT);
                                }
                                $transactionDate = trim((string)($rcpt['paid_at'] ?? '')) !== '' ? (string)$rcpt['paid_at'] : (string)($rcpt['created_at'] ?? '');
                                $periodLabel = htmlspecialchars(date('M j, Y', strtotime((string)$rcpt['billing_period_start'])) . ' to ' . date('M j, Y', strtotime((string)$rcpt['billing_period_end'])));
                            ?>
                            <article class="card receipt-card">
                                <div class="receipt-card-top">
                                    <div class="receipt-card-top-copy">
                                        <div class="receipt-eyebrow">Platform Subscription</div>
                                        <div class="receipt-company"><?php echo htmlspecialchars($invoiceNumber); ?></div>
                                        <div class="receipt-top-subtitle"><?php echo htmlspecialchars($settings['company_name']); ?></div>
                                    </div>
                                    <span class="receipt-paid-pill">Paid <?php echo htmlspecialchars(date('M j, Y', strtotime($transactionDate))); ?></span>
                                </div>
                                <div class="receipt-card-body">
                                    <div class="receipt-card-amount-row">
                                        <span class="receipt-reference">Receipt Amount</span>
                                        <span class="receipt-amount">PHP <?php echo number_format((float)($rcpt['amount'] ?? 0), 2); ?></span>
                                    </div>
                                    <div class="receipt-meta-grid">
                                        <div>
                                            <div class="receipt-meta-label">Billing Period</div>
                                            <div class="receipt-meta-value"><?php echo $periodLabel; ?></div>
                                        </div>
                                        <div>
                                            <div class="receipt-meta-label">Due Date</div>
                                            <div class="receipt-meta-value"><?php echo htmlspecialchars(date('M j, Y', strtotime((string)$rcpt['due_date']))); ?></div>
                                        </div>
                                        <div>
                                            <div class="receipt-meta-label"><?php echo !empty($rcpt['paid_at']) ? 'Paid On' : 'Created On'; ?></div>
                                            <div class="receipt-meta-value"><?php echo htmlspecialchars(date('M j, Y', strtotime($transactionDate))); ?></div>
                                        </div>
                                        <div>
                                            <div class="receipt-meta-label">Record Type</div>
                                            <div class="receipt-meta-value">Completed platform payment</div>
                                        </div>
                                    </div>
                                    <div class="receipt-chip-row">
                                        <span class="receipt-chip">Receipt <?php echo htmlspecialchars($invoiceNumber); ?></span>
                                        <?php if (!empty($rcpt['stripe_invoice_id'])): ?>
                                            <span class="receipt-chip">Gateway Ref <?php echo htmlspecialchars((string)$rcpt['stripe_invoice_id']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($rcpt['pdf_url'])): ?>
                                            <span class="receipt-chip">Original PDF available</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="receipt-card-actions">
                                        <a href="generate_receipt.php?invoice_id=<?php echo (int)$rcpt['invoice_id']; ?>" target="_blank" class="btn btn-sm btn-outline">
                                            <span class="material-symbols-rounded">visibility</span>
                                            View PDF
                                        </a>
                                        <a href="generate_receipt.php?invoice_id=<?php echo (int)$rcpt['invoice_id']; ?>&amp;download=1" target="_blank" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-rounded">download</span>
                                            Download PDF
                                        </a>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>


                <?php endif; ?>

                <!-- Staff & Roles View -->
                <section id="staff" class="view-section <?php echo $active_view === 'staff' ? 'active' : ''; ?>">
                    <div class="section-intro">
                        <h2>Staff Accounts</h2>
                        <p class="text-muted">Manage employee access, admin accounts, and role permissions for your workspace.</p>
                    </div>

                    <div class="tabs">
                        <a href="admin.php?tab=staff-list" class="tab-btn <?php echo (!isset($_GET['tab']) || $_GET['tab'] !== 'roles-list') ? 'active' : ''; ?>" data-tab="staff-list">Staff Accounts</a>
                        <a href="admin.php?tab=roles-list" class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'roles-list') ? 'active' : ''; ?>" data-tab="roles-list">Roles & Permissions</a>
                    </div>

                    <div id="staff-list" class="tab-content <?php echo (!isset($_GET['tab']) || $_GET['tab'] !== 'roles-list') ? 'active' : ''; ?>">
                        <div class="card">
                            <div class="card-header-flex">
                                <h3>Registered Staff</h3>
                                <div style="display:flex; gap:8px;">
                                    <button class="btn btn-sm btn-outline" style="border-color: var(--primary-color); color: var(--primary-color);" onclick="openAddAdminModal()">
                                        <span class="material-symbols-rounded">admin_panel_settings</span> Create Admin
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="openAddStaffModal()">
                                        <span class="material-symbols-rounded">add</span> Add Staff
                                    </button>
                                </div>
                                <script>
                                    function openAddStaffModal() {
                                        var m = document.getElementById('add-staff-modal');
                                        var sel = m.querySelector('select[name="role_id"]');
                                        var flag = document.getElementById('create-as-admin-flag');
                                        var title = document.getElementById('add-staff-modal-title');
                                        var submit = document.getElementById('add-staff-submit-btn');
                                        var roleGroup = document.getElementById('role-group');
                                        var billingToggle = document.getElementById('billing-toggle-group');
                                        var billingCheckbox = document.getElementById('create-can-manage-billing');

                                        if (flag) flag.value = '0';
                                        if (title) title.textContent = 'Add Staff Member';
                                        if (submit) submit.textContent = 'Add Staff';
                                        if (sel) sel.required = true;
                                        if (roleGroup) roleGroup.style.display = 'block';
                                        if (billingToggle) billingToggle.style.display = 'none';
                                        if (billingCheckbox) billingCheckbox.checked = false;

                                        m.style.display = 'flex';
                                    }
                                    function openAddAdminModal() {
                                        var m = document.getElementById('add-staff-modal');
                                        var sel = m.querySelector('select[name="role_id"]');
                                        var flag = document.getElementById('create-as-admin-flag');
                                        var title = document.getElementById('add-staff-modal-title');
                                        var submit = document.getElementById('add-staff-submit-btn');
                                        var roleGroup = document.getElementById('role-group');
                                        var billingToggle = document.getElementById('billing-toggle-group');
                                        var billingCheckbox = document.getElementById('create-can-manage-billing');

                                        if (flag) flag.value = '1';
                                        if (title) title.textContent = 'Create Admin Account';
                                        if (submit) submit.textContent = 'Create Admin';
                                        if (sel) sel.required = false;
                                        if (roleGroup) roleGroup.style.display = 'none';
                                        if (billingToggle) billingToggle.style.display = 'flex';
                                        if (billingCheckbox) billingCheckbox.checked = true;

                                        m.style.display = 'flex';
                                    }
                                </script>
                            </div>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Last Login</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($staff_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                                <span class="material-symbols-rounded" style="font-size: 36px; display: block; margin-bottom: 0.5rem;">person_add</span>
                                                No staff accounts yet. Click "Add Staff" to invite your first employee.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($staff_list as $staff): ?>
                                                <tr>
                                                    <td>
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <div class="avatar" style="width: 32px; height: 32px; font-size: 0.85rem; font-weight: 600;">
                                                                <?php echo htmlspecialchars(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                                                            </div>
                                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td class="text-muted"><?php echo htmlspecialchars($staff['email']); ?></td>
                                                    <td>
                                                        <span class="badge badge-gray"><?php echo htmlspecialchars($staff['role_name']); ?></span>
                                                    </td>
                                                    <td class="text-muted">
                                                        <span title="<?php echo htmlspecialchars(mf_last_login_exact_label($staff['last_login'] ?? null), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars(mf_humanize_last_login($staff['last_login'] ?? null), ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            $status_class = 'badge-gray';
                                                            if ($staff['status'] === 'Active') $status_class = 'badge-green';
                                                            if ($staff['status'] === 'Suspended') $status_class = 'badge-red';
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($staff['status']); ?></span>
                                                    </td>
                                                    <td>
                                                         <div style="display:flex; gap:0.4rem; align-items:center;">
                                                             <!-- Edit -->
                                                             <button type="button" class="icon-btn btn-edit-staff" title="Edit Staff"
                                                                 data-user-id="<?php echo htmlspecialchars($staff['user_id']); ?>"
                                                                 data-first-name="<?php echo htmlspecialchars($staff['first_name']); ?>"
                                                                 data-last-name="<?php echo htmlspecialchars($staff['last_name']); ?>"
                                                                 data-email="<?php echo htmlspecialchars($staff['email']); ?>"
                                                                 data-role-id="<?php echo htmlspecialchars($staff['role_id']); ?>"
                                                                 data-can-manage-billing="<?php echo !empty($staff['can_manage_billing']) ? '1' : '0'; ?>"
                                                                 data-status="<?php echo htmlspecialchars($staff['status']); ?>">
                                                                 <span class="material-symbols-rounded" style="font-size:18px;">edit</span>
                                                             </button>

                                                             <!-- Toggle Status -->
                                                             <form method="POST" action="admin.php" style="display:inline;" data-confirm-title="Update Staff Status" data-confirm-message="Change status of this staff member?" data-confirm-button="Confirm">
                                                                 <input type="hidden" name="action" value="toggle_staff_status">
                                                                 <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($staff['user_id']); ?>">
                                                                 <?php if ($staff['status'] === 'Active'): ?>
                                                                     <input type="hidden" name="new_status" value="Suspended">
                                                                     <button type="submit" class="icon-btn" title="Suspend Staff" style="color: #ef4444;">
                                                                         <span class="material-symbols-rounded" style="font-size:18px;">block</span>
                                                                     </button>
                                                                 <?php else: ?>
                                                                     <input type="hidden" name="new_status" value="Active">
                                                                     <button type="submit" class="icon-btn" title="Activate Staff" style="color: #22c55e;">
                                                                         <span class="material-symbols-rounded" style="font-size:18px;">check_circle</span>
                                                                     </button>
                                                                 <?php endif; ?>
                                                             </form>
                                                             <!-- Resend Email (Optional, if needed) -->
                                                             <!-- <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Resend invitation email to this staff member?');">
                                                                 <input type="hidden" name="action" value="resend_staff_invite">
                                                                 <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($staff['user_id']); ?>">
                                                                 <button type="submit" class="icon-btn" title="Resend Invitation" style="color: var(--primary-color);">
                                                                     <span class="material-symbols-rounded" style="font-size:18px;">mail</span>
                                                                 </button>
                                                             </form> -->
                                                         </div>
                                                     </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="roles-list" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'roles-list') ? 'active' : ''; ?>">
                        
                        <?php if (isset($_SESSION['admin_error'])): ?>
                        <div style="margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-weight: 500;">
                            <?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?>
                        </div>
                        <?php endif; ?>

                        <div class="roles-layout">
                            <!-- Left Sidebar: Role List -->
                            <div class="roles-sidebar card">
                                <div class="card-header-flex">
                                    <h3>Roles</h3>
                                    <button type="button" class="btn btn-primary btn-sm" id="btn-create-role" onclick="openCreateRoleModal()" title="Add New Role">
                                        <span class="material-symbols-rounded">add</span>
                                        Add Role
                                    </button>
                                    <script>
                                        // Robust fallback for opening/closing the Create Role modal as an overlay popup.
                                        (function () {
                                            var trigger = document.getElementById('btn-create-role');

                                            function getModal() {
                                                return document.getElementById('create-role-modal');
                                            }

                                            window.openCreateRoleModal = function () {
                                                var modal = getModal();
                                                if (!modal) return;
                                                modal.style.display = 'flex';
                                                modal.setAttribute('aria-hidden', 'false');
                                                document.body.style.overflow = 'hidden';
                                            };

                                            window.closeCreateRoleModal = function () {
                                                var modal = getModal();
                                                if (!modal) return;
                                                modal.style.display = 'none';
                                                modal.setAttribute('aria-hidden', 'true');
                                                document.body.style.overflow = '';
                                            };

                                            if (trigger) {
                                                trigger.addEventListener('click', function (event) {
                                                    event.preventDefault();
                                                    window.openCreateRoleModal();
                                                });
                                            }

                                            document.addEventListener('click', function (event) {
                                                var modal = getModal();
                                                if (!modal || modal.style.display === 'none') return;
                                                if (event.target === modal) {
                                                    window.closeCreateRoleModal();
                                                }
                                            });

                                            document.addEventListener('keydown', function (event) {
                                                if (event.key === 'Escape') {
                                                    window.closeCreateRoleModal();
                                                }
                                            });
                                        })();
                                    </script>
                                </div>
                                <div class="roles-sidebar-tools">
                                    <label class="roles-search-wrap" for="role-filter-input">
                                        <span class="material-symbols-rounded">search</span>
                                        <input type="search" id="role-filter-input" class="roles-search-input" placeholder="Search roles by name or purpose">
                                    </label>
                                    <div class="roles-sidebar-meta">
                                        <span><?php echo count($role_management_roles); ?> roles</span>
                                        <span><?php echo (int)$total_visible_permissions; ?> permissions tracked</span>
                                    </div>
                                </div>
                                <div class="role-list-container" id="role-list-container">
                                    <?php if (empty($role_management_roles)): ?>
                                        <div style="padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">No roles found.</div>
                                    <?php else: ?>
                                        <?php foreach ($role_management_roles as $role): ?>
                                            <?php
                                                $role_permission_total = (int)($role_permission_totals[(int)$role['role_id']] ?? 0);
                                                $role_description_preview = trim((string)($role['role_description'] ?? ''));
                                                $role_search_text = strtolower(trim(($role['role_name'] ?? '') . ' ' . $role_description_preview));
                                            ?>
                                            <a href="#" 
                                               data-role-id="<?php echo $role['role_id']; ?>"
                                               data-role-search="<?php echo htmlspecialchars($role_search_text); ?>"
                                               class="role-list-item <?php echo ($active_role_id == $role['role_id']) ? 'active' : ''; ?>" 
                                               style="text-decoration: none; color: inherit;">
                                                <span class="role-list-main">
                                                    <span class="role-list-name"><?php echo htmlspecialchars($role['role_name']); ?></span>
                                                    <span class="role-list-meta"><?php echo $role_permission_total; ?> of <?php echo (int)$total_visible_permissions; ?> permissions</span>
                                                    <?php if ($role_description_preview !== ''): ?>
                                                        <span class="role-list-description"><?php echo htmlspecialchars($role_description_preview); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="role-list-icon-wrap">
                                                    <?php if ((int)$role['is_system_role']): ?>
                                                        <span class="material-symbols-rounded" title="System Role">shield</span>
                                                    <?php else: ?>
                                                        <span class="material-symbols-rounded">person</span>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div id="role-filter-empty" class="roles-empty-search" hidden>
                                    <span class="material-symbols-rounded">search_off</span>
                                    <p>No matching roles found.</p>
                                </div>
                            </div>

                            <!-- Right Content: Permissions -->
                            <div class="roles-content card">
                                <?php if (empty($role_management_roles)): ?>
                                    <div id="empty-permissions-state" style="text-align: center; padding: 3rem 1rem;">
                                        <span class="material-symbols-rounded" style="font-size: 48px; color: var(--border-color); margin-bottom: 1rem;">tune</span>
                                        <p style="color: var(--text-muted);">Select a role from the sidebar to view and edit its permissions.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($role_management_roles as $role_panel): 
                                        $is_active_panel = ($role_panel['role_id'] == $active_role_id);
                                        $is_admin_role = ((int)$role_panel['is_system_role'] && $role_panel['role_name'] === 'Admin');
                                        $panel_active_codes = $active_codes_by_role[$role_panel['role_id']] ?? [];
                                        $panel_selected_permissions = $is_admin_role ? $total_visible_permissions : (int)($role_permission_totals[(int)$role_panel['role_id']] ?? 0);
                                    ?>
                                        <div class="role-permissions-panel" id="role-panel-<?php echo $role_panel['role_id']; ?>" style="display: <?php echo $is_active_panel ? 'block' : 'none'; ?>;">
                                            <div class="roles-content-header">
                                                <div class="role-header-title">
                                                    <h3><?php echo htmlspecialchars($role_panel['role_name']); ?></h3>
                                                    <?php if ((int)$role_panel['is_system_role']): ?>
                                                        <span class="badge badge-blue">System Role</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-gray">Custom Role</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!(int)$role_panel['is_system_role']): ?>
                                                <form method="POST" action="admin.php" data-confirm-title="Delete Role" data-confirm-message="Are you sure you want to delete this role? Users assigned to it will lose permissions immediately." data-confirm-button="Delete Role">
                                                    <input type="hidden" name="action" value="delete_role">
                                                    <input type="hidden" name="role_id" value="<?php echo $role_panel['role_id']; ?>">
                                                    <button type="submit" class="btn btn-sm" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);">
                                                        <span class="material-symbols-rounded">delete</span> Delete Role
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="text-muted" style="margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px solid var(--border-color);">
                                                Organize access by module, search quickly, and apply bulk updates safely for this role. <?php if ($is_admin_role) echo '<br><strong>Note: This role keeps all permissions enabled and cannot be edited.</strong>'; ?>
                                            </p>

                                            <form method="POST" action="admin.php">
                                                <input type="hidden" name="action" value="save_permissions">
                                                <input type="hidden" name="role_id" value="<?php echo $role_panel['role_id']; ?>">

                                                <div class="permissions-toolbar" data-role-id="<?php echo $role_panel['role_id']; ?>">
                                                    <label class="permissions-search-wrap" for="permission-filter-<?php echo $role_panel['role_id']; ?>">
                                                        <span class="material-symbols-rounded">search</span>
                                                        <input
                                                            type="search"
                                                            id="permission-filter-<?php echo $role_panel['role_id']; ?>"
                                                            class="permissions-filter-input"
                                                            data-role-id="<?php echo $role_panel['role_id']; ?>"
                                                            placeholder="Search permissions by action, help text, or module"
                                                        >
                                                    </label>
                                                    
                                                </div>

                                                <div id="permissions-container-<?php echo $role_panel['role_id']; ?>" class="permissions-grid">
                                                    <?php 
                                                    $module_icons = [
                                                        'Applications' => 'description',
                                                        'Clients' => 'group',
                                                        'Loans' => 'real_estate_agent',
                                                        'Payments' => 'payments',
                                                        'Reports' => 'analytics',
                                                        'Roles' => 'shield_person',
                                                        'Users' => 'manage_accounts',
                                                        'System' => 'settings'
                                                    ];
                                                    foreach ($grouped_permissions as $moduleName => $perms): 
                                                    $icon = $module_icons[$moduleName] ?? 'tune';
                                                    $module_total_permissions = count($perms);
                                                    $module_selected_permissions = 0;
                                                    foreach ($perms as $module_perm) {
                                                        if ($is_admin_role || in_array($module_perm['permission_code'], $panel_active_codes, true)) {
                                                            $module_selected_permissions++;
                                                        }
                                                    }
                                                    ?>
                                                        <div class="permission-module" data-module-name="<?php echo htmlspecialchars(strtolower((string)$moduleName)); ?>">
                                                            <div class="permission-module-header">
                                                                <h4>
                                                                    <span class="material-symbols-rounded"><?php echo $icon; ?></span>
                                                                    <?php echo htmlspecialchars($moduleName); ?>
                                                                </h4>
                                                                <div class="permission-module-actions">
                                                                    <span class="permission-module-count">
                                                                        <strong class="permission-module-visible-count"><?php echo (int)$module_selected_permissions; ?></strong>/<?php echo (int)$module_total_permissions; ?>
                                                                    </span>
                                                                    <?php if (!$is_admin_role): ?>
                                                                        <button type="button" class="module-action-btn permission-module-toggle" data-bulk="all">All</button>
                                                                        <button type="button" class="module-action-btn permission-module-toggle" data-bulk="none">None</button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="toggle-list">
                                                                <?php foreach ($perms as $p): 
                                                                    $is_checked = $is_admin_role || in_array($p['permission_code'], $panel_active_codes);
                                                                    $permission_help_text = $permission_capability_map[$p['permission_code']] ?? 'Members with this permission can perform the selected action in this module.';
                                                                    $permission_search_index = strtolower(trim(($p['description'] ?? '') . ' ' . $permission_help_text . ' ' . $moduleName . ' ' . ($p['permission_code'] ?? '')));
                                                                ?>
                                                                    <div class="toggle-item" data-permission-search="<?php echo htmlspecialchars($permission_search_index); ?>">
                                                                        <div class="toggle-info">
                                                                            <h4 style="margin-bottom: 4px; font-weight: 600;"><?php echo htmlspecialchars($p['description']); ?></h4>
                                                                            <p style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars($permission_help_text); ?></p>
                                                                            <span class="permission-code"><?php echo htmlspecialchars(str_replace('_', ' ', (string)$p['permission_code'])); ?></span>
                                                                        </div>
                                                                        <label class="switch">
                                                                            <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($p['permission_code']); ?>" <?php echo $is_checked ? 'checked' : ''; ?> <?php echo $is_admin_role ? 'disabled' : ''; ?>>
                                                                            <span class="slider round <?php echo $is_admin_role ? 'disabled' : ''; ?>"></span>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div class="permissions-empty-search" id="permissions-empty-search-<?php echo $role_panel['role_id']; ?>" hidden>
                                                    <span class="material-symbols-rounded">search_off</span>
                                                    <p>No permissions match your search.</p>
                                                </div>

                                                <?php if (!$is_admin_role): ?>
                                                <div class="action-bar roles-save-bar" style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ═══ WEBSITE EDITOR ═══ -->
                <section id="website" class="view-section <?php echo $active_view === 'website' ? 'active' : ''; ?>">
                    <div class="we-editor-card" style="height: calc(100vh - 120px); display: flex; flex-direction: column; margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h3 style="margin-bottom: 4px;">Website Live Preview</h3>
                                <p class="we-card-desc" style="margin-bottom: 0;">View your public-facing tenant website below. Use the builder to make live changes.</p>
                            </div>
                            <div style="display: flex; gap: 12px;">
                                <a href="<?php echo $e($site_url); ?>" target="_blank" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">open_in_new</span> Open Public Site
                                </a>
                                <a href="website_editor.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 10px 24px; font-weight: 600;">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">edit</span> Edit Website
                                </a>
                            </div>
                        </div>
                        <div style="flex: 1; border-radius: var(--radius); overflow: hidden; border: 1px solid var(--border-color, #e2e8f0); position: relative; background: #f8fafc; box-shadow: inset 0 2px 10px rgba(0,0,0,0.02);">
                            <iframe id="we-preview-iframe" src="<?php echo $e($site_url); ?>" style="width: 100%; height: 100%; border: none;"></iframe>
                        </div>
                    </div>
                </section>
                <!-- ═══ LOAN PRODUCTS SETTINGS ═══ -->
                <section id="loan_products" class="view-section <?php echo $active_view === 'loan_products' ? 'active' : ''; ?>">
                    <div class="section-intro">
                        <h2>Loan Products</h2>
                        <p class="text-muted">Configure the loan products, rates, terms, and borrower-facing offer details your team publishes.</p>
                    </div>
                    <div class="card">
                        <div class="loan-products-builder-header">
                            <div>
                                <h3><span class="material-symbols-rounded">inventory_2</span> Loan Product Builder</h3>
                                <p class="text-muted">Shape the offer, pricing, terms, and charges in one place while the borrower preview updates live beside you.</p>
                            </div>
                            <a href="admin.php?tab=loan_products&loan_product_mode=new" class="btn btn-outline loan-products-new-btn">
                                <span class="material-symbols-rounded" style="font-size:18px;">add</span> New Product
                            </a>
                        </div>

                        <div class="tabs loan-products-tabs">
                            <a href="admin.php?tab=loan_products" class="tab-btn active" data-tab="existing-loan-products">Loan Products</a>
                        </div>

                        <?php if ($loan_products_form_open): ?>
                        <a href="admin.php?tab=loan_products" class="loan-products-modal-backdrop" aria-label="Close loan product form"></a>
                        <div id="loan-products-form-panel" class="loan-products-modal-panel" role="dialog" aria-modal="true" aria-labelledby="loan-products-modal-title">
                        <div class="loan-products-modal-shell" tabindex="2" data-loan-products-modal>
                            <div class="loan-products-modal-head">
                                <div>
                                    <h3 id="loan-products-modal-title"><?php echo !empty($existing_product) ? 'Edit Loan Product' : 'Create Loan Product'; ?></h3>
                                    <p class="text-muted"><?php echo !empty($existing_product) ? 'Update rates, terms, and charges while validating the borrower preview.' : 'Define rates, terms, and charges for a new product while validating the borrower preview.'; ?></p>
                                </div>
                                <a href="admin.php?tab=loan_products" class="loan-products-modal-close" aria-label="Close loan product form">
                                    <span class="material-symbols-rounded">close</span>
                                </a>
                            </div>
                        <div class="loan-products-builder-layout">
                            <div class="loan-products-form-column">
                                <form method="POST" action="admin.php" id="loan-products-form" class="loan-form-stack">
                            <input type="hidden" name="action" value="save_loan_products">
                            <input type="hidden" name="loan_product_id" value="<?php echo (int)($existing_product['product_id'] ?? 0); ?>">
                            <h4 class="loan-form-section-title"><span class="material-symbols-rounded">inventory_2</span> Product Basics</h4>
                            <div class="loan-form-section-grid loan-form-section-grid-wide">
                                <div class="form-group">
                                    <label>Product Name <span style="color:#dc2626;">*</span></label>
                                    <input type="text" class="form-control" name="product_name" value="<?php echo htmlspecialchars($lp_form['product_name']); ?>" placeholder="e.g. Personal Cash Loan" required>
                                </div>
                                <div class="form-group">
                                    <label>Product Type <span style="color:#dc2626;">*</span></label>
                                    <select class="form-control" name="product_type" id="loan-product-type-select">
                                        <?php foreach ($loan_product_type_options as $loan_product_type_option): ?>
                                            <option value="<?php echo htmlspecialchars($loan_product_type_option); ?>" <?php echo $lp_form['product_type'] === $loan_product_type_option ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($loan_product_type_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="Others" <?php echo $lp_form['product_type'] === 'Others' ? 'selected' : ''; ?>>Others</option>
                                    </select>
                                </div>
                                <div class="form-group <?php echo $lp_form['product_type'] === 'Others' ? '' : 'hidden-input'; ?>" id="loan-custom-product-type-wrap">
                                    <label>Custom Product Type <span style="color:#dc2626;">*</span></label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        name="custom_product_type"
                                        id="loan-custom-product-type"
                                        value="<?php echo htmlspecialchars((string)($lp_form['custom_product_type'] ?? '')); ?>"
                                        placeholder="e.g. Salary Loan"
                                        <?php echo $lp_form['product_type'] === 'Others' ? 'required' : ''; ?>
                                    >
                                </div>
                                <div class="form-group loan-form-section-span-full">
                                    <label>Description</label>
                                    <textarea class="form-control" name="description" rows="2" placeholder="Brief description of this loan product..."><?php echo htmlspecialchars($lp_form['description']); ?></textarea>
                                </div>
                            </div>
                            
                            <h4 class="loan-form-section-title"><span class="material-symbols-rounded">percent</span> Interest & Rates</h4>
                            <div class="loan-form-section-grid">
                                <div class="form-group">
                                    <label>Interest Rate (%) <span style="color:#dc2626;">*</span></label>
                                    <input type="number" class="form-control" name="interest_rate" value="<?php echo htmlspecialchars($lp_form['interest_rate']); ?>" step="0.01" min="0" max="100" required>
                                </div>
                                <div class="form-group">
                                    <label>Interest Type <span style="color:#dc2626;">*</span></label>
                                    <select class="form-control" name="interest_type">
                                        <option value="Diminishing" <?php echo $lp_form['interest_type'] === 'Diminishing' ? 'selected' : ''; ?>>Diminishing</option>
                                        <option value="Fixed" <?php echo $lp_form['interest_type'] === 'Fixed' ? 'selected' : ''; ?>>Fixed</option>
                                        <option value="Flat" <?php echo $lp_form['interest_type'] === 'Flat' ? 'selected' : ''; ?>>Flat</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Penalty Rate (%)</label>
                                    <input type="number" class="form-control" name="penalty_rate" value="<?php echo htmlspecialchars($lp_form['penalty_rate']); ?>" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Penalty Type</label>
                                    <select class="form-control" name="penalty_type">
                                        <option value="Daily" <?php echo $lp_form['penalty_type'] === 'Daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="Monthly" <?php echo $lp_form['penalty_type'] === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="Flat" <?php echo $lp_form['penalty_type'] === 'Flat' ? 'selected' : ''; ?>>Flat</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Grace Period (days)</label>
                                    <input type="number" class="form-control" name="grace_period_days" value="<?php echo htmlspecialchars($lp_form['grace_period_days']); ?>" min="0">
                                </div>
                            </div>
                            
                            <h4 class="loan-form-section-title"><span class="material-symbols-rounded">payments</span> Amounts & Terms</h4>
                            <div class="loan-form-section-grid">
                                <div class="form-group">
                                    <label>Minimum Amount <span style="color:#dc2626;">*</span></label>
                                    <div class="credit-input-with-prefix">
                                        <span class="credit-input-prefix">&#8369;</span>
                                        <input type="number" class="form-control" name="min_amount" value="<?php echo htmlspecialchars($lp_form['min_amount']); ?>" step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Maximum Amount <span style="color:#dc2626;">*</span></label>
                                    <div class="credit-input-with-prefix">
                                        <span class="credit-input-prefix">&#8369;</span>
                                        <input type="number" class="form-control" name="max_amount" value="<?php echo htmlspecialchars($lp_form['max_amount']); ?>" step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Min Term (months) <span style="color:#dc2626;">*</span></label>
                                    <input type="number" class="form-control" name="min_term_months" value="<?php echo htmlspecialchars($lp_form['min_term_months']); ?>" min="1" required>
                                </div>
                                <div class="form-group">
                                    <label>Max Term (months) <span style="color:#dc2626;">*</span></label>
                                    <input type="number" class="form-control" name="max_term_months" value="<?php echo htmlspecialchars($lp_form['max_term_months']); ?>" min="1" required>
                                </div>
                            </div>

                            <h4 class="loan-form-section-title"><span class="material-symbols-rounded">receipt_long</span> Fees & Charges</h4>
                            <div class="loan-form-section-grid loan-form-section-grid-last">
                                <div class="form-group">
                                    <label>Processing Fee (%)</label>
                                    <input type="number" class="form-control" name="processing_fee_percentage" value="<?php echo htmlspecialchars($lp_form['processing_fee_percentage']); ?>" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Insurance Fee (%)</label>
                                    <input type="number" class="form-control" name="insurance_fee_percentage" value="<?php echo htmlspecialchars($lp_form['insurance_fee_percentage']); ?>" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Service Charge</label>
                                    <div class="credit-input-with-prefix">
                                        <span class="credit-input-prefix">&#8369;</span>
                                        <input type="number" class="form-control" name="service_charge" value="<?php echo htmlspecialchars($lp_form['service_charge']); ?>" step="0.01" min="0">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Documentary Stamp</label>
                                    <div class="credit-input-with-prefix">
                                        <span class="credit-input-prefix">&#8369;</span>
                                        <input type="number" class="form-control" name="documentary_stamp" value="<?php echo htmlspecialchars($lp_form['documentary_stamp']); ?>" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="action-bar loan-products-action-bar" style="margin-top: 24px;">
                                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                                    <span class="material-symbols-rounded" style="font-size:18px;">save</span> Save Loan Product
                                </button>
                                <a href="admin.php?tab=loan_products" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                                    <span class="material-symbols-rounded" style="font-size:18px;">close</span> Cancel
                                </a>
                            </div>
                        </form>
                            </div>

                            <aside class="loan-live-preview" data-loan-preview>
                                <div class="loan-live-preview-header">
                                    <span class="loan-live-preview-label"><span class="material-symbols-rounded">visibility</span>Live Borrower Preview</span>
                                    <h4>Borrower-facing offer snapshot</h4>
                                    <p>As you adjust the product configuration, this preview estimates what a borrower will understand about the offer.</p>
                                </div>

                                <div class="loan-borrower-preview-shell">
                                    <div class="loan-borrower-preview-topbar">
                                        <span>Mobile application card</span>
                                        <span class="loan-borrower-preview-status">Ready to apply</span>
                                    </div>
                                    <div class="loan-borrower-preview-card">
                                        <div class="loan-borrower-preview-type" data-loan-preview-bind="product-type">Personal Loan</div>
                                        <h5 data-loan-preview-bind="product-name">Personal Cash Loan</h5>
                                        <p data-loan-preview-bind="description">Borrowers will see a short description explaining what this loan is best for.</p>
                                        <div class="loan-borrower-preview-chips">
                                            <span class="loan-preview-chip loan-preview-chip-primary" data-loan-preview-bind="interest-chip">3.00% Diminishing</span>
                                            <span class="loan-preview-chip" data-loan-preview-bind="grace-chip">3 days grace period</span>
                                            <span class="loan-preview-chip" data-loan-preview-bind="penalty">0.50% daily</span>
                                        </div>
                                        <div class="loan-borrower-preview-stats">
                                            <div class="loan-borrower-preview-stat">
                                                <span>Maximum amount</span>
                                                <strong data-loan-preview-bind="max-amount">₱100,000.00</strong>
                                            </div>
                                            <div class="loan-borrower-preview-stat">
                                                <span>Term range</span>
                                                <strong data-loan-preview-bind="term-range">3-24 months</strong>
                                            </div>
                                            <div class="loan-borrower-preview-stat">
                                                <span>Est. cash release</span>
                                                <strong data-loan-preview-bind="cash-release">₱0.00</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="loan-preview-simulator">
                                        <div class="loan-preview-control">
                                            <div class="loan-preview-control-head">
                                                <strong>Sample loan amount</strong>
                                                <span data-loan-preview-bind="selected-amount">₱0.00</span>
                                            </div>
                                            <input type="range" id="loan-preview-amount" class="loan-preview-range" min="0" max="0" step="100">
                                            <div class="loan-preview-range-meta">
                                                <span data-loan-preview-bind="min-amount">₱0.00</span>
                                                <span data-loan-preview-bind="max-amount-range">₱0.00</span>
                                            </div>
                                        </div>

                                        <div class="loan-preview-control">
                                            <div class="loan-preview-control-head">
                                                <strong>Sample term</strong>
                                                <span data-loan-preview-bind="selected-term">0 months</span>
                                            </div>
                                            <input type="range" id="loan-preview-term" class="loan-preview-range" min="1" max="1" step="1">
                                            <div class="loan-preview-range-meta">
                                                <span data-loan-preview-bind="min-term">1 mo</span>
                                                <span data-loan-preview-bind="max-term">1 mo</span>
                                            </div>
                                        </div>

                                        <div class="loan-preview-summary-card">
                                            <div class="loan-preview-summary-row">
                                                <span>Estimated installment</span>
                                                <strong data-loan-preview-bind="estimated-installment">₱0.00</strong>
                                            </div>
                                            <div class="loan-preview-summary-row">
                                                <span>Total repayment</span>
                                                <strong data-loan-preview-bind="total-repayment">₱0.00</strong>
                                            </div>
                                            <div class="loan-preview-summary-row">
                                                <span>Total upfront charges</span>
                                                <strong data-loan-preview-bind="charges-total">₱0.00</strong>
                                            </div>
                                            <div class="loan-preview-summary-row">
                                                <span>Estimated cash release</span>
                                                <strong data-loan-preview-bind="cash-release">₱0.00</strong>
                                            </div>
                                        </div>

                                        <div class="loan-preview-fees-grid">
                                            <div class="loan-preview-fee-card">
                                                <span>Processing fee</span>
                                                <strong data-loan-preview-bind="processing-fee-value">₱0.00</strong>
                                            </div>
                                            <div class="loan-preview-fee-card">
                                                <span>Insurance fee</span>
                                                <strong data-loan-preview-bind="insurance-fee-value">₱0.00</strong>
                                            </div>
                                            <div class="loan-preview-fee-card">
                                                <span>Service charge</span>
                                                <strong data-loan-preview-bind="service-charge-value">₱0.00</strong>
                                            </div>
                                            <div class="loan-preview-fee-card">
                                                <span>Documentary stamp</span>
                                                <strong data-loan-preview-bind="doc-stamp-value">₱0.00</strong>
                                            </div>
                                        </div>

                                        <p class="loan-preview-footnote">This preview is an estimate for the borrower experience only. The final repayment and approval still follow your saved product rules.</p>
                                    </div>
                                </div>
                            </aside>
                        </div>
                        </div>
                        </div>
                        <?php endif; ?>

                        <div id="existing-loan-products" class="tab-content active">
                            <?php if (empty($loan_product_records)): ?>
                                <div class="credit-summary-empty" style="margin-top: 8px;">No loan products saved yet. Click + New Product to create your first one.</div>
                            <?php else: ?>
                                <div class="loan-products-existing-grid">
                                    <?php foreach ($loan_product_records as $loan_product_record): ?>
                                        <?php
                                        $loan_record_id = (int)($loan_product_record['product_id'] ?? 0);
                                        $loan_record_active = (int)($loan_product_record['is_active'] ?? 1) === 1;
                                        ?>
                                        <article class="loan-product-record-card <?php echo $loan_record_id === $selected_loan_product_id ? 'is-active' : ''; ?>">
                                            <div class="loan-product-record-head">
                                                <div>
                                                    <div class="loan-product-record-type"><?php echo htmlspecialchars((string)($loan_product_record['product_type'] ?? 'Loan Product')); ?></div>
                                                    <h4><?php echo htmlspecialchars((string)($loan_product_record['product_name'] ?? 'Untitled product')); ?></h4>
                                                </div>
                                                <span class="loan-product-record-badge <?php echo $loan_record_active ? 'is-active' : 'is-inactive'; ?>">
                                                    <?php echo $loan_record_active ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </div>
                                            <p class="loan-product-record-desc"><?php echo htmlspecialchars(trim((string)($loan_product_record['description'] ?? '')) !== '' ? (string)$loan_product_record['description'] : 'No product description added yet.'); ?></p>
                                            <div class="loan-product-record-metrics">
                                                <div class="loan-product-record-metric">
                                                    <span>Amount range</span>
                                                    <strong>&#8369;<?php echo number_format((float)($loan_product_record['min_amount'] ?? 0), 2); ?> to &#8369;<?php echo number_format((float)($loan_product_record['max_amount'] ?? 0), 2); ?></strong>
                                                </div>
                                                <div class="loan-product-record-metric">
                                                    <span>Interest</span>
                                                    <strong><?php echo number_format((float)($loan_product_record['interest_rate'] ?? 0), 2); ?>% <?php echo htmlspecialchars((string)($loan_product_record['interest_type'] ?? '')); ?></strong>
                                                </div>
                                                <div class="loan-product-record-metric">
                                                    <span>Term</span>
                                                    <strong><?php echo (int)($loan_product_record['min_term_months'] ?? 0); ?> to <?php echo (int)($loan_product_record['max_term_months'] ?? 0); ?> months</strong>
                                                </div>
                                                <div class="loan-product-record-metric">
                                                    <span>Charges</span>
                                                    <strong><?php echo number_format((float)($loan_product_record['processing_fee_percentage'] ?? 0), 2); ?>% processing</strong>
                                                </div>
                                            </div>
                                            <div class="loan-product-record-meta">
                                                <span>Penalty: <?php echo number_format((float)($loan_product_record['penalty_rate'] ?? 0), 2); ?>% <?php echo htmlspecialchars((string)($loan_product_record['penalty_type'] ?? '')); ?></span>
                                                <span>Grace: <?php echo (int)($loan_product_record['grace_period_days'] ?? 0); ?> day(s)</span>
                                            </div>
                                            <div class="loan-product-record-actions">
                                                <a href="admin.php?tab=loan_products&loan_product_id=<?php echo $loan_record_id; ?>" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;">
                                                    <span class="material-symbols-rounded" style="font-size:18px;">edit</span> Edit Product
                                                </a>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <?php if (false): ?>
                <!-- ═══ CREDIT SETTINGS ═══ -->
                <?php
                $credit_approval_mode = (string)($credit_limit_rules['workflow']['approval_mode'] ?? 'semi');
                $credit_approval_label = $credit_approval_mode === 'auto'
                    ? 'Fully Automatic'
                    : ($credit_approval_mode === 'manual' ? 'Fully Manual' : 'Semi-Automatic');
                $credit_base_limit_amount = (float)($credit_limit_rules['initial_limits']['base_limit_default'] ?? 5000);
                $credit_ci_required = (string)($cs_form['require_ci'] ?? '0') === '1';
                ?>
                <section id="credit_settings" class="view-section <?php echo $active_view === 'credit_settings' ? 'active' : ''; ?>">
                    <div class="section-intro credit-policy-intro">
                        <div>
                            <h2>Credit Policy Workspace</h2>
                            <p class="text-muted">Separate how borrowers are scored from how starting limits are assigned, then review both rules in one place before saving.</p>
                        </div>
                        <div class="credit-policy-badges">
                            <span class="receipt-filter-pill receipt-filter-pill-primary">Min score <strong id="credit-overview-min-score"><?php echo htmlspecialchars((string)$cs_form['minimum_credit_score']); ?>/<?php echo (int)$credit_policy_score_ceiling; ?></strong></span>
                            <span class="receipt-filter-pill">Approval <strong id="credit-overview-approval"><?php echo htmlspecialchars($credit_approval_label); ?></strong></span>
                            <span class="receipt-filter-pill">Base limit <strong id="credit-overview-base-limit"><?php echo 'PHP ' . number_format($credit_base_limit_amount, 2); ?></strong></span>
                            <span class="receipt-filter-pill">Investigation <strong id="credit-overview-ci"><?php echo $credit_ci_required ? 'Required' : 'Optional'; ?></strong></span>
                        </div>
                    </div>
                    <div class="credit-settings-stack">
                        <div class="credit-settings-overview-grid">
                            <form method="POST" action="admin.php" class="card credit-scoring-card" id="credit-scoring-form">
                                <input type="hidden" name="action" value="save_credit_settings">

                                <div class="credit-engine-header">
                                    <div>
                                        <h3>Scoring Model</h3>
                                        <p class="text-muted">Define the approval threshold, auto-reject floor, and score distribution used before a borrower reaches the limit engine.</p>
                                    </div>
                                    <div class="credit-preset-toolbar">
                                        <span class="credit-preset-label">Quick presets</span>
                                        <div class="credit-preset-buttons">
                                            <button type="button" class="btn btn-sm btn-outline credit-preset-btn is-active" data-credit-preset="balanced">Balanced</button>
                                            <button type="button" class="btn btn-sm btn-outline credit-preset-btn" data-credit-preset="conservative">Conservative</button>
                                            <button type="button" class="btn btn-sm btn-outline credit-preset-btn" data-credit-preset="growth">Growth</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="credit-scoring-layout">
                                    <div class="credit-engine-panel">
                                        <div class="credit-engine-panel-head">
                                            <div>
                                                <h4>Approval Guardrails</h4>
                                                <p class="text-muted">Set the thresholds that decide who qualifies for review and who gets filtered out immediately.</p>
                                            </div>
                                        </div>
                                        <div class="credit-engine-inline-grid credit-engine-inline-grid-tight">
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label for="credit-minimum-score">Minimum Approval Score</label>
                                                <input type="number" class="form-control" id="credit-minimum-score" name="minimum_credit_score" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" step="1" value="<?php echo htmlspecialchars((string)$cs_form['minimum_credit_score']); ?>">
                                            </div>
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label for="credit-auto-reject-below">Auto-Reject Below</label>
                                                <input type="number" class="form-control" id="credit-auto-reject-below" name="auto_reject_below" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" step="1" value="<?php echo htmlspecialchars((string)$cs_form['auto_reject_below']); ?>">
                                            </div>
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <input type="hidden" name="require_ci" value="0">
                                                <label class="credit-toggle-row" for="credit-require-ci">
                                                    <span class="credit-toggle-copy">
                                                        <strong>Require Credit Investigation</strong>
                                                        <small>Require an investigation before final approval when your staff wants an extra validation layer.</small>
                                                    </span>
                                                    <input type="checkbox" id="credit-require-ci" name="require_ci" value="1" <?php echo $credit_ci_required ? 'checked' : ''; ?>>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="credit-engine-panel">
                                        <div class="credit-engine-panel-head credit-weight-panel-head">
                                            <div>
                                                <h4>Scoring Weights</h4>
                                                <p class="text-muted">Weights must total exactly 100% so each borrower score stays predictable and easy to explain.</p>
                                            </div>
                                            <div id="credit-weight-total-badge" class="credit-weight-total-badge <?php echo $credit_weight_total === 100 ? 'is-valid' : 'is-invalid'; ?>">
                                                <span>Total</span>
                                                <strong id="credit-weight-total-value"><?php echo (int)$credit_weight_total; ?>%</strong>
                                            </div>
                                        </div>

                                        <div class="credit-weight-grid">
                                            <div class="credit-weight-card">
                                                <label for="credit-weight-income">Income</label>
                                                <input type="number" class="form-control" id="credit-weight-income" name="income_weight" min="0" max="100" step="1" value="<?php echo htmlspecialchars((string)$cs_form['income_weight']); ?>">
                                                <small>Capacity to repay based on monthly earnings.</small>
                                            </div>
                                            <div class="credit-weight-card">
                                                <label for="credit-weight-employment">Employment</label>
                                                <input type="number" class="form-control" id="credit-weight-employment" name="employment_weight" min="0" max="100" step="1" value="<?php echo htmlspecialchars((string)$cs_form['employment_weight']); ?>">
                                                <small>Stability of the borrower's work or source of income.</small>
                                            </div>
                                            <div class="credit-weight-card">
                                                <label for="credit-weight-credit-history">Credit History</label>
                                                <input type="number" class="form-control" id="credit-weight-credit-history" name="credit_history_weight" min="0" max="100" step="1" value="<?php echo htmlspecialchars((string)$cs_form['credit_history_weight']); ?>">
                                                <small>Past repayment behavior and reliability over time.</small>
                                            </div>
                                            <div class="credit-weight-card">
                                                <label for="credit-weight-collateral">Collateral</label>
                                                <input type="number" class="form-control" id="credit-weight-collateral" name="collateral_weight" min="0" max="100" step="1" value="<?php echo htmlspecialchars((string)$cs_form['collateral_weight']); ?>">
                                                <small>Asset backing available to reduce lending risk.</small>
                                            </div>
                                            <div class="credit-weight-card">
                                                <label for="credit-weight-character">Character</label>
                                                <input type="number" class="form-control" id="credit-weight-character" name="character_weight" min="0" max="100" step="1" value="<?php echo htmlspecialchars((string)$cs_form['character_weight']); ?>">
                                                <small>Reputation, references, and field validation notes.</small>
                                            </div>
                                            <div class="credit-weight-card">
                                                <label for="credit-weight-business">Business</label>
                                                <input type="number" class="form-control" id="credit-weight-business" name="business_weight" min="0" max="100" step="1" value="<?php echo htmlspecialchars((string)$cs_form['business_weight']); ?>">
                                                <small>Business performance for enterprise and livelihood borrowers.</small>
                                            </div>
                                        </div>

                                        <p id="credit-weight-total-message" class="credit-weight-helper <?php echo $credit_weight_total === 100 ? 'is-valid' : 'is-invalid'; ?>">
                                            <?php if ($credit_weight_total === 100): ?>
                                                Weights are balanced at exactly 100%.
                                            <?php else: ?>
                                                Weights must total exactly 100%. Current total: <?php echo (int)$credit_weight_total; ?>%.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="action-bar" style="margin-top: 24px;">
                                    <div class="text-muted">Save the scoring model separately from the limit engine.</div>
                                    <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                                        <span class="material-symbols-rounded" style="font-size:18px;">save</span> Save Scoring Model
                                    </button>
                                </div>
                            </form>

                            <aside class="card credit-scoring-sidebar">
                                <div class="credit-engine-summary-card-head">
                                    <div>
                                        <h4>Scoring Snapshot</h4>
                                        <p class="text-muted">Review the approval policy in plain language while you tune the borrower score.</p>
                                    </div>
                                </div>

                                <div class="credit-engine-summary credit-engine-summary-compact credit-scoring-snapshot">
                                    <div class="credit-engine-summary-item">
                                        <span>Minimum Score</span>
                                        <strong id="credit-summary-min-score"><?php echo htmlspecialchars((string)$cs_form['minimum_credit_score']); ?>/<?php echo (int)$credit_policy_score_ceiling; ?></strong>
                                    </div>
                                    <div class="credit-engine-summary-item">
                                        <span>Auto-Reject</span>
                                        <strong id="credit-summary-auto-reject">Below <?php echo htmlspecialchars((string)$cs_form['auto_reject_below']); ?></strong>
                                    </div>
                                    <div class="credit-engine-summary-item">
                                        <span>Investigation Requirement</span>
                                        <strong id="credit-summary-ci"><?php echo $credit_ci_required ? 'Required' : 'Optional'; ?></strong>
                                    </div>
                                    <div class="credit-engine-summary-item">
                                        <span>Weights</span>
                                        <strong id="credit-summary-weight-status"><?php echo (int)$credit_weight_total; ?>% total</strong>
                                    </div>
                                </div>

                                <div class="credit-scoring-bars">
                                    <div class="credit-scoring-bar">
                                        <div class="credit-scoring-bar-top">
                                            <span>Income</span>
                                            <strong id="credit-weight-display-income"><?php echo htmlspecialchars((string)$cs_form['income_weight']); ?>%</strong>
                                        </div>
                                        <div class="credit-scoring-bar-track"><span id="credit-weight-bar-income" class="credit-scoring-bar-fill" style="width: <?php echo max(0, min(100, (int)$cs_form['income_weight'])); ?>%;"></span></div>
                                    </div>
                                    <div class="credit-scoring-bar">
                                        <div class="credit-scoring-bar-top">
                                            <span>Employment</span>
                                            <strong id="credit-weight-display-employment"><?php echo htmlspecialchars((string)$cs_form['employment_weight']); ?>%</strong>
                                        </div>
                                        <div class="credit-scoring-bar-track"><span id="credit-weight-bar-employment" class="credit-scoring-bar-fill" style="width: <?php echo max(0, min(100, (int)$cs_form['employment_weight'])); ?>%;"></span></div>
                                    </div>
                                    <div class="credit-scoring-bar">
                                        <div class="credit-scoring-bar-top">
                                            <span>Credit History</span>
                                            <strong id="credit-weight-display-credit-history"><?php echo htmlspecialchars((string)$cs_form['credit_history_weight']); ?>%</strong>
                                        </div>
                                        <div class="credit-scoring-bar-track"><span id="credit-weight-bar-credit-history" class="credit-scoring-bar-fill" style="width: <?php echo max(0, min(100, (int)$cs_form['credit_history_weight'])); ?>%;"></span></div>
                                    </div>
                                    <div class="credit-scoring-bar">
                                        <div class="credit-scoring-bar-top">
                                            <span>Collateral</span>
                                            <strong id="credit-weight-display-collateral"><?php echo htmlspecialchars((string)$cs_form['collateral_weight']); ?>%</strong>
                                        </div>
                                        <div class="credit-scoring-bar-track"><span id="credit-weight-bar-collateral" class="credit-scoring-bar-fill" style="width: <?php echo max(0, min(100, (int)$cs_form['collateral_weight'])); ?>%;"></span></div>
                                    </div>
                                    <div class="credit-scoring-bar">
                                        <div class="credit-scoring-bar-top">
                                            <span>Character</span>
                                            <strong id="credit-weight-display-character"><?php echo htmlspecialchars((string)$cs_form['character_weight']); ?>%</strong>
                                        </div>
                                        <div class="credit-scoring-bar-track"><span id="credit-weight-bar-character" class="credit-scoring-bar-fill" style="width: <?php echo max(0, min(100, (int)$cs_form['character_weight'])); ?>%;"></span></div>
                                    </div>
                                    <div class="credit-scoring-bar">
                                        <div class="credit-scoring-bar-top">
                                            <span>Business</span>
                                            <strong id="credit-weight-display-business"><?php echo htmlspecialchars((string)$cs_form['business_weight']); ?>%</strong>
                                        </div>
                                        <div class="credit-scoring-bar-track"><span id="credit-weight-bar-business" class="credit-scoring-bar-fill" style="width: <?php echo max(0, min(100, (int)$cs_form['business_weight'])); ?>%;"></span></div>
                                    </div>
                                </div>

                                <div class="credit-scoring-note">
                                    <strong>How this workspace reads</strong>
                                    <p id="credit-scoring-policy-note">Borrowers must reach the minimum score to proceed, while accounts below the auto-reject floor are declined before limit rules are applied.</p>
                                </div>
                            </aside>
                        </div>

                        <div class="card credit-limit-engine-card credit-limit-workspace">
                            <div class="credit-engine-header">
                                <div>
                                    <h3>Limit Engine &amp; Simulator</h3>
                                    <p class="text-muted">Configure how approved borrowers get a starting limit, qualify for increases, and grow over time.</p>
                                </div>
                                <span class="badge badge-blue">Saved in system settings</span>
                            </div>

                            <form method="POST" action="admin.php" id="credit-limit-rules-form">
                                <input type="hidden" name="action" value="save_credit_limit_rules">
                                <input type="hidden" name="credit_limit_rules_payload" id="credit-limit-rules-payload" value="<?php echo htmlspecialchars((string)$credit_limit_rules_json, ENT_QUOTES, 'UTF-8'); ?>">
                                <script type="application/json" id="credit-limit-rules-seed"><?php echo htmlspecialchars((string)$credit_limit_rules_json, ENT_NOQUOTES, 'UTF-8'); ?></script>

                                <div class="credit-limit-layout">
                                    <div class="credit-limit-main">
                                        <div class="tabs credit-builder-tabs">
                                            <a href="admin.php?tab=credit_settings" class="tab-btn active" data-tab="credit-limit-policy">Upgrade Rules</a>
                                            <a href="admin.php?tab=credit_settings" class="tab-btn" data-tab="credit-limit-initial">Starting Limits</a>
                                        </div>

                                        <div id="credit-limit-policy" class="tab-content active">
                                            <div class="credit-engine-grid">
                                                <div class="credit-engine-panel">
                                                    <div class="credit-engine-panel-head">
                                                        <div>
                                                            <h4>Approval Workflow</h4>
                                                            <p class="text-muted">Choose how much control the system keeps once a borrower reaches the limit assignment stage.</p>
                                                        </div>
                                                    </div>
                                                    <div class="credit-workflow-grid">
                                                        <label class="credit-workflow-option <?php echo ($credit_limit_rules['workflow']['approval_mode'] ?? 'semi') === 'auto' ? 'is-active' : ''; ?>">
                                                            <input type="radio" name="credit_approval_mode" value="auto" <?php echo ($credit_limit_rules['workflow']['approval_mode'] ?? 'semi') === 'auto' ? 'checked' : ''; ?>>
                                                            <span class="credit-workflow-title">Fully Automatic</span>
                                                            <span class="credit-workflow-desc">The platform approves and assigns the suggested limit without manual review.</span>
                                                        </label>
                                                        <label class="credit-workflow-option <?php echo ($credit_limit_rules['workflow']['approval_mode'] ?? 'semi') === 'semi' ? 'is-active' : ''; ?>">
                                                            <input type="radio" name="credit_approval_mode" value="semi" <?php echo ($credit_limit_rules['workflow']['approval_mode'] ?? 'semi') === 'semi' ? 'checked' : ''; ?>>
                                                            <span class="credit-workflow-title">Semi-Automatic</span>
                                                            <span class="credit-workflow-desc">The system recommends the limit, but staff still confirms the approval.</span>
                                                        </label>
                                                        <label class="credit-workflow-option <?php echo ($credit_limit_rules['workflow']['approval_mode'] ?? 'semi') === 'manual' ? 'is-active' : ''; ?>">
                                                            <input type="radio" name="credit_approval_mode" value="manual" <?php echo ($credit_limit_rules['workflow']['approval_mode'] ?? 'semi') === 'manual' ? 'checked' : ''; ?>>
                                                            <span class="credit-workflow-title">Fully Manual</span>
                                                            <span class="credit-workflow-desc">The team decides the final limit from scratch for every borrower.</span>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="credit-engine-panel">
                                                    <div class="credit-engine-panel-head">
                                                        <div>
                                                            <h4>Upgrade Eligibility</h4>
                                                            <p class="text-muted">Set the borrower track record required before a limit increase becomes available.</p>
                                                        </div>
                                                    </div>
                                                    <div class="credit-engine-inline-grid">
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <label for="credit-min-completed-loans">Minimum Completed Loans</label>
                                                            <input type="number" class="form-control" id="credit-min-completed-loans" name="credit_min_completed_loans" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_limit_rules['upgrade_eligibility']['min_completed_loans'] ?? 2)); ?>">
                                                        </div>
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <label for="credit-max-late-payments">Maximum Late Payments Allowed</label>
                                                            <input type="number" class="form-control" id="credit-max-late-payments" name="credit_max_late_payments" min="0" step="1" value="<?php echo htmlspecialchars((string)($credit_limit_rules['upgrade_eligibility']['max_allowed_late_payments'] ?? 0)); ?>">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="credit-engine-panel credit-engine-panel-span-2">
                                                    <div class="credit-engine-panel-head">
                                                        <div>
                                                            <h4>Increase Rules</h4>
                                                            <p class="text-muted">Choose whether successful borrowers grow by a percentage or a fixed amount, with a hard ceiling.</p>
                                                        </div>
                                                    </div>
                                                    <div class="credit-engine-inline-grid credit-engine-inline-grid-tight">
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <label for="credit-increase-type">Increase Type</label>
                                                            <select class="form-control" id="credit-increase-type" name="credit_increase_type">
                                                                <option value="percentage" <?php echo ($credit_limit_rules['increase_rules']['increase_type'] ?? 'percentage') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                                                <option value="fixed" <?php echo ($credit_limit_rules['increase_rules']['increase_type'] ?? 'percentage') === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <label for="credit-increase-value">Increase Value</label>
                                                            <input type="number" class="form-control" id="credit-increase-value" name="credit_increase_value" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_limit_rules['increase_rules']['increase_value'] ?? 20)); ?>">
                                                        </div>
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <label for="credit-absolute-max-limit">Absolute Maximum Limit</label>
                                                            <div class="credit-input-with-prefix">
                                                                <span class="credit-input-prefix">&#8369;</span>
                                                                <input type="number" class="form-control" id="credit-absolute-max-limit" name="credit_absolute_max_limit" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_limit_rules['increase_rules']['absolute_max_limit'] ?? 50000)); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="credit-limit-initial" class="tab-content">
                                            <div class="credit-engine-panel credit-engine-panel-wide">
                                                <div class="credit-engine-panel-head">
                                                    <div>
                                                        <h4>Starting Limits</h4>
                                                        <p class="text-muted">Define the standard starting limit and add custom rules for borrower groups that need a different entry point.</p>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline" id="credit-add-category-rule">
                                                        <span class="material-symbols-rounded">add</span>
                                                        Add Rule
                                                    </button>
                                                </div>
                                                <div class="credit-engine-inline-grid">
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label for="credit-base-limit">Minimum Starting Limit</label>
                                                        <div class="credit-input-with-prefix">
                                                            <span class="credit-input-prefix">&#8369;</span>
                                                            <input type="number" class="form-control" id="credit-base-limit" name="credit_base_limit" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_limit_rules['initial_limits']['base_limit_default'] ?? 5000)); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="credit-category-rules" class="credit-category-rules"></div>

                                                <div class="credit-preview-calculator-card credit-preview-calculator-card-inline">
                                                    <div class="credit-engine-panel-head">
                                                        <div>
                                                            <h4>Quick Simulator</h4>
                                                            <p class="text-muted">Test a sample borrower against the current starting-limit and upgrade rules before you save them.</p>
                                                        </div>
                                                    </div>
                                                    <div class="credit-engine-inline-grid credit-engine-inline-grid-tight">
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <label for="credit-preview-category">Borrower Category</label>
                                                            <select class="form-control" id="credit-preview-category">
                                                                <option value="__default__">Standard borrower</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <label for="credit-preview-completed-loans">Completed Loans</label>
                                                            <input type="number" class="form-control" id="credit-preview-completed-loans" min="0" step="1" value="2">
                                                        </div>
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <label for="credit-preview-late-payments">Late Payments</label>
                                                            <input type="number" class="form-control" id="credit-preview-late-payments" min="0" step="1" value="0">
                                                        </div>
                                                    </div>
                                                    <div class="credit-calculator-slider-wrap">
                                                        <div class="credit-calculator-slider-head">
                                                            <strong>Monthly Income</strong>
                                                            <span id="credit-preview-income-display">&#8369;25,000.00</span>
                                                        </div>
                                                        <input type="range" class="credit-calculator-range" id="credit-preview-income" min="5000" max="150000" step="1000" value="25000">
                                                        <div class="credit-calculator-range-meta">
                                                            <span>&#8369;5,000</span>
                                                            <span>&#8369;150,000</span>
                                                        </div>
                                                    </div>
                                                    <div class="credit-calculator-output">
                                                        <span>Estimated starting limit</span>
                                                        <strong id="credit-preview-limit-output">&#8369;5,000.00</strong>
                                                        <div class="credit-calculator-meter">
                                                            <span id="credit-preview-limit-fill" class="credit-calculator-meter-fill"></span>
                                                        </div>
                                                        <p id="credit-preview-limit-note">Uses the standard starting limit.</p>
                                                    </div>
                                                    <div class="credit-calculator-insights">
                                                        <div class="credit-calculator-insight-card">
                                                            <span>Upgrade Status</span>
                                                            <strong id="credit-preview-upgrade-status">Eligible for upgrade</strong>
                                                            <p id="credit-preview-upgrade-note">This borrower currently meets the upgrade history rules.</p>
                                                        </div>
                                                        <div class="credit-calculator-insight-card">
                                                            <span>Potential Upgraded Limit</span>
                                                            <strong id="credit-preview-next-limit-output">&#8369;6,000.00</strong>
                                                            <p id="credit-preview-next-limit-note">Uses the simulated starting limit as the current limit, then applies the current increase rule.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="action-bar" style="margin-top: 24px;">
                                            <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                                                <span class="material-symbols-rounded" style="font-size:18px;">save</span> Save Limit Engine
                                            </button>
                                        </div>
                                    </div>

                                    <aside class="credit-limit-preview-pane">
                                        <div class="credit-engine-summary-card credit-limit-preview-card">
                                            <div class="credit-engine-summary-card-head">
                                                <div>
                                                    <h4>Limit Snapshot</h4>
                                                    <p class="text-muted">A compact summary of the current limit rules and borrower simulator.</p>
                                                </div>
                                            </div>

                                            <div class="credit-preview-stack">
                                                <div class="credit-engine-summary credit-engine-summary-compact">
                                                    <div class="credit-engine-summary-item">
                                                        <span>Workflow</span>
                                                        <strong id="credit-summary-workflow">Semi-Automatic</strong>
                                                    </div>
                                                    <div class="credit-engine-summary-item">
                                                        <span>Starting Limit</span>
                                                        <strong id="credit-summary-base-limit">&#8369;5,000.00</strong>
                                                    </div>
                                                    <div class="credit-engine-summary-item">
                                                        <span>Upgrade Rule</span>
                                                        <strong id="credit-summary-upgrade">2 completed loans, 0 late payments</strong>
                                                    </div>
                                                    <div class="credit-engine-summary-item">
                                                        <span>Increase Rule</span>
                                                        <strong id="credit-summary-increase">20% up to &#8369;50,000.00</strong>
                                                    </div>
                                                </div>

                                                <div class="credit-preview-initial-card">
                                                    <div class="credit-engine-summary-card-head">
                                                        <div>
                                                            <h4>Initial Limit</h4>
                                                            <p class="text-muted">Preview the standard starting limit and how category rules change it.</p>
                                                        </div>
                                                    </div>
                                                    <div id="credit-summary-initial-logic" class="credit-summary-initial-logic"></div>
                                                </div>

                                                <div class="credit-preview-category-card">
                                                    <div class="credit-engine-summary-card-head">
                                                        <div>
                                                            <h4>Category Rule Preview</h4>
                                                            <p class="text-muted">Review how the starting-limit rules read before saving.</p>
                                                        </div>
                                                    </div>
                                                    <div id="credit-summary-categories" class="credit-summary-categories"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </aside>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <section id="credit_settings" class="view-section <?php echo $active_view === 'credit_settings' ? 'active' : ''; ?>">
                    <div class="credit-policy-shell">
                    <div class="section-intro credit-policy-intro credit-policy-hero">
                        <div class="credit-policy-hero-copy">
                            <span class="credit-policy-kicker">
                                <span class="material-symbols-rounded" style="font-size: 16px;">tune</span>
                                Credit Policy Engine
                            </span>
                            <h2>Credit Policy</h2>
                            <p class="text-muted">Configure borrower checks, score classifications, investigation rules, product checks, and automatic limit estimates for your app.</p>
                            <div class="credit-policy-preview-hero credit-policy-header-preview">
                                <span class="credit-policy-preview-label">Current Snapshot</span>
                                <strong id="credit-policy-preview-summary">Below <?php echo (int)$credit_policy_not_recommended_from; ?> = At-Risk Credit Score, <?php echo (int)$credit_policy_not_recommended_from; ?>-<?php echo (int)$credit_policy_not_recommended_end; ?> = Fair Credit Score, <?php echo (int)$credit_policy_conditional_from; ?>-<?php echo (int)$credit_policy_conditional_end; ?> = Standard Credit Score, <?php echo (int)$credit_policy_recommended_from; ?>-<?php echo (int)$credit_policy_recommended_end; ?> = Good Credit Score, <?php echo (int)$credit_policy_highly_recommended_from; ?>+ = High Credit Score.</strong>
                                <p id="credit-policy-preview-caption">Decision routing: Below <?php echo (int)$credit_policy_conditional_from; ?> = Reject, <?php echo (int)$credit_policy_conditional_from; ?>-<?php echo (int)$credit_policy_conditional_end; ?> = Manual Review, <?php echo (int)$credit_policy_recommended_from; ?>+ = Approval Candidate. Maximum offer <?php echo '&#8369;' . number_format((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0), 2); ?>. Rounded to nearest <?php echo '&#8369;' . number_format((float)($credit_policy['credit_limit']['round_to_nearest'] ?? 0), 2); ?>.</p>
                            </div>
                        </div>
                        <div class="credit-policy-toolbar">
                            <div class="credit-policy-badges">
                                <span class="receipt-filter-pill receipt-filter-pill-primary">At-Risk Credit Score <strong id="credit-policy-badge-at-risk">Below <?php echo (int)$credit_policy_not_recommended_from; ?></strong></span>
                                <span class="receipt-filter-pill">Fair Credit Score <strong id="credit-policy-badge-not-recommended"><?php echo (int)$credit_policy_not_recommended_from; ?>-<?php echo (int)$credit_policy_not_recommended_end; ?></strong></span>
                                <span class="receipt-filter-pill">Standard Credit Score <strong id="credit-policy-badge-conditional"><?php echo (int)$credit_policy_conditional_from; ?>-<?php echo (int)$credit_policy_conditional_end; ?></strong></span>
                                <span class="receipt-filter-pill">Good Credit Score <strong id="credit-policy-badge-recommended"><?php echo (int)$credit_policy_recommended_from; ?>-<?php echo (int)$credit_policy_recommended_end; ?></strong></span>
                                <span class="receipt-filter-pill">High Credit Score <strong id="credit-policy-badge-highly-recommended"><?php echo (int)$credit_policy_highly_recommended_from; ?>+</strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="credit-settings-overview-grid">
                        <form method="POST" action="admin.php" class="card credit-scoring-card credit-policy-form-card" id="credit-policy-form" data-credit-policy-defaults="<?php echo $credit_policy_defaults_json; ?>">
                            <input type="hidden" name="action" value="save_credit_policy">

                            <div class="credit-engine-header credit-policy-engine-header">
                                <div>
                                    <h3>Policy Builder</h3>
                                    <p class="text-muted">Focus on borrower eligibility, score thresholds, and credit limit rules.</p>
                                </div>
                            </div>

                            <div class="credit-policy-tab-nav" id="credit-policy-tab-nav">
                                <button type="button" class="credit-policy-tab-btn" data-credit-policy-tab="eligibility">
                                    <span class="material-symbols-rounded">verified_user</span>
                                    Eligibility
                                    <small>Who can qualify</small>
                                </button>
                                <button type="button" class="credit-policy-tab-btn is-active" data-credit-policy-tab="score">
                                    <span class="material-symbols-rounded">query_stats</span>
                                    Score Rules
                                    <small>Classify and route</small>
                                </button>
                                <button type="button" class="credit-policy-tab-btn" data-credit-policy-tab="limit">
                                    <span class="material-symbols-rounded">payments</span>
                                    Credit Limit
                                    <small>Estimated offer</small>
                                </button>
                            </div>

                            <div class="credit-policy-tab-panels">
                                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="eligibility" hidden>
                                    <div class="credit-engine-panel credit-engine-panel-span-2 credit-policy-panel" id="credit-policy-eligibility">
                                    <div class="credit-engine-panel-head credit-policy-panel-headline">
                                        <div class="credit-policy-head-main">
                                            <div class="credit-policy-head-icon"><span class="material-symbols-rounded">verified_user</span></div>
                                            <div class="credit-policy-panel-title">
                                                <span class="credit-policy-section-step">Section 1</span>
                                                <h4>Eligibility Rules</h4>
                                                <p class="text-muted">Use these checks to decide who can move forward before scoring and offer sizing.</p>
                                            </div>
                                        </div>
                                        <div class="credit-policy-section-meta">
                                            <span class="badge badge-gray">Borrower checks</span>
                                            <span class="badge badge-blue" id="credit-policy-employment-count-badge"><?php echo (int)$credit_policy_allowed_employment_count; ?> statuses enabled</span>
                                        </div>
                                    </div>

                                    <div class="credit-policy-mini-grid">
                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                            <label for="cp-min-monthly-income">Minimum Monthly Income</label>
                                            <div class="credit-input-with-prefix">
                                                <span class="credit-input-prefix">&#8369;</span>
                                                <input type="number" class="form-control" id="cp-min-monthly-income" name="cp_min_monthly_income" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['eligibility']['min_monthly_income'] ?? 0)); ?>">
                                            </div>
                                            <p class="credit-policy-field-hint">Applications below this amount are filtered out before the offer is estimated.</p>
                                        </div>
                                        <div class="credit-policy-note-card">
                                            <strong>What this section controls</strong>
                                            <ul>
                                                <li>Uses borrower income, employment, and document status.</li>
                                                <li>Stops obviously ineligible borrowers before score or product checks run.</li>
                                                <li>Works best when at least one employment status stays enabled.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="credit-policy-choice-block">
                                        <div class="credit-policy-subhead">
                                            <strong>Allowed employment statuses</strong>
                                            <span class="text-muted" id="credit-policy-employment-count-text"><?php echo (int)$credit_policy_allowed_employment_count; ?> selected</span>
                                        </div>
                                        <p class="credit-policy-field-hint">Check the employment statuses that are allowed to proceed.</p>
                                    </div>

                                    <div class="credit-policy-table-map credit-policy-employment-list">
                                        <?php foreach ($credit_policy_employment_options as $employment_option): ?>
                                            <label class="credit-policy-table-row credit-policy-employment-row">
                                                <input type="checkbox" name="cp_allowed_employment_statuses[]" value="<?php echo htmlspecialchars($employment_option); ?>" <?php echo in_array($employment_option, $credit_policy_allowed_employment_values, true) ? 'checked' : ''; ?>>
                                                <div class="credit-policy-employment-row-copy">
                                                    <strong><?php echo htmlspecialchars($employment_option); ?></strong>
                                                    <span>Allow this employment status to proceed.</span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                    </div>

                                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="score" hidden>
                                <div class="credit-engine-panel credit-policy-panel" id="credit-policy-thresholds">
                                    <div class="credit-engine-panel-head credit-policy-panel-headline">
                                        <div class="credit-policy-head-main">
                                            <div class="credit-policy-head-icon"><span class="material-symbols-rounded">query_stats</span></div>
                                            <div class="credit-policy-panel-title">
                                                <span class="credit-policy-section-step">Section 2</span>
                                                <h4>Score Thresholds</h4>
                                                <p class="text-muted">Set the score ranges used to classify borrower credit scores. Decision routing is shown separately below.</p>
                                            </div>
                                        </div>
                                        <div class="credit-policy-section-meta">
                                            <span class="badge badge-gray">Score classification</span>
                                        </div>
                                    </div>

                                    <div class="credit-policy-threshold-support-grid" style="margin-top: 0; margin-bottom: 16px;">
                                        <div class="credit-policy-note-card">
                                            <strong>New Client Fallback</strong>
                                            <p class="credit-policy-field-hint" style="margin: 0;">Use this starting score when a borrower does not yet have a recorded credit score.</p>
                                            <p class="credit-policy-field-hint" id="credit-policy-default-score-band-hint" style="font-weight: 500; margin: 0;"></p>
                                        </div>
                                        <div class="credit-policy-threshold-card">
                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                                <label for="cp-new-client-default-score">Default Credit Score</label>
                                                <input type="number" class="form-control" id="cp-new-client-default-score" name="cp_new_client_default_score" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['new_client_default_score'] ?? 500)); ?>">
                                                <p class="credit-policy-field-hint">Maximum credit score is <?php echo (int)$credit_policy_score_ceiling; ?>.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="credit-policy-threshold-grid">
                                        <div class="credit-policy-threshold-card">
                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                                <label for="cp-not-recommended-score">Fair Credit Score From</label>
                                                <input type="number" class="form-control" id="cp-not-recommended-score" name="cp_not_recommended_min_score" min="0" max="<?php echo max(0, (int)$credit_policy_score_ceiling - 3); ?>" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['not_recommended_min_score'] ?? 200)); ?>">
                                                <p class="credit-policy-field-hint">Scores from this point up to the next band are classified as <code>Fair Credit Score</code>. Anything below this remains <code>At-Risk Credit Score</code>.</p>
                                            </div>
                                        </div>
                                        <div class="credit-policy-threshold-card">
                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                                <label for="cp-conditional-score">Standard Credit Score From</label>
                                                <input type="number" class="form-control" id="cp-conditional-score" name="cp_conditional_min_score" min="1" max="<?php echo max(1, (int)$credit_policy_score_ceiling - 2); ?>" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['conditional_min_score'] ?? 400)); ?>">
                                                <p class="credit-policy-field-hint">Scores from this point up to the next band are classified as <code>Standard Credit Score</code>.</p>
                                            </div>
                                        </div>
                                        <div class="credit-policy-threshold-card">
                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                                <label for="cp-recommended-score">Good Credit Score From</label>
                                                <input type="number" class="form-control" id="cp-recommended-score" name="cp_recommended_min_score" min="2" max="<?php echo max(2, (int)$credit_policy_score_ceiling - 1); ?>" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['recommended_min_score'] ?? 600)); ?>">
                                                <p class="credit-policy-field-hint">Scores from this point up to the next band are classified as <code>Good Credit Score</code>.</p>
                                            </div>
                                        </div>
                                        <div class="credit-policy-threshold-card">
                                            <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                                <label for="cp-highly-recommended-score">High Credit Score From</label>
                                                <input type="number" class="form-control" id="cp-highly-recommended-score" name="cp_highly_recommended_min_score" min="3" max="<?php echo (int)$credit_policy_score_ceiling; ?>" step="1" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['highly_recommended_min_score'] ?? 800)); ?>">
                                                <p class="credit-policy-field-hint">Scores at or above this are classified as <code>High Credit Score</code>. Maximum credit score is <?php echo (int)$credit_policy_score_ceiling; ?>.</p>
                                                <p class="credit-policy-field-hint is-warning" id="credit-policy-threshold-warning" hidden>Each score band must start higher than the band before it.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="credit-policy-threshold-support-grid">
                                        <div class="credit-policy-note-card">
                                            <strong>Score Classification</strong>
                                            <ul>
                                                <li><span id="credit-policy-threshold-copy-at-risk">Below <?php echo (int)$credit_policy_not_recommended_from; ?></span> = At-Risk Credit Score.</li>
                                                <li><span id="credit-policy-threshold-copy-not-recommended"><?php echo (int)$credit_policy_not_recommended_from; ?>-<?php echo (int)$credit_policy_not_recommended_end; ?></span> = Fair Credit Score.</li>
                                                <li><span id="credit-policy-threshold-copy-conditional"><?php echo (int)$credit_policy_conditional_from; ?>-<?php echo (int)$credit_policy_conditional_end; ?></span> = Standard Credit Score.</li>
                                                <li><span id="credit-policy-threshold-copy-recommended"><?php echo (int)$credit_policy_recommended_from; ?>-<?php echo (int)$credit_policy_recommended_end; ?></span> = Good Credit Score.</li>
                                                <li><span id="credit-policy-threshold-copy-highly-recommended"><?php echo (int)$credit_policy_highly_recommended_from; ?> and above</span> = High Credit Score.</li>
                                            </ul>
                                        </div>
                                        <div class="credit-policy-note-card">
                                            <strong>Decision Routing</strong>
                                            <p class="credit-policy-field-hint" id="credit-policy-threshold-routing-copy" style="margin: 0;">Decision routing: Below <?php echo (int)$credit_policy_conditional_from; ?> = Reject, <?php echo (int)$credit_policy_conditional_from; ?>-<?php echo (int)$credit_policy_conditional_end; ?> = Manual Review, <?php echo (int)$credit_policy_recommended_from; ?> and above = Approval Candidate.</p>
                                        </div>
                                    </div>
                                </div>

                                </div>

                                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="ci" hidden>
                                <div class="credit-engine-panel credit-engine-panel-span-2 credit-policy-panel" id="credit-policy-ci">
                                    <div class="credit-engine-panel-head credit-policy-panel-headline">
                                        <div class="credit-policy-head-main">
                                            <div class="credit-policy-head-icon"><span class="material-symbols-rounded">fact_check</span></div>
                                            <div class="credit-policy-panel-title">
                                                <span class="credit-policy-section-step">Section 3</span>
                                                <h4>Investigation Decision Flow</h4>
                                                <p class="text-muted">Decide when a completed investigation should affect the application decision.</p>
                                            </div>
                                        </div>
                                        <div class="credit-policy-section-meta">
                                            <span class="badge badge-gray">Investigation settings</span>
                                            <span class="badge badge-blue" id="credit-policy-ci-count-badge"><?php echo (int)$credit_policy_auto_ci_count; ?> approve / <?php echo (int)$credit_policy_review_ci_count; ?> review</span>
                                        </div>
                                    </div>

                                    <div class="credit-engine-inline-grid">
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <input type="hidden" name="cp_require_ci" value="0">
                                            <label class="credit-toggle-row" for="cp-require-ci">
                                                <span class="credit-toggle-copy">
                                                    <strong>Always Require Investigation</strong>
                                                    <small>Step 1. Turn this on when every application must wait for a completed investigation before it can continue.</small>
                                                </span>
                                                <input type="checkbox" id="cp-require-ci" name="cp_require_ci" value="1" <?php echo !empty($credit_policy['ci_rules']['require_ci']) ? 'checked' : ''; ?>>
                                            </label>
                                        </div>
                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                            <label for="cp-ci-required-above-amount">Require Investigation Above Amount</label>
                                            <div class="credit-input-with-prefix">
                                                <span class="credit-input-prefix">&#8369;</span>
                                                <input type="number" class="form-control" id="cp-ci-required-above-amount" name="cp_ci_required_above_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['ci_rules']['ci_required_above_amount'] ?? 0)); ?>">
                                            </div>
                                            <p class="credit-policy-field-hint">Step 1 alternative. Requests above this amount must have a completed investigation before they can continue.</p>
                                        </div>
                                    </div>

                                    <div class="credit-policy-note-card" style="margin-top: 16px;">
                                        <strong>How This Flow Works</strong>
                                        <ol>
                                            <li>If an investigation is required and none is completed yet, the application moves to <code>Pending Review</code>.</li>
                                            <li><code>Not Recommended</code> always rejects the application. It is not controlled by the checkboxes below.</li>
                                            <li>Anything checked under <code>Review Investigation Results</code> always moves to <code>Pending Review</code>.</li>
                                            <li>Anything checked under <code>Auto-Approve Investigation Results</code> may continue through automatic approval checks if the other rules also pass.</li>
                                            <li>If a completed result is left unchecked in both lists, it is treated as review, not automatic approval.</li>
                                        </ol>
                                    </div>

                                    <?php
                                    $credit_policy_ci_auto_copy = [
                                        'Highly Recommended' => 'Check this if a Highly Recommended result is allowed to continue through automatic approval checks.',
                                        'Recommended' => 'Check this if a Recommended result is allowed to continue through automatic approval checks.',
                                        'Conditional' => 'Check this if a Conditional result is still allowed to continue automatically instead of being stopped for review.',
                                    ];
                                    $credit_policy_ci_review_copy = [
                                        'Highly Recommended' => 'Check this if a Highly Recommended result should still stop and go to Pending Review for staff handling.',
                                        'Recommended' => 'Check this if a Recommended result should stop and go to Pending Review for staff handling.',
                                        'Conditional' => 'Check this if a Conditional result should stop and go to Pending Review for a manual decision.',
                                    ];
                                    ?>
                                    <div class="credit-policy-ci-columns" style="margin-top: 16px;">
                                        <div class="form-group credit-policy-ci-column" style="margin-bottom: 0;">
                                            <div class="credit-policy-subhead">
                                                <strong>Step 2. Let These Results Continue Automatically</strong>
                                                <span class="text-muted" id="credit-policy-auto-ci-count-text"><?php echo (int)$credit_policy_auto_ci_count; ?> selected</span>
                                            </div>
                                            <p class="credit-policy-field-hint" style="margin-bottom: 10px;">Check the completed investigation results that may stay eligible for automatic approval when the rest of the policy still passes.</p>
                                            <div class="credit-policy-table-map">
                                                <?php foreach ($credit_policy_ci_configurable_options as $ci_option): ?>
                                                    <label class="credit-policy-table-row credit-policy-ci-row">
                                                        <span class="credit-policy-ci-row-copy">
                                                            <strong><?php echo htmlspecialchars($ci_option); ?></strong>
                                                            <span><?php echo htmlspecialchars($credit_policy_ci_auto_copy[$ci_option] ?? 'Allow this investigation outcome to remain approval-eligible when the rest of the policy still passes.'); ?></span>
                                                        </span>
                                                        <input type="checkbox" name="cp_auto_approve_ci_values[]" value="<?php echo htmlspecialchars($ci_option); ?>" <?php echo in_array($ci_option, $credit_policy_auto_ci_values, true) ? 'checked' : ''; ?>>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            </div>

                                        <div class="form-group credit-policy-ci-column" style="margin-bottom: 0;">
                                            <div class="credit-policy-subhead">
                                                <strong>Step 3. Send These Results To Pending Review</strong>
                                                <span class="text-muted" id="credit-policy-review-ci-count-text"><?php echo (int)$credit_policy_review_ci_count; ?> selected</span>
                                            </div>
                                            <p class="credit-policy-field-hint" style="margin-bottom: 10px;">Check the completed investigation results that should always stop automatic progress and move to <code>Pending Review</code>.</p>
                                            <div class="credit-policy-table-map">
                                                <?php foreach ($credit_policy_ci_configurable_options as $ci_option): ?>
                                                    <label class="credit-policy-table-row credit-policy-ci-row">
                                                        <span class="credit-policy-ci-row-copy">
                                                            <strong><?php echo htmlspecialchars($ci_option); ?></strong>
                                                            <span><?php echo htmlspecialchars($credit_policy_ci_review_copy[$ci_option] ?? 'Move this investigation outcome to manual review so staff can decide the next step.'); ?></span>
                                                        </span>
                                                        <input type="checkbox" name="cp_review_ci_values[]" value="<?php echo htmlspecialchars($ci_option); ?>" <?php echo in_array($ci_option, $credit_policy_review_ci_values, true) ? 'checked' : ''; ?>>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="credit-policy-table-row credit-policy-static-rule">
                                        <span class="credit-policy-ci-row-copy">
                                            <strong>Fixed Rule: Not Recommended</strong>
                                            <span>A <code>Not Recommended</code> investigation result always rejects the application. It is not controlled by the review or automatic-approval checkboxes above.</span>
                                        </span>
                                        <span class="credit-policy-static-badge">Always Reject</span>
                                    </div>
                                </div>

                                </div>

                                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="limit" hidden>
                                <div class="credit-engine-panel credit-engine-panel-span-2 credit-policy-panel" id="credit-policy-limit">
                                    <div class="credit-engine-panel-head credit-policy-panel-headline">
                                        <div class="credit-policy-head-main">
                                            <div class="credit-policy-head-icon"><span class="material-symbols-rounded">payments</span></div>
                                            <div class="credit-policy-panel-title">
                                                <span class="credit-policy-section-step">Section 3</span>
                                                <h4>Credit Limit Rules</h4>
                                                <p class="text-muted">Estimate the offer using income, score strength, and a maximum limit. Backend adjustments can still refine the final offer.</p>
                                            </div>
                                        </div>
                                        <div class="credit-policy-section-meta">
                                            <span class="badge badge-gray">Automatic estimate</span>
                                        </div>
                                    </div>

                                    <div class="credit-policy-note-card" style="margin-bottom: 16px;">
                                        <strong>How the offer is estimated</strong>
                                        <ul>
                                            <li><span id="credit-policy-formula-preview">Start with monthly income x <?php echo htmlspecialchars((string)($credit_policy['credit_limit']['income_multiplier'] ?? 0)); ?> x score strength x classification multiplier</span></li>
                                            <li>Keep the final estimate within <span id="credit-policy-formula-cap"><?php echo '&#8369;' . number_format((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0), 2); ?></span>.</li>
                                            <li>Round the result down to the nearest <span id="credit-policy-formula-round"><?php echo number_format((float)($credit_policy['credit_limit']['round_to_nearest'] ?? 0), 2); ?></span>.</li>
                                        </ul>
                                    </div>

                                    <div class="credit-engine-inline-grid credit-engine-inline-grid-tight">
                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                            <label for="cp-income-multiplier">Income Multiplier</label>
                                            <input type="number" class="form-control" id="cp-income-multiplier" name="cp_income_multiplier" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['income_multiplier'] ?? 0)); ?>">
                                            <p class="credit-policy-field-hint">Base multiplier applied to monthly income before the classification multiplier, cap, and rounding.</p>
                                        </div>
                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                            <label for="cp-approve-band-multiplier">Good / High Band Multiplier</label>
                                            <input type="number" class="form-control" id="cp-approve-band-multiplier" name="cp_approve_band_multiplier" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['approve_band_multiplier'] ?? 1.10)); ?>">
                                            <p class="credit-policy-field-hint">Applied when the score classification is <code>Good Credit Score</code> or <code>High Credit Score</code>.</p>
                                        </div>
                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                            <label for="cp-review-band-multiplier">Standard Band Multiplier</label>
                                            <input type="number" class="form-control" id="cp-review-band-multiplier" name="cp_review_band_multiplier" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['review_band_multiplier'] ?? 1.00)); ?>">
                                            <p class="credit-policy-field-hint">Applied when the score classification is <code>Standard Credit Score</code>.</p>
                                        </div>
                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                            <label for="cp-reject-band-multiplier">At-Risk / Fair Band Multiplier</label>
                                            <input type="number" class="form-control" id="cp-reject-band-multiplier" name="cp_reject_band_multiplier" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['reject_band_multiplier'] ?? 0.50)); ?>">
                                            <p class="credit-policy-field-hint">Applied when the score classification is <code>Fair Credit Score</code> or <code>At-Risk Credit Score</code>.</p>
                                        </div>
                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                            <label for="cp-max-credit-limit-cap">Maximum Credit Limit Cap</label>
                                            <div class="credit-input-with-prefix">
                                                <span class="credit-input-prefix">&#8369;</span>
                                                <input type="number" class="form-control" id="cp-max-credit-limit-cap" name="cp_max_credit_limit_cap" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0)); ?>">
                                            </div>
                                            <p class="credit-policy-field-hint">Maximum offer the app can recommend, even when income and score suggest more.</p>
                                        </div>
                                        <div class="form-group credit-policy-field" style="margin-bottom: 0;">
                                            <label for="cp-round-to-nearest">Round To Nearest</label>
                                            <div class="credit-input-with-prefix">
                                                <span class="credit-input-prefix">&#8369;</span>
                                                <input type="number" class="form-control" id="cp-round-to-nearest" name="cp_round_to_nearest" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['round_to_nearest'] ?? 0)); ?>">
                                            </div>
                                            <p class="credit-policy-field-hint">Use values like <code>500</code> or <code>1000</code> for cleaner recommended offers.</p>
                                        </div>
                                    </div>
                                </div>

                                </div>

                                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="product" hidden>
                                <div class="credit-engine-panel credit-policy-panel" id="credit-policy-product">
                                    <div class="credit-engine-panel-head credit-policy-panel-headline">
                                        <div class="credit-policy-head-main">
                                            <div class="credit-policy-head-icon"><span class="material-symbols-rounded">inventory_2</span></div>
                                            <div class="credit-policy-panel-title">
                                                <span class="credit-policy-section-step">Section 5</span>
                                                <h4>Product Checks</h4>
                                                <p class="text-muted">Use product guardrails to keep the final offer within the selected loan option.</p>
                                            </div>
                                        </div>
                                        <div class="credit-policy-section-meta">
                                            <span class="badge badge-gray">Product guardrails</span>
                                        </div>
                                    </div>

                                    <div class="credit-engine-inline-grid">
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <input type="hidden" name="cp_use_product_minimum_credit_score" value="0">
                                            <label class="credit-toggle-row" for="cp-use-product-min-score">
                                                <span class="credit-toggle-copy">
                                                    <strong>Use Product Minimum Credit Score</strong>
                                                    <small>Reject when the borrower is below the product score floor.</small>
                                                </span>
                                                <input type="checkbox" id="cp-use-product-min-score" name="cp_use_product_minimum_credit_score" value="1" <?php echo !empty($credit_policy['product_checks']['use_product_minimum_credit_score']) ? 'checked' : ''; ?>>
                                            </label>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <input type="hidden" name="cp_use_product_min_amount" value="0">
                                            <label class="credit-toggle-row" for="cp-use-product-min-amount">
                                                <span class="credit-toggle-copy">
                                                    <strong>Use Product Minimum Amount</strong>
                                                    <small>Reject when the request or estimated offer falls below the product minimum.</small>
                                                </span>
                                                <input type="checkbox" id="cp-use-product-min-amount" name="cp_use_product_min_amount" value="1" <?php echo !empty($credit_policy['product_checks']['use_product_min_amount']) ? 'checked' : ''; ?>>
                                            </label>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <input type="hidden" name="cp_use_product_max_amount" value="0">
                                            <label class="credit-toggle-row" for="cp-use-product-max-amount">
                                                <span class="credit-toggle-copy">
                                                    <strong>Use Product Maximum Amount</strong>
                                                    <small>Reject when the request exceeds the product maximum.</small>
                                                </span>
                                                <input type="checkbox" id="cp-use-product-max-amount" name="cp_use_product_max_amount" value="1" <?php echo !empty($credit_policy['product_checks']['use_product_max_amount']) ? 'checked' : ''; ?>>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>

                            <div class="credit-policy-action-bar">
                                <div class="credit-policy-action-copy">
                                    <strong>Save when the flow feels right</strong>
                                    <span>This policy controls automatic limit estimates and application decisions for your app.</span>
                                </div>
                                <div class="credit-policy-primary-actions">
                                    <button type="button" class="btn btn-outline" id="credit-policy-reset-trigger-bottom">
                                        <span class="material-symbols-rounded">restart_alt</span>
                                        Reset to Defaults
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <span class="material-symbols-rounded">save</span>
                                        Save Credit Policy
                                    </button>
                                </div>
                            </div>
                        </form>

                        <aside class="credit-limit-preview-pane">
                            <div class="credit-engine-summary-card credit-limit-preview-card credit-policy-sticky-card" id="credit-policy-simulator">
                                <div class="credit-engine-summary-card-head">
                                    <div>
                                        <h4>Quick Simulator</h4>
                                        <p class="text-muted">Test the current form values with a sample borrower before saving.</p>
                                    </div>
                                </div>
                                <div class="credit-policy-simulator-shell">
                                    <div class="credit-policy-simulator-inputs">
                                        <div class="credit-policy-simulator-grid">
                                            <div class="form-group">
                                                <label for="credit-policy-sim-income">Monthly Income</label>
                                                <div class="credit-input-with-prefix">
                                                    <span class="credit-input-prefix">&#8369;</span>
                                                    <input type="number" class="form-control" id="credit-policy-sim-income" value="25000" min="0" step="0.01">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="credit-policy-sim-score">Credit Score</label>
                                                <input type="number" class="form-control" id="credit-policy-sim-score" value="820" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" step="1">
                                            </div>
                                            <div class="form-group">
                                                <label for="credit-policy-sim-requested">Requested Amount</label>
                                                <div class="credit-input-with-prefix">
                                                    <span class="credit-input-prefix">&#8369;</span>
                                                    <input type="number" class="form-control" id="credit-policy-sim-requested" value="60000" min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="credit-policy-simulator-hero is-review" id="credit-policy-sim-decision-card">
                                        <span>Likely Decision</span>
                                        <strong id="credit-policy-sim-decision">Review</strong>
                                        <p id="credit-policy-sim-caption">Shows the score classification, routing outcome, and estimated offer for the sample borrower.</p>
                                    </div>

                                    <div class="credit-policy-simulator-metrics">
                                        <div class="credit-policy-output-card">
                                            <span>Estimated Limit</span>
                                            <strong id="credit-policy-sim-limit">&#8369;0.00</strong>
                                        </div>
                                        <div class="credit-policy-output-card">
                                            <span>Suggested Offer</span>
                                            <strong id="credit-policy-sim-offer">&#8369;0.00</strong>
                                        </div>
                                    </div>

                                    <div class="credit-policy-simulator-formula">
                                        <span>Estimate Logic</span>
                                        <strong id="credit-policy-sim-formula">Income x score strength x classification multiplier</strong>
                                    </div>

                                    <div class="credit-policy-note">Simulator assumes the borrower passes eligibility checks. A missing score uses the new-client default score.</div>
                                </div>
                            </div>
                        </aside>
                    </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <!-- Modals -->
    <!-- Notification Toast -->
    <div id="toast" class="toast <?php echo $flash_message ? 'show' : ''; ?>">
        <span class="material-symbols-rounded">check_circle</span>
        <span id="toast-message"><?php echo htmlspecialchars($flash_message); ?></span>
    </div>

    <!-- Branded Confirmation Modal -->
    <div id="confirm-action-modal" class="modal-backdrop" style="display: none;">
        <div class="modal" style="width: 460px; max-width: 90vw;">
            <div class="modal-header">
                <h2 id="confirm-action-title">Confirm Action</h2>
                <button type="button" class="icon-btn" id="confirm-action-close">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="confirm-action-message" class="text-muted" style="margin: 0; line-height: 1.6;">Are you sure you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" id="confirm-action-cancel" style="background: transparent; color: var(--text-main); border: 1px solid var(--border-color);">Cancel</button>
                <button type="button" class="btn" id="confirm-action-submit" style="background: var(--primary-color); color: #fff;">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Auto-dismiss toast after 5 seconds
        (function() {
            var toast = document.getElementById('toast');
            if (toast && toast.classList.contains('show')) {
                // Auto-dismiss after 5 seconds
                setTimeout(function() {
                    toast.classList.remove('show');
                }, 5000);
                
                // Allow clicking to dismiss immediately
                toast.addEventListener('click', function() {
                    toast.classList.remove('show');
                });
            }
        })();
    </script>

    <!-- Add Staff Modal -->
    <div id="add-staff-modal" class="modal-backdrop" style="display: none;">
        <div class="modal" style="width: 500px; max-width: 90vw;">
            <div class="modal-header">
                <h2 id="add-staff-modal-title">Add Staff Member</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('add-staff-modal').style.display='none'">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="create_staff">
                <input type="hidden" id="create-as-admin-flag" name="create_as_admin" value="0">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>First Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Last Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span style="color:var(--danger-color);">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>

                    <div class="form-group" id="role-group">
                        <label>Role <span style="color:var(--danger-color);">*</span></label>
                        <select name="role_id" class="form-control" required>
                            <option value="">Select a role...</option>
                            <?php foreach ($staff_assignable_roles as $r): ?>
                                <option value="<?php echo $r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="billing-toggle-group" style="display: none; align-items: center; justify-content: space-between; padding: 12px; background: rgba(var(--primary-rgb), 0.05); border-radius: 8px; border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div>
                            <span style="display: block; font-weight: 500; font-size: 0.95rem;">Manage Plan & Billing</span>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">Allow this admin to change subscription and edit payment methods.</span>
                        </div>
                        <label class="switch" style="transform: scale(0.9);">
                            <input type="checkbox" id="create-can-manage-billing" name="can_manage_billing" value="1" checked>
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('add-staff-modal').style.display='none'">Cancel</button>
                    <button type="submit" id="add-staff-submit-btn" class="btn btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Role Modal -->
    <div id="create-role-modal" class="modal-backdrop" aria-hidden="true" style="display: none; position: fixed; top: 0; right: 0; bottom: 0; left: 0; align-items: center; justify-content: center; padding: 24px; background: rgba(15, 23, 42, 0.55); z-index: 9999; overflow-y: auto;">
        <div class="modal" style="width: 800px; max-width: 90vw;">
            <div class="modal-header">
                <h2>Create Custom Role</h2>
                <button type="button" class="icon-btn" onclick="closeCreateRoleModal()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="create_role">
                <div class="modal-body">
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 24px;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>Role Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="role_name" id="create_role_name" placeholder="e.g. Manager, Loan Officer" required>
                            <small class="text-muted">Role names are case-sensitive and must be unique.</small>
                        </div>

                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>Load Preset Defaults</label>
                            <select id="role-preset" class="form-control">
                                <option value="custom" selected>Custom (No Preset)</option>
                                <option value="manager">Manager (All Access)</option>
                                <option value="loan_officer">Loan Officer</option>
                                <option value="teller">Teller</option>
                            </select>
                            <small class="text-muted" style="display: block; margin-top: 4px;">Start from a preset to auto-fill permissions.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Initial Permissions</label>
                        <p class="text-muted" style="margin-bottom: 12px; font-size: 0.9rem;">Start from a preset, then refine using module groups and search.</p>
                        <div class="create-role-toolbar">
                            <label class="permissions-search-wrap" for="create-role-permissions-search">
                                <span class="material-symbols-rounded">search</span>
                                <input type="search" id="create-role-permissions-search" class="permissions-filter-input" placeholder="Search initial permissions">
                            </label>
                            
                        </div>
                        <div id="create-role-permissions-container" class="permissions-grid" style="max-height: 480px; overflow-y: auto; padding-right: 12px; align-items: stretch; margin-top: 10px;">
                            <?php foreach ($grouped_permissions as $moduleName => $perms): ?>
                                <div class="permission-module" data-module-name="<?php echo htmlspecialchars(strtolower((string)$moduleName)); ?>">
                                    <div class="permission-module-header">
                                        <?php
                                            $module_icons = [
                                                'Applications' => 'description',
                                                'Clients' => 'group',
                                                'Loans' => 'real_estate_agent',
                                                'Payments' => 'payments',
                                                'Reports' => 'analytics',
                                                'Users' => 'manage_accounts',
                                            ];
                                            $icon = $module_icons[$moduleName] ?? 'folder';
                                        ?>
                                        <h4>
                                            <span class="material-symbols-rounded"><?php echo $icon; ?></span>
                                            <?php echo htmlspecialchars($moduleName); ?>
                                        </h4>
                                        <div class="permission-module-actions">
                                            <span class="permission-module-count"><strong class="permission-module-visible-count">0</strong>/<?php echo count($perms); ?></span>
                                            <button type="button" class="module-action-btn create-role-module-toggle" data-bulk="all">All</button>
                                            <button type="button" class="module-action-btn create-role-module-toggle" data-bulk="none">None</button>
                                        </div>
                                    </div>
                                    <div class="toggle-list">
                                        <?php foreach ($perms as $p): ?>
                                            <?php
                                                $create_modal_search_text = strtolower(trim(($p['description'] ?? '') . ' ' . ($p['permission_code'] ?? '') . ' ' . $moduleName));
                                            ?>
                                            <div class="toggle-item" data-permission-search="<?php echo htmlspecialchars($create_modal_search_text); ?>">
                                                <div class="toggle-info">
                                                    <h4 style="margin-bottom: 2px; font-weight: 500; font-size: 0.9rem; border-bottom: none;"><?php echo htmlspecialchars($p['description']); ?></h4>
                                                    <span class="permission-code"><?php echo htmlspecialchars(str_replace('_', ' ', (string)$p['permission_code'])); ?></span>
                                                </div>
                                                <label class="switch" style="transform: scale(0.85); transform-origin: right;">
                                                    <input type="checkbox" name="initial_permissions[]" value="<?php echo htmlspecialchars($p['permission_code']); ?>">
                                                    <span class="slider round"></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="permissions-empty-search" id="create-role-permissions-empty" hidden>
                            <span class="material-symbols-rounded">search_off</span>
                            <p>No matching permissions in this role template.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCreateRoleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="edit-staff-modal" class="modal-backdrop" style="display: none;">
        <div class="modal" style="width: 500px; max-width: 90vw;">
            <div class="modal-header">
                <h2>Edit Staff Member</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('edit-staff-modal').style.display='none'">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="edit_staff">
                <input type="hidden" name="user_id" id="edit-staff-user-id">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>First Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="edit-staff-first-name" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Last Name <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="edit-staff-last-name" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span style="color:var(--danger-color);">*</span></label>
                        <input type="email" class="form-control" name="email" id="edit-staff-email" required>
                    </div>
                    <div class="form-group">
                        <label>Role <span style="color:var(--danger-color);">*</span></label>
                        <select name="role_id" id="edit-staff-role-id" class="form-control" required>
                            <option value="">Select a role...</option>
                            <?php foreach ($staff_assignable_roles as $r): ?>
                                <option value="<?php echo $r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="edit-billing-toggle-group" style="display: none; align-items: center; justify-content: space-between; padding: 12px; background: rgba(var(--primary-rgb), 0.05); border-radius: 8px; border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div>
                            <span style="display: block; font-weight: 500; font-size: 0.95rem;">Manage Plan & Billing</span>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">Allow this admin to change subscription and edit payment methods.</span>
                        </div>
                        <label class="switch" style="transform: scale(0.9);">
                            <input type="checkbox" id="edit-can-manage-billing" name="can_manage_billing" value="1">
                            <span class="slider round"></span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit-staff-status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('edit-staff-modal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Payment Method Modal -->
    <div id="edit-payment-method-modal" class="modal-backdrop" style="display: none;">
        <div class="modal" style="width: 500px; max-width: 90vw;">
            <div class="modal-header">
                <h2>Edit Payment Method</h2>
                <button type="button" class="icon-btn" onclick="closeEditPaymentMethodModal()">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="update_payment_method">
                <input type="hidden" name="method_id" id="edit-payment-method-id">
                <div class="modal-body">
                    <p class="text-muted" id="edit-payment-method-mask" style="margin-top: 0; margin-bottom: 12px;"></p>
                    <div class="form-group">
                        <label>Cardholder Name <span style="color:var(--danger-color);">*</span></label>
                        <input type="text" class="form-control" name="cardholder_name" id="edit-payment-cardholder-name" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Expiry Month <span style="color:var(--danger-color);">*</span></label>
                            <input type="number" class="form-control" name="exp_month" id="edit-payment-exp-month" min="1" max="12" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Expiry Year <span style="color:var(--danger-color);">*</span></label>
                            <input type="number" class="form-control" name="exp_year" id="edit-payment-exp-year" min="<?php echo (int)date('Y'); ?>" max="2099" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 1rem; margin-bottom: 0;">
                        <label style="display:flex; align-items:center; gap:8px; margin:0;">
                            <input type="checkbox" name="is_default" id="edit-payment-is-default" value="1">
                            <span>Set as default payment method</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditPaymentMethodModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var planSelect = document.getElementById('new-plan-select');
            var selectedPlanPrice = document.getElementById('selected-plan-price');
            if (!planSelect || !selectedPlanPrice) return;

            function updateSelectedPlanPrice() {
                var selectedOption = planSelect.options[planSelect.selectedIndex];
                var planPrice = Number(selectedOption ? (selectedOption.getAttribute('data-price') || 0) : 0);
                selectedPlanPrice.textContent = 'Selected plan price: ' + new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(planPrice) + '/month';
            }

            planSelect.addEventListener('change', updateSelectedPlanPrice);
            updateSelectedPlanPrice();
        })();

        // Shared branded confirmation for destructive/sensitive form actions.
        (function() {
            var modal = document.getElementById('confirm-action-modal');
            if (!modal) return;

            var titleEl = document.getElementById('confirm-action-title');
            var messageEl = document.getElementById('confirm-action-message');
            var confirmBtn = document.getElementById('confirm-action-submit');
            var cancelBtn = document.getElementById('confirm-action-cancel');
            var closeBtn = document.getElementById('confirm-action-close');
            var pendingForm = null;

            function closeModal() {
                modal.style.display = 'none';
                pendingForm = null;
            }

            function openModal(form) {
                pendingForm = form;
                titleEl.textContent = form.getAttribute('data-confirm-title') || 'Confirm Action';
                messageEl.textContent = form.getAttribute('data-confirm-message') || 'Are you sure you want to continue?';
                confirmBtn.textContent = form.getAttribute('data-confirm-button') || 'Confirm';
                modal.style.display = 'flex';
            }

            document.querySelectorAll('form[data-confirm-message]').forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.dataset.confirmed === '1') {
                        form.dataset.confirmed = '0';
                        return;
                    }
                    event.preventDefault();
                    openModal(form);
                });
            });

            confirmBtn.addEventListener('click', function() {
                if (!pendingForm) return;
                var formToSubmit = pendingForm;
                closeModal();
                formToSubmit.dataset.confirmed = '1';
                formToSubmit.submit();
            });

            cancelBtn.addEventListener('click', closeModal);
            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function(event) {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && modal.style.display === 'flex') {
                    closeModal();
                }
            });
        })();

        function syncEditBillingToggle() {
            var roleSelect = document.getElementById('edit-staff-role-id');
            var billingToggleGroup = document.getElementById('edit-billing-toggle-group');
            var billingCheckbox = document.getElementById('edit-can-manage-billing');
            if (!roleSelect || !billingToggleGroup || !billingCheckbox) {
                return;
            }

            var selectedOption = roleSelect.options[roleSelect.selectedIndex];
            var selectedRoleName = selectedOption ? String(selectedOption.text || '').trim() : '';
            var isAdminRole = selectedRoleName === 'Admin';

            billingToggleGroup.style.display = isAdminRole ? 'flex' : 'none';
            if (!isAdminRole) {
                billingCheckbox.checked = false;
            }
        }

        // Wire up Edit Staff buttons
        document.querySelectorAll('.btn-edit-staff').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('edit-staff-user-id').value    = this.dataset.userId;
                document.getElementById('edit-staff-first-name').value = this.dataset.firstName;
                document.getElementById('edit-staff-last-name').value  = this.dataset.lastName;
                document.getElementById('edit-staff-email').value      = this.dataset.email;
                document.getElementById('edit-staff-status').value     = this.dataset.status;
                document.getElementById('edit-can-manage-billing').checked = this.dataset.canManageBilling === '1';

                var roleSelect = document.getElementById('edit-staff-role-id');
                for (var i = 0; i < roleSelect.options.length; i++) {
                    if (roleSelect.options[i].value == this.dataset.roleId) {
                        roleSelect.selectedIndex = i;
                        break;
                    }
                }
                syncEditBillingToggle();
                document.getElementById('edit-staff-modal').style.display = 'flex';
            });
        });

        var editStaffRoleSelect = document.getElementById('edit-staff-role-id');
        if (editStaffRoleSelect) {
            editStaffRoleSelect.addEventListener('change', syncEditBillingToggle);
        }

        function closeEditPaymentMethodModal() {
            var modal = document.getElementById('edit-payment-method-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function openEditPaymentMethodModal(button) {
            if (!button) return;

            document.getElementById('edit-payment-method-id').value = button.dataset.methodId || '';
            document.getElementById('edit-payment-cardholder-name').value = button.dataset.cardholderName || '';
            document.getElementById('edit-payment-exp-month').value = button.dataset.expMonth || '';
            document.getElementById('edit-payment-exp-year').value = button.dataset.expYear || '';
            document.getElementById('edit-payment-is-default').checked = button.dataset.isDefault === '1';
            document.getElementById('edit-payment-method-mask').textContent = 'Card ending in ' + (button.dataset.lastFour || '----');

            var modal = document.getElementById('edit-payment-method-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        var editPaymentMethodModal = document.getElementById('edit-payment-method-modal');
        if (editPaymentMethodModal) {
            editPaymentMethodModal.addEventListener('click', function(event) {
                if (event.target === editPaymentMethodModal) {
                    closeEditPaymentMethodModal();
                }
            });
        }
    </script>

    <script>
        // Live preview for ALL color pickers
        const colorMappings = [
            { id: 'primary-color', cssVar: '--primary-color', rgbVar: '--primary-rgb' }
        ];

        function hexToRgb(hex) {
            hex = hex.replace('#', '');
            const r = parseInt(hex.substring(0, 2), 16);
            const g = parseInt(hex.substring(2, 4), 16);
            const b = parseInt(hex.substring(4, 6), 16);
            return r + ', ' + g + ', ' + b;
        }

        colorMappings.forEach(function(mapping) {
            const el = document.getElementById(mapping.id);
            if (!el) return;
            el.addEventListener('input', function() {
                document.documentElement.style.setProperty(mapping.cssVar, this.value);
                // Update the hex text display next to the picker
                const hexSpan = this.parentElement.querySelector('.color-hex');
                if (hexSpan) hexSpan.textContent = this.value;
                // If it has an RGB variant too (e.g. primary), update that
                if (mapping.rgbVar) {
                    document.documentElement.style.setProperty(mapping.rgbVar, hexToRgb(this.value));
                }
            });
        });
    </script>
    <script>
        document.querySelectorAll('.site-alert').forEach(function(el) {
            setTimeout(function() {
                el.style.transition = 'opacity 0.4s ease, margin 0.4s ease, padding 0.4s ease, max-height 0.4s ease';
                el.style.opacity = '0';
                el.style.maxHeight = el.offsetHeight + 'px';
                requestAnimationFrame(function() {
                    el.style.maxHeight = '0';
                    el.style.padding = '0 1rem';
                    el.style.margin = '0 2rem';
                });
                setTimeout(function() { el.remove(); }, 450);
            }, 5000);
        });
    </script>
    <script>
    // ── Website Editor JS ──
    (function() {
        // Section nav (Layout Template / Edit Content / Preview)
        var navLinks = document.querySelectorAll('#we-section-nav .we-nav-link');
        var sections = document.querySelectorAll('.we-editor-section');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                var target = link.dataset.section;
                navLinks.forEach(function(l) { l.classList.remove('active'); });
                link.classList.add('active');
                sections.forEach(function(s) { s.classList.remove('active'); });
                var el = document.getElementById(target);
                if (el) el.classList.add('active');
            });
        });

        // Content tabs (Hero / About / Services / Contact / Visibility / Advanced)
        var tabs = document.querySelectorAll('.we-editor-tab');
        var tabContents = document.querySelectorAll('.we-tab-content');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                tabs.forEach(function(t) { t.classList.remove('active'); });
                tab.classList.add('active');
                tabContents.forEach(function(tc) { tc.classList.remove('active'); });
                var target = document.getElementById(tab.dataset.tab);
                if (target) target.classList.add('active');
            });
        });
    })();

    function weAddServiceRow() {
        var list = document.getElementById('we-services-list');
        var emptyMsg = list.querySelector('p');
        if (emptyMsg) emptyMsg.remove();
        var row = document.createElement('div');
        row.className = 'we-service-row';
        row.innerHTML = '<input type="text" name="service_title[]" class="we-form-input" placeholder="Service name">' +
            '<textarea name="service_description[]" class="we-form-textarea" rows="2" placeholder="Brief description"></textarea>' +
            '<input type="text" name="service_icon[]" class="we-form-input" placeholder="star" value="star">' +
            '<button type="button" class="we-btn-remove" onclick="this.closest(\'.we-service-row\').remove()" title="Remove"><span class="material-symbols-rounded">close</span></button>';
        list.appendChild(row);
    }
    </script>
    <script>
    (function () {
        var form = document.getElementById('credit-policy-form');
        if (!form) return;

        var defaults = {};
        try {
            defaults = JSON.parse(form.getAttribute('data-credit-policy-defaults') || '{}') || {};
        } catch (e) {
            defaults = {};
        }

        function byId(id) {
            return document.getElementById(id);
        }

        function setText(id, text) {
            var el = byId(id);
            if (el) el.textContent = text;
        }

        function getValue(id) {
            var el = byId(id);
            return el ? el.value : '';
        }

        function getNumber(id) {
            var value = parseFloat(getValue(id));
            return isFinite(value) ? value : 0;
        }

        function getInt(id) {
            var value = parseInt(getValue(id), 10);
            return isFinite(value) ? value : 0;
        }

        function isChecked(id) {
            var el = byId(id);
            return !!(el && el.checked);
        }

        function getCheckedValues(name) {
            return Array.prototype.slice.call(form.querySelectorAll('input[name="' + name + '[]"]:checked')).map(function (el) {
                return el.value;
            });
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                currencyDisplay: 'narrowSymbol',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(Number(amount || 0));
        }

        function formatNumber(value) {
            var number = Number(value || 0);
            if (!isFinite(number)) return '0';
            if (Math.abs(number - Math.round(number)) < 0.00001) {
                return String(Math.round(number));
            }
            return number.toFixed(2).replace(/\.?0+$/, '');
        }

        function roundDown(amount, roundTo) {
            if (!(roundTo > 0)) return amount;
            return Math.floor(amount / roundTo) * roundTo;
        }

        function setDecisionTone(id, decision) {
            var el = byId(id);
            if (!el) return;
            el.classList.remove('is-approve', 'is-review', 'is-reject');
            if (decision === 'Approve') {
                el.classList.add('is-approve');
            } else if (decision === 'Reject') {
                el.classList.add('is-reject');
            } else {
                el.classList.add('is-review');
            }
        }

        var tabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-credit-policy-tab]'));
        var tabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-credit-policy-tab-panel]'));

        function getEmploymentStatuses() {
            return getCheckedValues('cp_allowed_employment_statuses');
        }

        function setPolicyTab(tabName) {
            var activeTab = tabName;
            var tabExists = tabButtons.some(function (tab) {
                return tab.getAttribute('data-credit-policy-tab') === tabName;
            });

            if (!tabExists && tabButtons.length) {
                activeTab = tabButtons[0].getAttribute('data-credit-policy-tab');
            }

            tabButtons.forEach(function (tab) {
                tab.classList.toggle('is-active', tab.getAttribute('data-credit-policy-tab') === activeTab);
            });

            tabPanels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-credit-policy-tab-panel') !== activeTab;
            });
        }

        function applyDefaults() {
            if (!defaults || typeof defaults !== 'object') return;

            function setValue(id, value) {
                var el = byId(id);
                if (el) el.value = value;
            }

            function setChecked(id, value) {
                var el = byId(id);
                if (el) el.checked = !!value;
            }

            function setGroup(name, values) {
                var lookup = {};
                (Array.isArray(values) ? values : []).forEach(function (value) {
                    lookup[value] = true;
                });
                form.querySelectorAll('input[name="' + name + '[]"]').forEach(function (el) {
                    el.checked = !!lookup[el.value];
                });
            }

            setValue('cp-min-monthly-income', defaults.eligibility ? defaults.eligibility.min_monthly_income : 0);
            setGroup('cp_allowed_employment_statuses', defaults.eligibility ? defaults.eligibility.allowed_employment_statuses : []);

            setValue('cp-not-recommended-score', defaults.score_thresholds ? defaults.score_thresholds.not_recommended_min_score : 200);
            setValue('cp-conditional-score', defaults.score_thresholds ? defaults.score_thresholds.conditional_min_score : 400);
            setValue('cp-recommended-score', defaults.score_thresholds ? defaults.score_thresholds.recommended_min_score : 600);
            setValue('cp-highly-recommended-score', defaults.score_thresholds ? defaults.score_thresholds.highly_recommended_min_score : 800);
            setValue('cp-new-client-default-score', defaults.score_thresholds ? defaults.score_thresholds.new_client_default_score : 500);

            setChecked('cp-require-ci', defaults.ci_rules ? defaults.ci_rules.require_ci : false);
            setValue('cp-ci-required-above-amount', defaults.ci_rules ? defaults.ci_rules.ci_required_above_amount : 0);
            setGroup('cp_auto_approve_ci_values', defaults.ci_rules ? defaults.ci_rules.auto_approve_ci_values : []);
            setGroup('cp_review_ci_values', defaults.ci_rules ? defaults.ci_rules.review_ci_values : []);

            setValue('cp-income-multiplier', defaults.credit_limit ? defaults.credit_limit.income_multiplier : 0);
            setValue('cp-approve-band-multiplier', defaults.credit_limit ? defaults.credit_limit.approve_band_multiplier : 1.10);
            setValue('cp-review-band-multiplier', defaults.credit_limit ? defaults.credit_limit.review_band_multiplier : 1.00);
            setValue('cp-reject-band-multiplier', defaults.credit_limit ? defaults.credit_limit.reject_band_multiplier : 0.50);
            setValue('cp-max-credit-limit-cap', defaults.credit_limit ? defaults.credit_limit.max_credit_limit_cap : 0);
            setValue('cp-round-to-nearest', defaults.credit_limit ? defaults.credit_limit.round_to_nearest : 0);

            setChecked('cp-use-product-min-score', defaults.product_checks ? defaults.product_checks.use_product_minimum_credit_score : false);
            setChecked('cp-use-product-min-amount', defaults.product_checks ? defaults.product_checks.use_product_min_amount : false);
            setChecked('cp-use-product-max-amount', defaults.product_checks ? defaults.product_checks.use_product_max_amount : false);

            syncCreditPolicyUI();
        }

        function syncCreditPolicyUI() {
            var scoreCeiling = Math.max(1, <?php echo (int)$credit_policy_score_ceiling; ?>);
            var reviewBandCeiling = Math.max(0, scoreCeiling - 1);
            var activeId = document.activeElement ? document.activeElement.id : '';
            var rejectBelow = Math.max(0, Math.min(reviewBandCeiling, getInt('cp-reject-below-score')));
            var reviewEndRaw = Math.max(0, Math.min(reviewBandCeiling, getInt('cp-review-end-score')));
            var approveFromRaw = Math.max(1, Math.min(scoreCeiling, getInt('cp-approve-min-score')));
            var reviewBandEnd = Math.max(rejectBelow, reviewEndRaw);
            var approveFrom = Math.min(scoreCeiling, Math.max(reviewBandEnd + 1, approveFromRaw));

            if (activeId === 'cp-approve-min-score') {
                approveFrom = Math.max(rejectBelow + 1, approveFromRaw);
                reviewBandEnd = Math.max(rejectBelow, Math.min(reviewBandCeiling, approveFrom - 1));
            } else {
                reviewBandEnd = Math.max(rejectBelow, reviewEndRaw);
                approveFrom = Math.min(scoreCeiling, Math.max(reviewBandEnd + 1, approveFromRaw));
                if (activeId === 'cp-review-end-score' || activeId === 'cp-reject-below-score' || approveFromRaw !== reviewBandEnd + 1) {
                    approveFrom = Math.min(scoreCeiling, reviewBandEnd + 1);
                }
            }

            setValue('cp-reject-below-score', rejectBelow);
            setValue('cp-review-end-score', reviewBandEnd);
            setValue('cp-approve-min-score', approveFrom);
            var defaultScoreRaw = Math.max(0, Math.min(scoreCeiling, getInt('cp-new-client-default-score')));
            setValue('cp-new-client-default-score', defaultScoreRaw);
            var defaultBandLabel = '';
            var defaultBandColor = '';
            if (defaultScoreRaw < rejectBelow) {
                defaultBandLabel = 'New clients will land in the Reject band (' + defaultScoreRaw + ' < ' + rejectBelow + ')';
                defaultBandColor = 'var(--danger-color, #ef4444)';
            } else if (defaultScoreRaw <= reviewBandEnd) {
                defaultBandLabel = 'New clients will land in the Review band (' + rejectBelow + '–' + reviewBandEnd + ')';
                defaultBandColor = 'var(--warning-color, #f59e0b)';
            } else {
                defaultBandLabel = 'New clients will land in the Approve band (' + approveFrom + '+)';
                defaultBandColor = 'var(--success-color, #22c55e)';
            }
            var hintEl = byId('credit-policy-default-score-band-hint');
            if (hintEl) {
                hintEl.textContent = defaultBandLabel;
                hintEl.style.color = defaultBandColor;
            }
            var incomeMultiplier = Math.max(0, getNumber('cp-income-multiplier'));
            var cap = Math.max(0, getNumber('cp-max-credit-limit-cap'));
            var roundTo = Math.max(0, getNumber('cp-round-to-nearest'));
            var requireCi = isChecked('cp-require-ci');
            var ciAbove = Math.max(0, getNumber('cp-ci-required-above-amount'));
            var allowedEmploymentCount = getEmploymentStatuses().length;
            var autoCiCount = getCheckedValues('cp_auto_approve_ci_values').length;
            var reviewCiCount = getCheckedValues('cp_review_ci_values').length;
            var productCheckCount = ['cp-use-product-min-score', 'cp-use-product-min-amount', 'cp-use-product-max-amount'].filter(function (id) {
                return isChecked(id);
            }).length;

            var ciMode = requireCi ? 'Always required' : (ciAbove > 0 ? 'Required above ' + formatCurrency(ciAbove) : 'Optional');
            var formulaText = 'Start with monthly income x ' + formatNumber(incomeMultiplier) + ' x score strength x band multiplier';
            var capText = cap > 0 ? formatCurrency(cap) : 'No cap';
            var roundText = roundTo > 0 ? formatCurrency(roundTo) : 'No rounding';

            setText('credit-policy-badge-reject', 'Below ' + rejectBelow);
            setText('credit-policy-badge-review', rejectBelow + '-' + reviewBandEnd);
            setText('credit-policy-badge-approve', approveFrom + '+');
            setText('credit-policy-badge-ci-mode', ciMode);
            setText('credit-policy-badge-product-count', productCheckCount + ' product checks');

            setText('credit-policy-employment-count-badge', allowedEmploymentCount + ' statuses enabled');
            setText('credit-policy-employment-count-text', allowedEmploymentCount + ' selected');
            setText('credit-policy-ci-count-badge', autoCiCount + ' approve / ' + reviewCiCount + ' review');
            setText('credit-policy-auto-ci-count-text', autoCiCount + ' selected');
            setText('credit-policy-review-ci-count-text', reviewCiCount + ' selected');

            setText('credit-policy-threshold-copy-reject', 'Below ' + rejectBelow);
            setText('credit-policy-threshold-copy-review', rejectBelow + '-' + reviewBandEnd);
            setText('credit-policy-threshold-copy-approve', approveFrom + ' and above');

            setText('credit-policy-formula-preview', formulaText);
            setText('credit-policy-formula-cap', capText);
            setText('credit-policy-formula-round', roundText);
            setText('credit-policy-preview-summary', 'Reject below ' + rejectBelow + ', manual review ' + rejectBelow + '-' + reviewBandEnd + ', approval candidate ' + approveFrom + '+.');
            setText('credit-policy-preview-caption', ciMode + '. Maximum offer ' + capText + '. ' + (roundTo > 0 ? 'Rounded to nearest ' + roundText + '.' : 'No rounding rule.'));

            var thresholdWarning = byId('credit-policy-threshold-warning');
            var rejectInput = byId('cp-reject-below-score');
            var reviewEndInput = byId('cp-review-end-score');
            var approveInput = byId('cp-approve-min-score');
            var hasThresholdConflict = reviewBandEnd < rejectBelow || approveFrom <= reviewBandEnd;
            if (thresholdWarning) {
                thresholdWarning.hidden = !hasThresholdConflict;
            }
            if (rejectInput) {
                rejectInput.classList.toggle('is-warning', hasThresholdConflict);
                rejectInput.setAttribute('aria-invalid', hasThresholdConflict ? 'true' : 'false');
            }
            if (reviewEndInput) {
                reviewEndInput.classList.toggle('is-warning', hasThresholdConflict);
                reviewEndInput.setAttribute('aria-invalid', hasThresholdConflict ? 'true' : 'false');
            }
            if (approveInput) {
                approveInput.classList.toggle('is-warning', hasThresholdConflict);
                approveInput.setAttribute('aria-invalid', hasThresholdConflict ? 'true' : 'false');
            }

            syncSimulator();
        }

        function syncSimulator() {
            var income = Math.max(0, getNumber('credit-policy-sim-income'));
            var scoreCeiling = Math.max(1, <?php echo (int)$credit_policy_score_ceiling; ?>);
            var simScore = getNumber('credit-policy-sim-score');
            var defaultScore = Math.max(0, Math.min(scoreCeiling, getInt('cp-new-client-default-score')));
            var effectiveScore = simScore > 0 ? Math.max(0, Math.min(scoreCeiling, simScore)) : defaultScore;
            var requested = Math.max(0, getNumber('credit-policy-sim-requested'));
            var incomeMultiplier = Math.max(0, getNumber('cp-income-multiplier'));
            var approveBandMult = Math.max(0, getNumber('cp-approve-band-multiplier'));
            var reviewBandMult = Math.max(0, getNumber('cp-review-band-multiplier'));
            var rejectBandMult = Math.max(0, getNumber('cp-reject-band-multiplier'));
            var cap = Math.max(0, getNumber('cp-max-credit-limit-cap'));
            var roundTo = Math.max(0, getNumber('cp-round-to-nearest'));
            var reviewBandCeiling = Math.max(0, scoreCeiling - 1);
            var rejectBelow = Math.max(0, Math.min(reviewBandCeiling, getInt('cp-reject-below-score')));
            var reviewBandEnd = Math.max(rejectBelow, Math.max(0, Math.min(reviewBandCeiling, getInt('cp-review-end-score'))));
            var approveFrom = Math.max(reviewBandEnd + 1, Math.min(scoreCeiling, getInt('cp-approve-min-score')));

            var bandMultiplier = rejectBandMult;
            if (effectiveScore >= approveFrom) {
                bandMultiplier = approveBandMult;
            } else if (effectiveScore >= rejectBelow) {
                bandMultiplier = reviewBandMult;
            }

            var scoreFactor = Math.max(0, Math.min(1, effectiveScore / scoreCeiling));
            var computedLimit = income * incomeMultiplier * scoreFactor * bandMultiplier;
            if (cap > 0) computedLimit = Math.min(computedLimit, cap);
            computedLimit = roundDown(computedLimit, roundTo);

            var suggestedOffer = requested > 0 ? Math.min(requested, computedLimit) : computedLimit;
            var decision = 'Approve';

            if (effectiveScore < rejectBelow) {
                decision = 'Reject';
            } else if (effectiveScore <= reviewBandEnd) {
                decision = 'Review';
            } else if (requested > 0 && suggestedOffer < requested) {
                decision = 'Review';
            }

            if (!(computedLimit > 0)) {
                decision = 'Review';
            }

            var decisionCaption = 'Based on the current score band and requested amount.';
            if (!(computedLimit > 0)) {
                decisionCaption = 'The current inputs do not produce a usable credit limit yet.';
            } else if (decision === 'Reject') {
                decisionCaption = 'The score falls below the reject threshold.';
            } else if (effectiveScore <= reviewBandEnd) {
                decisionCaption = 'The score is still inside the review band.';
            } else if (requested > 0 && suggestedOffer < requested) {
                decisionCaption = 'The estimated offer is lower than the requested amount.';
            } else if (decision === 'Approve') {
                decisionCaption = 'The current inputs line up with approval conditions.';
            }

            setText('credit-policy-sim-limit', formatCurrency(computedLimit));
            setText('credit-policy-sim-offer', formatCurrency(suggestedOffer > 0 ? suggestedOffer : 0));
            setText('credit-policy-sim-decision', decision);
            setText('credit-policy-sim-caption', decisionCaption);
            setText('credit-policy-sim-formula', 'Income x ' + formatNumber(incomeMultiplier) + ' x score ' + scoreFactor.toFixed(2) + ' x band ' + formatNumber(bandMultiplier));
            setDecisionTone('credit-policy-sim-decision-card', decision);
        }

        function getScoreThresholdState() {
            var scoreCeiling = Math.max(1, <?php echo (int)$credit_policy_score_ceiling; ?>);
            var notRecommendedFrom = Math.max(0, Math.min(Math.max(0, scoreCeiling - 3), getInt('cp-not-recommended-score')));
            var conditionalFrom = Math.max(1, Math.min(Math.max(1, scoreCeiling - 2), getInt('cp-conditional-score')));
            var recommendedFrom = Math.max(2, Math.min(Math.max(2, scoreCeiling - 1), getInt('cp-recommended-score')));
            var highlyRecommendedFrom = Math.max(3, Math.min(scoreCeiling, getInt('cp-highly-recommended-score')));

            conditionalFrom = Math.max(notRecommendedFrom + 1, conditionalFrom);
            recommendedFrom = Math.max(conditionalFrom + 1, recommendedFrom);
            highlyRecommendedFrom = Math.max(recommendedFrom + 1, highlyRecommendedFrom);

            setValue('cp-not-recommended-score', notRecommendedFrom);
            setValue('cp-conditional-score', conditionalFrom);
            setValue('cp-recommended-score', recommendedFrom);
            setValue('cp-highly-recommended-score', highlyRecommendedFrom);

            return {
                scoreCeiling: scoreCeiling,
                notRecommendedFrom: notRecommendedFrom,
                conditionalFrom: conditionalFrom,
                recommendedFrom: recommendedFrom,
                highlyRecommendedFrom: highlyRecommendedFrom,
                atRiskEnd: Math.max(0, notRecommendedFrom - 1),
                notRecommendedEnd: Math.max(notRecommendedFrom, conditionalFrom - 1),
                conditionalEnd: Math.max(conditionalFrom, recommendedFrom - 1),
                recommendedEnd: Math.max(recommendedFrom, highlyRecommendedFrom - 1)
            };
        }

        function getScoreRecommendation(scoreValue, thresholds) {
            if (scoreValue >= thresholds.highlyRecommendedFrom) return 'High Credit Score';
            if (scoreValue >= thresholds.recommendedFrom) return 'Good Credit Score';
            if (scoreValue >= thresholds.conditionalFrom) return 'Standard Credit Score';
            if (scoreValue >= thresholds.notRecommendedFrom) return 'Fair Credit Score';
            return 'At-Risk Credit Score';
        }

        function getScoreRecommendationRoute(recommendation) {
            if (recommendation === 'High Credit Score' || recommendation === 'Good Credit Score') return 'approve';
            if (recommendation === 'Standard Credit Score') return 'review';
            return 'reject';
        }

        function getScoreRecommendationRange(recommendation, thresholds) {
            if (recommendation === 'High Credit Score') return thresholds.highlyRecommendedFrom + '+';
            if (recommendation === 'Good Credit Score') return thresholds.recommendedFrom + '-' + thresholds.recommendedEnd;
            if (recommendation === 'Standard Credit Score') return thresholds.conditionalFrom + '-' + thresholds.conditionalEnd;
            if (recommendation === 'Fair Credit Score') return thresholds.notRecommendedFrom + '-' + thresholds.notRecommendedEnd;
            return 'Below ' + thresholds.notRecommendedFrom;
        }

        function syncCreditPolicyUI() {
            var thresholds = getScoreThresholdState();
            var scoreCeiling = thresholds.scoreCeiling;
            var defaultScoreRaw = Math.max(0, Math.min(scoreCeiling, getInt('cp-new-client-default-score')));
            setValue('cp-new-client-default-score', defaultScoreRaw);

            var defaultRecommendation = getScoreRecommendation(defaultScoreRaw, thresholds);
            var defaultRoute = getScoreRecommendationRoute(defaultRecommendation);
            var defaultBandLabel = 'New clients will start in the ' + defaultRecommendation + ' range (' + getScoreRecommendationRange(defaultRecommendation, thresholds) + ').';
            if (defaultRoute === 'reject') {
                defaultBandLabel += ' Under the current routing rules, this leads to Reject.';
            } else if (defaultRoute === 'review') {
                defaultBandLabel += ' Under the current routing rules, this leads to Manual Review.';
            } else {
                defaultBandLabel += ' Under the current routing rules, this remains an Approval Candidate if the other rules also pass.';
            }

            var defaultBandColor = defaultRoute === 'reject'
                ? 'var(--danger-color, #ef4444)'
                : (defaultRoute === 'review' ? 'var(--warning-color, #f59e0b)' : 'var(--success-color, #22c55e)');
            var hintEl = byId('credit-policy-default-score-band-hint');
            if (hintEl) {
                hintEl.textContent = defaultBandLabel;
                hintEl.style.color = defaultBandColor;
            }

            var incomeMultiplier = Math.max(0, getNumber('cp-income-multiplier'));
            var cap = Math.max(0, getNumber('cp-max-credit-limit-cap'));
            var roundTo = Math.max(0, getNumber('cp-round-to-nearest'));
            var allowedEmploymentCount = getEmploymentStatuses().length;
            var autoCiCount = getCheckedValues('cp_auto_approve_ci_values').length;
            var reviewCiCount = getCheckedValues('cp_review_ci_values').length;
            var formulaText = 'Start with monthly income x ' + formatNumber(incomeMultiplier) + ' x score strength x classification multiplier';
            var capText = cap > 0 ? formatCurrency(cap) : 'No cap';
            var roundText = roundTo > 0 ? formatCurrency(roundTo) : 'No rounding';

            setText('credit-policy-badge-at-risk', 'Below ' + thresholds.notRecommendedFrom);
            setText('credit-policy-badge-not-recommended', thresholds.notRecommendedFrom + '-' + thresholds.notRecommendedEnd);
            setText('credit-policy-badge-conditional', thresholds.conditionalFrom + '-' + thresholds.conditionalEnd);
            setText('credit-policy-badge-recommended', thresholds.recommendedFrom + '-' + thresholds.recommendedEnd);
            setText('credit-policy-badge-highly-recommended', thresholds.highlyRecommendedFrom + '+');

            setText('credit-policy-employment-count-badge', allowedEmploymentCount + ' statuses enabled');
            setText('credit-policy-employment-count-text', allowedEmploymentCount + ' selected');
            setText('credit-policy-ci-count-badge', autoCiCount + ' approve / ' + reviewCiCount + ' review');
            setText('credit-policy-auto-ci-count-text', autoCiCount + ' selected');
            setText('credit-policy-review-ci-count-text', reviewCiCount + ' selected');

            setText('credit-policy-threshold-copy-at-risk', 'Below ' + thresholds.notRecommendedFrom);
            setText('credit-policy-threshold-copy-not-recommended', thresholds.notRecommendedFrom + '-' + thresholds.notRecommendedEnd);
            setText('credit-policy-threshold-copy-conditional', thresholds.conditionalFrom + '-' + thresholds.conditionalEnd);
            setText('credit-policy-threshold-copy-recommended', thresholds.recommendedFrom + '-' + thresholds.recommendedEnd);
            setText('credit-policy-threshold-copy-highly-recommended', thresholds.highlyRecommendedFrom + ' and above');
            setText('credit-policy-threshold-routing-copy', 'Decision routing: Below ' + thresholds.conditionalFrom + ' = Reject, ' + thresholds.conditionalFrom + '-' + thresholds.conditionalEnd + ' = Manual Review, ' + thresholds.recommendedFrom + ' and above = Approval Candidate.');

            setText('credit-policy-formula-preview', formulaText);
            setText('credit-policy-formula-cap', capText);
            setText('credit-policy-formula-round', roundText);
            setText('credit-policy-preview-summary', 'Below ' + thresholds.notRecommendedFrom + ' = At-Risk Credit Score, ' + thresholds.notRecommendedFrom + '-' + thresholds.notRecommendedEnd + ' = Fair Credit Score, ' + thresholds.conditionalFrom + '-' + thresholds.conditionalEnd + ' = Standard Credit Score, ' + thresholds.recommendedFrom + '-' + thresholds.recommendedEnd + ' = Good Credit Score, ' + thresholds.highlyRecommendedFrom + '+ = High Credit Score.');
            setText('credit-policy-preview-caption', 'Decision routing: Below ' + thresholds.conditionalFrom + ' = Reject, ' + thresholds.conditionalFrom + '-' + thresholds.conditionalEnd + ' = Manual Review, ' + thresholds.recommendedFrom + '+ = Approval Candidate. Maximum offer ' + capText + '. ' + (roundTo > 0 ? 'Rounded to nearest ' + roundText + '.' : 'No rounding rule.'));

            var thresholdWarning = byId('credit-policy-threshold-warning');
            if (thresholdWarning) {
                thresholdWarning.hidden = true;
            }
            ['cp-not-recommended-score', 'cp-conditional-score', 'cp-recommended-score', 'cp-highly-recommended-score'].forEach(function (id) {
                var el = byId(id);
                if (!el) return;
                el.classList.remove('is-warning');
                el.setAttribute('aria-invalid', 'false');
            });

            syncSimulator(thresholds);
        }

        function syncSimulator(thresholds) {
            thresholds = thresholds || getScoreThresholdState();

            var income = Math.max(0, getNumber('credit-policy-sim-income'));
            var scoreCeiling = thresholds.scoreCeiling;
            var simScore = getNumber('credit-policy-sim-score');
            var defaultScore = Math.max(0, Math.min(scoreCeiling, getInt('cp-new-client-default-score')));
            var effectiveScore = simScore > 0 ? Math.max(0, Math.min(scoreCeiling, simScore)) : defaultScore;
            var requested = Math.max(0, getNumber('credit-policy-sim-requested'));
            var incomeMultiplier = Math.max(0, getNumber('cp-income-multiplier'));
            var approveBandMult = Math.max(0, getNumber('cp-approve-band-multiplier'));
            var reviewBandMult = Math.max(0, getNumber('cp-review-band-multiplier'));
            var rejectBandMult = Math.max(0, getNumber('cp-reject-band-multiplier'));
            var cap = Math.max(0, getNumber('cp-max-credit-limit-cap'));
            var roundTo = Math.max(0, getNumber('cp-round-to-nearest'));

            var recommendation = getScoreRecommendation(effectiveScore, thresholds);
            var route = getScoreRecommendationRoute(recommendation);
            var bandMultiplier = route === 'approve' ? approveBandMult : (route === 'review' ? reviewBandMult : rejectBandMult);

            var scoreFactor = Math.max(0, Math.min(1, effectiveScore / scoreCeiling));
            var computedLimit = income * incomeMultiplier * scoreFactor * bandMultiplier;
            if (cap > 0) computedLimit = Math.min(computedLimit, cap);
            computedLimit = roundDown(computedLimit, roundTo);

            var suggestedOffer = requested > 0 ? Math.min(requested, computedLimit) : computedLimit;
            var decision = route === 'reject' ? 'Reject' : (route === 'review' ? 'Review' : 'Approve');

            if (!(computedLimit > 0)) {
                decision = 'Review';
            } else if (decision === 'Approve' && requested > 0 && suggestedOffer < requested) {
                decision = 'Review';
            }

            var decisionCaption = 'Score classification is ' + recommendation + '.';
            if (simScore <= 0) {
                decisionCaption += ' Using the new-client default score.';
            }
            if (!(computedLimit > 0)) {
                decisionCaption = 'The current inputs do not produce a usable credit limit yet.';
            } else if (decision === 'Reject') {
                decisionCaption += ' Under the current routing rules, this leads to Reject.';
            } else if (decision === 'Review') {
                if (route === 'review') {
                    decisionCaption += ' Under the current routing rules, this leads to Manual Review.';
                } else if (requested > 0 && suggestedOffer < requested) {
                    decisionCaption = 'Score classification is ' + recommendation + ', but the requested amount exceeds the estimated offer, so Manual Review is required.';
                }
            } else if (decision === 'Approve') {
                decisionCaption += ' This remains an Approval Candidate if the other rules also pass.';
            }

            setText('credit-policy-sim-limit', formatCurrency(computedLimit));
            setText('credit-policy-sim-offer', formatCurrency(suggestedOffer > 0 ? suggestedOffer : 0));
            setText('credit-policy-sim-decision', decision);
            setText('credit-policy-sim-caption', decisionCaption);
            setText('credit-policy-sim-formula', 'Income x ' + formatNumber(incomeMultiplier) + ' x score ' + scoreFactor.toFixed(2) + ' x ' + recommendation + ' multiplier ' + formatNumber(bandMultiplier));
            setDecisionTone('credit-policy-sim-decision-card', decision);
        }

        form.querySelectorAll('input, select').forEach(function (el) {
            el.addEventListener('input', syncCreditPolicyUI);
            el.addEventListener('change', syncCreditPolicyUI);
        });

        ['credit-policy-sim-income', 'credit-policy-sim-score', 'credit-policy-sim-requested'].forEach(function (id) {
            var el = byId(id);
            if (!el) return;
            el.addEventListener('input', syncSimulator);
            el.addEventListener('change', syncSimulator);
        });

        ['credit-policy-reset-trigger-top', 'credit-policy-reset-trigger-bottom'].forEach(function (id) {
            var btn = byId(id);
            if (!btn) return;
            btn.addEventListener('click', applyDefaults);
        });

        tabButtons.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setPolicyTab(tab.getAttribute('data-credit-policy-tab') || 'eligibility');
            });
        });

        syncCreditPolicyUI();
        setPolicyTab('score');
    })();
    </script>
    <script src="admin.js?v=<?php echo filemtime(__DIR__ . '/admin.js'); ?>"></script>
</body>

</html>
