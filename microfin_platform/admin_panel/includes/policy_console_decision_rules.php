<?php

require_once __DIR__ . '/policy_console_system_defaults.php';

if (!function_exists('policy_console_decision_rules_setting_key')) {
    function policy_console_decision_rules_setting_key(): string
    {
        return 'policy_console_decision_rules';
    }
}

if (!function_exists('policy_console_decision_rules_approval_modes')) {
    function policy_console_decision_rules_approval_modes(): array
    {
        return ['automatic', 'semi_automatic', 'manual'];
    }
}

if (!function_exists('policy_console_decision_rules_defaults')) {
    function policy_console_decision_rules_defaults(int $scoreCeiling, array $ciOptions = []): array
    {
        return policy_console_decision_rules_normalize(
            policy_console_decision_rules_system_defaults(),
            $scoreCeiling,
            $ciOptions
        );
    }
}

if (!function_exists('policy_console_decision_rules_normalize')) {
    function policy_console_decision_rules_normalize($payload, int $scoreCeiling, array $ciOptions = []): array
    {
        $defaults = policy_console_decision_rules_system_defaults();
        $input = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;

        $normalizeToggle = static fn($value): bool => !empty($value) && !in_array($value, ['0', 0, false, 'false'], true);
        $normalizeScore = static function ($value, $fallback) use ($scoreCeiling): int {
            $score = is_numeric($value) ? (float)$value : (float)$fallback;
            return (int)round(min($scoreCeiling, max(0, $score)));
        };
        $normalizeInt = static function ($value, $fallback, int $min = 0, int $max = 1000): int {
            $number = is_numeric($value) ? (float)$value : (float)$fallback;
            return (int)round(min($max, max($min, $number)));
        };
        $normalizeDecimal = static function ($value, $fallback, float $min = 0.0, float $max = 999999999.0): float {
            $number = is_numeric($value) ? (float)$value : (float)$fallback;
            return round(min($max, max($min, $number)), 2);
        };

        $approvalMode = (string)($input['workflow']['approval_mode'] ?? $defaults['workflow']['approval_mode']);
        if (!in_array($approvalMode, policy_console_decision_rules_approval_modes(), true)) {
            $approvalMode = $defaults['workflow']['approval_mode'];
        }

        $legacyScoreGuardrails = is_array($input['score_guardrails'] ?? null) ? $input['score_guardrails'] : [];
        $legacyCiRules = is_array($input['ci_rules'] ?? null) ? $input['ci_rules'] : [];
        $legacyProductChecks = is_array($input['product_checks'] ?? null) ? $input['product_checks'] : [];

        $rulesInput = is_array($input['decision_rules'] ?? null) ? $input['decision_rules'] : [];
        $scoreThresholdsInput = is_array($rulesInput['score_thresholds'] ?? null) ? $rulesInput['score_thresholds'] : [];
        $ciInput = is_array($rulesInput['ci'] ?? null) ? $rulesInput['ci'] : [];
        $borrowingInput = is_array($rulesInput['borrowing_access_rules'] ?? null) ? $rulesInput['borrowing_access_rules'] : [];
        $manualReviewInput = is_array($rulesInput['manual_review_overrides'] ?? null) ? $rulesInput['manual_review_overrides'] : [];
        $borrowerSafeguardsInput = is_array($rulesInput['borrower_safeguards'] ?? null)
            ? $rulesInput['borrower_safeguards']
            : (is_array($input['borrower_safeguards'] ?? null) ? $input['borrower_safeguards'] : []);
        $legacyBorrowerSafeguardsInput = is_array($input['_legacy_borrower_safeguards'] ?? null)
            ? $input['_legacy_borrower_safeguards']
            : [];

        $autoRejectFloor = $normalizeScore(
            $scoreThresholdsInput['auto_reject_floor'] ?? $legacyScoreGuardrails['auto_reject_floor'] ?? null,
            $defaults['decision_rules']['score_thresholds']['auto_reject_floor']
        );
        $hardApprovalThreshold = $normalizeScore(
            $scoreThresholdsInput['hard_approval_threshold'] ?? $legacyScoreGuardrails['approval_candidate_starts'] ?? null,
            $defaults['decision_rules']['score_thresholds']['hard_approval_threshold']
        );
        if ($hardApprovalThreshold < $autoRejectFloor) {
            $hardApprovalThreshold = $autoRejectFloor;
        }

        $allowedCiOptions = array_values(array_filter(array_map('strval', $ciOptions), static fn(string $value): bool => $value !== ''));
        $normalizeCiValues = static function ($values) use ($allowedCiOptions): array {
            if (empty($allowedCiOptions) || !is_array($values)) {
                return [];
            }

            $filtered = [];
            foreach ($values as $value) {
                $option = (string)$value;
                if ($option !== '' && in_array($option, $allowedCiOptions, true) && !in_array($option, $filtered, true)) {
                    $filtered[] = $option;
                }
            }

            return $filtered;
        };

        $legacyManualReviewStart = $normalizeScore(
            $legacyScoreGuardrails['manual_review_starts'] ?? null,
            max($autoRejectFloor, $hardApprovalThreshold - $defaults['decision_rules']['manual_review_overrides']['points_window'])
        );
        $derivedPointsWindow = max(0, $hardApprovalThreshold - $legacyManualReviewStart);

        $manualReviewEnabled = $normalizeToggle(
            $manualReviewInput['enabled'] ?? ($derivedPointsWindow > 0)
        );
        if ($approvalMode !== 'semi_automatic') {
            $manualReviewEnabled = false;
        }

        return [
            'workflow' => [
                'approval_mode' => $approvalMode,
            ],
            'decision_rules' => [
                'score_thresholds' => [
                    'enabled' => $normalizeToggle(
                        $scoreThresholdsInput['enabled'] ?? true
                    ),
                    'auto_reject_floor' => $autoRejectFloor,
                    'hard_approval_threshold' => $hardApprovalThreshold,
                ],
                'ci' => [
                    'enabled' => $normalizeToggle(
                        $ciInput['enabled'] ?? $legacyCiRules['require_ci'] ?? $defaults['decision_rules']['ci']['enabled']
                    ),
                    'mandatory_ci_above_amount' => $normalizeDecimal(
                        $ciInput['mandatory_ci_above_amount'] ?? $legacyCiRules['ci_required_above_amount'] ?? null,
                        $defaults['decision_rules']['ci']['mandatory_ci_above_amount']
                    ),
                    'auto_approve_ci_values' => $normalizeCiValues(
                        $ciInput['auto_approve_ci_values'] ?? $legacyCiRules['auto_approve_ci_values'] ?? []
                    ),
                    'review_ci_values' => $normalizeCiValues(
                        $ciInput['review_ci_values'] ?? $legacyCiRules['review_ci_values'] ?? []
                    ),
                ],
                'borrowing_access_rules' => [
                    'enabled' => $normalizeToggle(
                        $borrowingInput['enabled'] ?? true
                    ),
                    'allow_multiple_active_loans_within_remaining_limit' => $normalizeToggle(
                        $borrowingInput['allow_multiple_active_loans_within_remaining_limit'] ?? false
                    ),
                    'stop_application_if_requested_amount_exceeds_remaining_limit' => $normalizeToggle(
                        $borrowingInput['stop_application_if_requested_amount_exceeds_remaining_limit']
                            ?? $legacyProductChecks['use_product_max_amount']
                            ?? $defaults['decision_rules']['borrowing_access_rules']['stop_application_if_requested_amount_exceeds_remaining_limit']
                    ),
                ],
                'manual_review_overrides' => [
                    'enabled' => $manualReviewEnabled,
                    'review_if_score_within_points_of_approval_threshold' => $normalizeToggle(
                        $manualReviewInput['review_if_score_within_points_of_approval_threshold']
                            ?? $defaults['decision_rules']['manual_review_overrides']['review_if_score_within_points_of_approval_threshold']
                    ),
                    'points_window' => $normalizeInt(
                        $manualReviewInput['points_window'] ?? $derivedPointsWindow,
                        $defaults['decision_rules']['manual_review_overrides']['points_window'],
                        0,
                        $scoreCeiling
                    ),
                ],
                'borrower_safeguards' => [
                    'enabled' => $normalizeToggle(
                        $borrowerSafeguardsInput['enabled']
                            ?? $legacyBorrowerSafeguardsInput['enabled']
                            ?? $defaults['decision_rules']['borrower_safeguards']['enabled']
                    ),
                    'guarantor_required_above_amount' => $normalizeDecimal(
                        $borrowerSafeguardsInput['guarantor_required_above_amount']
                            ?? $legacyBorrowerSafeguardsInput['guarantor_required_above_amount']
                            ?? null,
                        $defaults['decision_rules']['borrower_safeguards']['guarantor_required_above_amount']
                    ),
                    'collateral_enabled' => $normalizeToggle(
                        $borrowerSafeguardsInput['collateral_enabled']
                            ?? $legacyBorrowerSafeguardsInput['collateral_enabled']
                            ?? $defaults['decision_rules']['borrower_safeguards']['collateral_enabled']
                    ),
                    'risk_based_security_requirements' => substr(
                        trim((string)(
                            $borrowerSafeguardsInput['risk_based_security_requirements']
                                ?? $legacyBorrowerSafeguardsInput['risk_based_security_requirements']
                                ?? $defaults['decision_rules']['borrower_safeguards']['risk_based_security_requirements']
                        )),
                        0,
                        1000
                    ),
                ],
            ],
        ];
    }
}

if (!function_exists('policy_console_decision_rules_legacy_borrower_safeguards_load')) {
    function policy_console_decision_rules_legacy_borrower_safeguards_load(PDO $pdo, string $tenantId): array
    {
        $raw = admin_get_system_setting($pdo, $tenantId, 'policy_console_collections_safeguards', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $legacySafeguards = is_array($decoded['borrower_safeguards'] ?? null)
            ? $decoded['borrower_safeguards']
            : [];
        if ($legacySafeguards === []) {
            return [];
        }

        $guarantorAmount = is_numeric($legacySafeguards['guarantor_required_above_amount'] ?? null)
            ? round(max(0, (float)$legacySafeguards['guarantor_required_above_amount']), 2)
            : 0.0;
        $collateralAmount = is_numeric($legacySafeguards['collateral_required_above_amount'] ?? null)
            ? round(max(0, (float)$legacySafeguards['collateral_required_above_amount']), 2)
            : 0.0;

        return [
            'enabled' => ($guarantorAmount > 0 || $collateralAmount > 0),
            'guarantor_required_above_amount' => $guarantorAmount,
            'collateral_enabled' => ($collateralAmount > 0),
            'risk_based_security_requirements' => '',
        ];
    }
}

if (!function_exists('policy_console_decision_rules_load')) {
    function policy_console_decision_rules_load(PDO $pdo, string $tenantId, int $scoreCeiling, array $ciOptions = []): array
    {
        $legacyBorrowerSafeguards = policy_console_decision_rules_legacy_borrower_safeguards_load($pdo, $tenantId);
        $raw = admin_get_system_setting($pdo, $tenantId, policy_console_decision_rules_setting_key(), '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                if ($legacyBorrowerSafeguards !== []) {
                    $decoded['_legacy_borrower_safeguards'] = $legacyBorrowerSafeguards;
                }
                return policy_console_decision_rules_normalize($decoded, $scoreCeiling, $ciOptions);
            }
        }

        if ($legacyBorrowerSafeguards !== []) {
            return policy_console_decision_rules_normalize(
                ['_legacy_borrower_safeguards' => $legacyBorrowerSafeguards],
                $scoreCeiling,
                $ciOptions
            );
        }

        return policy_console_decision_rules_defaults($scoreCeiling, $ciOptions);
    }
}

if (!function_exists('policy_console_decision_rules_build_from_post')) {
    function policy_console_decision_rules_build_from_post(array $source, int $scoreCeiling, array $ciOptions = []): array
    {
        $payload = [
            'workflow' => [
                'approval_mode' => $source['pcdr_approval_mode'] ?? null,
            ],
            'decision_rules' => [
                'score_thresholds' => [
                    'enabled' => $source['pcdr_score_thresholds_enabled'] ?? 0,
                    'auto_reject_floor' => $source['pcdr_auto_reject_floor'] ?? null,
                    'hard_approval_threshold' => $source['pcdr_hard_approval_threshold'] ?? null,
                ],
                'ci' => [
                    'enabled' => $source['pcdr_ci_enabled'] ?? 0,
                    'mandatory_ci_above_amount' => $source['pcdr_ci_required_above_amount'] ?? null,
                    'auto_approve_ci_values' => isset($source['pcdr_auto_approve_ci_values']) && is_array($source['pcdr_auto_approve_ci_values'])
                        ? $source['pcdr_auto_approve_ci_values']
                        : [],
                    'review_ci_values' => isset($source['pcdr_review_ci_values']) && is_array($source['pcdr_review_ci_values'])
                        ? $source['pcdr_review_ci_values']
                        : [],
                ],
                'borrowing_access_rules' => [
                    'enabled' => $source['pcdr_borrowing_access_enabled'] ?? 0,
                    'allow_multiple_active_loans_within_remaining_limit' => $source['pcdr_allow_multiple_active_loans'] ?? 0,
                    'stop_application_if_requested_amount_exceeds_remaining_limit' => $source['pcdr_stop_if_exceeds_remaining_limit'] ?? 0,
                ],
                'manual_review_overrides' => [
                    'enabled' => $source['pcdr_manual_review_overrides_enabled'] ?? 0,
                    'review_if_score_within_points_of_approval_threshold' => $source['pcdr_review_if_within_points_window'] ?? 0,
                    'points_window' => $source['pcdr_points_window'] ?? null,
                ],
                'borrower_safeguards' => [
                    'enabled' => $source['pcdr_borrower_safeguards_enabled'] ?? 0,
                    'guarantor_required_above_amount' => $source['pcdr_guarantor_required_above_amount'] ?? null,
                    'collateral_enabled' => $source['pcdr_collateral_enabled'] ?? 0,
                    'risk_based_security_requirements' => $source['pcdr_risk_based_security_requirements'] ?? '',
                ],
            ],
        ];

        return policy_console_decision_rules_normalize($payload, $scoreCeiling, $ciOptions);
    }
}
