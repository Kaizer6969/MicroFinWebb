<?php
$policy_console_decision_config = isset($policy_console_decision_rules) && is_array($policy_console_decision_rules)
    ? $policy_console_decision_rules
    : policy_console_decision_rules_defaults(
        isset($credit_policy_score_ceiling) ? (int)$credit_policy_score_ceiling : 1000,
        isset($credit_policy_ci_configurable_options) && is_array($credit_policy_ci_configurable_options)
            ? $credit_policy_ci_configurable_options
            : []
    );

$policy_console_workflow = $policy_console_decision_config['workflow'] ?? [];
$policy_console_rule_groups = $policy_console_decision_config['decision_rules'] ?? [];
$policy_console_score_thresholds = $policy_console_rule_groups['score_thresholds'] ?? [];
$policy_console_ci_rules = $policy_console_rule_groups['ci'] ?? [];
$policy_console_borrowing_access_rules = $policy_console_rule_groups['borrowing_access_rules'] ?? [];
$policy_console_manual_review_overrides = $policy_console_rule_groups['manual_review_overrides'] ?? [];
$policy_console_borrower_safeguards = $policy_console_rule_groups['borrower_safeguards'] ?? [];
$policy_console_ci_options = isset($credit_policy_ci_configurable_options) && is_array($credit_policy_ci_configurable_options)
    ? $credit_policy_ci_configurable_options
    : [];
$policy_console_manual_review_available = (($policy_console_workflow['approval_mode'] ?? 'semi_automatic') === 'semi_automatic');
$policy_console_help = static function (string $text, string $label = 'More info'): string {
    return '<span class="policy-help" tabindex="0" role="button" aria-label="'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '" data-help="'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '">!</span>';
};

$policy_console_workflow_labels = [
    'automatic' => 'Automatic',
    'semi_automatic' => 'Semi-Automatic',
    'manual' => 'Manual',
];
?>
<form method="POST" action="admin.php" class="policy-tab-form" id="policy-console-decision-rules-form">
    <input type="hidden" name="action" value="save_policy_console_decision_rules">
    <input type="hidden" name="credit_policy_tab" value="decision_rules">

    <div class="policy-compact-stack">
        <section class="policy-compact-card">
            <div class="policy-save-row">
                <div class="policy-compact-toolbar-copy">
                    <h3>Decision Rules</h3>
                    <p class="text-muted">Configure workflow routing, approval thresholds, investigation rules, borrowing access, review overrides, and borrower protection in one tenant policy page.</p>
                </div>
                <div class="policy-compact-card-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-rounded">save</span>
                        Save Decision Rules
                    </button>
                </div>
            </div>
        </section>

        <section class="policy-compact-card">
            <div class="policy-compact-card-head">
                <div class="policy-compact-card-title">
                    <h4>Workflow Routing</h4>
                    <p class="text-muted">Keep approval workflow separate from the compact decisioning rules below.</p>
                </div>
            </div>

            <div class="policy-form-grid policy-form-grid--one">
                <label class="policy-field">
                    <span class="policy-field-label">Approval Workflow <?php echo $policy_console_help('Controls whether approvals are automatic, semi-automatic, or fully manual.'); ?></span>
                    <select class="form-control" name="pcdr_approval_mode" data-decision-workflow-mode>
                        <?php foreach ($policy_console_workflow_labels as $policy_console_mode_value => $policy_console_mode_label): ?>
                            <option value="<?php echo htmlspecialchars($policy_console_mode_value); ?>" <?php echo (($policy_console_workflow['approval_mode'] ?? 'semi_automatic') === $policy_console_mode_value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($policy_console_mode_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>

        <section class="policy-compact-card">
            <div class="policy-compact-card-head">
                <div class="policy-compact-card-title">
                    <h4>Decision Rules</h4>
                    <p class="text-muted">Toggle each rule group on or off, then adjust the related settings directly under the row when needed.</p>
                </div>
            </div>

            <div class="policy-decision-rule-list">
                <div class="policy-decision-rule-item" data-decision-rule-item>
                    <div class="policy-decision-rule-row">
                        <div class="policy-decision-rule-copy">
                            <div class="policy-decision-rule-title-line">
                                <?php echo $policy_console_help('Controls the tenant-wide reject and hard-approval thresholds used in this workspace.'); ?>
                                <strong>Score Thresholds</strong>
                            </div>
                            <p class="text-muted">Global Auto-Reject Floor and Hard Approval Threshold.</p>
                        </div>
                        <div class="policy-decision-rule-switch">
                            <input
                                type="hidden"
                                name="pcdr_score_thresholds_enabled"
                                value="<?php echo !empty($policy_console_score_thresholds['enabled']) ? '1' : '0'; ?>"
                                data-decision-rule-toggle="score_thresholds"
                                data-policy-toggle-input="score_thresholds"
                            >
                            <button
                                type="button"
                                class="policy-toggle-button <?php echo !empty($policy_console_score_thresholds['enabled']) ? 'is-on' : ''; ?>"
                                data-policy-toggle-button="score_thresholds"
                                aria-pressed="<?php echo !empty($policy_console_score_thresholds['enabled']) ? 'true' : 'false'; ?>"
                                aria-label="Enable Score Thresholds"
                            >
                                <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_score_thresholds['enabled']) ? 'On' : 'Off'; ?></span>
                            </button>
                        </div>
                    </div>
                    <div class="policy-decision-rule-content" data-decision-rule-content="score_thresholds" <?php echo empty($policy_console_score_thresholds['enabled']) ? 'hidden' : ''; ?>>
                        <div class="policy-form-grid policy-form-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">Global Auto-Reject Floor</span>
                                <input type="number" class="form-control" name="pcdr_auto_reject_floor" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" step="1" value="<?php echo htmlspecialchars((string)($policy_console_score_thresholds['auto_reject_floor'] ?? 250)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">Hard Approval Threshold</span>
                                <input type="number" class="form-control" name="pcdr_hard_approval_threshold" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" step="1" value="<?php echo htmlspecialchars((string)($policy_console_score_thresholds['hard_approval_threshold'] ?? 650)); ?>">
                            </label>
                        </div>
                    </div>
                </div>

                <div class="policy-decision-rule-item" data-decision-rule-item>
                    <div class="policy-decision-rule-row">
                        <div class="policy-decision-rule-copy">
                            <div class="policy-decision-rule-title-line">
                                <?php echo $policy_console_help('Use this group to control when the Credit Investigation phase becomes part of the decision process.'); ?>
                                <strong>CI</strong>
                            </div>
                            <p class="text-muted">Enable Credit Investigation phase and set mandatory CI above amount.</p>
                        </div>
                        <div class="policy-decision-rule-switch">
                            <input
                                type="hidden"
                                name="pcdr_ci_enabled"
                                value="<?php echo !empty($policy_console_ci_rules['enabled']) ? '1' : '0'; ?>"
                                data-decision-rule-toggle="ci"
                                data-policy-toggle-input="ci"
                            >
                            <button
                                type="button"
                                class="policy-toggle-button <?php echo !empty($policy_console_ci_rules['enabled']) ? 'is-on' : ''; ?>"
                                data-policy-toggle-button="ci"
                                aria-pressed="<?php echo !empty($policy_console_ci_rules['enabled']) ? 'true' : 'false'; ?>"
                                aria-label="Enable CI"
                            >
                                <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_ci_rules['enabled']) ? 'On' : 'Off'; ?></span>
                            </button>
                        </div>
                    </div>
                    <div class="policy-decision-rule-content" data-decision-rule-content="ci" <?php echo empty($policy_console_ci_rules['enabled']) ? 'hidden' : ''; ?>>
                        <div class="policy-form-grid policy-form-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">Mandatory CI Above Amount</span>
                                <input type="number" class="form-control" name="pcdr_ci_required_above_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_ci_rules['mandatory_ci_above_amount'] ?? 0)); ?>">
                            </label>
                            <div class="policy-blueprint-note">
                                <strong>Phase note</strong>
                                <span>When this rule group is off, the tenant treats CI as outside the default decision flow.</span>
                            </div>
                        </div>

                        <?php if ($policy_console_ci_options !== []): ?>
                            <div class="policy-form-grid policy-form-grid--two">
                                <div class="policy-field">
                                    <span class="policy-field-label">Auto-Approve CI Values</span>
                                    <div class="policy-step-checkboxes">
                                        <?php foreach ($policy_console_ci_options as $policy_console_ci_value): ?>
                                            <label class="policy-step-option">
                                                <input
                                                    type="checkbox"
                                                    name="pcdr_auto_approve_ci_values[]"
                                                    value="<?php echo htmlspecialchars($policy_console_ci_value); ?>"
                                                    <?php echo in_array($policy_console_ci_value, (array)($policy_console_ci_rules['auto_approve_ci_values'] ?? []), true) ? 'checked' : ''; ?>
                                                >
                                                <span><?php echo htmlspecialchars($policy_console_ci_value); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="policy-field">
                                    <span class="policy-field-label">Manual-Review CI Values</span>
                                    <div class="policy-step-checkboxes">
                                        <?php foreach ($policy_console_ci_options as $policy_console_ci_value): ?>
                                            <label class="policy-step-option">
                                                <input
                                                    type="checkbox"
                                                    name="pcdr_review_ci_values[]"
                                                    value="<?php echo htmlspecialchars($policy_console_ci_value); ?>"
                                                    <?php echo in_array($policy_console_ci_value, (array)($policy_console_ci_rules['review_ci_values'] ?? []), true) ? 'checked' : ''; ?>
                                                >
                                                <span><?php echo htmlspecialchars($policy_console_ci_value); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="policy-decision-rule-item" data-decision-rule-item>
                    <div class="policy-decision-rule-row">
                        <div class="policy-decision-rule-copy">
                            <div class="policy-decision-rule-title-line">
                                <?php echo $policy_console_help('Controls borrower access decisions tied to remaining limit and multiple active borrowing.'); ?>
                                <strong>Borrowing Access Rules</strong>
                            </div>
                            <p class="text-muted">Access handling for remaining limit and multiple active loans.</p>
                        </div>
                        <div class="policy-decision-rule-switch">
                            <input
                                type="hidden"
                                name="pcdr_borrowing_access_enabled"
                                value="<?php echo !empty($policy_console_borrowing_access_rules['enabled']) ? '1' : '0'; ?>"
                                data-decision-rule-toggle="borrowing_access_rules"
                                data-policy-toggle-input="borrowing_access_rules"
                            >
                            <button
                                type="button"
                                class="policy-toggle-button <?php echo !empty($policy_console_borrowing_access_rules['enabled']) ? 'is-on' : ''; ?>"
                                data-policy-toggle-button="borrowing_access_rules"
                                aria-pressed="<?php echo !empty($policy_console_borrowing_access_rules['enabled']) ? 'true' : 'false'; ?>"
                                aria-label="Enable Borrowing Access Rules"
                            >
                                <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_borrowing_access_rules['enabled']) ? 'On' : 'Off'; ?></span>
                            </button>
                        </div>
                    </div>
                    <div class="policy-decision-rule-content" data-decision-rule-content="borrowing_access_rules" <?php echo empty($policy_console_borrowing_access_rules['enabled']) ? 'hidden' : ''; ?>>
                        <div class="policy-form-grid policy-form-grid--two">
                            <div class="policy-decision-inline-rule">
                                <div class="policy-inline-toggle-row">
                                    <div class="policy-inline-toggle-row__copy">
                                        <span class="policy-field-label">Allow multiple active loans within remaining limit</span>
                                    </div>
                                    <div class="policy-inline-toggle-row__control">
                                        <input
                                            type="hidden"
                                            name="pcdr_allow_multiple_active_loans"
                                            value="<?php echo !empty($policy_console_borrowing_access_rules['allow_multiple_active_loans_within_remaining_limit']) ? '1' : '0'; ?>"
                                            data-policy-toggle-input="allow_multiple_active_loans"
                                        >
                                        <button
                                            type="button"
                                            class="policy-toggle-button <?php echo !empty($policy_console_borrowing_access_rules['allow_multiple_active_loans_within_remaining_limit']) ? 'is-on' : ''; ?>"
                                            data-policy-toggle-button="allow_multiple_active_loans"
                                            aria-pressed="<?php echo !empty($policy_console_borrowing_access_rules['allow_multiple_active_loans_within_remaining_limit']) ? 'true' : 'false'; ?>"
                                            aria-label="Allow multiple active loans within remaining limit"
                                        >
                                            <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                            <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_borrowing_access_rules['allow_multiple_active_loans_within_remaining_limit']) ? 'On' : 'Off'; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="policy-decision-inline-rule">
                                <div class="policy-inline-toggle-row">
                                    <div class="policy-inline-toggle-row__copy">
                                        <span class="policy-field-label">Stop application if requested amount exceeds remaining limit</span>
                                    </div>
                                    <div class="policy-inline-toggle-row__control">
                                        <input
                                            type="hidden"
                                            name="pcdr_stop_if_exceeds_remaining_limit"
                                            value="<?php echo !empty($policy_console_borrowing_access_rules['stop_application_if_requested_amount_exceeds_remaining_limit']) ? '1' : '0'; ?>"
                                            data-policy-toggle-input="stop_if_exceeds_remaining_limit"
                                        >
                                        <button
                                            type="button"
                                            class="policy-toggle-button <?php echo !empty($policy_console_borrowing_access_rules['stop_application_if_requested_amount_exceeds_remaining_limit']) ? 'is-on' : ''; ?>"
                                            data-policy-toggle-button="stop_if_exceeds_remaining_limit"
                                            aria-pressed="<?php echo !empty($policy_console_borrowing_access_rules['stop_application_if_requested_amount_exceeds_remaining_limit']) ? 'true' : 'false'; ?>"
                                            aria-label="Stop application if requested amount exceeds remaining limit"
                                        >
                                            <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                            <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_borrowing_access_rules['stop_application_if_requested_amount_exceeds_remaining_limit']) ? 'On' : 'Off'; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="policy-decision-rule-item" data-decision-rule-item>
                    <div class="policy-decision-rule-row">
                        <div class="policy-decision-rule-copy">
                            <div class="policy-decision-rule-title-line">
                                <?php echo $policy_console_help('Semi-Automatic workflow can use score-window overrides to push near-threshold cases into manual review.'); ?>
                                <strong>Manual Review Overrides</strong>
                            </div>
                            <p class="text-muted">Review if score is within a configured points window of the approval threshold.</p>
                        </div>
                        <div class="policy-decision-rule-switch">
                            <input
                                type="hidden"
                                name="pcdr_manual_review_overrides_enabled"
                                value="<?php echo !empty($policy_console_manual_review_overrides['enabled']) ? '1' : '0'; ?>"
                                data-decision-rule-toggle="manual_review_overrides"
                                data-policy-toggle-input="manual_review_overrides"
                            >
                            <button
                                type="button"
                                class="policy-toggle-button <?php echo !empty($policy_console_manual_review_overrides['enabled']) ? 'is-on' : ''; ?>"
                                data-policy-toggle-button="manual_review_overrides"
                                data-decision-manual-review-toggle
                                aria-pressed="<?php echo !empty($policy_console_manual_review_overrides['enabled']) ? 'true' : 'false'; ?>"
                                aria-label="Enable Manual Review Overrides"
                                <?php echo $policy_console_manual_review_available ? '' : 'disabled'; ?>
                            >
                                <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_manual_review_overrides['enabled']) ? 'On' : 'Off'; ?></span>
                            </button>
                        </div>
                    </div>
                    <div class="policy-decision-rule-note" data-decision-manual-review-note <?php echo $policy_console_manual_review_available ? 'hidden' : ''; ?>>
                        Available only when Approval Workflow is set to Semi-Automatic.
                    </div>
                    <div class="policy-decision-rule-content" data-decision-rule-content="manual_review_overrides" <?php echo (empty($policy_console_manual_review_overrides['enabled']) || !$policy_console_manual_review_available) ? 'hidden' : ''; ?>>
                        <div class="policy-form-grid policy-form-grid--two">
                            <div class="policy-decision-inline-rule">
                                <div class="policy-inline-toggle-row">
                                    <div class="policy-inline-toggle-row__copy">
                                        <span class="policy-field-label">Review if score is within the points window of approval threshold</span>
                                    </div>
                                    <div class="policy-inline-toggle-row__control">
                                        <input
                                            type="hidden"
                                            name="pcdr_review_if_within_points_window"
                                            value="<?php echo !empty($policy_console_manual_review_overrides['review_if_score_within_points_of_approval_threshold']) ? '1' : '0'; ?>"
                                            data-policy-toggle-input="review_if_within_points_window"
                                        >
                                        <button
                                            type="button"
                                            class="policy-toggle-button <?php echo !empty($policy_console_manual_review_overrides['review_if_score_within_points_of_approval_threshold']) ? 'is-on' : ''; ?>"
                                            data-policy-toggle-button="review_if_within_points_window"
                                            aria-pressed="<?php echo !empty($policy_console_manual_review_overrides['review_if_score_within_points_of_approval_threshold']) ? 'true' : 'false'; ?>"
                                            aria-label="Review if score is within the points window of approval threshold"
                                        >
                                            <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                            <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_manual_review_overrides['review_if_score_within_points_of_approval_threshold']) ? 'On' : 'Off'; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <label class="policy-field">
                                <span class="policy-field-label">Points Window</span>
                                <input type="number" class="form-control" name="pcdr_points_window" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" step="1" value="<?php echo htmlspecialchars((string)($policy_console_manual_review_overrides['points_window'] ?? 25)); ?>">
                            </label>
                        </div>
                    </div>
                </div>

                <div class="policy-decision-rule-item" data-decision-rule-item>
                    <div class="policy-decision-rule-row">
                        <div class="policy-decision-rule-copy">
                            <div class="policy-decision-rule-title-line">
                                <?php echo $policy_console_help('Use borrower safeguards to require additional security support for higher-risk or higher-exposure applications.'); ?>
                                <strong>Borrower Safeguards</strong>
                            </div>
                            <p class="text-muted">Guarantor thresholds, collateral handling, and risk-based security requirements.</p>
                        </div>
                        <div class="policy-decision-rule-switch">
                            <input
                                type="hidden"
                                name="pcdr_borrower_safeguards_enabled"
                                value="<?php echo !empty($policy_console_borrower_safeguards['enabled']) ? '1' : '0'; ?>"
                                data-decision-rule-toggle="borrower_safeguards"
                                data-policy-toggle-input="borrower_safeguards"
                            >
                            <button
                                type="button"
                                class="policy-toggle-button <?php echo !empty($policy_console_borrower_safeguards['enabled']) ? 'is-on' : ''; ?>"
                                data-policy-toggle-button="borrower_safeguards"
                                aria-pressed="<?php echo !empty($policy_console_borrower_safeguards['enabled']) ? 'true' : 'false'; ?>"
                                aria-label="Enable Borrower Safeguards"
                            >
                                <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_borrower_safeguards['enabled']) ? 'On' : 'Off'; ?></span>
                            </button>
                        </div>
                    </div>
                    <div class="policy-decision-rule-content" data-decision-rule-content="borrower_safeguards" <?php echo empty($policy_console_borrower_safeguards['enabled']) ? 'hidden' : ''; ?>>
                        <div class="policy-form-grid policy-form-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">Guarantor Required Above Amount</span>
                                <input type="number" class="form-control" name="pcdr_guarantor_required_above_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_borrower_safeguards['guarantor_required_above_amount'] ?? 50000)); ?>">
                            </label>
                            <div class="policy-decision-inline-rule">
                                <div class="policy-inline-toggle-row">
                                    <div class="policy-inline-toggle-row__copy">
                                        <span class="policy-field-label">Collateral Enabled</span>
                                        <span class="text-muted">Turn on collateral handling as part of borrower protection.</span>
                                    </div>
                                    <div class="policy-inline-toggle-row__control">
                                        <input
                                            type="hidden"
                                            name="pcdr_collateral_enabled"
                                            value="<?php echo !empty($policy_console_borrower_safeguards['collateral_enabled']) ? '1' : '0'; ?>"
                                            data-policy-toggle-input="collateral_enabled"
                                        >
                                        <button
                                            type="button"
                                            class="policy-toggle-button <?php echo !empty($policy_console_borrower_safeguards['collateral_enabled']) ? 'is-on' : ''; ?>"
                                            data-policy-toggle-button="collateral_enabled"
                                            aria-pressed="<?php echo !empty($policy_console_borrower_safeguards['collateral_enabled']) ? 'true' : 'false'; ?>"
                                            aria-label="Enable collateral handling"
                                        >
                                            <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                            <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_borrower_safeguards['collateral_enabled']) ? 'On' : 'Off'; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <label class="policy-field">
                            <span class="policy-field-label">Detailed Rules for Risk-Based Security Requirements</span>
                            <textarea class="form-control" name="pcdr_risk_based_security_requirements" rows="4" placeholder="Describe when guarantor or collateral requirements should become stricter for higher-risk applications."><?php echo htmlspecialchars((string)($policy_console_borrower_safeguards['risk_based_security_requirements'] ?? '')); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>
        </section>
    </div>
</form>
