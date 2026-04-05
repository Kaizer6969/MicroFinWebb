<?php
header('Content-Type: application/json');
require_once 'microfin_platform/backend/db_connect.php';

try {
    $stmt1 = $pdo->query("DESCRIBE email_delivery_logs");
    $email_logs = $stmt1->fetchAll();

    $stmt2 = $pdo->query("DESCRIBE mobile_install_attributions");
    $mobile_logs = $stmt2->fetchAll();

    echo json_encode([
        'status' => 'success',
        'email_delivery_logs' => $email_logs,
        'mobile_install_attributions' => $mobile_logs
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
