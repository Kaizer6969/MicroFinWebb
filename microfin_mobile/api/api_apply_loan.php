<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']); exit;
}

require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

// Required
$user_id    = $data['user_id']    ?? null;
$tenant_id  = $data['tenant_id']  ?? null;
$product_id = $data['product_id'] ?? null;
$amount     = floatval($data['amount'] ?? 0);
$term       = intval($data['term'] ?? 0);
$category   = $data['purpose_category'] ?? '';
$purpose    = $data['purpose']    ?? '';
$documents  = $data['documents']  ?? [];
$app_data   = $data['app_data']   ?? '{}'; // JSON string for dynamic purpose fields

if (empty($user_id) || empty($tenant_id) || empty($product_id) || $amount <= 0 || $term <= 0) {
    echo json_encode(['success' => false, 'message' => 'Required application details are missing']); exit;
}

$conn->begin_transaction();

try {
    // 1. Get client info and verify status
    $stmt = $conn->prepare("SELECT client_id, verification_status, document_verification_status, credit_limit, comaker_name, comaker_relationship, comaker_contact, comaker_income, comaker_house_no, comaker_street, comaker_barangay, comaker_city, comaker_province, comaker_postal_code FROM clients WHERE user_id = ? AND tenant_id = ?");
    $stmt->bind_param("is", $user_id, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception("Profile not verified. Please complete verification first.");
    }
    
    $client = $res->fetch_assoc();
    $client_id = $client['client_id'];
    
    // Check the admin-controlled verification_status (set when admin clicks "Verify Client")
    if ($client['verification_status'] !== 'Approved') {
        throw new Exception("Your profile must be Approved before applying for a loan.");
    }

    $credit_limit = floatval($client['credit_limit']);

    $stmt->close();


    // 2. Credit limit check
    $used_credit = 0;

    // Active / Overdue / Restructured loans consume credit
    $lStmt = $conn->prepare("SELECT COALESCE(SUM(principal_amount), 0) AS total FROM loans WHERE client_id = ? AND loan_status IN ('Active', 'Overdue', 'Restructured')");
    $lStmt->bind_param("i", $client_id);
    $lStmt->execute();
    $lRes = $lStmt->get_result();
    if ($lRow = $lRes->fetch_assoc()) $used_credit += floatval($lRow['total']);
    $lStmt->close();

    // Pending / in-review applications also consume credit
    $aStmt = $conn->prepare("SELECT COALESCE(SUM(requested_amount), 0) AS total FROM loan_applications WHERE client_id = ? AND application_status IN ('Submitted', 'Pending', 'Under Review', 'Document Verification', 'Credit Investigation', 'For Approval')");
    $aStmt->bind_param("i", $client_id);
    $aStmt->execute();
    $aRes = $aStmt->get_result();
    if ($aRow = $aRes->fetch_assoc()) $used_credit += floatval($aRow['total']);
    $aStmt->close();

    $available_credit = $credit_limit - $used_credit;
    if ($credit_limit > 0 && $amount > $available_credit) {
        throw new Exception("Requested amount (₱" . number_format($amount, 2) . ") exceeds your available credit limit of ₱" . number_format(max(0, $available_credit), 2) . ".");
    }



    // 3. Get product interest rate
    $pStmt = $conn->prepare("SELECT interest_rate, product_type FROM loan_products WHERE product_id = ?");
    $pStmt->bind_param("i", $product_id);
    $pStmt->execute();
    $pRes = $pStmt->get_result();
    if ($pRes->num_rows === 0) throw new Exception("Selected loan product not found.");
    $pRow = $pRes->fetch_assoc();
    $interest_rate = floatval($pRow['interest_rate']);
    $product_type = $pRow['product_type'];
    $pStmt->close();

    // Check duplicate pending identical product type
    $dupStmt = $conn->prepare("SELECT la.application_id FROM loan_applications la JOIN loan_products lp ON la.product_id = lp.product_id WHERE la.client_id = ? AND lp.product_type = ? AND la.application_status IN ('Submitted', 'Pending', 'Under Review', 'Document Verification', 'Credit Investigation', 'For Approval')");
    $dupStmt->bind_param("is", $client_id, $product_type);
    $dupStmt->execute();
    if ($dupStmt->get_result()->num_rows > 0) throw new Exception("You already have a pending application for this loan type.");
    $dupStmt->close();

    // 4. Generate application number
    $app_number = strtoupper($tenant_id) . '-' . date('YmdHi') . '-' . str_pad($client_id, 4, '0', STR_PAD_LEFT);

    // 5. Insert loan application
    $co_name = $client['comaker_name'];
    $has_co = !empty($co_name) ? 1 : 0;
    $co_address = trim($client['comaker_house_no'] . ' ' . $client['comaker_street'] . ' ' . $client['comaker_barangay'] . ' ' . $client['comaker_city']);

    $iaStmt = $conn->prepare("INSERT INTO loan_applications (application_number, client_id, tenant_id, product_id, requested_amount, loan_term_months, interest_rate, purpose_category, loan_purpose, application_data, application_status, submitted_date, has_comaker, comaker_name, comaker_relationship, comaker_contact, comaker_address, comaker_income) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', NOW(), ?, ?, ?, ?, ?, ?)");
    $iaStmt->bind_param("sisidissssissssd", $app_number, $client_id, $tenant_id, $product_id, $amount, $term, $interest_rate, $category, $purpose, $app_data, $has_co, $co_name, $client['comaker_relationship'], $client['comaker_contact'], $co_address, $client['comaker_income']);
    if (!$iaStmt->execute()) throw new Exception("Failed to save loan application: " . $iaStmt->error);
    $application_id = $conn->insert_id;
    $iaStmt->close();

    // 6. Insert Purpose-specific documents
    if (!empty($documents)) {
        $dStmt = $conn->prepare("INSERT INTO application_documents (application_id, tenant_id, document_type_id, file_name, file_path) VALUES (?, ?, ?, ?, ?)");
        foreach ($documents as $doc) {
            $doc_type_id = $doc['document_type_id'];
            $file_name   = $doc['file_name'];
            $file_path   = $doc['file_path'];
            $dStmt->bind_param("isiss", $application_id, $tenant_id, $doc_type_id, $file_name, $file_path);
            $dStmt->execute();
        }
        $dStmt->close();
    }

    // 7. Insert notification for user
    $notif_title   = 'Loan Application Submitted';
    $notif_message = "Your application $app_number for $product_type has been submitted and is under review.";
    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, tenant_id, notification_type, title, message, priority) VALUES (?, ?, 'General', ?, ?, 'High')");
    $nStmt->bind_param("isss", $user_id, $tenant_id, $notif_title, $notif_message);
    $nStmt->execute();
    $nStmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Loan application submitted successfully!', 'application_id' => $application_id, 'application_number' => $app_number]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
