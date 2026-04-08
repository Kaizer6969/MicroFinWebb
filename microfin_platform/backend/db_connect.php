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

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db_runtime.php';

$mf_db_runtime = mf_resolve_db_targets();
$mf_db_targets = $mf_db_runtime['targets'] ?? [];
$mf_db_mode = (string) ($mf_db_runtime['mode'] ?? 'local');

$host = (string) ($mf_db_targets[0]['host'] ?? 'localhost');
$port = (int) ($mf_db_targets[0]['port'] ?? 3306);
$db = (string) ($mf_db_targets[0]['db'] ?? 'microfin_db');
$user = (string) ($mf_db_targets[0]['user'] ?? 'root');
$pass = (string) ($mf_db_targets[0]['pass'] ?? '');

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

function mf_db_is_retryable_disconnect(\Throwable $error): bool
{
    $message = (string) $error->getMessage();

    foreach ([
        'SQLSTATE[HY000] [2006]',
        'SQLSTATE[HY000] [2013]',
        'MySQL server has gone away',
        'Lost connection to MySQL server',
    ] as $needle) {
        if (stripos($message, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function mf_db_should_expose_debug(): bool
{
    $flag = mf_env_first(['MF_DB_DEBUG']);
    if ($flag === null) {
        return PHP_SAPI === 'cli';
    }

    return in_array(strtolower(trim($flag)), ['1', 'true', 'yes', 'on'], true);
}

function mf_db_request_context(): array
{
    return [
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? PHP_SAPI),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];
}

function mf_db_log_connection_failure(string $errorId, \Throwable $error, array $context = []): void
{
    $payload = array_merge(mf_db_request_context(), $context, [
        'error_id' => $errorId,
        'message' => $error->getMessage(),
    ]);

    $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($encodedPayload === false) {
        $encodedPayload = $error->getMessage();
    }

    error_log('Database Connection Failed: ' . $encodedPayload);
}

function mf_db_connect_target(array $candidateTarget, string $charset, array $options): array
{
    $targetDsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) $candidateTarget['host'],
        (int) $candidateTarget['port'],
        (string) $candidateTarget['db'],
        $charset
    );

    $attempt = 0;
    do {
        $attempt++;

        try {
            $pdo = new PDO(
                $targetDsn,
                (string) $candidateTarget['user'],
                (string) $candidateTarget['pass'],
                $options
            );

            return [
                'pdo' => $pdo,
                'attempts' => $attempt,
                'dsn' => $targetDsn,
            ];
        } catch (\Throwable $connectionError) {
            if ($attempt >= 2 || !mf_db_is_retryable_disconnect($connectionError)) {
                throw $connectionError;
            }

            usleep(250000);
        }
    } while ($attempt < 2);

    throw new RuntimeException('Unable to establish database connection.');
}

// PDO Options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
    PDO::ATTR_TIMEOUT => 10,
];

try {
    $lastConnectionError = null;
    $lastConnectionTarget = null;
    $connectedTargetIndex = null;
    $connectedTargetAttempts = 1;
    $connectionFailures = [];

    foreach ($mf_db_targets as $index => $candidateTarget) {
        try {
            $connectionResult = mf_db_connect_target($candidateTarget, $charset, $options);
            $pdo = $connectionResult['pdo'];

            $host = (string) $candidateTarget['host'];
            $port = (int) $candidateTarget['port'];
            $db = (string) $candidateTarget['db'];
            $user = (string) $candidateTarget['user'];
            $pass = (string) $candidateTarget['pass'];
            $connectedTargetIndex = $index;
            $connectedTargetAttempts = (int) ($connectionResult['attempts'] ?? 1);
            break;
        } catch (\Throwable $connectionError) {
            $lastConnectionError = $connectionError;
            $lastConnectionTarget = $candidateTarget;
            $connectionFailures[] = [
                'target' => mf_db_target_signature($candidateTarget),
                'message' => $connectionError->getMessage(),
            ];
        }
    }

    if (!isset($pdo)) {
        throw $lastConnectionError ?? new RuntimeException('Unable to establish database connection.');
    }

    if ($mf_db_mode !== 'railway' && $connectedTargetIndex !== null && $connectedTargetIndex > 0) {
        error_log('Primary localhost DB connection failed; using alternate local credentials.');
    }

    if ($connectedTargetAttempts > 1) {
        error_log(sprintf(
            'Recovered transient DB connection after %d attempts for %s',
            $connectedTargetAttempts,
            mf_db_target_signature($mf_db_targets[$connectedTargetIndex] ?? [])
        ));
    }
} catch (\Throwable $e) {
    $errorId = bin2hex(random_bytes(4));

    mf_db_log_connection_failure($errorId, $e, [
        'db_mode' => $mf_db_mode ?? 'unknown',
        'target' => isset($lastConnectionTarget) && is_array($lastConnectionTarget)
            ? mf_db_target_signature($lastConnectionTarget)
            : '',
        'attempted_targets' => $connectionFailures ?? [],
    ]);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Critical System Error [' . $errorId . ']: Unable to establish database connection.' . PHP_EOL);
        if (mf_db_should_expose_debug()) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
        }
        exit(1);
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }

    $response = [
        'status' => 'error',
        'message' => 'Critical System Error: Unable to establish database connection.',
        'error_id' => $errorId,
    ];

    if (mf_db_should_expose_debug()) {
        $response['debug'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>
