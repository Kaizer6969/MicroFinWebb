<?php
session_start();
require_once '../backend/db_connect.php';
require_once '../backend/billing_access.php';
require_once '../backend/tenant_identity.php';

$form_success = false;
$form_error = '';

$is_talk_to_expert = false; // Sarah replaces the retired talk-to-staff flow.
$request_type = 'tenant_application';

function demo_column_exists(PDO $pdo, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sanitized_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE '{$sanitized_column}'");
    $stmt->execute();
    $cache[$key] = (bool) $stmt->fetch();

    return $cache[$key];
}

function demo_generate_username_base($preferredLastName, $fallbackInstitutionName = '')
{
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$preferredLastName));
    if ($base === '' && $fallbackInstitutionName !== '') {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '.', (string)$fallbackInstitutionName)));
    }
    return $base !== '' ? $base : 'tenantadmin';
}

function demo_send_acknowledgement_email($toEmail, $institutionName, $isTalkToExpert)
{
    if (trim((string)$toEmail) === '') {
        return;
    }

    $subject = $isTalkToExpert
        ? 'MicroFin Inquiry Received'
        : 'MicroFin Application Received';

    if ($isTalkToExpert) {
        $body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #0f172a;'>
                <h2 style='margin-bottom: 8px;'>MicroFin</h2>
                <p>Thank you for your inquiry.</p>
                <p>Please wait as our staff will email you shortly.</p>
            </body>
            </html>
        ";
    } else {
        $body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #0f172a;'>
                <h2 style='margin-bottom: 8px;'>MicroFin</h2>
                <p>Thank you for applying to MicroFin. We have received your application.</p>
                <p><strong>Institution:</strong> " . htmlspecialchars((string)$institutionName, ENT_QUOTES, 'UTF-8') . "</p>
                <p>Please wait for our team response while we review your application.</p>
                <p>Regards,<br>MicroFin Team</p>
            </body>
            </html>
        ";
    }

    $result_msg = mf_send_brevo_email($toEmail, $subject, $body);
    if ($result_msg !== 'Email sent successfully.') {
        error_log('Demo acknowledgement email failed: ' . $result_msg);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_demo') {
    $institution_name = trim($_POST['institution_name'] ?? '');
    $contact_first_name = trim($_POST['contact_first_name'] ?? '');
    $contact_last_name = trim($_POST['contact_last_name'] ?? '');
    $contact_mi = trim($_POST['contact_mi'] ?? '');
    $contact_suffix = trim($_POST['contact_suffix'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $company_address = trim($_POST['location'] ?? '');
    $location = $company_address;
    $concern_category = trim($_POST['concern_category'] ?? '');
    $plan_tier = trim($_POST['plan_tier'] ?? '');
    $company_email = trim($_POST['company_email'] ?? '');
    $demo_schedule_date = trim($_POST['demo_schedule_date'] ?? '');
    $demo_schedule_date = $demo_schedule_date === '' ? date('Y-m-d H:i:s') : $demo_schedule_date;
    $uploaded_files = [];
    
    // Primary upload flow: fixed slots legitimacy_document_1..5
    for ($slot = 1; $slot <= 5; $slot++) {
        $field = 'legitimacy_document_' . $slot;
        if (!isset($_FILES[$field])) {
            continue;
        }
        $uploaded_files[] = [
            'name' => $_FILES[$field]['name'] ?? '',
            'tmp_name' => $_FILES[$field]['tmp_name'] ?? '',
            'error' => $_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE,
        ];
    }

    // Backward compatibility: old multi-upload field legitimacy_documents[]
    if (count($uploaded_files) === 0 && isset($_FILES['legitimacy_documents']) && isset($_FILES['legitimacy_documents']['name']) && is_array($_FILES['legitimacy_documents']['name'])) {
        foreach ($_FILES['legitimacy_documents']['name'] as $idx => $legacy_name) {
            $uploaded_files[] = [
                'name' => $legacy_name,
                'tmp_name' => $_FILES['legitimacy_documents']['tmp_name'][$idx] ?? '',
                'error' => $_FILES['legitimacy_documents']['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
            ];
        }
    }

    if ($is_talk_to_expert && $plan_tier === '') {
        // Inquiries do not choose a subscription plan — leave plan_tier empty.
        $plan_tier = null;
    }

    $document_count = 0;
    if (is_array($uploaded_files)) {
        foreach ($uploaded_files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $document_count++;
            }
        }
    }

    $is_otp_verified = false;
    if (isset($_SESSION['verified_contact_email']) && $_SESSION['verified_contact_email'] === $company_email) {
        $is_otp_verified = true;
    }

    if ($institution_name === '' || $company_email === '' || (!$is_talk_to_expert && $plan_tier === '') || ($is_talk_to_expert && $concern_category === '')) {
        $form_error = $is_talk_to_expert
            ? 'Institution Name, Work Email, and Category of Concern are required.'
            : 'Institution Name, Work Email, and Subscription Plan are required.';
    } elseif (!$is_talk_to_expert && ($document_count < 1 || $document_count > 5)) {
        $form_error = 'Please upload 1 to 5 proof of legitimacy documents.';
    } elseif (!$is_otp_verified) {
        $form_error = 'Email has not been verified. Please complete OTP verification.';
    } else {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND deleted_at IS NULL");
        $check_stmt->execute([$company_email]);
        $duplicate_count = $check_stmt->fetchColumn();

        if ($duplicate_count > 0) {
            $form_error = 'A demo request with this email already exists. Our team will contact you shortly.';
        } else {
            try {
                $allowed_extensions = [
                    'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff',
                    'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods', 'odp'
                ];

                $plan_pricing_map = [
                    'Starter' => 4999.00,
                    'Pro' => 14999.00,
                    'Enterprise' => 19999.00,
                    'Unlimited' => 29999.00,
                ];
                $plan_limits_map = [
                    'Starter' => ['clients' => 1000, 'users' => 250],
                    'Pro' => ['clients' => 5000, 'users' => 2000],
                    'Enterprise' => ['clients' => 10000, 'users' => 5000],
                    'Unlimited' => ['clients' => -1, 'users' => -1],
                ];
                
                if ($is_talk_to_expert) {
                    $mrr = 0;
                    $max_c = 0;
                    $max_u = 0;
                } else {
                    $mrr = $plan_pricing_map[$plan_tier] ?? 4999.00;
                    $max_c = $plan_limits_map[$plan_tier]['clients'] ?? 1000;
                    $max_u = $plan_limits_map[$plan_tier]['users'] ?? 250;
                }

                try {
                    $pdo->exec("ALTER TABLE tenants ADD COLUMN concern_category VARCHAR(150) NULL AFTER request_type");
                } catch (Throwable $e) {}

                $pdo->beginTransaction();

                $tenant_id = mf_generate_tenant_id($pdo, 10);
                $request_status = 'Pending';
                $has_request_type = demo_column_exists($pdo, 'tenants', 'request_type');
                $has_company_address = demo_column_exists($pdo, 'tenants', 'company_address');
                $has_demo_schedule_date = demo_column_exists($pdo, 'tenants', 'demo_schedule_date');

                if ($has_demo_schedule_date && $has_request_type && $has_company_address) {
                    $stmt = $pdo->prepare("
                        INSERT INTO tenants (
                            tenant_id, tenant_name, company_address, plan_tier,
                            demo_schedule_date, request_type, mrr,
                            max_clients, max_users, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tenant_id, $institution_name, $company_address, $plan_tier,
                        $demo_schedule_date, $request_type, $mrr, $max_c, $max_u, $request_status
                    ]);
                } elseif ($has_demo_schedule_date && $has_company_address) {
                    $stmt = $pdo->prepare("
                        INSERT INTO tenants (
                            tenant_id, tenant_name, company_address, plan_tier,
                            demo_schedule_date, mrr, max_clients, max_users, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tenant_id, $institution_name, $company_address, $plan_tier,
                        $demo_schedule_date, $mrr, $max_c, $max_u, $request_status
                    ]);
                } elseif ($has_request_type && $has_company_address) {
                    $stmt = $pdo->prepare(" 
                        INSERT INTO tenants (
                            tenant_id, tenant_name, company_address, plan_tier,
                            request_type, mrr, max_clients, max_users, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tenant_id, $institution_name, $company_address, $plan_tier,
                        $request_type, $mrr, $max_c, $max_u, $request_status
                    ]);
                } elseif ($has_company_address) {
                    $stmt = $pdo->prepare(" 
                        INSERT INTO tenants (
                            tenant_id, tenant_name, company_address, plan_tier,
                            mrr, max_clients, max_users, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tenant_id, $institution_name, $company_address, $plan_tier,
                        $mrr, $max_c, $max_u, $request_status
                    ]);
                } else {
                    $stmt = $pdo->prepare(" 
                        INSERT INTO tenants (
                            tenant_id, tenant_name, first_name, last_name,
                            mi, suffix, branch_name, plan_tier,
                            email, mrr, max_clients, max_users, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tenant_id, $institution_name, null, null,
                        null, null, $location, $plan_tier,
                        $company_email, $mrr, $max_c, $max_u, $request_status
                    ]);
                }

                if ($concern_category !== '') {
                    try {
                        $stmtUpdate = $pdo->prepare("UPDATE tenants SET concern_category = ? WHERE tenant_id = ?");
                        $stmtUpdate->execute([$concern_category, $tenant_id]);
                    } catch (Throwable $e) {}
                }

                $admin_role_stmt = $pdo->prepare("INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, 'Admin', 'Default system administrator', TRUE)");
                $admin_role_stmt->execute([$tenant_id]);
                $admin_role_id = (int)$pdo->lastInsertId();

                $base_username = demo_generate_username_base($contact_last_name, $institution_name);
                $username = $base_username;
                $username_counter = 2;
                while (true) {
                    $username_check_stmt = $pdo->prepare('SELECT 1 FROM users WHERE tenant_id = ? AND username = ? LIMIT 1');
                    $username_check_stmt->execute([$tenant_id, $username]);
                    if (!$username_check_stmt->fetchColumn()) {
                        break;
                    }
                    $username = $base_username . $username_counter;
                    $username_counter++;
                }

                $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 12);
                $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
                $user_type = $is_talk_to_expert ? 'inquirer' : 'applicant';
                $users_has_billing_column = demo_column_exists($pdo, 'users', 'can_manage_billing');

                if ($users_has_billing_column) {
                    $user_insert_stmt = $pdo->prepare("INSERT INTO users (tenant_id, username, email, phone_number, password_hash, force_password_change, role_id, user_type, status, can_manage_billing, first_name, last_name, middle_name, suffix) VALUES (?, ?, ?, ?, ?, TRUE, ?, ?, 'Inactive', 1, ?, ?, ?, ?)");
                } else {
                    $user_insert_stmt = $pdo->prepare("INSERT INTO users (tenant_id, username, email, phone_number, password_hash, force_password_change, role_id, user_type, status, first_name, last_name, middle_name, suffix) VALUES (?, ?, ?, ?, ?, TRUE, ?, ?, 'Inactive', ?, ?, ?, ?)");
                }
                $user_insert_stmt->execute([
                    $tenant_id,
                    $username,
                    $company_email,
                    $contact_number !== '' ? $contact_number : null,
                    $password_hash,
                    $admin_role_id,
                    $user_type,
                    $contact_first_name !== '' ? $contact_first_name : null,
                    $contact_last_name !== '' ? $contact_last_name : null,
                    $contact_mi !== '' ? $contact_mi : null,
                    $contact_suffix !== '' ? $contact_suffix : null,
                ]);
                mf_set_user_billing_access($pdo, (string)$tenant_id, (int)$pdo->lastInsertId(), true);

                $upload_dir = __DIR__ . '/../uploads/business_permits/';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Failed to prepare upload directory.');
                }

                $doc_stmt = $pdo->prepare(
                    "INSERT INTO tenant_legitimacy_documents (tenant_id, original_file_name, file_path) VALUES (?, ?, ?)"
                );

                if (!$is_talk_to_expert && is_array($uploaded_files)) {
                    $file_sequence = 1;
                    foreach ($uploaded_files as $file) {
                        $original_name = $file['name'] ?? '';
                        $error_code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
                        if ($error_code === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }

                        if ($error_code !== UPLOAD_ERR_OK) {
                            throw new Exception('One of the uploaded files failed to upload.');
                        }

                        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowed_extensions, true)) {
                            throw new Exception('Unsupported file type detected in uploads.');
                        }

                        $stored_name = $tenant_id . '_doc_' . $file_sequence . '_' . time() . '_' . bin2hex(random_bytes(2)) . '.' . $extension;
                        $target_path = $upload_dir . $stored_name;
                        if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $target_path)) {
                            throw new Exception('Unable to save one of the uploaded documents.');
                        }

                        $relative_path = '../uploads/business_permits/' . $stored_name;
                        $doc_stmt->execute([$tenant_id, $original_name, $relative_path]);
                        $file_sequence++;
                    }
                }

                $pdo->commit();

                // Send acknowledgement email after successful save (best-effort only).
                try {
                    demo_send_acknowledgement_email($company_email, $institution_name, $is_talk_to_expert);
                } catch (Throwable $mailError) {
                    error_log('Demo acknowledgement email failed: ' . $mailError->getMessage());
                }

                $form_success = true;
                unset($_SESSION['verified_contact_email']);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Demo request submission failed: ' . $e->getMessage());
                $form_error = 'An error occurred while submitting your request. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_talk_to_expert ? 'Talk to an Expert' : 'Apply Now'; ?> | MicroFin</title>
    <meta name="description" content="<?php echo $is_talk_to_expert ? 'Talk to a MicroFin expert and get guidance tailored to your institution.' : 'Apply to MicroFin, the cloud banking platform built for Microfinance Institutions. Fill out the form and our team will be in touch.'; ?>">
    <script>
        (function () {
            try {
                var storedTheme = localStorage.getItem('microfin_public_theme');
                if (storedTheme === 'light' || storedTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', storedTheme);
                }
            } catch (error) {}
        }());
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --base-dark: #0B0F1A;
            --surface-light: #f8fafc;
            --primary: #3B82F6;
            --primary-light: #93c5fd;
            --accent: #8B5CF6;
            --accent-hover: #7C3AED;
            --primary-glow: rgba(59, 130, 246, 0.2);
            --text-dark: #0f172a;
            --text-gray: #475569;
            --text-light: #64748B;
            --shadow-lg: 0 20px 48px rgba(0, 0, 0, 0.08);
        }

        /* Updated Body for Light Theme */
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: #f1f5f9;
            padding: 86px 20px 40px;
            color: var(--text-dark);
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 12% 18%, rgba(59, 130, 246, 0.08) 0%, transparent 46%),
                        radial-gradient(circle at 86% 6%, rgba(139, 92, 246, 0.06) 0%, transparent 42%),
                        radial-gradient(circle at 70% 88%, rgba(59, 130, 246, 0.04) 0%, transparent 45%);
            pointer-events: none;
            z-index: 0;
        }

        /* Updated Back Button */
        .back-btn {
            position: fixed;
            top: 22px;
            left: 22px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 9px 16px;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.25s ease;
            z-index: 20;
        }

        .back-btn:hover {
            color: var(--primary);
            background: #f8fafc;
            transform: translateX(-2px);
            border-color: #cbd5e1;
        }

        .back-btn .material-symbols-rounded { font-size: 18px; transition: transform 0.2s; }
        .back-btn:hover .material-symbols-rounded { transform: translateX(-2px); }

        .demo-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1160px;
            margin: 0 auto;
            animation: slideUp 0.55s ease-out;
        }

        .demo-layout {
            display: grid;
            grid-template-columns: minmax(280px, 0.9fr) minmax(0, 1.1fr);
            gap: 22px;
            align-items: stretch;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Kept Left Side Dark for Premium Contrast */
        .demo-intro {
            background: linear-gradient(135deg, #0b0f1a 0%, #1e1b4b 100%);
            border: transparent;
            border-radius: 18px;
            padding: 40px 32px;
            box-shadow: 0 20px 48px -20px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .page-brand { text-align: left; margin-bottom: 6px; }
        .page-brand .logo {
            display: inline-flex; align-items: center; gap: 8px;
            color: #60A5FA; margin-bottom: 8px;
        }
        .page-brand .logo-text { font-size: 1.2rem; font-weight: 800; letter-spacing: -0.4px; color: white;}
        .page-brand p { color: #94A3B8; font-size: 0.9rem; }

        .intro-badge {
            display: inline-flex; align-items: center; gap: 6px; width: fit-content;
            border-radius: 999px; border: transparent;
            background: rgba(30, 64, 175, 0.2); color: #bfdbfe;
            font-size: 0.75rem; font-weight: 700; letter-spacing: 0.4px;
            text-transform: uppercase; padding: 7px 10px;
        }

        .intro-title { font-size: 1.95rem; line-height: 1.1; letter-spacing: -0.6px; font-weight: 800; color: #f8fbff; }
        .intro-sub { font-size: 0.95rem; color: #cbd5e1; line-height: 1.6; }

        .intro-list { list-style: none; display: grid; gap: 12px; }
        .intro-list li {
            display: grid; grid-template-columns: 22px 1fr; gap: 8px; align-items: start;
            color: #e2e8f0; font-size: 0.9rem; line-height: 1.45;
        }
        .intro-list .material-symbols-rounded { color: #34d399; font-size: 20px; margin-top: 1px; }

        .intro-note {
            margin-top: auto; font-size: 0.82rem; color: #93c5fd;
            border-top: transparent; padding-top: 14px;
        }

        /* Updated Form Card to Crisp Light Theme */
        .demo-card {
            background: #ffffff;
            border: transparent;
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 16px 40px -20px rgba(0, 0, 0, 0.1);
        }

        .demo-card h2 { font-size: 1.55rem; font-weight: 800; color: var(--text-dark); margin-bottom: 4px; letter-spacing: -0.4px; }
        .demo-card .subtitle { color: var(--text-gray); font-size: 0.95rem; margin-bottom: 24px; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 8px; color: #334155; }

        .form-row { display: flex; gap: 12px; }
        .form-row .form-group { flex: 1; }

        /* Updated Inputs */
        .input-field {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            color: var(--text-dark);
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .input-field::placeholder { color: #94a3b8; }
        .input-field:focus {
            outline: none; background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .text-danger { color: #ef4444; }

        .location-helper {
            color: #64748b;
            font-size: 0.8rem;
            margin-top: 6px;
            display: block;
        }

        .location-search-wrap {
            position: relative;
            z-index: 1200;
        }

        .location-suggestions {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            display: none;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.12);
            overflow: hidden;
            z-index: 1300;
            max-height: 260px;
            overflow-y: auto;
        }

        .location-suggestions.is-open {
            display: block;
        }

        .location-suggestion {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            padding: 12px 14px;
            cursor: pointer;
            transition: background 0.15s ease;
            border-bottom: 1px solid #e2e8f0;
        }

        .location-suggestion:last-child {
            border-bottom: 0;
        }

        .location-suggestion:hover,
        .location-suggestion.is-active {
            background: #eff6ff;
        }

        .location-suggestion-title {
            display: block;
            color: #0f172a;
            font-size: 0.88rem;
            line-height: 1.45;
            font-weight: 600;
        }

        .location-suggestion-sub {
            display: block;
            color: #64748b;
            font-size: 0.76rem;
            line-height: 1.45;
            margin-top: 4px;
        }

        .location-suggestion-empty {
            padding: 12px 14px;
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.5;
            background: #ffffff;
        }

        .location-map-wrap {
            display: block;
            margin-top: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            position: relative;
            z-index: 1;
        }

        .location-map-wrap.is-visible {
            display: block;
        }

        .location-map-frame {
            width: 100%;
            height: 280px;
            border: 0;
            display: block;
            background: #e2e8f0;
        }

        .location-map-frame .leaflet-control-attribution {
            font-size: 0.68rem;
        }

        .location-map-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 14px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .location-map-status {
            color: #475569;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .plan-helper { font-size: 0.8rem; color: var(--text-gray); margin-top: -2px; margin-bottom: 12px; }

        .plan-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; align-items: stretch; }
        .plan-option { position: relative; display: block; cursor: pointer; }
        .plan-option.plan-option-wide { grid-column: 1 / -1; }
        .plan-option input { position: absolute; opacity: 0; pointer-events: none; }

        /* Updated Plan Cards */
        .plan-card-content {
            display: flex; flex-direction: column; gap: 5px; width: 100%; height: 100%;
            border: 1px solid #e2e8f0; background: #ffffff; border-radius: 12px;
            padding: 16px 36px 16px 16px; min-height: 122px; transition: all 0.2s ease;
            position: relative;
        }
        .plan-card-content::after {
            content: ''; position: absolute; top: 12px; right: 12px; width: 16px; height: 16px;
            border-radius: 999px; border: 1px solid #cbd5e1; background: #f8fafc; transition: all 0.2s ease;
        }

        .plan-option:hover .plan-card-content {
            border-color: #93c5fd; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.05);
        }

        .plan-option input:focus + .plan-card-content { box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .plan-option input:checked + .plan-card-content {
            background: #eff6ff;
        }
        .plan-option.plan-starter input:checked + .plan-card-content {
            border-color: #16a34a; background: #f0fdf4;
            box-shadow: 0 0 0 1px #16a34a, 0 8px 20px rgba(22, 163, 74, 0.16);
        }
        .plan-option.plan-starter input:checked + .plan-card-content::after {
            background: #16a34a; border-color: #16a34a; box-shadow: inset 0 0 0 3px #ffffff;
        }
        .plan-option.plan-pro input:checked + .plan-card-content {
            border-color: #2563eb; background: #eff6ff;
            box-shadow: 0 0 0 1px #2563eb, 0 8px 20px rgba(37, 99, 235, 0.16);
        }
        .plan-option.plan-pro input:checked + .plan-card-content::after {
            background: #2563eb; border-color: #2563eb; box-shadow: inset 0 0 0 3px #ffffff;
        }
        .plan-option.plan-enterprise input:checked + .plan-card-content {
            border-color: #d97706; background: #fff7ed;
            box-shadow: 0 0 0 1px #d97706, 0 8px 20px rgba(217, 119, 6, 0.16);
        }
        .plan-option.plan-enterprise input:checked + .plan-card-content::after {
            background: #d97706; border-color: #d97706; box-shadow: inset 0 0 0 3px #ffffff;
        }
        .plan-option.plan-unlimited input:checked + .plan-card-content {
            border-color: #8b5cf6; background: #faf5ff;
            box-shadow: 0 0 0 1px #8b5cf6, 0 8px 20px rgba(139, 92, 246, 0.18);
        }
        .plan-option.plan-unlimited input:checked + .plan-card-content::after {
            background: #8b5cf6; border-color: #8b5cf6; box-shadow: inset 0 0 0 3px #ffffff;
        }

        .plan-name { display: block; font-weight: 700; color: var(--text-dark); font-size: 1rem; letter-spacing: -0.2px; }
        .plan-meta { display: block; max-width: 100%; margin-top: auto; }
        .plan-capacity { display: block; font-size: 0.8rem; color: var(--text-gray); line-height: 1.34; }
        
        .plan-price {
            display: block; margin-top: 8px; font-size: 0.8rem; font-weight: 700; color: #1d4ed8;
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 999px;
            width: fit-content; padding: 4px 10px;
        }

        .email-row { display: flex; gap: 10px; }

        /* Updated OTP Container */
        .otp-group {
            display: none; background: #f8fafc; padding: 16px; border-radius: 12px;
            border: 1px solid #e2e8f0; margin-bottom: 20px;
        }
        .otp-row { display: flex; gap: 10px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 12px 20px; border-radius: 10px; font-weight: 600; text-decoration: none;
            transition: all 0.2s ease; cursor: pointer; border: none; font-family: inherit; font-size: 0.95rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3); }
        
        /* Updated Outline Button */
        .btn-outline { background: #ffffff; border: 1px solid #cbd5e1; color: var(--text-dark); }
        .btn-outline:hover { background: #f1f5f9; border-color: #94a3b8; }

        .btn-block { width: 100%; padding: 14px; font-size: 1rem; }

        .success-view { text-align: center; padding: 28px 4px; }
        .success-view .material-symbols-rounded { font-size: 64px; color: #10b981; margin-bottom: 16px; }
        .success-view h3 { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; color: var(--text-dark); }
        .success-view p { color: var(--text-gray); font-size: 1rem; margin-bottom: 28px; }

        .alert-error {
            background: #fef2f2; color: #b91c1c; padding: 12px 16px; border-radius: 10px;
            border: 1px solid #fecaca; margin-bottom: 20px; font-size: 0.9rem; font-weight: 600;
        }

        @media (max-width: 980px) { .demo-layout { grid-template-columns: 1fr; } .demo-intro { padding: 24px 20px; } }
        @media (max-width: 760px) {
            body { padding: 78px 14px 24px; }
            .demo-card { padding: 24px 20px; }
            .form-row, .email-row, .otp-row { flex-direction: column; gap: 12px;}
            .plan-grid { grid-template-columns: 1fr; }
            .back-btn { top: 12px; left: 12px; padding: 8px 13px; font-size: 0.82rem; }
        }
        @media (max-width: 1024px) { .plan-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @keyframes spin { 100% { transform: rotate(360deg); } }

    </style>
    <link rel="stylesheet" href="demo.css?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/demo.css')); ?>">
    <link rel="stylesheet" href="sarah/sarah-chatbot.css?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/sarah/sarah-chatbot.css')); ?>">
</head>
<body>

    <a href="index.php" class="back-btn" id="back-btn">
        <span class="material-symbols-rounded">arrow_back</span>
        Back to Home
    </a>
    <button type="button" class="theme-toggle-btn theme-toggle-floating" id="public-theme-toggle" aria-label="Switch to dark mode">
        <span class="material-symbols-rounded theme-toggle-icon">dark_mode</span>
        <span class="theme-toggle-label">Dark</span>
    </button>
    <div class="demo-wrapper">
        <div class="demo-layout">
            <aside class="demo-intro">
                <div class="page-brand">
                    <div class="logo">
                        <span class="material-symbols-rounded">account_balance</span>
                        <span class="logo-text">MicroFin</span>
                    </div>
                    <p>Cloud core banking for modern MFIs</p>
                </div>
                <span class="intro-badge">
                    <span class="material-symbols-rounded" style="font-size: 15px;">rocket_launch</span>
                    <?php echo $is_talk_to_expert ? 'Expert Guidance' : 'Get Started'; ?>
                </span>
                <h1 class="intro-title"><?php echo $is_talk_to_expert ? 'Talk to a specialist before you commit.' : 'Bring your institution online with confidence.'; ?></h1>
                <p class="intro-sub"><?php echo $is_talk_to_expert ? 'Share your institution details and one of our experts will guide you through the best onboarding path.' : 'Complete this quick onboarding request and our team will start provisioning your isolated tenant environment.'; ?></p>
                <ul class="intro-list">
                    <li><span class="material-symbols-rounded">verified_user</span><span>Dedicated tenant isolation with strict data boundaries.</span></li>
                    <li><span class="material-symbols-rounded">bolt</span><span>Rapid setup with guided onboarding and migration assistance.</span></li>
                    <li><span class="material-symbols-rounded">support_agent</span><span>Hands-on support from implementation through go-live.</span></li>
                </ul>
                <p class="intro-note">Average review time for application request is within 24 hours.</p>
            </aside>

            <div class="demo-card">
            <?php if ($form_success): ?>
                <div class="success-view">
                    <span class="material-symbols-rounded">check_circle</span>
                    <h3>Request Received!</h3>
                    <p>Thanks for your interest. A MicroFin sales engineer will contact you shortly.</p>
                    <a href="index.php" class="btn btn-primary">
                        <span class="material-symbols-rounded" style="font-size:18px; margin-right:6px;">home</span>
                        Back to Home
                    </a>
                </div>
            <?php else: ?>
                <h2><?php echo $is_talk_to_expert ? 'Talk to an Expert' : 'Apply Now'; ?></h2>
                <p class="subtitle"><?php echo $is_talk_to_expert ? 'Fill out the form and our team will connect you with a specialist.' : 'Fill out the form and our team will get back to you.'; ?></p>

                <?php if ($form_error): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($form_error); ?></div>
                <?php endif; ?>

                <form id="demo-form" method="POST" action="demo.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="request_demo">
                    <input type="hidden" name="flow_mode" value="<?php echo $is_talk_to_expert ? 'talk-to-expert' : 'apply-now'; ?>">

                    <div class="form-group">
                        <label>Institution Name <span class="text-danger">*</span></label>
                        <input type="text" class="input-field" name="institution_name" placeholder="e.g. Sacred Hearts Savings" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="text-danger">*</span></label>
                            <input type="text" class="input-field" name="contact_first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="input-field" name="contact_last_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>M.I.</label>
                            <input type="text" class="input-field" name="contact_mi" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" class="input-field" name="contact_suffix" placeholder="e.g. Jr, Sr" maxlength="10">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" class="input-field" name="contact_number" placeholder="e.g. 09171234567">
                    </div>

                    <div class="form-group">
                        <label>Company Location</label>
                        <div class="location-search-wrap">
                            <input type="text" class="input-field" name="location" id="company_location" placeholder="e.g. 123 Main St, Makati City, Metro Manila" autocomplete="off">
                            <div class="location-suggestions" id="company-location-suggestions" role="listbox" aria-label="Company location suggestions"></div>
                        </div>
                        <small class="location-helper">Type your company address or click the map below to pin the exact location.</small>
                        <div class="location-map-wrap" id="company-location-map-wrap">
                            <div
                                id="company-location-map"
                                class="location-map-frame"
                                aria-label="Company location map picker"></div>
                            <div class="location-map-actions">
                                <span class="location-map-status" id="company-location-map-status">Click anywhere on the map to pin your company address.</span>
                            </div>
                        </div>
                    </div>

                    <?php if ($is_talk_to_expert): ?>
                    <div class="form-group">
                        <label>Category of Concern <span class="text-danger">*</span></label>
                        <select class="input-field select-field" name="concern_category" required>
                            <option value="" disabled selected>Select a category</option>
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Pricing & Billing">Pricing & Billing</option>
                            <option value="Technical Integration">Technical Integration</option>
                            <option value="Security & Compliance">Security & Compliance</option>
                            <option value="Custom Features">Custom Features</option>
                            <option value="Migration">Migration from existing system</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (!$is_talk_to_expert): ?>
                    <div class="form-group">
                        <label>Subscription Plan <span class="text-danger">*</span></label>
                        <p class="plan-helper">Select one plan to match your expected operational scale.</p>
                        <div class="plan-grid">
                            <label class="plan-option plan-starter">
                                <input type="radio" name="plan_tier" value="Starter" required>
                                <span class="plan-card-content">
                                    <span class="plan-name">Starter</span>
                                    <span class="plan-meta">
                                        <span class="plan-capacity">Up to 1,000 clients and 250 users</span>
                                        <span class="plan-price">Php 4,999/mo</span>
                                    </span>
                                </span>
                            </label>

                            <label class="plan-option plan-pro">
                                <input type="radio" name="plan_tier" value="Pro">
                                <span class="plan-card-content">
                                    <span class="plan-name">Pro</span>
                                    <span class="plan-meta">
                                        <span class="plan-capacity">Up to 5,000 clients and 2,000 users</span>
                                        <span class="plan-price">Php 14,999/mo</span>
                                    </span>
                                </span>
                            </label>

                            <label class="plan-option plan-enterprise">
                                <input type="radio" name="plan_tier" value="Enterprise">
                                <span class="plan-card-content">
                                    <span class="plan-name">Enterprise</span>
                                    <span class="plan-meta">
                                        <span class="plan-capacity">Up to 10,000 clients and 5,000 users</span>
                                        <span class="plan-price">Php 19,999/mo</span>
                                    </span>
                                </span>
                            </label>

                            <label class="plan-option plan-option-wide plan-unlimited">
                                <input type="radio" name="plan_tier" value="Unlimited">
                                <span class="plan-card-content">
                                    <span class="plan-name">Unlimited</span>
                                    <span class="plan-meta">
                                        <span class="plan-capacity">Unlimited clients and users</span>
                                        <span class="plan-price">Php 29,999/mo</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Business Email <span class="text-danger">*</span></label>
                        <div class="email-row">
                            <input type="email" class="input-field" name="company_email" id="work_email" placeholder="ceo@institution.com" required>
                            <button type="button" id="btn-send-otp" class="btn btn-outline otp-action-btn">Send OTP</button>
                        </div>
                        <small id="email-help-text" class="form-helper-text">Requires verification before submission.</small>
                    </div>

                    <div class="otp-group" id="otp-group">
                        <div class="otp-group-header">
                            <label class="otp-label">Enter 6-Digit OTP <span class="text-danger">*</span></label>
                            <span id="otp-countdown" class="otp-countdown"></span>
                        </div>
                        <div class="otp-row">
                            <input type="text" class="input-field" name="otp_code" id="otp_code" placeholder="123456" maxlength="6">
                            <button type="button" id="btn-verify-otp" class="btn btn-primary otp-action-btn">Verify</button>
                        </div>
                        <div id="otp-status-msg" class="otp-status-msg"></div>
                        <input type="hidden" name="is_otp_verified" id="is_otp_verified" value="0">
                    </div>

                    <?php if (!$is_talk_to_expert): ?>
                    <div class="form-group">
                        <label>Proof of Legitimacy Documents <span class="text-danger">*</span></label>
                        <input type="file" class="input-field legitimacy-slot legitimacy-file-input" name="legitimacy_document_1" id="legitimacy_document_1" data-slot="1" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp" required>
                        <input type="file" class="input-field legitimacy-slot legitimacy-file-input" name="legitimacy_document_2" id="legitimacy_document_2" data-slot="2" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp" style="display: none;">
                        <input type="file" class="input-field legitimacy-slot legitimacy-file-input" name="legitimacy_document_3" id="legitimacy_document_3" data-slot="3" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp" style="display: none;">
                        <input type="file" class="input-field legitimacy-slot legitimacy-file-input" name="legitimacy_document_4" id="legitimacy_document_4" data-slot="4" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp" style="display: none;">
                        <input type="file" class="input-field legitimacy-slot legitimacy-file-input" name="legitimacy_document_5" id="legitimacy_document_5" data-slot="5" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp" style="display: none;">
                        <small class="form-helper-text">Upload 1 to 5 files (business permit, DTI, SEC, and related proof).</small>
                    </div>
                    <?php endif; ?>

                    <div class="form-group policy-consent-group">
                        <label class="policy-consent-label">
                            <input type="checkbox" name="agree_terms" required style="margin-top: 4px; accent-color: var(--primary);">
                            <span class="policy-copy">
                                By submitting this request, I agree to the
                                <a href="#" id="open-tos-modal" class="policy-link">Terms of Service</a>
                                and <a href="#" id="open-pp-modal" class="policy-link">Privacy Policy</a>.
                                I understand that my information will be handled securely and according to these policies.
                            </span>
                        </label>
                    </div>

                    <button type="submit" id="btn-final-submit" class="btn btn-primary btn-block" style="opacity: 0.5; pointer-events: none; margin-top: 24px;"><?php echo $is_talk_to_expert ? 'Inquire' : 'Apply Now'; ?></button>
                    <small id="form-block-note" style="display: block; text-align: center; margin-top: 12px; color: #ef4444; font-weight: 500;">Verify your email to enable submission.</small>
                </form>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/sarah/widget.php'; ?>

    <script>
    (function () {
        const root = document.documentElement;
        const themeToggle = document.getElementById('public-theme-toggle');
        const storageKey = 'microfin_public_theme';

        const normalizeTheme = (value) => value === 'dark' ? 'dark' : 'light';

        const syncThemeToggle = (theme) => {
            if (!themeToggle) {
                return;
            }

            const nextTheme = theme === 'dark' ? 'light' : 'dark';
            const icon = themeToggle.querySelector('.theme-toggle-icon');
            const label = themeToggle.querySelector('.theme-toggle-label');
            themeToggle.setAttribute('aria-label', `Switch to ${nextTheme} mode`);
            themeToggle.setAttribute('title', `Switch to ${nextTheme} mode`);
            if (icon) {
                icon.textContent = nextTheme === 'dark' ? 'light_mode' : 'dark_mode';
            }
            if (label) {
                label.textContent = nextTheme === 'dark' ? 'Light' : 'Dark';
            }
        };

        const applyTheme = (theme) => {
            const resolvedTheme = normalizeTheme(theme);
            root.setAttribute('data-theme', resolvedTheme);
            syncThemeToggle(resolvedTheme);
            try {
                localStorage.setItem(storageKey, resolvedTheme);
            } catch (error) {}
        };

        applyTheme(root.getAttribute('data-theme') || 'light');

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const currentTheme = normalizeTheme(root.getAttribute('data-theme'));
                applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });
        }
    }());
    </script>

    <div id="tos-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index:9999; overflow-y:auto; padding:40px 20px;">
        <div style="background:var(--card-bg); border:1px solid var(--card-border); border-radius:18px; max-width:680px; margin:0 auto; padding:40px; color:var(--text-muted); line-height:1.7; box-shadow: var(--card-shadow);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                <h2 style="margin:0; font-size:1.4rem; color:var(--text-dark); font-weight: 800;">Terms of Service &amp; Refund Policy</h2>
                <button id="close-tos-modal" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.8rem; line-height:1; transition: color 0.2s;">&times;</button>
            </div>
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:24px;">Effective Date: <?php echo date('F d, Y'); ?> &mdash; MicroFin Platform</p>

            <h3 style="color:var(--text-dark); font-size:1rem; margin:20px 0 8px;">1. Acceptance of Terms</h3>
            <p style="font-size:0.9rem;">By submitting an application to use the MicroFin platform, you agree to be bound by these Terms of Service. If you do not agree, do not proceed with your application.</p>

            <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">2. Subscription &amp; Payment Rules</h3>
            <p style="font-size:0.9rem;">Upon approval and completion of your billing setup, the following payment rules apply:</p>
            <ul style="font-size:0.9rem; padding-left:20px; margin-top:8px;">
                <li><strong>Initial Activation Charge:</strong> Your first charge is the full monthly subscription fee paid immediately when your account is activated.</li>
                <li><strong>Recurring Billing:</strong> After activation, your subscription renews automatically every 30 days using your saved payment method.</li>
                <li><strong>Automatic Deduction:</strong> Payments are automatically charged to your registered payment method. It is your responsibility to ensure sufficient funds are available.</li>
                <li><strong>Late Payment:</strong> Failure to complete payment may result in suspension of your tenant account until the outstanding balance is settled.</li>
                <li><strong>Plan Changes:</strong> Upgrades or downgrades follow the subscription change settings applied to your account.</li>
                <li><strong>Billing Disputes:</strong> Any billing disputes must be raised within 30 days of the charge date by contacting MicroFin support.</li>
            </ul>

            <h3 style="color:var(--danger); font-size:1rem; margin:24px 0 8px;">3. No-Refund Policy</h3>
            <p style="font-size:0.9rem;">All subscription fees paid to MicroFin are <strong>strictly non-refundable</strong>. This includes, but is not limited to:</p>
            <ul style="font-size:0.9rem; padding-left:20px; margin-top:8px;">
                <li>Initial activation charges upon account activation.</li>
                <li>Monthly recurring subscription fees, regardless of usage during the billing period.</li>
                <li>Fees charged during any period prior to account suspension or cancellation.</li>
                <li>Any charges already billed before cancellation or deactivation.</li>
            </ul>
            <p style="font-size:0.9rem; margin-top:8px;">We encourage you to evaluate the platform thoroughly during any trial or demo period before committing to a paid subscription.</p>

            <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">4. Account Termination</h3>
            <p style="font-size:0.9rem;">MicroFin reserves the right to terminate or suspend any account that violates these terms, fails to pay subscription fees, or engages in fraudulent activity. Termination does not entitle the tenant to a refund of any previously paid fees.</p>

            <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">5. Data &amp; Privacy</h3>
            <p style="font-size:0.9rem;">Your data is stored in an isolated tenant environment. MicroFin will not share or sell your data to third parties. Card details are encrypted using AES-256 and CVV is never stored. All transactions are logged for compliance and audit purposes.</p>

            <div style="margin-top:32px; text-align:right; border-top: 1px solid var(--card-border); padding-top: 24px;">
                <button id="close-tos-modal-btn" style="background:linear-gradient(135deg,var(--primary),var(--purple-core)); color:#fff; border:none; border-radius:999px; padding:12px 28px; font-weight:600; cursor:pointer; box-shadow: 0 12px 24px -18px rgba(var(--primary-rgb), 0.38);">I Understand</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const tosBackdrop = document.getElementById('tos-modal-backdrop');
        const openTos = document.getElementById('open-tos-modal');
        const closeTos = document.getElementById('close-tos-modal');
        const closeTosBt = document.getElementById('close-tos-modal-btn');
        if (openTos) openTos.addEventListener('click', e => { e.preventDefault(); tosBackdrop.style.display = 'block'; document.body.style.overflow='hidden'; });
        if (closeTos) closeTos.addEventListener('click', () => { tosBackdrop.style.display = 'none'; document.body.style.overflow=''; });
        if (closeTosBt) closeTosBt.addEventListener('click', () => { tosBackdrop.style.display = 'none'; document.body.style.overflow=''; });
        if (tosBackdrop) tosBackdrop.addEventListener('click', e => { if (e.target === tosBackdrop) { tosBackdrop.style.display='none'; document.body.style.overflow=''; } });
    });
    </script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const demoForm = document.getElementById('demo-form');
        if (!demoForm) return;

        const legitimacyInputs = Array.from(document.querySelectorAll('.legitimacy-slot'));
        const refreshLegitimacySlots = () => {
            if (!legitimacyInputs.length) return;

            let revealUntil = 1;
            for (let i = 0; i < legitimacyInputs.length; i++) {
                const current = legitimacyInputs[i];
                const slotNumber = i + 1;
                if (slotNumber > revealUntil) {
                    current.style.display = 'none';
                    current.value = '';
                } else {
                    current.style.display = 'block';
                }

                if (current.files && current.files.length > 0 && slotNumber === revealUntil && revealUntil < legitimacyInputs.length) {
                    revealUntil++;
                }
            }
        };

        legitimacyInputs.forEach((input) => {
            input.addEventListener('change', refreshLegitimacySlots);
        });
        refreshLegitimacySlots();

        // OTP Elements
        const btnSendOtp = document.getElementById('btn-send-otp');
        const btnVerifyOtp = document.getElementById('btn-verify-otp');
        const otpGroup = document.getElementById('otp-group');
        const emailInput = document.getElementById('work_email');
        const otpInput = document.getElementById('otp_code');
        const otpMsg = document.getElementById('otp-status-msg');
        const btnFinalSubmit = document.getElementById('btn-final-submit');
        const formBlockNote = document.getElementById('form-block-note');
        const isOtpVerified = document.getElementById('is_otp_verified');
        const emailHelpText = document.getElementById('email-help-text');
        const otpCountdown = document.getElementById('otp-countdown');
        const companyLocationInput = document.getElementById('company_location');
        const companyLocationMapWrap = document.getElementById('company-location-map-wrap');
        const companyLocationMap = document.getElementById('company-location-map');
        const companyLocationMapLink = document.getElementById('company-location-map-link');
        const companyLocationMapStatus = document.getElementById('company-location-map-status');
        const companyLocationSuggestions = document.getElementById('company-location-suggestions');
        const companyLocationSearchWrap = companyLocationInput ? companyLocationInput.closest('.location-search-wrap') : null;
        let companyLocationLeafletMap = null;
        let companyLocationMarker = null;
        let companyLocationSearchTimer = null;
        let companyLocationSearchController = null;
        let companyLocationReverseController = null;
        let companyLocationSuppressSearch = false;
        let companyLocationLastPinned = null;
        let companyLocationResults = [];
        let companyLocationActiveIndex = -1;

        const setCompanyLocationStatus = (message) => {
            if (companyLocationMapStatus) {
                companyLocationMapStatus.textContent = message;
            }
        };

        const updateCompanyLocationLink = (queryOrCoords) => {
            if (!companyLocationMapLink) {
                return;
            }

            if (!queryOrCoords) {
                companyLocationMapLink.href = '#';
                return;
            }

            companyLocationMapLink.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(queryOrCoords)}`;
        };

        const closeCompanyLocationSuggestions = (clearResults = false) => {
            if (!companyLocationSuggestions) {
                return;
            }

            companyLocationSuggestions.classList.remove('is-open');
            companyLocationSuggestions.innerHTML = '';
            companyLocationActiveIndex = -1;

            if (clearResults) {
                companyLocationResults = [];
            }
        };

        const setActiveCompanyLocationSuggestion = (index) => {
            if (!companyLocationSuggestions || !companyLocationResults.length) {
                companyLocationActiveIndex = -1;
                return;
            }

            const suggestionButtons = Array.from(companyLocationSuggestions.querySelectorAll('.location-suggestion'));
            if (!suggestionButtons.length) {
                companyLocationActiveIndex = -1;
                return;
            }

            if (index < 0) {
                index = suggestionButtons.length - 1;
            } else if (index >= suggestionButtons.length) {
                index = 0;
            }

            companyLocationActiveIndex = index;

            suggestionButtons.forEach((button, buttonIndex) => {
                const isActive = buttonIndex === index;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');

                if (isActive) {
                    button.scrollIntoView({ block: 'nearest' });
                }
            });
        };

        const focusCompanyLocationPoint = (lat, lng, zoomLevel = 16) => {
            if (!companyLocationLeafletMap) {
                return;
            }

            companyLocationLastPinned = { lat, lng };
            companyLocationMapWrap?.classList.add('is-visible');
            ensureCompanyLocationMarker(lat, lng);
            companyLocationLeafletMap.setView([lat, lng], zoomLevel);
        };

        const selectCompanyLocationSuggestion = (result) => {
            if (!result) {
                return;
            }

            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            const displayName = typeof result.display_name === 'string' ? result.display_name.trim() : '';

            if (companyLocationInput && displayName !== '') {
                companyLocationSuppressSearch = true;
                companyLocationInput.value = displayName;
                companyLocationSuppressSearch = false;
            }

            focusCompanyLocationPoint(lat, lng, 16);
            updateCompanyLocationLink(displayName || `${lat},${lng}`);
            setCompanyLocationStatus(displayName ? `Selected: ${displayName}` : `Pinned coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
            closeCompanyLocationSuggestions();
        };

        const renderCompanyLocationSuggestions = (results, emptyMessage = 'No matching addresses found yet.') => {
            if (!companyLocationSuggestions) {
                return;
            }

            companyLocationSuggestions.innerHTML = '';
            companyLocationResults = Array.isArray(results) ? results : [];
            companyLocationActiveIndex = -1;

            if (!companyLocationResults.length) {
                const emptyState = document.createElement('div');
                emptyState.className = 'location-suggestion-empty';
                emptyState.textContent = emptyMessage;
                companyLocationSuggestions.appendChild(emptyState);
                companyLocationSuggestions.classList.add('is-open');
                return;
            }

            companyLocationResults.forEach((result, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'location-suggestion';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');

                const displayName = typeof result.display_name === 'string' ? result.display_name.trim() : '';
                const displayParts = displayName
                    .split(',')
                    .map((part) => part.trim())
                    .filter(Boolean);

                const title = document.createElement('span');
                title.className = 'location-suggestion-title';
                title.textContent = displayParts[0] || displayName || 'Suggested location';

                const subtitle = document.createElement('span');
                subtitle.className = 'location-suggestion-sub';
                subtitle.textContent = displayParts.slice(1).join(', ') || 'Click to pin this address on the map.';

                button.appendChild(title);
                button.appendChild(subtitle);
                button.addEventListener('mouseenter', () => {
                    setActiveCompanyLocationSuggestion(index);
                });
                button.addEventListener('click', () => {
                    selectCompanyLocationSuggestion(result);
                });

                companyLocationSuggestions.appendChild(button);
            });

            companyLocationSuggestions.classList.add('is-open');
        };

        const ensureCompanyLocationMarker = (lat, lng) => {
            if (!companyLocationLeafletMap || typeof L === 'undefined') {
                return;
            }

            if (!companyLocationMarker) {
                companyLocationMarker = L.marker([lat, lng], { draggable: true }).addTo(companyLocationLeafletMap);
                companyLocationMarker.on('dragend', () => {
                    const dragged = companyLocationMarker.getLatLng();
                    handlePinnedLocation(dragged.lat, dragged.lng, true);
                });
            } else {
                companyLocationMarker.setLatLng([lat, lng]);
            }
        };

        const reverseGeocodeCompanyLocation = async (lat, lng, updateInput = true) => {
            if (companyLocationReverseController) {
                companyLocationReverseController.abort();
            }

            companyLocationReverseController = new AbortController();

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`,
                    {
                        signal: companyLocationReverseController.signal,
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error('Reverse geocoding failed');
                }

                const data = await response.json();
                const resolvedAddress = (data && typeof data.display_name === 'string') ? data.display_name.trim() : '';

                if (updateInput && resolvedAddress && companyLocationInput) {
                    companyLocationSuppressSearch = true;
                    companyLocationInput.value = resolvedAddress;
                    companyLocationSuppressSearch = false;
                }

                closeCompanyLocationSuggestions(true);
                updateCompanyLocationLink(resolvedAddress || `${lat},${lng}`);
                setCompanyLocationStatus(resolvedAddress ? `Pinned: ${resolvedAddress}` : `Pinned coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    closeCompanyLocationSuggestions(true);
                    updateCompanyLocationLink(`${lat},${lng}`);
                    setCompanyLocationStatus(`Pinned coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
                }
            }
        };

        const handlePinnedLocation = (lat, lng, shouldReverseGeocode = false) => {
            if (!companyLocationLeafletMap) {
                return;
            }

            focusCompanyLocationPoint(lat, lng, Math.max(companyLocationLeafletMap.getZoom(), 16));
            closeCompanyLocationSuggestions(true);
            updateCompanyLocationLink(`${lat},${lng}`);
            setCompanyLocationStatus(`Pinned coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);

            if (shouldReverseGeocode) {
                reverseGeocodeCompanyLocation(lat, lng, true);
            }
        };

        const searchCompanyLocationAddress = async (address) => {
            if (!address || address.length < 3) {
                closeCompanyLocationSuggestions(true);
                setCompanyLocationStatus('Click anywhere on the map to pin your company address.');
                updateCompanyLocationLink('');
                return;
            }

            if (companyLocationSearchController) {
                companyLocationSearchController.abort();
            }

            companyLocationSearchController = new AbortController();
            renderCompanyLocationSuggestions([], 'Searching company locations...');
            setCompanyLocationStatus('Searching for your company address...');

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=6&addressdetails=1&q=${encodeURIComponent(address)}`,
                    {
                        signal: companyLocationSearchController.signal,
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error('Geocoding failed');
                }

                const results = await response.json();
                if (!Array.isArray(results) || !results.length) {
                    renderCompanyLocationSuggestions([], 'No matching addresses yet. Try a more specific company location.');
                    updateCompanyLocationLink(address);
                    setCompanyLocationStatus('No exact match yet. You can still click the map to pin the correct address.');
                    return;
                }

                renderCompanyLocationSuggestions(results);
                updateCompanyLocationLink(address);
                setCompanyLocationStatus('Choose the best match from the dropdown, or pin the map manually.');
            } catch (error) {
                if (error.name !== 'AbortError') {
                    renderCompanyLocationSuggestions([], 'We could not load suggestions right now.');
                    updateCompanyLocationLink(address);
                    setCompanyLocationStatus('We could not find that address automatically. You can click the map to pin it manually.');
                }
            }
        };

        if (companyLocationInput && companyLocationMap && typeof L !== 'undefined') {
            companyLocationLeafletMap = L.map(companyLocationMap, {
                zoomControl: true,
                scrollWheelZoom: false
            }).setView([14.5995, 120.9842], 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(companyLocationLeafletMap);

            companyLocationLeafletMap.on('click', (event) => {
                handlePinnedLocation(event.latlng.lat, event.latlng.lng, true);
            });

            companyLocationMapWrap?.classList.add('is-visible');
            updateCompanyLocationLink('');
            setCompanyLocationStatus('Click anywhere on the map to pin your company address.');

            const queueCompanyLocationSearch = () => {
                if (companyLocationSuppressSearch) {
                    return;
                }

                const value = companyLocationInput.value.trim();
                clearTimeout(companyLocationSearchTimer);

                if (!value) {
                    closeCompanyLocationSuggestions(true);
                    updateCompanyLocationLink('');
                    setCompanyLocationStatus('Click anywhere on the map to pin your company address.');
                    return;
                }

                updateCompanyLocationLink(value);
                companyLocationSearchTimer = window.setTimeout(() => {
                    searchCompanyLocationAddress(value);
                }, 650);
            };

            companyLocationInput.addEventListener('input', queueCompanyLocationSearch);
            companyLocationInput.addEventListener('change', queueCompanyLocationSearch);
            companyLocationInput.addEventListener('focus', () => {
                const value = companyLocationInput.value.trim();
                if (value.length >= 3) {
                    queueCompanyLocationSearch();
                }
            });
            companyLocationInput.addEventListener('keydown', (event) => {
                const suggestionsOpen = companyLocationSuggestions && companyLocationSuggestions.classList.contains('is-open');
                const hasResults = companyLocationResults.length > 0;

                if (!suggestionsOpen) {
                    if (event.key === 'Escape') {
                        closeCompanyLocationSuggestions();
                    }
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    if (hasResults) {
                        setActiveCompanyLocationSuggestion(companyLocationActiveIndex + 1);
                    }
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (hasResults) {
                        setActiveCompanyLocationSuggestion(companyLocationActiveIndex - 1);
                    }
                    return;
                }

                if (event.key === 'Enter') {
                    if (hasResults) {
                        event.preventDefault();
                        const selectedIndex = companyLocationActiveIndex >= 0 ? companyLocationActiveIndex : 0;
                        selectCompanyLocationSuggestion(companyLocationResults[selectedIndex]);
                    }
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeCompanyLocationSuggestions();
                }
            });

            document.addEventListener('click', (event) => {
                if (!companyLocationSearchWrap || companyLocationSearchWrap.contains(event.target)) {
                    return;
                }

                closeCompanyLocationSuggestions();
            });

            if (companyLocationInput.value.trim()) {
                queueCompanyLocationSearch();
            }
        }

        // OTP expiry countdown (5 minutes)
        let otpExpiryInterval = null;
        function startOtpExpiry() {
            if (otpExpiryInterval) clearInterval(otpExpiryInterval);
            let remaining = 300; // 5 minutes
            const updateExpiry = () => {
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                if (otpCountdown) {
                    if (remaining > 60) {
                        otpCountdown.style.color = '#b45309';
                        otpCountdown.innerText = `Expires in ${mins}:${secs.toString().padStart(2, '0')}`;
                    } else if (remaining > 0) {
                        otpCountdown.style.color = '#ef4444';
                        otpCountdown.innerText = `Expires in ${remaining}s`;
                    } else {
                        otpCountdown.style.color = '#ef4444';
                        otpCountdown.innerText = 'Expired';
                        otpMsg.style.color = '#ef4444';
                        otpMsg.innerText = 'OTP expired. Please request a new one.';
                        btnSendOtp.disabled = false;
                        btnSendOtp.innerHTML = 'Resend OTP';
                        clearInterval(otpExpiryInterval);
                        otpExpiryInterval = null;

                        // Mark OTP as expired in database
                        const expireData = new FormData();
                        expireData.append('action', 'expire_otp');
                        expireData.append('email', emailInput.value.trim());
                        fetch('api/api_demo.php', { method: 'POST', body: expireData });
                    }
                }
                remaining--;
            };
            updateExpiry();
            otpExpiryInterval = setInterval(updateExpiry, 1000);
        }

        // Send OTP
        if (btnSendOtp) {
            btnSendOtp.addEventListener('click', () => {
                const email = emailInput.value.trim();
                if (!email) { alert("Please enter a valid business email first."); return; }

                btnSendOtp.disabled = true;
                btnSendOtp.innerHTML = 'Sending...';

                // Show hint after 30 seconds
                const slowHintTimer = setTimeout(() => {
                    if (emailHelpText) {
                        emailHelpText.style.color = '#b45309';
                        emailHelpText.innerText = 'Still connecting... please wait.';
                    }
                }, 30000);

                // Abort after 60 seconds
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);

                const formData = new FormData();
                formData.append('action', 'send_otp');
                formData.append('email', email);

                fetch('api/api_demo.php', { method: 'POST', body: formData, signal: controller.signal })
                .then(res => res.json())
                .then(data => {
                    clearTimeout(slowHintTimer);
                    clearTimeout(timeoutId);

                    if (data.success) {
                        otpGroup.style.display = 'block';
                        btnSendOtp.innerHTML = 'OTP Sent';
                        btnSendOtp.classList.remove('btn-outline');
                        btnSendOtp.style.backgroundColor = '#10b981';
                        btnSendOtp.style.color = 'white';
                        btnSendOtp.style.borderColor = '#10b981';
                        otpMsg.style.color = '#10b981';

                        otpMsg.innerText = data.message || 'OTP sent.';

                        if (emailHelpText) {
                            emailHelpText.style.color = '#10b981';
                            emailHelpText.innerText = 'OTP sent! Check your inbox.';
                        }
                        startOtpExpiry(); // Start 5-minute countdown
                    } else {
                        // Failed - allow immediate retry
                        if (emailHelpText) {
                            emailHelpText.style.color = '#ef4444';
                            emailHelpText.innerText = data.message;
                        }
                        btnSendOtp.disabled = false;
                        btnSendOtp.innerHTML = 'Resend OTP';
                    }
                })
                .catch((err) => {
                    clearTimeout(slowHintTimer);
                    clearTimeout(timeoutId);
                    if (emailHelpText) {
                        emailHelpText.style.color = '#ef4444';
                        if (err.name === 'AbortError') {
                            emailHelpText.innerText = 'Request timed out. Please try again.';
                        } else {
                            emailHelpText.innerText = 'Connection error. Please try again.';
                        }
                    }
                    btnSendOtp.disabled = false;
                    btnSendOtp.innerHTML = 'Resend OTP';
                });
            });
        }

        // Verify OTP
        if (btnVerifyOtp) {
            btnVerifyOtp.addEventListener('click', () => {
                const email = emailInput.value.trim();
                const code = otpInput.value.trim();
                if (code.length !== 6) {
                    otpMsg.style.color = '#ef4444';
                    otpMsg.innerText = 'Please enter a valid 6-digit OTP.';
                    return;
                }

                btnVerifyOtp.disabled = true;
                btnVerifyOtp.innerHTML = 'Verifying...';

                const formData = new FormData();
                formData.append('action', 'verify_otp');
                formData.append('email', email);
                formData.append('otp_code', code);

                fetch('api/api_demo.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Stop expiry countdown and show verified
                        if (otpExpiryInterval) {
                            clearInterval(otpExpiryInterval);
                            otpExpiryInterval = null;
                        }
                        if (otpCountdown) {
                            otpCountdown.style.color = '#10b981';
                            otpCountdown.innerText = 'Verified';
                        }
                        btnVerifyOtp.innerHTML = 'Verified';
                        btnVerifyOtp.style.backgroundColor = '#10b981';
                        btnVerifyOtp.style.borderColor = '#10b981';
                        otpMsg.style.color = '#10b981';
                        otpMsg.innerText = data.message;
                        emailInput.readOnly = true;
                        otpInput.readOnly = true;
                        isOtpVerified.value = '1';
                        btnFinalSubmit.style.opacity = '1';
                        btnFinalSubmit.style.pointerEvents = 'auto';
                        formBlockNote.style.color = '#10b981';
                        formBlockNote.innerText = 'You may now submit your request.';
                    } else {
                        btnVerifyOtp.disabled = false;
                        btnVerifyOtp.innerHTML = 'Verify';
                        otpMsg.style.color = '#ef4444';
                        otpMsg.innerText = data.message;
                    }
                })
                .catch(() => { btnVerifyOtp.disabled = false; btnVerifyOtp.innerHTML = 'Verify'; });
            });
        }

        // Submit guard
        demoForm.addEventListener('submit', (e) => {
            if (isOtpVerified.value === '0') {
                e.preventDefault();
                alert("Please verify your email with the OTP before submitting.");
                return;
            }
            const submitBtn = demoForm.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="material-symbols-rounded" style="animation: spin 1s linear infinite; font-size: 18px; margin-right: 8px; vertical-align: middle;">sync</span> Submitting...';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.8';
        });


    });
    </script>
    <script src="sarah/sarah-chatbot.js?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/sarah/sarah-chatbot.js')); ?>"></script>
</body>
</html>
