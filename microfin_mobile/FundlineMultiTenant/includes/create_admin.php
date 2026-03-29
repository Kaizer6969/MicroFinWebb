<?php
/**
 * Create Admin Page - Fundline Web Application
 * Form to create new admin/employee accounts
 */

// Start session
session_start();

// Check permission
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Super Admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Include database connection
require_once '../config/db.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $password = $_POST['password'];
    $department = $_POST['department'];
    $position = $_POST['position'];

    // Basic Validation
    if (empty($username) || empty($password)) {
        $error_message = "All fields are required.";
    } else {
        // Check if username/email exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error_message = "Username or Email already exists.";
        } else {
            // Create Account
            $conn->begin_transaction();
            try {
                // 1. Insert User
                $hashed = password_hash($password, PASSWORD_ARGON2ID);
                // Get 'Employee' role ID (assuming standard employee role is 2, but let's query or default)
                // We'll trust standard Employee role exists. If not, we might need to handle it.
                // For this implementation, we set user_type='Employee'. 
                // We need a role_id. Let's look up 'Employee' role or default to a safe value.
                // Assuming role_id 2 is Employee/Admin based on typical setups, but good practice to lookup.
                $role_res = $conn->query("SELECT role_id FROM user_roles WHERE role_name = 'Employee' LIMIT 1");
                $role_id = ($role_res->num_rows > 0) ? $role_res->fetch_assoc()['role_id'] : 2; 

                $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role_id, user_type, status, email_verified) VALUES (?, ?, ?, ?, 'Employee', 'Active', 1)");
                $stmt->bind_param("sssi", $username, $email, $hashed, $role_id);
                $stmt->execute();
                $new_user_id = $conn->insert_id;

                // 2. Insert Employee Profile
                $emp_stmt = $conn->prepare("INSERT INTO employees (user_id, first_name, last_name, department, position, hire_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
                $emp_stmt->bind_param("issss", $new_user_id, $first_name, $last_name, $department, $position);
                $emp_stmt->execute();

                // 3. Log Action
                $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, description, ip_address) VALUES (?, 'CREATE_ADMIN', ?, ?)");
                $desc = "Created new admin user: $username";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $_SESSION['user_id'], $desc, $ip);
                $log_stmt->execute();

                $conn->commit();
                $success_message = "Admin account created successfully!";
                
                // Reset form
                $username = $email = $first_name = $last_name = $position = '';
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to create account: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Create Admin - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <!-- Header -->
            <?php include 'admin_header.php'; ?>
            
            <div class="container-fluid p-4">
                
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-xl-8">
                        <div class="mb-4">
                            <a href="admin_manage_admins.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 rounded-pill px-3">
                                <span class="material-symbols-outlined" style="font-size: 1.25rem;">arrow_back</span>
                                Back to Admins
                            </a>
                        </div>
                        
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                                <h4 class="fw-bold mb-1">Create New Admin</h4>
                                <p class="text-muted small">Create a new administrator account with system access</p>
                            </div>
                            
                            <div class="card-body p-4">
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined filled">error</span>
                                            <?php echo htmlspecialchars($error_message); ?>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($success_message): ?>
                                    <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="material-symbols-outlined filled">check_circle</span>
                                            <?php echo htmlspecialchars($success_message); ?>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <h5 class="fw-bold mb-3 text-primary">Account Details</h5>
                                    
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">Username</label>
                                            <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">Email Address</label>
                                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-muted">Temporary Password</label>
                                            <div class="input-group">
                                                <input type="text" name="password" class="form-control font-monospace" required minlength="8" value="Fundline<?php echo date('Y'); ?>!">
                                                <button class="btn btn-outline-secondary" type="button" onclick="this.previousElementSibling.select(); document.execCommand('copy');">
                                                    <span class="material-symbols-outlined">content_copy</span>
                                                </button>
                                            </div>
                                            <div class="form-text">Default password shown. User should change this upon login.</div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4 text-muted">
                                    
                                    <h5 class="fw-bold mb-3 text-primary">Employee Profile</h5>
                                    
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">First Name</label>
                                            <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">Last Name</label>
                                            <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">Department</label>
                                            <select name="department" class="form-select">
                                                <option value="Admin">Admin</option>
                                                <option value="Sales and Marketing">Sales and Marketing</option>
                                                <option value="Collections">Collections</option>
                                                <option value="Loan Processing">Loan Processing</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">Position</label>
                                            <input type="text" name="position" class="form-control" required placeholder="e.g. Loan Officer" value="<?php echo htmlspecialchars($position ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end gap-2 pt-2">
                                        <a href="admin_manage_admins.php" class="btn btn-light">Cancel</a>
                                        <button type="submit" class="btn btn-primary px-4">Create Account</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

