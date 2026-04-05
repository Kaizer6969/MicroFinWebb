<?php
$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$dbname = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "=== ADDING ALL SEED DATA ===\n\n";

// =====================
// 1. PERMISSIONS
// =====================
echo "--- PERMISSIONS ---\n";
$all_perms = [
    ['VIEW_DASHBOARD', 'Dashboard', 'View dashboard and analytics'],
    ['VIEW_APPLICATIONS', 'Loans', 'View loan applications'],
    ['MANAGE_APPLICATIONS', 'Loans', 'Manage loan applications'],
    ['VIEW_CLIENTS', 'Clients', 'View client list and details'],
    ['CREATE_CLIENTS', 'Clients', 'Create new clients'],
    ['MANAGE_CLIENTS', 'Clients', 'Create, edit, delete clients'],
    ['VIEW_CREDIT_ACCOUNTS', 'Credit', 'View credit accounts management'],
    ['VIEW_LOANS', 'Loans', 'View loan list and details'],
    ['CREATE_LOANS', 'Loans', 'Create new loans'],
    ['APPROVE_LOANS', 'Loans', 'Approve or reject loan applications'],
    ['MANAGE_LOANS', 'Loans', 'Process and manage loans'],
    ['PROCESS_PAYMENTS', 'Payments', 'Process and record payments'],
    ['MANAGE_PAYMENTS', 'Payments', 'Manage all payments'],
    ['VIEW_PAYMENTS', 'Payments', 'View payment history'],
    ['VIEW_USERS', 'Staff', 'View team directory'],
    ['VIEW_EMPLOYEES', 'Staff', 'View employee list'],
    ['MANAGE_EMPLOYEES', 'Staff', 'Create, edit, delete employees'],
    ['VIEW_REPORTS', 'Reports', 'View and export reports'],
    ['MANAGE_SETTINGS', 'Settings', 'Manage system settings'],
    ['MANAGE_ROLES', 'Settings', 'Create and manage user roles'],
    ['MANAGE_DOCUMENTS', 'Documents', 'Manage document types and uploads'],
    ['CONDUCT_CI', 'Credit', 'Conduct credit investigations'],
    ['VIEW_AUDIT_LOGS', 'Settings', 'View system audit logs'],
    ['MANAGE_BILLING', 'Billing', 'Manage tenant billing and payments'],
    ['MANAGE_BRANDING', 'Settings', 'Customize tenant branding'],
    ['MANAGE_PRODUCTS', 'Products', 'Create and manage loan products'],
    ['VIEW_PRODUCTS', 'Products', 'View loan products'],
    ['SUPER_ADMIN', 'Platform', 'Full platform administration access'],
];

$insert = $pdo->prepare("INSERT IGNORE INTO permissions (permission_code, module, description) VALUES (?, ?, ?)");
$count = 0;
foreach ($all_perms as $p) {
    $insert->execute($p);
    if ($insert->rowCount() > 0) $count++;
}
echo "✓ Added $count new permissions (total: " . count($all_perms) . ")\n";

// =====================
// 2. DOCUMENT TYPES (from original schema)
// =====================
echo "\n--- DOCUMENT TYPES ---\n";
$doc_types = [
    ['Scanned ID', 'Scanned government-issued ID from mobile app verification', 1, NULL],
    ['Valid ID Front', 'Front side of government-issued ID', 1, NULL],
    ['Valid ID Back', 'Back side of government-issued ID', 1, NULL],
    ['Proof of Income', 'Latest payslip, ITR, or bank statement', 1, NULL],
    ['Proof of Billing', 'Upload proof of address (utility bill, barangay certificate, etc.)', 1, NULL],
    ['Proof of Legitimacy Document', 'Any valid proof of legitimacy such as business permit, DTI, or SEC registration', 1, 'Business'],
    ['Business Financial Statements', 'Latest financial statements or income records', 1, 'Business'],
    ['Business Plan', 'Business plan or proposal (for new businesses)', 0, 'Business'],
    ['School Enrollment Certificate', 'Certificate of enrollment or admission letter', 1, 'Education'],
    ['School ID', 'Valid school ID', 1, 'Education'],
    ['Tuition Fee Assessment', 'Official assessment of tuition and fees', 1, 'Education'],
    ['Land Title/Lease Agreement', 'Proof of land ownership or lease', 1, 'Agricultural'],
    ['Farm Plan', 'Detailed farm plan or proposal', 1, 'Agricultural'],
    ['Medical Certificate', 'Medical certificate or hospital bill', 1, 'Medical'],
    ['Prescription/Treatment Plan', "Doctor's prescription or treatment plan", 0, 'Medical'],
    ['Property Documents', 'Land title, tax declaration, or contract to sell', 1, 'Housing'],
    ['Construction Estimate', 'Detailed construction estimate or quotation', 1, 'Housing'],
    ['DTI/SEC Registration', 'Business registration documents', 0, 'Business'],
    ['Barangay Clearance', 'Clearance from local barangay', 0, NULL],
    ['Marriage Certificate', 'For married applicants', 0, NULL],
    ['Birth Certificate', 'Birth certificate copy', 0, NULL],
];

$doc_insert = $pdo->prepare("INSERT IGNORE INTO document_types (document_name, description, is_required, loan_purpose) VALUES (?, ?, ?, ?)");
$doc_count = 0;
foreach ($doc_types as $d) {
    $doc_insert->execute($d);
    if ($doc_insert->rowCount() > 0) $doc_count++;
}
echo "✓ Added $doc_count new document types (total: " . count($doc_types) . ")\n";

// =====================
// 3. ASSIGN PERMISSIONS TO ROLES
// =====================
echo "\n--- ASSIGNING PERMISSIONS TO ROLES ---\n";
$permissions = $pdo->query("SELECT permission_id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
$roles = $pdo->query("SELECT role_id, role_name FROM user_roles WHERE tenant_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

if (empty($roles)) {
    echo "No tenant roles found. Create a tenant first.\n";
} else {
    $assign = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($roles as $role) {
        $assigned = 0;
        foreach ($permissions as $pid) {
            $assign->execute([$role['role_id'], $pid]);
            if ($assign->rowCount() > 0) $assigned++;
        }
        echo "✓ Role '{$role['role_name']}' - assigned $assigned new permissions\n";
    }
}

echo "\n=== DONE ===\n";
echo "- Permissions: " . count($all_perms) . "\n";
echo "- Document types: " . count($doc_types) . "\n";
echo "- Roles updated: " . count($roles) . "\n";
echo "\nRefresh the app to see all features!\n";
