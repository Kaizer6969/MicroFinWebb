<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'db.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $username = $data['username'] ?? '';
    // $email mapped to same param if user types email
    $password = $data['password'] ?? '';
    $tenant_id = $data['tenant_id'] ?? '';
    
    if(empty($username) || empty($password) || empty($tenant_id)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    $tenant_stmt = $conn->prepare("SELECT tenant_id FROM tenants WHERE tenant_id = ? AND deleted_at IS NULL LIMIT 1");
    $tenant_stmt->bind_param("s", $tenant_id);
    $tenant_stmt->execute();
    $tenant_exists = $tenant_stmt->get_result()->num_rows === 1;
    $tenant_stmt->close();

    if (!$tenant_exists) {
        echo json_encode(['success' => false, 'message' => 'Invalid tenant_id. Tenant does not exist.']);
        exit;
    }

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
        'u.password_hash',
        'u.status',
        'u.first_name AS user_first_name',
        'u.last_name AS user_last_name',
        'c.client_status',
        'c.first_name AS client_first_name',
        'c.last_name AS client_last_name',
        'c.document_verification_status',
        'c.credit_limit'
    ];
    if ($verificationColumnExists) {
        $selectColumns[] = 'c.verification_status';
    }

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
    $stmt->bind_param("sss", $username, $username, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if ($user['status'] !== 'Active') {
            echo json_encode(['success' => false, 'message' => 'Account is not active.']);
        } elseif ($user['client_status'] !== 'Active') {
            echo json_encode(['success' => false, 'message' => 'Client profile is not active.']);
        } elseif (password_verify($password, $user['password_hash'])) {
            $firstName = $user['user_first_name'] ?? $user['client_first_name'] ?? '';
            $lastName = $user['user_last_name'] ?? $user['client_last_name'] ?? '';
            $verificationStatus = microfin_login_normalize_status(
                $user['verification_status'] ?? null,
                $user['document_verification_status'] ?? null
            );

            echo json_encode([
                'success' => true,
                'message' => 'Login successful!',
                'user_id' => $user['user_id'],
                'first_name' => $firstName,
                'last_name' => $lastName,
                'verification_status' => $verificationStatus,
                'credit_limit' => (float) ($user['credit_limit'] ?? 0),
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid client credentials for this tenant.']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
