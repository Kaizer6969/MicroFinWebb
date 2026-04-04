<?php
require_once __DIR__ . '/config.php';

function microfin_email_config(): array
{
    return [
        'provider' => 'brevo',
        'api_url' => 'https://api.brevo.com/v3/smtp/email',
        'api_key' => trim((string) microfin_config('BREVO_API_KEY', '')),
        'sender_email' => trim((string) microfin_config('BREVO_SENDER_EMAIL', '')),
        'sender_name' => trim((string) microfin_config('BREVO_SENDER_NAME', 'MicroFin')),
        'sandbox_mode' => microfin_bool_config('BREVO_SANDBOX_MODE', false),
        'app_base_url' => microfin_app_base_url(),
    ];
}

function microfin_email_is_configured(): bool
{
    $config = microfin_email_config();

    return $config['api_key'] !== '' && $config['sender_email'] !== '';
}

function microfin_generate_one_time_code(int $length = 6): string
{
    $maxValue = (10 ** $length) - 1;
    return str_pad((string) random_int(0, $maxValue), $length, '0', STR_PAD_LEFT);
}

function microfin_build_verification_token(string $code, int $ttlMinutes = 15): string
{
    return json_encode([
        'hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + ($ttlMinutes * 60)),
    ]);
}

function microfin_decode_verification_token(?string $storedToken): ?array
{
    if (!$storedToken) {
        return null;
    }

    $decoded = json_decode($storedToken, true);
    if (is_array($decoded) && isset($decoded['hash'])) {
        return $decoded;
    }

    return ['hash' => $storedToken, 'expires_at' => null];
}

function microfin_verification_token_is_expired(?string $storedToken): bool
{
    $payload = microfin_decode_verification_token($storedToken);
    if (!$payload || empty($payload['expires_at'])) {
        return false;
    }

    $expiresAt = strtotime((string) $payload['expires_at']);
    return $expiresAt !== false && $expiresAt < time();
}

function microfin_verify_verification_code(?string $storedToken, string $code): bool
{
    $payload = microfin_decode_verification_token($storedToken);
    if (!$payload || microfin_verification_token_is_expired($storedToken)) {
        return false;
    }

    return password_verify($code, (string) $payload['hash']);
}

function microfin_log_email_attempt(mysqli $conn, array $details): void
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO email_delivery_logs (
                tenant_id,
                user_id,
                email_type,
                recipient_email,
                recipient_name,
                subject,
                provider,
                provider_message_id,
                status,
                error_message,
                request_payload,
                response_payload
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $tenantId = $details['tenant_id'] ?? null;
        $userId = isset($details['user_id']) ? (int) $details['user_id'] : null;
        $emailType = (string) ($details['email_type'] ?? 'generic');
        $recipientEmail = (string) ($details['recipient_email'] ?? '');
        $recipientName = $details['recipient_name'] ?? null;
        $subject = (string) ($details['subject'] ?? '');
        $provider = (string) ($details['provider'] ?? 'brevo');
        $providerMessageId = $details['provider_message_id'] ?? null;
        $status = (string) ($details['status'] ?? 'failed');
        $errorMessage = $details['error_message'] ?? null;
        $requestPayload = isset($details['request_payload']) ? json_encode($details['request_payload']) : null;
        $responsePayload = isset($details['response_payload']) ? json_encode($details['response_payload']) : null;

        $stmt->bind_param(
            'sissssssssss',
            $tenantId,
            $userId,
            $emailType,
            $recipientEmail,
            $recipientName,
            $subject,
            $provider,
            $providerMessageId,
            $status,
            $errorMessage,
            $requestPayload,
            $responsePayload
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Email delivery should not fail because logging failed.
    }
}

function microfin_send_email(mysqli $conn, array $message): array
{
    $config = microfin_email_config();
    $recipientEmail = trim((string) ($message['to_email'] ?? ''));
    $recipientName = trim((string) ($message['to_name'] ?? ''));
    $subject = trim((string) ($message['subject'] ?? ''));
    $htmlContent = (string) ($message['html_content'] ?? '');
    $textContent = (string) ($message['text_content'] ?? '');
    $emailType = (string) ($message['email_type'] ?? 'generic');

    if ($recipientEmail === '' || $subject === '' || ($htmlContent === '' && $textContent === '')) {
        return ['success' => false, 'message' => 'Email payload is incomplete.'];
    }

    if (!microfin_email_is_configured()) {
        $result = ['success' => false, 'message' => 'Brevo is not configured.'];
        microfin_log_email_attempt($conn, [
            'tenant_id' => $message['tenant_id'] ?? null,
            'user_id' => $message['user_id'] ?? null,
            'email_type' => $emailType,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'status' => 'failed',
            'error_message' => $result['message'],
        ]);
        return $result;
    }

    $requestPayload = [
        'sender' => [
            'email' => $config['sender_email'],
            'name' => $config['sender_name'],
        ],
        'to' => [[
            'email' => $recipientEmail,
            'name' => $recipientName !== '' ? $recipientName : $recipientEmail,
        ]],
        'subject' => $subject,
        'htmlContent' => $htmlContent,
        'textContent' => $textContent,
        'tags' => array_values(array_filter((array) ($message['tags'] ?? []))),
    ];

    if ($config['sandbox_mode']) {
        $requestPayload['headers'] = ['X-Sib-Sandbox' => 'drop'];
    }

    $response = microfin_send_brevo_request($config, $requestPayload);

    microfin_log_email_attempt($conn, [
        'tenant_id' => $message['tenant_id'] ?? null,
        'user_id' => $message['user_id'] ?? null,
        'email_type' => $emailType,
        'recipient_email' => $recipientEmail,
        'recipient_name' => $recipientName,
        'subject' => $subject,
        'status' => $response['success'] ? 'sent' : 'failed',
        'provider_message_id' => $response['message_id'] ?? null,
        'error_message' => $response['message'] ?? null,
        'request_payload' => $requestPayload,
        'response_payload' => $response['response'] ?? null,
    ]);

    return $response;
}

function microfin_send_brevo_request(array $config, array $requestPayload): array
{
    $jsonPayload = json_encode($requestPayload, JSON_UNESCAPED_SLASHES);
    if ($jsonPayload === false) {
        return ['success' => false, 'message' => 'Unable to encode email payload.'];
    }

    if (function_exists('curl_init')) {
        $curl = curl_init($config['api_url']);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'api-key: ' . $config['api_key'],
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
        ]);

        $rawResponse = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($rawResponse === false) {
            return ['success' => false, 'message' => $curlError !== '' ? $curlError : 'Unable to reach Brevo.'];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 20,
                'header' => implode("\r\n", [
                    'accept: application/json',
                    'api-key: ' . $config['api_key'],
                    'content-type: application/json',
                ]),
                'content' => $jsonPayload,
            ],
        ]);

        $rawResponse = @file_get_contents($config['api_url'], false, $context);
        $statusCode = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int) $matches[1];
        }

        if ($rawResponse === false) {
            return ['success' => false, 'message' => 'Unable to reach Brevo.'];
        }
    }

    $decodedResponse = json_decode((string) $rawResponse, true);
    $isSuccess = $statusCode >= 200 && $statusCode < 300;

    if ($isSuccess) {
        return [
            'success' => true,
            'message' => 'Email queued successfully.',
            'message_id' => is_array($decodedResponse) ? ($decodedResponse['messageId'] ?? null) : null,
            'response' => $decodedResponse,
        ];
    }

    $errorMessage = 'Brevo request failed.';
    if (is_array($decodedResponse) && isset($decodedResponse['message'])) {
        $errorMessage = (string) $decodedResponse['message'];
    } elseif (is_string($rawResponse) && trim($rawResponse) !== '') {
        $errorMessage = trim($rawResponse);
    }

    return [
        'success' => false,
        'message' => $errorMessage,
        'response' => $decodedResponse,
    ];
}

function microfin_render_email_layout(string $preheader, string $headline, string $bodyHtml, string $accentColor = '#0F766E'): string
{
    $appUrl = htmlspecialchars(microfin_email_config()['app_base_url'], ENT_QUOTES, 'UTF-8');
    $safePreheader = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');
    $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
    $safeAccent = htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . $safeHeadline . '</title>
</head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $safePreheader . '</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;">
    <tr>
      <td style="background:' . $safeAccent . ';padding:28px 32px;color:#ffffff;">
        <div style="font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;">MicroFin</div>
        <div style="font-size:28px;font-weight:700;margin-top:8px;">' . $safeHeadline . '</div>
      </td>
    </tr>
    <tr>
      <td style="padding:32px;">' . $bodyHtml . '</td>
    </tr>
    <tr>
      <td style="padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;">
        This is an automated message from MicroFin. Visit
        <a href="' . $appUrl . '" style="color:' . $safeAccent . ';text-decoration:none;">' . $appUrl . '</a>
        for more details.
      </td>
    </tr>
  </table>
</body>
</html>';
}

function microfin_send_registration_otp_email(mysqli $conn, array $context): array
{
    $tenantName = trim((string) ($context['tenant_name'] ?? 'MicroFin'));
    $recipientName = trim((string) ($context['recipient_name'] ?? ''));
    $otp = (string) ($context['otp'] ?? '');
    $minutes = (int) ($context['ttl_minutes'] ?? 15);

    $bodyHtml = '
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Hello ' . htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">Use the verification code below to finish creating your account for <strong>' . htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>
        <div style="margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</div>
        <p style="margin:0 0 12px;font-size:14px;line-height:1.7;">This code expires in ' . $minutes . ' minutes.</p>
        <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">If you did not request this, you can safely ignore this email.</p>';

    $textContent = "Hello " . ($recipientName !== '' ? $recipientName : 'there') . ",\n\nUse this verification code to finish creating your account for {$tenantName}: {$otp}\n\nThis code expires in {$minutes} minutes.";

    return microfin_send_email($conn, [
        'tenant_id' => $context['tenant_id'] ?? null,
        'user_id' => $context['user_id'] ?? null,
        'email_type' => 'registration_otp',
        'to_email' => $context['to_email'] ?? '',
        'to_name' => $recipientName,
        'subject' => $tenantName . ' verification code',
        'html_content' => microfin_render_email_layout(
            'Your MicroFin registration verification code is ready.',
            'Verify your email',
            $bodyHtml,
            '#0F766E'
        ),
        'text_content' => $textContent,
        'tags' => ['registration', 'otp'],
    ]);
}

function microfin_send_password_reset_email(mysqli $conn, array $context): array
{
    $tenantName = trim((string) ($context['tenant_name'] ?? 'MicroFin'));
    $recipientName = trim((string) ($context['recipient_name'] ?? ''));
    $otp = (string) ($context['otp'] ?? '');
    $minutes = (int) ($context['ttl_minutes'] ?? 15);

    $bodyHtml = '
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Hello ' . htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">We received a request to reset the password for your <strong>' . htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') . '</strong> account.</p>
        <div style="margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#fff7ed;border:1px solid #fdba74;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</div>
        <p style="margin:0 0 12px;font-size:14px;line-height:1.7;">This reset code expires in ' . $minutes . ' minutes.</p>
        <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">If you did not request a password reset, you can ignore this message.</p>';

    $textContent = "Hello " . ($recipientName !== '' ? $recipientName : 'there') . ",\n\nUse this password reset code for {$tenantName}: {$otp}\n\nThis code expires in {$minutes} minutes.";

    return microfin_send_email($conn, [
        'tenant_id' => $context['tenant_id'] ?? null,
        'user_id' => $context['user_id'] ?? null,
        'email_type' => 'password_reset_otp',
        'to_email' => $context['to_email'] ?? '',
        'to_name' => $recipientName,
        'subject' => $tenantName . ' password reset code',
        'html_content' => microfin_render_email_layout(
            'Your MicroFin password reset code is ready.',
            'Reset your password',
            $bodyHtml,
            '#B45309'
        ),
        'text_content' => $textContent,
        'tags' => ['password-reset', 'otp'],
    ]);
}

function microfin_send_receipt_email(mysqli $conn, array $context): array
{
    $tenantName = trim((string) ($context['tenant_name'] ?? 'MicroFin'));
    $recipientName = trim((string) ($context['client_name'] ?? ''));
    $paymentReference = trim((string) ($context['payment_reference'] ?? ''));
    $loanNumber = trim((string) ($context['loan_number'] ?? ''));
    $paymentMethod = trim((string) ($context['payment_method'] ?? 'Payment'));
    $paymentDate = trim((string) ($context['payment_date'] ?? ''));
    $amount = number_format((float) ($context['amount'] ?? 0), 2);

    $bodyHtml = '
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Hello ' . htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">Your payment has been posted successfully. Here is your receipt summary from <strong>' . htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f9fafb;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;">
          <tr><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;"><strong>Reference</strong></td><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($paymentReference, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;"><strong>Loan Number</strong></td><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($loanNumber, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;"><strong>Payment Method</strong></td><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;"><strong>Payment Date</strong></td><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($paymentDate, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:16px 18px;font-size:15px;"><strong>Total Paid</strong></td><td style="padding:16px 18px;font-size:24px;font-weight:700;text-align:right;color:#0F766E;">PHP ' . $amount . '</td></tr>
        </table>
        <p style="margin:20px 0 0;font-size:14px;line-height:1.7;color:#6b7280;">Keep this email for your records.</p>';

    $textContent = "Hello " . ($recipientName !== '' ? $recipientName : 'there') . ",\n\nYour payment receipt from {$tenantName}\nReference: {$paymentReference}\nLoan Number: {$loanNumber}\nPayment Method: {$paymentMethod}\nPayment Date: {$paymentDate}\nTotal Paid: PHP {$amount}";

    return microfin_send_email($conn, [
        'tenant_id' => $context['tenant_id'] ?? null,
        'user_id' => $context['user_id'] ?? null,
        'email_type' => 'payment_receipt',
        'to_email' => $context['client_email'] ?? '',
        'to_name' => $recipientName,
        'subject' => 'Payment receipt ' . $paymentReference,
        'html_content' => microfin_render_email_layout(
            'Your MicroFin payment receipt is ready.',
            'Payment received',
            $bodyHtml,
            '#0F766E'
        ),
        'text_content' => $textContent,
        'tags' => ['receipt', 'payment'],
    ]);
}
