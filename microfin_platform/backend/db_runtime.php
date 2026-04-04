<?php

if (!function_exists('mf_env_first')) {
    function mf_env_first(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}

if (!function_exists('mf_is_railway_runtime')) {
    function mf_is_railway_runtime(): bool
    {
        foreach ([
            'RAILWAY_ENVIRONMENT',
            'RAILWAY_PROJECT_ID',
            'RAILWAY_SERVICE_ID',
            'RAILWAY_PUBLIC_DOMAIN',
            'RAILWAY_STATIC_URL',
        ] as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mf_database_target_from_url')) {
    function mf_database_target_from_url(string $databaseUrl): ?array
    {
        $parts = parse_url($databaseUrl);
        if ($parts === false) {
            return null;
        }

        $target = [];

        if (!empty($parts['host'])) {
            $target['host'] = (string) $parts['host'];
        }
        if (!empty($parts['port'])) {
            $target['port'] = (int) $parts['port'];
        }
        if (array_key_exists('user', $parts)) {
            $target['user'] = urldecode((string) $parts['user']);
        }
        if (array_key_exists('pass', $parts)) {
            $target['pass'] = urldecode((string) $parts['pass']);
        }
        if (!empty($parts['path'])) {
            $target['db'] = ltrim((string) $parts['path'], '/');
        }

        return $target;
    }
}

if (!function_exists('mf_resolve_db_targets')) {
    function mf_resolve_db_targets(): array
    {
        if (mf_is_railway_runtime()) {
            $target = [
                'host' => 'centerbeam.proxy.rlwy.net',
                'port' => 52624,
                'db' => 'railway',
                'user' => 'root',
                'pass' => 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd',
            ];

            $databaseUrl = mf_env_first(['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL', 'MYSQL_PRIVATE_URL']);
            if ($databaseUrl !== null) {
                $parsedTarget = mf_database_target_from_url($databaseUrl);
                if ($parsedTarget !== null) {
                    $target = array_merge($target, $parsedTarget);
                }
            }

            $envOverrides = [
                'host' => mf_env_first(['MYSQLHOST', 'DB_HOST']),
                'port' => mf_env_first(['MYSQLPORT', 'DB_PORT']),
                'db' => mf_env_first(['MYSQLDATABASE', 'DB_NAME']),
                'user' => mf_env_first(['MYSQLUSER', 'DB_USER']),
                'pass' => mf_env_first(['MYSQLPASSWORD', 'DB_PASSWORD']),
            ];

            foreach ($envOverrides as $key => $value) {
                if ($value !== null) {
                    $target[$key] = $key === 'port' ? (int) $value : $value;
                }
            }

            return [
                'mode' => 'railway',
                'targets' => [$target],
            ];
        }

        $baseLocalTarget = [
            'host' => mf_env_first(['LOCAL_DB_HOST']) ?? 'localhost',
            'port' => (int) (mf_env_first(['LOCAL_DB_PORT']) ?? 3306),
            'db' => mf_env_first(['LOCAL_DB_NAME']) ?? 'microfin_db',
            'user' => mf_env_first(['LOCAL_DB_USER']) ?? 'root',
        ];

        $explicitLocalPassword = mf_env_first(['LOCAL_DB_PASSWORD']);
        $passwordCandidates = $explicitLocalPassword !== null
            ? [$explicitLocalPassword]
            : ['1234', ''];

        $targets = [];
        foreach ($passwordCandidates as $passwordCandidate) {
            $targets[] = array_merge($baseLocalTarget, [
                'pass' => $passwordCandidate,
            ]);
        }

        return [
            'mode' => 'local',
            'targets' => $targets,
        ];
    }
}
