<?php
$policy_console_credit_limit_rules = isset($credit_limit_rules) && is_array($credit_limit_rules) ? $credit_limit_rules : credit_limit_rule_defaults();
$policy_console_approval_mode = (string)($policy_console_credit_limit_rules['workflow']['approval_mode'] ?? 'semi');
$policy_console_approval_mode_label = $policy_console_approval_mode === 'auto'
    ? 'Automatic'
    : ($policy_console_approval_mode === 'manual' ? 'Manual' : 'Semi-Automatic');
?>
<div class="policy-console-workspace">
    <div class="policy-console-story-card policy-console-story-card-hero">
        <div class="policy-console-story-copy">
            <span class="policy-console-eyebrow">Decision Rules</span>
            <h3>Routing, hard thresholds, and investigation logic</h3>
            <p class="text-muted">This page mirrors the modular decision flow from the newer workspace while still reading from the current live policy values. It is UI-only for now, so edits here are part of the rebuilt experience and not a backend rewrite yet.</p>
        </div>
        <div class="policy-console-story-meta">
            <span class="policy-console-chip">Workflow view</span>
            <span class="policy-console-chip">Brand-adapted</span>
        </div>
    </div>

    <div class="policy-console-section-stack">
        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 1</span>
                    <h4>Workflow Configuration</h4>
                    <p class="text-muted">Keep one clear routing mode visible so the rest of the policy reads consistently.</p>
                </div>
                <span class="policy-console-chip">Core Setup</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-field-grid">
                    <label class="policy-console-field">
                        <span>Approval Workflow</span>
                        <select>
                            <option value="auto" <?php echo $policy_console_approval_mode === 'auto' ? 'selected' : ''; ?>>Automatic</option>
                            <option value="semi" <?php echo $policy_console_approval_mode === 'semi' ? 'selected' : ''; ?>>Semi-Automatic (Recommended)</option>
                            <option value="manual" <?php echo $policy_console_approval_mode === 'manual' ? 'selected' : ''; ?>>Manual</option>
                        </select>
                        <small>Current live workflow mode: <?php echo htmlspecialchars($policy_console_approval_mode_label); ?>.</small>
                    </label>
                </div>
            </div>
        </section>

        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 2</span>
                    <h4>Decision Guardrails</h4>
                    <p class="text-muted">Hard score floors and approval thresholds stay visible before deeper routing or CI logic is applied.</p>
                </div>
                <span class="policy-console-chip">Thresholds</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-field-grid">
                    <label class="policy-console-field">
                        <span>Auto-Reject Floor</span>
                        <input type="number" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" value="<?php echo htmlspecialchars((string)$credit_policy_reject_below); ?>">
                        <small>Scores below this level should be blocked before deeper checks.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Manual Review Starts</span>
                        <input type="number" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" value="<?php echo htmlspecialchars((string)$credit_policy_conditional_from); ?>">
                        <small>Beginning of the review band.</small>
                    </label>
                    <label class="policy-console-field">
                        <span>Approval Candidate Starts</span>
                        <input type="number" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" value="<?php echo htmlspecialchars((string)$credit_policy_approve_from); ?>">
                        <small>Scores above this line become approval candidates.</small>
                    </label>
                </div>
            </div>
        </section>

        <section class="policy-console-section-card">
            <div class="policy-console-section-header">
                <div>
                    <span class="policy-console-eyebrow">Section 3</span>
                    <h4>Credit Investigation (CI)</h4>
                    <p class="text-muted">Manual verification stays separate from score bands so admins can see when investigation is mandatory.</p>
                </div>
                <span class="policy-console-chip">Investigation</span>
            </div>
            <div class="policy-console-section-body">
                <div class="policy-console-split-grid">
                    <div class="policy-console-card">
                        <div class="policy-console-card-head">
                            <strong>Current CI snapshot</strong>
                            <span class="policy-console-chip">Live values</span>
                        </div>
                        <div class="policy-console-stat-list">
                            <div><span>Mode</span><strong><?php echo htmlspecialchars($credit_policy_ci_mode_label); ?></strong></div>
                            <div><span>Auto-approve CI values</span><strong><?php echo number_format((int)$credit_policy_auto_ci_count); ?></strong></div>
                            <div><span>Review CI values</span><strong><?php echo number_format((int)$credit_policy_review_ci_count); ?></strong></div>
                        </div>
                    </div>
                    <div class="policy-console-card">
                        <div class="policy-console-card-head">
                            <strong>Product checks</strong>
                            <span class="policy-console-chip">Current engine</span>
                        </div>
                        <ul class="policy-console-list">
                            <li>Use product minimum score: <?php echo !empty($credit_policy['product_checks']['use_product_minimum_credit_score']) ? 'Enabled' : 'Disabled'; ?></li>
                            <li>Use product minimum amount: <?php echo !empty($credit_policy['product_checks']['use_product_min_amount']) ? 'Enabled' : 'Disabled'; ?></li>
                            <li>Use product maximum amount: <?php echo !empty($credit_policy['product_checks']['use_product_max_amount']) ? 'Enabled' : 'Disabled'; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
