<?php

function mf_billing_get_setting(PDO $pdo, string $tenantId, string $settingKey, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
    $stmt->execute([$tenantId, $settingKey]);
    $value = $stmt->fetchColumn();
    return $value !== false ? trim((string)$value) : $default;
}

function mf_billing_set_setting(PDO $pdo, string $tenantId, string $settingKey, string $settingValue): void
{
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
        VALUES (?, ?, ?, 'Billing', 'String')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$tenantId, $settingKey, $settingValue]);
}

function mf_billing_get_contact(PDO $pdo, string $tenantId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            t.tenant_name,
            u.user_id,
            u.email,
            u.username,
            u.first_name,
            u.last_name,
            COALESCE(u.can_manage_billing, 0) AS can_manage_billing,
            COALESCE(u.user_type, '') AS user_type,
            COALESCE(u.status, '') AS user_status
        FROM users u
        INNER JOIN tenants t ON t.tenant_id = u.tenant_id
        WHERE u.tenant_id = ?
          AND u.deleted_at IS NULL
          AND TRIM(COALESCE(u.email, '')) <> ''
        ORDER BY
            COALESCE(u.can_manage_billing, 0) DESC,
            CASE WHEN u.user_type = 'Admin' THEN 0 ELSE 1 END,
            CASE WHEN u.status = 'Active' THEN 0 ELSE 1 END,
            u.user_id ASC
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mf_billing_contact_name(array $contact): string
{
    $fullName = trim((string)($contact['first_name'] ?? '') . ' ' . (string)($contact['last_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $username = trim((string)($contact['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    return 'Customer';
}

function mf_billing_money(float $amount): string
{
    return '&#8369;' . number_format($amount, 2);
}

function mf_billing_date_label(string $dateValue, string $format = 'F j, Y', string $default = 'N/A'): string
{
    $trimmed = trim($dateValue);
    if ($trimmed === '') {
        return $default;
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp === false) {
        return $default;
    }

    return date($format, $timestamp);
}

function mf_billing_send_due_soon_email(PDO $pdo, string $tenantId, array $details): string
{
    if (!function_exists('mf_send_brevo_email')) {
        return 'Brevo email helper is unavailable.';
    }

    $contact = mf_billing_get_contact($pdo, $tenantId);
    if (!$contact || trim((string)($contact['email'] ?? '')) === '') {
        return 'No tenant billing contact email found.';
    }

    $tenantName = htmlspecialchars((string)($contact['tenant_name'] ?? 'MicroFin Tenant'), ENT_QUOTES, 'UTF-8');
    $recipientName = htmlspecialchars(mf_billing_contact_name($contact), ENT_QUOTES, 'UTF-8');
    $planTier = htmlspecialchars((string)($details['plan_tier'] ?? 'Subscription'), ENT_QUOTES, 'UTF-8');
    $dueDate = mf_billing_date_label((string)($details['due_date'] ?? ''), 'F j, Y');
    $amountText = mf_billing_money((float)($details['amount'] ?? 0));

    $html = "
        <div style=\"font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;\">
            <h2 style=\"margin: 0 0 12px;\">Upcoming Subscription Payment</h2>
            <p>Hello {$recipientName},</p>
            <p>This is a reminder that your <strong>{$tenantName}</strong> subscription payment is coming up soon.</p>
            <div style=\"margin: 20px 0; padding: 16px; border: 1px solid #dbeafe; border-radius: 12px; background: #f8fbff;\">
                <p style=\"margin: 0 0 8px;\"><strong>Plan:</strong> {$planTier}</p>
                <p style=\"margin: 0 0 8px;\"><strong>Amount:</strong> {$amountText}</p>
                <p style=\"margin: 0;\"><strong>Scheduled charge date:</strong> {$dueDate}</p>
            </div>
            <p>Please make sure your saved billing method is up to date so your renewal can be processed smoothly.</p>
            <p style=\"margin-top: 24px;\">Thank you,<br>MicroFin Billing</p>
        </div>
    ";

    return mf_send_brevo_email((string)$contact['email'], "{$tenantName} - Upcoming Subscription Payment", $html);
}

function mf_billing_send_receipt_email(PDO $pdo, string $tenantId, array $details): string
{
    if (!function_exists('mf_send_brevo_email')) {
        return 'Brevo email helper is unavailable.';
    }

    $contact = mf_billing_get_contact($pdo, $tenantId);
    if (!$contact || trim((string)($contact['email'] ?? '')) === '') {
        return 'No tenant billing contact email found.';
    }

    $tenantName = htmlspecialchars((string)($contact['tenant_name'] ?? 'MicroFin Tenant'), ENT_QUOTES, 'UTF-8');
    $recipientName = htmlspecialchars(mf_billing_contact_name($contact), ENT_QUOTES, 'UTF-8');
    $planTier = htmlspecialchars((string)($details['plan_tier'] ?? 'Subscription'), ENT_QUOTES, 'UTF-8');
    $amountText = mf_billing_money((float)($details['amount'] ?? 0));
    $paymentDate = mf_billing_date_label((string)($details['payment_date'] ?? ''), 'F j, Y g:i A');
    $periodStart = mf_billing_date_label((string)($details['period_start'] ?? ''));
    $periodEnd = mf_billing_date_label((string)($details['period_end'] ?? ''));
    $nextBillingDate = mf_billing_date_label((string)($details['next_billing_date'] ?? ''));
    $invoiceNumber = htmlspecialchars((string)($details['invoice_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
    $paymentReference = htmlspecialchars((string)($details['payment_reference'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
    $paymentMethod = htmlspecialchars((string)($details['payment_method'] ?? 'Saved payment method'), ENT_QUOTES, 'UTF-8');

    $html = "
        <div style=\"font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;\">
            <h2 style=\"margin: 0 0 12px;\">Subscription Payment Receipt</h2>
            <p>Hello {$recipientName},</p>
            <p>Your payment for <strong>{$tenantName}</strong> has been successfully recorded. This email serves as your receipt.</p>
            <div style=\"margin: 20px 0; padding: 16px; border: 1px solid #dbeafe; border-radius: 12px; background: #f8fbff;\">
                <p style=\"margin: 0 0 8px;\"><strong>Plan:</strong> {$planTier}</p>
                <p style=\"margin: 0 0 8px;\"><strong>Amount paid:</strong> {$amountText}</p>
                <p style=\"margin: 0 0 8px;\"><strong>Payment date:</strong> {$paymentDate}</p>
                <p style=\"margin: 0 0 8px;\"><strong>Payment reference:</strong> {$paymentReference}</p>
                <p style=\"margin: 0 0 8px;\"><strong>Invoice number:</strong> {$invoiceNumber}</p>
                <p style=\"margin: 0 0 8px;\"><strong>Payment method:</strong> {$paymentMethod}</p>
                <p style=\"margin: 0 0 8px;\"><strong>Coverage period:</strong> {$periodStart} to {$periodEnd}</p>
                <p style=\"margin: 0;\"><strong>Next scheduled payment:</strong> {$nextBillingDate}</p>
            </div>
            <p style=\"margin-top: 24px;\">Thank you,<br>MicroFin Billing</p>
        </div>
    ";

    return mf_send_brevo_email((string)$contact['email'], "{$tenantName} - Payment Receipt {$invoiceNumber}", $html);
}
