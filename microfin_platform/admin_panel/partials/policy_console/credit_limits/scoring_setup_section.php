<section class="policy-blueprint-card" id="policy-credit-limits-scoring">
    <div class="policy-blueprint-card-head">
        <div class="policy-blueprint-card-title">
            <h4>Scoring Setup</h4>
            <p class="text-muted">Keep the base score engine in Core Setup and open Lifecycle Eligibility only when you want to work on reassessment rules.</p>
        </div>
    </div>

    <div class="policy-blueprint-panel">
        <div class="policy-blueprint-panel-head">
            <div>
                <span class="policy-blueprint-panel-kicker">Core Setup</span>
                <h5>Scoring defaults</h5>
            </div>
        </div>

        <div class="policy-blueprint-grid policy-blueprint-grid--three">
            <label class="policy-field">
                <span class="policy-field-label">Starting Credit Score <?php echo $policy_console_help('Default score assigned before repayment behavior moves the borrower up or down.'); ?></span>
                <input type="number" class="form-control" name="pcc_core_starting_credit_score" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" value="<?php echo htmlspecialchars((string)($policy_console_core_setup['starting_credit_score'] ?? 540)); ?>">
            </label>

            <label class="policy-field">
                <span class="policy-field-label">Repayment Score Bonus <?php echo $policy_console_help('Points added after a successful repayment cycle.'); ?></span>
                <input type="number" class="form-control" name="pcc_core_repayment_score_bonus" min="0" max="1000" value="<?php echo htmlspecialchars((string)($policy_console_core_setup['repayment_score_bonus'] ?? 10)); ?>">
            </label>

            <label class="policy-field">
                <span class="policy-field-label">Late Payment Score Penalty <?php echo $policy_console_help('Points deducted when late-payment behavior is recorded.'); ?></span>
                <input type="number" class="form-control" name="pcc_core_late_payment_score_penalty" min="0" max="1000" value="<?php echo htmlspecialchars((string)($policy_console_core_setup['late_payment_score_penalty'] ?? 15)); ?>">
            </label>
        </div>

        <div class="policy-blueprint-note">
            <strong>Balanced default scoring</strong>
            <span>New tenants start with ready-to-use scoring behavior, then adjust only the values that need to fit their operation.</span>
        </div>

        <div class="policy-blueprint-panel-actions">
            <button
                type="button"
                class="btn btn-outline"
                data-policy-toggle-panel="policy-lifecycle-panel"
                data-panel-open-label="Open Detailed Rules"
                data-panel-close-label="Close"
            >Open Detailed Rules</button>
        </div>
    </div>

    <div class="policy-blueprint-detail" id="policy-lifecycle-panel" hidden>
        <div class="policy-blueprint-detail-head">
            <div>
                <span class="policy-blueprint-panel-kicker">Detailed Rules</span>
                <h5>Lifecycle Eligibility</h5>
                <p class="text-muted">Use lifecycle toggles to define the conditions that make a borrower eligible for upgrade or downgrade review.</p>
            </div>
            <button
                type="button"
                class="btn btn-outline"
                data-policy-toggle-panel="policy-lifecycle-panel"
                data-panel-open-label="Open Detailed Rules"
                data-panel-close-label="Close"
            >Close</button>
        </div>

        <div class="policy-lifecycle-columns">
            <section class="policy-blueprint-panel">
                <div class="policy-blueprint-panel-head">
                    <div>
                        <h5>Upgrade Eligibility</h5>
                        <p class="text-muted">Enable only the conditions that should count toward upgrade candidacy.</p>
                    </div>
                </div>

                <div class="policy-lifecycle-rule-grid">
                    <?php $upgrade_success_cycles = $policy_console_upgrade_rules['successful_repayment_cycles'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($upgrade_success_cycles['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>Successful Repayment Cycles</strong>
                                <?php echo $policy_console_help('Borrower must complete at least this many successful repayment cycles before upgrade candidacy can begin.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch">
                                <input
                                    type="hidden"
                                    name="pcc_upgrade_successful_repayment_enabled"
                                    value="<?php echo !empty($upgrade_success_cycles['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_upgrade_successful_repayment_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($upgrade_success_cycles['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_upgrade_successful_repayment_enabled"
                                    aria-pressed="<?php echo !empty($upgrade_success_cycles['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable Successful Repayment Cycles"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($upgrade_success_cycles['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">Cycles Required</span>
                                <input type="number" class="form-control" name="pcc_upgrade_successful_repayment_cycles" min="1" max="999" value="<?php echo htmlspecialchars((string)($upgrade_success_cycles['required_cycles'] ?? 3)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Increase</span>
                                <input type="number" class="form-control" name="pcc_upgrade_successful_repayment_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($upgrade_success_cycles['score_points'] ?? 5)); ?>">
                            </label>
                        </div>
                    </article>

                    <?php $upgrade_late_payments = $policy_console_upgrade_rules['maximum_late_payments_review'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($upgrade_late_payments['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>Maximum Late Payments Allowed Within Review Period</strong>
                                <?php echo $policy_console_help('Upgrade passes when late payments inside the selected review period stay within this maximum.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch">
                                <input
                                    type="hidden"
                                    name="pcc_upgrade_late_payments_enabled"
                                    value="<?php echo !empty($upgrade_late_payments['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_upgrade_late_payments_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($upgrade_late_payments['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_upgrade_late_payments_enabled"
                                    aria-pressed="<?php echo !empty($upgrade_late_payments['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable Maximum Late Payments Allowed Within Review Period"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($upgrade_late_payments['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--three">
                            <label class="policy-field">
                                <span class="policy-field-label">Maximum Allowed</span>
                                <input type="number" class="form-control" name="pcc_upgrade_late_payments_max" min="0" max="365" value="<?php echo htmlspecialchars((string)($upgrade_late_payments['maximum_allowed'] ?? 1)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">Review Period <?php echo $policy_console_help('Lookback window used to check recent borrower activity, for example last 30, 60, or 90 days.'); ?></span>
                                <input type="number" class="form-control" name="pcc_upgrade_late_payments_review_days" min="1" max="3650" value="<?php echo htmlspecialchars((string)($upgrade_late_payments['review_period_days'] ?? 90)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Increase</span>
                                <input type="number" class="form-control" name="pcc_upgrade_late_payments_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($upgrade_late_payments['score_points'] ?? 5)); ?>">
                            </label>
                        </div>
                    </article>

                    <?php $upgrade_no_overdue = $policy_console_upgrade_rules['no_active_overdue'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($upgrade_no_overdue['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>No Active Overdue</strong>
                                <?php echo $policy_console_help('Borrower must have no current overdue balance for this upgrade rule to pass.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch">
                                <input
                                    type="hidden"
                                    name="pcc_upgrade_no_active_overdue_enabled"
                                    value="<?php echo !empty($upgrade_no_overdue['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_upgrade_no_active_overdue_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($upgrade_no_overdue['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_upgrade_no_active_overdue_enabled"
                                    aria-pressed="<?php echo !empty($upgrade_no_overdue['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable No Active Overdue"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($upgrade_no_overdue['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">CS Increase</span>
                                <input type="number" class="form-control" name="pcc_upgrade_no_active_overdue_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($upgrade_no_overdue['score_points'] ?? 5)); ?>">
                            </label>
                        </div>
                    </article>
                </div>
            </section>

            <section class="policy-blueprint-panel">
                <div class="policy-blueprint-panel-head">
                    <div>
                        <h5>Downgrade Eligibility</h5>
                        <p class="text-muted">Enable only the conditions that should trigger downward reassessment.</p>
                    </div>
                </div>

                <div class="policy-lifecycle-rule-grid">
                    <?php $downgrade_late_payments = $policy_console_downgrade_rules['late_payments_review'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($downgrade_late_payments['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>Late Payments Count Within Review Period</strong>
                                <?php echo $policy_console_help('Downgrade review is triggered when late payments reach at least this count within the selected review period.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch">
                                <input
                                    type="hidden"
                                    name="pcc_downgrade_late_payments_enabled"
                                    value="<?php echo !empty($downgrade_late_payments['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_downgrade_late_payments_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($downgrade_late_payments['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_downgrade_late_payments_enabled"
                                    aria-pressed="<?php echo !empty($downgrade_late_payments['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable Late Payments Count Within Review Period"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($downgrade_late_payments['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--three">
                            <label class="policy-field">
                                <span class="policy-field-label">Trigger At</span>
                                <input type="number" class="form-control" name="pcc_downgrade_late_payments_trigger" min="1" max="365" value="<?php echo htmlspecialchars((string)($downgrade_late_payments['trigger_count'] ?? 2)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">Review Period <?php echo $policy_console_help('Lookback window used to check recent borrower activity, for example last 30, 60, or 90 days.'); ?></span>
                                <input type="number" class="form-control" name="pcc_downgrade_late_payments_review_days" min="1" max="3650" value="<?php echo htmlspecialchars((string)($downgrade_late_payments['review_period_days'] ?? 90)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Deduction</span>
                                <input type="number" class="form-control" name="pcc_downgrade_late_payments_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($downgrade_late_payments['score_points'] ?? 12)); ?>">
                            </label>
                        </div>
                    </article>

                    <?php $downgrade_overdue = $policy_console_downgrade_rules['overdue_days_threshold'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($downgrade_overdue['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>Overdue Days Threshold</strong>
                                <?php echo $policy_console_help('This is different from grace period. It measures how long the borrower stays overdue after already becoming overdue.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch">
                                <input
                                    type="hidden"
                                    name="pcc_downgrade_overdue_days_enabled"
                                    value="<?php echo !empty($downgrade_overdue['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_downgrade_overdue_days_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($downgrade_overdue['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_downgrade_overdue_days_enabled"
                                    aria-pressed="<?php echo !empty($downgrade_overdue['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable Overdue Days Threshold"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($downgrade_overdue['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">Days Overdue</span>
                                <input type="number" class="form-control" name="pcc_downgrade_overdue_days_threshold" min="1" max="3650" value="<?php echo htmlspecialchars((string)($downgrade_overdue['days'] ?? 15)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Deduction</span>
                                <input type="number" class="form-control" name="pcc_downgrade_overdue_days_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($downgrade_overdue['score_points'] ?? 25)); ?>">
                            </label>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</section>
