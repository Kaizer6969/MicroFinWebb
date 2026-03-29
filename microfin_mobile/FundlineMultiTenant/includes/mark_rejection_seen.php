<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$user_id   = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("UPDATE clients SET seen_rejection_modal = 1 WHERE user_id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $user_id, $tenant_id);
    $stmt->execute();
    $stmt->close();
}
?>
