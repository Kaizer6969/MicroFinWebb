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

$user_id    = $data['user_id']    ?? null;
$tenant_id  = $data['tenant_id']  ?? null;

if (empty($user_id) || empty($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']); exit;
}

// Personal info
// email is fetched from users table later
$phone      = $data['phone_number']      ?? '';
$dob        = $data['date_of_birth']     ?? date('Y-m-d', strtotime('-25 years'));
$gender     = $data['gender']            ?? 'Male';
$civil      = $data['civil_status']      ?? 'Single';
$emp_status = $data['employment_status'] ?? 'Employed';
$occupation = $data['occupation']        ?? '';
$employer   = $data['employer_name']     ?? '';
$emp_contact= $data['employer_contact']  ?? '';
$income     = $data['monthly_income']    ?? 0;

// Address
$p_house   = $data['present_house_no']     ?? '';
$p_street  = $data['present_street']       ?? '';
$p_brgy    = $data['present_barangay']     ?? '';
$p_city    = $data['present_city']         ?? '';
$p_prov    = $data['present_province']     ?? '';
$p_postal  = $data['present_postal_code']  ?? '';
$same      = $data['same_as_present']      ?? false;
$sameInt   = $same ? 1 : 0;
$pm_house  = $data['permanent_house_no']   ?? $p_house;
$pm_street = $data['permanent_street']     ?? $p_street;
$pm_brgy   = $data['permanent_barangay']   ?? $p_brgy;
$pm_city   = $data['permanent_city']       ?? $p_city;
$pm_prov   = $data['permanent_province']   ?? $p_prov;
$pm_postal = $data['permanent_postal_code']?? $p_postal;

// Co-maker
$co_name     = $data['comaker_name']       ?? '';
$co_rel      = $data['comaker_relationship']?? '';
$co_contact  = $data['comaker_contact']    ?? '';
$co_income   = $data['comaker_income']     ?? 0;
$co_address  = $data['comaker_address']    ?? '';

$documents   = $data['documents'] ?? [];

$conn->begin_transaction();

try {
    // 1. Get or Create client
    $stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ? AND tenant_id = ?");
    $stmt->bind_param("is", $user_id, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    // Always fetch email from users table to prevent user tampering and because it's removed from verification screen
    $email = '';
    $first_name = 'Unknown';
    $last_name  = 'User';
    $uStmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE user_id = ?");
    $uStmt->bind_param("i", $user_id);
    $uStmt->execute();
    $uRes = $uStmt->get_result();
    if ($uRes->num_rows > 0) {
        $uRow = $uRes->fetch_assoc();
        if (!empty($uRow['first_name'])) $first_name = $uRow['first_name'];
        if (!empty($uRow['last_name'])) $last_name = $uRow['last_name'];
        if (!empty($uRow['email'])) $email = $uRow['email'];
    }
    $uStmt->close();

    if ($res->num_rows === 0) {

        $client_code = strtoupper(substr($tenant_id, 0, 3)) . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
        $today = date('Y-m-d');

        $ins = $conn->prepare("INSERT INTO clients (user_id, tenant_id, client_code, first_name, last_name, date_of_birth, contact_number, email_address, gender, civil_status, present_house_no, present_street, present_barangay, present_city, present_province, present_postal_code, permanent_house_no, permanent_street, permanent_barangay, permanent_city, permanent_province, permanent_postal_code, same_as_present, employment_status, occupation, employer_name, employer_contact, monthly_income, comaker_name, comaker_relationship, comaker_contact, comaker_income, document_verification_status, registration_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending',?)");
        $ins->bind_param("isssssssssssssssssssssissssdsssds", $user_id, $tenant_id, $client_code, $first_name, $last_name, $dob, $phone, $email, $gender, $civil, $p_house, $p_street, $p_brgy, $p_city, $p_prov, $p_postal, $pm_house, $pm_street, $pm_brgy, $pm_city, $pm_prov, $pm_postal, $sameInt, $emp_status, $occupation, $employer, $emp_contact, $income, $co_name, $co_rel, $co_contact, $co_income, $today);
        $ins->execute();
        $client_id = $conn->insert_id;
        $ins->close();
    } else {
        $client_id = $res->fetch_assoc()['client_id'];
        $upd = $conn->prepare("UPDATE clients SET 
            contact_number=?, email_address=?, gender=?, civil_status=?,
            present_house_no=?, present_street=?, present_barangay=?, present_city=?, present_province=?, present_postal_code=?,
            permanent_house_no=?, permanent_street=?, permanent_barangay=?, permanent_city=?, permanent_province=?, permanent_postal_code=?,
            same_as_present=?, employment_status=?, occupation=?, employer_name=?, employer_contact=?, monthly_income=?,
            comaker_name=?, comaker_relationship=?, comaker_contact=?, comaker_income=?, document_verification_status='Pending'
            WHERE client_id=?");
        $upd->bind_param("ssssssssssssssssissssdsssdi",
            $phone, $email, $gender, $civil,
            $p_house, $p_street, $p_brgy, $p_city, $p_prov, $p_postal,
            $pm_house, $pm_street, $pm_brgy, $pm_city, $pm_prov, $pm_postal,
            $sameInt, $emp_status, $occupation, $employer, $emp_contact, $income,
            $co_name, $co_rel, $co_contact, $co_income,
            $client_id);
        $upd->execute();
        $upd->close();
    }
    $stmt->close();

    // 2. Save KYC documents to client_documents table
    if (!empty($documents)) {
        $dStmt = $conn->prepare("INSERT INTO client_documents (client_id, tenant_id, document_type_id, file_name, file_path) VALUES (?, ?, ?, ?, ?)");
        foreach ($documents as $doc) {
            $doc_type_id = $doc['document_type_id'];
            $file_name   = $doc['file_name'];
            $file_path   = $doc['file_path'];
            
            // Delete existing document of same type
            $conn->query("DELETE FROM client_documents WHERE client_id = $client_id AND document_type_id = $doc_type_id");
            
            $dStmt->bind_param("isiss", $client_id, $tenant_id, $doc_type_id, $file_name, $file_path);
            $dStmt->execute();
        }
        $dStmt->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Profile updated and submitted for review.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
