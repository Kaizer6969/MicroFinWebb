<?php
header('Content-Type: application/json');
require_once 'session_auth.php';
mf_start_backend_session();
require_once 'db_connect.php';
mf_require_tenant_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized.',
]);

$tenant_id      = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($tenant_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing tenant context.']);
    exit;
}

// Load permissions
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

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: list ───────────────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'list' || $action === '')) {
    if (!has_perm('VIEW_APPLICATIONS') && !has_perm('MANAGE_APPLICATIONS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $status_filter = trim((string) ($_GET['status'] ?? ''));
    $where_extra   = '';
    $params        = [$tenant_id];

    if ($status_filter !== '' && $status_filter !== 'all') {
        $where_extra = ' AND la.application_status = ?';
        $params[]    = $status_filter;
    }

    $stmt = $pdo->prepare("
        SELECT
            la.application_id, la.application_number, la.application_status,
            la.requested_amount, la.approved_amount, la.loan_term_months,
            la.interest_rate, la.loan_purpose, la.submitted_date, la.created_at,
            la.review_notes, la.approval_notes, la.rejection_reason,
            c.client_id, c.first_name, c.last_name, c.contact_number, c.email_address,
            lp.product_name, lp.product_type
        FROM loan_applications la
        JOIN clients c ON la.client_id = c.client_id
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.tenant_id = ? $where_extra
        ORDER BY COALESCE(la.submitted_date, la.created_at) DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

// ─── GET: view single ────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'view') {
    if (!has_perm('VIEW_APPLICATIONS') && !has_perm('MANAGE_APPLICATIONS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $application_id = (int) ($_GET['id'] ?? 0);
    if ($application_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid application ID.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            la.*,
            c.first_name, c.last_name, c.contact_number, c.email_address,
            c.date_of_birth, c.civil_status, c.occupation, c.employer_name,
            c.monthly_income, c.present_street, c.present_barangay, c.present_city,
            c.present_province, c.credit_limit, c.client_status,
            lp.product_name, lp.product_type, lp.min_amount, lp.max_amount,
            lp.interest_rate AS product_interest_rate, lp.interest_type,
            lp.min_term_months, lp.max_term_months, lp.processing_fee_percentage,
            lp.service_charge, lp.documentary_stamp, lp.insurance_fee_percentage,
            lp.penalty_rate, lp.grace_period_days
        FROM loan_applications la
        JOIN clients c ON la.client_id = c.client_id
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.application_id = ? AND la.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$application_id, $tenant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Application not found.']);
        exit;
    }

    // Decode JSON data
    if (!empty($row['application_data']) && is_string($row['application_data'])) {
        $row['application_data'] = json_decode($row['application_data'], true) ?? [];
    }

    echo json_encode(['status' => 'success', 'data' => $row]);
    exit;
}

// ─── POST: update status ─────────────────────────────────────────────────────
if ($method === 'POST') {
    if (!has_perm('MANAGE_APPLICATIONS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $application_id = (int) ($payload['application_id'] ?? 0);
    $new_action     = strtolower(trim((string) ($payload['action'] ?? '')));
    $notes          = trim((string) ($payload['notes'] ?? ''));
    $approved_amount = isset($payload['approved_amount']) ? (float) $payload['approved_amount'] : null;

    if ($application_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid application ID.']);
        exit;
    }

    // Fetch current app
    $cur_stmt = $pdo->prepare('SELECT application_status, client_id, product_id FROM loan_applications WHERE application_id = ? AND tenant_id = ? LIMIT 1');
    $cur_stmt->execute([$application_id, $tenant_id]);
    $current = $cur_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['status' => 'error', 'message' => 'Application not found.']);
        exit;
    }

    // Get employee_id for the logged-in user
    $emp_stmt = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $emp_stmt->execute([$session_user_id, $tenant_id]);
    $employee_id = $emp_stmt->fetchColumn();
    $employee_id = $employee_id !== false ? (int) $employee_id : null;

    $now = date('Y-m-d H:i:s');

    $allowed_transitions = [
        'submit'          => ['Draft'          => 'Submitted'],
        'start_review'    => ['Submitted'       => 'Under Review', 'Pending Review' => 'Under Review'],
        'verify_docs'     => ['Under Review'    => 'Document Verification'],
        'credit_inv'      => ['Document Verification' => 'Credit Investigation'],
        'for_approval'    => ['Credit Investigation' => 'For Approval'],
        'approve'         => ['For Approval'    => 'Approved', 'Under Review' => 'Approved', 'Submitted' => 'Approved'],
        'reject'          => ['Submitted' => 'Rejected', 'Under Review' => 'Rejected', 'Pending Review' => 'Rejected', 'For Approval' => 'Rejected', 'Document Verification' => 'Rejected', 'Credit Investigation' => 'Rejected'],
        'cancel'          => ['Draft' => 'Cancelled', 'Submitted' => 'Cancelled'],
    ];

    if (!isset($allowed_transitions[$new_action])) {
        echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $new_action]);
        exit;
    }

    $cur_status = $current['application_status'];
    if (!isset($allowed_transitions[$new_action][$cur_status])) {
        echo json_encode(['status' => 'error', 'message' => "Cannot perform '$new_action' on application with status '$cur_status'."]);
        exit;
    }

    $new_status = $allowed_transitions[$new_action][$cur_status];

    try {
        $pdo->beginTransaction();

        if ($new_action === 'approve') {
            $pdo->prepare("
                UPDATE loan_applications
                SET application_status = ?, approved_amount = ?, approved_by = ?,
                    approval_date = ?, approval_notes = ?, updated_at = ?
                WHERE application_id = ? AND tenant_id = ?
            ")->execute([
                $new_status,
                $approved_amount ?? 0,
                $employee_id,
                $now, $notes, $now,
                $application_id, $tenant_id
            ]);

        } elseif ($new_action === 'reject') {
            if ($notes === '') {
                throw new Exception('Rejection reason is required.');
            }
            $pdo->prepare("
                UPDATE loan_applications
                SET application_status = ?, rejected_by = ?,
                    rejection_date = ?, rejection_reason = ?, updated_at = ?
                WHERE application_id = ? AND tenant_id = ?
            ")->execute([
                $new_status,
                $employee_id,
                $now, $notes, $now,
                $application_id, $tenant_id
            ]);

        } elseif (in_array($new_action, ['start_review', 'verify_docs', 'credit_inv', 'for_approval'])) {
            $pdo->prepare("
                UPDATE loan_applications
                SET application_status = ?, reviewed_by = ?,
                    review_date = ?, review_notes = ?, updated_at = ?
                WHERE application_id = ? AND tenant_id = ?
            ")->execute([
                $new_status,
                $employee_id,
                $now, $notes, $now,
                $application_id, $tenant_id
            ]);

        } else {
            // submit or cancel
            $extra_date = ($new_action === 'submit') ? ", submitted_date = '$now'" : '';
            $pdo->prepare("
                UPDATE loan_applications
                SET application_status = ?, updated_at = ? $extra_date
                WHERE application_id = ? AND tenant_id = ?
            ")->execute([$new_status, $now, $application_id, $tenant_id]);
        }

        // Audit log
        $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, ?, 'loan_application', ?, ?)")
            ->execute([$session_user_id, $tenant_id, 'APP_STATUS_' . strtoupper($new_action), $application_id, "Status changed from '$cur_status' to '$new_status'. Notes: $notes"]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Application status updated to '$new_status'.", 'new_status' => $new_status]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
