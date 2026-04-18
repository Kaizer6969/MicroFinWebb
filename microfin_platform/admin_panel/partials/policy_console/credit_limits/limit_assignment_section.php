<?php
$policy_console_limit_assignment = isset($policy_console_limit_assignment) && is_array($policy_console_limit_assignment)
    ? $policy_console_limit_assignment
    : policy_console_limit_assignment_defaults();

$policy_console_score_steps = array_values(array_map('intval', (array)($policy_console_limit_assignment['score_change_steps'] ?? [5, 10, 15, 20])));
$policy_console_score_step_options = [5, 10, 15, 20];
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
            <span class="policy-field-label">Initial Limit Percentage of Monthly Income <?php echo $policy_console_help('Default onboarding percentage used only when the tenant has not set a custom initial limit policy.'); ?></span>
            <input type="number" class="form-control" name="pcc_limit_initial_percent_of_income" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['initial_limit_percent_of_income'] ?? 45)); ?>">
        </label>

        <label class="policy-field">
            <span class="policy-field-label">Default Lending Cap <?php echo $policy_console_help('Optional onboarding cap. Leave disabled if the tenant should start without a default lending-cap constraint.'); ?></span>
            <div class="policy-inline-toggle">
                <label class="toggle-switch">
                    <input type="hidden" name="pcc_limit_use_default_lending_cap" value="0">
                    <input type="checkbox" name="pcc_limit_use_default_lending_cap" value="1" <?php echo !empty($policy_console_limit_assignment['use_default_lending_cap']) ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                    <span class="toggle-label">Use cap</span>
                </label>
                <input type="number" class="form-control" name="pcc_limit_default_lending_cap_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['default_lending_cap_amount'] ?? 0)); ?>" placeholder="No default cap">
            </div>
        </label>
    </div>

    <div class="policy-blueprint-note">
        <strong>One-time onboarding logic</strong>
        <span>This applies only once during onboarding. After first assignment, limit changes should come from score movement, score-band growth logic, and limit adjustment rules.</span>
    </div>

    <div class="policy-blueprint-panel-actions" style="margin-top: 20px;">
        <button
            type="button"
            class="btn btn-outline"
            data-policy-toggle-panel="policy-limit-assignment-advanced-panel"
            data-panel-open-label="View Advanced Rules"
            data-panel-close-label="Close"
        >View Advanced Rules</button>
    </div>
</div>

<div class="policy-blueprint-detail" id="policy-limit-assignment-advanced-panel" hidden>
    <div class="policy-blueprint-detail-head">
        <div>
            <span class="policy-blueprint-panel-kicker">Advanced Rules</span>
            <h5>Post-onboarding growth behavior</h5>
            <p class="text-muted">These rules describe how score changes affect future credit limit movement after onboarding is complete.</p>
        </div>
        <button
            type="button"
            class="btn btn-outline"
            data-policy-toggle-panel="policy-limit-assignment-advanced-panel"
            data-panel-open-label="View Advanced Rules"
            data-panel-close-label="Close"
        >Close</button>
    </div>

    <div class="policy-blueprint-grid policy-blueprint-grid--two">
        <div class="policy-blueprint-note">
            <strong>Growth formula</strong>
            <span>Growth % = Base Growth + ((CS - Band Min) x Micro % per point)</span>
        </div>
        <div class="policy-blueprint-note">
            <strong>New limit formula</strong>
            <span>New Credit Limit = Current Limit x (1 + Growth %)</span>
        </div>
    </div>

    <div class="policy-blueprint-panel">
        <div class="policy-blueprint-panel-head">
            <div>
                <h5>Score adjustment sensitivity</h5>
                <p class="text-muted">Choose which score-change increments are recognized by the limit adjustment model.</p>
            </div>
        </div>

        <div class="policy-step-checkboxes">
            <?php foreach ($policy_console_score_step_options as $stepOption): ?>
                <label class="policy-step-option">
                    <input
                        type="checkbox"
                        name="pcc_limit_score_change_steps[]"
                        value="<?php echo $stepOption; ?>"
                        <?php echo in_array($stepOption, $policy_console_score_steps, true) ? 'checked' : ''; ?>
                    >
                    <span><?php echo htmlspecialchars('±' . $stepOption); ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="policy-blueprint-note">
            <strong>Default behavior</strong>
            <span>Lower bands can react more sharply to score movement, while higher bands rely more on base growth and use gentler micro sensitivity.</span>
        </div>
    </div>

    <div class="policy-blueprint-grid policy-blueprint-grid--two">
        <div class="policy-blueprint-panel">
            <div class="policy-blueprint-panel-head">
                <div>
                    <h5>Threshold behavior</h5>
                    <p class="text-muted">Control whether score changes wait for a minimum threshold or affect growth immediately.</p>
                </div>
            </div>

            <label class="toggle-switch">
                <input type="hidden" name="pcc_limit_apply_score_changes_immediately" value="0">
                <input type="checkbox" name="pcc_limit_apply_score_changes_immediately" value="1" <?php echo !empty($policy_console_limit_assignment['apply_score_changes_immediately']) ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
                <span class="toggle-label">Apply all score changes immediately</span>
            </label>

            <div class="policy-blueprint-note">
                <strong>No minimum threshold</strong>
                <span>When enabled, every allowed score change step affects the growth calculation as soon as it occurs.</span>
            </div>
        </div>

        <div class="policy-blueprint-panel">
            <div class="policy-blueprint-panel-head">
                <div>
                    <h5>Behavior principles</h5>
                    <p class="text-muted">Keep the scoring-to-limit behavior readable for tenants before deeper automation is introduced.</p>
                </div>
            </div>

            <div class="policy-rule-stack">
                <div class="policy-blueprint-note">
                    <strong>Lower bands</strong>
                    <span>Higher sensitivity to score changes.</span>
                </div>
                <div class="policy-blueprint-note">
                    <strong>Higher bands</strong>
                    <span>Higher base growth with lower sensitivity.</span>
                </div>
                <div class="policy-blueprint-note">
                    <strong>Premium tier</strong>
                    <span>No hard top band cap in product logic intent, but the growth slope remains controlled.</span>
                </div>
            </div>
        </div>
    </div>
</div>
