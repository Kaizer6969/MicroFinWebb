<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$user_id = $_GET['user_id'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($user_id) || empty($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$profile = [
    'email' => '',
    'phone_number' => '',
    'date_of_birth' => '',
    'member_since' => '',
    'client_code' => '',
    'documents' => []
];

$email = '';
$phone = '';
$dob = '';
$client_code = '';

// Get user info
$stmt = $conn->prepare("SELECT email, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$uRes = $stmt->get_result();
if ($uRes->num_rows > 0) {
    while($r = $uRes->fetch_assoc()){
        $profile['email'] = $r['email'] ?? '';
        $profile['member_since'] = date('M Y', strtotime($r['created_at']));
    }
}
$stmt->close();

// Get client info
$client_id = null;
$stmt = $conn->prepare("SELECT client_id, client_code, contact_number, date_of_birth FROM clients WHERE user_id = ? AND tenant_id = ?");
if ($stmt) {
    $stmt->bind_param("is", $user_id, $tenant_id);
    $stmt->execute();
    $cRes = $stmt->get_result();
    if ($cRes->num_rows > 0) {
        $r = $cRes->fetch_assoc();
        $client_id = $r['client_id'];
        $profile['client_code'] = $r['client_code'] ?? '';
        if (!empty($r['contact_number'])) $profile['phone_number'] = $r['contact_number'];
        if (!empty($r['date_of_birth'])) $profile['date_of_birth'] = $r['date_of_birth'];
    }
    $stmt->close();
}

// Get documents query - we have document_types and client_documents
$documents = [];
if ($client_id) {
    // left join so we see missing ones too
    $stmt = $conn->prepare("
        SELECT dt.document_name, cd.file_path, cd.verification_status as doc_status 
        FROM document_types dt 
        LEFT JOIN client_documents cd ON dt.document_type_id = cd.document_type_id AND cd.client_id = ?
        WHERE dt.tenant_id = ? AND dt.is_required = 1
    ");
    if ($stmt) {
        $stmt->bind_param("is", $client_id, $tenant_id);
        $stmt->execute();
        $dRes = $stmt->get_result();
        while ($d = $dRes->fetch_assoc()) {
            $status = 'Missing';
            if (!empty($d['file_path'])) {
                $status = $d['doc_status'] ?? 'Pending';
            }
            $documents[] = [
                'name' => $d['document_name'],
                'status' => $status
            ];
        }
        $stmt->close();
    }
}
$profile['documents'] = $documents;

echo json_encode(['success' => true, 'profile' => $profile]);
?>
