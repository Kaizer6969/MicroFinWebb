<?php
header('Content-Type: application/json');
require_once 'db.php';

// If limit param passed, update it
if (isset($_GET['set_user_id']) && isset($_GET['limit'])) {
    $uid   = (int)$_GET['set_user_id'];
    $limit = (float)$_GET['limit'];
    $stmt = $conn->prepare("UPDATE clients SET credit_limit = ? WHERE user_id = ?");
    $stmt->bind_param("di", $limit, $uid);
    $stmt->execute();
    echo json_encode(['action' => 'update', 'user_id' => $uid, 'limit' => $limit, 'affected' => $stmt->affected_rows]);
    exit;
}

// List all clients
$res = $conn->query("SELECT client_id, user_id, tenant_id, first_name, last_name, credit_limit, verification_status FROM clients");
$rows = [];
while ($row = $res->fetch_assoc()) {
    $row['credit_limit'] = (float)$row['credit_limit'];
    $rows[] = $row;
}
echo json_encode(['clients' => $rows]);
?>
