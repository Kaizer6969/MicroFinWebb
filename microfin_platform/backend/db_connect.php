<?php
// backend/db_connect.php
// Centralized, secure database connection wrapper using PDO.

$charset = 'utf8mb4';

// ---------------------------------------------------------------
// Primary (local) DB defaults — Localhost (XAMPP/WAMP) credentials.
// ---------------------------------------------------------------
$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$db = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

// (Old local fallback variables removed as the primary is now localhost)

function mf_env_first(array $keys)
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && trim((string) $value) !== '') {
            return (string) $value;
        }
    }

    return null;
}

function mf_db_target_signature(array $target): string
{
    return implode('|', [
        (string) ($target['host'] ?? ''),
        (string) ($target['port'] ?? ''),
        (string) ($target['db'] ?? ''),
        (string) ($target['user'] ?? ''),
    ]);
}

$mf_local_mail_config = [];
$mf_local_mail_config_path = __DIR__ . DIRECTORY_SEPARATOR . 'local_mail_config.php';
if (is_file($mf_local_mail_config_path)) {
    $loaded_cfg = require $mf_local_mail_config_path;
    if (is_array($loaded_cfg)) {
        $mf_local_mail_config = $loaded_cfg;
    }
}

function mf_local_config_value(string $key, string $default = ''): string
{
    global $mf_local_mail_config;
    if (isset($mf_local_mail_config[$key])) {
        $value = trim((string) $mf_local_mail_config[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return $default;
}

// Override defaults with env-var URL (Railway DATABASE_URL, etc.).
$databaseUrl = mf_env_first(['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL', 'MYSQL_PRIVATE_URL']);
if ($databaseUrl !== null) {
    $parts = parse_url($databaseUrl);
    if ($parts !== false) {
        if (!empty($parts['host'])) {
            $host = (string) $parts['host'];
        }
        if (!empty($parts['port'])) {
            $port = (int) $parts['port'];
        }
        if (array_key_exists('user', $parts)) {
            $user = urldecode((string) $parts['user']);
        }
        if (array_key_exists('pass', $parts)) {
            $pass = urldecode((string) $parts['pass']);
        }
        if (!empty($parts['path'])) {
            $db = ltrim((string) $parts['path'], '/');
        }
    }
}

// Override further with Railway plugin-style discrete variables
// (these win over DATABASE_URL when explicitly set).
$envHost = mf_env_first(['MYSQLHOST', 'DB_HOST']);
if ($envHost !== null) {
    $host = $envHost;
}
$envPort = mf_env_first(['MYSQLPORT', 'DB_PORT']);
if ($envPort !== null) {
    $port = (int) $envPort;
}
$envDb = mf_env_first(['MYSQLDATABASE', 'DB_NAME']);
if ($envDb !== null) {
    $db = $envDb;
}
$envUser = mf_env_first(['MYSQLUSER', 'DB_USER']);
if ($envUser !== null) {
    $user = $envUser;
}
$envPass = mf_env_first(['MYSQLPASSWORD', 'DB_PASSWORD']);
if ($envPass !== null) {
    $pass = $envPass;
}

$resolvedBrevoApiKey = mf_env_first(['BREVO_API_KEY']) ?? mf_local_config_value('BREVO_API_KEY', 'YOUR_BREVO_API_KEY');
$resolvedBrevoSenderEmail = mf_env_first(['BREVO_SENDER_EMAIL']) ?? mf_local_config_value('BREVO_SENDER_EMAIL', 'microfin.statements@gmail.com');
$resolvedBrevoSenderName = mf_env_first(['BREVO_SENDER_NAME']) ?? mf_local_config_value('BREVO_SENDER_NAME', 'MicroFin');

if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', $resolvedBrevoApiKey);
}
if (!defined('BREVO_SENDER_EMAIL')) {
    define('BREVO_SENDER_EMAIL', $resolvedBrevoSenderEmail);
}
if (!defined('BREVO_SENDER_NAME')) {
    define('BREVO_SENDER_NAME', $resolvedBrevoSenderName);
}

function mf_send_brevo_email($toEmail, $subject, $htmlContent)
{
    $recipient = trim((string) $toEmail);
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid recipient email address.';
    }

    $apiKey = trim((string) BREVO_API_KEY);
    if ($apiKey === '' || stripos($apiKey, 'YOUR_BREVO_API_KEY') !== false) {
        return 'Brevo API key is not configured.';
    }

    $payload = json_encode([
        'sender' => [
            'name' => (string) BREVO_SENDER_NAME,
            'email' => (string) BREVO_SENDER_EMAIL,
        ],
        'to' => [['email' => $recipient]],
        'subject' => (string) $subject,
        'htmlContent' => (string) $htmlContent,
    ]);

    if ($payload === false) {
        return 'Failed to encode Brevo payload.';
    }

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($ch === false) {
        return 'Failed to initialize cURL for Brevo.';
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $result = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return 'cURL Error: ' . $curlError;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return 'Email sent successfully.';
    }

    return 'API Error: HTTP ' . $httpCode . ' - ' . (is_string($result) ? $result : '');
}

// PDO Options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
];

try {
    $targetDsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        (int)$port,
        $db,
        $charset
    );

    $pdo = new PDO($targetDsn, $user, $pass, $options);

    // Proceed with schema guards and migrations inside the same main try block

    // Schema guard for newer website customization flows.
    // This keeps older databases compatible without a manual migration step.
    try {
        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS tenant_website_content (\n                tenant_id VARCHAR(50) PRIMARY KEY,\n                layout_template ENUM('template1', 'template2', 'template3') DEFAULT 'template1',\n                hero_title VARCHAR(255) NULL,\n                hero_subtitle VARCHAR(255) NULL,\n                hero_description TEXT NULL,\n                hero_cta_text VARCHAR(100) DEFAULT 'Learn More',\n                hero_cta_url VARCHAR(255) DEFAULT '#about',\n                hero_image_path VARCHAR(500) NULL,\n                about_heading VARCHAR(255) DEFAULT 'About Us',\n                about_body TEXT NULL,\n                about_image_path VARCHAR(500) NULL,\n                services_heading VARCHAR(255) DEFAULT 'Our Services',\n                services_json LONGTEXT NULL,\n                contact_address TEXT NULL,\n                contact_phone VARCHAR(100) NULL,\n                contact_email VARCHAR(255) NULL,\n                contact_hours VARCHAR(255) NULL,\n                custom_css LONGTEXT NULL,\n                meta_description VARCHAR(255) NULL,\n                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n                CONSTRAINT fk_tenant_website_content_tenant\n                    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n        ");

        // Add newer content columns used by the Website Editor publish workflow.
        $websiteContentColumnMigrations = [
            "ALTER TABLE tenant_website_content ADD COLUMN hero_badge_text VARCHAR(255) NULL AFTER hero_image_path",
            "ALTER TABLE tenant_website_content ADD COLUMN stats_json LONGTEXT NULL AFTER services_json",
            "ALTER TABLE tenant_website_content ADD COLUMN stats_heading VARCHAR(255) NULL AFTER stats_json",
            "ALTER TABLE tenant_website_content ADD COLUMN stats_subheading VARCHAR(255) NULL AFTER stats_heading",
            "ALTER TABLE tenant_website_content ADD COLUMN stats_image_path VARCHAR(500) NULL AFTER stats_subheading",
            "ALTER TABLE tenant_website_content ADD COLUMN footer_description TEXT NULL AFTER contact_hours",
            // JSON bucket for all page content (builder migration)
            "ALTER TABLE tenant_website_content ADD COLUMN website_data JSON NULL COMMENT 'Stores hero, about, services, toggles, section_styles, and arrays' AFTER layout_template",
        ];
        foreach ($websiteContentColumnMigrations as $migrationSql) {
            try {
                $pdo->exec($migrationSql);
            } catch (\PDOException $columnError) {
                // Column already exists or DB flavor differs; safe to ignore.
            }
        }
    } catch (\PDOException $migrationError) {
        error_log('Schema guard warning (tenant_website_content): ' . $migrationError->getMessage());
    }

    // Migrate layout_template to flexible VARCHAR from old ENUM
    try {
        $pdo->exec("ALTER TABLE tenant_website_content MODIFY COLUMN layout_template VARCHAR(50) DEFAULT 'template1.php'");
    } catch (\PDOException $e) {
        // Already migrated or table does not exist yet.
    }

    // Add setup step tracking column for onboarding wizard
    try {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN setup_current_step INT DEFAULT 0 COMMENT 'Onboarding step: 0=password_reset, 1=billing, 2=branding, 3=website, 4=done'");
        // Backfill existing tenants based on their current progress
        $pdo->exec("
            UPDATE tenants t SET setup_current_step =
                CASE
                    WHEN t.setup_completed = TRUE THEN 4
                    WHEN EXISTS (SELECT 1 FROM tenant_website_content w WHERE w.tenant_id = t.tenant_id) THEN 3
                    WHEN EXISTS (SELECT 1 FROM tenant_branding br WHERE br.tenant_id = t.tenant_id) THEN 2
                    WHEN EXISTS (SELECT 1 FROM tenant_billing_payment_methods b WHERE b.tenant_id = t.tenant_id) THEN 1
                    ELSE 0
                END
        ");
    } catch (\PDOException $migrationError) {
        // Column already exists. Ignore.
    }

    // Add card style columns to tenant_branding
    try {
        $pdo->exec("ALTER TABLE tenant_branding ADD COLUMN theme_border_color VARCHAR(10) DEFAULT '#e2e8f0' COMMENT 'Card border/divider color'");
    } catch (\PDOException $e) {
    }
    try {
        $pdo->exec("ALTER TABLE tenant_branding ADD COLUMN card_border_width TINYINT DEFAULT 1 COMMENT 'Card border width in px (0-3)'");
    } catch (\PDOException $e) {
    }
    try {
        $pdo->exec("ALTER TABLE tenant_branding ADD COLUMN card_shadow VARCHAR(10) DEFAULT 'sm' COMMENT 'Card shadow: none, sm, md, lg'");
    } catch (\PDOException $e) {
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS mobile_install_attributions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tracking_token VARCHAR(64) NOT NULL UNIQUE,
                tenant_id VARCHAR(50) NOT NULL,
                tenant_slug VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent_hash VARCHAR(64) NOT NULL,
                user_agent TEXT NULL,
                platform_hint VARCHAR(32) NOT NULL DEFAULT 'unknown',
                referer_url VARCHAR(500) NULL,
                claimed_at DATETIME NULL,
                claimed_ip_address VARCHAR(45) NULL,
                claimed_platform_hint VARCHAR(32) NULL,
                claimed_user_agent TEXT NULL,
                last_seen_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_mobile_install_lookup (ip_address, platform_hint, claimed_at, expires_at, created_at),
                INDEX idx_mobile_install_tenant (tenant_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (\PDOException $e) {
        error_log('Schema guard warning (mobile_install_attributions): ' . $e->getMessage());
    }
} catch (\Throwable $e) {
    error_log('Database Connection Failed: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Critical System Error: Unable to establish database connection.',
        'debug' => $e->getMessage(),
    ]);
    exit;
}
?>
