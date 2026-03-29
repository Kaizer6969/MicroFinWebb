<?php

/**
 * Normalize any tenant slug input to lowercase URL-safe slug form.
 */
function mf_normalize_tenant_slug($rawValue)
{
    $slug = strtolower(trim((string) $rawValue));
    $slug = preg_replace('/[^a-z0-9]+/', '', $slug); // Strip all spaces and non-alphanumeric chars
    
    return $slug;
}

/**
 * Check if a tenant slug already exists, excluding an optional tenant_id.
 */
function mf_tenant_slug_exists(PDO $pdo, $slug, $excludeTenantId = null)
{
    if ($excludeTenantId !== null && $excludeTenantId !== '') {
        $stmt = $pdo->prepare('SELECT 1 FROM tenants WHERE tenant_slug = ? AND tenant_id <> ? LIMIT 1');
        $stmt->execute([$slug, $excludeTenantId]);
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM tenants WHERE tenant_slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool) $stmt->fetchColumn();
}

/**
 * Generate a unique slug by suffixing short random tokens if needed.
 */
function mf_generate_unique_tenant_slug(PDO $pdo, $baseSlug, $excludeTenantId = null)
{
    $base = mf_normalize_tenant_slug($baseSlug);
    if ($base === '') {
        $base = 'tenant';
    }

    $candidate = $base;
    $attempt = 0;
    while (mf_tenant_slug_exists($pdo, $candidate, $excludeTenantId)) {
        $attempt++;
        if ($attempt > 100) {
            throw new RuntimeException('Unable to generate a unique tenant slug.');
        }

        $candidate = $base . strtolower(substr(bin2hex(random_bytes(2)), 0, 4));
    }

    return $candidate;
}

/**
 * Generate a unique 10-character uppercase alphanumeric tenant ID.
 */
function mf_generate_tenant_id(PDO $pdo, $length = 10, $maxAttempts = 100)
{
    $length = (int) $length;
    if ($length < 6) {
        $length = 10;
    }

    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $maxIndex = strlen($characters) - 1;
    $checkStmt = $pdo->prepare('SELECT 1 FROM tenants WHERE tenant_id = ? LIMIT 1');

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $candidate = '';
        for ($i = 0; $i < $length; $i++) {
            $candidate .= $characters[random_int(0, $maxIndex)];
        }

        $checkStmt->execute([$candidate]);
        if (!$checkStmt->fetchColumn()) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to generate a unique tenant ID.');
}
