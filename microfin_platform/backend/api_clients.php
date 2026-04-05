<?php
header('Content-Type: application/json');
require_once 'session_auth.php';
mf_start_backend_session();
require_once 'db_connect.php';
require_once 'credit_policy.php';
require_once 'document_access.php';
mf_require_tenant_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized.',
]);

$tenant_id       = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int)    ($_SESSION['user_id']   ?? 0);

if ($tenant_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing tenant context.']);
    exit;
}

$perm_stmt = $pdo->prepare('
    SELECT p.permission_code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    JOIN users u ON u.role_id = rp.role_id
    WHERE u.user_id = ?
');
$perm_stmt->execute([$session_user_id]);
$permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);
function has_perm($code) { global $permissions; return in_array($code, $permissions); }
function client_table_has_column(PDO $pdo, string $column_name): bool {
    static $cache = [];

    $cache_key = strtolower($column_name);
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$column_name]);
    $cache[$cache_key] = (bool) $stmt->fetchColumn();

    return $cache[$cache_key];
}

function client_effective_verification_sql(PDO $pdo, string $alias = 'c'): string {
    if (client_table_has_column($pdo, 'verification_status')) {
        return "{$alias}.verification_status";
    }

    return "
        CASE
            WHEN {$alias}.document_verification_status IN ('Verified', 'Approved') THEN 'Approved'
            WHEN {$alias}.document_verification_status = 'Rejected' THEN 'Rejected'
            ELSE 'Pending'
        END
    ";
}

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: list clients ────────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'list' || $action === '')) {
    if (!has_perm('VIEW_CLIENTS') && !has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $search = trim((string) ($_GET['search'] ?? ''));
    $params = [$tenant_id];
    $where_extra = '';
    if ($search !== '') {
        $q = '%' . $search . '%';
        $where_extra = ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email_address LIKE ? OR c.contact_number LIKE ?)';
        $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
    }

    $stmt = $pdo->prepare("
        SELECT c.client_id, c.first_name, c.last_name, c.email_address,
               c.contact_number, c.client_status, c.document_verification_status, c.registration_date,
               c.credit_limit, c.date_of_birth, c.occupation, c.monthly_income,
               c.present_city, c.present_province, u.user_type,
               COUNT(DISTINCT la.application_id) AS total_applications,
               COUNT(DISTINCT l.loan_id) AS total_loans,
               COALESCE(SUM(CASE WHEN l.loan_status = 'Active' THEN l.remaining_balance END), 0) AS active_balance
        FROM clients c
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN loan_applications la ON la.client_id = c.client_id AND la.tenant_id = c.tenant_id
        LEFT JOIN loans l ON l.client_id = c.client_id AND l.tenant_id = c.tenant_id
        WHERE c.tenant_id = ? AND c.deleted_at IS NULL $where_extra
        GROUP BY c.client_id
        ORDER BY c.registration_date DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

// ─── GET: view single client with loans ───────────────────────────────────────
if ($method === 'GET' && $action === 'credit_accounts') {
    if (!has_perm('VIEW_CREDIT_ACCOUNTS') && !has_perm('VIEW_CLIENTS') && !has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $search = trim((string) ($_GET['search'] ?? ''));
    $filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
    $score_filter = strtolower(trim((string) ($_GET['score_filter'] ?? 'all')));
    $allowedFilters = ['all', 'eligible', 'not_yet_eligible', 'no_active_limit', 'at_max_limit'];
    if (!in_array($filter, $allowedFilters, true)) {
        $filter = 'all';
    }
    $allowedScoreFilters = ['all', 'high_credit', 'good_credit', 'standard_credit', 'fair_credit', 'at_risk_credit'];
    if (!in_array($score_filter, $allowedScoreFilters, true)) {
        $score_filter = 'all';
    }

    $params = [$tenant_id];
    $where_extra = '';
    if ($search !== '') {
        $q = '%' . $search . '%';
        $where_extra = ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email_address LIKE ? OR c.contact_number LIKE ?)';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $stmt = $pdo->prepare("
        SELECT c.client_id, c.first_name, c.last_name, c.email_address,
               c.contact_number, c.client_status, c.document_verification_status, c.registration_date,
               c.credit_limit, c.last_seen_credit_limit, c.monthly_income,
               c.occupation, c.employment_status, u.user_type
        FROM clients c
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE c.tenant_id = ? AND c.deleted_at IS NULL AND c.document_verification_status IN ('Verified', 'Approved') $where_extra
        ORDER BY c.registration_date DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $credit_limit_rules = mf_get_tenant_credit_limit_rules($pdo, $tenant_id);
    $credit_policy = mf_get_tenant_credit_policy($pdo, $tenant_id);
    $statusOrder = [
        'eligible' => 0,
        'not_yet_eligible' => 1,
        'no_active_limit' => 2,
        'at_max_limit' => 3,
    ];

    $matchesFilter = static function (array $upgrade, string $selectedFilter): bool {
        if ($selectedFilter === 'all') {
            return true;
        }

        return (string) ($upgrade['status'] ?? '') === $selectedFilter;
    };

    $matchesScoreFilter = static function (array $limitSnapshot, string $selectedScoreFilter): bool {
        if ($selectedScoreFilter === 'all') {
            return true;
        }

        $label = strtolower(trim((string) ($limitSnapshot['recommendation_label'] ?? '')));
        $labelMap = [
            'high_credit' => 'high credit score',
            'good_credit' => 'good credit score',
            'standard_credit' => 'standard credit score',
            'fair_credit' => 'fair credit score',
            'at_risk_credit' => 'at-risk credit score',
        ];

        return $label !== '' && $label === ($labelMap[$selectedScoreFilter] ?? '');
    };

    $rows = [];
    foreach ($clients as $client) {
        $client_id = (int) ($client['client_id'] ?? 0);
        if ($client_id <= 0) {
            continue;
        }

        $profile = mf_sync_client_credit_profile($pdo, $tenant_id, $client_id);
        if (array_key_exists('credit_limit', (array) ($profile['client'] ?? []))) {
            $client['credit_limit'] = $profile['client']['credit_limit'];
        }
        if (array_key_exists('last_seen_credit_limit', (array) ($profile['client'] ?? []))) {
            $client['last_seen_credit_limit'] = $profile['client']['last_seen_credit_limit'];
        }

        $score = $profile['score'] ?? null;
        if ($score) {
            $score['total_score'] = (float) mf_credit_policy_normalize_score_value($score['total_score'] ?? 0, 0, false);
        }

        $ci = $profile['ci'] ?? mf_credit_policy_fetch_latest_ci($pdo, $tenant_id, $client_id);
        $upgrade_metrics = mf_credit_policy_fetch_upgrade_metrics($pdo, $tenant_id, $client_id);
        $upgrade = mf_credit_policy_compute_upgrade_snapshot($credit_limit_rules, $client, $upgrade_metrics);
        $limit_snapshot = $profile['limit'] ?? mf_credit_policy_compute_limit_snapshot($credit_policy, $client, $score, $ci);

        if (!$matchesFilter($upgrade, $filter)) {
            continue;
        }

        if (!$matchesScoreFilter($limit_snapshot, $score_filter)) {
            continue;
        }

        $client['credit_upgrade'] = $upgrade;
        $client['limit_snapshot'] = $limit_snapshot;
        $client['latest_score'] = $score ?: null;
        $rows[] = $client;
    }

    usort($rows, static function (array $a, array $b) use ($statusOrder): int {
        $aStatus = (string) ($a['credit_upgrade']['status'] ?? '');
        $bStatus = (string) ($b['credit_upgrade']['status'] ?? '');
        $aRank = $statusOrder[$aStatus] ?? 99;
        $bRank = $statusOrder[$bStatus] ?? 99;

        if ($aRank !== $bRank) {
            return $aRank <=> $bRank;
        }

        return strcmp((string) ($b['registration_date'] ?? ''), (string) ($a['registration_date'] ?? ''));
    });

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

if ($method === 'GET' && $action === 'view') {
    if (!has_perm('VIEW_CLIENTS') && !has_perm('CREATE_CLIENTS') && !has_perm('VIEW_CREDIT_ACCOUNTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $client_id = (int) ($_GET['client_id'] ?? 0);
    if ($client_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid client ID.']);
        exit;
    }

    $verification_status_sql = client_effective_verification_sql($pdo, 'c');

    $stmt = $pdo->prepare("
        SELECT c.*, {$verification_status_sql} AS verification_status, u.email AS user_email, u.username, u.status AS user_status, u.last_login, u.user_type
        FROM clients c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.client_id = ? AND c.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$client_id, $tenant_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        echo json_encode(['status' => 'error', 'message' => 'Client not found.']);
        exit;
    }

    // load their loans
    $loans_stmt = $pdo->prepare("
        SELECT l.loan_id, l.loan_number, l.loan_status, l.principal_amount,
               l.remaining_balance, l.monthly_amortization, l.release_date,
               l.maturity_date, l.next_payment_due, l.total_paid,
               lp.product_name
        FROM loans l
        JOIN loan_products lp ON l.product_id = lp.product_id
        WHERE l.client_id = ? AND l.tenant_id = ?
        ORDER BY l.release_date DESC
    ");
    $loans_stmt->execute([$client_id, $tenant_id]);
    $client['loans'] = $loans_stmt->fetchAll(PDO::FETCH_ASSOC);

    // load applications
    $apps_stmt = $pdo->prepare("
        SELECT la.application_id, la.application_number, la.application_status,
               la.requested_amount, la.submitted_date, la.created_at,
               lp.product_name
        FROM loan_applications la
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.client_id = ? AND la.tenant_id = ?
        ORDER BY la.created_at DESC
    ");
    $apps_stmt->execute([$client_id, $tenant_id]);
    $client['applications'] = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);

    // load documents
    $docs_stmt = $pdo->prepare("
        SELECT cd.client_document_id, cd.document_type_id, cd.file_path, cd.verification_status, cd.upload_date,
               dt.document_name, dt.is_required
        FROM client_documents cd
        JOIN document_types dt ON cd.document_type_id = dt.document_type_id
        WHERE cd.client_id = ? AND cd.tenant_id = ?
        ORDER BY dt.is_required DESC, cd.upload_date DESC
    ");
    $docs_stmt->execute([$client_id, $tenant_id]);
    $client['documents'] = array_map(
        static fn(array $document): array => mf_document_attach_url($document),
        $docs_stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $credit_limit_rules = mf_get_tenant_credit_limit_rules($pdo, $tenant_id);
    $upgrade_metrics = mf_credit_policy_fetch_upgrade_metrics($pdo, $tenant_id, $client_id);
    $profile = mf_sync_client_credit_profile($pdo, $tenant_id, $client_id);
    if (array_key_exists('credit_limit', (array) ($profile['client'] ?? []))) {
        $client['credit_limit'] = $profile['client']['credit_limit'];
    }
    if (array_key_exists('last_seen_credit_limit', (array) ($profile['client'] ?? []))) {
        $client['last_seen_credit_limit'] = $profile['client']['last_seen_credit_limit'];
    }
    $client['credit_upgrade'] = mf_credit_policy_compute_upgrade_snapshot($credit_limit_rules, $client, $upgrade_metrics);
    $client['latest_score'] = $profile['score'] ?? null;
    $client['limit_snapshot'] = $profile['limit'] ?? null;

    echo json_encode(['status' => 'success', 'data' => $client]);
    exit;
}

// ─── POST: update client status ───────────────────────────────────────────────
if ($method === 'POST' && $action === 'update_status') {
    if (!has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $client_id  = (int)    ($payload['client_id']  ?? 0);
    $new_status = trim((string) ($payload['status'] ?? ''));

    $allowed = ['Active', 'Inactive', 'Blacklisted'];
    if ($client_id <= 0 || !in_array($new_status, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid client ID or status.']);
        exit;
    }

    $pdo->prepare("UPDATE clients SET client_status = ?, updated_at = NOW() WHERE client_id = ? AND tenant_id = ?")
        ->execute([$new_status, $client_id, $tenant_id]);

    mf_sync_client_credit_profile($pdo, $tenant_id, $client_id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'CLIENT_STATUS_CHANGE', 'client', ?, ?)")
        ->execute([$session_user_id, $tenant_id, $client_id, "Client status updated to $new_status"]);

    echo json_encode(['status' => 'success', 'message' => "Client status updated to $new_status."]);
    exit;
}

// ─── POST: verify document ──────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'verify_document') {
    if (!has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $doc_id = (int)($payload['document_id'] ?? 0);
    $status = trim((string)($payload['status'] ?? ''));

    if ($doc_id <= 0 || !in_array($status, ['Verified', 'Rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Resolve employee_id from user_id (verified_by is a FK to employees)
        $emp_row = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1");
        $emp_row->execute([$session_user_id, $tenant_id]);
        $emp = $emp_row->fetch(PDO::FETCH_ASSOC);
        $verified_by = $emp ? $emp['employee_id'] : null;

        $pdo->prepare("UPDATE client_documents SET verification_status = ?, verified_by = ?, verification_date = NOW() WHERE client_document_id = ? AND tenant_id = ?")
            ->execute([$status, $verified_by, $doc_id, $tenant_id]);

        // Cascade update to clients table (document_verification_status)
        $pdo->prepare("
            UPDATE clients c
            SET document_verification_status = (
                CASE
                    WHEN (SELECT COUNT(*) FROM client_documents WHERE client_id = c.client_id AND tenant_id = c.tenant_id AND verification_status = 'Rejected') > 0 THEN 'Rejected'
                    WHEN (SELECT COUNT(*) FROM client_documents WHERE client_id = c.client_id AND tenant_id = c.tenant_id AND verification_status IN ('Pending', 'Uploaded', 'CONSIDER')) > 0 THEN 'Unverified'
                    ELSE 'Verified'
                END
            )
            WHERE client_id = (SELECT client_id FROM client_documents WHERE client_document_id = ? LIMIT 1)
        ")->execute([$doc_id]);

        $target_client_stmt = $pdo->prepare("SELECT client_id FROM client_documents WHERE client_document_id = ? LIMIT 1");
        $target_client_stmt->execute([$doc_id]);
        $target_client_id = (int) $target_client_stmt->fetchColumn();

        if (client_table_has_column($pdo, 'verification_status')) {
            $pdo->prepare("
                UPDATE clients
                SET verification_status = (
                    CASE
                        WHEN document_verification_status IN ('Verified', 'Approved') THEN 'Approved'
                        WHEN document_verification_status = 'Rejected' THEN 'Rejected'
                        ELSE 'Pending'
                    END
                ),
                updated_at = NOW()
                WHERE client_id = ? AND tenant_id = ?
            ")->execute([$target_client_id, $tenant_id]);
        }

        if ($target_client_id > 0) {
            mf_sync_client_credit_profile($pdo, $tenant_id, $target_client_id);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Document marked as $status."]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Unable to verify document: ' . $e->getMessage()]);
    }
    exit;
}

// ─── POST: verify entire client ──────────────────────────────────────────────
if ($method === 'POST' && $action === 'verify_client_fully') {
    if (!has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $client_id = (int)($payload['client_id'] ?? 0);

    if ($client_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid client ID.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $client_status_stmt = $pdo->prepare("SELECT client_status FROM clients WHERE client_id = ? AND tenant_id = ? LIMIT 1");
        $client_status_stmt->execute([$client_id, $tenant_id]);
        $current_client_status = (string) $client_status_stmt->fetchColumn();

        // Resolve employee_id from user_id (verified_by is a FK to employees)
        $emp_row2 = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1");
        $emp_row2->execute([$session_user_id, $tenant_id]);
        $emp2 = $emp_row2->fetch(PDO::FETCH_ASSOC);
        $verified_by2 = $emp2 ? $emp2['employee_id'] : null;

        // Force ALL documents to be verified
        $pdo->prepare("UPDATE client_documents SET verification_status = 'Verified', verified_by = ?, verification_date = NOW() WHERE client_id = ? AND tenant_id = ? AND verification_status != 'Verified'")
            ->execute([$verified_by2, $client_id, $tenant_id]);

        $client_verify_sql = "
            UPDATE clients
            SET document_verification_status = 'Approved',
                client_status = CASE WHEN client_status = 'Inactive' THEN 'Active' ELSE client_status END,
                updated_at = NOW()
            WHERE client_id = ? AND tenant_id = ?
        ";

        if (client_table_has_column($pdo, 'verification_status')) {
            $client_verify_sql = "
                UPDATE clients
                SET document_verification_status = 'Approved',
                    client_status = CASE WHEN client_status = 'Inactive' THEN 'Active' ELSE client_status END,
                    verification_status = 'Approved',
                    updated_at = NOW()
                WHERE client_id = ? AND tenant_id = ?
            ";
        }

        $pdo->prepare($client_verify_sql)->execute([$client_id, $tenant_id]);
        mf_sync_client_credit_profile($pdo, $tenant_id, $client_id);

        $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'CLIENT_VERIFIED', 'client', ?, 'Admin manually verified client overall status')")
            ->execute([$session_user_id, $tenant_id, $client_id]);

        $pdo->commit();
        $success_message = $current_client_status === 'Inactive'
            ? 'Client fully verified, approved, and activated.'
            : 'Client fully verified and approved!';
        echo json_encode(['status' => 'success', 'message' => $success_message]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Unable to fully verify client: ' . $e->getMessage()]);
    }
    exit;
}

// ─── POST: approve credit upgrade ────────────────────────────────────────────────
if ($method === 'POST' && $action === 'approve_upgrade') {
    if (!has_perm('APPROVE_LOANS') && !has_perm('CREATE_CLIENTS') && !has_perm('VIEW_CREDIT_ACCOUNTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $client_ids = (array) ($payload['client_ids'] ?? []);
    if (empty($client_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No clients selected for upgrade.']);
        exit;
    }

    $credit_limit_rules = mf_get_tenant_credit_limit_rules($pdo, $tenant_id);
    $upgraded_count = 0;

    try {
        $pdo->beginTransaction();
        
        foreach ($client_ids as $client_id) {
            $client_id = (int)$client_id;
            
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$client_id, $tenant_id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client) continue;

            $upgrade_metrics = mf_credit_policy_fetch_upgrade_metrics($pdo, $tenant_id, $client_id);
            $upgrade = mf_credit_policy_compute_upgrade_snapshot($credit_limit_rules, $client, $upgrade_metrics);

            if (($upgrade['status'] ?? '') === 'eligible' && ($upgrade['potential_upgraded_limit'] ?? 0) > (($client['credit_limit'] ?? 0) > 0 ? $client['credit_limit'] : 0)) {
                $new_limit = (float) $upgrade['potential_upgraded_limit'];
                
                $pdo->prepare("UPDATE clients SET credit_limit = ?, updated_at = NOW() WHERE client_id = ? AND tenant_id = ?")
                    ->execute([$new_limit, $client_id, $tenant_id]);
                
                mf_sync_client_credit_profile($pdo, $tenant_id, $client_id);

                $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'CREDIT_LIMIT_UPGRADED', 'client', ?, ?)")
                    ->execute([$session_user_id, $tenant_id, $client_id, "Credit limit upgraded to limit {$new_limit}"]);
                    
                $upgraded_count++;
            }
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Successfully upgraded {$upgraded_count} client(s)."]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Unable to upgrade clients: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
