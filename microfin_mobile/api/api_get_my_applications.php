<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$user_id = $_GET['user_id'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($user_id) || empty($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'user_id and tenant_id are required']);
    exit;
}

// Get client info
$stmt = $conn->prepare("SELECT client_id, client_code, registration_date FROM clients WHERE user_id = ? AND tenant_id = ?");
$stmt->bind_param("is", $user_id, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => true, 'total' => 0, 'client_code' => '', 'member_since' => '']);
    exit;
}

$client = $res->fetch_assoc();
$client_id = $client['client_id'];
$client_code = $client['client_code'] ?? '';

// Format member since
$member_since = '';
if (!empty($client['registration_date'])) {
    try {
        $date = new DateTime($client['registration_date']);
        $member_since = $date->format('F Y');
    } catch (Exception $e) {
        $member_since = $client['registration_date'];
    }
}
$stmt->close();

// Count applications
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM loan_applications WHERE client_id = ? AND tenant_id = ?");
$stmt->bind_param("is", $client_id, $tenant_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$total = (int)$row['total'];
$stmt->close();

echo json_encode([
    'success' => true,
    'total' => $total,
    'client_code' => $client_code,
    'member_since' => $member_since
]);
?>
