<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$user_id = $_REQUEST['user_id'] ?? '';
$tenant_id = $_REQUEST['tenant_id'] ?? '';

if (empty($user_id) || empty($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'user_id and tenant_id are required']);
    exit;
}

// 1. Resolve User Name
$user_id_int = (int)$user_id;
$user_name = "User";

// Query both tables to be absolutely sure we get a name
$stmt = $conn->prepare("
    SELECT u.first_name, u.last_name, u.username, c.first_name as c_first, c.last_name as c_last 
    FROM users u 
    LEFT JOIN clients c ON u.user_id = c.user_id AND c.tenant_id = ? 
    WHERE u.user_id = ?
");
$stmt->bind_param("si", $tenant_id, $user_id_int);
$stmt->execute();
$resUser = $stmt->get_result();

if ($resUser->num_rows > 0) {
    $u = $resUser->fetch_assoc();
    
    $fname = trim($u['first_name'] ?? '');
    $lname = trim($u['last_name'] ?? '');
    
    // If users table is empty, try clients table
    if (empty($fname)) $fname = trim($u['c_first'] ?? '');
    if (empty($lname)) $lname = trim($u['c_last'] ?? '');
    
    // Final fallback to username
    if (empty($fname)) $fname = trim($u['username'] ?? '');
    
    if (!empty($fname)) {
        $user_name = $fname . (!empty($lname) ? ' ' . $lname : '');
    }
} else {
    // If the join query failed to find anything, try just the users table as a last resort
    $stmt2 = $conn->prepare("SELECT first_name, last_name, username FROM users WHERE user_id = ?");
    $stmt2->bind_param("i", $user_id_int);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2->num_rows > 0) {
        $u2 = $res2->fetch_assoc();
        $fname = trim($u2['first_name'] ?? '');
        $lname = trim($u2['last_name'] ?? '');
        if (empty($fname)) $fname = trim($u2['username'] ?? '');
        if (!empty($fname)) {
            $user_name = $fname . (!empty($lname) ? ' ' . $lname : '');
        }
    }
    $stmt2->close();
}
$stmt->close();

// 2. Resolve Client/Profile info
$is_profile_complete = false;
$client_code = "";
$verification_status = "Unverified";
$credit_limit = 0;
$client_id = null;

$stmt = $conn->prepare("SELECT client_id, client_code, verification_status, credit_limit FROM clients WHERE user_id = ? AND tenant_id = ?");
$stmt->bind_param("is", $user_id_int, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $client = $res->fetch_assoc();
    $client_id = $client['client_id'];
    $client_code = $client['client_code'];
    $verification_status = $client['verification_status'] ?? "Unverified";
    $credit_limit = floatval($client['credit_limit'] ?? 0);
    $is_profile_complete = true;
}
$stmt->close();

// 3. Resolve Active Loan
$active_loan = null;
if ($client_id) {
    $stmt = $conn->prepare("
        SELECT l.loan_number, l.remaining_balance, l.monthly_amortization, l.next_payment_due, 
               l.total_loan_amount, l.total_paid, l.loan_term_months, 
               p.product_name, p.product_type 
        FROM loans l 
        JOIN loan_products p ON l.product_id = p.product_id 
        WHERE l.client_id = ? AND l.tenant_id = ? AND l.loan_status = 'Active' 
        ORDER BY l.created_at DESC LIMIT 1
    ");
    $stmt->bind_param("is", $client_id, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $active_loan = $res->fetch_assoc();
        $active_loan['remaining_balance'] = (float)$active_loan['remaining_balance'];
        $active_loan['monthly_amortization'] = (float)$active_loan['monthly_amortization'];
        $active_loan['total_loan_amount'] = (float)$active_loan['total_loan_amount'];
        $active_loan['total_paid'] = (float)$active_loan['total_paid'];
        $active_loan['progress'] = $active_loan['total_loan_amount'] > 0 ? ($active_loan['total_paid'] / $active_loan['total_loan_amount']) : 0;
        
        // Count number of payments made
        $stmt2 = $conn->prepare("SELECT COUNT(*) as payments_made FROM amortization_schedule WHERE loan_id = (SELECT loan_id FROM loans WHERE loan_number = ?) AND payment_status = 'Paid'");
        $stmt2->bind_param("s", $active_loan['loan_number']);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $payments = $res2->fetch_assoc();
        $active_loan['payments_made'] = (int)$payments['payments_made'];
        $stmt2->close();
    }
    $stmt->close();
}

// 4. Notifications
$stmt = $conn->prepare("SELECT title, message, notification_type, created_at FROM notifications WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("is", $user_id, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();
$notifications = [];
while($row = $res->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// 5. Featured Products
$stmt = $conn->prepare("SELECT product_id as id, product_name as name, description, interest_rate as rate, min_amount as min, max_amount as max FROM loan_products WHERE tenant_id = ? AND is_active = 1 LIMIT 4");
$stmt->bind_param("s", $tenant_id);
$stmt->execute();
$res = $stmt->get_result();
$featured_products = [];
while($row = $res->fetch_assoc()) {
    $row['rate'] = (float)$row['rate'];
    $row['min'] = (float)$row['min'];
    $row['max'] = (float)$row['max'];
    $featured_products[] = $row;
}
$stmt->close();

echo json_encode([
    'success' => true, 
    'user_name' => $user_name, 
    'client_code' => $client_code, 
    'is_profile_complete' => $is_profile_complete, 
    'verification_status' => $verification_status,
    'credit_limit' => $credit_limit,
    'active_loan' => $active_loan, 
    'notifications' => $notifications,
    'featured_products' => $featured_products
]);
?>
