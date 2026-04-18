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
.policy-decision-rule-list {
    display: flex;
    flex-direction: column;
}
.policy-decision-rule-item {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color, #2d3748);
    transition: background-color 0.2s;
    background-color: var(--card-bg, #1a202c);
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
    margin-bottom: 12px;
}
.policy-decision-rule-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
}
.policy-decision-input-group {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    padding-top: 4px;
    padding-bottom: 4px;
    transition: opacity 0.2s ease;
}
.policy-decision-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 8px;
}
.policy-decision-field-col {
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
}
.policy-decision-field-label {
    font-size: 14px;
    font-weight: 500;
    color: #cbd5e0;
    white-space: nowrap;
}
.policy-decision-field .form-control {
    background-color: transparent;
    border: none;
    border-bottom: 1px dashed #4a5568;
    color: #63b3ed;
    border-radius: 0;
    padding: 4px 8px;
    font-size: 15px;
    font-weight: 600;
    width: auto;
    min-width: 60px;
    max-width: 100px;
    text-align: center;
    transition: all 0.2s ease;
    box-shadow: none;
}
.policy-decision-field .form-control:focus {
    outline: none;
    border-bottom: 1px solid #63b3ed;
    background-color: rgba(255, 255, 255, 0.05);
    box-shadow: none;
}
/* Hide number arrows for a cleaner minimalist text look */
.policy-decision-field .form-control::-webkit-outer-spin-button,
.policy-decision-field .form-control::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.policy-decision-field .form-control[type=number] {
  -moz-appearance: textfield;
}

/* Pill Checkbox Styles */
.policy-pill-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}
.policy-pill-label {
    cursor: pointer;
    display: inline-block;
}
.policy-pill-label input[type="checkbox"] {
    display: none;
}
.policy-pill-button {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    background-color: #2d3748;
    color: #a0aec0;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid #4a5568;
    transition: all 0.2s ease;
    user-select: none;
    opacity: 0.6;
}
.policy-pill-label input[type="checkbox"]:checked + .policy-pill-button {
    background-color: #3182ce;
    color: #ffffff;
    border-color: #3182ce;
    opacity: 1;
}
.policy-pill-label:hover .policy-pill-button {
    opacity: 0.8;
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

.policy-decision-category-header {
    background-color: #2d3748;
    color: #e2e8f0;
    padding: 12px 20px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #1a202c;
    border-top: 1px solid #1a202c;
    margin-top: 0;
}
.policy-decision-category-header:first-child {
    border-top: none;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}
</style>

<form method="POST" action="admin.php" class="policy-tab-form" id="policy-console-decision-rules-form">
    <input type="hidden" name="action" value="save_policy_console_decision_rules">
    <input type="hidden" name="credit_policy_tab" value="decision_rules">

    <div class="policy-compact-stack">
        <section class="policy-compact-card" style="margin-bottom: 16px;">
            <div class="policy-save-row">
                <div class="policy-compact-toolbar-copy">
                    <h3 style="margin-bottom: 4px;">Eligibility Criteria & Risk Tolerances</h3>
                    <p class="text-muted" style="font-size: 13px;">Manage fine-grained risk tolerances with independent master toggles.</p>
                </div>
            </div>
        </section>

        <section class="policy-compact-card">
            <div class="policy-decision-rule-list">
                
                <div class="policy-decision-category-header">
                    <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Demographics
                </div>
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
                        <div class="policy-decision-field policy-decision-field-col">
                            <span class="policy-decision-field-label">Eligible Statuses</span>
                            <?php 
                                $selectedStatuses = is_array($policy_console_demographics['eligible_statuses'] ?? null) ? $policy_console_demographics['eligible_statuses'] : []; 
                                $statusOptions = [
                                    'full_time' => 'Full Time',
                                    'part_time' => 'Part Time',
                                    'contract' => 'Contract / Freelance',
                                    'self_employed' => 'Self Employed',
                                    'casual' => 'Casual / Seasonal',
                                    'retired' => 'Retired / Pensioner',
                                    'student' => 'Student',
                                    'unemployed' => 'Unemployed'
                                ];
                            ?>
                            <div class="policy-pill-list">
                                <?php foreach($statusOptions as $val => $text): ?>
                                    <label class="policy-pill-label">
                                        <input type="checkbox" name="pcdr_eligible_statuses[]" value="<?php echo $val; ?>" <?php echo in_array($val, $selectedStatuses) ? 'checked' : ''; ?>>
                                        <span class="policy-pill-button"><?php echo $text; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="policy-decision-category-header">
                    <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                    Affordability
                </div>
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

                <div class="policy-decision-category-header">
                    <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v4.7c0 4.67-3.13 8.94-7 10.02-3.87-1.08-7-5.35-7-10.02v-4.7l7-3.12zm-2 11.82l-3.5-3.5 1.41-1.41L10 12.17l6.59-6.59L18 7l-8 8z"/></svg>
                    Guardrails
                </div>
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

                <div class="policy-decision-category-header">
                    <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                    Exposure
                </div>
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

    // Handle label clicks manually to prevent bubbling/stealing focus when inside .policy-pill-list rows if needed
});
</script>
