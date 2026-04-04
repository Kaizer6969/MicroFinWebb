<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_service.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$email = microfin_clean_string($data['email'] ?? '');
$tenantId = microfin_clean_string($data['tenant_id'] ?? '');

if ($email === '' || $tenantId === '') {
    microfin_json_response(['success' => false, 'message' => 'Required fields are missing'], 422);
}

try {
    $tenantStmt = $conn->prepare("
        SELECT tenant_id, tenant_name
        FROM tenants
        WHERE tenant_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $tenantStmt->bind_param('s', $tenantId);
    $tenantStmt->execute();
    $tenant = $tenantStmt->get_result()->fetch_assoc();
    $tenantStmt->close();

    if (!$tenant) {
        microfin_json_response(['success' => false, 'message' => 'Invalid tenant_id. Tenant does not exist.'], 404);
    }

    $userStmt = $conn->prepare("
        SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.email
        FROM users u
        INNER JOIN clients c
            ON c.user_id = u.user_id
           AND c.tenant_id = u.tenant_id
        WHERE u.email = ?
          AND u.tenant_id = ?
          AND u.user_type = 'Client'
          AND u.deleted_at IS NULL
          AND c.deleted_at IS NULL
          AND c.client_status = 'Active'
        LIMIT 1
    ");
    $userStmt->bind_param('ss', $email, $tenantId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        microfin_json_response(['success' => false, 'message' => 'No account found with that email for this tenant.'], 404);
    }

    $resetCode = microfin_generate_one_time_code();
    $resetToken = password_hash($resetCode, PASSWORD_DEFAULT);
    $resetExpiry = date('Y-m-d H:i:s', time() + (15 * 60));

    $conn->begin_transaction();

    $updateStmt = $conn->prepare("
        UPDATE users
        SET reset_token = ?, reset_token_expiry = ?
        WHERE user_id = ?
          AND tenant_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $updateStmt->bind_param('ssis', $resetToken, $resetExpiry, $user['user_id'], $tenantId);
    $updateStmt->execute();
    $updateStmt->close();

    $emailResult = microfin_send_password_reset_email($conn, [
        'tenant_id' => $tenantId,
        'tenant_name' => $tenant['tenant_name'],
        'user_id' => $user['user_id'],
        'to_email' => $email,
        'recipient_name' => trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? ''))),
        'otp' => $resetCode,
        'ttl_minutes' => 15,
    ]);

    if (!$emailResult['success']) {
        throw new RuntimeException('Unable to send reset email: ' . ($emailResult['message'] ?? 'Unknown email error.'));
    }

    $conn->commit();

    microfin_json_response(['success' => true, 'message' => 'Reset code sent to your email.']);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
