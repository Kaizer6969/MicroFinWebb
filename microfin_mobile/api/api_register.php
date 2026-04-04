<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_service.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$username = microfin_clean_string($data['username'] ?? '');
$email = microfin_clean_string($data['email'] ?? '');
$password = (string) ($data['password'] ?? '');
$firstName = microfin_clean_string($data['first_name'] ?? '');
$middleName = microfin_clean_string($data['middle_name'] ?? '');
$lastName = microfin_clean_string($data['last_name'] ?? '');
$suffix = microfin_clean_string($data['suffix'] ?? '');
$tenantId = microfin_clean_string($data['tenant_id'] ?? '');

if ($username === '' || $email === '' || $password === '' || $firstName === '' || $lastName === '' || $tenantId === '') {
    microfin_json_response(['success' => false, 'message' => 'Required fields are missing'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    microfin_json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
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
    $tenantResult = $tenantStmt->get_result();
    $tenant = $tenantResult->fetch_assoc();
    $tenantStmt->close();

    if (!$tenant) {
        microfin_json_response(['success' => false, 'message' => 'Invalid tenant_id. Tenant does not exist.'], 404);
    }

    $existingStmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE tenant_id = ?
          AND (username = ? OR email = ?)
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $existingStmt->bind_param('sss', $tenantId, $username, $email);
    $existingStmt->execute();
    $existingFound = $existingStmt->get_result()->num_rows > 0;
    $existingStmt->close();

    if ($existingFound) {
        microfin_json_response(['success' => false, 'message' => 'Username or Email already exists for this tenant.'], 409);
    }

    $conn->begin_transaction();

    $roleStmt = $conn->prepare("
        SELECT role_id
        FROM user_roles
        WHERE role_name = 'Client'
          AND tenant_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $roleStmt->bind_param('s', $tenantId);
    $roleStmt->execute();
    $roleResult = $roleStmt->get_result();

    if ($roleResult->num_rows === 0) {
        $insertRoleStmt = $conn->prepare("
            INSERT INTO user_roles (tenant_id, role_name, role_description)
            VALUES (?, 'Client', 'Default Client Role')
        ");
        $insertRoleStmt->bind_param('s', $tenantId);
        $insertRoleStmt->execute();
        $roleId = $insertRoleStmt->insert_id;
        $insertRoleStmt->close();
    } else {
        $roleId = (int) $roleResult->fetch_assoc()['role_id'];
    }
    $roleStmt->close();

    $verificationCode = microfin_generate_one_time_code();
    $verificationToken = microfin_build_verification_token($verificationCode, 15);
    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

    $userStmt = $conn->prepare("
        INSERT INTO users (
            tenant_id,
            username,
            email,
            password_hash,
            email_verified,
            first_name,
            last_name,
            middle_name,
            suffix,
            role_id,
            user_type,
            status,
            verification_token
        ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'Client', 'Active', ?)
    ");
    $userStmt->bind_param(
        'ssssssssis',
        $tenantId,
        $username,
        $email,
        $passwordHash,
        $firstName,
        $lastName,
        $middleName,
        $suffix,
        $roleId,
        $verificationToken
    );
    $userStmt->execute();
    $userId = $conn->insert_id;
    $userStmt->close();

    $clientCode = 'CLT' . date('Y') . '-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT);
    $clientStmt = $conn->prepare("
        INSERT INTO clients (
            user_id,
            tenant_id,
            client_code,
            first_name,
            middle_name,
            last_name,
            suffix,
            date_of_birth,
            contact_number,
            email_address,
            client_status,
            registration_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, '1990-01-01', '', ?, 'Active', CURDATE())
    ");
    $clientStmt->bind_param(
        'isssssss',
        $userId,
        $tenantId,
        $clientCode,
        $firstName,
        $middleName,
        $lastName,
        $suffix,
        $email
    );
    $clientStmt->execute();
    $clientStmt->close();

    $emailResult = microfin_send_registration_otp_email($conn, [
        'tenant_id' => $tenantId,
        'tenant_name' => $tenant['tenant_name'],
        'user_id' => $userId,
        'to_email' => $email,
        'recipient_name' => trim($firstName . ' ' . $lastName),
        'otp' => $verificationCode,
        'ttl_minutes' => 15,
    ]);

    if (!$emailResult['success']) {
        throw new RuntimeException('Unable to send verification email: ' . ($emailResult['message'] ?? 'Unknown email error.'));
    }

    $conn->commit();

    microfin_json_response([
        'success' => true,
        'requires_otp' => true,
        'message' => 'Verification code sent to your email.',
    ]);
} catch (Throwable $e) {
    if ($conn->errno === 0 && $conn->more_results()) {
        while ($conn->more_results() && $conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    }

    if ($conn->connect_errno === 0) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }
    }

    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
