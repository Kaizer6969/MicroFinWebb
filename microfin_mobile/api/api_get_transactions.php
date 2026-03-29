<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'db.php';

$user_id   = intval($_GET['user_id']   ?? 0);
$tenant_id = $_GET['tenant_id']  ?? '';
$search    = trim($_GET['search'] ?? '');
$status    = trim($_GET['status'] ?? ''); // Posted, Paid, All
$method    = trim($_GET['method'] ?? ''); // filter by method
$from_date = trim($_GET['from_date'] ?? '');
$to_date   = trim($_GET['to_date']   ?? '');
$page      = max(1, intval($_GET['page'] ?? 1));
$per_page  = 20;
$offset    = ($page - 1) * $per_page;

if (!$user_id || !$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id or tenant_id.']);
    exit;
}

// Build query with joins to clients + loans to get borrower name
$where = ["p.tenant_id = ?", "c.user_id = ?"];
$params = [$tenant_id, $user_id];
$types  = "si";

if (!empty($search)) {
    $where[] = "(p.payment_reference LIKE ? OR l.loan_number LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}
if (!empty($status) && $status !== 'All') {
    $where[] = "p.payment_status = ?";
    $params[] = $status;
    $types .= "s";
}
if (!empty($method) && $method !== 'All') {
    $where[] = "p.payment_method = ?";
    $params[] = $method;
    $types .= "s";
}
if (!empty($from_date)) {
    $where[] = "p.payment_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if (!empty($to_date)) {
    $where[] = "p.payment_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$whereClause = implode(' AND ', $where);

// Count total for pagination
$countSql = "
    SELECT COUNT(*) as total
    FROM payments p
    JOIN clients  c ON p.client_id = c.client_id
    JOIN loans    l ON p.loan_id   = l.loan_id
    WHERE $whereClause
";
$cStmt = $conn->prepare($countSql);
$cStmt->bind_param($types, ...$params);
$cStmt->execute();
$totalRows = $cStmt->get_result()->fetch_assoc()['total'] ?? 0;
$cStmt->close();

// Main query
$sql = "
    SELECT
        p.payment_id,
        p.payment_reference,
        p.payment_date,
        p.payment_amount,
        p.principal_paid,
        p.interest_paid,
        p.penalty_paid,
        p.payment_method,
        p.payment_reference_number,
        p.payment_status,
        p.created_at,
        l.loan_number,
        l.remaining_balance,
        CONCAT(c.first_name, ' ', c.last_name) AS client_name,
        c.email_address AS client_email
    FROM payments p
    JOIN clients  c ON p.client_id = c.client_id
    JOIN loans    l ON p.loan_id   = l.loan_id
    WHERE $whereClause
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

echo json_encode([
    'success'       => true,
    'transactions'  => $transactions,
    'total'         => (int)$totalRows,
    'page'          => $page,
    'per_page'      => $per_page,
    'total_pages'   => (int)ceil($totalRows / $per_page),
]);
?>
