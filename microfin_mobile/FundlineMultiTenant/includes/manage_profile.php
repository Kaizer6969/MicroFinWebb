<?php
/**
 * Client Manage Profile Page
 * Allows clients to update their personal information
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Client') {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$avatar_letter = strtoupper(substr($username, 0, 1));

$update_success = false;
$update_error = '';

// Get client_id
$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$client_result = $result->fetch_assoc();
$stmt->close();

if (!$client_result) {
        // Client record not found for this user
        echo "<div style='padding:20px; text-align:center;'>
                <h2>Profile Error</h2>
                <p>We could not find your client profile data.</p>
                <a href='logout.php'>Logout</a>
              </div>";
        exit();
}

$client_id = $client_result['client_id'];

// Get client data
$stmt = $conn->prepare("SELECT c.*, u.email FROM clients c JOIN users u ON c.user_id = u.user_id WHERE c.client_id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client_data = $result->fetch_assoc();
$stmt->close();

// Get document types and existing documents
$document_types = [];
// Get document types (Only specific required ones)
$target_docs = ["'Proof of Income'", "'Proof of Address'", "'Valid ID Front'", "'Valid ID Back'"];
$doc_list = implode(',', $target_docs);
$stmt = $conn->prepare("SELECT * FROM document_types WHERE is_active = 1 AND document_name IN ($doc_list) ORDER BY document_name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $document_types[] = $row;
}
$stmt->close();

$existing_documents = [];
$stmt = $conn->prepare("SELECT * FROM client_documents WHERE client_id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $existing_documents[$row['document_type_id']] = $row;
}
$stmt->close();

// Profile Completion & Stats Logic
$required_fields = [
    'first_name', 'last_name', 'date_of_birth', 'gender', 'civil_status', 
    'contact_number', 'present_house_no', 'present_barangay', 
    'present_city', 'present_province', 'employment_status', 'monthly_income'
];

$filled_count = 0;
foreach ($required_fields as $field) {
    if (!empty($client_data[$field])) {
        $filled_count++;
    }
}
$completion_percent = round(($filled_count / count($required_fields)) * 100);



// Get Active Loans Count
$active_loans_count = 0;
$al_stmt = $conn->prepare("SELECT COUNT(*) as count FROM loans WHERE client_id = ? AND loan_status IN ('Active', 'Past Due')");
$al_stmt->bind_param("i", $client_id);
$al_stmt->execute();
$active_loans_count = $al_stmt->get_result()->fetch_assoc()['count'];
$al_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'];
    $suffix = $_POST['suffix'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $gender = $_POST['gender'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $contact_number = $_POST['contact_number'];
    $alternate_contact = $_POST['alternate_contact'] ?? '';
    $email_address = $_POST['email_address'] ?? '';
    
    // Present address
    $present_house_no = $_POST['present_house_no'] ?? '';
    $present_street = $_POST['present_street'] ?? '';
    $present_barangay = $_POST['present_barangay'] ?? '';
    $present_city = $_POST['present_city'] ?? '';
    $present_province = $_POST['present_province'] ?? '';
    $present_postal_code = $_POST['present_postal_code'] ?? '';
    
    // Permanent address
    $same_as_present = isset($_POST['same_as_present']) ? 1 : 0;
    $permanent_house_no = $same_as_present ? $present_house_no : ($_POST['permanent_house_no'] ?? '');
    $permanent_street = $same_as_present ? $present_street : ($_POST['permanent_street'] ?? '');
    $permanent_barangay = $same_as_present ? $present_barangay : ($_POST['permanent_barangay'] ?? '');
    $permanent_city = $same_as_present ? $present_city : ($_POST['permanent_city'] ?? '');
    $permanent_province = $same_as_present ? $present_province : ($_POST['permanent_province'] ?? '');
    $permanent_postal_code = $same_as_present ? $present_postal_code : ($_POST['permanent_postal_code'] ?? '');
    
    // Co-maker information
    $comaker_name = $_POST['comaker_name'] ?? '';
    $comaker_relationship = $_POST['comaker_relationship'] ?? '';
    $comaker_contact = $_POST['comaker_contact'] ?? '';
    $comaker_income = $_POST['comaker_income'] ?? null;
    $comaker_house_no = $_POST['comaker_house_no'] ?? '';
    $comaker_street = $_POST['comaker_street'] ?? '';
    $comaker_barangay = $_POST['comaker_barangay'] ?? '';
    $comaker_city = $_POST['comaker_city'] ?? '';
    $comaker_province = $_POST['comaker_province'] ?? '';
    $comaker_postal_code = $_POST['comaker_postal_code'] ?? '';

    $stmt = $conn->prepare("UPDATE clients SET 
        first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
        date_of_birth = ?, gender = ?, civil_status = ?,
        contact_number = ?, alternate_contact = ?, email_address = ?,
        present_house_no = ?, present_street = ?, present_barangay = ?,
        present_city = ?, present_province = ?, present_postal_code = ?,
        permanent_house_no = ?, permanent_street = ?, permanent_barangay = ?,
        permanent_city = ?, permanent_province = ?, permanent_postal_code = ?,
        same_as_present = ?,
        employment_status = ?, occupation = ?, employer_name = ?,
        employer_contact = ?, employer_address = ?,
        monthly_income = ?, other_income_source = ?, other_income_amount = ?,
        comaker_name = ?, comaker_relationship = ?, comaker_contact = ?, comaker_income = ?,
        comaker_house_no = ?, comaker_street = ?, comaker_barangay = ?,
        comaker_city = ?, comaker_province = ?, comaker_postal_code = ?
        WHERE client_id = ?");
    
    $stmt->bind_param("ssssssssssssssssssssssissssdsdsssdsssssssi",
        $first_name, $middle_name, $last_name, $suffix,
        $date_of_birth, $gender, $civil_status,
        $contact_number, $alternate_contact, $email_address,
        $present_house_no, $present_street, $present_barangay,
        $present_city, $present_province, $present_postal_code,
        $permanent_house_no, $permanent_street, $permanent_barangay,
        $permanent_city, $permanent_province, $permanent_postal_code,
        $same_as_present,
        $employment_status, $occupation, $employer_name,
        $employer_contact, $employer_address,
        $monthly_income, $other_income_source, $other_income_amount,
        $comaker_name, $comaker_relationship, $comaker_contact, $comaker_income,
        $comaker_house_no, $comaker_street, $comaker_barangay, 
        $comaker_city, $comaker_province, $comaker_postal_code,
        $client_id
    );
    
    if ($stmt->execute()) {
        $update_success = true;
        // Refresh client data
        $stmt->close();
        $stmt = $conn->prepare("SELECT c.*, u.email FROM clients c JOIN users u ON c.user_id = u.user_id WHERE c.client_id = ?");
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $client_data = $result->fetch_assoc();
    } else {
        $update_error = "Failed to update profile. Please try again.";
    }
    $stmt->close();
    
    // Handle Document Uploads
    $client_upload_dir = '../uploads/client_documents/' . $client_id . '/';
    
    // Create directory with error checking
    if (!file_exists($client_upload_dir)) {
        if (!mkdir($client_upload_dir, 0777, true)) {
            $update_error = "Error: Failed to create upload directory. Please ask admin to check folder permissions for valid 'uploads' folder.";
        }
    }
    
    if (!$update_error) {
        $uploaded_any = false;
        foreach ($document_types as $dt) {
            $key = 'document_' . $dt['document_type_id'];
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] == 0) {
                $filename = $_FILES[$key]['name'];
                $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // Allow only specific types
                $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
                if (!in_array($file_ext, $allowed_types)) {
                    $update_error = "Error: File type '$file_ext' not allowed. Please upload JPG, PNG, or PDF.";
                    break;
                }

                $new_filename = 'doc_' . $dt['document_type_id'] . '_' . time() . '.' . $file_ext;
                $file_path = $client_upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES[$key]['tmp_name'], $file_path)) {
                    // Check if exists
                    $check_stmt = $conn->prepare("SELECT client_document_id FROM client_documents WHERE client_id = ? AND document_type_id = ?");
                    $check_stmt->bind_param("ii", $client_id, $dt['document_type_id']);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows > 0) {
                        $upd = $conn->prepare("UPDATE client_documents SET file_name = ?, file_path = ?, upload_date = NOW() WHERE client_id = ? AND document_type_id = ?");
                        $upd->bind_param("ssii", $new_filename, $file_path, $client_id, $dt['document_type_id']);
                        $upd->execute();
                        $upd->close();
                    } else {
                        $ins = $conn->prepare("INSERT INTO client_documents (client_id, document_type_id, file_name, file_path) VALUES (?, ?, ?, ?)");
                        $ins->bind_param("iiss", $client_id, $dt['document_type_id'], $new_filename, $file_path);
                        $ins->execute();
                        $ins->close();
                    }
                    $check_stmt->close();
                    $uploaded_any = true;
                    $update_success = true;
                    
                     // Update the $existing_documents array so the view reflects changes immediately
                     $existing_documents[$dt['document_type_id']] = [
                        'file_name' => $new_filename,
                        'file_path' => $file_path
                     ];
                } else {
                    $update_error = "Error: Failed to save file. Check folder permissions.";
                }
            } elseif (isset($_FILES[$key]) && $_FILES[$key]['error'] != 4) {
                // If error is not 4 (No file uploaded)
                $update_error = "Upload error code: " . $_FILES[$key]['error'];
            }
        }

        // If at least one document was uploaded, switch status to Pending (Under Review)
        if ($uploaded_any && $client_data['document_verification_status'] === 'Unverified') {
            $status_upd = $conn->prepare("UPDATE clients SET document_verification_status = 'Pending' WHERE client_id = ?");
            $status_upd->bind_param("i", $client_id);
            $status_upd->execute();
            $status_upd->close();
            
            // Refresh client data to show new badge
            $refresh = $conn->prepare("SELECT document_verification_status FROM clients WHERE client_id = ?");
            $refresh->bind_param("i", $client_id);
            $refresh->execute();
            $client_data['document_verification_status'] = $refresh->get_result()->fetch_assoc()['document_verification_status'];
            $refresh->close();
        }
    }
}

// $conn->close(); // Removed to allow header to access DB
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Manage Profile - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <style>
        /* Mobile Sidebar Handling */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1025; }
            .sidebar-overlay.show { display: block; }
        }
        
        .profile-banner {
            background: linear-gradient(135deg, var(--color-primary), #ff6b6b);
            padding: 3rem 2rem;
            border-radius: var(--radius-2xl);
            color: white;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }
        
        .profile-avatar-wrapper {
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            padding: 5px;
            box-shadow: var(--shadow-xl);
        }
        
        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--color-surface-light-alt);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--color-primary);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--color-text-muted);
            font-weight: 600;
            padding: 1rem 1.5rem;
            transition: all var(--transition-fast);
            border-bottom: 2px solid transparent;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--color-primary);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
            background: none;
        }
        
        .form-section {
            background-color: var(--color-surface-light);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            border: 1px solid var(--color-border-subtle);
        }
        
        .dark .form-section {
            background-color: var(--color-surface-dark);
        }
        
        .upload-box {
            border: 2px dashed var(--color-border-subtle);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        
        .upload-box:hover {
            border-color: var(--color-primary);
            background-color: rgba(59, 130, 246, 0.05);
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <?php include 'user_sidebar.php'; ?>
        
        <main class="main-content w-100 bg-body-tertiary min-vh-100">
            <?php include 'client_header.php'; ?>
            
            <div class="content-area">
                <div class="container-fluid p-0" style="max-width: 1000px; margin: 0 auto;">
                    
                    <?php if ($update_success): ?>
                    <div class="alert alert-success d-flex align-items-center mb-4">
                        <span class="material-symbols-outlined me-2">check_circle</span>
                        <div>Profile updated successfully!</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($update_error): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4">
                        <span class="material-symbols-outlined me-2">error</span>
                        <div><?php echo $update_error; ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- New Profile Header & Stats -->
                    <div class="row g-4 mb-4">
                        <!-- Profile Card -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
                                <div class="bg-primary bg-gradient" style="height: 100px;"></div>
                                <div class="card-body pt-0 ps-4 pe-4 pb-4 d-flex align-items-end">
                                    <div class="me-4 position-relative" style="margin-top: -40px;">
                                        <div class="rounded-circle bg-surface p-1 shadow-sm d-flex justify-content-center align-items-center" style="width: 100px; height: 100px;">
                                            <div class="rounded-circle bg-body-tertiary d-flex justify-content-center align-items-center text-primary fw-bold display-4" style="width: 100%; height: 100%;">
                                                <?php echo $avatar_letter; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-1">
                                        <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($client_data['first_name'] . ' ' . $client_data['last_name']); ?></h3>
                                        <div class="text-muted small mb-2"><?php echo htmlspecialchars($client_data['email']); ?></div>
                                        
                                        <?php 
                                        $badge_class = 'bg-secondary';
                                        $v_status = $client_data['document_verification_status'];
                                        if ($v_status === 'Approved') $badge_class = 'bg-success';
                                        elseif ($v_status === 'Pending') $badge_class = 'bg-warning text-dark';
                                        elseif ($v_status === 'Rejected') $badge_class = 'bg-danger';
                                        ?>
                                        <span class="badge rounded-pill <?php echo $badge_class; ?>">
                                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">verified_user</span>
                                            <?php echo $v_status; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stats Widgets -->
                        <div class="col-lg-4">
                            <div class="d-flex flex-column gap-3 h-100">
                                <!-- Completion Widget -->
                                <div class="card border-0 shadow-sm flex-fill">
                                    <div class="card-body py-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-uppercase text-muted small fw-bold">Profile Completion</span>
                                            <span class="badge bg-<?php echo $completion_percent==100?'success':'primary'; ?> bg-opacity-10 text-<?php echo $completion_percent==100?'success':'primary'; ?>"><?php echo $completion_percent; ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-<?php echo $completion_percent==100?'success':'primary'; ?>" role="progressbar" style="width: <?php echo $completion_percent; ?>%"></div>
                                        </div>
                                        <?php if($completion_percent < 100): ?>
                                            <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">Complete your profile to unlock all features.</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Mini Stats -->
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body p-3 text-center">
                                                <div class="text-muted small text-uppercase mb-1">Active Loans</div>
                                                <div class="h4 fw-bold mb-0 text-dark"><?php echo $active_loans_count; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-5 pt-4">
                         <form method="POST" enctype="multipart/form-data" id="profileForm">
                            <ul class="nav nav-tabs mb-4 px-2" id="profileTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">Personal Info</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab">Address</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">Employment</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="comaker-tab" data-bs-toggle="tab" data-bs-target="#comaker" type="button" role="tab">Co-maker</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">Documents</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="profileTabsContent">
                                <!-- Personal Info Tab -->
                                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                                    <!-- ... (same as original) ... -->
                                    <div class="form-section">
                                        <h4 class="h5 fw-bold text-main mb-4">Personal Details</h4>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">First Name *</label>
                                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($client_data['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Middle Name</label>
                                                <input type="text" class="form-control" name="middle_name" value="<?php echo htmlspecialchars($client_data['middle_name']); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Last Name *</label>
                                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($client_data['last_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Suffix</label>
                                                <input type="text" class="form-control" name="suffix" value="<?php echo htmlspecialchars($client_data['suffix']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Date of Birth</label>
                                                <input type="date" class="form-control" name="date_of_birth" value="<?php echo $client_data['date_of_birth']; ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Gender</label>
                                                <select class="form-select" name="gender">
                                                    <option value="">Select Gender</option>
                                                    <option value="Male" <?php echo $client_data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="Female" <?php echo $client_data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Civil Status</label>
                                                <select class="form-select" name="civil_status">
                                                    <option value="">Select Status</option>
                                                    <option value="Single" <?php echo $client_data['civil_status'] === 'Single' ? 'selected' : ''; ?>>Single</option>
                                                    <option value="Married" <?php echo $client_data['civil_status'] === 'Married' ? 'selected' : ''; ?>>Married</option>
                                                    <option value="Widowed" <?php echo $client_data['civil_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                                    <option value="Separated" <?php echo $client_data['civil_status'] === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Contact Number *</label>
                                                <input type="text" class="form-control" name="contact_number" value="<?php echo htmlspecialchars($client_data['contact_number']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Email Address</label>
                                                <input type="email" class="form-control" name="email_address" value="<?php echo htmlspecialchars($client_data['email_address'] ?: $client_data['email']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Address Tab -->
                                <div class="tab-pane fade" id="address" role="tabpanel">
                                    <!-- ... (keep existing address code) ... -->
                                     <div class="form-section">
                                        <h4 class="h5 fw-bold text-main mb-4">Present Address</h4>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label text-muted small fw-bold text-uppercase">House No / Unit</label>
                                                <input type="text" class="form-control" name="present_house_no" value="<?php echo htmlspecialchars($client_data['present_house_no']); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Street / Subdivision</label>
                                                <input type="text" class="form-control" name="present_street" value="<?php echo htmlspecialchars($client_data['present_street']); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Barangay</label>
                                                <select class="form-select" name="present_barangay" id="present_barangay" data-current="<?php echo htmlspecialchars($client_data['present_barangay'] ?? ''); ?>">
                                                    <option value="">Select Barangay</option>
                                                     <?php if(!empty($client_data['present_barangay'])): ?>
                                                        <option value="<?php echo htmlspecialchars($client_data['present_barangay']); ?>" selected><?php echo htmlspecialchars($client_data['present_barangay']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label text-muted small fw-bold text-uppercase">City / Municipality</label>
                                                <select class="form-select" name="present_city" id="present_city" data-current="<?php echo htmlspecialchars($client_data['present_city'] ?? ''); ?>">
                                                    <option value="">Select City</option>
                                                     <?php if(!empty($client_data['present_city'])): ?>
                                                        <option value="<?php echo htmlspecialchars($client_data['present_city']); ?>" selected><?php echo htmlspecialchars($client_data['present_city']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Province</label>
                                                <select class="form-select" name="present_province" id="present_province" data-current="<?php echo htmlspecialchars($client_data['present_province'] ?? ''); ?>">
                                                    <option value="">Select Province</option>
                                                     <?php if(!empty($client_data['present_province'])): ?>
                                                        <option value="<?php echo htmlspecialchars($client_data['present_province']); ?>" selected><?php echo htmlspecialchars($client_data['present_province']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Postal Code</label>
                                                <input type="text" class="form-control" name="present_postal_code" value="<?php echo htmlspecialchars($client_data['present_postal_code']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <h4 class="h5 fw-bold text-main mb-0">Permanent Address</h4>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="same_as_present" name="same_as_present" value="1" <?php echo $client_data['same_as_present'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="same_as_present">Same as Present</label>
                                            </div>
                                        </div>
                                        
                                        <div id="permanent_address_fields" class="<?php echo $client_data['same_as_present'] ? 'opacity-50 pointer-events-none' : ''; ?>">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label class="form-label text-muted small fw-bold text-uppercase">House No / Unit</label>
                                                    <input type="text" class="form-control" name="permanent_house_no" value="<?php echo htmlspecialchars($client_data['permanent_house_no']); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold text-uppercase">Street / Subdivision</label>
                                                    <input type="text" class="form-control" name="permanent_street" value="<?php echo htmlspecialchars($client_data['permanent_street']); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold text-uppercase">Barangay</label>
                                                    <select class="form-select" name="permanent_barangay" id="permanent_barangay" data-current="<?php echo htmlspecialchars($client_data['permanent_barangay'] ?? ''); ?>">
                                                        <option value="">Select Barangay</option>
                                                         <?php if(!empty($client_data['permanent_barangay'])): ?>
                                                            <option value="<?php echo htmlspecialchars($client_data['permanent_barangay']); ?>" selected><?php echo htmlspecialchars($client_data['permanent_barangay']); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label text-muted small fw-bold text-uppercase">City / Municipality</label>
                                                    <select class="form-select" name="permanent_city" id="permanent_city" data-current="<?php echo htmlspecialchars($client_data['permanent_city'] ?? ''); ?>">
                                                        <option value="">Select City</option>
                                                         <?php if(!empty($client_data['permanent_city'])): ?>
                                                            <option value="<?php echo htmlspecialchars($client_data['permanent_city']); ?>" selected><?php echo htmlspecialchars($client_data['permanent_city']); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label text-muted small fw-bold text-uppercase">Province</label>
                                                    <select class="form-select" name="permanent_province" id="permanent_province" data-current="<?php echo htmlspecialchars($client_data['permanent_province'] ?? ''); ?>">
                                                        <option value="">Select Province</option>
                                                         <?php if(!empty($client_data['permanent_province'])): ?>
                                                            <option value="<?php echo htmlspecialchars($client_data['permanent_province']); ?>" selected><?php echo htmlspecialchars($client_data['permanent_province']); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label text-muted small fw-bold text-uppercase">Postal Code</label>
                                                    <input type="text" class="form-control" name="permanent_postal_code" value="<?php echo htmlspecialchars($client_data['permanent_postal_code']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Employment Tab -->
                                <div class="tab-pane fade" id="employment" role="tabpanel">
                                    <!-- ... (keep existing employment code) ... -->
                                     <div class="form-section">
                                        <h4 class="h5 fw-bold text-main mb-4">Employment & Income</h4>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Employment Status</label>
                                                <select class="form-select" name="employment_status">
                                                    <option value="">Select Status</option>
                                                    <option value="Employed" <?php echo $client_data['employment_status'] === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                                                    <option value="Self-Employed" <?php echo $client_data['employment_status'] === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                                                    <option value="Unemployed" <?php echo $client_data['employment_status'] === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Occupation</label>
                                                <input type="text" class="form-control" name="occupation" value="<?php echo htmlspecialchars($client_data['occupation']); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Monthly Income</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-transparent border-end-0">₱</span>
                                                    <input type="number" class="form-control border-start-0 ps-0" name="monthly_income" value="<?php echo htmlspecialchars($client_data['monthly_income']); ?>" step="0.01">
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Employer Name</label>
                                                <input type="text" class="form-control" name="employer_name" value="<?php echo htmlspecialchars($client_data['employer_name']); ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Employer Address</label>
                                                <input type="text" class="form-control" name="employer_address" value="<?php echo htmlspecialchars($client_data['employer_address']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Co-maker Tab -->
                                <div class="tab-pane fade" id="comaker" role="tabpanel">
                                    <div class="form-section">
                                        <h4 class="h5 fw-bold text-main mb-4">Co-maker Information</h4>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Full Name</label>
                                                <input type="text" class="form-control" name="comaker_name" value="<?php echo htmlspecialchars($client_data['comaker_name'] ?? ''); ?>" placeholder="Co-maker's Name">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Relationship</label>
                                                <select class="form-select" name="comaker_relationship">
                                                    <option value="">Select Relationship</option>
                                                    <option value="Spouse" <?php echo ($client_data['comaker_relationship'] ?? '') === 'Spouse' ? 'selected' : ''; ?>>Spouse</option>
                                                    <option value="Parent" <?php echo ($client_data['comaker_relationship'] ?? '') === 'Parent' ? 'selected' : ''; ?>>Parent</option>
                                                    <option value="Sibling" <?php echo ($client_data['comaker_relationship'] ?? '') === 'Sibling' ? 'selected' : ''; ?>>Sibling</option>
                                                    <option value="Relative" <?php echo ($client_data['comaker_relationship'] ?? '') === 'Relative' ? 'selected' : ''; ?>>Relative</option>
                                                    <option value="Colleague" <?php echo ($client_data['comaker_relationship'] ?? '') === 'Colleague' ? 'selected' : ''; ?>>Colleague</option>
                                                    <option value="Friend" <?php echo ($client_data['comaker_relationship'] ?? '') === 'Friend' ? 'selected' : ''; ?>>Friend</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Contact Number</label>
                                                <input type="tel" class="form-control" name="comaker_contact" value="<?php echo htmlspecialchars($client_data['comaker_contact'] ?? ''); ?>" placeholder="09xxxxxxxxx">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Monthly Income</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-transparent border-end-0">₱</span>
                                                    <input type="number" class="form-control border-start-0 ps-0" name="comaker_income" value="<?php echo htmlspecialchars($client_data['comaker_income'] ?? ''); ?>" step="0.01" min="0">
                                                </div>
                                            </div>
                                            
                                            <div class="col-12 mt-3">
                                                <h6 class="fw-bold fs-6 text-muted mb-3 opacity-75">Co-maker Address</h6>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label class="form-label text-muted small fw-bold text-uppercase">House No / Unit</label>
                                                <input type="text" class="form-control" name="comaker_house_no" value="<?php echo htmlspecialchars($client_data['comaker_house_no'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Street / Subdivision</label>
                                                <input type="text" class="form-control" name="comaker_street" value="<?php echo htmlspecialchars($client_data['comaker_street'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Barangay</label>
                                                <select class="form-select" name="comaker_barangay" id="comaker_barangay" data-current="<?php echo htmlspecialchars($client_data['comaker_barangay'] ?? ''); ?>">
                                                    <option value="">Select Barangay</option>
                                                     <?php if(!empty($client_data['comaker_barangay'])): ?>
                                                        <option value="<?php echo htmlspecialchars($client_data['comaker_barangay']); ?>" selected><?php echo htmlspecialchars($client_data['comaker_barangay']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label text-muted small fw-bold text-uppercase">City / Municipality</label>
                                                <select class="form-select" name="comaker_city" id="comaker_city" data-current="<?php echo htmlspecialchars($client_data['comaker_city'] ?? ''); ?>">
                                                    <option value="">Select City</option>
                                                     <?php if(!empty($client_data['comaker_city'])): ?>
                                                        <option value="<?php echo htmlspecialchars($client_data['comaker_city']); ?>" selected><?php echo htmlspecialchars($client_data['comaker_city']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Province</label>
                                                <select class="form-select" name="comaker_province" id="comaker_province" data-current="<?php echo htmlspecialchars($client_data['comaker_province'] ?? ''); ?>">
                                                    <option value="">Select Province</option>
                                                     <?php if(!empty($client_data['comaker_province'])): ?>
                                                        <option value="<?php echo htmlspecialchars($client_data['comaker_province']); ?>" selected><?php echo htmlspecialchars($client_data['comaker_province']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label text-muted small fw-bold text-uppercase">Postal Code</label>
                                                <input type="text" class="form-control" name="comaker_postal_code" value="<?php echo htmlspecialchars($client_data['comaker_postal_code'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Documents Tab -->
                                <div class="tab-pane fade" id="documents" role="tabpanel">
                                    <div class="form-section">
                                        <h4 class="h5 fw-bold text-main mb-4">Upload Documents</h4>
                                        <p class="text-muted small mb-4">Please upload clear copies of the required documents for verification.</p>
                                        
                                        <div class="alert alert-info d-flex align-items-center mb-4">
                                            <span class="material-symbols-outlined me-2">verified_user</span>
                                            <div>Current Status: <strong><?php echo htmlspecialchars($client_data['document_verification_status']); ?></strong></div>
                                        </div>
                                        
                                        <div class="row g-4">
                                            <?php foreach ($document_types as $dt): ?>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded-3 bg-surface h-100">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="fw-bold text-main mb-0"><?php echo htmlspecialchars($dt['document_name']); ?></h6>
                                                        <?php if (isset($existing_documents[$dt['document_type_id']])): ?>
                                                            <span class="badge bg-success-subtle text-success">Uploaded</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger-subtle text-danger">Missing</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (isset($existing_documents[$dt['document_type_id']])): ?>
                                                        <div class="d-flex align-items-center gap-2 mb-3 bg-body-tertiary p-2 rounded">
                                                            <span class="material-symbols-outlined text-muted">description</span>
                                                            <div class="text-truncate small"><?php echo htmlspecialchars($existing_documents[$dt['document_type_id']]['file_name']); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <label class="upload-box d-block w-100 mb-0">
                                                        <input type="file" name="document_<?php echo $dt['document_type_id']; ?>" accept=".jpg,.jpeg,.png,.pdf" class="d-none" onchange="this.parentElement.classList.add('border-primary')">
                                                        <span class="material-symbols-outlined d-block fs-2 text-primary mb-1">cloud_upload</span>
                                                        <span class="small text-muted d-block">Click to upload new file</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-3 fixed-bottom bg-surface p-3 border-top shadow-lg" style="z-index: 100;">
                                <button type="reset" class="btn btn-light px-4">Reset</button>
                                <button type="submit" class="btn btn-primary px-5">Save Changes</button>
                            </div>
                         </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Theme toggle logic (reused)
        document.getElementById('themeToggle').addEventListener('click', function() {
            const html = document.documentElement;
            const icon = this.querySelector('.material-symbols-outlined');
            if (html.classList.contains('light')) {
                html.classList.remove('light');
                html.classList.add('dark');
                icon.textContent = 'light_mode';
                localStorage.setItem('theme', 'dark');
            } else {
                html.classList.remove('dark');
                html.classList.add('light');
                icon.textContent = 'dark_mode';
                localStorage.setItem('theme', 'light');
            }
        });
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.remove('light');
            document.documentElement.classList.add('dark');
            document.querySelector('#themeToggle .material-symbols-outlined').textContent = 'light_mode';
        }
        
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
        
        // Permanent address toggle
        document.getElementById('same_as_present').addEventListener('change', function() {
            const fields = document.getElementById('permanent_address_fields');
            if (this.checked) {
                fields.classList.add('opacity-50', 'pointer-events-none');
                fields.style.pointerEvents = 'none';
            } else {
                fields.classList.remove('opacity-50', 'pointer-events-none');
                fields.style.pointerEvents = 'auto';
            }
        });
    </script>
    <style>
        .pointer-events-none { pointer-events: none; }
        /* Fix for fixed bottom spacing */
        body { padding-bottom: 80px; }
    </style>
    <script src="../assets/js/address-selector.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
             // Initialize Address Selectors
            new AddressSelector('present_');
            new AddressSelector('permanent_');
            new AddressSelector('comaker_');
            
            // Re-run permanent address logic when "Same as Present" changes
            const sameAsCheckbox = document.getElementById('same_as_present');
            if (sameAsCheckbox) {
                sameAsCheckbox.addEventListener('change', function() {
                    // Logic is handled by CSS mostly, but if we wanted to copy values:
                    if (this.checked) {
                         // Copy values logic could be added here if needed, 
                         // or backend handles it as per current PHP logic.
                    }
                });
            }
        });
    </script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
