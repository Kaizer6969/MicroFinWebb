<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_identity.php';

microfin_api_bootstrap();
microfin_require_post();

function microfin_login_normalize_status(?string $status, ?string $documentStatus): string
{
    $status = trim((string) ($status ?? ''));
    if (in_array($status, ['Approved', 'Verified', 'Pending', 'Rejected', 'Unverified'], true)) {
        return $status;
    }

    $documentStatus = trim((string) ($documentStatus ?? ''));
    return match ($documentStatus) {
        'Approved', 'Verified', 'Rejected' => $documentStatus,
        default => 'Unverified',
    };
}

$data = microfin_read_json_input();
$password = (string) ($data['password'] ?? $data['pin'] ?? '');

if ($password === '') {
    microfin_json_response(['success' => false, 'message' => 'Password is required.'], 422);
}

$context = microfin_identity_resolve_login_context($conn, $data);
if (!is_array($context) || !is_array($context['tenant'] ?? null)) {
    microfin_json_response(['success' => false, 'message' => 'A valid login username is required.'], 422);
}

$tenant = $context['tenant'];
$tenantId = (string) ($tenant['tenant_id'] ?? '');
$tenantSlug = (string) ($tenant['tenant_slug'] ?? '');
$baseUsername = trim((string) ($context['base_username'] ?? ''));
$canonicalLoginUsername = mf_mobile_identity_build_login_username($baseUsername, $tenantSlug);
$isLegacyRequest = trim((string) ($data['login_username'] ?? '')) === '';

$verificationColumnExists = false;
if ($columnStmt = $conn->prepare("
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'verification_status'
    LIMIT 1
")) {
    $columnStmt->execute();
    $verificationColumnExists = $columnStmt->get_result()->num_rows === 1;
    $columnStmt->close();
}

$selectColumns = [
    'u.user_id',
    'u.username',
    'u.password_hash',
    'u.status',
    'u.first_name AS user_first_name',
    'u.last_name AS user_last_name',
    'u.email',
    'c.client_status',
    'c.first_name AS client_first_name',
    'c.last_name AS client_last_name',
    'c.document_verification_status',
    'c.credit_limit'
];
if ($verificationColumnExists) {
    $selectColumns[] = 'c.verification_status';
}

if ($isLegacyRequest) {
    $legacyIdentifier = $baseUsername;
    $stmt = $conn->prepare("
        SELECT " . implode(', ', $selectColumns) . "
        FROM users u
        INNER JOIN clients c
            ON c.user_id = u.user_id
           AND c.tenant_id = u.tenant_id
        WHERE (u.username = ? OR u.email = ?)
          AND u.tenant_id = ?
          AND u.user_type = 'Client'
        LIMIT 1
    ");
    $stmt->bind_param('sss', $legacyIdentifier, $legacyIdentifier, $tenantId);
} else {
    $stmt = $conn->prepare("
        SELECT " . implode(', ', $selectColumns) . "
        FROM users u
        INNER JOIN clients c
            ON c.user_id = u.user_id
           AND c.tenant_id = u.tenant_id
        WHERE u.username = ?
          AND u.tenant_id = ?
          AND u.user_type = 'Client'
        LIMIT 1
    ");
    $stmt->bind_param('ss', $baseUsername, $tenantId);
}

$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
    microfin_json_response(['success' => false, 'message' => 'Invalid login username or password.'], 401);
}

if (($user['status'] ?? '') !== 'Active') {
    microfin_json_response(['success' => false, 'message' => 'Account is not active.'], 403);
}

if (($user['client_status'] ?? '') !== 'Active') {
    microfin_json_response(['success' => false, 'message' => 'Client profile is not active.'], 403);
}

$firstName = trim((string) ($user['user_first_name'] ?? $user['client_first_name'] ?? ''));
$lastName = trim((string) ($user['user_last_name'] ?? $user['client_last_name'] ?? ''));
$verificationStatus = microfin_login_normalize_status(
    $user['verification_status'] ?? null,
    $user['document_verification_status'] ?? null
);

microfin_json_response([
    'success' => true,
    'message' => 'Login successful!',
    'user_id' => (int) ($user['user_id'] ?? 0),
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => (string) ($user['email'] ?? ''),
    'verification_status' => $verificationStatus,
    'credit_limit' => (float) ($user['credit_limit'] ?? 0),
    'login_username' => mf_mobile_identity_build_login_username((string) ($user['username'] ?? ''), $tenantSlug),
    'tenant' => microfin_identity_branding_payload($tenant),
]);
