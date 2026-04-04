<?php

if (!function_exists('mf_backend_session_timeout_seconds')) {
    function mf_backend_session_timeout_seconds(): int
    {
        return 1800;
    }
}

if (!function_exists('mf_backend_session_timeout_minutes')) {
    function mf_backend_session_timeout_minutes(): int
    {
        return 30;
    }
}

if (!function_exists('mf_start_backend_session')) {
    function mf_start_backend_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $timeout = mf_backend_session_timeout_seconds();

        if (!headers_sent()) {
            ini_set('session.gc_maxlifetime', (string) $timeout);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');

            if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
                ini_set('session.cookie_secure', '1');
            }
        }

        session_start();
    }
}

if (!function_exists('mf_backend_session_now')) {
    function mf_backend_session_now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}

if (!function_exists('mf_backend_session_expiry_string')) {
    function mf_backend_session_expiry_string(?DateTimeImmutable $base = null): string
    {
        $anchor = $base ?? mf_backend_session_now();
        return $anchor
            ->add(new DateInterval('PT' . mf_backend_session_timeout_seconds() . 'S'))
            ->format('Y-m-d H:i:s');
    }
}

if (!function_exists('mf_backend_session_ip')) {
    function mf_backend_session_ip(): ?string
    {
        // Temporarily disabled by request.
        return null;
    }
}

if (!function_exists('mf_backend_session_user_agent')) {
    function mf_backend_session_user_agent(): ?string
    {
        // Temporarily disabled by request.
        return null;
    }
}

if (!function_exists('mf_backend_session_snapshot')) {
    function mf_backend_session_snapshot(): array
    {
        return [
            'token' => trim((string) ($_SESSION['backend_session_token'] ?? '')),
            'user_id' => (int) ($_SESSION['backend_session_user_id'] ?? 0),
            'context' => trim((string) ($_SESSION['backend_session_context'] ?? '')),
            'tenant_id' => isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '',
            'tenant_slug' => isset($_SESSION['tenant_slug']) ? trim((string) $_SESSION['tenant_slug']) : '',
            'super_admin_id' => (int) ($_SESSION['super_admin_id'] ?? 0),
            'user_id_session' => (int) ($_SESSION['user_id'] ?? 0),
            'user_logged_in' => !empty($_SESSION['user_logged_in']),
            'super_admin_logged_in' => !empty($_SESSION['super_admin_logged_in']),
        ];
    }
}

if (!function_exists('mf_get_active_browser_backend_session')) {
    function mf_get_active_browser_backend_session(PDO $pdo): ?array
    {
        $snapshot = mf_backend_session_snapshot();
        if ($snapshot['token'] === '' || $snapshot['user_id'] <= 0) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('
                SELECT session_id, user_id, tenant_id, last_activity_at, expires_at
                FROM user_sessions
                WHERE session_token = ?
                  AND user_id = ?
                  AND expires_at > NOW()
                LIMIT 1
            ');
            $stmt->execute([$snapshot['token'], $snapshot['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Unable to inspect active browser session: ' . $e->getMessage());
            return null;
        }

        if (!$row) {
            return null;
        }

        return [
            'session_id' => (int) ($row['session_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'tenant_id' => isset($row['tenant_id']) ? trim((string) $row['tenant_id']) : '',
            'last_activity_at' => $row['last_activity_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'context' => trim((string) ($snapshot['context'] ?? '')) !== '' ? trim((string) $snapshot['context']) : ((isset($row['tenant_id']) && $row['tenant_id'] !== null && trim((string) $row['tenant_id']) !== '') ? 'tenant' : 'super_admin'),
            'super_admin_logged_in' => !empty($snapshot['super_admin_logged_in']),
            'user_logged_in' => !empty($snapshot['user_logged_in']),
        ];
    }
}

if (!function_exists('mf_browser_has_active_backend_session')) {
    function mf_browser_has_active_backend_session(PDO $pdo): bool
    {
        return mf_get_active_browser_backend_session($pdo) !== null;
    }
}

if (!function_exists('mf_backend_session_matches_expected_context')) {
    function mf_backend_session_matches_expected_context(array $snapshot, string $expectedContext): bool
    {
        $normalizedContext = $expectedContext === 'super_admin' ? 'super_admin' : 'tenant';

        if ($normalizedContext === 'tenant' && !empty($snapshot['super_admin_logged_in']) && !empty($snapshot['user_logged_in']) && (int) ($snapshot['user_id_session'] ?? 0) === 0) {
            return true;
        }

        return trim((string) ($snapshot['context'] ?? '')) === $normalizedContext;
    }
}

if (!function_exists('mf_backend_session_is_impersonation')) {
    function mf_backend_session_is_impersonation(): bool
    {
        return !empty($_SESSION['super_admin_logged_in'])
            && !empty($_SESSION['user_logged_in'])
            && (int) ($_SESSION['user_id'] ?? -1) === 0
            && !empty($_SESSION['tenant_id'])
            && !empty($_SESSION['super_admin_id']);
    }
}

if (!function_exists('mf_backend_session_destroy_php_session')) {
    function mf_backend_session_destroy_php_session(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                !empty($params['secure']),
                !empty($params['httponly'])
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

if (!function_exists('mf_clear_backend_session_pointer')) {
    function mf_clear_backend_session_pointer(): void
    {
        unset(
            $_SESSION['backend_session_token'],
            $_SESSION['backend_session_user_id'],
            $_SESSION['backend_session_context'],
            $_SESSION['backend_session_expires_at']
        );
    }
}

if (!function_exists('mf_expire_backend_session_record')) {
    function mf_expire_backend_session_record(PDO $pdo, string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        try {
            $stmt = $pdo->prepare('
                UPDATE user_sessions
                SET last_activity_at = NOW(),
                    expires_at = NOW()
                WHERE session_token = ?
            ');
            $stmt->execute([$token]);
        } catch (Throwable $e) {
            error_log('Unable to expire backend session: ' . $e->getMessage());
        }
    }
}

if (!function_exists('mf_destroy_backend_session')) {
    function mf_destroy_backend_session(PDO $pdo, bool $destroyPhpSession = true): void
    {
        $token = trim((string) ($_SESSION['backend_session_token'] ?? ''));

        if ($token !== '') {
            mf_expire_backend_session_record($pdo, $token);
        }

        if ($destroyPhpSession) {
            mf_backend_session_destroy_php_session();
        } else {
            mf_clear_backend_session_pointer();
        }
    }
}

if (!function_exists('mf_create_backend_session')) {
    function mf_create_backend_session(PDO $pdo, int $authUserId, ?string $tenantId, string $context): ?string
    {
        if ($authUserId <= 0) {
            return null;
        }

        $normalizedContext = $context === 'super_admin' ? 'super_admin' : 'tenant';
        $normalizedTenantId = $tenantId !== null && trim($tenantId) !== '' ? trim($tenantId) : null;

        if ($normalizedContext === 'super_admin') {
            $normalizedTenantId = null;
        }

        if (!empty($_SESSION['backend_session_token'])) {
            mf_clear_backend_session_pointer();
        }

        session_regenerate_id(true);

        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare('
            INSERT INTO user_sessions (user_id, tenant_id, session_token, ip_address, user_agent, last_activity_at, expires_at)
            VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ');
        $stmt->execute([
            $authUserId,
            $normalizedTenantId,
            $token,
            mf_backend_session_ip(),
            mf_backend_session_user_agent(),
        ]);

        $_SESSION['backend_session_token'] = $token;
        $_SESSION['backend_session_user_id'] = $authUserId;
        $_SESSION['backend_session_context'] = $normalizedContext;
        $_SESSION['backend_session_expires_at'] = null;

        return $token;
    }
}

if (!function_exists('mf_validate_backend_session')) {
    function mf_validate_backend_session(PDO $pdo, string $expectedContext): bool
    {
        $snapshot = mf_backend_session_snapshot();
        if ($snapshot['token'] === '' || $snapshot['user_id'] <= 0) {
            return false;
        }

        $normalizedContext = $expectedContext === 'super_admin' ? 'super_admin' : 'tenant';
        $params = [$snapshot['token'], $snapshot['user_id']];
        $sql = '
            SELECT session_id, user_id, tenant_id, expires_at
            FROM user_sessions
            WHERE session_token = ?
              AND user_id = ?
              AND expires_at > NOW()
        ';

        if ($normalizedContext === 'super_admin') {
            if (!$snapshot['super_admin_logged_in'] || $snapshot['super_admin_id'] <= 0 || $snapshot['context'] !== 'super_admin') {
                return false;
            }

            $sql .= ' AND tenant_id IS NULL';
        } else {
            if (!$snapshot['user_logged_in']) {
                return false;
            }

            if (mf_backend_session_is_impersonation()) {
                if ($snapshot['context'] !== 'super_admin' || $snapshot['super_admin_id'] <= 0) {
                    return false;
                }

                $sql .= ' AND tenant_id IS NULL';
            } else {
                if ($snapshot['tenant_id'] === '' || $snapshot['context'] !== 'tenant') {
                    return false;
                }

                $sql .= ' AND tenant_id = ?';
                $params[] = $snapshot['tenant_id'];
            }
        }

        $sql .= ' LIMIT 1';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Unable to validate backend session: ' . $e->getMessage());
            return false;
        }

        if (!$row) {
            return false;
        }

        try {
            $touchStmt = $pdo->prepare('
                UPDATE user_sessions
                SET ip_address = ?, user_agent = ?, last_activity_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                WHERE session_id = ?
            ');
            $touchStmt->execute([
                mf_backend_session_ip(),
                mf_backend_session_user_agent(),
                (int) $row['session_id'],
            ]);
        } catch (Throwable $e) {
            error_log('Unable to refresh backend session expiry: ' . $e->getMessage());
            return false;
        }

        $_SESSION['backend_session_expires_at'] = null;

        return true;
    }
}

if (!function_exists('mf_refresh_backend_session_state')) {
    function mf_refresh_backend_session_state(PDO $pdo, string $expectedContext): bool
    {
        if (!mf_validate_backend_session($pdo, $expectedContext)) {
            $snapshot = mf_backend_session_snapshot();
            if ($snapshot['token'] !== '' && mf_backend_session_matches_expected_context($snapshot, $expectedContext)) {
                mf_destroy_backend_session($pdo);
            } elseif (!empty($_SESSION['user_logged_in']) || !empty($_SESSION['super_admin_logged_in'])) {
                if ($snapshot['token'] === '') {
                    mf_backend_session_destroy_php_session();
                }
            }
            return false;
        }

        return true;
    }
}

if (!function_exists('mf_require_backend_session')) {
    function mf_require_backend_session(PDO $pdo, string $expectedContext, array $options = []): void
    {
        if (mf_validate_backend_session($pdo, $expectedContext)) {
            return;
        }

        $snapshot = mf_backend_session_snapshot();
        if ($snapshot['token'] !== '' && mf_backend_session_matches_expected_context($snapshot, $expectedContext)) {
            mf_destroy_backend_session($pdo);
        } elseif ($snapshot['token'] === '') {
            mf_backend_session_destroy_php_session();
        }

        $response = (string) ($options['response'] ?? 'redirect');
        $status = (int) ($options['status'] ?? ($response === 'json' ? 401 : 302));
        $message = (string) ($options['message'] ?? 'Unauthorized.');

        if ($response === 'json') {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message]);
            exit;
        }

        if ($response === 'die') {
            http_response_code($status > 0 ? $status : 403);
            exit($message);
        }

        $redirect = trim((string) ($options['redirect'] ?? ''));
        if ($expectedContext === 'tenant' && !empty($options['append_tenant_slug']) && $snapshot['tenant_slug'] !== '') {
            $separator = strpos($redirect, '?') === false ? '?' : '&';
            $redirect .= $separator . 's=' . urlencode($snapshot['tenant_slug']) . '&auth=1';
        }

        if ($redirect !== '') {
            header('Location: ' . $redirect);
            exit;
        }

        http_response_code($status > 0 ? $status : 401);
        exit($message);
    }
}

if (!function_exists('mf_require_tenant_session')) {
    function mf_require_tenant_session(PDO $pdo, array $options = []): void
    {
        mf_require_backend_session($pdo, 'tenant', $options);
    }
}

if (!function_exists('mf_require_super_admin_session')) {
    function mf_require_super_admin_session(PDO $pdo, array $options = []): void
    {
        mf_require_backend_session($pdo, 'super_admin', $options);
    }
}
