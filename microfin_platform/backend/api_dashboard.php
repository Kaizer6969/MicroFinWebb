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

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: dashboard stats ─────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'stats' || $action === '')) {

    $stats = [];

    // Pending applications count
    if (has_perm('VIEW_APPLICATIONS') || has_perm('MANAGE_APPLICATIONS')) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM loan_applications WHERE tenant_id = ? AND application_status NOT IN ('Approved','Rejected','Cancelled','Withdrawn')");
        $s->execute([$tenant_id]);
        $stats['pending_applications'] = (int) $s->fetchColumn();
    }

    // Today's collections
    if (has_perm('PROCESS_PAYMENTS')) {
        $s = $pdo->prepare("SELECT 
            (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND payment_status != 'Cancelled') +
            (SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND status != 'Cancelled')");
        $s->execute([$tenant_id, $tenant_id]);
        $stats['todays_collections'] = (float) $s->fetchColumn();
    }

    // Active clients
    if (has_perm('VIEW_CLIENTS') || has_perm('CREATE_CLIENTS')) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND client_status = 'Active' AND deleted_at IS NULL");
        $s->execute([$tenant_id]);
        $stats['active_clients'] = (int) $s->fetchColumn();
    }

    // Active loans
    if (has_perm('VIEW_LOANS')) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND loan_status = 'Active'");
        $s->execute([$tenant_id]);
        $stats['active_loans'] = (int) $s->fetchColumn();

        $s2 = $pdo->prepare("SELECT COALESCE(SUM(remaining_balance), 0) FROM loans WHERE tenant_id = ? AND loan_status IN ('Active','Overdue')");
        $s2->execute([$tenant_id]);
        $stats['total_portfolio'] = (float) $s2->fetchColumn();

        $s3 = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND loan_status = 'Overdue'");
        $s3->execute([$tenant_id]);
        $stats['overdue_loans'] = (int) $s3->fetchColumn();
    }

    // Recent audit log entries (last 10)
    $s = $pdo->prepare("SELECT action_type, description, created_at FROM audit_logs WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10");
    $s->execute([$tenant_id]);
    $stats['recent_activity'] = $s->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $stats]);
    exit;
}

// ─── GET: reports overview ─────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'reports') {
    if (!has_perm('VIEW_REPORTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $period = trim((string) ($_GET['period'] ?? 'month'));
    
    $date_from = match ($period) {
        'week'  => date('Y-m-d', strtotime('-7 days')),
        'month' => date('Y-m-01'),
        'year'  => date('Y-01-01'),
        default => date('Y-m-01'),
    };

    $data = [];

    // Collections by day (for chart)
    $s = $pdo->prepare("
        SELECT day, SUM(total) as total FROM (
            SELECT DATE(payment_date) as day, SUM(payment_amount) as total
            FROM payments
            WHERE tenant_id = ? AND payment_date >= ? AND payment_status != 'Cancelled'
            GROUP BY DATE(payment_date)
            UNION ALL
            SELECT DATE(payment_date) as day, SUM(amount) as total
            FROM payment_transactions
            WHERE tenant_id = ? AND payment_date >= ? AND status != 'Cancelled'
            GROUP BY DATE(payment_date)
        ) combined_collections
        GROUP BY day
        ORDER BY day ASC
    ");
    $s->execute([$tenant_id, $date_from, $tenant_id, $date_from]);
    $data['collections_by_day'] = $s->fetchAll(PDO::FETCH_ASSOC);

    // Summary
    $s = $pdo->prepare("SELECT 
        (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND payment_date >= ? AND payment_status != 'Cancelled') +
        (SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE tenant_id = ? AND payment_date >= ? AND status != 'Cancelled')");
    $s->execute([$tenant_id, $date_from, $tenant_id, $date_from]);
    $data['total_collections'] = (float) $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM loan_applications WHERE tenant_id = ? AND created_at >= ?");
    $s->execute([$tenant_id, $date_from]);
    $data['new_applications'] = (int) $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND release_date >= ?");
    $s->execute([$tenant_id, $date_from]);
    $data['new_loans'] = (int) $s->fetchColumn();

    $s = $pdo->prepare("SELECT COALESCE(SUM(principal_amount), 0) FROM loans WHERE tenant_id = ? AND release_date >= ?");
    $s->execute([$tenant_id, $date_from]);
    $data['disbursed_amount'] = (float) $s->fetchColumn();

    // Loan status breakdown
    $s = $pdo->prepare("SELECT loan_status, COUNT(*) as cnt FROM loans WHERE tenant_id = ? GROUP BY loan_status");
    $s->execute([$tenant_id]);
    $data['loan_status_breakdown'] = $s->fetchAll(PDO::FETCH_ASSOC);

    // Application status breakdown
    $s = $pdo->prepare("SELECT application_status, COUNT(*) as cnt FROM loan_applications WHERE tenant_id = ? GROUP BY application_status");
    $s->execute([$tenant_id]);
    $data['application_status_breakdown'] = $s->fetchAll(PDO::FETCH_ASSOC);

    $data['period'] = $period;
    $data['date_from'] = $date_from;

    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
