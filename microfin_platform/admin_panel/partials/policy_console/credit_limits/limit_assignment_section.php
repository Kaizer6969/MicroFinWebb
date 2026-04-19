<?php
$policy_console_limit_assignment = isset($policy_console_limit_assignment) && is_array($policy_console_limit_assignment)
    ? $policy_console_limit_assignment
    : policy_console_limit_assignment_defaults();

$system_defaults = policy_console_credit_limits_system_defaults();
$default_limit_assignment = $system_defaults['limit_assignment'] ?? [];
$is_limit_assignment_default = ($policy_console_limit_assignment == $default_limit_assignment);
?>
<div class="policy-blueprint-card-head" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div class="policy-blueprint-card-title">
        <span class="policy-blueprint-kicker" style="margin: 0; padding: 0 0 6px 0;">Onboarding Phase</span>
        <h4 style="margin-bottom: 0;">Initial credit limit</h4>
        <p class="text-muted" style="margin-top: 4px;">For first-time users only: Initial Credit Limit = Monthly Income x Configured Percentage.</p>
    </div>
    <div>
        <?php if ($is_limit_assignment_default): ?>
            <span style="font-size: 12px; padding: 4px 8px; border-radius: 12px; background: var(--bg-surface-secondary); color: var(--text-muted); border: 1px solid var(--border-color);">
                System Default
            </span>
        <?php else: ?>
            <span style="font-size: 12px; padding: 4px 8px; border-radius: 12px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                Modified
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="policy-blueprint-grid policy-blueprint-grid--two">
        <label class="policy-field">
            <span class="policy-field-label">Initial Limit Percentage of Monthly Income <?php echo $policy_console_help('This defines the maximum approved loan percentage compared to the applicant\'s stated monthly income for their very first loan.'); ?></span>
            <input type="number" class="form-control" id="pcc_limit_initial_percent_of_income" name="pcc_limit_initial_percent_of_income" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['initial_limit_percent_of_income'] ?? 40)); ?>">
            
            <div id="limit_dti_mapping_warning" style="display: none; background: rgba(var(--danger-rgb, 220, 38, 38), 0.1); border-left: 3px solid #dc2626; padding: 12px; border-radius: 4px; font-size: 13px; color: var(--text-muted); line-height: 1.4; margin-top: 12px;">
                <strong style="color: #dc2626;">/!\ Warning:</strong> This percentage is higher than your Max DTI Ratio (<span class="limit_warning_current_dti">X</span>%) rule. Onboarding users may be assigned an initial limit that the strict DTI rule will instantly reject them from borrowing.
            </div>
        </label>

        <div class="policy-field">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <span class="policy-field-label" style="margin-bottom: 0;">
                    First-Time Max Limit <?php echo $policy_console_help('This acts as a hard ceiling for any newly approved loan limit, regardless of the applicant\'s income percentage calculations.'); ?>
                    
                    <span id="pcc_limit_lending_cap_warning_icon" style="position: relative; display: <?php echo !empty($policy_console_limit_assignment['use_default_lending_cap']) ? 'inline-block' : 'none'; ?>; margin-left: 6px; cursor: help; color: #d97706; font-weight: bold; font-family: monospace;">
                        /!\
                        <div id="pcc_limit_lending_cap_warning_tooltip" style="position: absolute; bottom: 120%; left: 50%; transform: translateX(-50%); width: 280px; background: #fff; padding: 12px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 13px; color: var(--text-muted); font-weight: normal; line-height: 1.4; text-align: left; z-index: 100; transition: opacity 0.3s; opacity: 0; pointer-events: none;">
                            <strong style="color: #d97706; display: block; margin-bottom: 4px;">Warning</strong>
                            Turning this on will cap the limit calculation based on the Initial Limit Percentage of Monthly Income to the amount specified below. For example, if an applicant's monthly income qualifies them for a ₱5,000 limit, but you set this First-Time Max Limit to ₱3,000, their final approved limit will be capped at <strong>₱3,000</strong>.
                        </div>
                    </span>
                </span>
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
            </div>
            
            <div style="position: relative;" id="pcc_limit_default_lending_cap_wrapper">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px;">₱</span>
                <input type="number" class="form-control" id="pcc_limit_default_lending_cap_input" name="pcc_limit_default_lending_cap_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['default_lending_cap_amount'] ?? 0)); ?>" placeholder="0.00" style="width: 100%; padding-left: 24px;" <?php echo empty($policy_console_limit_assignment['use_default_lending_cap']) ? 'disabled' : ''; ?>>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const toggleInput = document.querySelector('input[name="pcc_limit_use_default_lending_cap"]');
                    const capInput = document.getElementById('pcc_limit_default_lending_cap_input');
                    const warningIcon = document.getElementById('pcc_limit_lending_cap_warning_icon');
                    const warningTooltip = document.getElementById('pcc_limit_lending_cap_warning_tooltip');
                    let timeoutId = null;

                    if(warningIcon && warningTooltip) {
                        warningIcon.addEventListener('mouseenter', function() {
                            clearTimeout(timeoutId);
                            warningTooltip.style.opacity = '1';
                        });
                        warningIcon.addEventListener('mouseleave', function() {
                            warningTooltip.style.opacity = '0';
                        });
                    }
                    
                    if(toggleInput && capInput && warningIcon && warningTooltip) {
                        toggleInput.addEventListener('change', function(e) {
                            const isOn = this.value === '1';
                            capInput.disabled = !isOn;
                            warningIcon.style.display = isOn ? 'inline-block' : 'none';
                            
                            if (isOn) {
                                clearTimeout(timeoutId);
                                warningTooltip.style.opacity = '1';
                                timeoutId = setTimeout(function() {
                                    warningTooltip.style.opacity = '0';
                                }, 7000);
                            } else {
                                warningTooltip.style.opacity = '0';
                                clearTimeout(timeoutId);
                            }
                        });
                    }
                });
            </script>
        </div>
    </div>
