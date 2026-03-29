<?php
/**
 * Document Submission Handler
 * Handles the step-by-step document submission process
 */

// CRITICAL: Set headers first to prevent hosting provider from injecting HTML
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

ob_start(); // Start output buffering to catch any warnings/errors
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Get client_id
$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$client_data = $result->fetch_assoc();
$client_id = $client_data['client_id'];
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for Post Max Size violation
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
        ob_clean();
        echo json_encode(['success' => false, 'message' => "Total file size too large! Server limit is $max_size. Please compress your images."]);
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'submit_documents') {
        // First, update profile if fields are provided
        if (isset($_POST['first_name'])) {
            $first_name = trim($_POST['first_name']);
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name']);
            $suffix = trim($_POST['suffix'] ?? '');
            $date_of_birth = $_POST['date_of_birth'];
            $gender = $_POST['gender'];
            $civil_status = $_POST['civil_status'];
            $contact_number = trim($_POST['contact_number']);
            $alternate_contact = trim($_POST['alternate_contact'] ?? '');
            $present_house_no = trim($_POST['present_house_no']);
            $present_street = trim($_POST['present_street']);
            $present_barangay = trim($_POST['present_barangay']);
            $present_city = trim($_POST['present_city']);
            $present_province = trim($_POST['present_province']);
            $present_postal_code = trim($_POST['present_postal_code']);
            $employment_status = $_POST['employment_status'];
            $employer_name = trim($_POST['employer_name'] ?? '');
            $occupation = trim($_POST['occupation'] ?? '');
            $monthly_income = floatval($_POST['monthly_income']);
            $id_type = trim($_POST['id_type'] ?? '');
            
            $sql = "UPDATE clients SET 
                first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
                date_of_birth = ?, gender = ?, civil_status = ?, 
                contact_number = ?, alternate_contact = ?,
                present_house_no = ?, present_street = ?, present_barangay = ?,
                present_city = ?, present_province = ?, present_postal_code = ?,
                employment_status = ?, employer_name = ?, occupation = ?, monthly_income = ?,
                id_type = ?
                WHERE client_id = ?";
            
            $update_stmt = $conn->prepare($sql);
            
            if (!$update_stmt) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                exit;
            }

            $update_stmt->bind_param("ssssssssssssssssssdsi", 
                $first_name, $middle_name, $last_name, $suffix,
                $date_of_birth, $gender, $civil_status,
                $contact_number, $alternate_contact,
                $present_house_no, $present_street, $present_barangay,
                $present_city, $present_province, $present_postal_code,
                $employment_status, $employer_name, $occupation, $monthly_income,
                $id_type,
                $client_id);
            
            if (!$update_stmt->execute()) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Profile update failed: ' . $update_stmt->error]);
                exit;
            }
            $update_stmt->close();
        }
        
        // Handle document uploads
        $client_upload_dir = '../uploads/client_documents/' . $client_id . '/';
        
        // Ensure folder exists
        if (!file_exists($client_upload_dir)) {
            if (!@mkdir($client_upload_dir, 0777, true)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Cannot create upload folder. Please contact administrator.']);
                exit;
            }
        }
        
        $required_docs = ['proof_of_income', 'proof_of_address', 'valid_id_front', 'valid_id_back'];
        $uploaded_count = 0;
        $errors = [];
        
        foreach ($required_docs as $doc_key) {
            if (!isset($_FILES[$doc_key])) continue;
            
            if ($_FILES[$doc_key]['error'] == 0) {
                $filename = $_FILES[$doc_key]['name'];
                $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
                $new_filename = $doc_key . '_' . time() . '.' . $file_ext;
                $file_path = $client_upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES[$doc_key]['tmp_name'], $file_path)) {
                    // Map form field names to database document names (EXACT match from fundline_revised_schema.sql)
                    $doc_name_map = [
                        'proof_of_income' => 'Proof of Income',
                        'proof_of_address' => 'Proof of Billing',
                        'valid_id_front' => 'Valid ID Front',
                        'valid_id_back' => 'Valid ID Back'
                    ];
                    
                    $stmt = $conn->prepare("SELECT document_type_id FROM document_types WHERE document_name = ?");
                    $stmt->bind_param("s", $doc_name_map[$doc_key]);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $doc_type = $result->fetch_assoc();
                    $stmt->close();
                    
                    // FIX: Check if document type was found
                    if (!$doc_type) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => "Document type '{$doc_name_map[$doc_key]}' not found in database. Please run fix_document_names.sql"]);
                        exit;
                    }
                    
                    $doc_type_id = $doc_type['document_type_id'];
                    
                    // Check if document already exists
                    $check_stmt = $conn->prepare("SELECT client_document_id FROM client_documents WHERE client_id = ? AND document_type_id = ?");
                    $check_stmt->bind_param("ii", $client_id, $doc_type_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        // Update existing
                        $update_stmt = $conn->prepare("UPDATE client_documents SET file_name = ?, file_path = ?, upload_date = NOW() WHERE client_id = ? AND document_type_id = ?");
                        $update_stmt->bind_param("ssii", $new_filename, $file_path, $client_id, $doc_type_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    } else {
                        // Insert new
                        $insert_stmt = $conn->prepare("INSERT INTO client_documents (client_id, document_type_id, file_name, file_path) VALUES (?, ?, ?, ?)");
                        $insert_stmt->bind_param("iiss", $client_id, $doc_type_id, $new_filename, $file_path);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                    $check_stmt->close();
                    $uploaded_count++;
                } else {
                    $errors[] = "Failed to save $doc_key";
                }
            } else {
                // Handle upload errors
                $err_code = $_FILES[$doc_key]['error'];
                if ($err_code != 4) {
                    $err_msg = "Unknown error";
                    switch ($err_code) {
                        case 1: $err_msg = "File too large (server limit)"; break;
                        case 2: $err_msg = "File too large (form limit)"; break;
                        case 3: $err_msg = "File only partially uploaded"; break;
                    }
                    $errors[] = "$doc_key: $err_msg";
                }
            }
        }
        
        if ($uploaded_count === 4) {
            // Update client verification status to Pending
            $update_stmt = $conn->prepare("UPDATE clients SET document_verification_status = 'Pending' WHERE client_id = ?");
            $update_stmt->bind_param("i", $client_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $response['success'] = true;
            $response['message'] = 'Documents submitted successfully! Your documents are now under review.';
        } else {
            if (!empty($errors)) {
                $response['message'] = 'Upload errors: ' . implode(', ', $errors);
            } else {
                $response['message'] = 'Please upload all 4 required documents (' . $uploaded_count . ' received).';
            }
        }
    }
}

ob_clean();
echo json_encode($response);
exit;
?>

