<?php
/**
 * Get Documents by Loan Purpose API
 * Returns required documents for a specific loan purpose
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/db.php';

$loan_purpose = $_GET['purpose'] ?? '';

$documents = [];
$stmt = $conn->prepare("
    SELECT document_type_id, document_name, description, is_required 
    FROM document_types 
    WHERE is_active = TRUE 
    AND (loan_purpose = ? OR loan_purpose IS NULL)
    ORDER BY is_required DESC, document_name
");
$stmt->bind_param("s", $loan_purpose);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($documents);
?>

