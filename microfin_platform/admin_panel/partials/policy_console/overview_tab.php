<?php
$policy_console_credit_limits_overview = isset($policy_console_credit_limits) && is_array($policy_console_credit_limits)
    ? $policy_console_credit_limits
    : policy_console_credit_limits_system_defaults();
$policy_console_decision_overview = isset($policy_console_decision_rules) && is_array($policy_console_decision_rules)
    ? $policy_console_decision_rules
    : policy_console_decision_rules_system_defaults();
$policy_console_compliance_overview = isset($policy_console_compliance_documents) && is_array($policy_console_compliance_documents)
    ? $policy_console_compliance_documents
    : policy_console_compliance_documents_system_defaults();

$policy_console_overview_workflow_labels = [
    'automatic' => 'Automatic',
    'semi_automatic' => 'Semi-automatic',
    'manual' => 'Manual',
];

$policy_console_required_categories = count(array_filter(
    (array)($policy_console_compliance_overview['document_requirements'] ?? []),
    static fn(array $row): bool => ($row['requirement'] ?? 'not_needed') === 'required'
));
?>
<div class="policy-compact-stack">
    <section class="policy-compact-card">
        <div class="policy-compact-card-head">
            <div class="policy-compact-card-title">
                <h3>Overview</h3>
                <p class="text-muted">This page is read-only. It summarizes the tenant configs already saved by the active Policy Console workspaces.</p>
            </div>
        </div>

        <div class="policy-metric-grid">
            <div class="policy-metric-card">
                <span>Starting Credit Score</span>
                <strong><?php echo number_format((int)($policy_console_credit_limits_overview['scoring_setup']['core']['starting_credit_score'] ?? 0)); ?></strong>
                <small>Current starting-score fallback in Credit &amp; Limits.</small>
            </div>
            <div class="policy-metric-card">
                <span>Decision Workflow</span>
                <strong><?php echo htmlspecialchars($policy_console_overview_workflow_labels[$policy_console_decision_overview['workflow']['approval_mode'] ?? 'semi_automatic'] ?? 'Semi-automatic'); ?></strong>
                <small>Current approval routing posture.</small>
            </div>
            <div class="policy-metric-card">
                <span>Guarantor Trigger</span>
                <strong>PHP <?php echo number_format((float)($policy_console_decision_overview['decision_rules']['borrower_safeguards']['guarantor_required_above_amount'] ?? 0), 2); ?></strong>
                <small>Borrower safeguard trigger inside Decision Rules.</small>
            </div>
        </div>
    </section>

    <section class="policy-compact-card">
        <div class="policy-console-module-grid">
            <div class="policy-console-module-card">
                <div class="policy-console-card-head">
                    <strong>Credit &amp; Limits</strong>
                    <span class="policy-console-chip"><?php echo number_format(count((array)($policy_console_credit_limits_overview['score_bands']['rows'] ?? []))); ?> bands</span>
                </div>
                <p class="text-muted">Scoring setup, lifecycle rules, score band matrix, and limit assignment defaults.</p>
                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="credit_limits">Open Credit &amp; Limits</button>
            </div>
            <div class="policy-console-module-card">
                <div class="policy-console-card-head">
                    <strong>Decision Rules</strong>
                    <span class="policy-console-chip"><?php echo number_format((int)($policy_console_decision_overview['decision_rules']['score_thresholds']['hard_approval_threshold'] ?? 0)); ?>+</span>
                </div>
                <p class="text-muted">Workflow mode, score guardrails, CI expectations, access rules, review overrides, and borrower safeguards.</p>
                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="decision_rules">Open Decision Rules</button>
            </div>
            <div class="policy-console-module-card">
                <div class="policy-console-card-head">
                    <strong>Compliance &amp; Documents</strong>
                    <span class="policy-console-chip"><?php echo number_format($policy_console_required_categories); ?> required</span>
                </div>
                <p class="text-muted">Document validity defaults and the Governance Matrix saved by document name.</p>
                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="compliance_documents">Open Compliance &amp; Documents</button>
            </div>
        </div>
    </section>
</div>
