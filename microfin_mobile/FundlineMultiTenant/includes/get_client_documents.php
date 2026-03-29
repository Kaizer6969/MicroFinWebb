<?php
/**
 * Get Client Documents API
 * Returns documents for a specific client
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Employee') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/db.php';

$client_id = intval($_GET['client_id'] ?? 0);

// Get client profile
$profile = null;
$stmt_profile = $conn->prepare("SELECT * FROM clients WHERE client_id = ?");
$stmt_profile->bind_param("i", $client_id);
$stmt_profile->execute();
$res_profile = $stmt_profile->get_result();
if ($res_profile->num_rows > 0) {
    $profile = $res_profile->fetch_assoc();
}
$stmt_profile->close();

$documents = [];
$stmt = $conn->prepare("
    SELECT cd.*, dt.document_name 
    FROM client_documents cd
    JOIN document_types dt ON cd.document_type_id = dt.document_type_id
    WHERE cd.client_id = ?
    ORDER BY cd.upload_date DESC
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Fix file path if it's just a filename (legacy issue)
    $file_path = $row['file_path'];
    
    // If path doesn't start with ../ or /, it's just a filename - prepend upload directory
    if (!str_starts_with($file_path, '../') && !str_starts_with($file_path, '/') && !str_starts_with($file_path, 'uploads/')) {
        $file_path = '../uploads/documents/' . $file_path;
    }
    
    $documents[] = [
        'document_name' => $row['document_name'],
        'file_name' => $row['file_name'],
        'file_path' => $file_path,
        'upload_date' => date('M d, Y', strtotime($row['upload_date'])),
        'document_type_id' => $row['document_type_id'] // Add for debugging
    ];
}

$stmt->close();
$conn->close();

// Add error info if no documents found
$response = [
    'profile' => $profile, 
    'documents' => $documents,
    'debug' => [
        'client_id' => $client_id,
        'document_count' => count($documents),
        'has_profile' => $profile !== null
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
?>

