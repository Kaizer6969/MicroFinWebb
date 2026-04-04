<?php

function microfin_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }

    return $default;
}

function microfin_database_config(bool $includeDatabase = true): array
{
    $defaultDatabaseUrl = 'mysql://root:zVULvPIbSyHVavTRnPFAkMWGVmvRwInd@centerbeam.proxy.rlwy.net:52624/railway';
    $databaseUrl = microfin_env('DATABASE_URL', $defaultDatabaseUrl);
    $parsedUrl = $databaseUrl ? parse_url($databaseUrl) : false;

    $databaseNameFromUrl = '';
    if (is_array($parsedUrl) && isset($parsedUrl['path'])) {
        $databaseNameFromUrl = ltrim((string) $parsedUrl['path'], '/');
    }

    $config = [
        'host' => microfin_env(
            'MYSQLHOST',
            is_array($parsedUrl) && isset($parsedUrl['host']) ? (string) $parsedUrl['host'] : 'centerbeam.proxy.rlwy.net'
        ),
        'port' => (int) microfin_env(
            'MYSQLPORT',
            is_array($parsedUrl) && isset($parsedUrl['port']) ? (string) $parsedUrl['port'] : '52624'
        ),
        'username' => microfin_env(
            'MYSQLUSER',
            is_array($parsedUrl) && isset($parsedUrl['user']) ? (string) $parsedUrl['user'] : 'root'
        ),
        'password' => microfin_env(
            'MYSQLPASSWORD',
            is_array($parsedUrl) && isset($parsedUrl['pass']) ? (string) $parsedUrl['pass'] : 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd'
        ),
        'database' => microfin_env('MYSQLDATABASE', $databaseNameFromUrl !== '' ? $databaseNameFromUrl : 'railway'),
    ];

    if (!$includeDatabase) {
        unset($config['database']);
    }

    return $config;
}
