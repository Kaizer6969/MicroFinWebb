<?php
require_once "../../backend/session_auth.php";
mf_start_backend_session();
require_once "../../backend/db_connect.php";
mf_require_tenant_session($pdo, [
    'response' => 'redirect',
    'redirect' => '../../tenant_login/login.php',
    'append_tenant_slug' => true,
]);

// 2. Authorization Check (Only Employees)
if ($_SESSION['user_type'] !== 'Employee') {
    header("Location: ../admin.php");
    exit;
}

// 3. Setup Wizard Check
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

$check_stmt = $pdo->prepare('SELECT force_password_change, role_id, ui_theme FROM users WHERE user_id = ? AND tenant_id = ?');
$check_stmt->execute([$user_id, $tenant_id]);
$user_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data || $user_data['force_password_change']) {
    header('Location: setup_wizard.php');
    exit;
}

$ui_theme = (($user_data['ui_theme'] ?? ($_SESSION['ui_theme'] ?? 'light')) === 'dark') ? 'dark' : 'light';
$_SESSION['ui_theme'] = $ui_theme;

// 4. Load Permissions
$role_id = $user_data['role_id'];
$perm_stmt = $pdo->prepare('
    SELECT p.permission_code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    WHERE rp.role_id = ?
');
$perm_stmt->execute([$role_id]);
$permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);

function has_permission($code) {
    global $permissions;
    return in_array($code, $permissions);
}

// Fetch Pending Applications
$pending_applications = [];
if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')) {
    $apps_stmt = $pdo->prepare("
        SELECT la.application_id, la.application_number, la.requested_amount, 
               la.application_status, la.submitted_date, la.created_at,
               c.first_name, c.last_name, lp.product_name
        FROM loan_applications la
        JOIN clients c ON la.client_id = c.client_id
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.tenant_id = ? AND la.application_status NOT IN ('Approved', 'Rejected', 'Cancelled', 'Withdrawn')
        ORDER BY COALESCE(la.submitted_date, la.created_at) DESC
    ");
    $apps_stmt->execute([$tenant_id]);
    $pending_applications = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Clients — ORDER BY registration_date (clients has no created_at column)
$all_clients = [];
$_client_debug = [
    'tenant_id'       => $tenant_id,
    'has_perm'        => (has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')),
    'query_error'     => null,
    'row_count'       => 0,
    'raw_count_check' => null,
];
if (has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')) {
    try {
        // Raw count first — simplest possible query to verify tenant_id match
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ?");
        $cnt->execute([$tenant_id]);
        $_client_debug['raw_count_check'] = $cnt->fetchColumn();

        $clients_stmt = $pdo->prepare("
            SELECT c.client_id, c.first_name, c.last_name, c.email_address,
                   c.contact_number, c.client_status, c.registration_date,
                   u.user_type
            FROM clients c
            JOIN users u ON c.user_id = u.user_id
            WHERE c.tenant_id = ?
            ORDER BY c.registration_date DESC
        ");
        $clients_stmt->execute([$tenant_id]);
        $all_clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);
        $_client_debug['row_count'] = count($all_clients);
    } catch (\Throwable $e) {
        $_client_debug['query_error'] = $e->getMessage();
    }
}

$loan_products = [];
$loan_products_stmt = $pdo->prepare("SELECT product_id, product_name, product_type, min_amount, max_amount, min_term_months, max_term_months, interest_rate FROM loan_products WHERE tenant_id = ? AND is_active = 1 ORDER BY product_name ASC");
$loan_products_stmt->execute([$tenant_id]);
$loan_products = $loan_products_stmt->fetchAll(PDO::FETCH_ASSOC);

$document_types = [];
$document_types_stmt = $pdo->query("SELECT document_type_id, document_name, loan_purpose, is_required FROM document_types WHERE is_active = 1 ORDER BY is_required DESC, document_name ASC");
$document_types = $document_types_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tenant branding
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_secondary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family, logo_path FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$tenant_brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);

$theme_color     = ($tenant_brand && $tenant_brand['theme_primary_color'])   ? $tenant_brand['theme_primary_color']   : '#2563eb';
$theme_sidebar   = ($tenant_brand && $tenant_brand['theme_secondary_color']) ? $tenant_brand['theme_secondary_color'] : '#0f172a';
$theme_text_main = ($tenant_brand && $tenant_brand['theme_text_main'])       ? $tenant_brand['theme_text_main']       : '#0f172a';
$theme_text_muted= ($tenant_brand && $tenant_brand['theme_text_muted'])      ? $tenant_brand['theme_text_muted']      : '#64748b';
$theme_bg_body   = ($tenant_brand && $tenant_brand['theme_bg_body'])         ? $tenant_brand['theme_bg_body']         : '#f1f5f9';
$theme_bg_card   = ($tenant_brand && $tenant_brand['theme_bg_card'])         ? $tenant_brand['theme_bg_card']         : '#ffffff';
$theme_font      = ($tenant_brand && $tenant_brand['font_family'])           ? $tenant_brand['font_family']           : 'DM Sans';
$logo_path       = ($tenant_brand && $tenant_brand['logo_path'])             ? $tenant_brand['logo_path']             : '';

// Compute sidebar text color (auto-contrast)
function hex_is_dark($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    $r = hexdec(substr($hex,0,2)); $g = hexdec(substr($hex,2,2)); $b = hexdec(substr($hex,4,2));
    $lum = 0.299*$r + 0.587*$g + 0.114*$b;
    return $lum < 140;
}

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

$sidebar_text = hex_is_dark($theme_sidebar) ? '#f8fafc' : '#0f172a';
$sidebar_text_muted = hex_is_dark($theme_sidebar) ? 'rgba(248,250,252,0.55)' : 'rgba(15,23,42,0.45)';
$sidebar_hover_bg = hex_is_dark($theme_sidebar) ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
$sidebar_active_bg = $theme_color . '22';

$name_parts = explode(' ', trim($_SESSION['username']));
$initials = strtoupper(substr($name_parts[0],0,1) . (isset($name_parts[1]) ? substr($name_parts[1],0,1) : ''));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($_SESSION['tenant_name']); ?> — Employee Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
<link rel="stylesheet" href="../admin.css">
<style>
/* ── CSS Variables (tenant-driven) ── */
:root {
    --primary-color:   <?php echo htmlspecialchars($theme_color); ?>;
    --primary-rgb:     <?php echo hexToRgb($theme_color); ?>;
    
    /* Backwards compatibility with dashboard existing sizes/names */
    --brand:           var(--primary-color);
    --brand-light:     rgba(var(--primary-rgb), 0.1);
    --brand-mid:       rgba(var(--primary-rgb), 0.3);
    --body-bg:         var(--bg-body);
    --card-bg:         var(--bg-card);
    --text:            var(--text-main);
    --muted:           var(--text-muted);
    --border:          var(--border-color);
    --font:            var(--font-family);

    --sidebar-bg:      <?php echo htmlspecialchars($theme_bg_card); ?>;
    --sidebar-text:    var(--text-main);
    --sidebar-muted:   var(--text-muted);
    --sidebar-hover:   var(--bg-body);
    --sidebar-active:  rgba(var(--primary-rgb), 0.1);
    --bg-body:         <?php echo htmlspecialchars($theme_bg_body); ?>;
    --bg-card:         <?php echo htmlspecialchars($theme_bg_card); ?>;
    --text-main:       <?php echo htmlspecialchars($theme_text_main); ?>;
    --text-muted:      <?php echo htmlspecialchars($theme_text_muted); ?>;
    --border-color:    #e2e8f0;
    --shadow-sm:       0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.05);
    --shadow-md:       0 4px 16px rgba(0,0,0,.08), 0 2px 6px rgba(0,0,0,.04);
    --shadow-lg:       0 10px 40px rgba(0,0,0,.12);
    --sidebar-w:       260px;
    --header-h:        70px;
    --radius:          12px;
    --radius-sm:       8px;
    --font-family:     '<?php echo htmlspecialchars($theme_font); ?>', sans-serif;
    --mono:            'JetBrains Mono', monospace;
    --transition:      .18s ease;
}

[data-theme="dark"] {
    --bg-body:  #0b1220;
    --bg-card:  #111827;
    --sidebar-bg: #111827;
    --text-main:#e5e7eb;
    --text-muted:#94a3b8;
    --border-color:#334155;
    --sidebar-text:#cbd5e1;
    --sidebar-active-bg: rgba(var(--primary-rgb), 0.24);

    --body-bg: var(--bg-body);
    --card-bg: var(--bg-card);
    --text: var(--text-main);
    --muted: var(--text-muted);
    --border: var(--border-color);
}

/* ── Reset & Base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; scroll-behavior: smooth; }
body { font-family: var(--font); background: var(--body-bg); color: var(--text); display: flex; min-height: 100vh; overflow: hidden; }

/* ── Sidebar ── */
.sidebar {
    width: var(--sidebar-w);
    min-height: 100vh;
    background: var(--sidebar-bg);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    position: fixed;
    top: 0; left: 0;
    z-index: 100;
    overflow-y: auto;
    transition: transform var(--transition);
}
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 20px 18px 16px;
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.logo-mark {
    width: 38px; height: 38px;
    border-radius: 9px;
    background: var(--brand);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
.logo-mark img { width: 100%; height: 100%; object-fit: cover; }
.logo-mark .ms { color: #fff; font-size: 20px; }
.logo-text { overflow: hidden; }
.logo-text h2 { font-size: .9rem; font-weight: 600; color: var(--sidebar-text); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.logo-text p  { font-size: .72rem; color: var(--sidebar-muted); }

.nav-section { padding: 12px 10px 4px; }
.nav-label { font-size: .65rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--sidebar-muted); padding: 0 8px; margin-bottom: 4px; }
.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px; border-radius: var(--radius-sm);
    color: var(--sidebar-muted);
    text-decoration: none;
    font-size: .85rem; font-weight: 500;
    cursor: pointer;
    transition: background var(--transition), color var(--transition);
    position: relative;
}
.nav-item:hover  { background: var(--sidebar-hover); color: var(--sidebar-text); }
.nav-item.active { background: var(--sidebar-active); color: var(--brand); }
.nav-item.active .ms { color: var(--brand); }
.nav-item .ms { font-size: 19px; flex-shrink: 0; transition: color var(--transition); }
.nav-badge {
    margin-left: auto;
    background: var(--brand);
    color: #fff;
    font-size: .65rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 99px;
    min-width: 18px;
    text-align: center;
}

.sidebar-footer {
    margin-top: auto;
    padding: 12px 10px;
    border-top: 1px solid rgba(255,255,255,.07);
}

/* ── Main Layout ── */
.main-wrap {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-width: 0;
}

/* ── Top Header ── */
.topbar {
    height: var(--header-h);
    background: var(--card-bg);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 16px;
    padding: 0 24px;
    position: sticky; top: 0; z-index: 90;
}
.topbar-title { font-size: 1rem; font-weight: 600; color: var(--text); flex: 1; }
.topbar-title span { color: var(--muted); font-weight: 400; font-size: .85rem; margin-left: 6px; }
.icon-btn {
    width: 34px; height: 34px; border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: transparent;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--muted);
    transition: background var(--transition), color var(--transition);
}
.icon-btn:hover { background: var(--brand-light); color: var(--brand); border-color: var(--brand-mid); }
.icon-btn .ms { font-size: 18px; }

.user-chip {
    display: flex; align-items: center; gap: 9px;
    padding: 5px 10px 5px 5px;
    border: 1px solid var(--border);
    border-radius: 99px;
    cursor: pointer;
    background: transparent;
    transition: background var(--transition);
}
.user-chip:hover { background: var(--brand-light); }
.avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--brand);
    color: #fff;
    font-size: .7rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.user-chip-name { font-size: .8rem; font-weight: 500; color: var(--text); }
.user-chip-role { font-size: .7rem; color: var(--muted); }

.btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px;
    background: var(--brand);
    color: #fff;
    border: none; border-radius: var(--radius-sm);
    font-size: .95rem; font-weight: 600;
    cursor: pointer;
    transition: opacity var(--transition), transform var(--transition);
}
.btn-primary:hover { opacity: .88; transform: translateY(-1px); }
.btn-primary .ms { font-size: 16px; }

/* ── Content Area ── */
.content { flex: 1; padding: 32px; overflow-y: auto; height: calc(100vh - var(--header-h)); }

/* ── View Sections ── */
.view { display: none; }
.view.active { display: block; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

/* ── Page Header ── */
.page-header {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 22px;
}
.page-icon {
    width: 42px; height: 42px; border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.page-header h1 { font-size: 1.3rem; font-weight: 700; color: var(--text); }
.page-header p  { font-size: .82rem; color: var(--muted); margin-top: 1px; }
.page-header-actions { margin-left: auto; display: flex; gap: 8px; }

/* ── Cards & Stats ── */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px; }
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    box-shadow: var(--shadow);
    transition: box-shadow var(--transition), transform var(--transition);
}
.stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.stat-label  { font-size: .75rem; color: var(--muted); font-weight: 500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .04em; }
.stat-value  { font-size: 1.6rem; font-weight: 700; color: var(--text); line-height: 1; }
.stat-value.brand { color: var(--brand); }
.stat-sub    { font-size: .72rem; color: var(--muted); margin-top: 4px; }
.stat-icon   { width: 32px; height: 32px; border-radius: var(--radius-sm); background: var(--brand-light); color: var(--brand); display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
.stat-icon .ms { font-size: 18px; }

.card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.card-header h3 { font-size: .92rem; font-weight: 600; color: var(--text); flex: 1; }
.card-header .ms { font-size: 18px; color: var(--brand); }

/* ── Tables ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: var(--body-bg); }
th {
    padding: 11px 16px;
    font-size: .72rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    text-align: left;
    white-space: nowrap;
}
td {
    padding: 12px 16px;
    font-size: .85rem;
    color: var(--text);
    border-top: 1px solid var(--border);
    vertical-align: middle;
}
tbody tr { transition: background var(--transition); }
tbody tr:hover { background: var(--brand-light); }
.td-muted { color: var(--muted) !important; }
.td-mono  { font-family: var(--mono); font-size: .8rem; }
.td-bold  { font-weight: 600; }
.empty-row td { text-align: center; padding: 40px 16px; color: var(--muted); }

/* ── Status Badges ── */
.badge {
    display: inline-flex; align-items: center;
    padding: 3px 9px; border-radius: 99px;
    font-size: .72rem; font-weight: 600;
    white-space: nowrap;
}
.badge-green  { background: #dcfce7; color: #166534; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-amber  { background: #fef3c7; color: #92400e; }
.badge-blue   { background: #dbeafe; color: #1e40af; }
.badge-purple { background: #ede9fe; color: #5b21b6; }
.badge-gray   { background: #f1f5f9; color: #475569; }
.badge-teal   { background: #ccfbf1; color: #115e59; }
[data-theme="dark"] .badge-green  { background: #14532d40; color: #86efac; }
[data-theme="dark"] .badge-red    { background: #7f1d1d40; color: #fca5a5; }
[data-theme="dark"] .badge-amber  { background: #78350f40; color: #fcd34d; }
[data-theme="dark"] .badge-blue   { background: #1e3a5f40; color: #93c5fd; }
[data-theme="dark"] .badge-purple { background: #3b076440; color: #c4b5fd; }
[data-theme="dark"] .badge-gray   { background: #1e293b;   color: #94a3b8; }

/* ── Buttons ── */
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: var(--radius-sm); font-size: .95rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; font-family: inherit; }
.btn .ms { font-size: 15px; }
.btn-sm { padding: 6px 12px; font-size: .85rem; }
.btn-outline { background: var(--card-bg); border: 1px solid var(--border); color: var(--text); }
.btn-outline:hover { background: var(--brand-light); border-color: var(--brand-mid); color: var(--brand); }
.btn-brand { background: var(--brand); color: #fff; }
.btn-brand:hover { opacity: .85; }
.btn-danger { background: #fee2e2; color: #991b1b; }
.btn-danger:hover { background: #fca5a5; }
.btn-success { background: #dcfce7; color: #166534; }
.btn-success:hover { background: #bbf7d0; }
.table-icon-btn { width: 34px; height: 34px; padding: 0; justify-content: center; }
.status-stack { display: flex; flex-direction: column; gap: 4px; }
.status-note { font-size: .72rem; color: var(--muted); line-height: 1.35; }

/* ── Filter Tabs ── */
.filter-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-tab {
    padding: 5px 14px;
    border-radius: 99px;
    font-size: .78rem; font-weight: 500;
    cursor: pointer; border: 1px solid var(--border);
    background: var(--card-bg); color: var(--muted);
    transition: all var(--transition);
}
.filter-tab:hover  { border-color: var(--brand); color: var(--brand); }
.filter-tab.active { background: var(--brand); color: #fff; border-color: var(--brand); }

/* ── Search Bar ── */
.search-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.search-input-wrap {
    flex: 1; display: flex; align-items: center; gap: 8px;
    padding: 7px 12px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    max-width: 340px;
}
.search-input-wrap .ms { color: var(--muted); font-size: 17px; }
.search-input-wrap input { border: none; outline: none; background: none; color: var(--text); font-family: var(--font); font-size: .85rem; flex: 1; }

/* ── Welcome Card (Home) ── */
.welcome-banner {
    background: linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 60%, #000) 100%);
    border-radius: var(--radius);
    padding: 24px 28px;
    margin-bottom: 22px;
    position: relative;
    overflow: hidden;
    color: #fff;
}
.welcome-banner::before {
    content: '';
    position: absolute; top: -30px; right: -30px;
    width: 160px; height: 160px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
}
.welcome-banner::after {
    content: '';
    position: absolute; bottom: -50px; right: 80px;
    width: 120px; height: 120px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
}
.welcome-banner h1 { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
.welcome-banner p  { font-size: .85rem; opacity: .8; }
.welcome-banner-meta { display: flex; gap: 20px; margin-top: 14px; }
.welcome-meta-item   { font-size: .78rem; opacity: .75; display: flex; align-items: center; gap: 5px; }
.welcome-meta-item .ms { font-size: 15px; }

/* ── Activity Feed ── */
.activity-item { display: flex; gap: 12px; padding: 12px 20px; border-top: 1px solid var(--border); }
.activity-item:first-child { border-top: none; }
.activity-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--brand); flex-shrink: 0; margin-top: 5px; }
.activity-text { font-size: .83rem; color: var(--text); line-height: 1.5; }
.activity-time { font-size: .72rem; color: var(--muted); margin-top: 2px; }

/* ── Modal ── */
.modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    backdrop-filter: blur(3px);
    display: none;
    align-items: center; justify-content: center;
    z-index: 500;
    padding: 20px;
}
.modal-backdrop.open { display: flex; }
.modal-backdrop.top  { align-items: flex-start; padding-top: 48px; }
.modal {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    width: 100%;
    max-height: 88vh;
    overflow-y: auto;
    animation: modalIn .22s ease;
}
@keyframes modalIn { from { opacity: 0; transform: scale(.96) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.modal-sm  { max-width: 440px; }
.modal-md  { max-width: 560px; }
.modal-lg  { max-width: 700px; }
.modal-xl  { max-width: 820px; }
.modal-header { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.modal-header h3 { font-size: 1rem; font-weight: 600; flex: 1; }
.modal-body   { padding: 22px; }
.modal-footer { padding: 14px 22px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }

/* ── Forms ── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
.form-full { grid-column: 1 / -1; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: .78rem; font-weight: 600; color: var(--muted); }
.form-group input, .form-group select, .form-group textarea {
    padding: 8px 11px;
    background: var(--body-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-family: var(--font);
    font-size: .85rem;
    transition: border-color var(--transition), box-shadow var(--transition);
    outline: none;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px var(--brand-mid);
}
.form-group textarea { resize: vertical; min-height: 70px; }
.form-hint { font-size: .72rem; color: var(--muted); }
.section-sep { grid-column: 1 / -1; border: none; border-top: 1px solid var(--border); margin: 6px 0 2px; }
.section-label { grid-column: 1 / -1; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--brand); padding-top: 4px; }

/* ── Document Checklist ── */
.doc-list { display: flex; flex-direction: column; gap: 6px; max-height: 240px; overflow-y: auto; padding: 4px 0; }
.doc-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--body-bg); }
.doc-item input[type=checkbox] { width: 15px; height: 15px; accent-color: var(--brand); flex-shrink: 0; }
.doc-item-label { flex: 1; font-size: .82rem; color: var(--text); }
.doc-badge { font-size: .65rem; background: var(--brand-light); color: var(--brand); padding: 1px 6px; border-radius: 99px; font-weight: 600; }
.doc-item input[type=file] { font-size: .75rem; color: var(--muted); flex: 0 0 auto; }

/* ── Loading Spinner ── */
.spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--brand); border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.loading-row td { text-align: center; padding: 32px; }

/* ── Amortization table compact ── */
.sched-table td, .sched-table th { padding: 8px 12px; }
.sched-table td { font-size: .8rem; }

/* ── Reports grid ── */
.reports-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
.kpi-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; box-shadow: var(--shadow); }
.kpi-label { font-size: .72rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
.kpi-val { font-size: 1.5rem; font-weight: 700; color: var(--text); }

/* ── Read-only detail views ── */
.detail-sections { display: flex; flex-direction: column; gap: 18px; }
.detail-section {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--body-bg);
    padding: 16px 18px;
}
.detail-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
}
.detail-section-header .ms { color: var(--brand); font-size: 18px; }
.detail-section-title {
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--brand);
}
.detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 16px; }
.detail-item { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.detail-item-full { grid-column: 1 / -1; }
.detail-label { font-size: .72rem; color: var(--muted); }
.detail-value { font-size: .85rem; font-weight: 500; color: var(--text); line-height: 1.45; word-break: break-word; }
.detail-value.is-empty { color: var(--muted); font-style: italic; font-weight: 400; }
.detail-table { overflow-x: auto; }

/* ── Two-col layout for reports breakdown ── */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main-wrap { margin-left: 0; }
    .form-grid, .form-grid-3, .two-col, .detail-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-mark">
            <?php if ($logo_path): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo">
            <?php else: ?>
                <span class="material-symbols-rounded ms">account_balance</span>
            <?php endif; ?>
        </div>
        <div class="logo-text">
            <h2><?php echo htmlspecialchars($_SESSION['tenant_name']); ?></h2>
            <p>Employee Portal</p>
        </div>
    </div>

    <nav style="flex:1; padding: 8px 10px;">
        <div class="nav-section">
            <div class="nav-label">Workspace</div>
            <a class="nav-item active" data-target="home" data-title="Home" data-subtitle="Dashboard" href="#home">
                <span class="material-symbols-rounded ms">home</span> Home
            </a>

            <?php if (has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')): ?>
            <a class="nav-item" data-target="clients" data-title="Client Management" data-subtitle="Profiles" href="#clients">
                <span class="material-symbols-rounded ms">group</span> Client Management
            </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')): ?>
            <a class="nav-item" data-target="applications" data-title="Applications" data-subtitle="Monitoring" href="#applications">
                <span class="material-symbols-rounded ms">description</span>
                Applications
                <span class="nav-badge" id="navPendingAppsBadge" style="<?php echo count($pending_applications) > 0 ? '' : 'display:none;'; ?>"><?php echo count($pending_applications); ?></span>
            </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_LOANS') || has_permission('CREATE_LOANS')): ?>
            <a class="nav-item" data-target="loans" data-title="Loans Management" data-subtitle="Servicing" href="#loans">
                <span class="material-symbols-rounded ms">real_estate_agent</span> Loans Management
            </a>
            <?php endif; ?>

            <?php if (has_permission('PROCESS_PAYMENTS')): ?>
            <a class="nav-item" data-target="payments" data-title="Receipts & Transactions" data-subtitle="Collections" href="#payments">
                <span class="material-symbols-rounded ms">receipt_long</span> Receipts & Transactions
            </a>
            <?php endif; ?>
            <?php if (has_permission('VIEW_USERS')): ?>
            <a class="nav-item" data-target="users" data-title="Team Directory" data-subtitle="Staff" href="#users">
                <span class="material-symbols-rounded ms">badge</span> Team Directory
            </a>
            <?php endif; ?>
        </div>

        <div class="nav-section" style="margin-top:8px;">
            <div class="nav-label">Insights</div>
            <?php if (has_permission('VIEW_REPORTS')): ?>
            <a class="nav-item" data-target="reports" data-title="Reports & Analytics" data-subtitle="Insights" href="#reports">
                <span class="material-symbols-rounded ms">bar_chart</span> Reports & Analytics
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a class="nav-item" href="../../tenant_login/logout.php" style="color:#f87171;">
            <span class="material-symbols-rounded ms" style="color:#f87171;">logout</span> Sign Out
        </a>
    </div>
</aside>

<!-- ════════════════════════════════════════════
     MAIN
═══════════════════════════════════════════════ -->
<div class="main-wrap">
    <header class="topbar">
        <div class="topbar-title" id="pageTitle">Home <span>Dashboard</span></div>

        <button class="icon-btn" id="themeToggle" title="Toggle dark mode">
            <span class="material-symbols-rounded ms"><?php echo $ui_theme === 'dark' ? 'light_mode' : 'dark_mode'; ?></span>
        </button>

        <?php if (has_permission('CREATE_CLIENTS')): ?>
        <button class="btn-primary" onclick="openModal('walkInModal')">
            <span class="material-symbols-rounded ms">person_add</span> Walk-In
        </button>
        <?php endif; ?>

        <div class="user-chip">
            <div class="avatar"><?php echo $initials; ?></div>
            <div>
                <div class="user-chip-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="user-chip-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Employee'); ?></div>
            </div>
        </div>
    </header>

    <div class="content">

    <!-- ── HOME ── -->
    <section id="home" class="view active">
        <div class="welcome-banner">
            <h1>Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'); ?>, <?php echo htmlspecialchars($name_parts[0]); ?>!</h1>
            <p><?php echo date('l, F j, Y'); ?> · <?php echo htmlspecialchars($_SESSION['tenant_name']); ?></p>
            <div class="welcome-banner-meta">
                <span class="welcome-meta-item"><span class="material-symbols-rounded ms">schedule</span> <?php echo date('h:i A'); ?></span>
                <span class="welcome-meta-item"><span class="material-symbols-rounded ms">badge</span> <?php echo htmlspecialchars($_SESSION['role'] ?? 'Employee'); ?></span>
            </div>
        </div>

        <div class="stats-grid" id="homeStats">
            <?php if (has_permission('VIEW_APPLICATIONS')): ?>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-rounded ms">pending_actions</span></div>
                <div class="stat-label">Pending Applications</div>
                <div class="stat-value brand" id="statPendingApps"><?php echo count($pending_applications); ?></div>
                <div class="stat-sub">Needs your review</div>
            </div>
            <?php endif; ?>
            <?php if (has_permission('VIEW_LOANS')): ?>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-rounded ms">account_balance_wallet</span></div>
                <div class="stat-label">Active Loans</div>
                <div class="stat-value" id="statActiveLoans">—</div>
                <div class="stat-sub">Currently disbursed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-rounded ms">warning</span></div>
                <div class="stat-label">Overdue Loans</div>
                <div class="stat-value" style="color:#ef4444;" id="statOverdueLoans">—</div>
                <div class="stat-sub">Needs follow-up</div>
            </div>
            <?php endif; ?>
            <?php if (has_permission('PROCESS_PAYMENTS')): ?>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-rounded ms">payments</span></div>
                <div class="stat-label">Today's Collections</div>
                <div class="stat-value brand" id="statTodayCollections">—</div>
                <div class="stat-sub">Posted payments today</div>
            </div>
            <?php endif; ?>
            <?php if (has_permission('VIEW_CLIENTS')): ?>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-rounded ms">verified_user</span></div>
                <div class="stat-label">Active Clients</div>
                <div class="stat-value" id="statActiveClients">0</div>
                <div class="stat-sub">Ready for servicing</div>
            </div>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
            <?php if (has_permission('VIEW_APPLICATIONS')): ?>
            <div class="card">
                <div class="card-header">
                    <span class="material-symbols-rounded ms">list_alt</span>
                    <h3>Recent Applications</h3>
                    <a class="btn btn-sm btn-outline" data-target="applications" href="#applications" style="text-decoration:none;">View All</a>
                </div>
                <div>
                    <?php if (empty($pending_applications)): ?>
                        <div style="padding:24px;text-align:center;color:var(--muted);font-size:.85rem;">No pending applications.</div>
                    <?php else: ?>
                        <?php foreach (array_slice($pending_applications, 0, 5) as $app): ?>
                        <div class="activity-item">
                            <div class="activity-dot"></div>
                            <div>
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                                    — <?php echo htmlspecialchars($app['product_name']); ?>
                                    <strong style="color:var(--brand);">₱<?php echo number_format($app['requested_amount'], 0); ?></strong>
                                </div>
                                <div class="activity-time"><?php echo htmlspecialchars($app['application_status']); ?> · <?php echo date('M j', strtotime($app['submitted_date'] ?? $app['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <span class="material-symbols-rounded ms">notifications</span>
                    <h3>Quick Actions</h3>
                </div>
                <div style="padding:16px;display:flex;flex-direction:column;gap:8px;">
                    <?php if (has_permission('CREATE_CLIENTS')): ?>
                    <button class="btn btn-outline" onclick="openModal('walkInModal')" style="justify-content:flex-start;width:100%;">
                        <span class="material-symbols-rounded ms">person_add</span> Register Walk-In Client
                    </button>
                    <?php endif; ?>
                    <?php if (has_permission('PROCESS_PAYMENTS')): ?>
                    <button class="btn btn-outline" onclick="navTo('payments');loadPayments();" style="justify-content:flex-start;width:100%;">
                        <span class="material-symbols-rounded ms">receipt_long</span> View Receipts & Transactions
                    </button>
                    <?php endif; ?>
                    <?php if (has_permission('VIEW_LOANS')): ?>
                    <button class="btn btn-outline" onclick="navTo('loans')" style="justify-content:flex-start;width:100%;">
                        <span class="material-symbols-rounded ms">real_estate_agent</span> View All Loans
                    </button>
                    <?php endif; ?>
                    <?php if (has_permission('VIEW_REPORTS')): ?>
                    <button class="btn btn-outline" onclick="navTo('reports');loadReports('month');" style="justify-content:flex-start;width:100%;">
                        <span class="material-symbols-rounded ms">bar_chart</span> Monthly Report
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ── CLIENTS ── -->
    <?php if (has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')): ?>
    <section id="clients" class="view">
        <div class="page-header">
            <div class="page-icon" style="background:rgba(16,185,129,.1);color:#10b981;">
                <span class="material-symbols-rounded ms" style="font-size:22px;">group</span>
            </div>
            <div>
                <h1>Client Management</h1>
                <p>View, search, and manage all registered borrowers.</p>
            </div>
            <div class="page-header-actions">
                <?php if (has_permission('CREATE_CLIENTS')): ?>
                <button class="btn-primary" onclick="openModal('walkInModal')">
                    <span class="material-symbols-rounded ms">person_add</span> New Client
                </button>
                <?php endif; ?>
            </div>
        </div>
        <!-- ══ TEMPORARY DEBUG PANEL — remove after fixing ══ -->
        <?php if (false): ?>
        <div style="background:#1e293b;color:#7dd3fc;font-family:monospace;font-size:.8rem;padding:14px 18px;border-radius:8px;margin-bottom:16px;border:1px solid #334155;">
            <strong style="color:#f8fafc;">🔍 Client Fetch Debug</strong><br><br>
            Tenant ID in session: <strong style="color:#fbbf24;"><?php echo htmlspecialchars((string)$_client_debug['tenant_id']); ?></strong><br>
            Has VIEW_CLIENTS permission: <strong style="color:<?php echo $_client_debug['has_perm'] ? '#4ade80' : '#f87171'; ?>"><?php echo $_client_debug['has_perm'] ? 'YES' : 'NO — this is why nothing shows'; ?></strong><br>
            Raw COUNT(*) from clients WHERE tenant_id matches: <strong style="color:#fbbf24;"><?php echo var_export($_client_debug['raw_count_check'], true); ?></strong><br>
            Rows returned by full JOIN query: <strong style="color:#fbbf24;"><?php echo $_client_debug['row_count']; ?></strong><br>
            Query error: <strong style="color:#f87171;"><?php echo htmlspecialchars((string)($_client_debug['query_error'] ?? 'none')); ?></strong><br><br>
            <?php
            // Also show what tenant_ids exist in clients table
            try {
                $tid_check = $pdo->query("SELECT DISTINCT tenant_id, COUNT(*) as cnt FROM clients GROUP BY tenant_id");
                $tids = $tid_check->fetchAll(PDO::FETCH_ASSOC);
                echo 'All tenant_ids in clients table: ';
                foreach ($tids as $t) {
                    echo '<strong style="color:#a78bfa">' . htmlspecialchars($t['tenant_id']) . '</strong> (' . $t['cnt'] . ' rows)  ';
                }
            } catch (\Throwable $e) {
                echo 'Could not check tenant_ids: ' . htmlspecialchars($e->getMessage());
            }
            ?>
        </div>
        <!-- ══ END DEBUG PANEL ══ -->

        <?php endif; ?>
        <div class="search-bar">
            <div class="search-input-wrap">
                <span class="material-symbols-rounded ms">search</span>
                <input type="text" id="clientSearch" placeholder="Search by name, email, phone…" oninput="debounce(() => loadClients(this.value), 350)()">
            </div>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Client Name</th><th>Email</th><th>Phone</th>
                        <th>Registered</th><th>Source</th><th>Status</th><th>Action</th>
                    </tr></thead>
                    <tbody id="clientsTbody">
                        <?php if (empty($all_clients)): ?>
                        <tr class="empty-row"><td colspan="7">No clients registered yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($all_clients as $c): ?>
                        <tr>
                            <td class="td-bold"><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></td>
                            <td class="td-muted"><?php echo htmlspecialchars(!empty($c['email_address']) ? $c['email_address'] : '—'); ?></td>
                            <td class="td-muted"><?php echo htmlspecialchars(!empty($c['contact_number']) ? $c['contact_number'] : '—'); ?></td>
                            <td class="td-muted"><?php echo date('M d, Y', strtotime($c['registration_date'])); ?></td>
                            <td>
                                <?php if (($c['user_type'] ?? '') === 'Client'): ?>
                                    <span class="badge badge-blue" title="Registered via mobile app">📱 App</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">🏢 Walk-in</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo statusBadgePHP($c['client_status']); ?></td>
                            <td><button class="btn btn-sm btn-outline" onclick="viewClient(<?php echo (int)$c['client_id']; ?>)">View Profile</button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── APPLICATIONS ── -->
    <?php if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')): ?>
    <section id="applications" class="view">
        <div class="page-header">
            <div class="page-icon" style="background:rgba(59,130,246,.1);color:#3b82f6;">
                <span class="material-symbols-rounded ms" style="font-size:22px;">description</span>
            </div>
            <div>
                <h1>Applications</h1>
                <p>Monitor application progress with clearer borrower-facing status groups.</p>
            </div>
            <div class="page-header-actions">
                <button class="btn btn-outline" onclick="loadApps(document.querySelector('#appFilterTabs .filter-tab.active')?.dataset?.status||'all')">
                    <span class="material-symbols-rounded ms">refresh</span> Refresh
                </button>
            </div>
        </div>
        <div class="filter-tabs" id="appFilterTabs">
            <button class="filter-tab active" data-status="all" onclick="loadApps('all',this)">All</button>
            <button class="filter-tab" data-status="Draft" onclick="loadApps('Draft',this)">Draft</button>
            <button class="filter-tab" data-status="Under Review" onclick="loadApps('Under Review',this)">Under Review</button>
            <button class="filter-tab" data-status="Reviewed" onclick="loadApps('Reviewed',this)">Reviewed</button>
            <button class="filter-tab" data-status="Approved" onclick="loadApps('Approved',this)">Approved</button>
            <button class="filter-tab" data-status="Rejected" onclick="loadApps('Rejected',this)">Rejected</button>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>App #</th><th>Client</th><th>Product</th>
                        <th>Amount</th><th>Date</th><th>Status</th><th>Action</th>
                    </tr></thead>
                    <tbody id="appsTbody"><tr class="loading-row"><td colspan="7"><span class="spinner"></span></td></tr></tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── LOANS ── -->
    <?php if (has_permission('VIEW_LOANS') || has_permission('CREATE_LOANS')): ?>
    <section id="loans" class="view">
        <div class="page-header">
            <div class="page-icon" style="background:rgba(79,70,229,.1);color:#4f46e5;">
                <span class="material-symbols-rounded ms" style="font-size:22px;">real_estate_agent</span>
            </div>
            <div><h1>Loans Management</h1><p>Active disbursed loans, balances, and payment schedules.</p></div>
        </div>
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="loadLoans('all',this)">All</button>
            <button class="filter-tab" onclick="loadLoans('Active',this)">Active</button>
            <button class="filter-tab" onclick="loadLoans('Overdue',this)">Overdue</button>
            <button class="filter-tab" onclick="loadLoans('Fully Paid',this)">Fully Paid</button>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Loan #</th><th>Client</th><th>Product</th>
                        <th>Principal</th><th>Balance</th><th>Next Due</th><th>Status</th><th>Action</th>
                    </tr></thead>
                    <tbody id="loansTbody"><tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr></tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── PAYMENTS ── -->
    <?php if (has_permission('PROCESS_PAYMENTS')): ?>
    <section id="payments" class="view">
        <div class="page-header">
            <div class="page-icon" style="background:rgba(16,185,129,.1);color:#10b981;">
                <span class="material-symbols-rounded ms" style="font-size:22px;">receipt_long</span>
            </div>
            <div>
                <h1>Receipts & Transactions</h1>
                <p>Today's collections: <strong id="todayTotal" style="color:var(--brand);">₱0.00</strong></p>
            </div>
        </div>
        <div class="stats-grid" style="margin-bottom:16px;">
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-rounded ms">payments</span></div>
                <div class="stat-label">Today's Collections</div>
                <div class="stat-value brand" id="receiptTodayTotal">â€”</div>
                <div class="stat-sub">Sum of posted receipts today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-rounded ms">receipt</span></div>
                <div class="stat-label">Today's Transactions</div>
                <div class="stat-value" id="receiptTodayCount">0</div>
                <div class="stat-sub">Transactions posted today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-rounded ms">history</span></div>
                <div class="stat-label">Latest Posting</div>
                <div class="stat-value" id="receiptLatestPosted">â€”</div>
                <div class="stat-sub">Most recent transaction date</div>
            </div>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Receipt #</th><th>Transaction Ref</th><th>Client</th><th>Loan #</th>
                        <th>Amount</th><th>Method</th><th>Date</th><th>Status</th>
                    </tr></thead>
                    <tbody id="paymentsTbody"><tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr></tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (has_permission('VIEW_USERS')): ?>
    <section id="users" class="view">
        <div class="page-header">
            <div class="page-icon" style="background:rgba(245,158,11,.12);color:#f59e0b;">
                <span class="material-symbols-rounded ms" style="font-size:22px;">badge</span>
            </div>
            <div>
                <h1>Team Directory</h1>
                <p>View staff accounts assigned to this tenant. Account creation and edits stay in the Admin panel.</p>
            </div>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Staff Member</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th>
                    </tr></thead>
                    <tbody id="usersTbody"><tr class="loading-row"><td colspan="5"><span class="spinner"></span></td></tr></tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── REPORTS ── -->
    <?php if (has_permission('VIEW_REPORTS')): ?>
    <section id="reports" class="view">
        <div class="page-header">
            <div class="page-icon" style="background:rgba(168,85,247,.1);color:#a855f7;">
                <span class="material-symbols-rounded ms" style="font-size:22px;">analytics</span>
            </div>
            <div><h1>Reports & Analytics</h1><p>Financial performance and portfolio overview.</p></div>
            <div class="page-header-actions">
                <button class="filter-tab active" onclick="loadReports('week');setActiveTab(this)">Week</button>
                <button class="filter-tab" onclick="loadReports('month');setActiveTab(this)">Month</button>
                <button class="filter-tab" onclick="loadReports('year');setActiveTab(this)">Year</button>
            </div>
        </div>
        <div id="reportsBody"><div style="text-align:center;padding:40px;color:var(--muted);"><span class="spinner"></span></div></div>
    </section>
    <?php endif; ?>

    <!-- ── USERS ── -->
    </div><!-- /content -->
</div><!-- /main-wrap -->


<!-- ════════════════════════════════════════════
     MODALS
═══════════════════════════════════════════════ -->

<!-- Application Review Modal -->
<div class="modal-backdrop top" id="appReviewModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="material-symbols-rounded ms" style="color:var(--brand);">description</span>
            <h3 id="appModalTitle">Application Review</h3>
            <button class="icon-btn" onclick="closeModal('appReviewModal')"><span class="material-symbols-rounded ms">close</span></button>
        </div>
        <div class="modal-body" id="appModalBody"><div style="text-align:center;padding:32px;"><span class="spinner"></span></div></div>
        <div class="modal-footer" id="appModalFooter">
            <button class="btn btn-outline" onclick="closeModal('appReviewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Loan Release Modal -->
<div class="modal-backdrop" id="loanReleaseModal">
    <div class="modal modal-md">
        <div class="modal-header">
            <span class="material-symbols-rounded ms" style="color:var(--brand);">rocket_launch</span>
            <h3>Release Loan</h3>
            <button class="icon-btn" onclick="closeModal('loanReleaseModal')"><span class="material-symbols-rounded ms">close</span></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="releaseAppId">
            <div class="form-grid">
                <div class="form-group">
                    <label>Approved Amount (PHP)</label>
                    <input type="number" id="releaseAmount" step="0.01" min="1">
                </div>
                <div class="form-group">
                    <label>Release Date</label>
                    <input type="date" id="releaseDate">
                </div>
                <div class="form-group">
                    <label>Disbursement Method</label>
                    <select id="releaseMethod"><option>Cash</option><option>Check</option><option>Bank Transfer</option><option>GCash</option></select>
                </div>
                <div class="form-group">
                    <label>Payment Frequency</label>
                    <select id="releaseFreq"><option value="Monthly">Monthly</option><option value="Weekly">Weekly</option><option value="Bi-Weekly">Bi-Weekly</option></select>
                </div>
                <div class="form-group form-full">
                    <label>Reference / Check # (optional)</label>
                    <input type="text" id="releaseRef">
                </div>
                <div class="form-group form-full">
                    <label>Notes</label>
                    <textarea id="releaseNotes"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('loanReleaseModal')">Cancel</button>
            <button class="btn btn-brand" onclick="submitLoanRelease()">
                <span class="material-symbols-rounded ms">rocket_launch</span> Release Loan
            </button>
        </div>
    </div>
</div>

<!-- Loan Detail Modal -->
<div class="modal-backdrop top" id="loanDetailModal">
    <div class="modal modal-xl">
        <div class="modal-header">
            <span class="material-symbols-rounded ms" style="color:var(--brand);">real_estate_agent</span>
            <h3 id="loanDetailTitle">Loan Details</h3>
            <button class="icon-btn" onclick="closeModal('loanDetailModal')"><span class="material-symbols-rounded ms">close</span></button>
        </div>
        <div class="modal-body" id="loanDetailBody"><div style="text-align:center;padding:32px;"><span class="spinner"></span></div></div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('loanDetailModal')">Close</button>
        </div>
    </div>
</div>

<!-- Post Payment Modal -->
<div class="modal-backdrop" id="paymentModal">
    <div class="modal modal-md">
        <div class="modal-header">
            <span class="material-symbols-rounded ms" style="color:#10b981;">add_card</span>
            <h3>Post Payment</h3>
            <button class="icon-btn" onclick="closeModal('paymentModal')"><span class="material-symbols-rounded ms">close</span></button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label>Select Loan</label>
                    <select id="payLoanId" onchange="onPayLoanChange()"><option value="">— Loading loans… —</option></select>
                    <p class="form-hint" id="payLoanInfo"></p>
                </div>
                <div class="form-group">
                    <label>Payment Amount (PHP)</label>
                    <input type="number" id="payAmount" step="0.01" min="1">
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select id="payMethod"><option>Cash</option><option>GCash</option><option>Bank Transfer</option><option>Check</option></select>
                </div>
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" id="payDate">
                </div>
                <div class="form-group">
                    <label>OR / Receipt #</label>
                    <input type="text" id="payOR">
                </div>
                <div class="form-group">
                    <label>Reference # (GCash/Bank)</label>
                    <input type="text" id="payRef">
                </div>
                <div class="form-group form-full">
                    <label>Remarks</label>
                    <input type="text" id="payRemarks">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('paymentModal')">Cancel</button>
            <button class="btn btn-brand" onclick="submitPayment()">
                <span class="material-symbols-rounded ms">check</span> Post Payment
            </button>
        </div>
    </div>
</div>

<!-- Client Detail Modal -->
<div class="modal-backdrop top" id="clientDetailModal">
    <div class="modal modal-xl">
        <div class="modal-header">
            <span class="material-symbols-rounded ms" style="color:#10b981;">person</span>
            <h3 id="clientDetailTitle">Client Profile</h3>
            <button class="icon-btn" onclick="closeModal('clientDetailModal')"><span class="material-symbols-rounded ms">close</span></button>
        </div>
        <div class="modal-body" id="clientDetailBody"><div style="text-align:center;padding:32px;"><span class="spinner"></span></div></div>
        <div class="modal-footer" id="clientDetailFooter">
            <button class="btn btn-outline" onclick="closeModal('clientDetailModal')">Close</button>
        </div>
    </div>
</div>

<!-- Walk-In / Register Client Modal -->
<div class="modal-backdrop top" id="walkInModal">
    <div class="modal modal-xl">
        <div class="modal-header">
            <span class="material-symbols-rounded ms" style="color:var(--brand);">person_add</span>
            <h3>Register Walk-In Client</h3>
            <button class="icon-btn" onclick="closeModal('walkInModal')"><span class="material-symbols-rounded ms">close</span></button>
        </div>
        <div class="modal-body">
            <form id="walkInForm" enctype="multipart/form-data">
                <input type="hidden" name="walk_in_action" id="walkInAction" value="draft">
                <div class="form-grid">
                    <div class="section-label">Personal Information</div>
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" required></div>
                    <div class="form-group"><label>Email Address *</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Phone Number</label><input type="tel" name="phone_number"></div>
                    <div class="form-group"><label>Date of Birth *</label><input type="date" name="date_of_birth" required></div>
                    <div class="form-group"><label>Physical Address</label><input type="text" name="address"></div>
                    <div class="form-group"><label>Password (App Login) *</label><input type="password" name="password" minlength="8" required><p class="form-hint">Minimum 8 characters.</p></div>
                    <div class="form-group"><label>Confirm Password *</label><input type="password" name="confirm_password" minlength="8" required></div>

                    <hr class="section-sep">
                    <div class="section-label">Loan Request</div>
                    <div class="form-group form-full">
                        <label>Loan Product *</label>
                        <select name="product_id" id="walkInProduct" required>
                            <option value="">Select a product…</option>
                            <?php foreach ($loan_products as $p): ?>
                            <option value="<?php echo (int)$p['product_id']; ?>"
                                data-min="<?php echo $p['min_amount']; ?>"
                                data-max="<?php echo $p['max_amount']; ?>"
                                data-min-term="<?php echo $p['min_term_months']; ?>"
                                data-max-term="<?php echo $p['max_term_months']; ?>">
                                <?php echo htmlspecialchars($p['product_name'].' ('.$p['product_type'].')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Requested Amount (PHP) *</label><input type="number" name="requested_amount" min="0.01" step="0.01" required></div>
                    <div class="form-group"><label>Loan Term (Months) *</label><input type="number" name="loan_term_months" min="1" step="1" required></div>
                    <div class="form-group"><label>Monthly Income (PHP) *</label><input type="number" name="monthly_income" min="0.01" step="0.01" required></div>
                    <div class="form-group form-full"><label>Loan Purpose</label><textarea name="loan_purpose" placeholder="Purpose of the loan request…"></textarea></div>

                    <hr class="section-sep">
                    <div class="section-label">Document Submission</div>
                    <div class="form-group form-full">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="documents_complete" id="documentsComplete" value="1" style="width:auto;accent-color:var(--brand);">
                            All required documents have been submitted
                        </label>
                    </div>
                    <div class="form-group form-full">
                        <label>Collected Documents</label>
                        <div class="doc-list">
                            <?php foreach ($document_types as $doc): ?>
                            <div class="doc-item">
                                <input type="checkbox" class="doc-collected-checkbox"
                                    data-doc-id="<?php echo (int)$doc['document_type_id']; ?>"
                                    name="submitted_document_type_ids[]"
                                    value="<?php echo (int)$doc['document_type_id']; ?>">
                                <span class="doc-item-label">
                                    <?php echo htmlspecialchars($doc['document_name']); ?>
                                    <?php if ($doc['is_required']): ?><span class="doc-badge">Required</span><?php endif; ?>
                                </span>
                                <input type="file" class="document-upload-input"
                                    data-doc-id="<?php echo (int)$doc['document_type_id']; ?>"
                                    name="uploaded_documents[<?php echo (int)$doc['document_type_id']; ?>]"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="form-hint" style="margin-top:8px;">Upload each collected document. Missing items can be followed-up later via Draft.</p>
                    </div>
                    <div class="form-group form-full">
                        <label>Missing / Follow-up Notes</label>
                        <textarea name="missing_documents_notes" placeholder="List pending documents or remarks…"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('walkInModal')">Cancel</button>
            <button class="btn" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;" onclick="submitWalkIn('draft')">
                <span class="material-symbols-rounded ms">save</span> Save as Draft
            </button>
            <button class="btn btn-brand" onclick="submitWalkIn('submit')">
                <span class="material-symbols-rounded ms">send</span> Create & Submit
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════ -->
<script>
const API = {
    applications: '../../backend/api_applications.php',
    loans:        '../../backend/api_loans.php',
    payments:     '../../backend/api_payments.php',
    clients:      '../../backend/api_clients.php',
    dashboard:    '../../backend/api_dashboard.php',
    walk_in:      '../../backend/api_walk_in.php',
    theme:        '../../backend/api_theme_preference.php',
};

let activeLoanId = null;
let _debounceTimer = null;

// ── Utilities ──────────────────────────────────────────────
function debounce(fn, ms) { return () => { clearTimeout(_debounceTimer); _debounceTimer = setTimeout(fn, ms); }; }
function fmt(n) { return '₱' + parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function fmtDate(d) { if (!d) return '—'; const dt = new Date(d); return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'2-digit'}); }

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[char]);
}
function isBlank(value) { return value === null || value === undefined || String(value).trim() === ''; }
function formatTextValue(value, emptyLabel='Not provided') {
    if (isBlank(value)) return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
    return escapeHtml(value);
}
function formatMoneyValue(value, emptyLabel='Not provided') {
    const amount = parseFloat(value);
    if (!Number.isFinite(amount) || amount <= 0) return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
    return fmt(amount);
}
function formatDateValue(value, emptyLabel='Not provided') {
    if (isBlank(value) || value === '1990-01-01') return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
    return escapeHtml(fmtDate(value));
}
function renderDetailItem(label, valueHtml, full=false) {
    return `<div class="detail-item${full ? ' detail-item-full' : ''}"><div class="detail-label">${escapeHtml(label)}</div><div class="detail-value">${valueHtml}</div></div>`;
}
function joinAddress(parts) {
    return parts.map(part => String(part ?? '').trim()).filter(Boolean).join(', ');
}
function sourceBadge(userType) {
    return userType === 'Client'
        ? '<span class="badge badge-blue">Mobile App</span>'
        : '<span class="badge badge-gray">Walk-in / Staff</span>';
}
function applicationMonitorState(status='') {
    const map = {
        'Draft': 'Draft',
        'Submitted': 'Under Review',
        'Pending Review': 'Under Review',
        'Under Review': 'Under Review',
        'Document Verification': 'Under Review',
        'Credit Investigation': 'Under Review',
        'For Approval': 'Reviewed',
        'Reviewed': 'Reviewed',
        'Approved': 'Approved',
        'Rejected': 'Rejected',
        'Cancelled': 'Rejected',
        'Withdrawn': 'Rejected'
    };
    return map[status] || status || 'Under Review';
}
function applicationMonitorBadge(status='') {
    const monitor = applicationMonitorState(status);
    const stageNote = monitor !== status && !isBlank(status)
        ? `<div class="status-note">Current stage: ${escapeHtml(status)}</div>`
        : '';
    return `<div class="status-stack">${badge(monitor)}${stageNote}</div>`;
}
function matchesApplicationFilter(rawStatus, filter='all') {
    if (!filter || filter === 'all') return true;
    return applicationMonitorState(rawStatus) === filter;
}
function getActiveAppFilter() {
    return document.querySelector('#appFilterTabs .filter-tab.active')?.dataset?.status || 'all';
}

function badge(s) {
    const map = {
        'Active':                 'badge-green',
        'Approved':               'badge-green',
        'Posted':                 'badge-green',
        'Verified':               'badge-green',
        'Fully Paid':             'badge-blue',
        'Under Review':           'badge-blue',
        'For Approval':           'badge-purple',
        'Credit Investigation':   'badge-purple',
        'Document Verification':  'badge-purple',
        'Overdue':                'badge-red',
        'Rejected':               'badge-red',
        'Bounced':                'badge-red',
        'Blacklisted':            'badge-red',
        'Cancelled':              'badge-gray',
        'Withdrawn':              'badge-gray',
        'Inactive':               'badge-gray',
        'Suspended':              'badge-gray',
        'Draft':                  'badge-amber',
        'Submitted':              'badge-amber',
        'Pending Review':         'badge-amber',
        'Pending':                'badge-amber',
        'Partially Paid':         'badge-amber',
    };
    const cls = map[s] || 'badge-gray';
    return `<span class="badge ${cls}">${s}</span>`;
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function setActiveTab(el) {
    el.closest('.page-header-actions').querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}

function navTo(target) {
    document.querySelectorAll('.nav-item[data-target]').forEach(n => {
        if (n.dataset.target === target) n.click();
    });
}

// ── Navigation ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('.nav-item[data-target]');
    const views    = document.querySelectorAll('.view');
    const title    = document.getElementById('pageTitle');

    navItems.forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');
            views.forEach(v => v.classList.remove('active'));
            const tid = item.dataset.target;
            const tv  = document.getElementById(tid);
            if (tv) tv.classList.add('active');
            const titleText = item.dataset.title || item.textContent.trim();
            const subtitleText = item.dataset.subtitle || tid.charAt(0).toUpperCase() + tid.slice(1);
            title.innerHTML = `${escapeHtml(titleText)} <span>${escapeHtml(subtitleText)}</span>`;
            history.pushState(null,'',`#${tid}`);
            // Lazy load on first visit
            if (tid === 'applications') loadApps('all');
            if (tid === 'loans')    loadLoans('all');
            if (tid === 'payments') loadPayments();
            if (tid === 'users')    loadUsers();
            if (tid === 'reports')  loadReports('month');
        });
    });

    // Handle hash
    const hash = location.hash.replace('#','');
    if (hash) { const n = document.querySelector(`.nav-item[data-target="${hash}"]`); if (n) n.click(); }

    // Theme toggle
    const themeBtn = document.getElementById('themeToggle');
    const html = document.documentElement;
    themeBtn.addEventListener('click', () => {
        const nt = html.dataset.theme === 'dark' ? 'light' : 'dark';
        html.dataset.theme = nt;
        themeBtn.querySelector('.ms').textContent = nt === 'dark' ? 'light_mode' : 'dark_mode';
        fetch(API.theme, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({theme:nt})}).catch(()=>{});
    });

    // Date defaults
    const today = new Date().toISOString().slice(0,10);
    document.getElementById('payDate') && (document.getElementById('payDate').value = today);
    document.getElementById('releaseDate') && (document.getElementById('releaseDate').value = today);
    const legacyPaymentsHint = document.getElementById('todayTotal');
    if (legacyPaymentsHint && legacyPaymentsHint.parentElement) {
        legacyPaymentsHint.parentElement.textContent = 'Review posted receipts, transaction references, and collection activity.';
    }

    // Doc upload sync
    document.querySelectorAll('.document-upload-input').forEach(inp => {
        inp.addEventListener('change', () => {
            const cb = document.querySelector(`.doc-collected-checkbox[data-doc-id="${inp.dataset.docId}"]`);
            if (cb && inp.files.length > 0) cb.checked = true;
        });
    });
    document.querySelectorAll('.doc-collected-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            if (!cb.checked) {
                const inp = document.querySelector(`.document-upload-input[data-doc-id="${cb.dataset.docId}"]`);
                if (inp) inp.value = '';
            }
        });
    });

    loadDashboardStats();
});

// ── Dashboard Stats ─────────────────────────────────────────
async function loadDashboardStats() {
    try {
        const r = await fetch(API.dashboard + '?action=stats');
        const d = await r.json();
        if (d.status !== 'success') return;
        const s = d.data;
        if (s.pending_applications !== undefined) {
            setText('statPendingApps', s.pending_applications);
            const pendingBadge = document.getElementById('navPendingAppsBadge');
            if (pendingBadge) {
                pendingBadge.textContent = s.pending_applications;
                pendingBadge.style.display = s.pending_applications > 0 ? 'inline-flex' : 'none';
            }
        }
        if (s.active_clients     !== undefined) setText('statActiveClients', s.active_clients);
        if (s.active_loans       !== undefined) setText('statActiveLoans', s.active_loans);
        if (s.overdue_loans      !== undefined) setText('statOverdueLoans', s.overdue_loans);
        if (s.todays_collections !== undefined) {
            setText('statTodayCollections', fmt(s.todays_collections));
            setText('receiptTodayTotal', fmt(s.todays_collections));
        }
    } catch(_) {}
}
function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }

// ── App Filter (live API) ─────────────────────────────────────
async function loadApps(status='all', btn=null) {
    const filterTabs = Array.from(document.querySelectorAll('#appFilterTabs .filter-tab'));
    const activeBtn = btn || filterTabs.find(tab => tab.dataset.status === status);
    if (activeBtn) {
        filterTabs.forEach(tab => tab.classList.remove('active'));
        activeBtn.classList.add('active');
    }
    const tbody = document.getElementById('appsTbody');
    tbody.innerHTML = '<tr class="loading-row"><td colspan="7"><span class="spinner"></span></td></tr>';
    const r = await fetch(API.applications + '?action=list');
    const d = await r.json();
    const rows = (d.data || []).filter(application => matchesApplicationFilter(application.application_status, status));
    if (!rows.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="7">No applications found for this filter.</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(a => `<tr>
        <td class="td-mono td-bold">${escapeHtml(a.application_number)}</td>
        <td class="td-bold">${escapeHtml(a.first_name)} ${escapeHtml(a.last_name)}</td>
        <td class="td-muted">${escapeHtml(a.product_name)}</td>
        <td class="td-bold" style="color:var(--brand);">${fmt(a.requested_amount)}</td>
        <td class="td-muted">${fmtDate(a.submitted_date || a.created_at)}</td>
        <td>${applicationMonitorBadge(a.application_status)}</td>
        <td><button class="icon-btn table-icon-btn" onclick="viewApplication(${a.application_id})" title="Open application" aria-label="Open application"><span class="material-symbols-rounded ms">visibility</span></button></td>
    </tr>`).join('');
}

function filterApps(status, btn) { loadApps(status, btn); }

// ── View Application ────────────────────────────────────────
async function viewApplication(id) {
    openModal('appReviewModal');
    document.getElementById('appModalBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
    document.getElementById('appModalFooter').innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
    const r = await fetch(API.applications + `?action=view&id=${id}`);
    const d = await r.json();
    if (d.status !== 'success') { document.getElementById('appModalBody').innerHTML = `<p style="color:#ef4444;">${d.message}</p>`; return; }
    const a = d.data;
    document.getElementById('appModalTitle').textContent = 'App: ' + a.application_number;

    document.getElementById('appModalBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:18px;">
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Client</p><p style="font-weight:600;">${a.first_name} ${a.last_name}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Status</p>${badge(a.application_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Product</p><p>${a.product_name} (${a.product_type})</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Requested Amount</p><p style="font-weight:700;color:var(--brand);font-size:1.05rem;">${fmt(a.requested_amount)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Term</p><p>${a.loan_term_months} months</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Interest Rate</p><p>${a.interest_rate}% / month</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Contact</p><p>${a.contact_number||'—'}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Submitted</p><p>${fmtDate(a.submitted_date||a.created_at)}</p></div>
            ${a.loan_purpose ? `<div style="grid-column:1/-1;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Loan Purpose</p><p>${a.loan_purpose}</p></div>` : ''}
        </div>
        ${a.review_notes ? `<div style="background:var(--body-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:4px;">Review Notes</p><p style="font-size:.85rem;">${a.review_notes}</p></div>` : ''}
        ${a.rejection_reason ? `<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:#991b1b;margin-bottom:4px;">Rejection Reason</p><p style="font-size:.85rem;color:#7f1d1d;">${a.rejection_reason}</p></div>` : ''}
        ${a.application_status === 'Approved' ? `<div class="form-group" style="margin-bottom:14px;"><label>Approved Amount (PHP)</label><input type="number" id="approvedAmountInput" value="${a.approved_amount||a.requested_amount}" style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;"></div>` : ''}
        <div class="form-group"><label>Notes / Reason</label><textarea id="appActionNotes" placeholder="Add notes or reason for action…" style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;min-height:70px;resize:vertical;outline:none;"></textarea></div>`;

    const footer = document.getElementById('appModalFooter');
    footer.innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
    const s = a.application_status;
    if (s === 'Submitted' || s === 'Pending Review') {
        footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'start_review')">Start Review</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'Under Review') {
        footer.innerHTML += `<button class="btn btn-outline" onclick="appAction(${a.application_id},'verify_docs')"><span class="material-symbols-rounded ms">verified</span> Verify Docs</button>
                             <button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'Document Verification') {
        footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'credit_inv')">Credit Investigation</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'Credit Investigation') {
        footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'for_approval')">For Approval</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'For Approval') {
        footer.innerHTML += `<button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'Draft') {
        footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'submit')">Submit Application</button>`;
    }
}

async function appAction(id, action, needsAmount=false) {
    const notes = (document.getElementById('appActionNotes')||{}).value || '';
    const approved = needsAmount ? parseFloat((document.getElementById('approvedAmountInput')||{}).value||0) : null;
    if (action === 'reject' && !notes.trim()) { alert('Please enter a rejection reason.'); return; }
    const payload = { application_id: id, action, notes };
    if (approved) payload.approved_amount = approved;
    const r = await fetch(API.applications, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const d = await r.json();
    alert(d.message);
    if (d.status === 'success') {
        closeModal('appReviewModal');
        loadApps(getActiveAppFilter());
        loadDashboardStats();
    }
}

// ── Loans ────────────────────────────────────────────────────
// Credit policy modal override
async function viewApplication(id) {
    openModal('appReviewModal');
    document.getElementById('appModalBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
    document.getElementById('appModalFooter').innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
    const r = await fetch(API.applications + `?action=view&id=${id}`);
    const d = await r.json();
    if (d.status !== 'success') {
        document.getElementById('appModalBody').innerHTML = `<p style="color:#ef4444;">${d.message}</p>`;
        return;
    }

    const a = d.data;
    const policy = a.application_data && a.application_data.credit_policy ? a.application_data.credit_policy : null;
    const showApprovedAmountInput = ['Under Review', 'For Approval', 'Pending Review'].includes(a.application_status);
    const safe = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[char]);
    const latestScore = a.latest_credit_score !== null && a.latest_credit_score !== undefined && a.latest_credit_score !== ''
        ? `${safe(a.latest_credit_score)}${a.latest_credit_rating ? ` (${safe(a.latest_credit_rating)})` : ''}`
        : 'Not available';
    const investigationSummary = a.latest_ci_recommendation || a.latest_ci_status || 'Not available';
    const approvedAmountValue = a.approved_amount || (policy && policy.approved_amount) || a.requested_amount || '';
    const policyReasons = policy && Array.isArray(policy.reasons) && policy.reasons.length
        ? `<ul style="margin:8px 0 0 18px;padding:0;">${policy.reasons.map(reason => `<li style="margin-bottom:4px;">${safe(reason)}</li>`).join('')}</ul>`
        : '<p style="font-size:.82rem;color:var(--muted);margin:8px 0 0;">No policy reasons recorded yet.</p>';

    document.getElementById('appModalTitle').textContent = 'App: ' + a.application_number;
    document.getElementById('appModalBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:18px;">
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Client</p><p style="font-weight:600;">${safe(a.first_name)} ${safe(a.last_name)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Monitoring Status</p>${applicationMonitorBadge(a.application_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Current Stage</p><p>${safe(a.application_status)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Product</p><p>${safe(a.product_name)} (${safe(a.product_type)})</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Requested Amount</p><p style="font-weight:700;color:var(--brand);font-size:1.05rem;">${fmt(a.requested_amount)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Term</p><p>${safe(a.loan_term_months)} months</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Interest Rate</p><p>${safe(a.interest_rate)}% / month</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Contact</p><p>${safe(a.contact_number || '-')}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Submitted</p><p>${fmtDate(a.submitted_date || a.created_at)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Credit Limit</p><p>${fmt(a.credit_limit || 0)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Latest Score</p><p>${latestScore}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Investigation Result</p><p>${safe(investigationSummary)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Document Status</p><p>${safe(a.document_verification_status || 'Unverified')}</p></div>
            ${a.loan_purpose ? `<div style="grid-column:1/-1;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Loan Purpose</p><p>${safe(a.loan_purpose)}</p></div>` : ''}
        </div>
        ${policy ? `
            <div style="background:var(--body-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                    <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Policy Decision</p><p style="font-weight:700;text-transform:capitalize;">${safe(policy.decision || 'n/a')}</p></div>
                    <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Computed Limit</p><p style="font-weight:600;">${fmt(policy.computed_credit_limit || 0)}</p></div>
                    <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Suggested Amount</p><p style="font-weight:600;">${fmt(policy.approved_amount || 0)}</p></div>
                    <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Evaluated At</p><p>${fmtDate(policy.evaluated_at)}</p></div>
                </div>
                <p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Policy Reasons</p>
                ${policyReasons}
            </div>
        ` : ''}
        ${a.approval_notes ? `<div style="background:#ecfdf5;border:1px solid #86efac;border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:#166534;margin-bottom:4px;">Approval Notes</p><p style="font-size:.85rem;color:#14532d;">${safe(a.approval_notes)}</p></div>` : ''}
        ${a.review_notes ? `<div style="background:var(--body-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:4px;">Review Notes</p><p style="font-size:.85rem;">${safe(a.review_notes)}</p></div>` : ''}
        ${a.rejection_reason ? `<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:#991b1b;margin-bottom:4px;">Rejection Reason</p><p style="font-size:.85rem;color:#7f1d1d;">${safe(a.rejection_reason)}</p></div>` : ''}
        ${showApprovedAmountInput ? `<div class="form-group" style="margin-bottom:14px;"><label>Approved Amount (PHP)</label><input type="number" id="approvedAmountInput" value="${approvedAmountValue}" min="0" step="0.01" style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;"></div>` : ''}
        <div class="form-group"><label>Notes / Reason</label><textarea id="appActionNotes" placeholder="Add notes or reason for action..." style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;min-height:70px;resize:vertical;outline:none;"></textarea></div>`;

    const footer = document.getElementById('appModalFooter');
    footer.innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
    const s = a.application_status;

    if (s === 'Submitted' || s === 'Pending Review') {
        footer.innerHTML += `<button class="btn btn-outline" onclick="appAction(${a.application_id},'evaluate_policy')">Run Policy</button>
                             <button class="btn btn-brand" onclick="appAction(${a.application_id},'start_review')">Start Review</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'Under Review') {
        footer.innerHTML += `<button class="btn btn-outline" onclick="appAction(${a.application_id},'evaluate_policy')">Run Policy</button>
                             <button class="btn btn-outline" onclick="appAction(${a.application_id},'verify_docs')"><span class="material-symbols-rounded ms">verified</span> Verify Docs</button>
                             <button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'Document Verification') {
        footer.innerHTML += `<button class="btn btn-outline" onclick="appAction(${a.application_id},'evaluate_policy')">Run Policy</button>
                             <button class="btn btn-brand" onclick="appAction(${a.application_id},'credit_inv')">Investigation</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'Credit Investigation') {
        footer.innerHTML += `<button class="btn btn-outline" onclick="appAction(${a.application_id},'evaluate_policy')">Run Policy</button>
                             <button class="btn btn-brand" onclick="appAction(${a.application_id},'for_approval')">Mark Reviewed</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'For Approval') {
        footer.innerHTML += `<button class="btn btn-outline" onclick="appAction(${a.application_id},'evaluate_policy')">Run Policy</button>
                             <button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
    } else if (s === 'Draft') {
        footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'submit')">Submit Application</button>`;
    }
}

async function appAction(id, action, needsAmount=false) {
    const notes = (document.getElementById('appActionNotes') || {}).value || '';
    const approved = needsAmount ? parseFloat((document.getElementById('approvedAmountInput') || {}).value || 0) : null;

    if (action === 'reject' && !notes.trim()) {
        alert('Please enter a rejection reason.');
        return;
    }
    if (needsAmount && !(approved > 0)) {
        alert('Please enter an approved amount.');
        return;
    }

    const payload = { application_id: id, action, notes };
    if (needsAmount) {
        payload.approved_amount = approved;
    }

    const r = await fetch(API.applications, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const d = await r.json();
    alert(d.message);
    if (d.status === 'success') {
        closeModal('appReviewModal');
        loadApps(getActiveAppFilter());
        loadDashboardStats();
    }
}

async function loadLoans(status='all', btn=null) {
    if (btn) {
        btn.closest('.filter-tabs').querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
    }
    const tbody = document.getElementById('loansTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
    const r = await fetch(API.loans + '?action=list&status=' + encodeURIComponent(status));
    const d = await r.json();
    if (!d.data || !d.data.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No loans found.</td></tr>'; return; }
    tbody.innerHTML = d.data.map(l => `
        <tr>
            <td class="td-mono td-bold">${l.loan_number}</td>
            <td>${l.first_name} ${l.last_name}</td>
            <td class="td-muted">${l.product_name}</td>
            <td class="td-bold">${fmt(l.principal_amount)}</td>
            <td class="td-bold" style="color:${parseFloat(l.remaining_balance)>0?'var(--brand)':'#22c55e'};">${fmt(l.remaining_balance)}</td>
            <td class="td-muted" style="color:${l.days_overdue>0?'#ef4444':''};">${fmtDate(l.next_payment_due)}</td>
            <td>${badge(l.loan_status)}</td>
            <td><button class="btn btn-sm btn-outline" onclick="viewLoan(${l.loan_id})">View</button></td>
        </tr>`).join('');
}

async function viewLoan(id) {
    activeLoanId = id;
    openModal('loanDetailModal');
    document.getElementById('loanDetailBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
    const [lr, sr] = await Promise.all([
        fetch(API.loans + `?action=view&loan_id=${id}`),
        fetch(API.loans + `?action=schedule&loan_id=${id}`)
    ]);
    const ld = await lr.json(); const sd = await sr.json();
    if (ld.status !== 'success') { document.getElementById('loanDetailBody').innerHTML = `<p style="color:#ef4444;">${ld.message}</p>`; return; }
    const l = ld.data;
    document.getElementById('loanDetailTitle').textContent = l.loan_number;
    const sched = (sd.data||[]).map(s => `<tr>
        <td style="text-align:center;">#${s.payment_number}</td>
        <td>${fmtDate(s.due_date)}</td>
        <td>${fmt(s.beginning_balance)}</td>
        <td>${fmt(s.principal_amount)}</td>
        <td>${fmt(s.interest_amount)}</td>
        <td class="td-bold">${fmt(s.total_payment)}</td>
        <td>${badge(s.payment_status)}</td>
    </tr>`).join('');

    document.getElementById('loanDetailBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:20px;">
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Client</p><p style="font-weight:600;">${l.first_name} ${l.last_name}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Status</p>${badge(l.loan_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Principal</p><p style="font-weight:700;">${fmt(l.principal_amount)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Remaining Balance</p><p style="font-weight:700;color:var(--brand);">${fmt(l.remaining_balance)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Monthly Amortization</p><p>${fmt(l.monthly_amortization)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Next Due</p><p style="color:${l.days_overdue>0?'#ef4444':''};">${fmtDate(l.next_payment_due)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Release Date</p><p>${fmtDate(l.release_date)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Maturity Date</p><p>${fmtDate(l.maturity_date)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Total Paid</p><p style="color:#22c55e;font-weight:600;">${fmt(l.total_paid)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Interest Rate</p><p>${l.interest_rate}% / month</p></div>
        </div>
        <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">Amortization Schedule</p>
        <div style="overflow-x:auto;"><table class="sched-table">
            <thead><tr style="background:var(--body-bg);">
                <th style="text-align:center;">#</th><th>Due Date</th><th>Beg. Balance</th>
                <th>Principal</th><th>Interest</th><th>Total</th><th>Status</th>
            </tr></thead>
            <tbody>${sched || '<tr class="empty-row"><td colspan="7">No schedule found.</td></tr>'}</tbody>
        </table></div>`;
}

function openLoanRelease(appId, amount) {
    closeModal('appReviewModal');
    document.getElementById('releaseAppId').value = appId;
    document.getElementById('releaseAmount').value = amount;
    openModal('loanReleaseModal');
}

async function submitLoanRelease() {
    const payload = {
        application_id:      parseInt(document.getElementById('releaseAppId').value),
        approved_amount:     parseFloat(document.getElementById('releaseAmount').value),
        disbursement_method: document.getElementById('releaseMethod').value,
        release_date:        document.getElementById('releaseDate').value,
        payment_frequency:   document.getElementById('releaseFreq').value,
        disbursement_reference: document.getElementById('releaseRef').value,
        notes:               document.getElementById('releaseNotes').value,
    };
    if (!payload.approved_amount || !payload.release_date) { alert('Please fill all required fields.'); return; }
    const r = await fetch(API.loans + '?action=release', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const d = await r.json();
    alert(d.message);
    if (d.status === 'success') { closeModal('loanReleaseModal'); location.reload(); }
}

// ── Clients ──────────────────────────────────────────────────
// NOTE: api_clients.php?action=list must also use ORDER BY registration_date DESC
// (not created_at — clients table has no created_at column).
// Also JOIN users ON c.user_id = u.user_id and SELECT u.user_type so the Source badge works.
async function loadClients(search='') {
    const tbody = document.getElementById('clientsTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr class="loading-row"><td colspan="7"><span class="spinner"></span></td></tr>';
    const r = await fetch(API.clients + '?action=list' + (search ? '&search='+encodeURIComponent(search) : ''));
    const d = await r.json();
    if (!d.data || !d.data.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="7">No clients found.</td></tr>'; return; }
    tbody.innerHTML = d.data.map(c => `<tr>
        <td class="td-bold">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</td>
        <td class="td-muted">${c.email_address && c.email_address.trim() ? c.email_address : '—'}</td>
        <td class="td-muted">${c.contact_number && c.contact_number.trim() ? c.contact_number : '—'}</td>
        <td class="td-muted">${fmtDate(c.registration_date)}</td>
        <td>${c.user_type === 'Client' ? '<span class="badge badge-blue">📱 App</span>' : '<span class="badge badge-gray">🏢 Walk-in</span>'}</td>
        <td>${badge(c.client_status)}</td>
        <td><button class="btn btn-sm btn-outline" onclick="viewClient(${c.client_id})">View Profile</button></td>
    </tr>`).join('');
}

async function viewClient(id) {
    openModal('clientDetailModal');
    document.getElementById('clientDetailBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
    const r = await fetch(API.clients + `?action=view&client_id=${id}`);
    const d = await r.json();
    if (d.status !== 'success') { document.getElementById('clientDetailBody').innerHTML = `<p style="color:#ef4444;">${d.message}</p>`; return; }
    const c = d.data;
    document.getElementById('clientDetailTitle').textContent = c.first_name + ' ' + c.last_name;

    const footer = document.getElementById('clientDetailFooter');
    if (footer) {
        let footerHtml = `<button class="btn btn-outline" onclick="closeModal('clientDetailModal')">Close</button>`;
        if (c.verification_status !== 'Approved') {
            footerHtml += `<button class="btn btn-success" onclick="verifyClientFully(${c.client_id})"><span class="material-symbols-rounded ms">verified</span> Verify Client</button>`;
        }
        footer.innerHTML = footerHtml;
    }

    const loansHtml = (c.loans||[]).length ? (c.loans||[]).map(l => `<tr>
        <td class="td-mono">${l.loan_number}</td>
        <td>${l.product_name}</td>
        <td>${fmt(l.principal_amount)}</td>
        <td style="color:var(--brand);font-weight:600;">${fmt(l.remaining_balance)}</td>
        <td>${fmtDate(l.next_payment_due)}</td>
        <td>${badge(l.loan_status)}</td>
    </tr>`).join('') : '<tr class="empty-row"><td colspan="6">No active loans.</td></tr>';

    const docsHtml = (c.documents||[]).length ? (c.documents||[]).map(d => `<tr>
        <td class="td-bold">${d.document_name} ${d.is_required ? '<span class="badge badge-amber" style="font-size:.65rem;padding:2px 6px;">Req</span>' : ''}</td>
        <td>${d.file_path ? `<a href="../../../${d.file_path}" target="_blank" class="btn btn-sm btn-outline"><span class="material-symbols-rounded ms" style="font-size:16px;">visibility</span> View</a>` : '<span class="td-muted">Not uploaded</span>'}</td>
        <td class="td-muted">${fmtDate(d.upload_date)}</td>
        <td>${badge(d.verification_status || 'Pending')}</td>
        <td>
            ${d.file_path && d.verification_status !== 'Verified' ? `<button class="btn btn-sm" style="background:#dcfce7;color:#166534;border:none;" onclick="verifyDoc(${d.client_document_id}, 'Verified', ${c.client_id})">Verify</button> <button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;border:none;" onclick="verifyDoc(${d.client_document_id}, 'Rejected', ${c.client_id})">Reject</button>` : '—'}
        </td>
    </tr>`).join('') : '<tr class="empty-row"><td colspan="5">No documents submitted.</td></tr>';

    // DOB '1990-01-01' is the mobile registration placeholder — show as incomplete
    const dob = c.date_of_birth === '1990-01-01' ? '<span style="color:var(--muted);font-style:italic;">Not provided</span>' : fmtDate(c.date_of_birth);
    const sourceLabel = c.user_type === 'Client'
        ? '<span class="badge badge-blue">📱 Mobile App</span>'
        : '<span class="badge badge-gray">🏢 Walk-in / Staff</span>';
    const phone = c.contact_number && c.contact_number.trim() ? c.contact_number : '<span style="color:var(--muted);font-style:italic;">Not provided</span>';
    const income = c.monthly_income && parseFloat(c.monthly_income) > 0 ? fmt(c.monthly_income) : '<span style="color:var(--muted);font-style:italic;">Not provided</span>';

    document.getElementById('clientDetailBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:20px;">
            <div><p style="font-size:.72rem;color:var(--muted);">Email</p><p style="font-weight:500;">${c.email_address||'—'}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);">Phone</p><p style="font-weight:500;">${phone}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);">Date of Birth</p><p style="font-weight:500;">${dob}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);">Status</p>${badge(c.client_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);">Address</p><p style="font-weight:500;">${[c.present_street,c.present_barangay,c.present_city,c.present_province].filter(Boolean).join(', ')||'<span style="color:var(--muted);font-style:italic;">Not provided</span>'}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);">Monthly Income</p><p style="font-weight:500;">${income}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);">Source</p>${sourceLabel}</div>
        </div>
        
        <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">Submitted Documents</p>
        <div style="overflow-x:auto; margin-bottom: 24px;">
        <table><thead><tr style="background:var(--body-bg);">
            <th>Document Requirement</th><th>File</th><th>Uploaded</th><th>Status</th><th>Action</th>
        </tr></thead><tbody>${docsHtml}</tbody></table></div>

        <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">Loan History</p>
        <div style="overflow-x:auto;">
        <table><thead><tr style="background:var(--body-bg);">
            <th>Loan #</th><th>Product</th><th>Principal</th><th>Balance</th><th>Next Due</th><th>Status</th>
        </tr></thead><tbody>${loansHtml}</tbody></table></div>`;
}

async function loadClients(search='') {
    const tbody = document.getElementById('clientsTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr class="loading-row"><td colspan="7"><span class="spinner"></span></td></tr>';
    const r = await fetch(API.clients + '?action=list' + (search ? '&search=' + encodeURIComponent(search) : ''));
    const d = await r.json();
    if (!d.data || !d.data.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="7">No clients found.</td></tr>';
        return;
    }
    tbody.innerHTML = d.data.map(c => `<tr>
        <td class="td-bold">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</td>
        <td class="td-muted">${c.email_address && c.email_address.trim() ? escapeHtml(c.email_address) : 'â€”'}</td>
        <td class="td-muted">${c.contact_number && c.contact_number.trim() ? escapeHtml(c.contact_number) : 'â€”'}</td>
        <td class="td-muted">${fmtDate(c.registration_date)}</td>
        <td>${sourceBadge(c.user_type)}</td>
        <td>${badge(c.client_status)}</td>
        <td><button class="btn btn-sm btn-outline" onclick="viewClient(${c.client_id})">View Profile</button></td>
    </tr>`).join('');
}

async function viewClient(id) {
    openModal('clientDetailModal');
    document.getElementById('clientDetailBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
    const r = await fetch(API.clients + `?action=view&client_id=${id}`);
    const d = await r.json();
    if (d.status !== 'success') {
        document.getElementById('clientDetailBody').innerHTML = `<p style="color:#ef4444;">${escapeHtml(d.message)}</p>`;
        return;
    }

    const c = d.data;
    const fullName = [c.first_name, c.middle_name, c.last_name, c.suffix].map(part => String(part ?? '').trim()).filter(Boolean).join(' ');
    const presentAddress = joinAddress([c.present_street, c.present_barangay, c.present_city, c.present_province, c.present_postal_code]);
    const permanentAddress = joinAddress([c.permanent_street, c.permanent_barangay, c.permanent_city, c.permanent_province, c.permanent_postal_code]);
    const coMakerAddress = joinAddress([c.comaker_house_no, c.comaker_street, c.comaker_barangay, c.comaker_city, c.comaker_province, c.comaker_postal_code]);
    const verificationBadge = badge(c.verification_status || c.document_verification_status || 'Pending');
    const accountEmail = !isBlank(c.email_address) ? escapeHtml(c.email_address) : formatTextValue(c.user_email);

    document.getElementById('clientDetailTitle').textContent = fullName || `${c.first_name || ''} ${c.last_name || ''}`.trim();

    const footer = document.getElementById('clientDetailFooter');
    if (footer) {
        let footerHtml = `<button class="btn btn-outline" onclick="closeModal('clientDetailModal')">Close</button>`;
        if (c.verification_status !== 'Approved') {
            footerHtml += `<button class="btn btn-success" onclick="verifyClientFully(${c.client_id})"><span class="material-symbols-rounded ms">verified</span> Verify Client</button>`;
        }
        footer.innerHTML = footerHtml;
    }

    const applicationsHtml = (c.applications || []).length ? c.applications.map(app => `<tr>
        <td class="td-mono td-bold">${escapeHtml(app.application_number)}</td>
        <td>${escapeHtml(app.product_name)}</td>
        <td>${fmt(app.requested_amount)}</td>
        <td class="td-muted">${fmtDate(app.submitted_date || app.created_at)}</td>
        <td>${applicationMonitorBadge(app.application_status)}</td>
    </tr>`).join('') : '<tr class="empty-row"><td colspan="5">No applications found.</td></tr>';

    const docsHtml = (c.documents || []).length ? c.documents.map(doc => `<tr>
        <td class="td-bold">${escapeHtml(doc.document_name)} ${doc.is_required ? '<span class="badge badge-amber" style="font-size:.65rem;padding:2px 6px;">Req</span>' : ''}</td>
        <td>${doc.file_path ? `<a href="../../../${doc.file_path}" target="_blank" class="btn btn-sm btn-outline"><span class="material-symbols-rounded ms" style="font-size:16px;">visibility</span> View</a>` : '<span class="td-muted">Not uploaded</span>'}</td>
        <td class="td-muted">${fmtDate(doc.upload_date)}</td>
        <td>${badge(doc.verification_status || 'Pending')}</td>
        <td>${doc.file_path && doc.verification_status !== 'Verified' ? `<button class="btn btn-sm" style="background:#dcfce7;color:#166534;border:none;" onclick="verifyDoc(${doc.client_document_id}, 'Verified', ${c.client_id})">Verify</button> <button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;border:none;" onclick="verifyDoc(${doc.client_document_id}, 'Rejected', ${c.client_id})">Reject</button>` : 'â€”'}</td>
    </tr>`).join('') : '<tr class="empty-row"><td colspan="5">No documents submitted.</td></tr>';

    const loansHtml = (c.loans || []).length ? c.loans.map(loan => `<tr>
        <td class="td-mono">${escapeHtml(loan.loan_number)}</td>
        <td>${escapeHtml(loan.product_name)}</td>
        <td>${fmt(loan.principal_amount)}</td>
        <td style="color:var(--brand);font-weight:600;">${fmt(loan.remaining_balance)}</td>
        <td>${fmtDate(loan.next_payment_due)}</td>
        <td>${badge(loan.loan_status)}</td>
    </tr>`).join('') : '<tr class="empty-row"><td colspan="6">No loans found.</td></tr>';

    document.getElementById('clientDetailBody').innerHTML = `
        <div class="detail-sections">
            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">badge</span>
                    <div class="detail-section-title">Personal Information</div>
                </div>
                <div class="detail-grid">
                    ${renderDetailItem('Full Name', formatTextValue(fullName))}
                    ${renderDetailItem('Email Address', accountEmail)}
                    ${renderDetailItem('Phone Number', formatTextValue(c.contact_number))}
                    ${renderDetailItem('Date of Birth', formatDateValue(c.date_of_birth))}
                    ${renderDetailItem('Gender', formatTextValue(c.gender))}
                    ${renderDetailItem('Civil Status', formatTextValue(c.civil_status))}
                    ${renderDetailItem('Nationality', formatTextValue(c.nationality))}
                    ${renderDetailItem('Source', sourceBadge(c.user_type))}
                    ${renderDetailItem('Client Status', badge(c.client_status))}
                    ${renderDetailItem('Verification Status', verificationBadge)}
                    ${renderDetailItem('Registered', formatDateValue(c.registration_date))}
                    ${renderDetailItem('Last Login', formatDateValue(c.last_login, 'Never logged in'))}
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">work</span>
                    <div class="detail-section-title">Employment & Financial Profile</div>
                </div>
                <div class="detail-grid">
                    ${renderDetailItem('Employment Status', formatTextValue(c.employment_status))}
                    ${renderDetailItem('Occupation', formatTextValue(c.occupation))}
                    ${renderDetailItem('Employer Name', formatTextValue(c.employer_name))}
                    ${renderDetailItem('Monthly Income', formatMoneyValue(c.monthly_income))}
                    ${renderDetailItem('Credit Limit', formatMoneyValue(c.credit_limit))}
                    ${renderDetailItem('Last Seen Credit Limit', formatMoneyValue(c.last_seen_credit_limit))}
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">home_pin</span>
                    <div class="detail-section-title">Address Details</div>
                </div>
                <div class="detail-grid">
                    ${renderDetailItem('Present Address', formatTextValue(presentAddress), true)}
                    ${renderDetailItem('Permanent Address', formatTextValue(permanentAddress), true)}
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">groups</span>
                    <div class="detail-section-title">Co-Maker Information</div>
                </div>
                <div class="detail-grid">
                    ${renderDetailItem('Co-Maker Name', formatTextValue(c.comaker_name))}
                    ${renderDetailItem('Relationship', formatTextValue(c.comaker_relationship))}
                    ${renderDetailItem('Contact Number', formatTextValue(c.comaker_contact))}
                    ${renderDetailItem('Monthly Income', formatMoneyValue(c.comaker_income))}
                    ${renderDetailItem('Address', formatTextValue(coMakerAddress), true)}
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">description</span>
                    <div class="detail-section-title">Application History</div>
                </div>
                <div class="detail-table">
                    <table>
                        <thead><tr style="background:var(--body-bg);">
                            <th>App #</th><th>Product</th><th>Requested</th><th>Submitted</th><th>Status</th>
                        </tr></thead>
                        <tbody>${applicationsHtml}</tbody>
                    </table>
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">folder_open</span>
                    <div class="detail-section-title">Submitted Documents</div>
                </div>
                <div class="detail-table">
                    <table>
                        <thead><tr style="background:var(--body-bg);">
                            <th>Document Requirement</th><th>File</th><th>Uploaded</th><th>Status</th><th>Action</th>
                        </tr></thead>
                        <tbody>${docsHtml}</tbody>
                    </table>
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">account_balance_wallet</span>
                    <div class="detail-section-title">Loan History</div>
                </div>
                <div class="detail-table">
                    <table>
                        <thead><tr style="background:var(--body-bg);">
                            <th>Loan #</th><th>Product</th><th>Principal</th><th>Balance</th><th>Next Due</th><th>Status</th>
                        </tr></thead>
                        <tbody>${loansHtml}</tbody>
                    </table>
                </div>
            </section>
        </div>`;
}

async function loadClients(search='') {
    const tbody = document.getElementById('clientsTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr class="loading-row"><td colspan="7"><span class="spinner"></span></td></tr>';
    const r = await fetch(API.clients + '?action=list' + (search ? '&search=' + encodeURIComponent(search) : ''));
    const d = await r.json();
    if (!d.data || !d.data.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="7">No clients found.</td></tr>';
        return;
    }
    tbody.innerHTML = d.data.map(c => `<tr>
        <td class="td-bold">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</td>
        <td class="td-muted">${c.email_address && c.email_address.trim() ? escapeHtml(c.email_address) : '-'}</td>
        <td class="td-muted">${c.contact_number && c.contact_number.trim() ? escapeHtml(c.contact_number) : '-'}</td>
        <td class="td-muted">${fmtDate(c.registration_date)}</td>
        <td>${sourceBadge(c.user_type)}</td>
        <td>${badge(c.client_status)}</td>
        <td><button class="btn btn-sm btn-outline" onclick="viewClient(${c.client_id})">View Profile</button></td>
    </tr>`).join('');
}

async function verifyDoc(doc_id, status, client_id) {
    if (!confirm(`Are you sure you want to mark this document as ${status}?`)) return;
    try {
        const payload = { document_id: doc_id, status: status };
        const res = await fetch(API.clients + '?action=verify_document', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const raw = await res.text();
        let result;
        try {
            result = JSON.parse(raw);
        } catch (_) {
            result = {
                status: 'error',
                message: raw && !raw.trim().startsWith('<')
                    ? raw.trim()
                    : `Document verification failed (HTTP ${res.status}).`
            };
        }
        alert(result.message);
        if (result.status === 'success') {
            viewClient(client_id); // Reload the modal to show updated status
            loadClients(); // Reload the clients grid
        }
    } catch(err) {
        alert('An error occurred.');
    }
}

async function verifyClientFully(client_id) {
    if (!confirm('Are you sure you want to fully verify this client? This will approve all their documents and allow them to apply for loans.')) return;
    try {
        const payload = { client_id: client_id };
        const res = await fetch(API.clients + '?action=verify_client_fully', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const raw = await res.text();
        let result;
        try {
            result = JSON.parse(raw);
        } catch (_) {
            result = {
                status: 'error',
                message: raw && !raw.trim().startsWith('<')
                    ? raw.trim()
                    : `Client verification failed (HTTP ${res.status}).`
            };
        }
        alert(result.message);
        if (result.status === 'success') {
            viewClient(client_id);
            loadClients();
        }
    } catch(err) {
        alert('An error occurred automatically verifying the client.');
    }
}

// ── Payments ──────────────────────────────────────────────────
async function loadPayments() {
    const tbody = document.getElementById('paymentsTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
    const r = await fetch(API.payments + '?action=list');
    const d = await r.json();
    const rows = d.data || [];
    if (d.todays_total !== undefined) setText('receiptTodayTotal', fmt(d.todays_total));
    const todayString = new Date().toISOString().slice(0, 10);
    const todaysCount = rows.filter(p => String(p.payment_date || '').slice(0, 10) === todayString && p.payment_status !== 'Cancelled').length;
    setText('receiptTodayCount', todaysCount);
    setText('receiptLatestPosted', rows.length ? fmtDate(rows[0].payment_date || rows[0].created_at) : 'â€”');
    if (!rows.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No transaction records found.</td></tr>'; return; }
    tbody.innerHTML = rows.map(p => `<tr>
        <td class="td-mono td-bold">${escapeHtml(p.official_receipt_number || p.payment_reference || '-')}</td>
        <td class="td-mono td-muted">${escapeHtml(p.payment_reference_number || p.payment_reference || '-')}</td>
        <td>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</td>
        <td class="td-mono td-muted">${escapeHtml(p.loan_number)}</td>
        <td class="td-bold" style="color:#10b981;">${fmt(p.payment_amount)}</td>
        <td class="td-muted">${escapeHtml(p.payment_method)}</td>
        <td class="td-muted">${fmtDate(p.payment_date)}</td>
        <td>${badge(p.payment_status)}</td>
    </tr>`).join('');
}

async function loadPayments() {
    const tbody = document.getElementById('paymentsTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
    const r = await fetch(API.payments + '?action=list');
    const d = await r.json();
    const rows = d.data || [];
    if (d.todays_total !== undefined) setText('receiptTodayTotal', fmt(d.todays_total));
    const todayString = new Date().toISOString().slice(0, 10);
    const todaysCount = rows.filter(p => String(p.payment_date || '').slice(0, 10) === todayString && p.payment_status !== 'Cancelled').length;
    setText('receiptTodayCount', todaysCount);
    setText('receiptLatestPosted', rows.length ? fmtDate(rows[0].payment_date || rows[0].created_at) : '-');
    if (!rows.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No transaction records found.</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(p => `<tr>
        <td class="td-mono td-bold">${escapeHtml(p.official_receipt_number || p.payment_reference || '-')}</td>
        <td class="td-mono td-muted">${escapeHtml(p.payment_reference_number || p.payment_reference || '-')}</td>
        <td>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</td>
        <td class="td-mono td-muted">${escapeHtml(p.loan_number)}</td>
        <td class="td-bold" style="color:#10b981;">${fmt(p.payment_amount)}</td>
        <td class="td-muted">${escapeHtml(p.payment_method)}</td>
        <td class="td-muted">${fmtDate(p.payment_date)}</td>
        <td>${badge(p.payment_status)}</td>
    </tr>`).join('');
}

async function loadPaymentLoans() {
    const sel = document.getElementById('payLoanId');
    if (!sel) return;
    const r = await fetch(API.payments + '?action=active_loans');
    const d = await r.json();
    if (!d.data) return;
    sel.innerHTML = '<option value="">— Select a loan —</option>' +
        d.data.map(l => `<option value="${l.loan_id}" data-balance="${l.remaining_balance}" data-amort="${l.monthly_amortization}" data-due="${l.next_payment_due}">
            ${l.first_name} ${l.last_name} — ${l.loan_number} (Bal: ${fmt(l.remaining_balance)})
        </option>`).join('');
}

function onPayLoanChange() {
    const sel = document.getElementById('payLoanId');
    const opt = sel.selectedOptions[0];
    const info = document.getElementById('payLoanInfo');
    if (!opt || !opt.value) { info.textContent = ''; return; }
    info.textContent = `Balance: ${fmt(opt.dataset.balance)} · Monthly: ${fmt(opt.dataset.amort)} · Next Due: ${fmtDate(opt.dataset.due)}`;
    document.getElementById('payAmount').value = opt.dataset.amort;
}

function openPaymentFromLoan() {
    const sel = document.getElementById('payLoanId');
    if (sel && activeLoanId) { sel.value = activeLoanId; onPayLoanChange(); }
    closeModal('loanDetailModal');
    openModal('paymentModal');
}

async function submitPayment() {
    const payload = {
        loan_id: parseInt(document.getElementById('payLoanId').value),
        payment_amount: parseFloat(document.getElementById('payAmount').value),
        payment_method: document.getElementById('payMethod').value,
        payment_date: document.getElementById('payDate').value,
        or_number: document.getElementById('payOR').value,
        payment_ref_number: document.getElementById('payRef').value,
        remarks: document.getElementById('payRemarks').value,
    };
    if (!payload.loan_id || !payload.payment_amount) { alert('Please select a loan and enter an amount.'); return; }
    const r = await fetch(API.payments + '?action=post', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const d = await r.json();
    alert(d.message);
    if (d.status === 'success') { closeModal('paymentModal'); loadPayments(); loadDashboardStats(); loadPaymentLoans(); }
}

// ── Reports ───────────────────────────────────────────────────
async function loadReports(period='month') {
    const body = document.getElementById('reportsBody');
    if (!body) return;
    body.innerHTML = '<div style="text-align:center;padding:40px;"><span class="spinner"></span></div>';
    const r = await fetch(API.dashboard + `?action=reports&period=${period}`);
    const d = await r.json();
    if (d.status !== 'success') { body.innerHTML = '<p style="color:var(--muted);padding:24px;">Could not load report data.</p>'; return; }
    const s = d.data;
    body.innerHTML = `
        <div class="reports-kpi">
            <div class="kpi-card"><div class="kpi-label">Total Collections</div><div class="kpi-val" style="color:var(--brand);">${fmt(s.total_collections)}</div></div>
            <div class="kpi-card"><div class="kpi-label">Disbursed</div><div class="kpi-val">${fmt(s.disbursed_amount)}</div></div>
            <div class="kpi-card"><div class="kpi-label">New Applications</div><div class="kpi-val">${s.new_applications}</div></div>
            <div class="kpi-card"><div class="kpi-label">Loans Released</div><div class="kpi-val">${s.new_loans}</div></div>
        </div>
        <div class="two-col">
            <div class="card">
                <div class="card-header"><span class="material-symbols-rounded ms">donut_small</span><h3>Loan Portfolio Status</h3></div>
                <div style="padding:4px 0;">
                    ${(s.loan_status_breakdown||[]).map(b => `
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;border-top:1px solid var(--border);">
                            <span style="display:flex;align-items:center;gap:8px;">${badge(b.loan_status)} ${b.loan_status}</span>
                            <strong>${b.cnt}</strong>
                        </div>`).join('') || '<p style="padding:20px;color:var(--muted);">No data.</p>'}
                </div>
            </div>
            <div class="card">
                <div class="card-header"><span class="material-symbols-rounded ms">format_list_numbered</span><h3>Application Pipeline</h3></div>
                <div style="padding:4px 0;">
                    ${(s.application_status_breakdown||[]).map(b => `
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;border-top:1px solid var(--border);">
                            <span style="display:flex;align-items:center;gap:8px;">${badge(b.application_status)} ${b.application_status}</span>
                            <strong>${b.cnt}</strong>
                        </div>`).join('') || '<p style="padding:20px;color:var(--muted);">No data.</p>'}
                </div>
            </div>
        </div>`;
}

// ── Users ──────────────────────────────────────────────────────
async function loadUsers() {
    const tbody = document.getElementById('usersTbody');
    if (!tbody) return;
    const r = await fetch('../../backend/api_auth.php?action=list_users');
    const d = await r.json();
    if (d.status !== 'success') {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="5">The team directory is unavailable right now.</td></tr>';
        return;
    }
    const rows = d.data || [];
    if (!rows.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="5">No staff accounts are available for this tenant.</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(u => `<tr>
        <td class="td-bold">${u.first_name||''} ${u.last_name||''} <span style="font-size:.78rem;color:var(--muted);">(${u.username})</span></td>
        <td class="td-muted">${u.email||'—'}</td>
        <td class="td-muted">${u.department||'—'}</td>
        <td class="td-muted">${u.position||u.role_name||'—'}</td>
        <td>${badge(u.status)}</td>
    </tr>`).join('');
}

// ── Walk-In ────────────────────────────────────────────────────
async function submitWalkIn(action) {
    const form = document.getElementById('walkInForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const formData = new FormData(form);
    formData.set('walk_in_action', action === 'submit' ? 'submit' : 'draft');
    formData.set('documents_complete', document.getElementById('documentsComplete').checked ? '1' : '0');
    try {
        const res = await fetch(API.walk_in, {method:'POST',body:formData});
        const result = await res.json();
        if (result.status === 'success') {
            alert(result.message || 'Walk-in registration saved.');
            form.reset();
            closeModal('walkInModal');
            location.reload();
        } else { alert('Error: ' + result.message); }
    } catch(err) { console.error(err); alert('An error occurred. Please try again.'); }
}

// ── Close on backdrop click ─────────────────────────────────────
document.querySelectorAll('.modal-backdrop').forEach(bd => {
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});
</script>

<?php
// Helper for PHP-rendered badges
function statusBadgePHP($s) {
    $map = [
        'Active' => 'badge-green', 'Approved' => 'badge-green', 'Posted' => 'badge-green',
        'Fully Paid' => 'badge-blue', 'Under Review' => 'badge-blue',
        'For Approval' => 'badge-purple', 'Credit Investigation' => 'badge-purple',
        'Document Verification' => 'badge-purple',
        'Overdue' => 'badge-red', 'Rejected' => 'badge-red', 'Blacklisted' => 'badge-red',
        'Cancelled' => 'badge-gray', 'Withdrawn' => 'badge-gray', 'Inactive' => 'badge-gray',
        'Draft' => 'badge-amber', 'Submitted' => 'badge-amber',
    ];
    $cls = $map[$s] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($s) . '</span>';
}
?>
</body>
</html>
