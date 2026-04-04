<?php

if (!function_exists('mf_credit_policy_employment_options')) {
    function mf_credit_policy_employment_options(): array
    {
        return [
            'Employed',
            'Self-Employed',
            'Freelancer',
            'Contractual',
            'Part-Time',
            'OFW',
            'Student',
            'Unemployed',
            'Retired',
        ];
    }
}

if (!function_exists('mf_credit_policy_ci_recommendation_options')) {
    function mf_credit_policy_ci_recommendation_options(): array
    {
        return ['Highly Recommended', 'Recommended', 'Conditional', 'Not Recommended'];
    }
}

if (!function_exists('mf_credit_policy_score_ceiling')) {
    function mf_credit_policy_score_ceiling(): int
    {
        return 1000;
    }
}

if (!function_exists('mf_credit_policy_normalize_score_value')) {
    function mf_credit_policy_normalize_score_value($value, $fallback = 0, bool $scaleLegacy = true): int
    {
        $score = is_numeric($value) ? (float) $value : (float) $fallback;
        $scoreCeiling = mf_credit_policy_score_ceiling();

        if ($scaleLegacy && $score > 0 && $score <= 100 && $scoreCeiling > 100) {
            $score *= $scoreCeiling / 100;
        }

        return (int) round(max(0, min($scoreCeiling, $score)));
    }
}

if (!function_exists('mf_credit_policy_defaults')) {
    function mf_credit_policy_defaults(): array
    {
        return [
            'eligibility' => [
                'min_monthly_income' => 0,
                'allowed_employment_statuses' => ['Employed', 'Self-Employed', 'Retired'],
            ],
            'score_thresholds' => [
                'review_min_score' => 500,
                'review_max_score' => 799,
                'approve_min_score' => 800,
                'new_client_default_score' => 500,
            ],
            'ci_rules' => [
                'require_ci' => false,
                'ci_required_above_amount' => 0,
                'auto_approve_ci_values' => ['Highly Recommended', 'Recommended'],
                'review_ci_values' => ['Conditional'],
            ],
            'credit_limit' => [
                'income_multiplier' => 4,
                'approve_band_multiplier' => 1.10,
                'review_band_multiplier' => 1.00,
                'reject_band_multiplier' => 0.50,
                'max_credit_limit_cap' => 200000,
                'round_to_nearest' => 500,
            ],
            'product_checks' => [
                'use_product_minimum_credit_score' => true,
                'use_product_min_amount' => true,
                'use_product_max_amount' => true,
            ],
        ];
    }
}

if (!function_exists('mf_credit_policy_truthy')) {
    function mf_credit_policy_truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}

if (!function_exists('mf_credit_policy_normalize_list')) {
    function mf_credit_policy_normalize_list($values, array $allowed, array $fallback, bool $allowEmpty = false): array
    {
        if (!is_array($values)) {
            $values = [];
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && in_array($value, $allowed, true)) {
                $normalized[$value] = $value;
            }
        }

        if (!empty($normalized)) {
            return array_values($normalized);
        }

        if ($allowEmpty) {
            return [];
        }

        return $fallback;
    }
}

if (!function_exists('mf_credit_policy_normalize')) {
    function mf_credit_policy_normalize($payload): array
    {
        $defaults = mf_credit_policy_defaults();
        $policy = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;
        $scoreCeiling = mf_credit_policy_score_ceiling();
        $reviewBandCeiling = max(0, $scoreCeiling - 1);

        $reviewMinScore = min(
            $reviewBandCeiling,
            mf_credit_policy_normalize_score_value(
                $policy['score_thresholds']['review_min_score'] ?? $defaults['score_thresholds']['review_min_score'],
                $defaults['score_thresholds']['review_min_score']
            )
        );
        $reviewMaxFallback = ($policy['score_thresholds']['approve_min_score'] ?? $defaults['score_thresholds']['approve_min_score']) - 1;
        $reviewMaxScore = mf_credit_policy_normalize_score_value(
            $policy['score_thresholds']['review_max_score'] ?? $reviewMaxFallback,
            $defaults['score_thresholds']['review_max_score']
        );
        $approveMinScore = mf_credit_policy_normalize_score_value(
            $policy['score_thresholds']['approve_min_score'] ?? ($reviewMaxScore + 1),
            $defaults['score_thresholds']['approve_min_score']
        );
        $reviewMaxScore = min($reviewBandCeiling, max($reviewMinScore, min($reviewMaxScore, $approveMinScore - 1)));
        $approveMinScore = min($scoreCeiling, max($reviewMaxScore + 1, $approveMinScore));

        $newClientDefaultScore = mf_credit_policy_normalize_score_value(
            $policy['score_thresholds']['new_client_default_score'] ?? $defaults['score_thresholds']['new_client_default_score'],
            $defaults['score_thresholds']['new_client_default_score']
        );

        // Backward-compat: map old CI multiplier keys to new band multiplier keys
        $creditLimit = $policy['credit_limit'] ?? [];
        $hasNewBandKeys = isset($creditLimit['approve_band_multiplier'])
            || isset($creditLimit['review_band_multiplier'])
            || isset($creditLimit['reject_band_multiplier']);
        if (!$hasNewBandKeys) {
            if (isset($creditLimit['ci_multiplier_highly_recommended'])) {
                $creditLimit['approve_band_multiplier'] = $creditLimit['ci_multiplier_highly_recommended'];
            }
            if (isset($creditLimit['ci_multiplier_recommended'])) {
                $creditLimit['review_band_multiplier'] = $creditLimit['ci_multiplier_recommended'];
            }
            if (isset($creditLimit['ci_multiplier_conditional'])) {
                $creditLimit['reject_band_multiplier'] = $creditLimit['ci_multiplier_conditional'];
            }
        }

        return [
            'eligibility' => [
                'min_monthly_income' => round(max(0, (float) ($policy['eligibility']['min_monthly_income'] ?? $defaults['eligibility']['min_monthly_income'])), 2),
                'allowed_employment_statuses' => mf_credit_policy_normalize_list(
                    $policy['eligibility']['allowed_employment_statuses'] ?? [],
                    mf_credit_policy_employment_options(),
                    $defaults['eligibility']['allowed_employment_statuses'],
                    true
                ),
            ],
            'score_thresholds' => [
                'review_min_score' => $reviewMinScore,
                'review_max_score' => $reviewMaxScore,
                'approve_min_score' => $approveMinScore,
                'new_client_default_score' => $newClientDefaultScore,
            ],
            'ci_rules' => [
                'require_ci' => mf_credit_policy_truthy($policy['ci_rules']['require_ci'] ?? $defaults['ci_rules']['require_ci']),
                'ci_required_above_amount' => round(max(0, (float) ($policy['ci_rules']['ci_required_above_amount'] ?? $defaults['ci_rules']['ci_required_above_amount'])), 2),
                'auto_approve_ci_values' => mf_credit_policy_normalize_list(
                    $policy['ci_rules']['auto_approve_ci_values'] ?? [],
                    mf_credit_policy_ci_recommendation_options(),
                    $defaults['ci_rules']['auto_approve_ci_values']
                ),
                'review_ci_values' => mf_credit_policy_normalize_list(
                    $policy['ci_rules']['review_ci_values'] ?? [],
                    mf_credit_policy_ci_recommendation_options(),
                    $defaults['ci_rules']['review_ci_values']
                ),
            ],
            'credit_limit' => [
                'income_multiplier' => round(max(0, (float) ($creditLimit['income_multiplier'] ?? $defaults['credit_limit']['income_multiplier'])), 2),
                'approve_band_multiplier' => round(max(0, (float) ($creditLimit['approve_band_multiplier'] ?? $defaults['credit_limit']['approve_band_multiplier'])), 2),
                'review_band_multiplier' => round(max(0, (float) ($creditLimit['review_band_multiplier'] ?? $defaults['credit_limit']['review_band_multiplier'])), 2),
                'reject_band_multiplier' => round(max(0, (float) ($creditLimit['reject_band_multiplier'] ?? $defaults['credit_limit']['reject_band_multiplier'])), 2),
                'max_credit_limit_cap' => round(max(0, (float) ($creditLimit['max_credit_limit_cap'] ?? $defaults['credit_limit']['max_credit_limit_cap'])), 2),
                'round_to_nearest' => round(max(0, (float) ($creditLimit['round_to_nearest'] ?? $defaults['credit_limit']['round_to_nearest'])), 2),
            ],
            'product_checks' => [
                'use_product_minimum_credit_score' => mf_credit_policy_truthy($policy['product_checks']['use_product_minimum_credit_score'] ?? $defaults['product_checks']['use_product_minimum_credit_score']),
                'use_product_min_amount' => mf_credit_policy_truthy($policy['product_checks']['use_product_min_amount'] ?? $defaults['product_checks']['use_product_min_amount']),
                'use_product_max_amount' => mf_credit_policy_truthy($policy['product_checks']['use_product_max_amount'] ?? $defaults['product_checks']['use_product_max_amount']),
            ],
        ];
    }
}

if (!function_exists('mf_credit_policy_legacy_keys')) {
    function mf_credit_policy_legacy_keys(): array
    {
        return [
            'credit_policy',
            'minimum_credit_score',
            'require_credit_investigation',
            'auto_reject_below_score',
            'credit_limit_rules',
        ];
    }
}

if (!function_exists('mf_credit_policy_from_legacy_settings')) {
    function mf_credit_policy_from_legacy_settings(array $settings): array
    {
        $defaults = mf_credit_policy_defaults();
        $legacy = $defaults;

        $legacy['score_thresholds']['review_min_score'] = (int) ($settings['auto_reject_below_score'] ?? $defaults['score_thresholds']['review_min_score']);
        $legacy['score_thresholds']['approve_min_score'] = (int) ($settings['minimum_credit_score'] ?? $defaults['score_thresholds']['approve_min_score']);
        $legacy['score_thresholds']['review_max_score'] = max(
            $legacy['score_thresholds']['review_min_score'],
            $legacy['score_thresholds']['approve_min_score'] - 1
        );
        $legacy['ci_rules']['require_ci'] = mf_credit_policy_truthy($settings['require_credit_investigation'] ?? $defaults['ci_rules']['require_ci']);

        if (!empty($settings['credit_limit_rules'])) {
            $decoded = json_decode((string) $settings['credit_limit_rules'], true);
            if (is_array($decoded)) {
                $absoluteMaxLimit = $decoded['increase_rules']['absolute_max_limit'] ?? null;
                if (is_numeric($absoluteMaxLimit)) {
                    $legacy['credit_limit']['max_credit_limit_cap'] = (float) $absoluteMaxLimit;
                }
            }
        }

        return mf_credit_policy_normalize($legacy);
    }
}

if (!function_exists('mf_get_tenant_credit_policy')) {
    function mf_get_tenant_credit_policy(PDO $pdo, string $tenantId): array
    {
        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            return mf_credit_policy_defaults();
        }

        $keys = mf_credit_policy_legacy_keys();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $params = array_merge([$tenantId], $keys);

        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = ? AND setting_key IN ($placeholders)");
        $stmt->execute($params);

        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string) $row['setting_key']] = $row['setting_value'];
        }

        if (!empty($settings['credit_policy'])) {
            $decoded = json_decode((string) $settings['credit_policy'], true);
            if (is_array($decoded)) {
                return mf_credit_policy_normalize($decoded);
            }
        }

        return mf_credit_policy_from_legacy_settings($settings);
    }
}

if (!function_exists('mf_credit_policy_fetch_client')) {
    function mf_credit_policy_fetch_client(PDO $pdo, string $tenantId, int $clientId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT client_id, tenant_id, monthly_income, employment_status, client_status,
                   document_verification_status, credit_limit, last_seen_credit_limit
            FROM clients
            WHERE tenant_id = ? AND client_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['total_score'] = mf_credit_policy_normalize_score_value($row['total_score'] ?? 0);
        }

        return $row ?: null;
    }
}

if (!function_exists('mf_credit_policy_fetch_latest_score')) {
    function mf_credit_policy_fetch_latest_score(PDO $pdo, string $tenantId, int $clientId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT score_id, ci_id, total_score, credit_rating, max_loan_amount,
                   recommended_interest_rate, computation_date
            FROM credit_scores
            WHERE tenant_id = ? AND client_id = ?
            ORDER BY computation_date DESC, score_id DESC
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('mf_credit_policy_fetch_latest_ci')) {
    function mf_credit_policy_fetch_latest_ci(PDO $pdo, string $tenantId, int $clientId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT ci_id, recommendation, status, investigation_date, completed_at
            FROM credit_investigations
            WHERE tenant_id = ? AND client_id = ? AND status = 'Completed'
            ORDER BY COALESCE(completed_at, investigation_date, created_at) DESC, ci_id DESC
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('mf_credit_policy_band_multiplier')) {
    function mf_credit_policy_band_multiplier(array $policy, float $effectiveScore): float
    {
        $approveMin = (float) ($policy['score_thresholds']['approve_min_score'] ?? 800);
        $reviewMin  = (float) ($policy['score_thresholds']['review_min_score'] ?? 500);

        if ($effectiveScore >= $approveMin) {
            return (float) ($policy['credit_limit']['approve_band_multiplier'] ?? 1.10);
        }
        if ($effectiveScore >= $reviewMin) {
            return (float) ($policy['credit_limit']['review_band_multiplier'] ?? 1.00);
        }

        return (float) ($policy['credit_limit']['reject_band_multiplier'] ?? 0.50);
    }
}

if (!function_exists('mf_credit_policy_round_limit')) {
    function mf_credit_policy_round_limit(float $amount, float $roundTo): float
    {
        $amount = max(0, $amount);
        if ($roundTo <= 0) {
            return round($amount, 2);
        }

        $rounded = floor($amount / $roundTo) * $roundTo;
        return round(max(0, $rounded), 2);
    }
}

if (!function_exists('mf_credit_policy_compute_limit_snapshot')) {
    function mf_credit_policy_compute_limit_snapshot(array $policy, array $client, ?array $score, ?array $ci): array
    {
        $monthlyIncome = (float) ($client['monthly_income'] ?? 0);
        $scoreCeiling = max(1, mf_credit_policy_score_ceiling());
        $defaultScore = (float) ($policy['score_thresholds']['new_client_default_score'] ?? 500);

        $hasScore = $score !== null && isset($score['total_score']);
        $totalScore = $hasScore
            ? (float) mf_credit_policy_normalize_score_value($score['total_score'] ?? 0)
            : $defaultScore;
        $effectiveScore = max(0, min($scoreCeiling, $totalScore));
        $scoreFactor = max(0, min(1, $effectiveScore / $scoreCeiling));
        $bandMultiplier = mf_credit_policy_band_multiplier($policy, $effectiveScore);

        $rawLimit = $monthlyIncome
            * (float) ($policy['credit_limit']['income_multiplier'] ?? 0)
            * $scoreFactor
            * $bandMultiplier;

        $cap = (float) ($policy['credit_limit']['max_credit_limit_cap'] ?? 0);
        if ($cap > 0) {
            $rawLimit = min($rawLimit, $cap);
        }

        $computedLimit = mf_credit_policy_round_limit(
            $rawLimit,
            (float) ($policy['credit_limit']['round_to_nearest'] ?? 0)
        );

        return [
            'can_compute_limit' => true,
            'computed_limit' => $computedLimit,
            'applied_limit' => $computedLimit,
            'score_factor' => $scoreFactor,
            'band_multiplier' => $bandMultiplier,
            'effective_score' => $effectiveScore,
            'used_default_score' => !$hasScore,
        ];
    }
}

if (!function_exists('mf_sync_client_credit_profile')) {
    function mf_sync_client_credit_profile(PDO $pdo, string $tenantId, int $clientId): array
    {
        $policy = mf_get_tenant_credit_policy($pdo, $tenantId);
        $client = mf_credit_policy_fetch_client($pdo, $tenantId, $clientId);

        if (!$client) {
            throw new RuntimeException('Client not found for credit policy evaluation.');
        }

        $score = mf_credit_policy_fetch_latest_score($pdo, $tenantId, $clientId);
        $ci = mf_credit_policy_fetch_latest_ci($pdo, $tenantId, $clientId);
        $snapshot = mf_credit_policy_compute_limit_snapshot($policy, $client, $score, $ci);

        if ($snapshot['can_compute_limit']) {
            $newLimit = (float) ($snapshot['computed_limit'] ?? 0);
            $currentLimit = (float) ($client['credit_limit'] ?? 0);

            if (abs($newLimit - $currentLimit) > 0.009) {
                $update = $pdo->prepare("
                    UPDATE clients
                    SET last_seen_credit_limit = credit_limit,
                        credit_limit = ?,
                        updated_at = NOW()
                    WHERE tenant_id = ? AND client_id = ?
                ");
                $update->execute([$newLimit, $tenantId, $clientId]);
            }

            if ($score && !empty($score['score_id'])) {
                $updateScore = $pdo->prepare("
                    UPDATE credit_scores
                    SET max_loan_amount = ?
                    WHERE score_id = ? AND tenant_id = ?
                ");
                $updateScore->execute([$newLimit, (int) $score['score_id'], $tenantId]);
            }

            $client['last_seen_credit_limit'] = $currentLimit;
            $client['credit_limit'] = $newLimit;
        }

        return [
            'policy' => $policy,
            'client' => $client,
            'score' => $score,
            'ci' => $ci,
            'limit' => $snapshot,
        ];
    }
}

if (!function_exists('mf_credit_policy_format_amount')) {
    function mf_credit_policy_format_amount($amount): string
    {
        return 'PHP ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('mf_credit_policy_compose_note')) {
    function mf_credit_policy_compose_note(string $decision, array $reasons, array $evaluation): string
    {
        $limit = $evaluation['computed_credit_limit'];
        $amount = $evaluation['approved_amount'];
        $score = $evaluation['score']['total_score'];
        $ciRecommendation = $evaluation['ci']['recommendation'];

        $parts = [];
        if ($decision === 'approve') {
            $parts[] = 'Credit policy auto-approved this application.';
        } elseif ($decision === 'review') {
            $parts[] = 'Credit policy routed this application for manual review.';
        } else {
            $parts[] = 'Credit policy rejected this application.';
        }

        if ($amount !== null && $amount > 0) {
            $parts[] = 'Suggested amount: ' . mf_credit_policy_format_amount($amount) . '.';
        }
        if ($limit !== null && $limit > 0) {
            $parts[] = 'Computed credit limit: ' . mf_credit_policy_format_amount($limit) . '.';
        }
        if ($score !== null) {
            $parts[] = 'Latest score: ' . number_format((float) $score, 0) . '/' . mf_credit_policy_score_ceiling() . '.';
        }
        if ($ciRecommendation !== null && $ciRecommendation !== '') {
            $parts[] = 'CI recommendation: ' . $ciRecommendation . '.';
        }
        if (!empty($reasons)) {
            $parts[] = 'Reasons: ' . implode('; ', $reasons) . '.';
        }

        return trim(implode(' ', $parts));
    }
}

if (!function_exists('mf_credit_policy_decode_application_data')) {
    function mf_credit_policy_decode_application_data($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}

if (!function_exists('mf_evaluate_application_policy')) {
    function mf_evaluate_application_policy(PDO $pdo, string $tenantId, int $applicationId): array
    {
        $stmt = $pdo->prepare("
            SELECT la.application_id, la.application_number, la.application_status, la.requested_amount,
                   la.approved_amount, la.loan_term_months, la.application_data, la.client_id, la.product_id,
                   c.monthly_income, c.employment_status, c.client_status, c.document_verification_status,
                   c.credit_limit, c.last_seen_credit_limit,
                   lp.product_name, lp.min_amount, lp.max_amount, lp.minimum_credit_score
            FROM loan_applications la
            JOIN clients c ON c.client_id = la.client_id AND c.tenant_id = la.tenant_id
            JOIN loan_products lp ON lp.product_id = la.product_id AND lp.tenant_id = la.tenant_id
            WHERE la.tenant_id = ? AND la.application_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $applicationId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            throw new RuntimeException('Application not found for credit policy evaluation.');
        }

        $profile = mf_sync_client_credit_profile($pdo, $tenantId, (int) $application['client_id']);
        $policy = $profile['policy'];
        $client = $profile['client'];
        $score = $profile['score'];
        $ci = $profile['ci'];
        $limitSnapshot = $profile['limit'];

        $requestedAmount = round((float) ($application['requested_amount'] ?? 0), 2);
        $clientLimit = (float) ($limitSnapshot['applied_limit'] ?? 0);
        $productMinAmount = (float) ($application['min_amount'] ?? 0);
        $productMaxAmount = (float) ($application['max_amount'] ?? 0);
        $productMinimumScore = (float) mf_credit_policy_normalize_score_value($application['minimum_credit_score'] ?? 0);
        $defaultScore = (float) ($policy['score_thresholds']['new_client_default_score'] ?? 500);
        $hasLatestScore = $score !== null && isset($score['total_score']);
        $totalScore = $hasLatestScore
            ? (float) mf_credit_policy_normalize_score_value($score['total_score'] ?? 0)
            : null;
        $effectiveScore = $totalScore !== null ? $totalScore : $defaultScore;
        $ciRecommendation = trim((string) ($ci['recommendation'] ?? ''));

        $rejectReasons = [];
        $reviewReasons = [];

        if ((float) ($policy['eligibility']['min_monthly_income'] ?? 0) > 0
            && (float) ($client['monthly_income'] ?? 0) < (float) ($policy['eligibility']['min_monthly_income'] ?? 0)
        ) {
            $rejectReasons[] = 'Monthly income is below the minimum requirement.';
        }

        $allowedEmployment = $policy['eligibility']['allowed_employment_statuses'] ?? [];
        $employmentStatus = trim((string) ($client['employment_status'] ?? ''));
        $documentStatus = trim((string) ($client['document_verification_status'] ?? ''));
        if (!empty($allowedEmployment) && !in_array($employmentStatus, $allowedEmployment, true)) {
            $rejectReasons[] = 'Employment status is not allowed by the current credit policy.';
        }

        if (!empty($policy['product_checks']['use_product_min_amount']) && $requestedAmount < $productMinAmount) {
            $rejectReasons[] = 'Requested amount is below the selected product minimum.';
        }
        if (!empty($policy['product_checks']['use_product_max_amount']) && $requestedAmount > $productMaxAmount) {
            $rejectReasons[] = 'Requested amount exceeds the selected product maximum.';
        }

        if ($effectiveScore < (float) ($policy['score_thresholds']['review_min_score'] ?? 0)) {
            $rejectReasons[] = 'Credit score is below the rejection threshold.';
        } elseif ($effectiveScore < (float) ($policy['score_thresholds']['approve_min_score'] ?? 0)) {
            $reviewReasons[] = 'Credit score requires manual review.';
        }

        if (!empty($policy['product_checks']['use_product_minimum_credit_score']) && $effectiveScore < $productMinimumScore) {
            $rejectReasons[] = 'Credit score is below the product minimum credit score.';
        }

        $ciRequired = !empty($policy['ci_rules']['require_ci']);
        $ciRequiredAboveAmount = (float) ($policy['ci_rules']['ci_required_above_amount'] ?? 0);
        if ($ciRequiredAboveAmount > 0 && $requestedAmount > $ciRequiredAboveAmount) {
            $ciRequired = true;
        }

        if ($ciRequired && !$ci) {
            $reviewReasons[] = 'A completed credit investigation is required.';
        }

        if ($ci) {
            if ($ciRecommendation === 'Not Recommended') {
                $rejectReasons[] = 'Credit investigation recommends not proceeding.';
            } elseif (in_array($ciRecommendation, (array) ($policy['ci_rules']['review_ci_values'] ?? []), true)) {
                $reviewReasons[] = 'Credit investigation requires manual review.';
            } elseif ($ciRecommendation !== ''
                && !in_array($ciRecommendation, (array) ($policy['ci_rules']['auto_approve_ci_values'] ?? []), true)
            ) {
                $reviewReasons[] = 'Credit investigation result is outside the auto-approval list.';
            }
        }

        $approvedAmount = null;
        if ($clientLimit > 0) {
            $approvedAmount = min($requestedAmount, $clientLimit);
            if (!empty($policy['product_checks']['use_product_max_amount'])) {
                $approvedAmount = min($approvedAmount, $productMaxAmount);
            }
            $approvedAmount = round($approvedAmount, 2);
        }

        if ($approvedAmount !== null && !empty($policy['product_checks']['use_product_min_amount']) && $approvedAmount < $productMinAmount) {
            $rejectReasons[] = 'Computed eligible amount falls below the product minimum.';
        }

        if ($approvedAmount !== null && $requestedAmount > $approvedAmount) {
            $reviewReasons[] = 'Requested amount exceeds the computed credit limit.';
        }

        $decision = 'approve';
        $decisionReasons = [];
        if (!empty($rejectReasons)) {
            $decision = 'reject';
            $decisionReasons = array_values(array_unique($rejectReasons));
            $approvedAmount = null;
        } elseif (!empty($reviewReasons)) {
            $decision = 'review';
            $decisionReasons = array_values(array_unique($reviewReasons));
        }

        if ($decision === 'approve' && $approvedAmount === null) {
            $decision = 'review';
            $decisionReasons[] = 'Approved amount could not be computed yet.';
        }

        $newStatus = $decision === 'approve'
            ? 'Approved'
            : ($decision === 'review' ? 'Pending Review' : 'Rejected');

        $evaluation = [
            'decision' => $decision,
            'new_status' => $newStatus,
            'requested_amount' => $requestedAmount,
            'approved_amount' => $approvedAmount,
            'computed_credit_limit' => $clientLimit > 0 ? round($clientLimit, 2) : null,
            'reasons' => $decisionReasons,
            'policy' => $policy,
            'client' => [
                'client_id' => (int) ($application['client_id'] ?? 0),
                'monthly_income' => round((float) ($client['monthly_income'] ?? 0), 2),
                'employment_status' => $employmentStatus,
                'client_status' => (string) ($client['client_status'] ?? ''),
                'document_verification_status' => $documentStatus,
                'credit_limit' => round((float) ($client['credit_limit'] ?? 0), 2),
            ],
            'score' => [
                'score_id' => $score !== null ? (int) ($score['score_id'] ?? 0) : null,
                'total_score' => $totalScore,
                'effective_score' => $effectiveScore,
                'used_default_score' => !$hasLatestScore,
                'credit_rating' => $score['credit_rating'] ?? null,
            ],
            'ci' => [
                'ci_id' => $ci !== null ? (int) ($ci['ci_id'] ?? 0) : null,
                'status' => $ci['status'] ?? null,
                'recommendation' => $ciRecommendation !== '' ? $ciRecommendation : null,
            ],
            'product' => [
                'product_id' => (int) ($application['product_id'] ?? 0),
                'product_name' => (string) ($application['product_name'] ?? ''),
                'min_amount' => round($productMinAmount, 2),
                'max_amount' => round($productMaxAmount, 2),
                'minimum_credit_score' => round($productMinimumScore, 2),
            ],
            'evaluated_at' => date('Y-m-d H:i:s'),
        ];

        return [
            'application' => $application,
            'evaluation' => $evaluation,
        ];
    }
}

if (!function_exists('mf_apply_application_policy')) {
    function mf_apply_application_policy(PDO $pdo, string $tenantId, int $applicationId, array $options = []): array
    {
        $result = mf_evaluate_application_policy($pdo, $tenantId, $applicationId);
        $application = $result['application'];
        $evaluation = $result['evaluation'];
        $employeeId = isset($options['employee_id']) && $options['employee_id'] !== null ? (int) $options['employee_id'] : null;
        $sessionUserId = isset($options['session_user_id']) && $options['session_user_id'] !== null ? (int) $options['session_user_id'] : null;
        $now = date('Y-m-d H:i:s');

        $applicationData = mf_credit_policy_decode_application_data($application['application_data'] ?? null);
        $applicationData['credit_policy'] = $evaluation;
        $encodedData = json_encode($applicationData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedData === false) {
            $encodedData = '{}';
        }

        if ($evaluation['decision'] === 'approve') {
            $note = mf_credit_policy_compose_note('approve', $evaluation['reasons'], $evaluation);
            $stmt = $pdo->prepare("
                UPDATE loan_applications
                SET application_status = 'Approved',
                    approved_amount = ?,
                    approved_by = ?,
                    approval_date = ?,
                    approval_notes = ?,
                    reviewed_by = NULL,
                    review_date = NULL,
                    review_notes = NULL,
                    rejected_by = NULL,
                    rejection_date = NULL,
                    rejection_reason = NULL,
                    application_data = ?,
                    updated_at = NOW()
                WHERE tenant_id = ? AND application_id = ?
            ");
            $stmt->execute([
                $evaluation['approved_amount'],
                $employeeId,
                $now,
                $note,
                $encodedData,
                $tenantId,
                $applicationId,
            ]);
            $message = 'Credit policy approved the application.';
            $actionType = 'CREDIT_POLICY_APPROVED';
            $description = $note;
        } elseif ($evaluation['decision'] === 'review') {
            $note = mf_credit_policy_compose_note('review', $evaluation['reasons'], $evaluation);
            $stmt = $pdo->prepare("
                UPDATE loan_applications
                SET application_status = 'Pending Review',
                    approved_amount = ?,
                    reviewed_by = ?,
                    review_date = ?,
                    review_notes = ?,
                    approved_by = NULL,
                    approval_date = NULL,
                    approval_notes = NULL,
                    rejected_by = NULL,
                    rejection_date = NULL,
                    rejection_reason = NULL,
                    application_data = ?,
                    updated_at = NOW()
                WHERE tenant_id = ? AND application_id = ?
            ");
            $stmt->execute([
                $evaluation['approved_amount'],
                $employeeId,
                $now,
                $note,
                $encodedData,
                $tenantId,
                $applicationId,
            ]);
            $message = 'Credit policy sent the application to Pending Review.';
            $actionType = 'CREDIT_POLICY_REVIEW';
            $description = $note;
        } else {
            $note = mf_credit_policy_compose_note('reject', $evaluation['reasons'], $evaluation);
            $stmt = $pdo->prepare("
                UPDATE loan_applications
                SET application_status = 'Rejected',
                    approved_amount = NULL,
                    rejected_by = ?,
                    rejection_date = ?,
                    rejection_reason = ?,
                    approved_by = NULL,
                    approval_date = NULL,
                    approval_notes = NULL,
                    reviewed_by = NULL,
                    review_date = NULL,
                    review_notes = NULL,
                    application_data = ?,
                    updated_at = NOW()
                WHERE tenant_id = ? AND application_id = ?
            ");
            $stmt->execute([
                $employeeId,
                $now,
                $note,
                $encodedData,
                $tenantId,
                $applicationId,
            ]);
            $message = 'Credit policy rejected the application.';
            $actionType = 'CREDIT_POLICY_REJECTED';
            $description = $note;
        }

        $audit = $pdo->prepare("
            INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description)
            VALUES (?, ?, ?, 'loan_application', ?, ?)
        ");
        $audit->execute([
            $sessionUserId ?: null,
            $tenantId,
            $actionType,
            $applicationId,
            $description,
        ]);

        return [
            'status' => 'success',
            'message' => $message,
            'new_status' => $evaluation['new_status'],
            'approved_amount' => $evaluation['approved_amount'],
            'evaluation' => $evaluation,
        ];
    }
}
