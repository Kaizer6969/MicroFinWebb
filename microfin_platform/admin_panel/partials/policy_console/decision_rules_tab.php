<?php
$policy_console_decision_config = isset($policy_console_decision_rules) && is_array($policy_console_decision_rules)
    ? $policy_console_decision_rules
    : policy_console_decision_rules_defaults(
        isset($credit_policy_score_ceiling) ? (int)$credit_policy_score_ceiling : 1000,
        isset($credit_policy_ci_configurable_options) && is_array($credit_policy_ci_configurable_options)
            ? $credit_policy_ci_configurable_options : []
    );

$policy_console_workflow = $policy_console_decision_config['workflow'] ?? [];
$policy_console_rule_groups = $policy_console_decision_config['decision_rules'] ?? [];
$policy_console_demographics = $policy_console_rule_groups['demographics'] ?? [];
$policy_console_affordability = $policy_console_rule_groups['affordability'] ?? [];
$policy_console_guardrails = $policy_console_rule_groups['guardrails'] ?? [];
$policy_console_exposure = $policy_console_rule_groups['exposure'] ?? [];

$policy_console_help = static function (string $text, string ...$label): string {
    $labelText = $label[0] ?? 'More info';
    return '<span class="policy-help" tabindex="0" role="button" aria-label="'
        . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8')
        . '" data-help="'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '">i</span>';
};

function renderToggleHeader($label, $helpText, $name, $value) {
    global $policy_console_help;
    $isOn = !empty($value);
    $isOnClass = $isOn ? 'is-on' : '';
    $ariaPressed = $isOn ? 'true' : 'false';
    $labelState = $isOn ? 'On' : 'Off';
    return "
        <div class=\"policy-decision-rule-header\">
            <div class=\"policy-decision-rule-label\">
                <strong>" . htmlspecialchars($label) . "</strong>
                " . $policy_console_help($helpText) . "
            </div>
            <div class=\"policy-inline-toggle-row__control\" style=\"transform: scale(0.85); margin: 0;\">
                <input type=\"hidden\" name=\"{$name}\" value=\"" . ($isOn ? '1' : '0') . "\" data-policy-toggle-input=\"{$name}\">
                <button type=\"button\" class=\"policy-toggle-button {$isOnClass}\" data-policy-toggle-button=\"{$name}\" aria-pressed=\"{$ariaPressed}\" aria-label=\"{$label}\">
                    <span class=\"policy-toggle-button__track\"><span class=\"policy-toggle-button__thumb\"></span></span>
                    <span class=\"policy-toggle-button__label\" data-policy-toggle-label>{$labelState}</span>
                </button>
            </div>
        </div>
    ";
}
?>
<style>
.policy-rules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
    gap: 16px;
    align-items: start;
}
.policy-decision-rule-list {
    display: flex;
    flex-direction: column;
}
.policy-decision-rule-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color, #2d3748);
    transition: background-color 0.2s;
}
.policy-decision-rule-item:last-child {
    border-bottom: none;
}
.policy-decision-rule-item:hover {
    background-color: rgba(255, 255, 255, 0.02);
}
.policy-decision-rule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.policy-decision-rule-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}
.policy-decision-input-group {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 4px;
    transition: opacity 0.2s ease;
}
.policy-decision-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 160px;
}
.policy-decision-field-col {
    flex-direction: column;
    align-items: flex-start;
}
.policy-decision-field-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted, #a0aec0);
    white-space: nowrap;
}
.policy-decision-field .form-control {
    background-color: #1a202c;
    border: 1px solid #4a5568;
    color: #fff;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 13px;
    width: 100%;
    max-width: 160px;
}
.policy-decision-field select.form-control[multiple] {
    height: auto;
    min-height: 100px;
    max-width: 100%;
}
.policy-help {
    position: relative; display: inline-flex; align-items: center; justify-content: center;
    width: 16px; height: 16px; border-radius: 50%; background-color: #4a5568; color: #cbd5e0;
    font-size: 10px; font-weight: bold; cursor: help; z-index: 20; font-style: italic; font-family: serif;
}
.policy-help:hover { background-color: #718096; color: white; }
.policy-help:hover::after {
    content: attr(data-help); position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(-6px);
    width: 240px; padding: 10px; background-color: #2d3748; border: 1px solid #4a5568; color: #e2e8f0;
    font-size: 12px; font-weight: normal; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    z-index: 1000; pointer-events: none; white-space: normal; text-align: left; font-family: sans-serif; font-style: normal;
}
.is-visually-disabled { opacity: 0.35; pointer-events: none; filter: grayscale(1); }
</style>

<form method="POST" action="admin.php" class="policy-tab-form" id="policy-console-decision-rules-form">
    <input type="hidden" name="action" value="save_policy_console_decision_rules">
    <input type="hidden" name="credit_policy_tab" value="decision_rules">

    <div class="policy-compact-stack">
        <section class="policy-compact-card" style="margin-bottom: 16px;">
            <div class="policy-save-row">
                <div class="policy-compact-toolbar-copy">
                    <h3 style="margin-bottom: 4px;">Risk Tolerances</h3>
                    <p class="text-muted" style="font-size: 13px;">Manage fine-grained risk tolerances with independent master toggles.</p>
                </div>
            </div>
        </section>

        <div class="policy-rules-grid">

            <!-- Demographics -->
            <section class="policy-compact-card">
                <div class="policy-compact-card-head" style="padding: 14px 16px;"><div class="policy-compact-card-title"><h4 style="font-size: 15px; margin: 0;">Demographics</h4></div></div>
                <div class="policy-decision-rule-list">
                    
                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Age Restrictions', 'Controls demographic age eligibility.', 'pcdr_age_enabled', $policy_console_demographics['age_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_age_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Min Age</span>
                                <input type="number" class="form-control" name="pcdr_min_age" value="<?php echo htmlspecialchars((string)($policy_console_demographics['min_age'] ?? '')); ?>">
                            </label>
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Max Age</span>
                                <input type="number" class="form-control" name="pcdr_max_age" value="<?php echo htmlspecialchars((string)($policy_console_demographics['max_age'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Employment Tenure', 'Minimum required months of employment.', 'pcdr_employment_tenure_enabled', $policy_console_demographics['employment_tenure_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_employment_tenure_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Min Months</span>
                                <input type="number" class="form-control" name="pcdr_min_employment_months" value="<?php echo htmlspecialchars((string)($policy_console_demographics['min_employment_months'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Residency Tenure', 'Minimum required months of living at current residence.', 'pcdr_residency_tenure_enabled', $policy_console_demographics['residency_tenure_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_residency_tenure_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Min Months</span>
                                <input type="number" class="form-control" name="pcdr_min_residency_months" value="<?php echo htmlspecialchars((string)($policy_console_demographics['min_residency_months'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Employment Status', 'Which employment statuses are allowed.', 'pcdr_employment_status_enabled', $policy_console_demographics['employment_status_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_employment_status_enabled">
                            <label class="policy-decision-field policy-decision-field-col"><span class="policy-decision-field-label">Eligible Statuses (Hold Ctrl to select multiple)</span>
                            <?php $selectedStatuses = is_array($policy_console_demographics['eligible_statuses'] ?? null) ? $policy_console_demographics['eligible_statuses'] : []; ?>
                                <select multiple class="form-control" name="pcdr_eligible_statuses[]">
                                    <option value="full_time" <?php echo in_array('full_time', $selectedStatuses) ? 'selected' : ''; ?>>Full Time</option>
                                    <option value="part_time" <?php echo in_array('part_time', $selectedStatuses) ? 'selected' : ''; ?>>Part Time</option>
                                    <option value="contract" <?php echo in_array('contract', $selectedStatuses) ? 'selected' : ''; ?>>Contract / Freelance</option>
                                    <option value="self_employed" <?php echo in_array('self_employed', $selectedStatuses) ? 'selected' : ''; ?>>Self Employed (Business Owner)</option>
                                    <option value="casual" <?php echo in_array('casual', $selectedStatuses) ? 'selected' : ''; ?>>Casual / Seasonal worker</option>
                                    <option value="retired" <?php echo in_array('retired', $selectedStatuses) ? 'selected' : ''; ?>>Retired / Pensioner</option>
                                    <option value="student" <?php echo in_array('student', $selectedStatuses) ? 'selected' : ''; ?>>Student</option>
                                    <option value="unemployed" <?php echo in_array('unemployed', $selectedStatuses) ? 'selected' : ''; ?>>Unemployed</option>
                                </select>
                            </label>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Affordability -->
            <section class="policy-compact-card">
                <div class="policy-compact-card-head" style="padding: 14px 16px;"><div class="policy-compact-card-title"><h4 style="font-size: 15px; margin: 0;">Affordability</h4></div></div>
                <div class="policy-decision-rule-list">

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Minimum Income', 'Minimum gross monthly income requirement.', 'pcdr_income_enabled', $policy_console_affordability['income_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_income_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Min Income /mo</span>
                                <input type="number" step="0.01" class="form-control" name="pcdr_min_monthly_income" value="<?php echo htmlspecialchars((string)($policy_console_affordability['min_monthly_income'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Debt-to-Income (DTI)', 'Maximum DTI ratio percentage.', 'pcdr_dti_enabled', $policy_console_affordability['dti_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_dti_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Max Ratio (%)</span>
                                <input type="number" step="0.01" class="form-control" name="pcdr_max_dti_percentage" value="<?php echo htmlspecialchars((string)($policy_console_affordability['max_dti_percentage'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Payment-to-Income (PTI)', 'Maximum PTI ratio percentage.', 'pcdr_pti_enabled', $policy_console_affordability['pti_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_pti_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Max Ratio (%)</span>
                                <input type="number" step="0.01" class="form-control" name="pcdr_max_pti_percentage" value="<?php echo htmlspecialchars((string)($policy_console_affordability['max_pti_percentage'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Guardrails -->
            <section class="policy-compact-card">
                <div class="policy-compact-card-head" style="padding: 14px 16px;"><div class="policy-compact-card-title"><h4 style="font-size: 15px; margin: 0;">Guardrails</h4></div></div>
                <div class="policy-decision-rule-list">

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Score Thresholds', 'Reject and Hard Approval score limits.', 'pcdr_score_thresholds_enabled', $policy_console_guardrails['score_thresholds_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_score_thresholds_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Auto Reject Under</span>
                                <input type="number" class="form-control" name="pcdr_auto_reject_floor" value="<?php echo htmlspecialchars((string)($policy_console_guardrails['auto_reject_floor'] ?? '')); ?>">
                            </label>
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Auto Approve Over</span>
                                <input type="number" class="form-control" name="pcdr_hard_approval_threshold" value="<?php echo htmlspecialchars((string)($policy_console_guardrails['hard_approval_threshold'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Cooling Period', 'Days required to wait after rejection.', 'pcdr_cooling_period_enabled', $policy_console_guardrails['cooling_period_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_cooling_period_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Penalty Days</span>
                                <input type="number" class="form-control" name="pcdr_rejected_cooling_days" value="<?php echo htmlspecialchars((string)($policy_console_guardrails['rejected_cooling_days'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Exposure -->
            <section class="policy-compact-card">
                <div class="policy-compact-card-head" style="padding: 14px 16px;"><div class="policy-compact-card-title"><h4 style="font-size: 15px; margin: 0;">Exposure</h4></div></div>
                <div class="policy-decision-rule-list">

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('New Borrower Cap', 'Maximum amount allowed for a first-time borrower.', 'pcdr_new_borrower_cap_enabled', $policy_console_exposure['new_borrower_cap_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_new_borrower_cap_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Max 1st Loan</span>
                                <input type="number" step="0.01" class="form-control" name="pcdr_first_loan_max_amount" value="<?php echo htmlspecialchars((string)($policy_console_exposure['first_loan_max_amount'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Multiple Active Loans', 'Allow borrowers to have more than one active loan.', 'pcdr_multiple_active_loans_enabled', $policy_console_exposure['multiple_active_loans_enabled']); ?>
                        <!-- No inputs required -->
                    </div>

                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Guarantor Required', 'Requires a guarantor if the loan amount exceeds a specific cap.', 'pcdr_guarantor_required_enabled', $policy_console_exposure['guarantor_required_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_guarantor_required_enabled">
                            <label class="policy-decision-field"><span class="policy-decision-field-label">Max No-Guarantor</span>
                                <input type="number" step="0.01" class="form-control" name="pcdr_guarantor_required_above_amount" value="<?php echo htmlspecialchars((string)($policy_console_exposure['guarantor_required_above_amount'] ?? '')); ?>">
                            </label>
                        </div>
                    </div>

                </div>
            </section>
        </div>

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('policy-console-decision-rules-form');
    if (!form) return;

    form.querySelectorAll('.policy-toggle-button').forEach(btn => {
        const toggleName = btn.getAttribute('data-policy-toggle-button');
        const targetGroup = form.querySelector(`.toggle-group-${toggleName}`);
        
        if (targetGroup) {
            const observer = new MutationObserver(() => {
                const isNowOff = !btn.classList.contains('is-on');
                targetGroup.classList.toggle('is-visually-disabled', isNowOff);
            });
            observer.observe(btn, { attributes: true, attributeFilter: ['class'] });

            if (!btn.classList.contains('is-on')) {
                targetGroup.classList.add('is-visually-disabled');
            }
        }
    });
});
</script>
