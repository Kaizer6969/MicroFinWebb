<?php
$credit_limit_rules_safe = isset($credit_limit_rules) && is_array($credit_limit_rules) ? $credit_limit_rules : credit_limit_rule_defaults();

$policy_console_score_rows = [
    [
        'label' => 'At-Risk',
        'from' => 0,
        'to' => max(0, (int)$credit_policy_not_recommended_from - 1),
        'summary' => 'Below the fair-entry zone.',
    ],
    [
        'label' => 'Fair',
        'from' => (int)$credit_policy_not_recommended_from,
        'to' => (int)$credit_policy_not_recommended_end,
        'summary' => 'Needs tighter review or lower multipliers.',
    ],
    [
        'label' => 'Standard',
        'from' => (int)$credit_policy_conditional_from,
        'to' => (int)$credit_policy_conditional_end,
        'summary' => 'Usually sits inside the manual review band.',
    ],
    [
        'label' => 'Good',
        'from' => (int)$credit_policy_recommended_from,
        'to' => (int)$credit_policy_recommended_end,
        'summary' => 'Healthy approval candidate zone.',
    ],
    [
        'label' => 'High',
        'from' => (int)$credit_policy_highly_recommended_from,
        'to' => (int)$credit_policy_score_ceiling,
        'summary' => 'Top-end score band.',
    ],
];

$policy_console_base_limit = (float)($credit_limit_rules_safe['initial_limits']['base_limit_default'] ?? 5000);
$policy_console_limit_increase_type = (string)($credit_limit_rules_safe['increase_rules']['increase_type'] ?? 'percentage');
$policy_console_limit_increase_value = (float)($credit_limit_rules_safe['increase_rules']['increase_value'] ?? 20);
$policy_console_limit_max = (float)($credit_limit_rules_safe['increase_rules']['absolute_max_limit'] ?? 50000);
$policy_console_limit_custom_categories = count((array)($credit_limit_rules_safe['initial_limits']['custom_categories'] ?? []));
?>
<div class="policy-console-workspace">
    <div class="policy-console-story-card policy-console-story-card-hero">
        <div class="policy-console-story-copy">
            <span class="policy-console-eyebrow">Credit &amp; Limits</span>
            <h3>Scoring setup, band logic, and limit assignment in one workspace</h3>
            <p class="text-muted">This page mirrors the newer `credit_policy` flow and adapts it to the admin panel’s tenant-branded styling. Inputs are UI-focused for now, so the backend and save behavior stay untouched while the experience is being rebuilt.</p>
        </div>
        <div class="policy-console-story-meta">
            <span class="policy-console-chip">Copied flow</span>
            <span class="policy-console-chip">Brand-adapted</span>
            <span class="policy-console-chip">Backend untouched</span>
        </div>
    </div>

    <div class="policy-console-kpi-grid">
        <div class="policy-console-kpi-card">
            <span>Starting Credit Score</span>
            <strong><?php echo number_format((int)($credit_policy['score_thresholds']['new_client_default_score'] ?? 500)); ?></strong>
            <small>Current live default for new borrowers</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>Approval Candidate From</span>
            <strong><?php echo number_format((int)$credit_policy_approve_from); ?>+</strong>
            <small>Approval threshold currently used by the live engine</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>Base Limit Default</span>
            <strong>PHP <?php echo number_format($policy_console_base_limit, 2); ?></strong>
            <small>Current starting point from limit rules</small>
        </div>
    </div>

    <div class="policy-console-section-stack">
        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 1</span>
                    <h4>Scoring Setup</h4>
                    <p class="text-muted">Keep the scoring core visible first, then open Detailed Rules for lifecycle-related flow later.</p>
                </div>
                <span class="policy-console-chip">Core Setup</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-field-grid">
                    <label class="policy-console-field">
                        <span>Starting Credit Score</span>
                        <input type="number" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" value="<?php echo htmlspecialchars((string)($credit_policy['score_thresholds']['new_client_default_score'] ?? 500)); ?>">
                        <small>Default score assigned before repayment behavior starts moving it.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Repayment Score Bonus</span>
                        <input type="number" min="0" max="200" value="<?php echo htmlspecialchars((string)($credit_policy['score_growth']['completed_loan_bonus'] ?? 30)); ?>">
                        <small>How much the score grows after a completed successful loan cycle.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Late Payment Score Penalty</span>
                        <input type="number" min="0" max="200" value="<?php echo htmlspecialchars((string)($credit_policy['score_growth']['late_payment_penalty'] ?? 18)); ?>">
                        <small>Deduction applied when late-payment behavior is recorded.</small>
                    </label>
                </div>

                <details class="policy-console-detail">
                    <summary>Open Detailed Rules</summary>
                    <div class="policy-console-detail-body">
                        <div class="policy-console-banner">
                            <strong>Lifecycle Eligibility</strong>
                            <p class="text-muted">This space is reserved for the newer lifecycle reassessment flow. It stays in the new UI path and does not rewrite the old live upgrade engine yet.</p>
                        </div>
                        <div class="policy-console-split-grid">
                            <div class="policy-console-card">
                                <div class="policy-console-card-head">
                                    <strong>Upgrade direction</strong>
                                    <span class="policy-console-chip">Preview</span>
                                </div>
                                <ul class="policy-console-list">
                                    <li>Successful repayment cycles can become visible upgrade criteria.</li>
                                    <li>Late-payment tolerance can stay strict before a borrower becomes upgrade-eligible.</li>
                                    <li>Lifecycle details remain UI-only in this pass.</li>
                                </ul>
                            </div>
                            <div class="policy-console-card">
                                <div class="policy-console-card-head">
                                    <strong>Downgrade direction</strong>
                                    <span class="policy-console-chip">Preview</span>
                                </div>
                                <ul class="policy-console-list">
                                    <li>Late-payment triggers and overdue duration remain the clearest downgrade signals.</li>
                                    <li>Old backend downgrade execution is not being replaced in this pass.</li>
                                    <li>Use the legacy tabs in the sidebar to compare current live forms.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        </section>

        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 2</span>
                    <h4>Score Band Matrix</h4>
                    <p class="text-muted">The newer flow turns score bands into a clearer matrix instead of spreading thresholds across dense forms.</p>
                </div>
                <span class="policy-console-chip">Matrix View</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-table-wrap">
                    <table class="policy-console-table">
                        <thead>
                            <tr>
                                <th>Band</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Interpretation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($policy_console_score_rows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['label']); ?></td>
                                    <td><?php echo number_format((int)$row['from']); ?></td>
                                    <td><?php echo number_format((int)$row['to']); ?></td>
                                    <td><?php echo htmlspecialchars($row['summary']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="policy-console-split-grid">
                    <div class="policy-console-card">
                        <div class="policy-console-card-head">
                            <strong>Decision routing snapshot</strong>
                            <span class="policy-console-chip">Live values</span>
                        </div>
                        <div class="policy-console-stat-list">
                            <div><span>Reject below</span><strong><?php echo number_format((int)$credit_policy_reject_below); ?></strong></div>
                            <div><span>Manual review starts</span><strong><?php echo number_format((int)$credit_policy_conditional_from); ?></strong></div>
                            <div><span>Approval candidate starts</span><strong><?php echo number_format((int)$credit_policy_approve_from); ?></strong></div>
                        </div>
                    </div>

                    <div class="policy-console-card">
                        <div class="policy-console-card-head">
                            <strong>Validation guard</strong>
                            <span class="policy-console-chip">UI validation</span>
                        </div>
                        <p class="text-muted">Band ordering is kept ascending in the new flow. When the edit behavior is wired later, ranges should stay sequential and non-overlapping.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 3</span>
                    <h4>Limit Assignment</h4>
                    <p class="text-muted">This part mirrors the newer flow by separating the initial limit basis from the detailed multiplier rules.</p>
                </div>
                <span class="policy-console-chip">Calculation Flow</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-field-grid">
                    <label class="policy-console-field">
                        <span>Base Limit Default</span>
                        <input type="number" min="0" step="0.01" value="<?php echo htmlspecialchars((string)$policy_console_base_limit); ?>">
                        <small>Starting fallback amount for new borrower limit assignments.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Income Multiplier</span>
                        <input type="number" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['income_multiplier'] ?? 4)); ?>">
                        <small>Main multiplier applied to income before band adjustments.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Maximum Credit Cap</span>
                        <input type="number" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 200000)); ?>">
                        <small>Upper ceiling for assigned limits.</small>
                    </label>
                </div>

                <details class="policy-console-detail" open>
                    <summary>Open Calculation Details</summary>
                    <div class="policy-console-detail-body">
                        <div class="policy-console-kpi-grid">
                            <div class="policy-console-kpi-card">
                                <span>Good / High Multiplier</span>
                                <strong><?php echo number_format((float)($credit_policy['credit_limit']['approve_band_multiplier'] ?? 1.10), 2); ?>x</strong>
                                <small>Best-performing band multiplier</small>
                            </div>
                            <div class="policy-console-kpi-card">
                                <span>Review Band Multiplier</span>
                                <strong><?php echo number_format((float)($credit_policy['credit_limit']['review_band_multiplier'] ?? 1.00), 2); ?>x</strong>
                                <small>Used for borderline review scores</small>
                            </div>
                            <div class="policy-console-kpi-card">
                                <span>Fair / At-Risk Floor</span>
                                <strong><?php echo number_format((float)($credit_policy['credit_limit']['fair_band_multiplier'] ?? 0.75), 2); ?>x</strong>
                                <small>Lower-band restraint multiplier</small>
                            </div>
                        </div>

                        <div class="policy-console-split-grid">
                            <div class="policy-console-card">
                                <div class="policy-console-card-head">
                                    <strong>Upgrade path snapshot</strong>
                                    <span class="policy-console-chip">Current live rules</span>
                                </div>
                                <div class="policy-console-stat-list">
                                    <div><span>Completed loans required</span><strong><?php echo number_format((int)($credit_limit_rules_safe['upgrade_eligibility']['min_completed_loans'] ?? 2)); ?></strong></div>
                                    <div><span>Late payments allowed</span><strong><?php echo number_format((int)($credit_limit_rules_safe['upgrade_eligibility']['max_allowed_late_payments'] ?? 0)); ?></strong></div>
                                    <div><span>Increase rule</span><strong><?php echo htmlspecialchars(ucfirst($policy_console_limit_increase_type)); ?> <?php echo number_format($policy_console_limit_increase_value, $policy_console_limit_increase_type === 'fixed' ? 2 : 0); ?><?php echo $policy_console_limit_increase_type === 'percentage' ? '%' : ''; ?></strong></div>
                                </div>
                            </div>

                            <div class="policy-console-card">
                                <div class="policy-console-card-head">
                                    <strong>Validation guard</strong>
                                    <span class="policy-console-chip">UI validation</span>
                                </div>
                                <ul class="policy-console-list">
                                    <li>Base limit and cap values should never drop below zero.</li>
                                    <li>Multiplier inputs should stay numeric and positive.</li>
                                    <li>Custom category overrides currently tracked: <?php echo number_format($policy_console_limit_custom_categories); ?>.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        </section>
    </div>
</div>
