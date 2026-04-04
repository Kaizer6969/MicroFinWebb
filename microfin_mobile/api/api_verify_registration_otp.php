<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_service.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$email = microfin_clean_string($data['email'] ?? '');
$tenantId = microfin_clean_string($data['tenant_id'] ?? '');
$otp = microfin_clean_string($data['otp'] ?? '');

if ($email === '' || $tenantId === '' || $otp === '') {
    microfin_json_response(['success' => false, 'message' => 'Required fields are missing'], 422);
}

try {
    $userStmt = $conn->prepare("
        SELECT user_id, email_verified, verification_token
        FROM users
        WHERE email = ?
          AND tenant_id = ?
          AND user_type = 'Client'
          AND deleted_at IS NULL
        ORDER BY user_id DESC
        LIMIT 1
    ");
    $userStmt->bind_param('ss', $email, $tenantId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        microfin_json_response(['success' => false, 'message' => 'No pending registration was found for this email.'], 404);
    }

    if ((int) ($user['email_verified'] ?? 0) === 1) {
        microfin_json_response(['success' => true, 'message' => 'Email is already verified.']);
    }

    if (microfin_verification_token_is_expired($user['verification_token'] ?? null)) {
        microfin_json_response(['success' => false, 'message' => 'This verification code has expired. Please register again.'], 422);
    }

    if (!microfin_verify_verification_code($user['verification_token'] ?? null, $otp)) {
        microfin_json_response(['success' => false, 'message' => 'The verification code you entered is invalid.'], 422);
    }

    $updateStmt = $conn->prepare("
        UPDATE users
        SET email_verified = 1, verification_token = NULL
        WHERE user_id = ?
          AND tenant_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $updateStmt->bind_param('is', $user['user_id'], $tenantId);
    $updateStmt->execute();
    $updateStmt->close();

    microfin_json_response(['success' => true, 'message' => 'Email verified successfully.']);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
