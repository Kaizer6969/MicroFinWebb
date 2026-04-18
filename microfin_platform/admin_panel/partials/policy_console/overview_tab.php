<?php
$policy_console_income_label = !empty($credit_policy['eligibility']['allow_no_minimum_income'])
    ? 'No minimum income'
    : 'PHP ' . number_format((float)($credit_policy['eligibility']['min_monthly_income'] ?? 0), 2);
?>
<div class="policy-console-workspace">
    <div class="policy-console-story-card policy-console-story-card-hero">
        <div class="policy-console-story-copy">
            <span class="policy-console-eyebrow">Overview</span>
            <h3>Policy Console workspace</h3>
            <p class="text-muted">This overview copies the newer workspace flow into the admin panel while keeping the branding, spacing, and surface treatment native to the tenant-admin experience. The legacy tabs remain available in the sidebar so you can compare the rebuilt flow against the current live forms.</p>
        </div>
        <div class="policy-console-story-meta">
            <span class="policy-console-chip">New flow</span>
            <span class="policy-console-chip">Legacy comparison</span>
            <span class="policy-console-chip">No backend rewrite</span>
        </div>
    </div>

    <div class="policy-console-kpi-grid">
        <div class="policy-console-kpi-card">
            <span>Income Gate</span>
            <strong><?php echo htmlspecialchars($policy_console_income_label); ?></strong>
            <small>Current borrower entry threshold</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>Approval Score</span>
            <strong><?php echo number_format((int)$credit_policy_approve_from); ?>+</strong>
            <small>Current approval candidate threshold</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>CI Mode</span>
            <strong><?php echo htmlspecialchars($credit_policy_ci_mode_label); ?></strong>
            <small>Current investigation expectation</small>
        </div>
        <div class="policy-console-kpi-card">
            <span>Max Credit Cap</span>
            <strong>PHP <?php echo number_format((float)($credit_policy['credit_limit']['max_credit_limit_cap'] ?? 0), 2); ?></strong>
            <small>Current top-end limit guardrail</small>
        </div>
    </div>

    <div class="policy-console-module-grid">
        <div class="policy-console-module-card">
            <div class="policy-console-card-head">
                <strong>Credit &amp; Limits</strong>
                <span class="policy-console-chip">Copied first</span>
            </div>
            <p class="text-muted">Scoring Setup, Score Band Matrix, and Limit Assignment now sit together in one cleaner flow.</p>
            <button type="button" class="btn btn-outline" data-credit-policy-nav-action="credit_limits">Open Credit &amp; Limits</button>
        </div>
        <div class="policy-console-module-card">
            <div class="policy-console-card-head">
                <strong>Decision Rules</strong>
                <span class="policy-console-chip">Adapted shell</span>
            </div>
            <p class="text-muted">Workflow, guardrails, CI rules, and access checks are being reframed into the newer module layout.</p>
            <button type="button" class="btn btn-outline" data-credit-policy-nav-action="decision_rules">Open Decision Rules</button>
        </div>
        <div class="policy-console-module-card">
            <div class="policy-console-card-head">
                <strong>Collections &amp; Safeguards</strong>
                <span class="policy-console-chip">Planned shell</span>
            </div>
            <p class="text-muted">Penalty and collections flow are separated from credit scoring in the rebuilt workspace.</p>
            <button type="button" class="btn btn-outline" data-credit-policy-nav-action="collections_safeguards">Open Collections &amp; Safeguards</button>
        </div>
        <div class="policy-console-module-card">
            <div class="policy-console-card-head">
                <strong>Compliance &amp; Documents</strong>
                <span class="policy-console-chip">Planned shell</span>
            </div>
            <p class="text-muted">Document and compliance ownership stay isolated from credit decisioning in the new flow.</p>
            <button type="button" class="btn btn-outline" data-credit-policy-nav-action="compliance_documents">Open Compliance &amp; Documents</button>
        </div>
    </div>

    <div class="policy-console-split-grid">
        <div class="policy-console-card">
            <div class="policy-console-card-head">
                <strong>What changed in the new flow</strong>
                <span class="policy-console-chip">UX direction</span>
            </div>
            <ul class="policy-console-list">
                <li>Policy work is grouped into clearer modules instead of one dense legacy form.</li>
                <li>Credit &amp; Limits follows the newer multi-section flow from the standalone workspace.</li>
                <li>Legacy tabs remain available so you can compare the rebuilt UI against the current live forms.</li>
            </ul>
        </div>
        <div class="policy-console-card">
            <div class="policy-console-card-head">
                <strong>What stays untouched</strong>
                <span class="policy-console-chip">Safe migration</span>
            </div>
            <ul class="policy-console-list">
                <li>Backend save handlers and live policy execution remain unchanged.</li>
                <li>Current tenant policy values still come from the real admin data, not prototype preset tenants.</li>
                <li>Old sidebar entries and old content remain available for comparison.</li>
            </ul>
        </div>
    </div>
</div>
