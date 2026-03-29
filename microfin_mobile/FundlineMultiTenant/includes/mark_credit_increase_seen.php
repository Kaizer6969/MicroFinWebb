<?php
/**
 * Mark Credit Increase as Seen
 * Updates the last_seen_credit_limit when user acknowledges the modal
 */

session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$credit_limit = floatval($data['credit_limit'] ?? 0);

if ($credit_limit <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid credit limit']);
    exit();
}

$stmt = $conn->prepare("UPDATE clients SET last_seen_credit_limit = ? WHERE user_id = ?");
$stmt->bind_param("di", $credit_limit, $_SESSION['user_id']);
$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => $success]);
?>
