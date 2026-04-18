<?php
$policy_console_limit_assignment = isset($policy_console_limit_assignment) && is_array($policy_console_limit_assignment)
    ? $policy_console_limit_assignment
    : policy_console_limit_assignment_defaults();
?>
<div class="policy-blueprint-card-head">
    <div class="policy-blueprint-card-title">
        <h4>Limit Assignment</h4>
        <p class="text-muted">Separate one-time onboarding assignment from the score-based growth model that takes over after first credit assignment.</p>
    </div>
</div>

<div class="policy-blueprint-panel">
    <div class="policy-blueprint-panel-head">
        <div>
            <span class="policy-blueprint-panel-kicker">Onboarding Phase</span>
            <h5>Initial credit limit</h5>
            <p class="text-muted">For first-time users only: Initial Credit Limit = Monthly Income x Configured Percentage.</p>
        </div>
    </div>

    <div class="policy-blueprint-grid policy-blueprint-grid--two">
        <label class="policy-field">
            <span class="policy-field-label">Initial Limit Percentage of Monthly Income <?php echo $policy_console_help('This defines the maximum approved loan percentage compared to the applicant\'s stated monthly income for their very first loan.'); ?></span>
            <input type="number" class="form-control" name="pcc_limit_initial_percent_of_income" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['initial_limit_percent_of_income'] ?? 45)); ?>">
        </label>

        <label class="policy-field">
            <span class="policy-field-label">Default Lending Cap <?php echo $policy_console_help('This acts as a hard ceiling for any newly approved loan limit, regardless of the applicant\'s income percentage calculations.'); ?></span>
            <div class="policy-inline-toggle">
                <div class="policy-decision-rule-switch">
                    <input
                        type="hidden"
                        name="pcc_limit_use_default_lending_cap"
                        value="<?php echo !empty($policy_console_limit_assignment['use_default_lending_cap']) ? '1' : '0'; ?>"
                        data-policy-toggle-input="pcc_limit_use_default_lending_cap"
                    >
                    <button
                        type="button"
                        class="policy-toggle-button <?php echo !empty($policy_console_limit_assignment['use_default_lending_cap']) ? 'is-on' : ''; ?>"
                        data-policy-toggle-button="pcc_limit_use_default_lending_cap"
                        aria-pressed="<?php echo !empty($policy_console_limit_assignment['use_default_lending_cap']) ? 'true' : 'false'; ?>"
                        aria-label="Use default lending cap"
                    >
                        <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                        <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_limit_assignment['use_default_lending_cap']) ? 'On' : 'Off'; ?></span>
                    </button>
                </div>
                <input type="number" class="form-control" name="pcc_limit_default_lending_cap_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['default_lending_cap_amount'] ?? 0)); ?>" placeholder="No default cap">
            </div>
        </label>
    </div>
</div>
