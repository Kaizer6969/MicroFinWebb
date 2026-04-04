<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$userId = $input['user_id'] ?? null;
$tenantId = $input['tenant_id'] ?? null;
$productId = $input['product_id'] ?? null;
$amount = floatval($input['amount'] ?? 0);
$term = intval($input['term'] ?? 0);
$purposeCat = $input['purpose_category'] ?? '';
$purpose = $input['purpose'] ?? '';
$appData = $input['app_data'] ?? '{}';
$documents = $input['documents'] ?? [];

if (!$userId || !$tenantId || !$productId || $amount <= 0 || $term <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Check if user has an active/pending loan 
$stmt = $conn->prepare("SELECT COUNT(*) FROM loan_applications WHERE client_id = ? AND tenant_id = ? AND application_status NOT IN ('Rejected', 'Cancelled', 'Withdrawn', 'Closed')");
$stmt->bind_param("is", $userId, $tenantId);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();
/*
if ($count > 0) {
    echo json_encode(['success' => false, 'message' => 'You currently have an active or pending loan.']);
    exit;
}
*/

// Fetch Product Interest Rate
$stmt = $conn->prepare("SELECT interest_rate FROM loan_products WHERE product_id = ? AND tenant_id = ?");
$stmt->bind_param("is", $productId, $tenantId);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

$interestRate = $product ? floatval($product['interest_rate']) : 0.0;
$appStatus = 'Submitted';
$appNum = 'APP-' . strtoupper(bin2hex(random_bytes(4)));
$today = date('Y-m-d H:i:s');

$conn->begin_transaction();

try {
    // Insert Loan Application
    $stmt = $conn->prepare("INSERT INTO loan_applications (application_number, client_id, tenant_id, product_id, requested_amount, loan_term_months, interest_rate, purpose_category, loan_purpose, application_data, application_status, submitted_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisiiddsssss", $appNum, $userId, $tenantId, $productId, $amount, $term, $interestRate, $purposeCat, $purpose, $appData, $appStatus, $today);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert loan application: " . $stmt->error);
    }
    
    $appId = $conn->insert_id;
    $stmt->close();

    // Insert Documents if any exist
    if (!empty($documents) && is_array($documents)) {
        $stmtDoc = $conn->prepare("INSERT INTO application_documents (application_id, tenant_id, document_type_id, file_name, file_path) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmtDoc) {
            foreach ($documents as $doc) {
                $docTypeId = $doc['document_type_id'];
                $fileName = $doc['file_name'];
                $filePath = $doc['file_path'];
                $stmtDoc->bind_param("isiss", $appId, $tenantId, $docTypeId, $fileName, $filePath);
                @$stmtDoc->execute();
            }
            $stmtDoc->close();
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'application_number' => $appNum]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
